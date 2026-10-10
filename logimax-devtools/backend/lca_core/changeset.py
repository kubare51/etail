#!/usr/bin/env python3
"""
Changeset Manager — Structured change tracking for commits and PRs
===================================================================
Maintains a .changes/ directory with structured change entries.
Git hooks and PR generators read from this to auto-generate messages.

Usage:
    # Create a new changeset
    python changeset.py add --type fix --module billing --summary "correct discount calc"

    # Add details to current changeset
    python changeset.py detail "Fixed calculateDiscount() - was using gross instead of net"

    # Set ticket reference
    python changeset.py ticket EST-057

    # View current changeset
    python changeset.py show

    # Generate commit message from changeset
    python changeset.py commit-msg

    # Generate PR body from changeset
    python changeset.py pr-body

    # Archive current changeset (after commit/merge)
    python changeset.py archive

    # Clear current changeset
    python changeset.py clear
"""

import argparse
import json
import os
import sys
import time
from datetime import datetime
from pathlib import Path


CHANGES_DIR = '.changes'
CURRENT_FILE = os.path.join(CHANGES_DIR, 'current.json')
HISTORY_DIR = os.path.join(CHANGES_DIR, 'history')

VALID_TYPES = ['fix', 'feat', 'refactor', 'style', 'perf', 'docs', 'chore', 'test']


def find_project_root():
    """Walk up from CWD to find .git directory."""
    path = Path(os.getcwd())
    while path != path.parent:
        if (path / '.git').exists():
            return str(path)
        path = path.parent
    return os.getcwd()


def get_changes_path(project_root=None):
    """Get absolute path to .changes directory."""
    root = project_root or find_project_root()
    return os.path.join(root, CHANGES_DIR)


def get_current_path(project_root=None):
    """Get absolute path to current.json."""
    root = project_root or find_project_root()
    return os.path.join(root, CURRENT_FILE)


def get_history_path(project_root=None):
    """Get absolute path to history directory."""
    root = project_root or find_project_root()
    return os.path.join(root, HISTORY_DIR)


def load_current(project_root=None):
    """Load current changeset, return empty dict if none."""
    path = get_current_path(project_root)
    if os.path.exists(path):
        with open(path, 'r', encoding='utf-8') as f:
            return json.load(f)
    return {}


def save_current(data, project_root=None):
    """Save current changeset."""
    path = get_current_path(project_root)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)


def cmd_add(args):
    """Create or update a changeset entry."""
    data = load_current()

    if not data:
        data = {
            'type': args.type,
            'module': args.module,
            'summary': args.summary,
            'details': [],
            'files': [],
            'ticket': args.ticket or '',
            'impact': '',
            'testing': '',
            'breaking': False,
            'created_at': datetime.now().isoformat(),
            'updated_at': datetime.now().isoformat(),
        }
    else:
        # Update existing
        if args.type:
            data['type'] = args.type
        if args.module:
            data['module'] = args.module
        if args.summary:
            data['summary'] = args.summary
        if args.ticket:
            data['ticket'] = args.ticket
        data['updated_at'] = datetime.now().isoformat()

    save_current(data)
    print(f'Changeset created: {data["type"]}({data["module"]}): {data["summary"]}')


def cmd_detail(args):
    """Add a detail line to current changeset."""
    data = load_current()
    if not data:
        print('No active changeset. Run: python changeset.py add --type fix --module billing --summary "..."')
        sys.exit(1)

    data.setdefault('details', [])
    data['details'].append(args.text)
    data['updated_at'] = datetime.now().isoformat()
    save_current(data)
    print(f'Added detail ({len(data["details"])} total)')


def cmd_ticket(args):
    """Set ticket reference."""
    data = load_current()
    if not data:
        print('No active changeset.')
        sys.exit(1)
    data['ticket'] = args.ticket_id
    data['updated_at'] = datetime.now().isoformat()
    save_current(data)
    print(f'Ticket set: {args.ticket_id}')


