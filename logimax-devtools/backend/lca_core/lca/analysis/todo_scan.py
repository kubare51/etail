"""TODO Scanner — find all TODO/FIXME/HACK/XXX comments in the codebase.

Scans PHP and JS files for comment markers that indicate technical debt,
unfinished work, or known issues.

Outputs grouped by severity (HACK/FIXME > TODO > NOTE) with module attribution.
"""

import re
from collections import defaultdict
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional

from lca.analysis.modules import detect_module


# Priority order: higher = more urgent
TAG_PRIORITY = {
    'HACK': 4,
    'FIXME': 3,
    'BUG': 3,
    'XXX': 2,
    'TODO': 1,
    'NOTE': 0,
    'OPTIMIZE': 1,
    'REVIEW': 1,
    'TEMP': 2,
    'WORKAROUND': 2,
}

TAG_LABELS = {
    4: 'CRITICAL',
    3: 'HIGH',
    2: 'MEDIUM',
    1: 'NORMAL',
    0: 'INFO',
}

# Pattern to match comment tags: // TODO: ..., /* FIXME: ..., # TODO ...
TAG_PATTERN = re.compile(
    r'(?://|/\*|#)\s*'
    r'(?P<tag>' + '|'.join(TAG_PRIORITY.keys()) + r')'
    r'\s*[:(\-]?\s*(?P<text>[^\n*]{0,200})',
    re.IGNORECASE,
)


@dataclass
class TodoItem:
    """A single TODO/FIXME/HACK comment found in code."""
    tag: str
    text: str
    file: str
    line: int
    module: str
    priority: int
    context: str = ''  # surrounding function name if detectable


@dataclass
class TodoScanResult:
    """Results of a TODO scan."""
    files_scanned: int
    total_items: int
    by_tag: Dict[str, int] = field(default_factory=dict)
    by_module: Dict[str, int] = field(default_factory=dict)
    by_priority: Dict[str, int] = field(default_factory=dict)
    items: List[TodoItem] = field(default_factory=list)


def _find_function_context(content: str, line_num: int) -> str:
    """Find the function name containing a given line number."""
    lines = content.split('\n')
    pattern = re.compile(r'(?:public|private|protected|static)?\s*function\s+(\w+)\s*\(')

    current_func = '(top-level)'
    for i, line in enumerate(lines, 1):
        match = pattern.search(line)
        if match:
            current_func = match.group(1) + '()'
        if i == line_num:
            break
    return current_func


def scan_todos(
    project_root: Path,
    module_filter: Optional[str] = None,
    tag_filter: Optional[str] = None,
) -> TodoScanResult:
    """Scan codebase for TODO/FIXME/HACK comments."""
    scan_dirs = [
        project_root / 'admin' / 'application' / 'controllers',
        project_root / 'admin' / 'application' / 'models',
        project_root / 'admin' / 'application' / 'helpers',
        project_root / 'admin' / 'application' / 'views',
        project_root / 'admin' / 'assets' / 'js',
    ]

    items = []
    files_scanned = 0
    by_tag = defaultdict(int)
    by_module = defaultdict(int)
    by_priority = defaultdict(int)

    for scan_dir in scan_dirs:
        if not scan_dir.is_dir():
            continue
        for fpath in sorted(scan_dir.rglob('*')):
            if fpath.suffix not in ('.php', '.js', '.html'):
                continue
            if fpath.name.startswith('.'):
                continue

            rel_path = str(fpath.relative_to(project_root))
            module = detect_module(rel_path)

            if module_filter and module != module_filter:
                continue

            files_scanned += 1

            try:
                content = fpath.read_text(encoding='utf-8', errors='ignore')
            except Exception:
                continue

            for line_num, line_text in enumerate(content.split('\n'), 1):
                match = TAG_PATTERN.search(line_text)
                if not match:
                    continue

                tag = match.group('tag').upper()
                text = match.group('text').strip().rstrip('*/')

                if tag_filter and tag != tag_filter.upper():
                    continue

                priority = TAG_PRIORITY.get(tag, 1)
                context = _find_function_context(content, line_num)

                item = TodoItem(
                    tag=tag,
                    text=text if text else '(no description)',
                    file=rel_path,
                    line=line_num,
                    module=module,
                    priority=priority,
                    context=context,
                )
                items.append(item)
                by_tag[tag] += 1
                by_module[module] += 1
                by_priority[TAG_LABELS.get(priority, 'NORMAL')] += 1

    # Sort by priority descending, then file
    items.sort(key=lambda x: (-x.priority, x.file, x.line))

    return TodoScanResult(
        files_scanned=files_scanned,
        total_items=len(items),
        by_tag=dict(by_tag),
        by_module=dict(by_module),
        by_priority=dict(by_priority),
        items=items,
    )
