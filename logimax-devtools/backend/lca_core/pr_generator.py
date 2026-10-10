#!/usr/bin/env python3
"""
PR Generator — Auto-generate PR descriptions with impact analysis
===================================================================
Reads changeset manifest + runs LCA analysis to generate rich PR body.
Includes auto-generated review checklist based on changed file types.

Usage:
    python pr_generator.py --project-root . --branch feature/my-fix
    python pr_generator.py --project-root . --changeset  # reads .changes/current.json
"""

import argparse
import json
import os
import subprocess
import sys
from datetime import datetime


def get_branch_files(project_root, branch=None, base='PRODUCTION'):
    """Get list of changed files in current branch vs base."""
    try:
        if branch:
            cmd = ['git', 'diff', '--name-only', f'{base}...{branch}']
        else:
            cmd = ['git', 'diff', '--name-only', base]
        result = subprocess.run(cmd, capture_output=True, text=True, cwd=project_root)
        return [f.strip() for f in result.stdout.strip().split('\n') if f.strip()]
    except Exception:
        return []


def get_branch_commits(project_root, base='PRODUCTION'):
    """Get commits in current branch not in base."""
    try:
        cmd = ['git', 'log', f'{base}..HEAD', '--oneline', '--no-merges']
        result = subprocess.run(cmd, capture_output=True, text=True, cwd=project_root)
        return [line.strip() for line in result.stdout.strip().split('\n') if line.strip()]
    except Exception:
        return []


def detect_file_categories(files):
    """Categorize changed files."""
    categories = {
        'controllers': [],
        'models': [],
        'views': [],
        'js': [],
        'config': [],
        'helpers': [],
        'css': [],
        'python': [],
        'other': [],
    }
    for f in files:
        fl = f.lower()
        if '/controllers/' in fl:
            categories['controllers'].append(f)
        elif '/models/' in fl:
            categories['models'].append(f)
        elif '/views/' in fl:
            categories['views'].append(f)
        elif fl.endswith('.js'):
            categories['js'].append(f)
        elif '/config/' in fl:
            categories['config'].append(f)
        elif '/helpers/' in fl:
            categories['helpers'].append(f)
        elif fl.endswith('.css'):
            categories['css'].append(f)
        elif fl.endswith('.py'):
            categories['python'].append(f)
        else:
            categories['other'].append(f)
    return categories


def detect_modules(files):
    """Detect which modules are affected."""
    import re
    modules = set()
    module_patterns = {
        'billing': r'billing', 'estimation': r'estimation',
        'purchase': r'purchase', 'stock': r'stock',
        'reports': r'report', 'payment': r'payment',
        'customer': r'customer', 'tagging': r'tagging',
        'order': r'order', 'settings': r'setting',
        'employee': r'employee', 'dashboard': r'dashboard',
    }
    for f in files:
        for mod, pat in module_patterns.items():
            if re.search(pat, f.lower()):
                modules.add(mod)
    return sorted(modules)


def generate_review_checklist(categories, modules):
    """Generate context-aware review checklist."""
    checks = []

    if categories['models']:
        checks.append('Model logic is correct — no business rules in controllers')
        checks.append('All queries use query builder (no raw SQL without justification)')
        checks.append('Transactions wrap multi-table operations')

    if categories['controllers']:
        checks.append('Controller only orchestrates (no calculations or business logic)')
        checks.append('All user input via `$this->input->post()`')
        checks.append('AJAX responses follow standard format')

    if categories['js']:
        checks.append('All form values wrapped with `parseFloat()` before arithmetic')
        checks.append('`$.ajax` calls have error handlers')
        checks.append('No hardcoded URLs — uses `base_url` variable')

    if categories['views']:
        checks.append('No raw PHP output — `htmlspecialchars()` on user data')
        checks.append('Print/view layout matches existing patterns')

    if categories['config']:
        checks.append('**Config change — impacts entire application**')
        checks.append('Change approved by TL before merge')

    if categories['helpers']:
        checks.append('**Helper change — affects all modules**')
        checks.append('All callers checked for breaking changes')

    if len(modules) > 1:
        checks.append(f'**Cross-module change** — affects: {", ".join(modules)}')
        checks.append('Cross-module integration tested')

    # Financial checks
    financial_modules = {'billing', 'estimation', 'payment', 'purchase'}
    if financial_modules & set(modules):
        checks.append('Financial calculations verified with test data')
        checks.append('`toFixed(2)` on final currency amounts')
        checks.append('NaN guards on all calculations')

    return checks


def load_changeset(project_root):
    """Load .changes/current.json if exists."""
    path = os.path.join(project_root, '.changes', 'current.json')
    if os.path.exists(path):
        with open(path, 'r', encoding='utf-8') as f:
            return json.load(f)
    return None


def generate_pr_body(project_root, base='PRODUCTION'):
    """Generate full PR body."""
    files = get_branch_files(project_root, base=base)
    commits = get_branch_commits(project_root, base)
    changeset = load_changeset(project_root)
    categories = detect_file_categories(files)
    modules = detect_modules(files)
    checklist = generate_review_checklist(categories, modules)

    lines = []

    # Title from changeset or auto-detect
    if changeset:
        ctype = changeset.get('type', 'chore')
        module = changeset.get('module', '')
        summary = changeset.get('summary', '')
        ticket = changeset.get('ticket', '')
        ref = f' [{ticket}]' if ticket else ''
        lines.append(f'## {ctype}({module}): {summary}{ref}')
        lines.append('')

        # Details
        details = changeset.get('details', [])
        if details:
            lines.append('### What Changed')
            for d in details:
                lines.append(f'- {d}')
            lines.append('')

        # Impact
        if changeset.get('impact'):
            lines.append('### Impact')
            lines.append(changeset['impact'])
            lines.append('')

        # Testing
        if changeset.get('testing'):
            lines.append('### Testing Done')
            lines.append(changeset['testing'])
            lines.append('')
    else:
        lines.append('## Changes')
        lines.append('')

    # Files summary
    lines.append('### Files Changed')
    lines.append('')
    total = len(files)
    lines.append(f'**{total} files** across {len(modules)} module(s): {", ".join(modules) if modules else "N/A"}')
    lines.append('')

    for cat_name, cat_files in categories.items():
        if cat_files:
            lines.append(f'<details><summary>{cat_name.title()} ({len(cat_files)})</summary>')
            lines.append('')
            for f in cat_files:
                lines.append(f'- `{f}`')
            lines.append('')
            lines.append('</details>')
            lines.append('')

    # Commits
    if commits:
        lines.append('### Commits')
        lines.append('')
        for c in commits[:20]:
            lines.append(f'- {c}')
        if len(commits) > 20:
            lines.append(f'- ... and {len(commits)-20} more')
        lines.append('')

    # Review checklist
    if checklist:
        lines.append('### Review Checklist')
        lines.append('')
        for check in checklist:
            lines.append(f'- [ ] {check}')
        lines.append('')

    return '\n'.join(lines)


def main():
    parser = argparse.ArgumentParser(description='PR Body Generator')
    parser.add_argument('--project-root', required=True)
    parser.add_argument('--base', default='PRODUCTION', help='Base branch (default: PRODUCTION)')
    parser.add_argument('--output', help='Output file')
    args = parser.parse_args()

    project_root = os.path.abspath(args.project_root)
    body = generate_pr_body(project_root, args.base)

    if sys.platform == 'win32':
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    print(body)

    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            f.write(body)
        print(f'\nSaved to: {args.output}', file=sys.stderr)


if __name__ == '__main__':
    main()