def cmd_files(args):
    """Add files to current changeset."""
    data = load_current()
    if not data:
        print('No active changeset.')
        sys.exit(1)
    data.setdefault('files', [])
    for f in args.file_list:
        if f not in data['files']:
            data['files'].append(f)
    data['updated_at'] = datetime.now().isoformat()
    save_current(data)
    print(f'Files: {len(data["files"])} tracked')


def cmd_impact(args):
    """Set impact description."""
    data = load_current()
    if not data:
        print('No active changeset.')
        sys.exit(1)
    data['impact'] = args.text
    data['updated_at'] = datetime.now().isoformat()
    save_current(data)
    print('Impact updated.')


def cmd_testing(args):
    """Set testing notes."""
    data = load_current()
    if not data:
        print('No active changeset.')
        sys.exit(1)
    data['testing'] = args.text
    data['updated_at'] = datetime.now().isoformat()
    save_current(data)
    print('Testing notes updated.')


def cmd_show(args):
    """Display current changeset."""
    data = load_current()
    if not data:
        print('No active changeset.')
        return

    print()
    print(f'  Type:    {data.get("type", "?")}')
    print(f'  Module:  {data.get("module", "?")}')
    print(f'  Summary: {data.get("summary", "?")}')
    print(f'  Ticket:  {data.get("ticket", "-")}')
    print()

    details = data.get('details', [])
    if details:
        print('  Details:')
        for d in details:
            print(f'    - {d}')
        print()

    files = data.get('files', [])
    if files:
        print(f'  Files ({len(files)}):')
        for f in files:
            print(f'    - {f}')
        print()

    if data.get('impact'):
        print(f'  Impact:  {data["impact"]}')
    if data.get('testing'):
        print(f'  Testing: {data["testing"]}')
    print()


def cmd_commit_msg(args):
    """Generate commit message from changeset."""
    data = load_current()
    if not data:
        # No changeset — return empty (hook will skip)
        return

    # Build commit message
    ctype = data.get('type', 'chore')
    module = data.get('module', '')
    summary = data.get('summary', 'update')
    ticket = data.get('ticket', '')

    # First line
    if module:
        msg = f'{ctype}({module}): {summary}'
    else:
        msg = f'{ctype}: {summary}'

    # Body
    details = data.get('details', [])
    if details:
        msg += '\n'
        for d in details:
            msg += f'\n- {d}'

    # Footer
    if ticket:
        msg += f'\n\nRef: {ticket}'

    print(msg)


def cmd_pr_body(args):
    """Generate PR body from changeset."""
    data = load_current()
    if not data:
        print('No active changeset.')
        sys.exit(1)

    ctype = data.get('type', 'chore')
    module = data.get('module', '')
    summary = data.get('summary', '')
    ticket = data.get('ticket', '')

    lines = []

    # Title section
    if ticket:
        lines.append(f'## {ctype}({module}): {summary} [{ticket}]')
    else:
        lines.append(f'## {ctype}({module}): {summary}')
    lines.append('')

    # Details
    details = data.get('details', [])
    if details:
        lines.append('### Changes')
        for d in details:
            lines.append(f'- {d}')
        lines.append('')

    # Files
    files = data.get('files', [])
    if files:
        lines.append('### Files Changed')
        for f in files:
            lines.append(f'- `{f}`')
        lines.append('')

    # Impact
    impact = data.get('impact', '')
    if impact:
        lines.append('### Impact')
        lines.append(impact)
        lines.append('')

    # Testing
    testing = data.get('testing', '')
    if testing:
        lines.append('### Testing')
        lines.append(testing)
        lines.append('')

    # Breaking
    if data.get('breaking'):
        lines.append('> **BREAKING CHANGE**: This change may affect downstream consumers.')
        lines.append('')

    print('\n'.join(lines))


