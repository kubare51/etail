#!/usr/bin/env python3
"""
Security Scanner (L2)
======================
Static Application Security Testing (SAST) for CodeIgniter 3.

Checks for:
  - Raw $_POST/$_GET/$_REQUEST instead of $this->input->post()
  - Raw SQL via $this->db->query() without bindings
  - Missing XSS protection on output
  - Hardcoded credentials/secrets
  - Unsafe file upload handling
  - SQL injection vectors in active record
  - Unescaped user input in queries

Usage:
    python security_scanner.py --project-root /path/to/project
    python security_scanner.py --project-root /path/to/project --module billing --output report.md
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


# ── Rule Definitions ──────────────────────────────────────────────────

RULES = [
    {
        'id': 'SEC-001',
        'name': 'Raw Superglobal Access',
        'severity': 'HIGH',
        'pattern': r'\$_(POST|GET|REQUEST|COOKIE|SERVER|FILES)\s*\[',
        'description': 'Use $this->input->post/get() instead of raw superglobals',
        'fix': 'Replace with $this->input->post() / $this->input->get()',
    },
    {
        'id': 'SEC-002',
        'name': 'Raw SQL Query',
        'severity': 'HIGH',
        'pattern': r'\$this\s*->\s*db\s*->\s*query\s*\(\s*["\']',
        'description': 'Raw SQL without parameterized bindings — SQL injection risk',
        'fix': 'Use query bindings: $this->db->query($sql, array($param))',
    },
    {
        'id': 'SEC-003',
        'name': 'Raw SQL with Variable Interpolation',
        'severity': 'CRITICAL',
        'pattern': r'\$this\s*->\s*db\s*->\s*query\s*\(\s*"[^"]*\$',
        'description': 'SQL query with PHP variable interpolation — direct injection risk',
        'fix': 'Use query bindings instead of string interpolation',
    },
    {
        'id': 'SEC-004',
        'name': 'Unescaped Where Clause',
        'severity': 'MEDIUM',
        'pattern': r'->\s*where\s*\(\s*["\'][^"\']+\s+\$',
        'description': 'Variable in raw where string — potential injection if user-controlled',
        'fix': 'Use ->where(\'col\', $value) with separate key/value params',
    },
    {
        'id': 'SEC-005',
        'name': 'Hardcoded Password/Secret',
        'severity': 'CRITICAL',
        'pattern': r'(?:password|passwd|secret|api_key|apikey|token|auth)\s*[=:]\s*["\'][^"\']{4,}["\']',
        'description': 'Hardcoded credential or secret in source code',
        'fix': 'Move to environment variable or CI3 config file',
    },
    {
        'id': 'SEC-006',
        'name': 'Missing Upload Validation',
        'severity': 'HIGH',
        'pattern': r'move_uploaded_file\s*\(',
        'description': 'Direct move_uploaded_file without CI3 upload library validation',
        'fix': 'Use $this->upload->do_upload() with allowed_types config',
    },
    {
        'id': 'SEC-007',
        'name': 'Eval Usage',
        'severity': 'CRITICAL',
        'pattern': r'\beval\s*\(',
        'description': 'eval() executes arbitrary code — remote code execution risk',
        'fix': 'Remove eval() entirely. Find alternative approach.',
    },
    {
        'id': 'SEC-008',
        'name': 'Shell Execution',
        'severity': 'CRITICAL',
        'pattern': r'\b(?:exec|shell_exec|system|passthru|popen)\s*\(',
        'description': 'Shell command execution — command injection risk',
        'fix': 'Avoid shell commands. If necessary, use escapeshellarg()',
    },
    {
        'id': 'SEC-009',
        'name': 'Unvalidated Redirect',
        'severity': 'MEDIUM',
        'pattern': r'redirect\s*\(\s*\$',
        'description': 'Redirect with variable URL — open redirect vulnerability',
        'fix': 'Validate redirect target against whitelist',
    },
    {
        'id': 'SEC-010',
        'name': 'Direct Echo Without Escape',
        'severity': 'MEDIUM',
        'pattern': r'echo\s+\$(?:_POST|_GET|_REQUEST|this\s*->\s*input)',
        'description': 'Echoing user input without htmlspecialchars — XSS risk',
        'fix': 'Wrap with htmlspecialchars($val, ENT_QUOTES, \'UTF-8\')',
    },
    {
        'id': 'SEC-011',
        'name': 'MD5/SHA1 for Passwords',
        'severity': 'MEDIUM',
        'pattern': r'(?:md5|sha1)\s*\(\s*\$.*(?:pass|pwd)',
        'description': 'Weak hashing for passwords — use password_hash()',
        'fix': 'Replace with password_hash() and password_verify()',
    },
    {
        'id': 'SEC-012',
        'name': 'Disabled CSRF',
        'severity': 'HIGH',
        'pattern': r'csrf_protection\s*[=:]\s*(?:FALSE|false|0)',
        'description': 'CSRF protection disabled',
        'fix': 'Enable CSRF protection in config/config.php',
    },
]


# ── Scanner ───────────────────────────────────────────────────────────

def scan_file(filepath, project_root):
    """Scan a PHP file for security issues."""
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
            line_content = content.split('\n')[line-1].strip() if line <= content.count('\n') else ''

            findings.append({
                'rule_id': rule['id'],
                'rule_name': rule['name'],
                'severity': rule['severity'],
                'file': rel_path,
                'module': module,
                'line': line,
                'function': func,
                'match': match.group(0)[:80],
                'line_content': line_content[:120],
                'description': rule['description'],
                'fix': rule['fix'],
            })

    return findings


def scan_project(project_root, module_filter=None):
    """Scan entire project."""
    scan_dirs = [
        os.path.join(project_root, 'admin', 'application', 'controllers'),
        os.path.join(project_root, 'admin', 'application', 'models'),
        os.path.join(project_root, 'admin', 'application', 'helpers'),
        os.path.join(project_root, 'admin', 'application', 'config'),
        os.path.join(project_root, 'admin', 'application', 'views'),
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
    """Generate security report."""
    lines = [
        '# Security Scan Report',
        '',
        f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
    ]

    by_severity = defaultdict(int)
    for f in findings:
        by_severity[f['severity']] += 1

    lines.extend([
        '| Metric | Value |',
        '|---|---|',
        f'| **Files scanned** | {files_scanned} |',
        f'| **Total findings** | {len(findings)} |',
        f'| 💀 CRITICAL | {by_severity["CRITICAL"]} |',
        f'| 🔴 HIGH | {by_severity["HIGH"]} |',
        f'| 🟡 MEDIUM | {by_severity["MEDIUM"]} |',
        '',
    ])

    # By rule summary
    by_rule = defaultdict(int)
    for f in findings:
        by_rule[f'{f["rule_id"]} {f["rule_name"]}'] += 1

    lines.extend([
        '## Findings by Rule',
        '',
        '| Rule | Count | Fix |',
        '|---|---|---|',
    ])
    for rule_name, count in sorted(by_rule.items(), key=lambda x: -x[1]):
        rule_id = rule_name.split(' ')[0]
        rule_obj = next((r for r in RULES if r['id'] == rule_id), None)
        fix = rule_obj['fix'] if rule_obj else ''
        lines.append(f'| {rule_name} | {count} | {fix} |')
    lines.append('')

    # Critical findings first
    critical = [f for f in findings if f['severity'] == 'CRITICAL']
    if critical:
        lines.extend([
            '## 💀 Critical Findings',
            '',
            '| Rule | File | Function | Line | Match |',
            '|---|---|---|---|---|',
        ])
        for f in critical[:30]:
            lines.append(
                f'| `{f["rule_id"]}` | `{os.path.basename(f["file"])}` | '
                f'`{f["function"]}()` | L{f["line"]} | `{f["match"][:50]}` |'
            )
        lines.append('')

    # High findings
    high = [f for f in findings if f['severity'] == 'HIGH']
    if high:
        lines.extend([
            '## 🔴 High Severity',
            '',
            '| Rule | File | Function | Line |',
            '|---|---|---|---|',
        ])
        for f in high[:40]:
            lines.append(
                f'| `{f["rule_id"]}` | `{os.path.basename(f["file"])}` | '
                f'`{f["function"]}()` | L{f["line"]} |'
            )
        lines.append('')

    # By module summary
    mod_stats = defaultdict(lambda: {'CRITICAL': 0, 'HIGH': 0, 'MEDIUM': 0})
    for f in findings:
        mod_stats[f['module']][f['severity']] += 1

    lines.extend([
        '## Module Risk Summary',
        '',
        '| Module | Critical | High | Medium | Total |',
        '|---|---|---|---|---|',
    ])
    for mod, s in sorted(mod_stats.items(), key=lambda x: -(x[1]['CRITICAL']*100 + x[1]['HIGH']*10 + x[1]['MEDIUM'])):
        total = s['CRITICAL'] + s['HIGH'] + s['MEDIUM']
        lines.append(f'| `{mod}` | {s["CRITICAL"]} | {s["HIGH"]} | {s["MEDIUM"]} | {total} |')
    lines.append('')

    lines.append('---')
    lines.append('*Generated by Logimax Security Scanner (LCA)*')
    return '\n'.join(lines)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Security Scanner')
    parser.add_argument('--project-root', required=True)
    parser.add_argument('--module', help='Filter to specific module')
    parser.add_argument('--output', help='Output markdown file')

    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)

    print(f'Security scan starting...', file=sys.stderr)
    t_start = time.time()
    findings, files_scanned = scan_project(project_root, args.module)
    elapsed = time.time() - t_start

    crit = sum(1 for f in findings if f['severity'] == 'CRITICAL')
    high = sum(1 for f in findings if f['severity'] == 'HIGH')
    print(f'Done in {elapsed:.1f}s — {len(findings)} findings ({crit} critical, {high} high)', file=sys.stderr)

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
