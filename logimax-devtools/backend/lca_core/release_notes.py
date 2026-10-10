#!/usr/bin/env python3
"""
Release Notes Generator — Auto-generate changelogs from changeset history
==========================================================================
Reads .changes/history/ and generates grouped release notes.

Usage:
    python release_notes.py --project-root . --since 2026-06-01
    python release_notes.py --project-root . --last 10
    python release_notes.py --project-root . --all --output CHANGELOG.md
"""

import argparse
import json
import os
import sys
from collections import defaultdict
from datetime import datetime


TYPE_LABELS = {
    'feat': 'Features',
    'fix': 'Bug Fixes',
    'refactor': 'Refactoring',
    'perf': 'Performance',
    'style': 'UI/Style',
    'docs': 'Documentation',
    'chore': 'Maintenance',
    'test': 'Testing',
}


def load_history(project_root, since=None, last_n=None):
    """Load archived changesets from history."""
    history_dir = os.path.join(project_root, '.changes', 'history')
    if not os.path.exists(history_dir):
        return []

    entries = []
    for filename in sorted(os.listdir(history_dir)):
        if not filename.endswith('.json'):
            continue
        filepath = os.path.join(history_dir, filename)
        with open(filepath, 'r', encoding='utf-8') as f:
            data = json.load(f)

        # Date filter
        if since:
            created = data.get('created_at', '')[:10]
            if created < since:
                continue

        entries.append(data)

    if last_n:
        entries = entries[-last_n:]

    return entries


def generate_notes(entries, version=None):
    """Generate markdown release notes."""
    if not entries:
        return 'No changes found.'

    lines = []

    if version:
        lines.append(f'# Release {version}')
    else:
        lines.append('# Release Notes')

    # Date range
    dates = [e.get('created_at', '')[:10] for e in entries if e.get('created_at')]
    if dates:
        lines.append(f'\n> {min(dates)} to {max(dates)} | {len(entries)} changes')
    lines.append('')

    # Group by type
    grouped = defaultdict(list)
    for entry in entries:
        ctype = entry.get('type', 'chore')
        grouped[ctype].append(entry)

    # Render each group
    for ctype in ['feat', 'fix', 'refactor', 'perf', 'style', 'docs', 'chore', 'test']:
        items = grouped.get(ctype, [])
        if not items:
            continue

        label = TYPE_LABELS.get(ctype, ctype.title())
        lines.append(f'## {label}')
        lines.append('')

        for item in items:
            module = item.get('module', '')
            summary = item.get('summary', '')
            ticket = item.get('ticket', '')
            ref = f' [{ticket}]' if ticket else ''

            if module:
                lines.append(f'- **{module}**: {summary}{ref}')
            else:
                lines.append(f'- {summary}{ref}')

            # Details as sub-items
            for detail in item.get('details', []):
                lines.append(f'  - {detail}')

        lines.append('')

    # Breaking changes
    breaking = [e for e in entries if e.get('breaking')]
    if breaking:
        lines.append('## Breaking Changes')
        lines.append('')
        for item in breaking:
            lines.append(f'- **{item.get("module", "?")}**: {item.get("summary", "?")}')
        lines.append('')

    # Stats
    modules = set(e.get('module', '') for e in entries)
    lines.append('---')
    lines.append(f'*{len(entries)} changes across {len(modules)} modules*')

    return '\n'.join(lines)


def main():
    parser = argparse.ArgumentParser(description='Release Notes Generator')
    parser.add_argument('--project-root', required=True)
    parser.add_argument('--since', help='Since date (YYYY-MM-DD)')
    parser.add_argument('--last', type=int, help='Last N changesets')
    parser.add_argument('--all', action='store_true', help='All history')
    parser.add_argument('--version', help='Version label')
    parser.add_argument('--output', help='Output file')
    args = parser.parse_args()

    project_root = os.path.abspath(args.project_root)

    if args.all:
        entries = load_history(project_root)
    elif args.last:
        entries = load_history(project_root, last_n=args.last)
    elif args.since:
        entries = load_history(project_root, since=args.since)
    else:
        entries = load_history(project_root, last_n=20)

    notes = generate_notes(entries, args.version)

    if sys.platform == 'win32':
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    print(notes)

    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            f.write(notes)


if __name__ == '__main__':
    main()
