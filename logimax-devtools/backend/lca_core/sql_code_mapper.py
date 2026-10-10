#!/usr/bin/env python3
"""
SQL-to-Code Mapper (C3)
=======================
Extracts all database table references from PHP code and maps them
to the functions/files that read or write each table.

For each PHP file in models/controllers/helpers:
  1. Parses $this->db-> calls (from, join, where, insert, update, delete)
  2. Parses raw SQL queries ($this->db->query())
  3. Extracts table names referenced
  4. Maps table -> [{file, function, operation, line}]

Output: A comprehensive table-to-code mapping for:
  - Impact analysis: "If I change table X, what code breaks?"
  - Schema safety: "Before ALTERing this table, who uses it?"
  - Audit: "Which tables have no code referencing them?"

Usage:
    python sql_code_mapper.py --project-root /path/to/project
    python sql_code_mapper.py --project-root /path/to/project --table ret_billing
    python sql_code_mapper.py --project-root /path/to/project --output report.md --json-output map.json
"""

import argparse
import json
import os
import re
import sys
import time
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from impact_analyzer import detect_module


# ── Table Extraction Patterns ─────────────────────────────────────────

# CI3 Active Record patterns that reference table names
# $this->db->from('table'), $this->db->join('table', ...), etc.
AR_TABLE_PATTERNS = [
    # Direct table references
    (r"""\$this\s*->\s*db\s*->\s*(?:from|get|get_where)\s*\(\s*['"](\w+)['"]""", 'READ'),
    (r"""\$this\s*->\s*db\s*->\s*(?:join|left_join)\s*\(\s*['"](\w+)['"]""", 'READ'),
    (r"""\$this\s*->\s*db\s*->\s*insert(?:_batch)?\s*\(\s*['"](\w+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*update(?:_batch)?\s*\(\s*['"](\w+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*delete\s*\(\s*['"](\w+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*replace\s*\(\s*['"](\w+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*empty_table\s*\(\s*['"](\w+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*truncate\s*\(\s*['"](\w+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*count_all\s*\(\s*['"](\w+)['"]""", 'READ'),
    (r"""\$this\s*->\s*db\s*->\s*count_all_results\s*\(\s*['"](\w+)['"]""", 'READ'),
]

# Raw SQL patterns: FROM table, JOIN table, INSERT INTO table, UPDATE table, DELETE FROM table
RAW_SQL_PATTERNS = [
    (r"""\bFROM\s+[`'"]?(\w+)[`'"]?""", 'READ'),
    (r"""\bJOIN\s+[`'"]?(\w+)[`'"]?""", 'READ'),
    (r"""\bINSERT\s+INTO\s+[`'"]?(\w+)[`'"]?""", 'WRITE'),
    (r"""\bUPDATE\s+[`'"]?(\w+)[`'"]?""", 'WRITE'),
    (r"""\bDELETE\s+FROM\s+[`'"]?(\w+)[`'"]?""", 'WRITE'),
    (r"""\bTRUNCATE\s+(?:TABLE\s+)?[`'"]?(\w+)[`'"]?""", 'WRITE'),
    (r"""\bALTER\s+TABLE\s+[`'"]?(\w+)[`'"]?""", 'SCHEMA'),
    (r"""\bDROP\s+TABLE\s+[`'"]?(\w+)[`'"]?""", 'SCHEMA'),
    (r"""\bCREATE\s+TABLE\s+[`'"]?(\w+)[`'"]?""", 'SCHEMA'),
]

# Tables that are NOT application tables (skip these)
SKIP_TABLES = {
    'as', 'on', 'and', 'or', 'set', 'where', 'select', 'from',
    'join', 'left', 'right', 'inner', 'outer', 'group', 'order',
    'limit', 'having', 'union', 'not', 'null', 'true', 'false',
    'values', 'into', 'table', 'index', 'key', 'primary',
    'if', 'then', 'else', 'end', 'case', 'when', 'like',
    'in', 'between', 'exists', 'all', 'any', 'is', 'by',
    'asc', 'desc', 'distinct', 'count', 'sum', 'avg', 'max', 'min',
}


# ── Function Context Detection ────────────────────────────────────────

def build_function_map(content):
    """
    Precompute function boundaries for a file.
    Returns a sorted list of (start_offset, func_name) for binary search.
    """
    pattern = r'(?:public|private|protected|static)?\s*function\s+(\w+)\s*\('
    func_starts = []
    for match in re.finditer(pattern, content):
        func_starts.append((match.start(), match.group(1)))
    return func_starts


def find_function_at_offset(func_map, offset):
    """Find which function contains a given character offset using precomputed map."""
    result = '(top-level)'
    for start, name in func_map:
        if start <= offset:
            result = name
        else:
            break
    return result


def build_line_offsets(content):
    """Precompute cumulative character offset for each line number."""
    offsets = [0]
    for i, ch in enumerate(content):
        if ch == '\n':
            offsets.append(i + 1)
    return offsets


