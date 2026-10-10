"""N+1 Query Static Detector — finds DB calls inside loops.

Patterns detected:
  1. foreach/for/while loop containing $this->db-> calls
  2. foreach loop calling a model method that queries the DB
  3. Nested loops with queries (CRITICAL — exponential growth)

Ported from standalone n1_detector.py into LCA v2 package.
"""

import re
from dataclasses import dataclass, field
from pathlib import Path
from collections import defaultdict


# -- Pattern Definitions ---------------------------------------------------

LOOP_STARTS = [
    r'foreach\s*\(',
    r'for\s*\(',
    r'while\s*\(',
]

DB_CALLS = [
    r'\$this\s*->\s*db\s*->\s*(?:get|get_where|query|insert|update|delete|select|from|where|join)\s*\(',
    r'\$this\s*->\s*\w+_model\s*->\s*\w+\s*\(',
]


@dataclass
class N1Finding:
    """A single N+1 query pattern."""
    severity: str           # CRITICAL, HIGH, MEDIUM
    pattern: str            # e.g., "Direct DB call in loop"
    file: str               # Relative path
    function: str           # Enclosing function name
    loop_type: str          # foreach, for, while
    loop_line: int          # Line number of loop start
    db_line: int            # Line number of DB call
    call: str               # Truncated call text


@dataclass
class N1ScanResult:
    """Results of an N+1 scan."""
    findings: list[N1Finding] = field(default_factory=list)
    files_scanned: int = 0

    @property
    def critical_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == "CRITICAL")

    @property
    def high_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == "HIGH")

    @property
    def medium_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == "MEDIUM")


# -- Loop Block Finder -----------------------------------------------------

def _find_loop_blocks(content: str) -> list[dict]:
    """Find all loop blocks with their body and position."""
    loops = []

    for loop_pattern in LOOP_STARTS:
        for match in re.finditer(loop_pattern, content):
            loop_start = match.start()
            line = content[:loop_start].count('\n') + 1
            loop_type = match.group(0).split('(')[0].strip()

            # Skip past the condition parentheses
            pos = match.end()
            paren_depth = 1
            while pos < len(content) and paren_depth > 0:
                if content[pos] == '(':
                    paren_depth += 1
                elif content[pos] == ')':
                    paren_depth -= 1
                pos += 1

            # Find the opening brace
            while pos < len(content) and content[pos] in ' \t\n\r':
                pos += 1

            if pos >= len(content) or content[pos] != '{':
                continue

            # Find matching closing brace
            brace_start = pos + 1
            depth = 1
            pos = brace_start
            while pos < len(content) and depth > 0:
                if content[pos] == '{':
                    depth += 1
                elif content[pos] == '}':
                    depth -= 1
                pos += 1

            if depth == 0:
                body = content[brace_start:pos-1]
                loops.append({
                    'type': loop_type,
                    'start': loop_start,
                    'end': pos,
                    'body': body,
                    'line': line,
                })

    return loops


# -- Function Finder -------------------------------------------------------

def _find_function_at_offset(content: str, offset: int) -> str:
    """Find the function name at a given character offset."""
    # Match PHP function/method declarations
    pattern = re.compile(
        r'(?:public|private|protected|static)?\s*function\s+(\w+)\s*\(',
        re.IGNORECASE
    )

    best_func = "GLOBAL"
    best_pos = -1

    for match in pattern.finditer(content):
        func_start = match.start()
        if func_start <= offset and func_start > best_pos:
            best_func = match.group(1)
            best_pos = func_start

    return best_func


# -- Core Detector ---------------------------------------------------------

def detect_n1_in_file(
    file_path: Path,
    project_root: Path | None = None,
    whitelist: list[str] | None = None,
) -> list[N1Finding]:
    """Detect N+1 patterns in a single file.

    Args:
        whitelist: List of strings to suppress. If any whitelist entry
                   appears in the call text, the finding is skipped.
    """
    findings = []
    wl = whitelist or []

    try:
        content = file_path.read_text(encoding='utf-8', errors='ignore')
    except Exception:
        return findings

    rel_path = str(file_path.relative_to(project_root)) if project_root else str(file_path)
    loops = _find_loop_blocks(content)

    for loop in loops:
        body = loop['body']
        enclosing_func = _find_function_at_offset(content, loop['start'])

        for db_pattern in DB_CALLS:
            for db_match in re.finditer(db_pattern, body):
                db_line = loop['line'] + body[:db_match.start()].count('\n')
                call_text = db_match.group(0).strip()

                # Skip if whitelisted
                if wl and any(w in call_text for w in wl):
                    continue

                # Determine severity
                if '$this->db->' in call_text:
                    severity = 'HIGH'
                    pattern_type = 'Direct DB call in loop'
                else:
                    severity = 'MEDIUM'
                    pattern_type = 'Model call in loop'

                # Check for nested loops (extra dangerous)
                nested_loops = _find_loop_blocks(body)
                for nested in nested_loops:
                    if (db_match.start() > (nested['start'] - loop['start']) and
                            db_match.start() < (nested['end'] - loop['start'])):
                        severity = 'CRITICAL'
                        pattern_type = 'DB call in NESTED loop'
                        break

                findings.append(N1Finding(
                    severity=severity,
                    pattern=pattern_type,
                    file=rel_path,
                    function=enclosing_func,
                    loop_type=loop['type'],
                    loop_line=loop['line'],
                    db_line=db_line,
                    call=call_text[:80],
                ))

    return findings


def scan_directory(
    directory: Path,
    project_root: Path | None = None,
    whitelist: list[str] | None = None,
) -> N1ScanResult:
    """Scan a directory for N+1 patterns."""
    result = N1ScanResult()
    root = project_root or directory

    for php_file in sorted(directory.rglob("*.php")):
        result.files_scanned += 1
        result.findings.extend(detect_n1_in_file(php_file, root, whitelist))

    # Sort by severity
    severity_order = {"CRITICAL": 0, "HIGH": 1, "MEDIUM": 2}
    result.findings.sort(key=lambda f: severity_order.get(f.severity, 99))

    return result


def scan_project(
    project_root: Path,
    module_filter: str = "",
    whitelist: list[str] | None = None,
) -> N1ScanResult:
    """Scan standard CI3 project directories for N+1 patterns."""
    result = N1ScanResult()

    scan_dirs = [
        project_root / "admin" / "application" / "controllers",
        project_root / "admin" / "application" / "models",
    ]

    for scan_dir in scan_dirs:
        if not scan_dir.is_dir():
            continue
        for php_file in sorted(scan_dir.rglob("*.php")):
            if module_filter:
                # Simple module detection from path
                rel = str(php_file.relative_to(project_root))
                if module_filter.lower() not in rel.lower():
                    continue
            result.files_scanned += 1
            result.findings.extend(detect_n1_in_file(php_file, project_root, whitelist))

    severity_order = {"CRITICAL": 0, "HIGH": 1, "MEDIUM": 2}
    result.findings.sort(key=lambda f: severity_order.get(f.severity, 99))

    return result
