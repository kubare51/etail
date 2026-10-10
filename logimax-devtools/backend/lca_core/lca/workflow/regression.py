"""Regression Risk Scorer -- per-file risk from churn + complexity + context.

For each file in a PR/branch, calculates regression risk based on:
  1. Git churn (how often it changes)
  2. Author diversity (many authors = more risk)
  3. Complexity (cyclomatic complexity)
  4. Financial module flag
  5. Shared model/helper flag

Score: 0-100 with CRITICAL/HIGH/MEDIUM/LOW classification.

Ported from standalone regression_risk.py into LCA v2 package.
"""

from __future__ import annotations

import os
import re
import subprocess
from datetime import datetime, timedelta
from pathlib import Path


# ---- Git Helpers -----------------------------------------------------

def get_branch_files(project_root: Path, base: str = 'PRODUCTION') -> list[str]:
    """Get changed files in current branch vs base."""
    try:
        result = subprocess.run(
            ['git', 'diff', '--name-only', base],
            capture_output=True, text=True, cwd=str(project_root), timeout=15,
        )
        return [f.strip() for f in result.stdout.strip().split('\n') if f.strip()]
    except Exception:
        return []


def get_file_churn(project_root: Path, filepath: str, months: int = 3) -> int:
    """Get change count for a file in recent months."""
    since = (datetime.now() - timedelta(days=months * 30)).strftime('%Y-%m-%d')
    try:
        result = subprocess.run(
            ['git', 'log', f'--since={since}', '--oneline', '--', filepath],
            capture_output=True, text=True, cwd=str(project_root), timeout=10,
        )
        return len([l for l in result.stdout.strip().split('\n') if l.strip()])
    except Exception:
        return 0


def get_file_authors(project_root: Path, filepath: str, months: int = 3) -> int:
    """Get unique author count for a file."""
    since = (datetime.now() - timedelta(days=months * 30)).strftime('%Y-%m-%d')
    try:
        result = subprocess.run(
            ['git', 'log', f'--since={since}', '--format=%an', '--', filepath],
            capture_output=True, text=True, cwd=str(project_root), timeout=10,
        )
        authors = set(l.strip() for l in result.stdout.strip().split('\n') if l.strip())
        return len(authors)
    except Exception:
        return 0


# ---- Complexity Helper -----------------------------------------------

def get_file_complexity(filepath: Path) -> tuple[int, str]:
    """Get worst complexity for a file using regex-based CC estimation."""
    try:
        content = filepath.read_text(encoding='utf-8', errors='ignore')
    except Exception:
        return 0, '?'

    worst_cc = 0
    func_re = re.compile(r'(?:public|private|protected)?\s*function\s+(\w+)\s*\(', re.IGNORECASE)

    for match in func_re.finditer(content):
        start = match.start()
        remaining = content[start:start + 5000]
        cc = 1
        for kw in ['if(', 'if (', 'elseif', 'else if', 'case ', '&&', '||',
                   'foreach', 'for(', 'for (', 'while(', 'while (', 'catch']:
            cc += remaining.count(kw)
        worst_cc = max(worst_cc, cc)

    if worst_cc >= 50:
        rating = 'F'
    elif worst_cc >= 30:
        rating = 'D'
    elif worst_cc >= 20:
        rating = 'C'
    elif worst_cc >= 10:
        rating = 'B'
    else:
        rating = 'A'

    return worst_cc, rating


# ---- Risk Calculator -------------------------------------------------

def _is_financial(filepath: str) -> bool:
    fl = filepath.lower()
    return any(m in fl for m in ['billing', 'estimation', 'payment', 'purchase', 'scheme'])


def _is_shared(filepath: str) -> bool:
    fl = filepath.lower()
    return any(p in fl for p in ['common_model', 'auth_model', '/helpers/', '/config/', 'access_right'])


