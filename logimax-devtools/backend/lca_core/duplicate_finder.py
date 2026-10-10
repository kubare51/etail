#!/usr/bin/env python3
"""
Duplicate Code Finder (C4)
==========================
Finds functions that are 80%+ similar across files in the codebase.

Algorithm:
  1. Extract all functions with their body text
  2. Normalize the body (strip whitespace, comments, variable names)
  3. Compute similarity using SequenceMatcher
  4. Group functions with >80% similarity as duplicates

Output: Report of duplicate function clusters for refactoring.

Usage:
    python duplicate_finder.py --project-root /path/to/project
    python duplicate_finder.py --project-root /path/to/project --threshold 0.85 --output report.md
"""

import argparse
import json
import os
import re
import sys
import time
from collections import defaultdict
from difflib import SequenceMatcher
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from impact_analyzer import detect_module


# ── Function Extraction ───────────────────────────────────────────────

def extract_functions_with_body(filepath):
    """
    Extract all functions with their full body from a PHP file.
    Returns list of dicts: {name, body, line, file, size}
    """
    functions = []
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
    except Exception:
        return functions

    # Find function definitions
    pattern = r'(?:public|private|protected)?\s*(?:static\s+)?function\s+(\w+)\s*\([^)]*\)\s*\{'
    matches = list(re.finditer(pattern, content))

    for i, match in enumerate(matches):
        func_name = match.group(1)
        start = match.end()  # After opening {
        line = content[:match.start()].count('\n') + 1

        # Find matching closing brace
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
            if len(body) > 50:  # Skip trivial functions
                functions.append({
                    'name': func_name,
                    'body': body,
                    'line': line,
                    'size': len(body),
                })

    return functions


def normalize_body(body):
    """
    Normalize function body for comparison:
    - Remove comments
    - Remove variable names (replace $var with $V)
    - Remove string literals
    - Collapse whitespace
    """
    # Remove single-line comments
    text = re.sub(r'//[^\n]*', '', body)
    # Remove multi-line comments
    text = re.sub(r'/\*.*?\*/', '', text, flags=re.DOTALL)
    # Replace string literals with placeholder
    text = re.sub(r"'[^']*'", "'S'", text)
    text = re.sub(r'"[^"]*"', '"S"', text)
    # Replace variable names with generic $V
    text = re.sub(r'\$\w+', '$V', text)
    # Collapse whitespace
    text = re.sub(r'\s+', ' ', text).strip()
    return text


# ── Similarity Computation ────────────────────────────────────────────

def compute_similarity(body1, body2):
    """Compute normalized similarity ratio between two function bodies."""
    return SequenceMatcher(None, body1, body2).ratio()


# ── Main Scanner ──────────────────────────────────────────────────────

def scan_duplicates(project_root, threshold=0.80, module_filter=None):
    """
    Scan codebase for duplicate functions.
    """
    scan_dirs = [
        os.path.join(project_root, 'admin', 'application', 'controllers'),
        os.path.join(project_root, 'admin', 'application', 'models'),
        os.path.join(project_root, 'admin', 'application', 'helpers'),
    ]

    all_functions = []
    files_scanned = 0

    for scan_dir in scan_dirs:
        if not os.path.isdir(scan_dir):
            continue
        for root, _, files in os.walk(scan_dir):
            for fname in sorted(files):
                if not fname.endswith('.php'):
                    continue
                fpath = os.path.join(root, fname)
                rel_path = os.path.relpath(fpath, project_root)
                module = detect_module(rel_path)

                if module_filter and module != module_filter:
                    continue

                files_scanned += 1
                funcs = extract_functions_with_body(fpath)
                for f in funcs:
                    f['file'] = rel_path
                    f['module'] = module
                    f['normalized'] = normalize_body(f['body'])
                    all_functions.append(f)

    print(f'Extracted {len(all_functions)} functions from {files_scanned} files',
          file=sys.stderr)

    # Compare functions — O(n^2) but we can prune by size
    # Only compare functions within 50% size of each other
    duplicates = []
    seen = set()

    # Sort by normalized body length for faster pruning
    all_functions.sort(key=lambda x: len(x['normalized']))

    for i in range(len(all_functions)):
        if i in seen:
            continue
        cluster = [all_functions[i]]

        for j in range(i + 1, len(all_functions)):
            if j in seen:
                continue

            # Size pruning — if normalized body differs by >50%, skip
            len_i = len(all_functions[i]['normalized'])
            len_j = len(all_functions[j]['normalized'])
            if len_j > len_i * 1.5:
                break  # Sorted, so all subsequent will be larger

            if len_i < 100:  # Skip very short functions
                continue

            # Compute similarity
            sim = compute_similarity(
                all_functions[i]['normalized'],
                all_functions[j]['normalized']
            )

            if sim >= threshold:
                # Skip if same function name in same file (overloaded)
                if (all_functions[i]['file'] == all_functions[j]['file'] and
                    all_functions[i]['name'] == all_functions[j]['name']):
                    continue

                cluster.append(all_functions[j])
                seen.add(j)

        if len(cluster) > 1:
            seen.add(i)
            # Compute avg similarity for the cluster
            sims = []
            for a in range(len(cluster)):
                for b in range(a+1, len(cluster)):
                    sims.append(compute_similarity(
                        cluster[a]['normalized'],
                        cluster[b]['normalized']
                    ))
            avg_sim = sum(sims) / len(sims) if sims else 0

            duplicates.append({
                'functions': [{
                    'name': f['name'],
                    'file': f['file'],
                    'module': f['module'],
                    'line': f['line'],
                    'size': f['size'],
                } for f in cluster],
                'similarity': round(avg_sim * 100, 1),
                'count': len(cluster),
                'cross_module': len(set(f['module'] for f in cluster)) > 1,
                'cross_file': len(set(f['file'] for f in cluster)) > 1,
            })

    # Sort by similarity descending
    duplicates.sort(key=lambda x: (-x['similarity'], -x['count']))

    results = {
        'files_scanned': files_scanned,
        'total_functions': len(all_functions),
        'duplicate_clusters': len(duplicates),
        'duplicate_functions': sum(d['count'] for d in duplicates),
        'cross_module_duplicates': sum(1 for d in duplicates if d['cross_module']),
        'cross_file_duplicates': sum(1 for d in duplicates if d['cross_file']),
        'duplicates': duplicates,
    }

    return results


