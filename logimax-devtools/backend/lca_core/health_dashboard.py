#!/usr/bin/env python3
"""
Code Health Dashboard (L4)
============================
Aggregates ALL LCA tools into a single unified report per module.

Runs: Complexity, Security, N+1, Dead Code, Duplicates, Hardcoded Values
Outputs: Module health score (0-100) + combined report.

Usage:
    python health_dashboard.py --project-root /path/to/project --module billing
    python health_dashboard.py --project-root /path/to/project --all --output-dir ./reports
"""

import argparse
import json
import os
import sys
import time
from collections import defaultdict
from pathlib import Path
from datetime import datetime

sys.path.insert(0, str(Path(__file__).parent))
from impact_analyzer import detect_module

# Import all scanners
from complexity_analyzer import scan_project as scan_complexity
from security_scanner import scan_project as scan_security
from n1_detector import scan_project as scan_n1
from hardcoded_scanner import scan_project as scan_hardcoded
from dead_code_detector import scan_dead_code
from duplicate_finder import scan_duplicates


# ── Health Score Calculator ───────────────────────────────────────────

def calculate_health_score(metrics):
    """
    Calculate module health score 0-100.
    Higher = healthier.
    """
    score = 100.0

    # Complexity penalty (max -25 points)
    # Penalize for E/F rated functions
    ef_ratio = metrics.get('ef_ratio', 0)  # % of functions rated E or F
    score -= min(25, ef_ratio * 50)

    # Security penalty (max -30 points)
    critical_sec = metrics.get('security_critical', 0)
    high_sec = metrics.get('security_high', 0)
    score -= min(15, critical_sec * 5)
    score -= min(15, high_sec * 1)

    # N+1 penalty (max -15 points)
    n1_critical = metrics.get('n1_critical', 0)
    n1_high = metrics.get('n1_high', 0)
    score -= min(10, n1_critical * 5)
    score -= min(5, n1_high * 0.5)

    # Dead code penalty (max -10 points)
    dead_ratio = metrics.get('dead_code_ratio', 0)
    score -= min(10, dead_ratio * 20)

    # Duplicate penalty (max -10 points)
    dup_clusters = metrics.get('duplicate_clusters', 0)
    score -= min(10, dup_clusters * 0.5)

    # Hardcoded values penalty (max -10 points)
    hardcoded = metrics.get('hardcoded_count', 0)
    score -= min(10, hardcoded * 0.1)

    return max(0, round(score))


def get_grade(score):
    """Convert score to letter grade."""
    if score >= 90:
        return 'A', '🟢'
    elif score >= 75:
        return 'B', '🟢'
    elif score >= 60:
        return 'C', '🟡'
    elif score >= 40:
        return 'D', '🟠'
    elif score >= 20:
        return 'E', '🔴'
    else:
        return 'F', '💀'


# ── Module Scanner ────────────────────────────────────────────────────

