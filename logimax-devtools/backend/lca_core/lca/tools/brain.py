"""Auto Module Brain Generator — generates module documentation from code.

Produces 7 documents per module:
  1. CONTROLLER_MAP.md   - All controller methods, params, views loaded
  2. MODEL_MAP.md        - All model methods, visibility, tables touched
  3. AJAX_MAP.md         - JS AJAX calls mapped to controller endpoints
  4. VIEW_MAP.md         - View files and which controllers load them
  5. SHARED_DEPENDENCIES.md - Tables shared with other modules
  6. MODULE_BRAIN.md     - Skeleton with method index + placeholders
  7. DATA_FLOW.md        - Skeleton with traced call chains

Ported from standalone auto_brain.py into LCA v2 package.
"""

from __future__ import annotations

import os
import re
from collections import defaultdict
from datetime import datetime
from pathlib import Path


# ---- Module Detection (centralized) -----------------------------------

from lca.analysis.modules import detect_module as _detect_module, CRITICAL_MODULES


# ---- PHP Extraction Helpers -------------------------------------------

def _extract_function_body(content: str, match_end: int) -> str:
    """Extract function body by brace-matching."""
    depth = 1
    pos = match_end
    while pos < len(content) and depth > 0:
        if content[pos] == '{':
            depth += 1
        elif content[pos] == '}':
            depth -= 1
        pos += 1
    return content[match_end:pos - 1] if depth == 0 else ''


_FUNC_RE = re.compile(
    r'(public|private|protected)?\s*(static)?\s*function\s+(\w+)\s*\(([^)]*)\)\s*\{',
    re.IGNORECASE,
)


def _build_function_map(content: str) -> list[tuple[str, int, int]]:
    """Build (name, start_offset, line) list."""
    funcs = []
    for m in _FUNC_RE.finditer(content):
        line = content[:m.start()].count('\n') + 1
        funcs.append((m.group(3), m.start(), line))
    return funcs


def _find_function_at(func_map, offset):
    """Find enclosing function for a given offset."""
    name = '<global>'
    for fname, fstart, _ in func_map:
        if fstart <= offset:
            name = fname
        else:
            break
    return name


# ---- SQL Reference Scanner (lightweight, no sql_code_mapper dep) ------

_TABLE_PATTERNS = [
    (r"\$this\s*->\s*db\s*->\s*(?:get|get_where)\s*\(\s*['\"](\w+)['\"]", 'READ'),
    (r"\$this\s*->\s*db\s*->\s*from\s*\(\s*['\"](\w+)['\"]", 'READ'),
    (r"\$this\s*->\s*db\s*->\s*join\s*\(\s*['\"](\w+)['\"]", 'READ'),
    (r"\$this\s*->\s*db\s*->\s*insert\s*\(\s*['\"](\w+)['\"]", 'INSERT'),
    (r"\$this\s*->\s*db\s*->\s*update\s*\(\s*['\"](\w+)['\"]", 'UPDATE'),
    (r"\$this\s*->\s*db\s*->\s*delete\s*\(\s*['\"](\w+)['\"]", 'DELETE'),
]


def _scan_sql_refs(filepath: str) -> list[dict]:
    """Scan a PHP file for table references."""
    try:
        content = Path(filepath).read_text(encoding='utf-8', errors='ignore')
    except Exception:
        return []

    func_map = _build_function_map(content)
    refs = []
    for pattern, operation in _TABLE_PATTERNS:
        for m in re.finditer(pattern, content):
            func = _find_function_at(func_map, m.start())
            refs.append({
                'table': m.group(1),
                'operation': operation,
                'function': func,
            })
    return refs


# ---- Module File Discovery -------------------------------------------

