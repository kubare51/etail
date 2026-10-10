"""Hardcoded Value Scanner -- finds magic numbers, URLs, IPs, dates in PHP code.

Rules:
  HC-001: Magic number in WHERE clause
  HC-002: Magic number in condition (==, !=, >=, etc.)
  HC-003: Hardcoded URL
  HC-004: Hardcoded IP address
  HC-005: Hardcoded date
  HC-006: Hardcoded email
  HC-007: Status/type magic number in raw WHERE string
  HC-008: Hardcoded port number

Ported from standalone hardcoded_scanner.py into LCA v2 package.
"""

import re
from dataclasses import dataclass, field
from pathlib import Path
from collections import defaultdict


@dataclass
class HardcodedFinding:
    """A single hardcoded value finding."""
    rule_id: str            # e.g., "HC-001"
    rule_name: str          # e.g., "Magic Number in WHERE"
    category: str           # SQL, Logic, Config
    severity: str           # HIGH, MEDIUM, LOW
    file: str
    line: int
    function: str
    match: str              # The matched text
    description: str


@dataclass
class HardcodedScanResult:
    """Results of a hardcoded value scan."""
    findings: list[HardcodedFinding] = field(default_factory=list)
    files_scanned: int = 0

    @property
    def sql_count(self) -> int:
        return sum(1 for f in self.findings if f.category == "SQL")

    @property
    def logic_count(self) -> int:
        return sum(1 for f in self.findings if f.category == "Logic")

    @property
    def config_count(self) -> int:
        return sum(1 for f in self.findings if f.category == "Config")


# -- Rules ----------------------------------------------------------------

RULES = [
    {
        'id': 'HC-001',
        'name': 'Magic Number in WHERE',
        'category': 'SQL',
        'severity': 'MEDIUM',
        'pattern': r"(?:where|or_where|having)\s*\(\s*['\"][^'\"]+['\"]\s*,\s*(\d+)\s*\)",
        'description': 'Bare number in query WHERE -- no context for what it means',
        'exclude_values': {'0', '1'},
    },
    {
        'id': 'HC-002',
        'name': 'Magic Number in Condition',
        'category': 'Logic',
        'severity': 'LOW',
        'pattern': r"(?:==|!=|===|!==|>=|<=|>|<)\s*(\d{2,})",
        'description': 'Undocumented numeric comparison -- use named constant',
    },
    {
        'id': 'HC-003',
        'name': 'Hardcoded URL',
        'category': 'Config',
        'severity': 'HIGH',
        'pattern': r"['\"]https?://[^'\"]{10,}['\"]",
        'description': 'Hardcoded URL -- should use config or environment variable',
    },
    {
        'id': 'HC-004',
        'name': 'Hardcoded IP Address',
        'category': 'Config',
        'severity': 'HIGH',
        'pattern': r"['\"](\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})['\"]",
        'description': 'Hardcoded IP address -- use config',
        'exclude_values': {'127.0.0.1', '0.0.0.0', '255.255.255.255'},
    },
    {
        'id': 'HC-005',
        'name': 'Hardcoded Date',
        'category': 'Logic',
        'severity': 'MEDIUM',
        'pattern': r"['\"](\d{4}-\d{2}-\d{2})['\"]",
        'description': 'Hardcoded date -- likely a temporary fix that became permanent',
    },
    {
        'id': 'HC-006',
        'name': 'Hardcoded Email',
        'category': 'Config',
        'severity': 'MEDIUM',
        'pattern': r"['\"]([\\w.+-]+@[\\w-]+\\.[\\w.]+)['\"]",
        'description': 'Hardcoded email address -- use config',
    },
    {
        'id': 'HC-007',
        'name': 'Status Magic Number',
        'category': 'SQL',
        'severity': 'HIGH',
        'pattern': r"(?:where|or_where)\s*\(\s*['\"][^'\"]*(?:status|type|flag|is_|active)\s*[=<>!]+\s*(\d+)['\"]",
        'description': 'Status/type magic number in raw WHERE string',
    },
    {
        'id': 'HC-008',
        'name': 'Hardcoded Port Number',
        'category': 'Config',
        'severity': 'LOW',
        'pattern': r"['\"](?:port|PORT)['\"]?\s*(?:=>|=|:)\s*['\"]?(\d{2,5})['\"]?",
        'description': 'Hardcoded port -- use config',
        'exclude_values': {'80', '443', '3306', '25'},
    },
]


