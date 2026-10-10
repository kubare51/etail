#!/usr/bin/env python3
"""
Dead Code Detector (C2)
=======================
Scans the entire PHP codebase, finds functions with zero callers.

For each function in models/helpers:
  1. Extracts the function definition
  2. Searches for callers across the codebase
  3. Functions with zero callers = dead code candidates

Exclusions (NOT flagged as dead):
  - Controller public methods (entry points via URL routing)
  - Magic methods (__construct, __destruct, etc.)
  - CI3 lifecycle methods (index, _remap, _output)
  - Hook callbacks
  - Private methods that are called within the same file

Usage:
    python dead_code_detector.py --project-root /path/to/project
    python dead_code_detector.py --project-root /path/to/project --output report.md --json-output results.json
    python dead_code_detector.py --project-root /path/to/project --module billing
"""

import argparse
import json
import os
import re
import sys
import time
from collections import defaultdict
from pathlib import Path

# Reuse from impact analyzer
sys.path.insert(0, str(Path(__file__).parent))
from impact_analyzer import detect_module, find_callers, CRITICAL_MODULES


# ── Exclusion Lists ──────────────────────────────────────────────────

# CI3 lifecycle / magic methods -- never flag these
EXCLUDED_METHODS = {
    '__construct', '__destruct', '__get', '__set', '__call',
    '__toString', '__clone', '__invoke', '__debugInfo',
    'index', '_remap', '_output',
}

# Methods that are entry points (called by CI3 routing, cron, AJAX)
# Controller public methods are detected automatically, but these
# are common names that might appear in models too
ENTRY_POINT_PATTERNS = [
    r'^(get|set|is|has)_',   # Getters/setters are often called dynamically
]

# File patterns to skip entirely
SKIP_FILES = {
    'MY_Controller.php', 'MY_Model.php', 'MY_Loader.php',
    'MY_Router.php', 'MY_Input.php', 'MY_Security.php',
}


# ── Function Extraction ───────────────────────────────────────────────

def extract_functions_with_visibility(filepath):
    """
    Extract all function/method definitions with their visibility.
    Returns list of dicts: {name, visibility, line, is_static}
    """
    functions = []
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
        
        pattern = r'(public|private|protected)?\s*(static)?\s*function\s+(\w+)\s*\('
        for match in re.finditer(pattern, content):
            visibility = match.group(1) or 'public'  # PHP defaults to public
            is_static = match.group(2) is not None
            func_name = match.group(3)
            line = content[:match.start()].count('\n') + 1
            
            functions.append({
                'name': func_name,
                'visibility': visibility,
                'is_static': is_static,
                'line': line,
            })
    except Exception:
        pass
    return functions


def is_called_internally(filepath, func_name):
    """Check if a private/protected method is called within its own file."""
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
        
        # Count calls to this function (excluding its definition)
        # Match: $this->func_name( or self::func_name( or static::func_name(
        call_patterns = [
            rf'\$this\s*->\s*{re.escape(func_name)}\s*\(',
            rf'self\s*::\s*{re.escape(func_name)}\s*\(',
            rf'static\s*::\s*{re.escape(func_name)}\s*\(',
        ]
        
        for pattern in call_patterns:
            matches = list(re.finditer(pattern, content))
            if matches:
                return True
        
        return False
    except Exception:
        return False


# ── Main Scanner ──────────────────────────────────────────────────────

def scan_dead_code(project_root, module_filter=None):
    """
    Scan entire codebase for dead functions.
    Returns dict with dead code candidates organized by file type and module.
    """
    dirs_to_scan = {
        'models': os.path.join(project_root, 'admin', 'application', 'models'),
        'helpers': os.path.join(project_root, 'admin', 'application', 'helpers'),
        'controllers': os.path.join(project_root, 'admin', 'application', 'controllers'),
    }
    
    # Directories to search for callers
    search_dirs = [
        os.path.join(project_root, 'admin', 'application', 'controllers'),
        os.path.join(project_root, 'admin', 'application', 'models'),
        os.path.join(project_root, 'admin', 'application', 'helpers'),
        os.path.join(project_root, 'admin', 'application', 'views'),
    ]
    
    results = {
        'total_functions': 0,
        'dead_functions': 0,
        'dead_by_module': defaultdict(list),
        'dead_by_type': defaultdict(list),
        'skipped': 0,
        'scanned_files': 0,
        'dead_list': [],
        'summary_by_file': [],
    }
    
    file_dead_counts = defaultdict(lambda: {'total': 0, 'dead': 0, 'dead_funcs': []})
    
    for file_type, scan_dir in dirs_to_scan.items():
        if not os.path.isdir(scan_dir):
            continue
        
        for root, _, files in os.walk(scan_dir):
            for fname in sorted(files):
                if not fname.endswith('.php'):
                    continue
                if fname in SKIP_FILES:
                    continue
                
                fpath = os.path.join(root, fname)
                rel_path = os.path.relpath(fpath, project_root)
                module = detect_module(rel_path)
                
                # Module filter
                if module_filter and module != module_filter:
                    continue
                
                results['scanned_files'] += 1
                functions = extract_functions_with_visibility(fpath)
                
                for func in functions:
                    func_name = func['name']
                    
                    # Skip excluded methods
                    if func_name in EXCLUDED_METHODS:
                        results['skipped'] += 1
                        continue
                    if func_name.startswith('__'):
                        results['skipped'] += 1
                        continue
                    
                    # Skip controller public methods (they are URL entry points)
                    if file_type == 'controllers' and func['visibility'] == 'public':
                        results['skipped'] += 1
                        continue
                    
                    # Skip controller private methods that start with _ (CI3 convention for non-routable)
                    # But still check if they are called
                    
                    results['total_functions'] += 1
                    file_dead_counts[rel_path]['total'] += 1
                    
                    # Check internal calls first (fast path)
                    if func['visibility'] in ('private', 'protected'):
                        if is_called_internally(fpath, func_name):
                            continue
                    
                    # Check external callers (slow path)
                    callers = find_callers(
                        project_root, func_name, fpath,
                        file_type='model' if file_type == 'models' else 'other',
                        php_dirs=search_dirs
                    )
                    
                    if not callers:
                        # Also check if called internally for public methods
                        if func['visibility'] == 'public' and is_called_internally(fpath, func_name):
                            continue
                        
                        # Dead code found
                        results['dead_functions'] += 1
                        dead_item = {
                            'function': func_name,
                            'file': rel_path,
                            'file_type': file_type,
                            'module': module,
                            'line': func['line'],
                            'visibility': func['visibility'],
                            'is_static': func['is_static'],
                        }
                        results['dead_list'].append(dead_item)
                        results['dead_by_module'][module].append(dead_item)
                        results['dead_by_type'][file_type].append(dead_item)
                        file_dead_counts[rel_path]['dead'] += 1
                        file_dead_counts[rel_path]['dead_funcs'].append(func_name)
    
    # Build file summary (sorted by dead count descending)
    for fpath, counts in sorted(file_dead_counts.items(), key=lambda x: x[1]['dead'], reverse=True):
        if counts['dead'] > 0:
            results['summary_by_file'].append({
                'file': fpath,
                'total_functions': counts['total'],
                'dead_functions': counts['dead'],
                'dead_pct': round(counts['dead'] / max(counts['total'], 1) * 100, 1),
                'functions': counts['dead_funcs'],
            })
    
    # Convert defaultdicts for JSON
    results['dead_by_module'] = dict(results['dead_by_module'])
    results['dead_by_type'] = dict(results['dead_by_type'])
    
    return results