# ── Report Generation ─────────────────────────────────────────────────

def generate_report(results, threshold):
    """Generate markdown duplicate code report."""
    lines = []

    lines.append('# Duplicate Code Report')
    lines.append('')
    lines.append(f'| Metric | Value |')
    lines.append(f'|---|---|')
    lines.append(f'| **Files scanned** | {results["files_scanned"]} |')
    lines.append(f'| **Functions analyzed** | {results["total_functions"]} |')
    lines.append(f'| **Duplicate clusters** | {results["duplicate_clusters"]} |')
    lines.append(f'| **Functions in duplicates** | {results["duplicate_functions"]} |')
    lines.append(f'| **Cross-module duplicates** | {results["cross_module_duplicates"]} |')
    lines.append(f'| **Similarity threshold** | {int(threshold*100)}% |')
    lines.append('')

    if not results['duplicates']:
        lines.append('No duplicate functions found above threshold.')
        return '\n'.join(lines)

    # Cross-module duplicates first (highest risk)
    cross_mod = [d for d in results['duplicates'] if d['cross_module']]
    if cross_mod:
        lines.append('## Cross-Module Duplicates (Highest Risk)')
        lines.append('')
        lines.append('> These functions are duplicated across different modules. Fix one copy and the other stays broken.')
        lines.append('')
        for i, dup in enumerate(cross_mod[:20], 1):
            lines.append(f'### Cluster {i} ({dup["similarity"]}% similar, {dup["count"]} copies)')
            lines.append('')
            lines.append('| Function | File | Module | Line | Size |')
            lines.append('|---|---|---|---|---|')
            for f in dup['functions']:
                lines.append(f'| `{f["name"]}()` | `{os.path.basename(f["file"])}` | {f["module"]} | L{f["line"]} | {f["size"]}b |')
            lines.append('')
        lines.append('')

    # Same-module duplicates
    same_mod = [d for d in results['duplicates'] if not d['cross_module']]
    if same_mod:
        lines.append('<details>')
        lines.append(f'<summary>Same-Module Duplicates ({len(same_mod)} clusters)</summary>')
        lines.append('')
        for i, dup in enumerate(same_mod[:30], 1):
            lines.append(f'**Cluster {i}** ({dup["similarity"]}% similar)')
            lines.append('')
            lines.append('| Function | File | Module | Line |')
            lines.append('|---|---|---|---|')
            for f in dup['functions']:
                lines.append(f'| `{f["name"]}()` | `{os.path.basename(f["file"])}` | {f["module"]} | L{f["line"]} |')
            lines.append('')
        lines.append('</details>')
        lines.append('')

    lines.append('---')
    lines.append('*Generated by Logimax Duplicate Code Finder (LCA)*')

    return '\n'.join(lines)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Duplicate Code Finder')
    parser.add_argument('--project-root', required=True, help='Project root directory')
    parser.add_argument('--threshold', type=float, default=0.80,
                        help='Similarity threshold (0.0-1.0, default: 0.80)')
    parser.add_argument('--module', help='Filter to specific module')
    parser.add_argument('--output', help='Output markdown report file')
    parser.add_argument('--json-output', help='Output JSON results file')

    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)

    print(f'Scanning for duplicate code in {project_root}...', file=sys.stderr)
    print(f'Similarity threshold: {int(args.threshold*100)}%', file=sys.stderr)
    t_start = time.time()

    results = scan_duplicates(project_root, args.threshold, args.module)

    elapsed = time.time() - t_start
    print(f'Done in {elapsed:.1f}s -- {results["duplicate_clusters"]} duplicate clusters, '
          f'{results["duplicate_functions"]} functions involved', file=sys.stderr)

    report = generate_report(results, args.threshold)

    if args.output:
        Path(args.output).write_text(report, encoding='utf-8')
        print(f'Report written to {args.output}', file=sys.stderr)
    else:
        if sys.platform == 'win32':
            sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        print(report)

    if args.json_output:
        Path(args.json_output).write_text(
            json.dumps(results, indent=2, default=str),
            encoding='utf-8'
        )

    sys.exit(0)


if __name__ == '__main__':
    main()