def scan_module_health(project_root, module_name):
    """Run all scanners on a module and compile results."""
    metrics = {}
    details = {}

    # 1. Complexity
    print(f'  [1/6] Complexity analysis...', file=sys.stderr)
    complexity_results, _ = scan_complexity(project_root, module_name)
    total_funcs = len(complexity_results)
    ef_funcs = sum(1 for r in complexity_results if r['rating'] in ('E', 'F'))
    metrics['total_functions'] = total_funcs
    metrics['ef_ratio'] = ef_funcs / max(total_funcs, 1)
    metrics['ef_count'] = ef_funcs
    metrics['avg_complexity'] = sum(r['complexity'] for r in complexity_results) / max(total_funcs, 1)
    metrics['worst_complexity'] = max((r['complexity'] for r in complexity_results), default=0)
    details['complexity_worst'] = sorted(complexity_results,
                                         key=lambda x: -x['complexity'])[:10]

    # 2. Security
    print(f'  [2/6] Security scan...', file=sys.stderr)
    security_results, _ = scan_security(project_root, module_name)
    metrics['security_critical'] = sum(1 for f in security_results if f['severity'] == 'CRITICAL')
    metrics['security_high'] = sum(1 for f in security_results if f['severity'] == 'HIGH')
    metrics['security_medium'] = sum(1 for f in security_results if f['severity'] == 'MEDIUM')
    metrics['security_total'] = len(security_results)
    details['security_critical'] = [f for f in security_results if f['severity'] == 'CRITICAL'][:10]

    # 3. N+1
    print(f'  [3/6] N+1 detection...', file=sys.stderr)
    n1_results, _ = scan_n1(project_root, module_name)
    metrics['n1_critical'] = sum(1 for f in n1_results if f['severity'] == 'CRITICAL')
    metrics['n1_high'] = sum(1 for f in n1_results if f['severity'] == 'HIGH')
    metrics['n1_total'] = len(n1_results)
    details['n1_critical'] = [f for f in n1_results if f['severity'] == 'CRITICAL'][:10]

    # 4. Dead Code
    print(f'  [4/6] Dead code detection...', file=sys.stderr)
    try:
        dead_results = scan_dead_code(project_root, module_name)
        dead_funcs = dead_results.get('dead_list', [])
        total_scanned = dead_results.get('total_functions', 1)
        metrics['dead_code_count'] = len(dead_funcs)
        metrics['dead_code_ratio'] = len(dead_funcs) / max(total_scanned, 1)
        details['dead_code'] = [{'name': d['function'], 'file': d['file'],
                                  'line': d['line'], 'visibility': d['visibility']}
                                 for d in dead_funcs[:10]]
    except Exception:
        metrics['dead_code_count'] = 0
        metrics['dead_code_ratio'] = 0
        details['dead_code'] = []

    # 5. Duplicates
    print(f'  [5/6] Duplicate detection...', file=sys.stderr)
    try:
        dup_results = scan_duplicates(project_root, 0.80, module_name)
        metrics['duplicate_clusters'] = dup_results['duplicate_clusters']
        metrics['duplicate_functions'] = dup_results['duplicate_functions']
        details['duplicates'] = dup_results['duplicates'][:10]
    except Exception:
        metrics['duplicate_clusters'] = 0
        metrics['duplicate_functions'] = 0
        details['duplicates'] = []

    # 6. Hardcoded Values
    print(f'  [6/6] Hardcoded value scan...', file=sys.stderr)
    hc_results, _ = scan_hardcoded(project_root, module_name)
    metrics['hardcoded_count'] = len(hc_results)
    details['hardcoded'] = hc_results[:10]

    # Calculate score
    score = calculate_health_score(metrics)
    grade, icon = get_grade(score)
    metrics['score'] = score
    metrics['grade'] = grade
    metrics['icon'] = icon

    return metrics, details


# ── Report ────────────────────────────────────────────────────────────

def generate_dashboard(module_name, metrics, details):
    """Generate health dashboard report."""
    grade, icon = metrics['grade'], metrics['icon']
    score = metrics['score']

    lines = [
        f'# Code Health Dashboard — {module_name}',
        '',
        f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
        f'## Overall Score: {icon} **{score}/100** (Grade {grade})',
        '',
        '---',
        '',
        '## Score Breakdown',
        '',
        '| Category | Status | Impact on Score |',
        '|---|---|---|',
    ]

    # Complexity
    ef = metrics['ef_count']
    total = metrics['total_functions']
    lines.append(f'| **Complexity** | {ef}/{total} functions rated E/F | -{min(25, metrics["ef_ratio"]*50):.0f} pts |')

    # Security
    crit = metrics['security_critical']
    high = metrics['security_high']
    sec_penalty = min(15, crit*5) + min(15, high*1)
    lines.append(f'| **Security** | {crit} critical, {high} high | -{sec_penalty:.0f} pts |')

    # N+1
    n1c = metrics['n1_critical']
    n1h = metrics['n1_high']
    n1_penalty = min(10, n1c*5) + min(5, n1h*0.5)
    lines.append(f'| **N+1 Queries** | {n1c} critical, {n1h} high | -{n1_penalty:.0f} pts |')

    # Dead code
    dc = metrics['dead_code_count']
    dc_penalty = min(10, metrics['dead_code_ratio']*20)
    lines.append(f'| **Dead Code** | {dc} unused functions ({metrics["dead_code_ratio"]*100:.1f}%) | -{dc_penalty:.0f} pts |')

    # Duplicates
    dup = metrics['duplicate_clusters']
    dup_penalty = min(10, dup*0.5)
    lines.append(f'| **Duplicates** | {dup} clusters ({metrics["duplicate_functions"]} functions) | -{dup_penalty:.0f} pts |')

    # Hardcoded
    hc = metrics['hardcoded_count']
    hc_penalty = min(10, hc*0.1)
    lines.append(f'| **Hardcoded Values** | {hc} findings | -{hc_penalty:.0f} pts |')

    lines.append('')

    # Key findings
    lines.extend([
        '## Key Findings',
        '',
    ])

    # Top complex functions
    if details.get('complexity_worst'):
        lines.extend([
            '### Most Complex Functions',
            '',
            '| Function | CC | Nesting | LOC | Rating |',
            '|---|---|---|---|---|',
        ])
        for r in details['complexity_worst'][:5]:
            rating_icon = {'A': '🟢', 'B': '🟢', 'C': '🟡', 'D': '🟠', 'E': '🔴', 'F': '💀'}[r['rating']]
            lines.append(f'| `{r["name"]}()` | {r["complexity"]} | {r["nesting"]} | {r["loc"]} | {rating_icon} {r["rating"]} |')
        lines.append('')

    # Critical security
    if details.get('security_critical'):
        lines.extend([
            '### Critical Security Issues',
            '',
            '| Rule | Function | Line |',
            '|---|---|---|',
        ])
        for f in details['security_critical'][:5]:
            lines.append(f'| `{f["rule_id"]}` {f["rule_name"]} | `{f["function"]}()` | L{f["line"]} |')
        lines.append('')

    # Critical N+1
    if details.get('n1_critical'):
        lines.extend([
            '### Critical N+1 Patterns',
            '',
            '| Function | Loop | DB Line |',
            '|---|---|---|',
        ])
        for f in details['n1_critical'][:5]:
            lines.append(f'| `{f["function"]}()` | {f["loop_type"]} L{f["loop_line"]} | L{f["db_line"]} |')
        lines.append('')

    lines.extend([
        '---',
        '*Generated by Logimax Code Health Dashboard (LCA)*',
    ])

    return '\n'.join(lines)


