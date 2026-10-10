"""Health Dashboard -- unified 0-100 score from all analysis tools.

Computes a weighted health score combining:
  - Complexity (30%)   : avg cyclomatic complexity + high-CC function ratio
  - Security (25%)     : security findings per file
  - Dead Code (15%)    : percentage of uncalled functions (needs index)
  - N+1 Queries (15%)  : N+1 patterns per controller/model
  - Hardcoded (10%)    : hardcoded values per file
  - Code Smells (5%)   : structural anti-patterns (needs index)

Dimensions that can't be measured are marked N/A with weight
redistributed to measured dimensions. Confidence % shows how much
of the score is backed by actual data.

Each sub-score is 0-100. Final grade: A (90+), B (75+), C (60+), D (40+), F (<40).
"""

from __future__ import annotations

import os
import re
from dataclasses import dataclass, field
from pathlib import Path
from datetime import datetime
import json


@dataclass
class SubScore:
    """A sub-component of the health score."""
    name: str
    score: float          # 0-100, or -1 for N/A
    weight: float         # 0.0-1.0 (original weight)
    detail: str           # Human-readable explanation
    findings_count: int = 0
    measured: bool = True  # False = N/A, not measured

    @property
    def effective_score(self) -> float:
        """Score used in calculation (N/A = excluded)."""
        return self.score if self.measured else 0.0


@dataclass
class HealthReport:
    """Complete health report for a module or project."""
    target: str                           # Module name or "project"
    score: float = 0.0                    # 0-100 weighted score
    grade: str = "?"                      # A, B, C, D, F
    confidence: float = 0.0               # 0-100% of score backed by data
    sub_scores: list[SubScore] = field(default_factory=list)
    timestamp: str = field(default_factory=lambda: datetime.now().isoformat())
    files_analyzed: int = 0

    def to_dict(self) -> dict:
        return {
            "target": self.target,
            "score": round(self.score, 1),
            "grade": self.grade,
            "confidence": round(self.confidence, 1),
            "timestamp": self.timestamp,
            "files_analyzed": self.files_analyzed,
            "sub_scores": [
                {
                    "name": s.name,
                    "score": round(s.score, 1) if s.measured else None,
                    "weight": s.weight,
                    "weighted": round(s.score * s.weight, 1) if s.measured else None,
                    "detail": s.detail,
                    "findings_count": s.findings_count,
                    "measured": s.measured,
                }
                for s in self.sub_scores
            ],
        }


def _grade_from_score(score: float) -> str:
    """Convert numeric score to letter grade."""
    if score >= 90:
        return "A"
    elif score >= 75:
        return "B"
    elif score >= 60:
        return "C"
    elif score >= 40:
        return "D"
    return "F"


def _complexity_score(
    avg_cc: float,
    high_cc_count: int,
    total_functions: int,
) -> SubScore:
    """Score based on cyclomatic complexity metrics."""
    if total_functions == 0:
        return SubScore("Complexity", 0, 0.30, "No functions analyzed", 0, measured=False)

    avg_penalty = min(avg_cc * 5, 50)
    ratio = high_cc_count / total_functions
    ratio_penalty = min(ratio * 200, 50)
    score = max(0, 100 - avg_penalty - ratio_penalty)
    detail = f"Avg CC: {avg_cc:.1f}, {high_cc_count}/{total_functions} with CC>15"
    return SubScore("Complexity", score, 0.30, detail, high_cc_count)


def _security_score(
    findings_count: int,
    critical_count: int,
    files_scanned: int,
) -> SubScore:
    """Score based on security scan findings."""
    if files_scanned == 0:
        return SubScore("Security", 0, 0.25, "No files scanned", 0, measured=False)

    critical_penalty = critical_count * 15
    other_penalty = (findings_count - critical_count) * 3
    score = max(0, 100 - critical_penalty - other_penalty)
    detail = f"{findings_count} findings ({critical_count} critical) in {files_scanned} files"
    return SubScore("Security", score, 0.25, detail, findings_count)


