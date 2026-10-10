"""Release Notes Generator — auto-generate changelogs from changeset history.

Reads .changes/history/ (JSON files saved by `lca changeset --save`)
and generates grouped, formatted release notes.

Ported from: release_notes.py (standalone tool)
"""

import json
from collections import defaultdict
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import List, Optional


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


@dataclass
class ChangeEntry:
    """A single change from history."""
    type: str
    module: str
    summary: str
    ticket: str = ''
    details: List[str] = field(default_factory=list)
    breaking: bool = False
    created_at: str = ''


@dataclass
class ReleaseNotesResult:
    """Results of release notes generation."""
    entries: List[ChangeEntry]
    date_range: str
    version: str
    markdown: str


def load_history(
    project_root: Path,
    since: Optional[str] = None,
    last_n: Optional[int] = None,
) -> List[ChangeEntry]:
    """Load archived changesets from history."""
    history_dir = project_root / '.changes' / 'history'
    if not history_dir.exists():
        return []

    entries = []
    for filepath in sorted(history_dir.glob('*.json')):
        try:
            data = json.loads(filepath.read_text(encoding='utf-8'))
        except (json.JSONDecodeError, OSError):
            continue

        # Date filter
        if since:
            created = data.get('created_at', '')[:10]
            if created < since:
                continue

        entries.append(ChangeEntry(
            type=data.get('type', 'chore'),
            module=data.get('module', ''),
            summary=data.get('summary', ''),
            ticket=data.get('ticket', ''),
            details=data.get('details', []),
            breaking=data.get('breaking', False),
            created_at=data.get('created_at', ''),
        ))

    if last_n:
        entries = entries[-last_n:]

    return entries


def generate_notes(
    entries: List[ChangeEntry],
    version: Optional[str] = None,
) -> str:
    """Generate markdown release notes."""
    if not entries:
        return 'No changes found.'

    lines = []

    if version:
        lines.append(f'# Release {version}')
    else:
        lines.append('# Release Notes')

    # Date range
    dates = [e.created_at[:10] for e in entries if e.created_at]
    if dates:
        lines.append(f'\n> {min(dates)} to {max(dates)} | {len(entries)} changes')
    lines.append('')

    # Group by type
    grouped = defaultdict(list)
    for entry in entries:
        grouped[entry.type].append(entry)

    # Render each group in priority order
    for ctype in ['feat', 'fix', 'refactor', 'perf', 'style', 'docs', 'chore', 'test']:
        items = grouped.get(ctype, [])
        if not items:
            continue

        label = TYPE_LABELS.get(ctype, ctype.title())
        lines.append(f'## {label}')
        lines.append('')

        for item in items:
            ref = f' [{item.ticket}]' if item.ticket else ''
            if item.module:
                lines.append(f'- **{item.module}**: {item.summary}{ref}')
            else:
                lines.append(f'- {item.summary}{ref}')
            for detail in item.details:
                lines.append(f'  - {detail}')
        lines.append('')

    # Breaking changes
    breaking = [e for e in entries if e.breaking]
    if breaking:
        lines.append('## Breaking Changes')
        lines.append('')
        for item in breaking:
            lines.append(f'- **{item.module or "?"}**: {item.summary}')
        lines.append('')

    # Stats
    modules = set(e.module for e in entries)
    lines.append('---')
    lines.append(f'*{len(entries)} changes across {len(modules)} modules*')

    return '\n'.join(lines)


def build_release_notes(
    project_root: Path,
    since: Optional[str] = None,
    last_n: Optional[int] = None,
    version: Optional[str] = None,
) -> ReleaseNotesResult:
    """Full pipeline: load history and generate notes."""
    if last_n is None and since is None:
        last_n = 20  # Default

    entries = load_history(project_root, since=since, last_n=last_n)
    markdown = generate_notes(entries, version=version)

    dates = [e.created_at[:10] for e in entries if e.created_at]
    date_range = f'{min(dates)} to {max(dates)}' if dates else 'N/A'

    return ReleaseNotesResult(
        entries=entries,
        date_range=date_range,
        version=version or 'latest',
        markdown=markdown,
    )
