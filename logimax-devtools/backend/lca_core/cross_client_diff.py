#!/usr/bin/env python3
"""
Cross-Client Diff Tool (C7)
==============================
Compares two client codebases and reports differences.

For each PHP file that exists in both codebases:
  1. Identifies added/removed/modified functions
  2. Shows which functions have client-specific overrides
  3. Flags customizations that could break auto-fix recipes

Usage:
    python cross_client_diff.py --base /path/to/client_A --target /path/to/client_B
    python cross_client_diff.py --base /path/to/client_A --target /path/to/client_B --module billing
    python cross_client_diff.py --base /path/to/client_A --target /path/to/client_B --output diff_report.md
"""

import argparse
import os
import re
import sys
import time
from collections import defaultdict
from pathlib import Path
from datetime import datetime
from difflib import SequenceMatcher

sys.path.insert(0, str(Path(__file__).parent))
from impact_analyzer import detect_module


# ── Function Extraction ───────────────────────────────────────────────

def extract_functions(filepath):
    """Extract all functions from a PHP file with their normalized body."""
    functions = {}
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
    except Exception:
        return functions

    pattern = r'(?:public|private|protected)?\s*(?:static\s+)?function\s+(\w+)\s*\(([^)]*)\)\s*\{'
    matches = list(re.finditer(pattern, content))

    for match in matches:
        func_name = match.group(1)
        params = match.group(2).strip()
        line = content[:match.start()].count('\n') + 1

        # Find matching closing brace
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
            body = content[start:pos-1].strip()
            # Normalize for comparison (strip comments and extra whitespace)
            normalized = re.sub(r'//[^\n]*', '', body)
            normalized = re.sub(r'/\*.*?\*/', '', normalized, flags=re.DOTALL)
            normalized = re.sub(r'\s+', ' ', normalized).strip()

            functions[func_name] = {
                'name': func_name,
                'params': params,
                'line': line,
                'body': body,
                'normalized': normalized,
                'size': len(body),
            }

    return functions


# ── File Discovery ────────────────────────────────────────────────────

def discover_php_files(project_root, module_filter=None):
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
                module = detect_module(rel_path)

                if module_filter and module != module_filter:
                    continue

                files[rel_path] = {
                    'path': fpath,
                    'rel_path': rel_path,
                    'name': fname,
                    'module': module,
                }

    return files


# ── Diff Engine ───────────────────────────────────────────────────────

def diff_files(base_root, target_root, module_filter=None):
    """Compare two codebases and return differences."""
    base_files = discover_php_files(base_root, module_filter)
    target_files = discover_php_files(target_root, module_filter)

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

    # Files only in base
    for rel_path in sorted(set(base_files.keys()) - set(target_files.keys())):
        results['files_base_only'].append({
            'path': rel_path,
            'module': base_files[rel_path]['module'],
        })

    # Files only in target
    for rel_path in sorted(set(target_files.keys()) - set(base_files.keys())):
        results['files_target_only'].append({
            'path': rel_path,
            'module': target_files[rel_path]['module'],
        })

    # Common files — compare functions
    common_files = sorted(set(base_files.keys()) & set(target_files.keys()))
    for rel_path in common_files:
        results['files_compared'] += 1

        base_funcs = extract_functions(base_files[rel_path]['path'])
        target_funcs = extract_functions(target_files[rel_path]['path'])

        added = []
        removed = []
        modified = []

        # Functions only in base (removed in target)
        for name in sorted(set(base_funcs.keys()) - set(target_funcs.keys())):
            removed.append(base_funcs[name])

        # Functions only in target (added)
        for name in sorted(set(target_funcs.keys()) - set(base_funcs.keys())):
            added.append(target_funcs[name])

        # Common functions — check for modifications
        for name in sorted(set(base_funcs.keys()) & set(target_funcs.keys())):
            bf = base_funcs[name]
            tf = target_funcs[name]

            if bf['normalized'] != tf['normalized']:
                sim = SequenceMatcher(None, bf['normalized'], tf['normalized']).ratio()
                modified.append({
                    'name': name,
                    'base_line': bf['line'],
                    'target_line': tf['line'],
                    'base_size': bf['size'],
                    'target_size': tf['size'],
                    'similarity': round(sim * 100, 1),
                    'params_changed': bf['params'] != tf['params'],
                })

        if not added and not removed and not modified:
            results['files_identical'] += 1
        else:
            results['files_modified'] += 1
            results['modified_files'].append({
                'path': rel_path,
                'module': base_files[rel_path]['module'],
                'added': added,
                'removed': removed,
                'modified': modified,
                'total_base_funcs': len(base_funcs),
                'total_target_funcs': len(target_funcs),
            })

    return results


# ── Report Generator ──────────────────────────────────────────────────

