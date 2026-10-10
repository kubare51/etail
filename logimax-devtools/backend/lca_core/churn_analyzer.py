#!/usr/bin/env python3
"""
Churn Analyzer — Find high-change files that are bug magnets
=============================================================
Analyzes git history to find files that change most frequently.
High churn + low health = highest risk for bugs.

Usage:
    python churn_analyzer.py --project-root . --months 3
    python churn_analyzer.py --project-root . --module billing --months 6
    python churn_analyzer.py --project-root . --top 20 --output churn.md
"""

import argparse
import os
import re
import subprocess
import sys
from collections import Counter, defaultdict
from datetime import datetime, timedelta


def detect_module(filepath):
    """Detect module from file path."""
    module_patterns = {
        'billing': r'billing',
        'estimation': r'estimation',
        'purchase': r'purchase',
        'stock': r'stock',
        'reports': r'report',
        'payment': r'payment',
        'customer': r'customer',
        'tagging': r'tagging',
        'order': r'order',
        'settings': r'setting',
        'employee': r'employee',
        'dashboard': r'dashboard',
        'branch_transfer': r'branch_transfer',
        'scheme': r'scheme',
        'catalog': r'catalog',
        'services': r'service',
        'lot': r'lot',
        'gst': r'gst',
    }
    fp = filepath.lower()
    for module, pattern in module_patterns.items():
        if re.search(pattern, fp):
            return module
    return 'other'


def get_git_log(project_root, months=3, module_filter=None):
    """Get git log with changed files."""
    since_date = (datetime.now() - timedelta(days=months * 30)).strftime('%Y-%m-%d')

    cmd = [
        'git', 'log',
        f'--since={since_date}',
        '--pretty=format:%H|%an|%ae|%ad|%s',
        '--date=short',
        '--name-only',
        '--diff-filter=AMRD',
    ]

    # Filter to module path if specified
    if module_filter:
        # Don't add path filter here, we'll filter after
        pass

    try:
        result = subprocess.run(
            cmd, capture_output=True, text=True,
            cwd=project_root, encoding='utf-8', errors='replace'
        )
        return result.stdout
    except Exception as e:
        print(f'Error running git log: {e}', file=sys.stderr)
        return ''


def parse_git_log(log_output, module_filter=None):
    """Parse git log output into file change data."""
    file_changes = Counter()
    file_authors = defaultdict(set)
    file_last_changed = {}
    file_commits = defaultdict(list)
    author_changes = Counter()

    current_commit = None
    current_author = None
    current_date = None
    current_msg = None

    for line in log_output.split('\n'):
        line = line.strip()
        if not line:
            continue

        if '|' in line and len(line.split('|')) >= 5:
            parts = line.split('|', 4)
            current_commit = parts[0]
            current_author = parts[1]
            current_date = parts[3]
            current_msg = parts[4] if len(parts) > 4 else ''
            continue

        # File line
        if current_commit and (line.endswith('.php') or line.endswith('.js') or line.endswith('.py')):
            # Filter by module if specified
            if module_filter:
                mod = detect_module(line)
                if mod != module_filter:
                    continue

            # Only count app code
            if not (line.startswith('admin/application/') or
                    line.startswith('admin/assets/js/') or
                    line.startswith('logimax-devtools/')):
                continue

            file_changes[line] += 1
            file_authors[line].add(current_author)
            file_last_changed[line] = current_date
            file_commits[line].append({
                'hash': current_commit[:8],
                'author': current_author,
                'date': current_date,
                'msg': current_msg,
            })
            author_changes[current_author] += 1

    return {
        'file_changes': file_changes,
        'file_authors': file_authors,
        'file_last_changed': file_last_changed,
        'file_commits': file_commits,
        'author_changes': author_changes,
    }


def classify_risk(changes, authors_count):
    """Classify churn risk based on change count and author diversity."""
    if changes >= 30 or (changes >= 15 and authors_count >= 3):
        return 'CRITICAL', '🔴'
    elif changes >= 15 or (changes >= 8 and authors_count >= 3):
        return 'HIGH', '🟠'
    elif changes >= 8:
        return 'MEDIUM', '🟡'
    else:
        return 'LOW', '🟢'


