#!/usr/bin/env python3
"""
Regression Risk Scorer — Combine churn + callers + bug history per file
========================================================================
For each file in a PR, calculate regression risk based on:
1. Git churn (how often it changes)
2. Caller count (how many other files depend on it)
3. Complexity (CC rating)

Usage:
    python regression_risk.py --project-root . --files admin/application/models/ret_billing_model.php
    python regression_risk.py --project-root . --branch feature/my-fix
"""

import argparse
import importlib.util
import os
import subprocess
import sys
from datetime import datetime, timedelta


def load_module_from_file(name, filepath):
    spec = importlib.util.spec_from_file_location(name, filepath)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def get_branch_files(project_root, base='PRODUCTION'):
    """Get changed files in current branch."""
    try:
        result = subprocess.run(
            ['git', 'diff', '--name-only', base],
            capture_output=True, text=True, cwd=project_root
        )
        return [f.strip() for f in result.stdout.strip().split('\n') if f.strip()]
    except Exception:
        return []


def get_file_churn(project_root, filepath, months=3):
    """Get change count for a file in recent months."""
    since = (datetime.now() - timedelta(days=months * 30)).strftime('%Y-%m-%d')
    try:
        result = subprocess.run(
            ['git', 'log', f'--since={since}', '--oneline', '--', filepath],
            capture_output=True, text=True, cwd=project_root
        )
        return len([l for l in result.stdout.strip().split('\n') if l.strip()])
    except Exception:
        return 0


def get_file_authors(project_root, filepath, months=3):
    """Get unique author count for a file."""
    since = (datetime.now() - timedelta(days=months * 30)).strftime('%Y-%m-%d')
    try:
        result = subprocess.run(
            ['git', 'log', f'--since={since}', '--format=%an', '--', filepath],
            capture_output=True, text=True, cwd=project_root
        )
        authors = set(l.strip() for l in result.stdout.strip().split('\n') if l.strip())
        return len(authors)
    except Exception:
        return 0


def get_file_complexity(project_root, filepath):
    """Get worst complexity for a file."""
    try:
        lca_dir = os.path.dirname(os.path.abspath(__file__))
        mod = load_module_from_file('complexity_analyzer', os.path.join(lca_dir, 'complexity_analyzer.py'))
        full_path = os.path.join(project_root, filepath)
        if not os.path.exists(full_path):
            return 0, 'N/A'
        results = mod.scan_file(full_path)
        if not results:
            return 0, 'A'
        worst = max(r.get('complexity', 0) for r in results)
        worst_rating = max(results, key=lambda r: r.get('complexity', 0)).get('rating', 'A')
        return worst, worst_rating
    except Exception:
        return 0, '?'


def calculate_risk(churn, authors, complexity, is_financial, is_shared):
    """Calculate 0-100 regression risk score."""
    score = 0

    # Churn factor (0-30)
    if churn >= 30: score += 30
    elif churn >= 15: score += 20
    elif churn >= 8: score += 10
    elif churn >= 3: score += 5

    # Author diversity (0-15) — many authors = more risk
    if authors >= 5: score += 15
    elif authors >= 3: score += 10
    elif authors >= 2: score += 5

    # Complexity (0-25)
    if complexity >= 100: score += 25
    elif complexity >= 50: score += 20
    elif complexity >= 20: score += 15
    elif complexity >= 10: score += 10

    # Financial module (0-15)
    if is_financial:
        score += 15

    # Shared model/helper (0-15)
    if is_shared:
        score += 15

    return min(100, score)


def classify_risk(score):
    if score >= 70: return 'CRITICAL', '🔴'
    if score >= 50: return 'HIGH', '🟠'
    if score >= 30: return 'MEDIUM', '🟡'
    return 'LOW', '🟢'


def is_financial_file(filepath):
    """Check if file is in a financial module."""
    fl = filepath.lower()
    return any(m in fl for m in ['billing', 'estimation', 'payment', 'purchase', 'scheme'])