# ── All-Modules Summary ──────────────────────────────────────────────

def discover_modules(project_root):
    """Find all modules in the project."""
    modules = set()
    scan_dirs = [
        os.path.join(project_root, 'admin', 'application', 'controllers'),
        os.path.join(project_root, 'admin', 'application', 'models'),
    ]
    for scan_dir in scan_dirs:
        if not os.path.isdir(scan_dir):
            continue
        for root, _, files in os.walk(scan_dir):
            for fname in files:
                if fname.endswith('.php'):
                    rel = os.path.relpath(os.path.join(root, fname), project_root)
                    modules.add(detect_module(rel))
    return sorted(modules)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Code Health Dashboard')
    parser.add_argument('--project-root', required=True)
    parser.add_argument('--module', help='Specific module to analyze')
    parser.add_argument('--all', action='store_true', help='Scan all modules')
    parser.add_argument('--output', help='Output file (single module)')
    parser.add_argument('--output-dir', help='Output directory (all modules)')

    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)

    if args.all:
        modules = discover_modules(project_root)
        output_dir = args.output_dir or os.path.join(project_root, 'logimax-devtools', 'reports')
        os.makedirs(output_dir, exist_ok=True)

        all_scores = []
        for mod in modules:
            print(f'\n=== {mod} ===', file=sys.stderr)
            t_start = time.time()
            metrics, details = scan_module_health(project_root, mod)
            elapsed = time.time() - t_start
            print(f'  Score: {metrics["icon"]} {metrics["score"]}/100 ({elapsed:.1f}s)', file=sys.stderr)

            report = generate_dashboard(mod, metrics, details)
            Path(os.path.join(output_dir, f'{mod}_health.md')).write_text(report, encoding='utf-8')
            all_scores.append({'module': mod, **metrics})

        # Summary report
        summary = ['# Code Health Summary — All Modules', '',
                    f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}', '',
                    '| Module | Score | Grade | Functions | Security | N+1 | Dead Code | Duplicates |',
                    '|---|---|---|---|---|---|---|---|']
        for s in sorted(all_scores, key=lambda x: x['score']):
            summary.append(
                f'| `{s["module"]}` | {s["icon"]} **{s["score"]}** | {s["grade"]} | '
                f'{s["total_functions"]} | {s["security_total"]} | {s["n1_total"]} | '
                f'{s["dead_code_count"]} | {s["duplicate_clusters"]} |'
            )
        summary.extend(['', '---', '*Generated by Logimax Code Health Dashboard (LCA)*'])

        Path(os.path.join(output_dir, 'SUMMARY.md')).write_text('\n'.join(summary), encoding='utf-8')
        print(f'\nSummary: {os.path.join(output_dir, "SUMMARY.md")}', file=sys.stderr)

    else:
        module = args.module
        if not module:
            print('Error: specify --module or --all', file=sys.stderr)
            sys.exit(1)

        print(f'Code Health Dashboard for: {module}', file=sys.stderr)
        t_start = time.time()
        metrics, details = scan_module_health(project_root, module)
        elapsed = time.time() - t_start

        print(f'\nScore: {metrics["icon"]} {metrics["score"]}/100 (Grade {metrics["grade"]}) in {elapsed:.1f}s', file=sys.stderr)

        report = generate_dashboard(module, metrics, details)
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