def generate_report(data, months, module_filter, top_n):
    """Generate markdown report."""
    file_changes = data['file_changes']
    file_authors = data['file_authors']
    file_last_changed = data['file_last_changed']
    author_changes = data['author_changes']

    if not file_changes:
        return f'# Churn Analysis Report\n\nNo changes found in the last {months} months.'

    lines = []
    lines.append('# Churn Analysis Report')
    lines.append('')
    lines.append(f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}')
    lines.append('')

    scope = f'Module: `{module_filter}`' if module_filter else 'All modules'
    lines.append(f'| Metric | Value |')
    lines.append(f'|---|---|')
    lines.append(f'| **Scope** | {scope} |')
    lines.append(f'| **Period** | Last {months} months |')
    lines.append(f'| **Files with changes** | {len(file_changes)} |')
    lines.append(f'| **Total file-level changes** | {sum(file_changes.values())} |')
    lines.append(f'| **Unique authors** | {len(author_changes)} |')
    lines.append('')

    # Top changed files
    top_files = file_changes.most_common(top_n)

    lines.append(f'## Top {min(top_n, len(top_files))} Most Changed Files')
    lines.append('')
    lines.append('| # | File | Changes | Authors | Last Changed | Module | Risk |')
    lines.append('|---|---|---|---|---|---|---|')

    for i, (filepath, count) in enumerate(top_files, 1):
        basename = os.path.basename(filepath)
        authors = len(file_authors.get(filepath, set()))
        last = file_last_changed.get(filepath, '?')
        module = detect_module(filepath)
        risk_label, risk_icon = classify_risk(count, authors)
        lines.append(f'| {i} | `{basename}` | **{count}** | {authors} | {last} | {module} | {risk_icon} {risk_label} |')

    lines.append('')

    # Risk distribution
    risk_counts = Counter()
    for filepath, count in file_changes.items():
        authors = len(file_authors.get(filepath, set()))
        risk_label, _ = classify_risk(count, authors)
        risk_counts[risk_label] += 1

    lines.append('## Risk Distribution')
    lines.append('')
    lines.append('| Risk Level | Files |')
    lines.append('|---|---|')
    for level in ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW']:
        if risk_counts[level] > 0:
            lines.append(f'| {level} | {risk_counts[level]} |')
    lines.append('')

    # Module breakdown
    module_changes = Counter()
    for filepath, count in file_changes.items():
        mod = detect_module(filepath)
        module_changes[mod] += count

    if len(module_changes) > 1:
        lines.append('## Changes by Module')
        lines.append('')
        lines.append('| Module | Total Changes | Files |')
        lines.append('|---|---|---|')
        for mod, count in module_changes.most_common():
            mod_files = sum(1 for f in file_changes if detect_module(f) == mod)
            lines.append(f'| `{mod}` | {count} | {mod_files} |')
        lines.append('')

    # Top authors
    lines.append('## Top Contributors')
    lines.append('')
    lines.append('| Author | Changes |')
    lines.append('|---|---|')
    for author, count in author_changes.most_common(10):
        lines.append(f'| {author} | {count} |')
    lines.append('')

    lines.append('---')
    lines.append('*Generated by Logimax Churn Analyzer (LCA)*')

    return '\n'.join(lines)


def main():
    parser = argparse.ArgumentParser(description='Churn Analyzer')
    parser.add_argument('--project-root', required=True, help='Path to project root')
    parser.add_argument('--module', help='Filter to specific module')
    parser.add_argument('--months', type=int, default=3, help='Months of history (default: 3)')
    parser.add_argument('--top', type=int, default=30, help='Top N files (default: 30)')
    parser.add_argument('--output', help='Output file path')
    parser.add_argument('--json-output', help='JSON output file path')
    args = parser.parse_args()

    project_root = os.path.abspath(args.project_root)

    print(f'Analyzing churn over last {args.months} months...', file=sys.stderr)

    log_output = get_git_log(project_root, args.months, args.module)
    data = parse_git_log(log_output, args.module)

    elapsed_label = f'{len(data["file_changes"])} files, {sum(data["file_changes"].values())} changes'
    print(f'Done: {elapsed_label}', file=sys.stderr)

    report = generate_report(data, args.months, args.module, args.top)

    if sys.platform == 'win32':
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    print(report)

    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            f.write(report)

    if args.json_output:
        json_data = {
            'period_months': args.months,
            'module': args.module,
            'files': [
                {
                    'path': fp,
                    'changes': count,
                    'authors': len(data['file_authors'].get(fp, set())),
                    'last_changed': data['file_last_changed'].get(fp, ''),
                    'module': detect_module(fp),
                }
                for fp, count in data['file_changes'].most_common()
            ],
        }
        with open(args.json_output, 'w', encoding='utf-8') as f:
            json.dump(json_data, f, indent=2)


if __name__ == '__main__':
    main()