def _dead_code_score(
    dead_count: int,
    dead_total_functions: int,
) -> SubScore:
    """Score based on dead code percentage.

    Args:
        dead_count: Number of functions with zero callers
        dead_total_functions: Total functions analyzed for dead code
            (separate from complexity total; requires cross-file analysis).
            Pass 0 to mark as N/A.
    """
    if dead_total_functions == 0:
        return SubScore("Dead Code", 0, 0.15, "Needs fullstack index (lca index)", 0, measured=False)

    dead_pct = dead_count * 100 / dead_total_functions
    penalty = min(dead_pct * 5, 100)
    score = max(0, 100 - penalty)
    detail = f"{dead_count}/{dead_total_functions} uncalled ({dead_pct:.1f}%)"
    return SubScore("Dead Code", score, 0.15, detail, dead_count)


def _n1_score(
    n1_count: int,
    critical_count: int,
) -> SubScore:
    """Score based on N+1 query patterns."""
    # n1 is always measured (scan runs on raw files)
    if n1_count == 0:
        return SubScore("N+1 Queries", 100, 0.15, "No N+1 patterns found", 0)

    critical_penalty = critical_count * 20
    other_penalty = (n1_count - critical_count) * 5
    score = max(0, 100 - critical_penalty - other_penalty)
    detail = f"{n1_count} N+1 patterns ({critical_count} critical/nested)"
    return SubScore("N+1 Queries", score, 0.15, detail, n1_count)


def _hardcoded_score(
    hc_count: int,
    sql_count: int,
) -> SubScore:
    """Score based on hardcoded values."""
    # hardcoded is always measured
    if hc_count == 0:
        return SubScore("Hardcoded", 100, 0.10, "No hardcoded values found", 0)

    sql_penalty = sql_count * 3
    other_penalty = (hc_count - sql_count) * 1
    score = max(0, 100 - sql_penalty - other_penalty)
    detail = f"{hc_count} hardcoded values ({sql_count} SQL magic numbers)"
    return SubScore("Hardcoded", score, 0.10, detail, hc_count)


def _smell_score(
    smell_count: int,
    high_severity_count: int,
) -> SubScore:
    """Score based on code smells."""
    if smell_count == -1:  # sentinel: not measured
        return SubScore("Code Smells", 0, 0.05, "Needs fullstack index (lca index)", 0, measured=False)

    if smell_count == 0:
        return SubScore("Code Smells", 100, 0.05, "No code smells detected", 0)

    high_penalty = high_severity_count * 5
    other_penalty = (smell_count - high_severity_count) * 2
    score = max(0, 100 - high_penalty - other_penalty)
    detail = f"{smell_count} smells ({high_severity_count} high severity)"
    return SubScore("Code Smells", score, 0.05, detail, smell_count)


def compute_health(
    *,
    target: str = "project",
    files_analyzed: int = 0,
    # Complexity inputs
    avg_cc: float = 0,
    high_cc_count: int = 0,
    total_functions: int = 0,
    # Security inputs
    security_findings: int = 0,
    security_critical: int = 0,
    security_files: int = 0,
    # Dead code inputs (needs cross-file analysis; pass 0 for N/A)
    dead_functions: int = 0,
    dead_total_functions: int = 0,
    # N+1 inputs
    n1_count: int = 0,
    n1_critical: int = 0,
    # Hardcoded inputs
    hardcoded_count: int = 0,
    hardcoded_sql: int = 0,
    # Smell inputs
    smell_count: int = -1,  # -1 = not measured
    smell_high: int = 0,
) -> HealthReport:
    """Compute unified health score from all analysis inputs.

    Unmeasured dimensions (measured=False) are excluded from the score.
    Their weight is redistributed proportionally to measured dimensions.
    Confidence % shows what fraction of the total weight is backed by data.
    """

    sub_scores = [
        _complexity_score(avg_cc, high_cc_count, total_functions),
        _security_score(security_findings, security_critical, security_files),
        _dead_code_score(dead_functions, dead_total_functions),
        _n1_score(n1_count, n1_critical),
        _hardcoded_score(hardcoded_count, hardcoded_sql),
        _smell_score(smell_count, smell_high),
    ]

    # Calculate score only from measured dimensions, redistributing weight
    measured = [s for s in sub_scores if s.measured]
    total_measured_weight = sum(s.weight for s in measured)
    total_original_weight = sum(s.weight for s in sub_scores)

    if total_measured_weight > 0:
        # Redistribute: scale measured weights so they sum to 1.0
        total_score = sum(
            s.score * (s.weight / total_measured_weight)
            for s in measured
        )
    else:
        total_score = 0.0

    confidence = (total_measured_weight / total_original_weight * 100) if total_original_weight > 0 else 0

    report = HealthReport(
        target=target,
        score=total_score,
        grade=_grade_from_score(total_score),
        confidence=confidence,
        sub_scores=sub_scores,
        files_analyzed=files_analyzed,
    )

    return report