def is_shared_file(filepath):
    """Check if file is a shared model/helper."""
    fl = filepath.lower()
    return any(p in fl for p in ['common_model', 'auth_model', '/helpers/', '/config/', 'access_right'])


def generate_report(results):
    """Generate markdown report."""
    lines = []
    lines.append('# Regression Risk Report')
    lines.append('')
    lines.append(f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}')
    lines.append('')

    # Summary
    total = len(results)
    critical = sum(1 for r in results if r['risk_label'] == 'CRITICAL')
    high = sum(1 for r in results if r['risk_label'] == 'HIGH')

    lines.append(f'| Metric | Value |')
    lines.append(f'|---|---|')
    lines.append(f'| **Files analyzed** | {total} |')
    lines.append(f'| **Critical risk** | {critical} |')
    lines.append(f'| **High risk** | {high} |')

    avg_risk = sum(r['risk_score'] for r in results) / max(total, 1)
    overall_label, overall_icon = classify_risk(avg_risk)
    lines.append(f'| **Overall risk** | {overall_icon} {overall_label} ({avg_risk:.0f}/100) |')
    lines.append('')

    # Reviewer recommendation
    if critical > 0 or avg_risk >= 50:
        lines.append('> **Recommendation:** Requires **2 reviewers** including TL')
    elif high > 0:
        lines.append('> **Recommendation:** Requires **TL review**')
    else:
        lines.append('> **Recommendation:** Standard peer review')
    lines.append('')

    # Per-file table
    lines.append('## File-Level Risk')
    lines.append('')
    lines.append('| File | Churn | Authors | CC | Financial | Shared | Risk | Score |')
    lines.append('|---|---|---|---|---|---|---|---|')

    for r in sorted(results, key=lambda x: x['risk_score'], reverse=True):
        basename = os.path.basename(r['file'])
        financial = '✅' if r['financial'] else ''
        shared = '✅' if r['shared'] else ''
        lines.append(
            f'| `{basename}` | {r["churn"]} | {r["authors"]} | '
            f'{r["complexity"]} ({r["rating"]}) | {financial} | {shared} | '
            f'{r["risk_icon"]} {r["risk_label"]} | **{r["risk_score"]}** |'
        )

    lines.append('')
    lines.append('---')
    lines.append('*Generated by Logimax Regression Risk Scorer (LCA)*')

    return '\n'.join(lines)


def main():
    parser = argparse.ArgumentParser(description='Regression Risk Scorer')
    parser.add_argument('--project-root', required=True)
    parser.add_argument('--files', nargs='+', help='Files to analyze')
    parser.add_argument('--branch', action='store_true', help='Analyze current branch diff')
    parser.add_argument('--base', default='PRODUCTION', help='Base branch')
    parser.add_argument('--months', type=int, default=3, help='Churn lookback months')
    parser.add_argument('--output', help='Output file')
    args = parser.parse_args()

    project_root = os.path.abspath(args.project_root)

    if args.branch:
        files = get_branch_files(project_root, args.base)
    elif args.files:
        files = args.files
    else:
        parser.error('--files or --branch required')

    # Filter to PHP/JS only
    files = [f for f in files if f.endswith('.php') or f.endswith('.js')]

    if not files:
        print('No PHP/JS files to analyze.')
        sys.exit(0)

    print(f'Analyzing regression risk for {len(files)} files...', file=sys.stderr)

    results = []
    for filepath in files:
        churn = get_file_churn(project_root, filepath, args.months)
        authors = get_file_authors(project_root, filepath, args.months)
        complexity, rating = get_file_complexity(project_root, filepath)
        financial = is_financial_file(filepath)
        shared = is_shared_file(filepath)
        risk_score = calculate_risk(churn, authors, complexity, financial, shared)
        risk_label, risk_icon = classify_risk(risk_score)

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
            'risk_icon': risk_icon,
        })

    report = generate_report(results)

    if sys.platform == 'win32':
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    print(report)

    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            f.write(report)


if __name__ == '__main__':
    main()