# ── File Scanner ──────────────────────────────────────────────────────

def scan_file(filepath, project_root):
    """Scan a single PHP file for table references."""
    refs = []
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
    except Exception:
        return refs
    
    rel_path = os.path.relpath(filepath, project_root)
    module = detect_module(rel_path)
    
    # Determine file type
    if 'controllers/' in rel_path:
        file_type = 'controller'
    elif 'models/' in rel_path:
        file_type = 'model'
    elif 'helpers/' in rel_path:
        file_type = 'helper'
    else:
        file_type = 'other'
    
    # Precompute function map and line offsets (O(n) once, not per-match)
    func_map = build_function_map(content)
    line_offsets = build_line_offsets(content)
    
    # Scan Active Record patterns
    for pattern, operation in AR_TABLE_PATTERNS:
        for match in re.finditer(pattern, content, re.IGNORECASE):
            table = match.group(1).lower()
            if table in SKIP_TABLES or len(table) < 3:
                continue
            
            line = content[:match.start()].count('\n') + 1
            func = find_function_at_offset(func_map, match.start())
            
            refs.append({
                'table': table,
                'operation': operation,
                'file': rel_path,
                'file_type': file_type,
                'module': module,
                'function': func,
                'line': line,
                'method': 'active_record',
            })
    
    # Scan raw SQL patterns line-by-line (avoid DOTALL catastrophic backtracking)
    lines_list = content.split('\n')
    for line_num, line_text in enumerate(lines_list, 1):
        # Only scan lines that contain SQL keywords
        line_upper = line_text.upper()
        if not any(kw in line_upper for kw in ('FROM', 'JOIN', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'ALTER', 'DROP', 'CREATE')):
            continue
        for pattern, operation in RAW_SQL_PATTERNS:
            for match in re.finditer(pattern, line_text, re.IGNORECASE):
                table = match.group(1).lower()
                if table in SKIP_TABLES or len(table) < 3:
                    continue
                offset = line_offsets[line_num - 1] if line_num - 1 < len(line_offsets) else 0
                func = find_function_at_offset(func_map, offset)
                refs.append({
                    'table': table,
                    'operation': operation,
                    'file': rel_path,
                    'file_type': file_type,
                    'module': module,
                    'function': func,
                    'line': line_num,
                    'method': 'raw_sql',
                })
    
    return refs


# ── Main Scanner ──────────────────────────────────────────────────────

def scan_all(project_root, table_filter=None):
    """Scan entire codebase and build table-to-code mapping."""
    scan_dirs = [
        os.path.join(project_root, 'admin', 'application', 'controllers'),
        os.path.join(project_root, 'admin', 'application', 'models'),
        os.path.join(project_root, 'admin', 'application', 'helpers'),
    ]
    
    all_refs = []
    files_scanned = 0
    
    for scan_dir in scan_dirs:
        if not os.path.isdir(scan_dir):
            continue
        for root, _, files in os.walk(scan_dir):
            for fname in sorted(files):
                if not fname.endswith('.php'):
                    continue
                fpath = os.path.join(root, fname)
                files_scanned += 1
                refs = scan_file(fpath, project_root)
                all_refs.extend(refs)
    
    # Build table -> references mapping
    table_map = defaultdict(lambda: {
        'references': [],
        'modules': set(),
        'files': set(),
        'operations': set(),
        'read_count': 0,
        'write_count': 0,
        'schema_count': 0,
    })
    
    for ref in all_refs:
        table = ref['table']
        if table_filter and table_filter.lower() not in table:
            continue
        
        entry = table_map[table]
        entry['references'].append(ref)
        entry['modules'].add(ref['module'])
        entry['files'].add(ref['file'])
        entry['operations'].add(ref['operation'])
        if ref['operation'] == 'READ':
            entry['read_count'] += 1
        elif ref['operation'] == 'WRITE':
            entry['write_count'] += 1
        elif ref['operation'] == 'SCHEMA':
            entry['schema_count'] += 1
    
    # Build results
    results = {
        'files_scanned': files_scanned,
        'total_references': len(all_refs),
        'unique_tables': len(table_map),
        'tables': {},
        'cross_module_tables': [],
        'write_heavy_tables': [],
        'orphan_risk_tables': [],
    }
    
    for table, data in sorted(table_map.items()):
        modules = sorted(data['modules'])
        files = sorted(data['files'])
        
        table_info = {
            'table': table,
            'modules': modules,
            'files': files,
            'operations': sorted(data['operations']),
            'read_count': data['read_count'],
            'write_count': data['write_count'],
            'total_refs': len(data['references']),
            'cross_module': len(modules) > 1,
            'references': data['references'],
        }
        results['tables'][table] = table_info
        
        # Flag cross-module tables
        if len(modules) > 1:
            results['cross_module_tables'].append({
                'table': table,
                'modules': modules,
                'total_refs': len(data['references']),
            })
        
        # Flag write-heavy tables (>10 write references)
        if data['write_count'] > 10:
            results['write_heavy_tables'].append({
                'table': table,
                'write_count': data['write_count'],
                'modules': modules,
            })
        
        # Flag tables with only 1 reference (potential orphan)
        if len(data['references']) == 1:
            results['orphan_risk_tables'].append({
                'table': table,
                'only_ref': data['references'][0]['file'],
                'operation': data['references'][0]['operation'],
            })
    
    return results


