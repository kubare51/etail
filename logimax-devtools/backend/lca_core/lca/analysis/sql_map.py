"""SQL-to-Code Mapper — maps which PHP functions touch which DB tables.

For each PHP file in models/controllers/helpers:
  1. Parses $this->db-> calls (from, join, where, insert, update, delete)
  2. Parses raw SQL queries ($this->db->query())
  3. Extracts table names referenced
  4. Maps table -> [{file, function, operation, line}]

Use cases:
  - Impact analysis: "If I change table X, what code breaks?"
  - Schema safety: "Before ALTERing this table, who uses it?"
  - Audit: "Which tables have no code referencing them?"

Ported from: sql_code_mapper.py (standalone C3 tool)
"""

import re
from collections import defaultdict
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional

from lca.analysis.modules import detect_module


@dataclass
class TableReference:
    """A single reference to a database table from code."""
    table: str
    operation: str   # READ, WRITE, SCHEMA
    file: str
    file_type: str   # model, controller, helper
    module: str
    function: str
    line: int
    method: str      # active_record, raw_sql


@dataclass
class TableInfo:
    """Aggregated info about a single table."""
    table: str
    modules: List[str]
    files: List[str]
    operations: List[str]
    read_count: int
    write_count: int
    total_refs: int
    cross_module: bool


@dataclass
class SqlMapResult:
    """Results of a SQL-to-code mapping scan."""
    files_scanned: int
    total_references: int
    unique_tables: int
    tables: Dict[str, TableInfo] = field(default_factory=dict)
    cross_module_tables: List[dict] = field(default_factory=list)
    write_heavy_tables: List[dict] = field(default_factory=list)
    orphan_risk_tables: List[dict] = field(default_factory=list)


# ── Table Extraction Patterns ─────────────────────────────────────────

# CI3 Active Record patterns
AR_TABLE_PATTERNS = [
    (r"""\$this\s*->\s*db\s*->\s*(?:from|get|get_where)\s*\(\s*['"]([\w]+)['"]""", 'READ'),
    (r"""\$this\s*->\s*db\s*->\s*(?:join|left_join)\s*\(\s*['"]([\w]+)['"]""", 'READ'),
    (r"""\$this\s*->\s*db\s*->\s*insert(?:_batch)?\s*\(\s*['"]([\w]+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*update(?:_batch)?\s*\(\s*['"]([\w]+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*delete\s*\(\s*['"]([\w]+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*replace\s*\(\s*['"]([\w]+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*empty_table\s*\(\s*['"]([\w]+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*truncate\s*\(\s*['"]([\w]+)['"]""", 'WRITE'),
    (r"""\$this\s*->\s*db\s*->\s*count_all\s*\(\s*['"]([\w]+)['"]""", 'READ'),
    (r"""\$this\s*->\s*db\s*->\s*count_all_results\s*\(\s*['"]([\w]+)['"]""", 'READ'),
]

# Raw SQL patterns
RAW_SQL_PATTERNS = [
    (r"""\bFROM\s+[`'"']?([\w]+)[`'"']?""", 'READ'),
    (r"""\bJOIN\s+[`'"']?([\w]+)[`'"']?""", 'READ'),
    (r"""\bINSERT\s+INTO\s+[`'"']?([\w]+)[`'"']?""", 'WRITE'),
    (r"""\bUPDATE\s+[`'"']?([\w]+)[`'"']?""", 'WRITE'),
    (r"""\bDELETE\s+FROM\s+[`'"']?([\w]+)[`'"']?""", 'WRITE'),
    (r"""\bTRUNCATE\s+(?:TABLE\s+)?[`'"']?([\w]+)[`'"']?""", 'WRITE'),
    (r"""\bALTER\s+TABLE\s+[`'"']?([\w]+)[`'"']?""", 'SCHEMA'),
    (r"""\bDROP\s+TABLE\s+[`'"']?([\w]+)[`'"']?""", 'SCHEMA'),
    (r"""\bCREATE\s+TABLE\s+[`'"']?([\w]+)[`'"']?""", 'SCHEMA'),
]

# SQL keywords to skip (not table names)
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

def _build_function_map(content: str):
    """Precompute function boundaries for binary search."""
    pattern = r'(?:public|private|protected|static)?\s*function\s+(\w+)\s*\('
    return [(m.start(), m.group(1)) for m in re.finditer(pattern, content)]


