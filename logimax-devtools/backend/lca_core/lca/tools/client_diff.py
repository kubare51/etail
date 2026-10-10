"""Cross-Client Diff -- compare two client codebases.

For each PHP file in both codebases:
  1. Identifies added/removed/modified functions
  2. Shows client-specific overrides
  3. Flags customizations that could break auto-fix recipes

Ported from standalone cross_client_diff.py into LCA v2 package.
"""

from __future__ import annotations

import os
import re
from collections import defaultdict
from datetime import datetime
from difflib import SequenceMatcher
from pathlib import Path

from lca.analysis.modules import detect_module as _detect_module


# ---- Function Extraction --------------------------------------------

_FUNC_RE = re.compile(
    r'(?:public|private|protected)?\s*(?:static\s+)?function\s+(\w+)\s*\(([^)]*)\)\s*\{',
)


def _extract_functions(filepath: str) -> dict:
    """Extract all functions with normalized body for comparison."""
    functions = {}
    try:
        content = Path(filepath).read_text(encoding='utf-8', errors='ignore')
    except Exception:
        return functions

    for match in _FUNC_RE.finditer(content):
        func_name = match.group(1)
        params = match.group(2).strip()
        line = content[:match.start()].count('\n') + 1

        start = match.end()
        depth = 1
        pos = start
        while pos < len(content) and depth > 0:
            if content[pos] == '{':
                depth += 1
            elif content[pos] == '}':
                depth -= 1
            pos += 1

        if depth == 0:
            body = content[start:pos - 1].strip()
            normalized = re.sub(r'//[^\n]*', '', body)
            normalized = re.sub(r'/\*.*?\*/', '', normalized, flags=re.DOTALL)
            normalized = re.sub(r'\s+', ' ', normalized).strip()

            functions[func_name] = {
                'name': func_name, 'params': params, 'line': line,
                'body': body, 'normalized': normalized, 'size': len(body),
            }

    return functions


# ---- File Discovery --------------------------------------------------

def _discover_php_files(project_root: str, module_filter: str | None = None) -> dict:
    """Find all PHP files in the project."""
    scan_dirs = [
        os.path.join(project_root, 'admin', 'application', 'controllers'),
        os.path.join(project_root, 'admin', 'application', 'models'),
        os.path.join(project_root, 'admin', 'application', 'helpers'),
    ]

    files = {}
    for scan_dir in scan_dirs:
        if not os.path.isdir(scan_dir):
            continue
        for root, _, filenames in os.walk(scan_dir):
            for fname in sorted(filenames):
                if not fname.endswith('.php'):
                    continue
                fpath = os.path.join(root, fname)
                rel_path = os.path.relpath(fpath, project_root)
                module = _detect_module(rel_path)
                if module_filter and module != module_filter:
                    continue
                files[rel_path] = {
                    'path': fpath, 'rel_path': rel_path,
                    'name': fname, 'module': module,
                }

    return files


# ---- Diff Engine -----------------------------------------------------

def diff_codebases(
    base_root: str,
    target_root: str,
    module_filter: str | None = None,
) -> dict:
    """Compare two codebases and return differences.

    Args:
        base_root: Base/reference codebase path
        target_root: Target/client codebase path
        module_filter: Optional module filter

    Returns:
        Results dict with files_compared, modified_files, etc.
    """
    base_files = _discover_php_files(base_root, module_filter)
    target_files = _discover_php_files(target_root, module_filter)

    results = {
        'base_name': os.path.basename(base_root),
        'target_name': os.path.basename(target_root),
        'files_compared': 0,
        'files_identical': 0,
        'files_modified': 0,
        'files_base_only': [],
        'files_target_only': [],
        'modified_files': [],
    }

    # Files only in one side
    for rel_path in sorted(set(base_files.keys()) - set(target_files.keys())):
        results['files_base_only'].append({
            'path': rel_path, 'module': base_files[rel_path]['module'],
        })
    for rel_path in sorted(set(target_files.keys()) - set(base_files.keys())):
        results['files_target_only'].append({
            'path': rel_path, 'module': target_files[rel_path]['module'],
        })

    # Common files
    for rel_path in sorted(set(base_files.keys()) & set(target_files.keys())):
        results['files_compared'] += 1
        base_funcs = _extract_functions(base_files[rel_path]['path'])
        target_funcs = _extract_functions(target_files[rel_path]['path'])

        added = [target_funcs[n] for n in sorted(set(target_funcs) - set(base_funcs))]
        removed = [base_funcs[n] for n in sorted(set(base_funcs) - set(target_funcs))]
        modified = []

        for name in sorted(set(base_funcs) & set(target_funcs)):
            bf, tf = base_funcs[name], target_funcs[name]
            if bf['normalized'] != tf['normalized']:
                sim = SequenceMatcher(None, bf['normalized'], tf['normalized']).ratio()
                modified.append({
                    'name': name, 'base_line': bf['line'], 'target_line': tf['line'],
                    'base_size': bf['size'], 'target_size': tf['size'],
                    'similarity': round(sim * 100, 1),
                    'params_changed': bf['params'] != tf['params'],
                })

        if not added and not removed and not modified:
            results['files_identical'] += 1
        else:
            results['files_modified'] += 1
            results['modified_files'].append({
                'path': rel_path, 'module': base_files[rel_path]['module'],
                'added': added, 'removed': removed, 'modified': modified,
                'total_base_funcs': len(base_funcs),
                'total_target_funcs': len(target_funcs),
            })

    return results