# -- Inline Scanners (regex-based, no tree-sitter needed) ---------------

def scan_complexity_inline(path: Path) -> tuple[float, int, int]:
    """Scan PHP files for complexity using regex. Returns (avg_cc, high_count, total)."""
    func_re = re.compile(
        r'(?:public|private|protected)?\s*function\s+(\w+)\s*\(', re.IGNORECASE,
    )
    cc_keywords = ['if(', 'if (', 'elseif', 'else if', 'case ',
                   '&&', '||', 'foreach', 'for(', 'for (', 'while(', 'while (',
                   'catch', '? ']

    files = _collect_php_files(path)
    total_cc = 0
    total_funcs = 0
    high_cc = 0

    for filepath in files:
        try:
            content = filepath.read_text(encoding='utf-8', errors='ignore')
        except Exception:
            continue

        for match in func_re.finditer(content):
            # Get ~next 5000 chars as approximate function body
            body = content[match.start():match.start() + 5000]
            cc = 1
            for kw in cc_keywords:
                cc += body.count(kw)
            total_cc += cc
            total_funcs += 1
            if cc > 15:
                high_cc += 1

    avg_cc = total_cc / max(total_funcs, 1)
    return avg_cc, high_cc, total_funcs


def scan_security_inline(path: Path) -> tuple[int, int, int]:
    """Scan PHP files for security issues using regex. Returns (findings, critical, files)."""
    rules = [
        # (pattern, severity, rule_id)
        (r'\$this\s*->\s*db\s*->\s*query\s*\(', 'HIGH', 'SEC-001'),  # raw SQL
        (r'\$_(POST|GET|REQUEST|COOKIE)\s*\[', 'HIGH', 'SEC-002'),  # raw superglobals
        (r'\beval\s*\(', 'CRITICAL', 'SEC-007'),  # eval
        (r'\bsystem\s*\(', 'CRITICAL', 'SEC-007'),  # system
        (r'\bexec\s*\(', 'CRITICAL', 'SEC-007'),  # exec
        (r'\bshell_exec\s*\(', 'CRITICAL', 'SEC-007'),  # shell_exec
        (r'\bpassthru\s*\(', 'CRITICAL', 'SEC-007'),  # passthru
    ]

    files = _collect_php_files(path)
    total_findings = 0
    critical_count = 0
    files_scanned = 0

    for filepath in files:
        try:
            content = filepath.read_text(encoding='utf-8', errors='ignore')
        except Exception:
            continue

        files_scanned += 1
        for pattern, severity, _ in rules:
            matches = re.findall(pattern, content)
            count = len(matches)
            total_findings += count
            if severity == 'CRITICAL':
                critical_count += count

    return total_findings, critical_count, files_scanned


def _collect_php_files(path: Path) -> list[Path]:
    """Collect .php files from path (file or directory)."""
    if path.is_file():
        return [path] if path.suffix == '.php' else []
    files = []
    for root, _, filenames in os.walk(str(path)):
        for fname in filenames:
            if fname.endswith('.php'):
                files.append(Path(root) / fname)
    return files


# -- Trend Tracking --------------------------------------------------------

def save_trend(report: HealthReport, history_dir: Path) -> Path:
    """Save a health report to the trend history."""
    history_dir.mkdir(parents=True, exist_ok=True)
    history_file = history_dir / f"{report.target}_history.json"
    history = []
    if history_file.exists():
        try:
            history = json.loads(history_file.read_text(encoding='utf-8'))
        except Exception:
            history = []

    history.append(report.to_dict())
    history = history[-100:]

    history_file.write_text(
        json.dumps(history, indent=2),
        encoding='utf-8',
    )
    return history_file


def load_trend(target: str, history_dir: Path) -> list[dict]:
    """Load trend history for a target."""
    history_file = history_dir / f"{target}_history.json"
    if not history_file.exists():
        return []
    try:
        return json.loads(history_file.read_text(encoding='utf-8'))
    except Exception:
        return []