def cmd_archive(args):
    """Archive current changeset to history."""
    data = load_current()
    if not data:
        print('No active changeset to archive.')
        return

    history_dir = get_history_path()
    os.makedirs(history_dir, exist_ok=True)

    # Filename: YYYY-MM-DD_HHMMSS_type_module.json
    ts = datetime.now().strftime('%Y-%m-%d_%H%M%S')
    ctype = data.get('type', 'chore')
    module = data.get('module', 'unknown')
    filename = f'{ts}_{ctype}_{module}.json'

    archive_path = os.path.join(history_dir, filename)
    data['archived_at'] = datetime.now().isoformat()

    with open(archive_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)

    # Clear current
    current_path = get_current_path()
    if os.path.exists(current_path):
        os.remove(current_path)

    print(f'Archived to: {filename}')


def cmd_clear(args):
    """Clear current changeset without archiving."""
    current_path = get_current_path()
    if os.path.exists(current_path):
        os.remove(current_path)
        print('Current changeset cleared.')
    else:
        print('No active changeset.')


def cmd_history(args):
    """List archived changesets."""
    history_dir = get_history_path()
    if not os.path.exists(history_dir):
        print('No history.')
        return

    files = sorted(os.listdir(history_dir), reverse=True)
    if not files:
        print('No history.')
        return

    limit = args.limit if hasattr(args, 'limit') else 20
    print(f'\nLast {min(limit, len(files))} changesets:\n')
    for f in files[:limit]:
        filepath = os.path.join(history_dir, f)
        with open(filepath, 'r', encoding='utf-8') as fh:
            data = json.load(fh)
        ctype = data.get('type', '?')
        module = data.get('module', '?')
        summary = data.get('summary', '?')
        ticket = data.get('ticket', '')
        ref = f' [{ticket}]' if ticket else ''
        print(f'  {f[:10]}  {ctype}({module}): {summary}{ref}')
    print()


def main():
    parser = argparse.ArgumentParser(description='Changeset Manager')
    sub = parser.add_subparsers(dest='command')

    # add
    p_add = sub.add_parser('add', help='Create/update changeset')
    p_add.add_argument('--type', '-t', choices=VALID_TYPES, required=True)
    p_add.add_argument('--module', '-m', required=True)
    p_add.add_argument('--summary', '-s', required=True)
    p_add.add_argument('--ticket', default='')

    # detail
    p_detail = sub.add_parser('detail', help='Add detail line')
    p_detail.add_argument('text')

    # ticket
    p_ticket = sub.add_parser('ticket', help='Set ticket ref')
    p_ticket.add_argument('ticket_id')

    # files
    p_files = sub.add_parser('files', help='Add files')
    p_files.add_argument('file_list', nargs='+')

    # impact
    p_impact = sub.add_parser('impact', help='Set impact')
    p_impact.add_argument('text')

    # testing
    p_testing = sub.add_parser('testing', help='Set testing notes')
    p_testing.add_argument('text')

    # show
    sub.add_parser('show', help='Show current changeset')

    # commit-msg
    sub.add_parser('commit-msg', help='Generate commit message')

    # pr-body
    sub.add_parser('pr-body', help='Generate PR body')

    # archive
    sub.add_parser('archive', help='Archive to history')

    # clear
    sub.add_parser('clear', help='Clear without archiving')

    # history
    p_hist = sub.add_parser('history', help='Show history')
    p_hist.add_argument('--limit', '-n', type=int, default=20)

    args = parser.parse_args()

    commands = {
        'add': cmd_add,
        'detail': cmd_detail,
        'ticket': cmd_ticket,
        'files': cmd_files,
        'impact': cmd_impact,
        'testing': cmd_testing,
        'show': cmd_show,
        'commit-msg': cmd_commit_msg,
        'pr-body': cmd_pr_body,
        'archive': cmd_archive,
        'clear': cmd_clear,
        'history': cmd_history,
    }

    if args.command in commands:
        commands[args.command](args)
    else:
        parser.print_help()


if __name__ == '__main__':
    main()