def discover_module_files(project_root: str, module_name: str) -> dict:
    """Find all files belonging to a module."""
    dirs = {
        'controllers': os.path.join(project_root, 'admin', 'application', 'controllers'),
        'models': os.path.join(project_root, 'admin', 'application', 'models'),
        'views': os.path.join(project_root, 'admin', 'application', 'views'),
        'js': os.path.join(project_root, 'admin', 'assets', 'js'),
        'helpers': os.path.join(project_root, 'admin', 'application', 'helpers'),
    }

    files = {k: [] for k in dirs}
    ext_map = {
        'controllers': '.php', 'models': '.php', 'helpers': '.php',
        'views': '.php', 'js': '.js',
    }

    for file_type, scan_dir in dirs.items():
        if not os.path.isdir(scan_dir):
            continue
        ext = ext_map[file_type]
        for root, _, filenames in os.walk(scan_dir):
            for fname in sorted(filenames):
                if not fname.endswith(ext):
                    continue
                fpath = os.path.join(root, fname)
                rel_path = os.path.relpath(fpath, project_root)
                if _detect_module(rel_path) == module_name:
                    files[file_type].append({
                        'path': fpath,
                        'rel_path': rel_path,
                        'name': fname,
                    })

    return files


# ---- Controller Extraction -------------------------------------------

def extract_controller_methods(filepath: str) -> dict:
    """Extract all methods from a controller with details."""
    try:
        content = Path(filepath).read_text(encoding='utf-8', errors='ignore')
    except Exception:
        return {'class_name': 'Unknown', 'methods': []}

    class_match = re.search(r'class\s+(\w+)\s+extends', content)
    class_name = class_match.group(1) if class_match else 'Unknown'

    methods = []
    for match in _FUNC_RE.finditer(content):
        visibility = match.group(1) or 'public'
        func_name = match.group(3)
        params = match.group(4).strip()
        line = content[:match.start()].count('\n') + 1
        body = _extract_function_body(content, match.end())

        model_calls = re.findall(r'\$this\s*->\s*(\w+_model)\s*->\s*(\w+)\s*\(', body)
        view_loads = re.findall(r"\$this\s*->\s*load\s*->\s*view\s*\(\s*['\"]([^'\"]+)['\"]", body)
        redirects = re.findall(r"redirect\s*\(\s*['\"]([^'\"]+)['\"]", body)
        is_ajax = bool(re.search(r'json_encode|echo\s+json', body))
        is_page = bool(view_loads)

        methods.append({
            'name': func_name, 'visibility': visibility, 'params': params,
            'line': line, 'model_calls': model_calls, 'view_loads': view_loads,
            'redirects': redirects, 'is_ajax': is_ajax, 'is_page': is_page,
            'body_size': len(body),
        })

    return {'class_name': class_name, 'methods': methods}


# ---- Model Extraction ------------------------------------------------

def extract_model_methods(filepath: str) -> list[dict]:
    """Extract model methods with table references."""
    try:
        content = Path(filepath).read_text(encoding='utf-8', errors='ignore')
    except Exception:
        return []

    sql_refs = _scan_sql_refs(filepath)
    table_by_func = defaultdict(set)
    for ref in sql_refs:
        table_by_func[ref['function']].add((ref['table'], ref['operation']))

    methods = []
    for match in _FUNC_RE.finditer(content):
        visibility = match.group(1) or 'public'
        func_name = match.group(3)
        params = match.group(4).strip()
        line = content[:match.start()].count('\n') + 1
        body = _extract_function_body(content, match.end())

        # Return type heuristic
        if re.search(r'->result\(\)|->result_array\(\)|return\s+\[', body):
            ret = 'array'
        elif re.search(r'->row\(\)|->row_array\(\)', body):
            ret = 'object/row'
        elif re.search(r'return\s+(true|false|TRUE|FALSE)', body):
            ret = 'bool'
        elif re.search(r'->num_rows\(\)|count_all', body):
            ret = 'int'
        elif re.search(r'\breturn\b', body):
            ret = 'mixed'
        else:
            ret = 'void'

        methods.append({
            'name': func_name, 'visibility': visibility, 'params': params,
            'line': line, 'return_hint': ret,
            'tables': sorted(table_by_func.get(func_name, [])),
            'body_size': len(body),
        })

    return methods


# ---- AJAX Mapping ----------------------------------------------------