# ── Report Generation ─────────────────────────────────────────────────

def generate_report(results):
    """Generate markdown dead code report."""
    lines = []
    
    total = results['total_functions']
    dead = results['dead_functions']
    pct = round(dead / max(total, 1) * 100, 1)
    
    lines.append('# Dead Code Report')
    lines.append('')
    lines.append(f'| Metric | Value |')
    lines.append(f'|---|---|')
    lines.append(f'| **Files scanned** | {results["scanned_files"]} |')
    lines.append(f'| **Functions analyzed** | {total} |')
    lines.append(f'| **Dead functions** | {dead} ({pct}%) |')
    lines.append(f'| **Skipped** (entry points, magic methods) | {results["skipped"]} |')
    lines.append('')
    
    if dead == 0:
        lines.append('No dead code found.')
        return '\n'.join(lines)
    
    # Module breakdown
    lines.append('## By Module')
    lines.append('')
    lines.append('| Module | Dead Functions | Files |')
    lines.append('|---|---|---|')
    for module, items in sorted(results['dead_by_module'].items(), 
                                 key=lambda x: len(x[1]), reverse=True):
        files = len(set(i['file'] for i in items))
        critical = ' \U0001f6a8' if module in CRITICAL_MODULES else ''
        lines.append(f'| {module}{critical} | {len(items)} | {files} |')
    lines.append('')
    
    # Worst files
    lines.append('## Worst Files (Most Dead Code)')
    lines.append('')
    lines.append('| File | Dead / Total | % Dead | Functions |')
    lines.append('|---|---|---|---|')
    for f in results['summary_by_file'][:20]:
        funcs = ', '.join(f'`{fn}`' for fn in f['functions'][:5])
        if len(f['functions']) > 5:
            funcs += f' +{len(f["functions"])-5} more'
        lines.append(f'| `{os.path.basename(f["file"])}` | {f["dead_functions"]}/{f["total_functions"]} | {f["dead_pct"]}% | {funcs} |')
    lines.append('')
    
    # Full dead function list (collapsible)
    lines.append('<details>')
    lines.append(f'<summary>Full dead function list ({dead} functions)</summary>')
    lines.append('')
    lines.append('| Function | File | Module | Line | Visibility |')
    lines.append('|---|---|---|---|---|')
    for item in sorted(results['dead_list'], key=lambda x: (x['module'], x['file'])):
        lines.append(f'| `{item["function"]}()` | `{os.path.basename(item["file"])}` | {item["module"]} | L{item["line"]} | {item["visibility"]} |')
    lines.append('')
    lines.append('</details>')
    lines.append('')
    
    lines.append('---')
    lines.append('*Generated by Logimax Dead Code Detector (LCA)*')
    
    return '\n'.join(lines)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Dead Code Detector')
    parser.add_argument('--project-root', required=True, help='Project root directory')
    parser.add_argument('--module', help='Filter to specific module (e.g., billing)')
    parser.add_argument('--output', help='Output markdown report file')
    parser.add_argument('--json-output', help='Output JSON results file')
    
    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)
    
    print(f'Scanning for dead code in {project_root}...', file=sys.stderr)
    t_start = time.time()
    
    results = scan_dead_code(project_root, args.module)
    
    elapsed = time.time() - t_start
    total = results['total_functions']
    dead = results['dead_functions']
    pct = round(dead / max(total, 1) * 100, 1)
    
    print(f'Done in {elapsed:.1f}s -- {results["scanned_files"]} files, '
          f'{total} functions, {dead} dead ({pct}%)', file=sys.stderr)
    
    report = generate_report(results)
    
    if args.output:
        Path(args.output).write_text(report, encoding='utf-8')
        print(f'Report written to {args.output}', file=sys.stderr)
    else:
        # Ensure UTF-8 output on Windows
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