# -- Function Finder -------------------------------------------------------

def _find_function_at_offset(content: str, offset: int) -> str:
    """Find the function name at a given character offset."""
    pattern = re.compile(
        r'(?:public|private|protected|static)?\s*function\s+(\w+)\s*\(',
        re.IGNORECASE
    )
    best_func = "GLOBAL"
    best_pos = -1
    for match in pattern.finditer(content):
        if match.start() <= offset and match.start() > best_pos:
            best_func = match.group(1)
            best_pos = match.start()
    return best_func


# -- Core Scanner ----------------------------------------------------------

def scan_file(
    file_path: Path,
    project_root: Path | None = None,
    whitelist: list[str] | None = None,
) -> list[HardcodedFinding]:
    """Scan a PHP file for hardcoded values.

    Args:
        whitelist: List of strings to suppress. If any whitelist entry
                   appears in the matched text, the finding is skipped.
    """
    findings = []

    try:
        content = file_path.read_text(encoding='utf-8', errors='ignore')
    except Exception:
        return findings

    rel_path = str(file_path.relative_to(project_root)) if project_root else str(file_path)
    lines_list = content.split('\n')
    wl = whitelist or []

    for rule in RULES:
        for match in re.finditer(rule['pattern'], content, re.IGNORECASE):
            line_num = content[:match.start()].count('\n') + 1
            func = _find_function_at_offset(content, match.start())
            matched_text = match.group(0)[:80]

            # Check exclusions
            if 'exclude_values' in rule and match.lastindex:
                val = match.group(1) if match.lastindex >= 1 else None
                if val and val in rule['exclude_values']:
                    continue

            # Skip if in a comment
            if line_num <= len(lines_list):
                line_text = lines_list[line_num - 1].strip()
                if line_text.startswith('//') or line_text.startswith('*') or line_text.startswith('/*'):
                    continue

            # Skip if whitelisted
            if wl and any(w in matched_text for w in wl):
                continue

            findings.append(HardcodedFinding(
                rule_id=rule['id'],
                rule_name=rule['name'],
                category=rule['category'],
                severity=rule['severity'],
                file=rel_path,
                line=line_num,
                function=func,
                match=matched_text,
                description=rule['description'],
            ))

    return findings


def scan_directory(
    directory: Path,
    project_root: Path | None = None,
    whitelist: list[str] | None = None,
) -> HardcodedScanResult:
    """Scan a directory for hardcoded values."""
    result = HardcodedScanResult()
    root = project_root or directory

    for php_file in sorted(directory.rglob("*.php")):
        result.files_scanned += 1
        result.findings.extend(scan_file(php_file, root, whitelist))

    return result


def scan_project(
    project_root: Path,
    module_filter: str = "",
    whitelist: list[str] | None = None,
) -> HardcodedScanResult:
    """Scan standard CI3 project directories."""
    result = HardcodedScanResult()

    scan_dirs = [
        project_root / "admin" / "application" / "controllers",
        project_root / "admin" / "application" / "models",
    ]

    for scan_dir in scan_dirs:
        if not scan_dir.is_dir():
            continue
        for php_file in sorted(scan_dir.rglob("*.php")):
            if module_filter:
                rel = str(php_file.relative_to(project_root))
                if module_filter.lower() not in rel.lower():
                    continue
            result.files_scanned += 1
            result.findings.extend(scan_file(php_file, project_root, whitelist))

    return result