def generate_report(results):
    """Generate markdown diff report."""
    lines = [
        '# Cross-Client Code Diff Report',
        '',
        f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
        f'| Metric | Value |',
        f'|---|---|',
        f'| **Base** | `{results["base_name"]}` |',
        f'| **Target** | `{results["target_name"]}` |',
        f'| **Files compared** | {results["files_compared"]} |',
        f'| **Files identical** | {results["files_identical"]} |',
        f'| **Files modified** | {results["files_modified"]} |',
        f'| **Files only in base** | {len(results["files_base_only"])} |',
        f'| **Files only in target** | {len(results["files_target_only"])} |',
        '',
    ]

    # Summary of modifications by module
    mod_by_module = defaultdict(lambda: {'added': 0, 'removed': 0, 'modified': 0})
    for mf in results['modified_files']:
        mod_by_module[mf['module']]['added'] += len(mf['added'])
        mod_by_module[mf['module']]['removed'] += len(mf['removed'])
        mod_by_module[mf['module']]['modified'] += len(mf['modified'])

    if mod_by_module:
        lines.extend([
            '## Modification Summary by Module',
            '',
            '| Module | Added | Removed | Modified |',
            '|---|---|---|---|',
        ])
        for module, counts in sorted(mod_by_module.items(),
                                     key=lambda x: sum(x[1].values()), reverse=True):
            lines.append(f'| `{module}` | +{counts["added"]} | -{counts["removed"]} | ~{counts["modified"]} |')
        lines.append('')

    # Heavily modified files (most risk for auto-fix recipes)
    high_risk = [mf for mf in results['modified_files']
                 if len(mf['modified']) >= 3 or len(mf['added']) >= 5]
    if high_risk:
        lines.extend([
            '## ⚠️ High-Risk Files for Auto-Fix',
            '',
            '> These files have significant differences. Auto-fix recipes may not apply cleanly.',
            '',
            '| File | Module | Added | Removed | Modified |',
            '|---|---|---|---|---|',
        ])
        for mf in sorted(high_risk, key=lambda x: len(x['modified']), reverse=True):
            fname = os.path.basename(mf['path'])
            lines.append(f'| `{fname}` | `{mf["module"]}` | +{len(mf["added"])} | -{len(mf["removed"])} | ~{len(mf["modified"])} |')
        lines.append('')

    # Detailed modified files
    if results['modified_files']:
        lines.extend([
            '## Detailed Function Differences',
            '',
        ])

        for mf in results['modified_files']:
            fname = os.path.basename(mf['path'])
            lines.append(f'### `{fname}` ({mf["module"]})')
            lines.append('')

            if mf['modified']:
                lines.append('**Modified functions:**')
                lines.append('')
                lines.append('| Function | Similarity | Base Line | Target Line | Params Changed |')
                lines.append('|---|---|---|---|---|')
                for m in sorted(mf['modified'], key=lambda x: x['similarity']):
                    params_flag = '⚠️ YES' if m['params_changed'] else 'No'
                    sim_color = '🔴' if m['similarity'] < 70 else ('🟡' if m['similarity'] < 90 else '🟢')
                    lines.append(f'| `{m["name"]}()` | {sim_color} {m["similarity"]}% | L{m["base_line"]} | L{m["target_line"]} | {params_flag} |')
                lines.append('')

            if mf['added']:
                lines.append(f'**Added in target** (+{len(mf["added"])} functions):')
                lines.append('')
                for a in mf['added'][:10]:
                    lines.append(f'- `{a["name"]}()` (L{a["line"]}, {a["size"]}b)')
                if len(mf['added']) > 10:
                    lines.append(f'- ...and {len(mf["added"])-10} more')
                lines.append('')

            if mf['removed']:
                lines.append(f'**Removed from base** (-{len(mf["removed"])} functions):')
                lines.append('')
                for r in mf['removed'][:10]:
                    lines.append(f'- ~~`{r["name"]}()`~~ (was at L{r["line"]})')
                if len(mf['removed']) > 10:
                    lines.append(f'- ...and {len(mf["removed"])-10} more')
                lines.append('')

    # Files only in base
    if results['files_base_only']:
        lines.extend([
            '<details>',
            f'<summary>Files only in base ({len(results["files_base_only"])})</summary>',
            '',
        ])
        for f in results['files_base_only']:
            lines.append(f'- `{f["path"]}` ({f["module"]})')
        lines.extend(['', '</details>', ''])

    # Files only in target
    if results['files_target_only']:
        lines.extend([
            '<details>',
            f'<summary>Files only in target ({len(results["files_target_only"])})</summary>',
            '',
        ])
        for f in results['files_target_only']:
            lines.append(f'- `{f["path"]}` ({f["module"]})')
        lines.extend(['', '</details>', ''])

    lines.extend([
        '---',
        '*Generated by Logimax Cross-Client Diff Tool (LCA)*',
    ])

    return '\n'.join(lines)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Cross-Client Diff Tool')
    parser.add_argument('--base', required=True, help='Base project root (reference codebase)')
    parser.add_argument('--target', required=True, help='Target project root (client codebase)')
    parser.add_argument('--module', help='Filter to specific module')
    parser.add_argument('--output', help='Output markdown report file')

    args = parser.parse_args()
    base_root = os.path.abspath(args.base)
    target_root = os.path.abspath(args.target)

    print(f'Cross-Client Diff', file=sys.stderr)
    print(f'  Base:   {base_root}', file=sys.stderr)
    print(f'  Target: {target_root}', file=sys.stderr)
    if args.module:
        print(f'  Module: {args.module}', file=sys.stderr)
    print('', file=sys.stderr)

    t_start = time.time()
    results = diff_files(base_root, target_root, args.module)
    elapsed = time.time() - t_start

    print(f'Done in {elapsed:.1f}s', file=sys.stderr)
    print(f'  Files compared:  {results["files_compared"]}', file=sys.stderr)
    print(f'  Files identical: {results["files_identical"]}', file=sys.stderr)
    print(f'  Files modified:  {results["files_modified"]}', file=sys.stderr)

    report = generate_report(results)

    if args.output:
        Path(args.output).parent.mkdir(parents=True, exist_ok=True)
        Path(args.output).write_text(report, encoding='utf-8')
        print(f'Report written to {args.output}', file=sys.stderr)
    else:
        if sys.platform == 'win32':
            sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        print(report)

    sys.exit(0)


if __name__ == '__main__':
    main()