def calculate_risk(churn: int, authors: int, complexity: int,
                   is_financial: bool, is_shared: bool) -> int:
    """Calculate 0-100 regression risk score."""
    score = 0

    # Churn factor (0-30)
    if churn >= 30:
        score += 30
    elif churn >= 15:
        score += 20
    elif churn >= 8:
        score += 10
    elif churn >= 3:
        score += 5

    # Author diversity (0-15)
    if authors >= 5:
        score += 15
    elif authors >= 3:
        score += 10
    elif authors >= 2:
        score += 5

    # Complexity (0-25)
    if complexity >= 100:
        score += 25
    elif complexity >= 50:
        score += 20
    elif complexity >= 20:
        score += 15
    elif complexity >= 10:
        score += 10

    # Financial module (0-15)
    if is_financial:
        score += 15

    # Shared model/helper (0-15)
    if is_shared:
        score += 15

    return min(100, score)


def classify_risk(score: int) -> tuple[str, str]:
    if score >= 70:
        return 'CRITICAL', 'CRITICAL'
    if score >= 50:
        return 'HIGH', 'HIGH'
    if score >= 30:
        return 'MEDIUM', 'MEDIUM'
    return 'LOW', 'LOW'


# ---- Main Analysis ---------------------------------------------------

def analyze_regression_risk(
    project_root: Path,
    files: list[str],
    months: int = 3,
) -> list[dict]:
    """Analyze regression risk for a list of files.

    Args:
        project_root: Project root path
        files: List of file paths (relative to project root)
        months: Lookback period for churn

    Returns:
        List of dicts with file, risk_score, risk_label, etc.
    """
    # Filter to PHP/JS only
    files = [f for f in files if f.endswith('.php') or f.endswith('.js')]

    results = []
    for filepath in files:
        full_path = project_root / filepath
        churn = get_file_churn(project_root, filepath, months)
        authors = get_file_authors(project_root, filepath, months)
        complexity, rating = get_file_complexity(full_path) if full_path.exists() else (0, '?')
        financial = _is_financial(filepath)
        shared = _is_shared(filepath)
        risk_score = calculate_risk(churn, authors, complexity, financial, shared)
        risk_label, _ = classify_risk(risk_score)

        results.append({
            'file': filepath,
            'churn': churn,
            'authors': authors,
            'complexity': complexity,
            'rating': rating,
            'financial': financial,
            'shared': shared,
            'risk_score': risk_score,
            'risk_label': risk_label,
        })

    return results


def generate_risk_report(results: list[dict]) -> str:
    """Generate markdown risk report."""
    total = len(results)
    critical = sum(1 for r in results if r['risk_label'] == 'CRITICAL')
    high = sum(1 for r in results if r['risk_label'] == 'HIGH')
    avg_risk = sum(r['risk_score'] for r in results) / max(total, 1)
    overall_label, _ = classify_risk(int(avg_risk))

    lines = [
        '# Regression Risk Report', '',
        f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}', '',
        '| Metric | Value |', '|---|---|',
        f'| **Files analyzed** | {total} |',
        f'| **Critical risk** | {critical} |',
        f'| **High risk** | {high} |',
        f'| **Overall risk** | {overall_label} ({avg_risk:.0f}/100) |', '',
    ]

    # Reviewer recommendation
    if critical > 0 or avg_risk >= 50:
        lines.append('> **Recommendation:** Requires **2 reviewers** including TL')
    elif high > 0:
        lines.append('> **Recommendation:** Requires **TL review**')
    else:
        lines.append('> **Recommendation:** Standard peer review')
    lines.append('')

    # Per-file table
    lines.extend(['## File-Level Risk', '',
                  '| File | Churn | Authors | CC | Financial | Shared | Risk | Score |',
                  '|---|---|---|---|---|---|---|---|'])

    for r in sorted(results, key=lambda x: x['risk_score'], reverse=True):
        basename = os.path.basename(r['file'])
        financial = 'Yes' if r['financial'] else ''
        shared = 'Yes' if r['shared'] else ''
        lines.append(
            f'| `{basename}` | {r["churn"]} | {r["authors"]} | '
            f'{r["complexity"]} ({r["rating"]}) | {financial} | {shared} | '
            f'{r["risk_label"]} | **{r["risk_score"]}** |'
        )

    lines.extend(['', '---', '*Generated by LCA v2*'])
    return '\n'.join(lines)
