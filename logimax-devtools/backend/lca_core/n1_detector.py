#!/usr/bin/env python3
"""
N+1 Query Static Detector (L3)
================================
Statically detects potential N+1 query patterns in PHP code.

Patterns detected:
  1. foreach/for/while loop containing $this->db-> calls
  2. foreach loop calling a model method that queries the DB
  3. Nested loops with queries

Complements the runtime APM N+1 detection (catches them before production).

Usage:
    python n1_detector.py --project-root /path/to/project
    python n1_detector.py --project-root /path/to/project --module billing --output report.md
"""

import argparse
import os
import re
import sys
import time
from collections import defaultdict
from pathlib import Path
from datetime import datetime

sys.path.insert(0, str(Path(__file__).parent))
from impact_analyzer import detect_module
from sql_code_mapper import build_function_map, find_function_at_offset


# ── Pattern Definitions ───────────────────────────────────────────────

LOOP_STARTS = [
    r'foreach\s*\(',
    r'for\s*\(',
    r'while\s*\(',
]

DB_CALLS = [
    r'\$this\s*->\s*db\s*->\s*(?:get|get_where|query|insert|update|delete|select|from|where|join)\s*\(',
    r'\$this\s*->\s*\w+_model\s*->\s*\w+\s*\(',
]


# ── Detector ──────────────────────────────────────────────────────────

def find_loop_blocks(content):
    """Find all loop blocks with their body and position."""
    loops = []

    for loop_pattern in LOOP_STARTS:
        for match in re.finditer(loop_pattern, content):
            loop_start = match.start()
            line = content[:loop_start].count('\n') + 1

            # Find the loop type
            loop_type = match.group(0).split('(')[0].strip()

            # Find opening brace after the loop condition
            pos = match.end()
            # Skip past the condition parentheses
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
                    'condition': content[match.end():brace_start].strip().strip('{ '),
                })

    return loops


def detect_n1_patterns(filepath, project_root):
    """Detect N+1 patterns in a file."""
    findings = []
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
    except Exception:
        return findings

    rel_path = os.path.relpath(filepath, project_root)
    module = detect_module(rel_path)
    func_map = build_function_map(content)
    loops = find_loop_blocks(content)

    for loop in loops:
        body = loop['body']
        enclosing_func = find_function_at_offset(func_map, loop['start'])

        # Check for direct DB calls inside loop
        for db_pattern in DB_CALLS:
            for db_match in re.finditer(db_pattern, body):
                db_line = loop['line'] + body[:db_match.start()].count('\n')
                call_text = db_match.group(0).strip()

                # Determine severity
                # Direct $this->db-> calls are more dangerous
                if '$this->db->' in call_text:
                    severity = 'HIGH'
                    pattern_type = 'Direct DB call in loop'
                else:
                    severity = 'MEDIUM'
                    pattern_type = 'Model call in loop'

                # Check for nested loops (extra dangerous)
                nested_loops = find_loop_blocks(body)
                for nested in nested_loops:
                    if db_match.start() > (nested['start'] - loop['start']) and \
                       db_match.start() < (nested['end'] - loop['start']):
                        severity = 'CRITICAL'
                        pattern_type = 'DB call in NESTED loop'
                        break

                findings.append({
                    'severity': severity,
                    'pattern': pattern_type,
                    'file': rel_path,
                    'module': module,
                    'function': enclosing_func,
                    'loop_type': loop['type'],
                    'loop_line': loop['line'],
                    'db_line': db_line,
                    'call': call_text[:80],
                })

    return findings


def scan_project(project_root, module_filter=None):
    """Scan entire project for N+1 patterns."""
    scan_dirs = [
        os.path.join(project_root, 'admin', 'application', 'controllers'),
        os.path.join(project_root, 'admin', 'application', 'models'),
    ]

    all_findings = []
    files_scanned = 0

    for scan_dir in scan_dirs:
        if not os.path.isdir(scan_dir):
            continue
        for root, _, files in os.walk(scan_dir):
            for fname in sorted(files):
                if not fname.endswith('.php'):
                    continue
                fpath = os.path.join(root, fname)
                rel = os.path.relpath(fpath, project_root)
                if module_filter and detect_module(rel) != module_filter:
                    continue
                files_scanned += 1
                all_findings.extend(detect_n1_patterns(fpath, project_root))

    return all_findings, files_scanned