def extract_ajax_calls(js_files: list[dict], module_name: str) -> list[dict]:
    """Extract AJAX calls from JS files."""
    ajax_calls = []
    patterns = [
        r"""\$\.ajax\s*\(\s*\{[^}]*url\s*:\s*[^,'"]*['"]([^'"]+)['"]""",
        r"""\$\.(?:post|get)\s*\(\s*['"]([^'"]+)['"]""",
        r"""url\s*:\s*(?:base_url\s*\+\s*)?['"]([^'"]+)['"]""",
    ]

    for js_file in js_files:
        try:
            content = Path(js_file['path']).read_text(encoding='utf-8', errors='ignore')
        except Exception:
            continue

        for pattern in patterns:
            for match in re.finditer(pattern, content, re.DOTALL):
                url = match.group(1).strip()
                line = content[:match.start()].count('\n') + 1
                parts = url.strip('/').split('/')
                controller = method = None
                if len(parts) >= 3:
                    controller = parts[1] if parts[0] == 'admin' else parts[0]
                    method = parts[2] if parts[0] == 'admin' else parts[1]
                elif len(parts) >= 2:
                    controller, method = parts[0], parts[1]

                js_func = None
                for fm in re.finditer(r'function\s+(\w+)\s*\(', content):
                    if fm.start() <= match.start():
                        js_func = fm.group(1)

                ajax_calls.append({
                    'url': url, 'file': js_file['rel_path'], 'line': line,
                    'controller': controller, 'method': method,
                    'js_function': js_func,
                })

    return ajax_calls


# ---- View Mapping ----------------------------------------------------

def extract_view_loads(controller_files: list[dict]) -> dict:
    """Extract which views are loaded by which controller methods."""
    view_map = defaultdict(list)
    for ctrl_file in controller_files:
        try:
            content = Path(ctrl_file['path']).read_text(encoding='utf-8', errors='ignore')
        except Exception:
            continue

        func_map = _build_function_map(content)
        for match in re.finditer(r"\$this\s*->\s*load\s*->\s*view\s*\(\s*['\"]([^'\"]+)['\"]", content):
            view_name = match.group(1)
            line = content[:match.start()].count('\n') + 1
            func = _find_function_at(func_map, match.start())
            view_map[view_name].append({
                'controller': ctrl_file['name'], 'method': func, 'line': line,
            })

    return dict(view_map)


# ---- Call Chain Tracing ----------------------------------------------

def trace_call_chains(controller_data, model_data):
    """Trace controller -> model -> table chains."""
    chains = []
    for ctrl in controller_data:
        for method in ctrl['methods']:
            if method['visibility'] != 'public' or method['name'].startswith('_'):
                continue

            chain = {
                'entry': f"{ctrl['class_name']}::{method['name']}()",
                'type': 'AJAX' if method['is_ajax'] else ('PAGE' if method['is_page'] else 'ACTION'),
                'model_calls': [], 'tables': set(), 'views': method['view_loads'],
            }

            for model_name, model_method in method['model_calls']:
                tables = []
                for _, model_funcs in model_data.items():
                    for mf in model_funcs:
                        if mf['name'] == model_method:
                            tables = mf['tables']
                            break

                chain['model_calls'].append({
                    'model': model_name, 'method': model_method, 'tables': tables,
                })
                for t, op in tables:
                    chain['tables'].add((t, op))

            if chain['model_calls'] or chain['views']:
                chain['tables'] = sorted(chain['tables'])
                chains.append(chain)

    return chains


# ---- Document Generators ---------------------------------------------

def _timestamp():
    return datetime.now().strftime("%Y-%m-%d %H:%M")