# ---- Report Generator ------------------------------------------------

def generate_diff_report(results: dict) -> str:
    """Generate markdown diff report."""
    lines = [
        '# Cross-Client Code Diff Report', '',
        f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}', '',
        '| Metric | Value |', '|---|---|',
        f'| **Base** | `{results["base_name"]}` |',
        f'| **Target** | `{results["target_name"]}` |',
        f'| **Files compared** | {results["files_compared"]} |',
        f'| **Files identical** | {results["files_identical"]} |',
        f'| **Files modified** | {results["files_modified"]} |',
        f'| **Base only** | {len(results["files_base_only"])} |',
        f'| **Target only** | {len(results["files_target_only"])} |', '',
    ]

    # Module summary
    mod_by_module = defaultdict(lambda: {'added': 0, 'removed': 0, 'modified': 0})
    for mf in results['modified_files']:
        mod_by_module[mf['module']]['added'] += len(mf['added'])
        mod_by_module[mf['module']]['removed'] += len(mf['removed'])
        mod_by_module[mf['module']]['modified'] += len(mf['modified'])

    if mod_by_module:
        lines.extend(['## By Module', '', '| Module | Added | Removed | Modified |', '|---|---|---|---|'])
        for module, counts in sorted(mod_by_module.items(), key=lambda x: sum(x[1].values()), reverse=True):
            lines.append(f'| `{module}` | +{counts["added"]} | -{counts["removed"]} | ~{counts["modified"]} |')
        lines.append('')

    # High-risk files
    high_risk = [mf for mf in results['modified_files'] if len(mf['modified']) >= 3 or len(mf['added']) >= 5]
    if high_risk:
        lines.extend(['## High-Risk Files', '',
                      '| File | Module | Added | Removed | Modified |', '|---|---|---|---|---|'])
        for mf in sorted(high_risk, key=lambda x: len(x['modified']), reverse=True):
            lines.append(f'| `{os.path.basename(mf["path"])}` | `{mf["module"]}` | '
                        f'+{len(mf["added"])} | -{len(mf["removed"])} | ~{len(mf["modified"])} |')
        lines.append('')

    # Detailed diffs
    if results['modified_files']:
        lines.extend(['## Detailed Differences', ''])
        for mf in results['modified_files']:
            lines.extend([f'### `{os.path.basename(mf["path"])}` ({mf["module"]})', ''])
            if mf['modified']:
                lines.extend(['| Function | Similarity | Base Line | Target Line | Params Changed |', '|---|---|---|---|---|'])
                for m in sorted(mf['modified'], key=lambda x: x['similarity']):
                    pflag = 'YES' if m['params_changed'] else 'No'
                    lines.append(f'| `{m["name"]}()` | {m["similarity"]}% | L{m["base_line"]} | L{m["target_line"]} | {pflag} |')
                lines.append('')
            if mf['added']:
                lines.append(f'**Added** (+{len(mf["added"])}):')
                for a in mf['added'][:10]:
                    lines.append(f'- `{a["name"]}()` (L{a["line"]}, {a["size"]}b)')
                lines.append('')
            if mf['removed']:
                lines.append(f'**Removed** (-{len(mf["removed"])}):')
                for r in mf['removed'][:10]:
                    lines.append(f'- ~~`{r["name"]}()`~~ (was L{r["line"]})')
                lines.append('')

    lines.extend(['---', '*Generated by LCA v2*'])
    return '\n'.join(lines)