# ── Report ────────────────────────────────────────────────────────────

def generate_report(findings, files_scanned):
    """Generate N+1 detection report."""
    lines = [
        '# N+1 Query Detection Report (Static Analysis)',
        '',
        f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
    ]

    by_sev = defaultdict(int)
    for f in findings:
        by_sev[f['severity']] += 1

    lines.extend([
        '| Metric | Value |',
        '|---|---|',
        f'| **Files scanned** | {files_scanned} |',
        f'| **Total N+1 patterns** | {len(findings)} |',
        f'| 💀 CRITICAL (nested loops) | {by_sev["CRITICAL"]} |',
        f'| 🔴 HIGH (direct DB in loop) | {by_sev["HIGH"]} |',
        f'| 🟡 MEDIUM (model call in loop) | {by_sev["MEDIUM"]} |',
        '',
    ])

    # Critical first
    critical = [f for f in findings if f['severity'] == 'CRITICAL']
    if critical:
        lines.extend([
            '## 💀 Critical: DB Calls in Nested Loops',
            '',
            '> These will cause exponential query growth (N×M queries).',
            '',
            '| Function | File | Module | Loop | DB Line | Call |',
            '|---|---|---|---|---|---|',
        ])
        for f in critical:
            lines.append(
                f'| `{f["function"]}()` | `{os.path.basename(f["file"])}` | '
                f'{f["module"]} | {f["loop_type"]} L{f["loop_line"]} | L{f["db_line"]} | `{f["call"][:50]}` |'
            )
        lines.append('')

    # High
    high = [f for f in findings if f['severity'] == 'HIGH']
    if high:
        lines.extend([
            '## 🔴 High: Direct DB Calls in Loops',
            '',
            '> Each loop iteration fires a separate query. Use where_in() or batch operations.',
            '',
            '| Function | File | Module | Loop | DB Line |',
            '|---|---|---|---|---|',
        ])
        for f in high[:30]:
            lines.append(
                f'| `{f["function"]}()` | `{os.path.basename(f["file"])}` | '
                f'{f["module"]} | {f["loop_type"]} L{f["loop_line"]} | L{f["db_line"]} |'
            )
        lines.append('')

    # Module summary
    mod_stats = defaultdict(lambda: {'CRITICAL': 0, 'HIGH': 0, 'MEDIUM': 0})
    for f in findings:
        mod_stats[f['module']][f['severity']] += 1

    lines.extend([
        '## Module Summary',
        '',
        '| Module | Critical | High | Medium | Total |',
        '|---|---|---|---|---|',
    ])
    for mod, s in sorted(mod_stats.items(), key=lambda x: -(x[1]['CRITICAL']*100 + x[1]['HIGH']*10 + x[1]['MEDIUM'])):
        total = s['CRITICAL'] + s['HIGH'] + s['MEDIUM']
        lines.append(f'| `{mod}` | {s["CRITICAL"]} | {s["HIGH"]} | {s["MEDIUM"]} | {total} |')
    lines.append('')

    lines.append('---')
    lines.append('*Generated by Logimax N+1 Static Detector (LCA)*')
    return '\n'.join(lines)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='N+1 Query Static Detector')
    parser.add_argument('--project-root', required=True)
    parser.add_argument('--module', help='Filter to specific module')
    parser.add_argument('--output', help='Output markdown file')

    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)

    print(f'Scanning for N+1 patterns...', file=sys.stderr)
    t_start = time.time()
    findings, files_scanned = scan_project(project_root, args.module)
    elapsed = time.time() - t_start

    crit = sum(1 for f in findings if f['severity'] == 'CRITICAL')
    print(f'Done in {elapsed:.1f}s — {len(findings)} N+1 patterns ({crit} critical)', file=sys.stderr)

    report = generate_report(findings, files_scanned)

    if args.output:
        Path(args.output).write_text(report, encoding='utf-8')
        print(f'Report: {args.output}', file=sys.stderr)
    else:
        if sys.platform == 'win32':
            sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        print(report)

    sys.exit(0)

if __name__ == '__main__':
    main()