def gen_controller_map(module_name, controller_data):
    lines = [f'# Controller Map -- {module_name}', '', f'> Auto-generated on {_timestamp()}', '']
    for ctrl in controller_data:
        lines.append(f'## `{ctrl["class_name"]}`')
        lines.append('')
        public = [m for m in ctrl['methods'] if m['visibility'] == 'public' and not m['name'].startswith('_')]
        private = [m for m in ctrl['methods'] if m['visibility'] != 'public' or m['name'].startswith('_')]
        pages = [m for m in public if m['is_page']]
        ajax = [m for m in public if m['is_ajax'] and not m['is_page']]
        actions = [m for m in public if not m['is_page'] and not m['is_ajax']]

        for label, subset, cols in [
            ('Page Endpoints', pages, '| Method | Params | View | Line |'),
            ('AJAX Endpoints', ajax, '| Method | Params | Model Calls | Line |'),
            ('Action Endpoints', actions, '| Method | Params | Redirects | Line |'),
        ]:
            if not subset:
                continue
            lines.extend([f'### {label}', '', cols, '|---|---|---|---|'])
            for m in subset:
                if label == 'Page Endpoints':
                    views = ', '.join(f'`{v}`' for v in m['view_loads'][:3])
                    lines.append(f'| `{m["name"]}()` | {m["params"] or "-"} | {views} | L{m["line"]} |')
                elif label == 'AJAX Endpoints':
                    calls = ', '.join(f'`{mod}.{met}`' for mod, met in m['model_calls'][:3])
                    lines.append(f'| `{m["name"]}()` | {m["params"] or "-"} | {calls or "-"} | L{m["line"]} |')
                else:
                    redir = ', '.join(f'`{r}`' for r in m['redirects'][:2]) or '-'
                    lines.append(f'| `{m["name"]}()` | {m["params"] or "-"} | {redir} | L{m["line"]} |')
            lines.append('')

        if private:
            lines.extend(['### Private/Protected Methods', '', '| Method | Visibility | Line | Size |', '|---|---|---|---|'])
            for m in private:
                lines.append(f'| `{m["name"]}()` | {m["visibility"]} | L{m["line"]} | {m["body_size"]}b |')
            lines.append('')

    return '\n'.join(lines)


def gen_model_map(module_name, model_data):
    lines = [f'# Model Map -- {module_name}', '', f'> Auto-generated on {_timestamp()}', '']
    for model_file, methods in model_data.items():
        basename = os.path.basename(model_file)
        lines.extend([f'## `{basename}`', '', f'**{len(methods)} methods**', '',
                      '| Method | Vis | Returns | Tables | Line |', '|---|---|---|---|---|'])
        for m in methods:
            ts = ', '.join(f'`{t}`({op[0]})' for t, op in m['tables'][:4])
            lines.append(f'| `{m["name"]}()` | {m["visibility"][:3]} | {m["return_hint"]} | {ts or "-"} | L{m["line"]} |')
        lines.append('')
    return '\n'.join(lines)


def gen_ajax_map(module_name, ajax_calls):
    lines = [f'# AJAX Map -- {module_name}', '', f'> Auto-generated on {_timestamp()}', '',
             f'**{len(ajax_calls)} AJAX endpoints found**', '']
    if ajax_calls:
        lines.extend(['| URL | Controller | Method | JS Function | JS File | Line |', '|---|---|---|---|---|---|'])
        for c in ajax_calls:
            lines.append(
                f'| `{c["url"][:60]}` | `{c["controller"] or "?"}` | '
                f'`{c["method"] or "?"}` | `{c["js_function"] or "?"}` | '
                f'`{os.path.basename(c["file"])}` | L{c["line"]} |'
            )
        lines.append('')
    return '\n'.join(lines)


def gen_view_map(module_name, view_map, view_files):
    lines = [f'# View Map -- {module_name}', '', f'> Auto-generated on {_timestamp()}', '',
             f'**{len(view_files)} view files, {len(view_map)} referenced**', '']
    if view_map:
        lines.extend(['| View | Loaded By | Method | Line |', '|---|---|---|---|'])
        for vn, loaders in sorted(view_map.items()):
            for l in loaders:
                lines.append(f'| `{vn}` | `{l["controller"]}` | `{l["method"]}()` | L{l["line"]} |')
        lines.append('')
    return '\n'.join(lines)