def _find_function_at_offset(func_map, offset: int) -> str:
    """Find which function contains a given character offset."""
    result = '(top-level)'
    for start, name in func_map:
        if start <= offset:
            result = name
        else:
            break
    return result


# ── File Scanner ──────────────────────────────────────────────────────

def scan_file(filepath: Path, project_root: Path) -> List[TableReference]:
    """Scan a single PHP file for table references."""
    refs = []
    try:
        content = filepath.read_text(encoding='utf-8', errors='ignore')
    except Exception:
        return refs

    rel_path = str(filepath.relative_to(project_root))
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

    func_map = _build_function_map(content)

    # Scan Active Record patterns
    for pattern, operation in AR_TABLE_PATTERNS:
        for match in re.finditer(pattern, content, re.IGNORECASE):
            table = match.group(1).lower()
            if table in SKIP_TABLES or len(table) < 3:
                continue
            line = content[:match.start()].count('\n') + 1
            func = _find_function_at_offset(func_map, match.start())
            refs.append(TableReference(
                table=table, operation=operation, file=rel_path,
                file_type=file_type, module=module, function=func,
                line=line, method='active_record',
            ))

    # Scan raw SQL patterns line-by-line
    for line_num, line_text in enumerate(content.split('\n'), 1):
        line_upper = line_text.upper()
        if not any(kw in line_upper for kw in (
            'FROM', 'JOIN', 'INSERT', 'UPDATE', 'DELETE',
            'TRUNCATE', 'ALTER', 'DROP', 'CREATE',
        )):
            continue
        for pattern, operation in RAW_SQL_PATTERNS:
            for match in re.finditer(pattern, line_text, re.IGNORECASE):
                table = match.group(1).lower()
                if table in SKIP_TABLES or len(table) < 3:
                    continue
                func = _find_function_at_offset(func_map, match.start())
                refs.append(TableReference(
                    table=table, operation=operation, file=rel_path,
                    file_type=file_type, module=module, function=func,
                    line=line_num, method='raw_sql',
                ))

    return refs


# ── Main Scanner ──────────────────────────────────────────────────────

def scan_sql_map(
    project_root: Path,
    table_filter: Optional[str] = None,
) -> SqlMapResult:
    """Scan entire codebase and build table-to-code mapping."""
    scan_dirs = [
        project_root / 'admin' / 'application' / 'controllers',
        project_root / 'admin' / 'application' / 'models',
        project_root / 'admin' / 'application' / 'helpers',
    ]

    all_refs = []
    files_scanned = 0

    for scan_dir in scan_dirs:
        if not scan_dir.is_dir():
            continue
        for fpath in sorted(scan_dir.rglob('*.php')):
            files_scanned += 1
            refs = scan_file(fpath, project_root)
            all_refs.extend(refs)

    # Build table -> references mapping
    table_data = defaultdict(lambda: {
        'references': [], 'modules': set(), 'files': set(),
        'operations': set(), 'read_count': 0, 'write_count': 0,
    })

    for ref in all_refs:
        if table_filter and table_filter.lower() not in ref.table:
            continue
        entry = table_data[ref.table]
        entry['references'].append(ref)
        entry['modules'].add(ref.module)
        entry['files'].add(ref.file)
        entry['operations'].add(ref.operation)
        if ref.operation == 'READ':
            entry['read_count'] += 1
        elif ref.operation == 'WRITE':
            entry['write_count'] += 1

    # Build result
    result = SqlMapResult(
        files_scanned=files_scanned,
        total_references=len(all_refs),
        unique_tables=len(table_data),
    )

    for table, data in sorted(table_data.items()):
        modules = sorted(data['modules'])
        files = sorted(data['files'])

        result.tables[table] = TableInfo(
            table=table, modules=modules, files=files,
            operations=sorted(data['operations']),
            read_count=data['read_count'], write_count=data['write_count'],
            total_refs=len(data['references']),
            cross_module=len(modules) > 1,
        )

        if len(modules) > 1:
            result.cross_module_tables.append({
                'table': table, 'modules': modules,
                'total_refs': len(data['references']),
            })

        if data['write_count'] > 10:
            result.write_heavy_tables.append({
                'table': table, 'write_count': data['write_count'],
                'modules': modules,
            })

        if len(data['references']) == 1:
            ref = data['references'][0]
            result.orphan_risk_tables.append({
                'table': table, 'only_ref': ref.file,
                'operation': ref.operation,
            })

    return result