# ── Report Generation ─────────────────────────────────────────────────

def generate_report(results):
    """Generate markdown SQL-to-code mapping report."""
    lines = []
    
    lines.append('# SQL-to-Code Mapping Report')
    lines.append('')
    lines.append(f'| Metric | Value |')
    lines.append(f'|---|---|')
    lines.append(f'| **Files scanned** | {results["files_scanned"]} |')
    lines.append(f'| **Total table references** | {results["total_references"]} |')
    lines.append(f'| **Unique tables** | {results["unique_tables"]} |')
    lines.append(f'| **Cross-module tables** | {len(results["cross_module_tables"])} |')
    lines.append(f'| **Write-heavy tables** | {len(results["write_heavy_tables"])} |')
    lines.append(f'| **Single-reference tables** | {len(results["orphan_risk_tables"])} |')
    lines.append('')
    
    # Cross-module tables (most important for impact analysis)
    if results['cross_module_tables']:
        lines.append('## Cross-Module Tables (Shared Data)')
        lines.append('')
        lines.append('> These tables are accessed by multiple modules. Changes require cross-module testing.')
        lines.append('')
        lines.append('| Table | Modules | References |')
        lines.append('|---|---|---|')
        for t in sorted(results['cross_module_tables'], key=lambda x: x['total_refs'], reverse=True):
            mods = ', '.join(f'`{m}`' for m in t['modules'])
            lines.append(f'| `{t["table"]}` | {mods} | {t["total_refs"]} |')
        lines.append('')
    
    # Write-heavy tables
    if results['write_heavy_tables']:
        lines.append('## Write-Heavy Tables')
        lines.append('')
        lines.append('> High write volume tables - consider transaction safety and locking.')
        lines.append('')
        lines.append('| Table | Write Refs | Modules |')
        lines.append('|---|---|---|')
        for t in sorted(results['write_heavy_tables'], key=lambda x: x['write_count'], reverse=True):
            mods = ', '.join(f'`{m}`' for m in t['modules'])
            lines.append(f'| `{t["table"]}` | {t["write_count"]} | {mods} |')
        lines.append('')
    
    # Full table map (collapsible)
    lines.append('<details>')
    lines.append(f'<summary>Full table map ({results["unique_tables"]} tables)</summary>')
    lines.append('')
    lines.append('| Table | R | W | Modules | Files |')
    lines.append('|---|---|---|---|---|')
    for table, info in sorted(results['tables'].items()):
        mods = ', '.join(info['modules'][:3])
        if len(info['modules']) > 3:
            mods += f' +{len(info["modules"])-3}'
        file_count = len(info['files'])
        lines.append(f'| `{table}` | {info["read_count"]} | {info["write_count"]} | {mods} | {file_count} |')
    lines.append('')
    lines.append('</details>')
    lines.append('')
    
    lines.append('---')
    lines.append('*Generated by Logimax SQL-to-Code Mapper (LCA)*')
    
    return '\n'.join(lines)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='SQL-to-Code Mapper')
    parser.add_argument('--project-root', required=True, help='Project root directory')
    parser.add_argument('--table', help='Filter to tables containing this string')
    parser.add_argument('--output', help='Output markdown report file')
    parser.add_argument('--json-output', help='Output JSON results file')
    
    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)
    
    print(f'Scanning SQL references in {project_root}...', file=sys.stderr)
    t_start = time.time()
    
    results = scan_all(project_root, args.table)
    
    elapsed = time.time() - t_start
    print(f'Done in {elapsed:.1f}s -- {results["files_scanned"]} files, '
          f'{results["unique_tables"]} tables, {results["total_references"]} refs',
          file=sys.stderr)
    
    report = generate_report(results)
    
    if args.output:
        Path(args.output).write_text(report, encoding='utf-8')
        print(f'Report written to {args.output}', file=sys.stderr)
    else:
        if sys.platform == 'win32':
            sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        print(report)
    
    if args.json_output:
        # Slim down JSON - don't include full reference list per table
        slim = dict(results)
        slim['tables'] = {
            k: {key: val for key, val in v.items() if key != 'references'}
            for k, v in results['tables'].items()
        }
        Path(args.json_output).write_text(
            json.dumps(slim, indent=2, default=list),
            encoding='utf-8'
        )
    
    sys.exit(0)


if __name__ == '__main__':
    main()