def gen_shared_deps(module_name, all_sql_refs):
    module_tables = defaultdict(lambda: {'operations': set(), 'functions': set()})
    all_module_tables = defaultdict(set)
    for ref in all_sql_refs:
        all_module_tables[ref['table']].add(ref['module'])
        if ref['module'] == module_name:
            module_tables[ref['table']]['operations'].add(ref['operation'])
            module_tables[ref['table']]['functions'].add(ref['function'])

    shared, exclusive = [], []
    for table, info in sorted(module_tables.items()):
        others = all_module_tables[table] - {module_name}
        if others:
            shared.append({'table': table, 'our_ops': sorted(info['operations']),
                          'our_funcs': sorted(info['functions']), 'other_modules': sorted(others)})
        else:
            exclusive.append({'table': table, 'ops': sorted(info['operations']),
                            'funcs': sorted(info['functions'])})

    lines = [f'# Shared Dependencies -- {module_name}', '', f'> Auto-generated on {_timestamp()}', '',
             '| Category | Count |', '|---|---|',
             f'| **Total tables** | {len(module_tables)} |',
             f'| **Shared** | {len(shared)} |',
             f'| **Exclusive** | {len(exclusive)} |', '']

    if shared:
        lines.extend(['## Shared Tables', '', '| Table | Our Ops | Shared With | Our Functions |', '|---|---|---|---|'])
        for s in sorted(shared, key=lambda x: len(x['other_modules']), reverse=True):
            ops = ', '.join(s['our_ops'])
            mods = ', '.join(f'`{m}`' for m in s['other_modules'][:5])
            funcs = ', '.join(f'`{f}`' for f in list(s['our_funcs'])[:3])
            lines.append(f'| `{s["table"]}` | {ops} | {mods} | {funcs} |')
        lines.append('')

    return '\n'.join(lines)


def gen_module_brain(module_name, controller_data, model_data, stats):
    lines = [f'# Module Brain -- {module_name}', '', f'> Auto-generated on {_timestamp()}',
             '> **Status: SKELETON** -- Business logic sections need human review', '', '---', '',
             '## Overview', '', '| Metric | Value |', '|---|---|',
             f'| **Controllers** | {stats["controller_count"]} |',
             f'| **Models** | {stats["model_count"]} |',
             f'| **Views** | {stats["view_count"]} |',
             f'| **JS Files** | {stats["js_count"]} |',
             f'| **Total Methods** | {stats["total_methods"]} |',
             f'| **Page Endpoints** | {stats["page_endpoints"]} |',
             f'| **AJAX Endpoints** | {stats["ajax_endpoints"]} |',
             f'| **Tables Used** | {stats["tables_used"]} |',
             f'| **Shared Tables** | {stats["shared_tables"]} |',
             f'| **Critical Module** | {"Yes" if module_name in CRITICAL_MODULES else "No"} |', '',
             '## Business Logic', '', '<!-- TODO: Describe business purpose -->', '',
             '## Key Business Rules', '', '<!-- TODO: Document rules -->', '',
             '## File Index', '']

    for ctrl in controller_data:
        total = len(ctrl['methods'])
        pub = len([m for m in ctrl['methods'] if m['visibility'] == 'public'])
        lines.append(f'- **Controller**: `{ctrl["class_name"]}` ({total} methods, {pub} public)')
    for mf, methods in model_data.items():
        lines.append(f'- **Model**: `{os.path.basename(mf)}` ({len(methods)} methods)')
    lines.extend(['', '## Cross-References', '',
                  '- [Controller Map](CONTROLLER_MAP.md)', '- [Model Map](MODEL_MAP.md)',
                  '- [AJAX Map](AJAX_MAP.md)', '- [View Map](VIEW_MAP.md)',
                  '- [Shared Dependencies](SHARED_DEPENDENCIES.md)',
                  '- [Data Flow](DATA_FLOW.md)', ''])
    return '\n'.join(lines)


