#!/usr/bin/env python3
"""
Hardcoded Value Scanner (L5)
==============================
Finds magic numbers, hardcoded strings, and undocumented constants in PHP code.

Detects:
  - Magic numbers in WHERE clauses (status = 1, type = 2)
  - Hardcoded URLs and IPs
  - Hardcoded dates
  - Hardcoded email addresses
  - Magic number comparisons (== 1, != 0, etc.)

Usage:
    python hardcoded_scanner.py --project-root /path/to/project
    python hardcoded_scanner.py --project-root /path/to/project --module billing --output report.md
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


# ── Rules ─────────────────────────────────────────────────────────────

RULES = [
    {
        'id': 'HC-001',
        'name': 'Magic Number in WHERE',
        'category': 'SQL',
        'pattern': r"(?:where|or_where|having)\s*\(\s*['\"][^'\"]+['\"],\s*(\d+)\s*\)",
        'description': 'Bare number in query WHERE — no context for what it means',
        'exclude_values': {'0', '1'},  # 0/1 are very common booleans, still flag but lower
    },
    {
        'id': 'HC-002',
        'name': 'Magic Number in Condition',
        'category': 'Logic',
        'pattern': r"(?:==|!=|===|!==|>=|<=|>|<)\s*(\d{2,})",
        'description': 'Undocumented numeric comparison — use named constant',
    },
    {
        'id': 'HC-003',
        'name': 'Hardcoded URL',
        'category': 'Config',
        'pattern': r"['\"]https?://[^'\"]{10,}['\"]",
        'description': 'Hardcoded URL — should use config or environment variable',
    },
    {
        'id': 'HC-004',
        'name': 'Hardcoded IP Address',
        'category': 'Config',
        'pattern': r"['\"](\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})['\"]",
        'description': 'Hardcoded IP address — use config',
        'exclude_values': {'127.0.0.1', '0.0.0.0', '255.255.255.255'},
    },
    {
        'id': 'HC-005',
        'name': 'Hardcoded Date',
        'category': 'Logic',
        'pattern': r"['\"](\d{4}-\d{2}-\d{2})['\"]",
        'description': 'Hardcoded date — likely a temporary fix that became permanent',
    },
    {
        'id': 'HC-006',
        'name': 'Hardcoded Email',
        'category': 'Config',
        'pattern': r"['\"]([\w.+-]+@[\w-]+\.[\w.]+)['\"]",
        'description': 'Hardcoded email address — use config',
    },
    {
        'id': 'HC-007',
        'name': 'Status Magic Number in WHERE string',
        'category': 'SQL',
        'pattern': r"(?:where|or_where)\s*\(\s*['\"][^'\"]*(?:status|type|flag|is_|active)\s*[=<>!]+\s*(\d+)['\"]",
        'description': 'Status/type magic number in raw WHERE string',
    },
    {
        'id': 'HC-008',
        'name': 'Hardcoded Port Number',
        'category': 'Config',
        'pattern': r"['\"](?:port|PORT)['\"]?\s*(?:=>|=|:)\s*['\"]?(\d{2,5})['\"]?",
        'description': 'Hardcoded port — use config',
        'exclude_values': {'80', '443', '3306', '25'},
    },
]


# ── Scanner ───────────────────────────────────────────────────────────

def scan_file(filepath, project_root):
    """Scan a PHP file for hardcoded values."""
    findings = []
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
    except Exception:
        return findings

    rel_path = os.path.relpath(filepath, project_root)
    module = detect_module(rel_path)
    func_map = build_function_map(content)

    for rule in RULES:
        for match in re.finditer(rule['pattern'], content, re.IGNORECASE):
            line = content[:match.start()].count('\n') + 1
            func = find_function_at_offset(func_map, match.start())
            matched_text = match.group(0)[:80]

            # Check exclusions
            if 'exclude_values' in rule and match.lastindex:
                val = match.group(1) if match.lastindex >= 1 else None
                if val and val in rule['exclude_values']:
                    continue

            # Skip if in a comment
            line_text = content.split('\n')[line-1].strip() if line <= content.count('\n') else ''
            if line_text.startswith('//') or line_text.startswith('*') or line_text.startswith('/*'):
                continue

            findings.append({
                'rule_id': rule['id'],
                'rule_name': rule['name'],
                'category': rule['category'],
                'file': rel_path,
                'module': module,
                'line': line,
                'function': func,
                'match': matched_text,
                'line_content': line_text[:120],
                'description': rule['description'],
            })

    return findings


def scan_project(project_root, module_filter=None):
    """Scan entire project."""
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
                all_findings.extend(scan_file(fpath, project_root))

    return all_findings, files_scanned


# ── Report ────────────────────────────────────────────────────────────

def generate_report(findings, files_scanned):
    """Generate hardcoded values report."""
    lines = [
        '# Hardcoded Values Report',
        '',
        f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
    ]

    by_cat = defaultdict(int)
    for f in findings:
        by_cat[f['category']] += 1

    lines.extend([
        '| Metric | Value |',
        '|---|---|',
        f'| **Files scanned** | {files_scanned} |',
        f'| **Total findings** | {len(findings)} |',
        f'| SQL magic numbers | {by_cat["SQL"]} |',
        f'| Logic magic numbers | {by_cat["Logic"]} |',
        f'| Config hardcodes | {by_cat["Config"]} |',
        '',
    ])

    # Group by rule
    by_rule = defaultdict(list)
    for f in findings:
        by_rule[f['rule_id']].append(f)

    for rule_id, rule_findings in sorted(by_rule.items()):
        rule_name = rule_findings[0]['rule_name']
        lines.extend([
            f'## {rule_id}: {rule_name} ({len(rule_findings)} findings)',
            '',
            '| File | Function | Line | Match |',
            '|---|---|---|---|',
        ])
        for f in rule_findings[:25]:
            lines.append(
                f'| `{os.path.basename(f["file"])}` | `{f["function"]}()` | '
                f'L{f["line"]} | `{f["match"][:60]}` |'
            )
        if len(rule_findings) > 25:
            lines.append(f'| ... | ... | ... | +{len(rule_findings)-25} more |')
        lines.append('')

    # Module summary
    mod_stats = defaultdict(int)
    for f in findings:
        mod_stats[f['module']] += 1

    lines.extend([
        '## Module Summary',
        '',
        '| Module | Findings |',
        '|---|---|',
    ])
    for mod, count in sorted(mod_stats.items(), key=lambda x: -x[1]):
        lines.append(f'| `{mod}` | {count} |')
    lines.append('')

    lines.append('---')
    lines.append('*Generated by Logimax Hardcoded Value Scanner (LCA)*')
    return '\n'.join(lines)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Hardcoded Value Scanner')
    parser.add_argument('--project-root', required=True)
    parser.add_argument('--module', help='Filter to specific module')
    parser.add_argument('--output', help='Output markdown file')

    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)

    print(f'Scanning for hardcoded values...', file=sys.stderr)
    t_start = time.time()
    findings, files_scanned = scan_project(project_root, args.module)
    elapsed = time.time() - t_start

    print(f'Done in {elapsed:.1f}s — {len(findings)} hardcoded values found', file=sys.stderr)

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