def gen_data_flow(module_name, chains):
    lines = [f'# Data Flow -- {module_name}', '', f'> Auto-generated on {_timestamp()}',
             '> **Status: SKELETON** -- Flow descriptions need human review', '', '---', '']

    page_chains = [c for c in chains if c['type'] == 'PAGE']
    ajax_chains = [c for c in chains if c['type'] == 'AJAX']

    if page_chains:
        lines.extend(['## Page Load Flows', ''])
        for chain in page_chains[:20]:
            lines.extend([f'### `{chain["entry"]}`', '', '```'])
            lines.append(f'  {chain["entry"]}')
            for mc in chain['model_calls']:
                ts = ', '.join(f'{t}({op[0]})' for t, op in mc['tables'][:3])
                lines.append(f'    -> {mc["model"]}.{mc["method"]}() [{ts}]')
            for v in chain.get('views', []):
                lines.append(f'    -> view: {v}')
            lines.extend(['```', ''])

    if ajax_chains:
        lines.extend(['## AJAX Flows', '', '| Entry Point | Model Calls | Tables |', '|---|---|---|'])
        for chain in ajax_chains[:40]:
            calls = ', '.join(f'`{mc["model"]}.{mc["method"]}`' for mc in chain['model_calls'][:3])
            tables = ', '.join(f'`{t}`' for t, op in chain['tables'][:4])
            lines.append(f'| `{chain["entry"]}` | {calls or "-"} | {tables or "-"} |')
        lines.append('')

    return '\n'.join(lines)


# ---- Main Orchestrator -----------------------------------------------

def generate_brain(project_root: str, module_name: str, output_dir: str) -> dict:
    """Generate all Module Brain documents.

    Args:
        project_root: Path to project root
        module_name: Module name (e.g., 'billing')
        output_dir: Output directory for generated docs

    Returns:
        Stats dict with counts
    """
    files = discover_module_files(project_root, module_name)

    controller_data = []
    for ctrl_file in files['controllers']:
        data = extract_controller_methods(ctrl_file['path'])
        if data['methods']:
            controller_data.append(data)

    model_data = {}
    for model_file in files['models']:
        methods = extract_model_methods(model_file['path'])
        if methods:
            model_data[model_file['rel_path']] = methods

    ajax_calls = extract_ajax_calls(files['js'], module_name)
    view_map = extract_view_loads(files['controllers'])

    # Collect SQL refs for shared deps
    all_sql_refs = []
    scan_dirs = [
        os.path.join(project_root, 'admin', 'application', 'controllers'),
        os.path.join(project_root, 'admin', 'application', 'models'),
    ]
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
                for ref in _scan_sql_refs(fpath):
                    ref['module'] = module
                    all_sql_refs.append(ref)

    chains = trace_call_chains(controller_data, model_data)

    # Stats
    total_methods = sum(len(d['methods']) for d in controller_data) + sum(len(m) for m in model_data.values())
    module_tables = set()
    all_mod_tables = defaultdict(set)
    for ref in all_sql_refs:
        all_mod_tables[ref['table']].add(ref['module'])
        if ref['module'] == module_name:
            module_tables.add(ref['table'])
    shared_count = sum(1 for t in module_tables if len(all_mod_tables[t]) > 1)

    stats = {
        'controller_count': len(files['controllers']),
        'model_count': len(files['models']),
        'view_count': len(files['views']),
        'js_count': len(files['js']),
        'total_methods': total_methods,
        'page_endpoints': sum(1 for d in controller_data for m in d['methods'] if m['is_page']),
        'ajax_endpoints': sum(1 for d in controller_data for m in d['methods'] if m['is_ajax']),
        'tables_used': len(module_tables),
        'shared_tables': shared_count,
    }

    # Write documents
    os.makedirs(output_dir, exist_ok=True)
    docs = {
        'CONTROLLER_MAP.md': gen_controller_map(module_name, controller_data),
        'MODEL_MAP.md': gen_model_map(module_name, model_data),
        'AJAX_MAP.md': gen_ajax_map(module_name, ajax_calls),
        'VIEW_MAP.md': gen_view_map(module_name, view_map, files['views']),
        'SHARED_DEPENDENCIES.md': gen_shared_deps(module_name, all_sql_refs),
        'MODULE_BRAIN.md': gen_module_brain(module_name, controller_data, model_data, stats),
        'DATA_FLOW.md': gen_data_flow(module_name, chains),
    }

    written = []
    for filename, content in docs.items():
        filepath = os.path.join(output_dir, filename)
        Path(filepath).write_text(content, encoding='utf-8')
        written.append(filepath)

    return stats, written
