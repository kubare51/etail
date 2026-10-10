#!/usr/bin/env python3
"""
Auto Module Brain Generator (C5)
=================================
Auto-generates Module Brain documentation by scanning live code.

Produces 6 complete docs + 2 skeleton docs per module:
  1. CONTROLLER_MAP.md   - All controller methods, params, views loaded
  2. MODEL_MAP.md        - All model methods, visibility, tables touched
  3. AJAX_MAP.md         - JS AJAX calls mapped to controller endpoints
  4. VIEW_MAP.md         - View files and which controllers load them
  5. SHARED_DEPENDENCIES.md - Tables shared with other modules
  6. DEAD_CODE.md        - Dead/duplicate function report
  7. MODULE_BRAIN.md     - Skeleton with method index + placeholders
  8. DATA_FLOW.md        - Skeleton with traced call chains

Usage:
    python auto_brain.py --project-root /path/to/project --module billing
    python auto_brain.py --project-root /path/to/project --module billing --output-dir ./knowledge_brain/billing
"""

import argparse
import os
import re
import sys
import time
from collections import defaultdict
from pathlib import Path
from datetime import datetime

sys.path.insert(0, str(Path(__file__).parent))
from impact_analyzer import detect_module, CRITICAL_MODULES
from sql_code_mapper import scan_file as sql_scan_file, build_function_map, find_function_at_offset


# ── Module File Discovery ─────────────────────────────────────────────

def discover_module_files(project_root, module_name):
    """Find all files belonging to a module."""
    dirs = {
        'controllers': os.path.join(project_root, 'admin', 'application', 'controllers'),
        'models': os.path.join(project_root, 'admin', 'application', 'models'),
        'views': os.path.join(project_root, 'admin', 'application', 'views'),
        'js': os.path.join(project_root, 'admin', 'assets', 'js'),
        'helpers': os.path.join(project_root, 'admin', 'application', 'helpers'),
    }

    files = {
        'controllers': [],
        'models': [],
        'views': [],
        'js': [],
        'helpers': [],
    }

    for file_type, scan_dir in dirs.items():
        if not os.path.isdir(scan_dir):
            continue
        for root, _, filenames in os.walk(scan_dir):
            for fname in sorted(filenames):
                if file_type in ('controllers', 'models', 'helpers'):
                    if not fname.endswith('.php'):
                        continue
                elif file_type == 'views':
                    if not fname.endswith('.php'):
                        continue
                elif file_type == 'js':
                    if not fname.endswith('.js'):
                        continue

                fpath = os.path.join(root, fname)
                rel_path = os.path.relpath(fpath, project_root)
                detected = detect_module(rel_path)

                if detected == module_name:
                    files[file_type].append({
                        'path': fpath,
                        'rel_path': rel_path,
                        'name': fname,
                    })

    return files


# ── Controller Extraction ─────────────────────────────────────────────

def extract_controller_methods(filepath):
    """Extract all methods from a controller with details."""
    methods = []
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
    except Exception:
        return methods

    # Extract class name
    class_match = re.search(r'class\s+(\w+)\s+extends', content)
    class_name = class_match.group(1) if class_match else 'Unknown'

    # Extract methods (visibility is optional — old PHP defaults to public)
    pattern = r'(public|private|protected)?\s*(static)?\s*function\s+(\w+)\s*\(([^)]*)\)\s*\{'
    for match in re.finditer(pattern, content):
        visibility = match.group(1) or 'public'
        is_static = match.group(2) is not None
        func_name = match.group(3)
        params = match.group(4).strip()
        line = content[:match.start()].count('\n') + 1

        # Find what this method does
        # Get method body (find matching brace)
        start = match.end()
        depth = 1
        pos = start
        while pos < len(content) and depth > 0:
            if content[pos] == '{':
                depth += 1
            elif content[pos] == '}':
                depth -= 1
            pos += 1
        body = content[start:pos-1] if depth == 0 else ''

        # Find model calls
        model_calls = re.findall(r'\$this\s*->\s*(\w+_model)\s*->\s*(\w+)\s*\(', body)

        # Find view loads
        view_loads = re.findall(r"\$this\s*->\s*load\s*->\s*view\s*\(\s*['\"]([^'\"]+)['\"]", body)

        # Find redirects
        redirects = re.findall(r"redirect\s*\(\s*['\"]([^'\"]+)['\"]", body)

        # Check if it returns JSON (AJAX endpoint)
        is_ajax = bool(re.search(r'json_encode|echo\s+json', body))

        # Check if it loads a view (page endpoint)
        is_page = bool(view_loads)

        methods.append({
            'name': func_name,
            'visibility': visibility,
            'is_static': is_static,
            'params': params,
            'line': line,
            'model_calls': model_calls,
            'view_loads': view_loads,
            'redirects': redirects,
            'is_ajax': is_ajax,
            'is_page': is_page,
            'body_size': len(body),
        })

    return {'class_name': class_name, 'methods': methods}


# ── Model Extraction ──────────────────────────────────────────────────

def extract_model_methods(filepath, project_root):
    """Extract all methods from a model with table references."""
    methods = []
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
    except Exception:
        return methods

    # Get SQL references for this file
    sql_refs = sql_scan_file(filepath, project_root)
    table_by_func = defaultdict(set)
    for ref in sql_refs:
        table_by_func[ref['function']].add((ref['table'], ref['operation']))

    # Extract methods (visibility is optional — old PHP defaults to public)
    pattern = r'(public|private|protected)?\s*(static)?\s*function\s+(\w+)\s*\(([^)]*)\)\s*\{'
    for match in re.finditer(pattern, content):
        visibility = match.group(1) or 'public'
        is_static = match.group(2) is not None
        func_name = match.group(3)
        params = match.group(4).strip()
        line = content[:match.start()].count('\n') + 1

        # Get method body
        start = match.end()
        depth = 1
        pos = start
        while pos < len(content) and depth > 0:
            if content[pos] == '{':
                depth += 1
            elif content[pos] == '}':
                depth -= 1
            pos += 1
        body = content[start:pos-1] if depth == 0 else ''

        # Determine return type heuristic
        has_return = bool(re.search(r'\breturn\b', body))
        returns_array = bool(re.search(r'return\s+\$.*result|->result\(\)|->result_array\(\)|return\s+\[', body))
        returns_row = bool(re.search(r'->row\(\)|->row_array\(\)', body))
        returns_bool = bool(re.search(r'return\s+(true|false|TRUE|FALSE)', body))
        returns_count = bool(re.search(r'->num_rows\(\)|count_all', body))

        if returns_array:
            return_hint = 'array'
        elif returns_row:
            return_hint = 'object/row'
        elif returns_bool:
            return_hint = 'bool'
        elif returns_count:
            return_hint = 'int'
        elif has_return:
            return_hint = 'mixed'
        else:
            return_hint = 'void'

        # Tables touched
        tables = sorted(table_by_func.get(func_name, []))

        methods.append({
            'name': func_name,
            'visibility': visibility,
            'is_static': is_static,
            'params': params,
            'line': line,
            'return_hint': return_hint,
            'tables': tables,
            'body_size': len(body),
        })

    return methods


# ── AJAX Mapping ──────────────────────────────────────────────────────

def extract_ajax_calls(js_files, module_name):
    """Extract AJAX calls from JS files and map to controller endpoints."""
    ajax_calls = []

    for js_file in js_files:
        try:
            with open(js_file['path'], 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
        except Exception:
            continue

        # Pattern: $.ajax({ url: '...', or $.ajax({ url: base_url + '...',
        # Also: $.post('url', ...) and $.get('url', ...)
        patterns = [
            r"""\$\.ajax\s*\(\s*\{[^}]*url\s*:\s*[^,'"]*['"]([^'"]+)['"]""",
            r"""\$\.(?:post|get)\s*\(\s*['"]([^'"]+)['"]""",
            r"""url\s*:\s*(?:base_url\s*\+\s*)?['"]([^'"]+)['"]""",
        ]

        for pattern in patterns:
            for match in re.finditer(pattern, content, re.DOTALL):
                url = match.group(1).strip()
                line = content[:match.start()].count('\n') + 1

                # Parse controller/method from URL
                # Typical: admin/controller_name/method_name
                parts = url.strip('/').split('/')
                controller = None
                method = None
                if len(parts) >= 3:
                    controller = parts[1] if parts[0] == 'admin' else parts[0]
                    method = parts[2] if parts[0] == 'admin' else parts[1]
                elif len(parts) >= 2:
                    controller = parts[0]
                    method = parts[1]

                # Find enclosing JS function
                func_pattern = r'function\s+(\w+)\s*\('
                js_func = None
                for fm in re.finditer(func_pattern, content):
                    if fm.start() <= match.start():
                        js_func = fm.group(1)

                ajax_calls.append({
                    'url': url,
                    'file': js_file['rel_path'],
                    'line': line,
                    'controller': controller,
                    'method': method,
                    'js_function': js_func,
                })

    return ajax_calls


# ── View Mapping ──────────────────────────────────────────────────────

def extract_view_loads(controller_files):
    """Extract which views are loaded by which controller methods."""
    view_map = defaultdict(list)

    for ctrl_file in controller_files:
        try:
            with open(ctrl_file['path'], 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
        except Exception:
            continue

        func_map = build_function_map(content)

        pattern = r"\$this\s*->\s*load\s*->\s*view\s*\(\s*['\"]([^'\"]+)['\"]"
        for match in re.finditer(pattern, content):
            view_name = match.group(1)
            line = content[:match.start()].count('\n') + 1
            func = find_function_at_offset(func_map, match.start())

            view_map[view_name].append({
                'controller': ctrl_file['name'],
                'method': func,
                'line': line,
            })

    return dict(view_map)


# ── Call Chain Tracing ────────────────────────────────────────────────

def trace_call_chains(controller_methods, model_methods_by_file):
    """Trace controller → model → table call chains."""
    chains = []

    for ctrl in controller_methods:
        for method in ctrl['methods']:
            if method['visibility'] != 'public':
                continue
            if method['name'].startswith('_'):
                continue

            chain = {
                'entry': f"{ctrl['class_name']}::{method['name']}()",
                'type': 'AJAX' if method['is_ajax'] else ('PAGE' if method['is_page'] else 'ACTION'),
                'model_calls': [],
                'tables': set(),
                'views': method['view_loads'],
            }

            for model_name, model_method in method['model_calls']:
                # Find this model method's tables
                tables = []
                for model_file, model_funcs in model_methods_by_file.items():
                    for mf in model_funcs:
                        if mf['name'] == model_method:
                            tables = mf['tables']
                            break

                chain['model_calls'].append({
                    'model': model_name,
                    'method': model_method,
                    'tables': tables,
                })
                for t, op in tables:
                    chain['tables'].add((t, op))

            if chain['model_calls'] or chain['views']:
                chain['tables'] = sorted(chain['tables'])
                chains.append(chain)

    return chains


# ── Document Generators ───────────────────────────────────────────────

def gen_controller_map(module_name, controller_data):
    """Generate CONTROLLER_MAP.md"""
    lines = [
        f'# Controller Map — {module_name}',
        '',
        f'> Auto-generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
    ]

    for ctrl in controller_data:
        lines.append(f'## `{ctrl["class_name"]}`')
        lines.append('')

        # Group by type
        public = [m for m in ctrl['methods'] if m['visibility'] == 'public' and not m['name'].startswith('_')]
        private = [m for m in ctrl['methods'] if m['visibility'] != 'public' or m['name'].startswith('_')]

        # Page endpoints
        pages = [m for m in public if m['is_page']]
        ajax = [m for m in public if m['is_ajax'] and not m['is_page']]
        actions = [m for m in public if not m['is_page'] and not m['is_ajax']]

        if pages:
            lines.append('### Page Endpoints')
            lines.append('')
            lines.append('| Method | Params | View | Line |')
            lines.append('|---|---|---|---|')
            for m in pages:
                views = ', '.join(f'`{v}`' for v in m['view_loads'][:3])
                lines.append(f'| `{m["name"]}()` | {m["params"] or "-"} | {views} | L{m["line"]} |')
            lines.append('')

        if ajax:
            lines.append('### AJAX Endpoints')
            lines.append('')
            lines.append('| Method | Params | Model Calls | Line |')
            lines.append('|---|---|---|---|')
            for m in ajax:
                calls = ', '.join(f'`{model}.{method}`' for model, method in m['model_calls'][:3])
                if len(m['model_calls']) > 3:
                    calls += f' +{len(m["model_calls"])-3}'
                lines.append(f'| `{m["name"]}()` | {m["params"] or "-"} | {calls or "-"} | L{m["line"]} |')
            lines.append('')

        if actions:
            lines.append('### Action Endpoints')
            lines.append('')
            lines.append('| Method | Params | Redirects | Line |')
            lines.append('|---|---|---|---|')
            for m in actions:
                redir = ', '.join(f'`{r}`' for r in m['redirects'][:2]) or '-'
                lines.append(f'| `{m["name"]}()` | {m["params"] or "-"} | {redir} | L{m["line"]} |')
            lines.append('')

        if private:
            lines.append('### Private/Protected Methods')
            lines.append('')
            lines.append('| Method | Visibility | Line | Size |')
            lines.append('|---|---|---|---|')
            for m in private:
                lines.append(f'| `{m["name"]}()` | {m["visibility"]} | L{m["line"]} | {m["body_size"]}b |')
            lines.append('')

    return '\n'.join(lines)


def gen_model_map(module_name, model_data):
    """Generate MODEL_MAP.md"""
    lines = [
        f'# Model Map — {module_name}',
        '',
        f'> Auto-generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
    ]

    for model_file, methods in model_data.items():
        basename = os.path.basename(model_file)
        lines.append(f'## `{basename}`')
        lines.append('')
        lines.append(f'**{len(methods)} methods**')
        lines.append('')

        # Group: CRUD, Query, Utility
        lines.append('| Method | Vis | Returns | Tables | Line |')
        lines.append('|---|---|---|---|---|')
        for m in methods:
            tables_str = ', '.join(f'`{t}`({op[0]})' for t, op in m['tables'][:4])
            if len(m['tables']) > 4:
                tables_str += f' +{len(m["tables"])-4}'
            lines.append(f'| `{m["name"]}()` | {m["visibility"][:3]} | {m["return_hint"]} | {tables_str or "-"} | L{m["line"]} |')
        lines.append('')

    return '\n'.join(lines)


def gen_ajax_map(module_name, ajax_calls):
    """Generate AJAX_MAP.md"""
    lines = [
        f'# AJAX Map — {module_name}',
        '',
        f'> Auto-generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
        f'**{len(ajax_calls)} AJAX endpoints found**',
        '',
    ]

    if ajax_calls:
        lines.append('| URL | Controller | Method | JS Function | JS File | Line |')
        lines.append('|---|---|---|---|---|---|')
        for call in ajax_calls:
            lines.append(
                f'| `{call["url"][:60]}` | `{call["controller"] or "?"}` | '
                f'`{call["method"] or "?"}` | `{call["js_function"] or "?"}` | '
                f'`{os.path.basename(call["file"])}` | L{call["line"]} |'
            )
        lines.append('')
    else:
        lines.append('No AJAX calls found for this module.')
        lines.append('')

    return '\n'.join(lines)


def gen_view_map(module_name, view_map, view_files):
    """Generate VIEW_MAP.md"""
    lines = [
        f'# View Map — {module_name}',
        '',
        f'> Auto-generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
        f'**{len(view_files)} view files, {len(view_map)} referenced in controllers**',
        '',
    ]

    if view_map:
        lines.append('| View | Loaded By | Method | Line |')
        lines.append('|---|---|---|---|')
        for view_name, loaders in sorted(view_map.items()):
            for loader in loaders:
                lines.append(
                    f'| `{view_name}` | `{loader["controller"]}` | '
                    f'`{loader["method"]}()` | L{loader["line"]} |'
                )
        lines.append('')

    # Unreferenced views
    referenced = set(view_map.keys())
    unreferenced = [v for v in view_files if not any(
        v['name'].replace('.php', '') in ref or ref in v['rel_path']
        for ref in referenced
    )]
    if unreferenced:
        lines.append('### Potentially Unreferenced Views')
        lines.append('')
        for v in unreferenced[:20]:
            lines.append(f'- `{v["rel_path"]}`')
        lines.append('')

    return '\n'.join(lines)


def gen_shared_deps(module_name, sql_data, project_root):
    """Generate SHARED_DEPENDENCIES.md"""
    lines = [
        f'# Shared Dependencies — {module_name}',
        '',
        f'> Auto-generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
    ]

    # Get all tables this module touches
    module_tables = defaultdict(lambda: {'operations': set(), 'functions': set()})
    for ref in sql_data:
        if ref['module'] == module_name:
            module_tables[ref['table']]['operations'].add(ref['operation'])
            module_tables[ref['table']]['functions'].add(ref['function'])

    # Get all tables from ALL modules to find shared ones
    all_module_tables = defaultdict(set)
    for ref in sql_data:
        all_module_tables[ref['table']].add(ref['module'])

    # Find shared tables (tables this module uses that other modules also use)
    shared = []
    exclusive = []
    for table, info in sorted(module_tables.items()):
        other_modules = all_module_tables[table] - {module_name}
        if other_modules:
            shared.append({
                'table': table,
                'our_ops': sorted(info['operations']),
                'our_funcs': sorted(info['functions']),
                'other_modules': sorted(other_modules),
            })
        else:
            exclusive.append({
                'table': table,
                'ops': sorted(info['operations']),
                'funcs': sorted(info['functions']),
            })

    lines.append(f'| Category | Count |')
    lines.append(f'|---|---|')
    lines.append(f'| **Total tables used** | {len(module_tables)} |')
    lines.append(f'| **Shared with other modules** | {len(shared)} |')
    lines.append(f'| **Exclusive to {module_name}** | {len(exclusive)} |')
    lines.append('')

    if shared:
        lines.append('## Shared Tables')
        lines.append('')
        lines.append('> Changes to these tables require cross-module testing.')
        lines.append('')
        lines.append('| Table | Our Ops | Shared With | Our Functions |')
        lines.append('|---|---|---|---|')
        for s in sorted(shared, key=lambda x: len(x['other_modules']), reverse=True):
            ops = ', '.join(s['our_ops'])
            mods = ', '.join(f'`{m}`' for m in s['other_modules'][:5])
            if len(s['other_modules']) > 5:
                mods += f' +{len(s["other_modules"])-5}'
            funcs = ', '.join(f'`{f}`' for f in list(s['our_funcs'])[:3])
            if len(s['our_funcs']) > 3:
                funcs += f' +{len(s["our_funcs"])-3}'
            lines.append(f'| `{s["table"]}` | {ops} | {mods} | {funcs} |')
        lines.append('')

    if exclusive:
        lines.append('## Exclusive Tables')
        lines.append('')
        lines.append('| Table | Operations | Functions |')
        lines.append('|---|---|---|')
        for e in exclusive:
            ops = ', '.join(e['ops'])
            funcs = ', '.join(f'`{f}`' for f in list(e['funcs'])[:3])
            lines.append(f'| `{e["table"]}` | {ops} | {funcs} |')
        lines.append('')

    return '\n'.join(lines)


def gen_module_brain(module_name, controller_data, model_data, stats):
    """Generate MODULE_BRAIN.md skeleton"""
    lines = [
        f'# Module Brain — {module_name}',
        '',
        f'> Auto-generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        f'> **Status: SKELETON** — Business logic sections need human review',
        '',
        '---',
        '',
        '## Overview',
        '',
        f'| Metric | Value |',
        f'|---|---|',
        f'| **Controllers** | {stats["controller_count"]} |',
        f'| **Models** | {stats["model_count"]} |',
        f'| **Views** | {stats["view_count"]} |',
        f'| **JS Files** | {stats["js_count"]} |',
        f'| **Total Methods** | {stats["total_methods"]} |',
        f'| **Page Endpoints** | {stats["page_endpoints"]} |',
        f'| **AJAX Endpoints** | {stats["ajax_endpoints"]} |',
        f'| **Tables Used** | {stats["tables_used"]} |',
        f'| **Shared Tables** | {stats["shared_tables"]} |',
        f'| **Critical Module** | {"Yes" if module_name in CRITICAL_MODULES else "No"} |',
        '',
        '## Business Logic',
        '',
        '<!-- TODO: Describe what this module does in business terms -->',
        '<!-- Example: "Handles customer billing, invoicing, payment processing, and returns" -->',
        '',
        '## Key Business Rules',
        '',
        '<!-- TODO: Document critical business rules -->',
        '<!-- Example: "Bill totals must match sum of line items + taxes - discounts" -->',
        '',
        '1. <!-- Rule 1 -->',
        '2. <!-- Rule 2 -->',
        '3. <!-- Rule 3 -->',
        '',
        '## Danger Zones',
        '',
        '<!-- TODO: Document areas that break silently -->',
        '<!-- Example: "Changing ret_billing.total_amount without updating billing_payment will cause mismatch" -->',
        '',
        '## File Index',
        '',
    ]

    # Controllers
    for ctrl in controller_data:
        total = len(ctrl['methods'])
        public = len([m for m in ctrl['methods'] if m['visibility'] == 'public'])
        lines.append(f'- **Controller**: `{ctrl["class_name"]}` ({total} methods, {public} public)')

    for model_file, methods in model_data.items():
        basename = os.path.basename(model_file)
        lines.append(f'- **Model**: `{basename}` ({len(methods)} methods)')

    lines.append('')
    lines.append('## Cross-References')
    lines.append('')
    lines.append('- [Controller Map](CONTROLLER_MAP.md)')
    lines.append('- [Model Map](MODEL_MAP.md)')
    lines.append('- [AJAX Map](AJAX_MAP.md)')
    lines.append('- [View Map](VIEW_MAP.md)')
    lines.append('- [Shared Dependencies](SHARED_DEPENDENCIES.md)')
    lines.append('- [Dead Code Report](DEAD_CODE.md)')
    lines.append('- [Data Flow](DATA_FLOW.md)')
    lines.append('')

    return '\n'.join(lines)


def gen_data_flow(module_name, chains):
    """Generate DATA_FLOW.md skeleton with traced call chains."""
    lines = [
        f'# Data Flow — {module_name}',
        '',
        f'> Auto-generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        f'> **Status: SKELETON** — Business flow descriptions need human review',
        '',
        '---',
        '',
        '## Business Flows',
        '',
        '<!-- TODO: Group these chains into business flows -->',
        '<!-- Example: "Create Bill Flow", "Return Bill Flow", "Payment Processing" -->',
        '',
    ]

    # Group chains by type
    page_chains = [c for c in chains if c['type'] == 'PAGE']
    ajax_chains = [c for c in chains if c['type'] == 'AJAX']
    action_chains = [c for c in chains if c['type'] == 'ACTION']

    if page_chains:
        lines.append('## Page Load Flows')
        lines.append('')
        for chain in page_chains[:20]:
            lines.append(f'### `{chain["entry"]}`')
            lines.append('')
            if chain['model_calls']:
                lines.append('```')
                lines.append(f'  {chain["entry"]}')
                for mc in chain['model_calls']:
                    tables_str = ', '.join(f'{t}({op[0]})' for t, op in mc['tables'][:3])
                    lines.append(f'    -> {mc["model"]}.{mc["method"]}() [{tables_str}]')
                if chain['views']:
                    for v in chain['views']:
                        lines.append(f'    -> view: {v}')
                lines.append('```')
                lines.append('')

    if ajax_chains:
        lines.append('## AJAX Flows')
        lines.append('')
        lines.append('| Entry Point | Model Calls | Tables |')
        lines.append('|---|---|---|')
        for chain in ajax_chains[:40]:
            calls = ', '.join(f'`{mc["model"]}.{mc["method"]}`' for mc in chain['model_calls'][:3])
            tables = ', '.join(f'`{t}`' for t, op in chain['tables'][:4])
            lines.append(f'| `{chain["entry"]}` | {calls or "-"} | {tables or "-"} |')
        lines.append('')

    if action_chains:
        lines.append('## Action Flows')
        lines.append('')
        lines.append('| Entry Point | Model Calls | Tables |')
        lines.append('|---|---|---|')
        for chain in action_chains[:20]:
            calls = ', '.join(f'`{mc["model"]}.{mc["method"]}`' for mc in chain['model_calls'][:3])
            tables = ', '.join(f'`{t}`' for t, op in chain['tables'][:4])
            lines.append(f'| `{chain["entry"]}` | {calls or "-"} | {tables or "-"} |')
        lines.append('')

    return '\n'.join(lines)


def gen_dead_code_report(module_name, dead_funcs, dup_clusters):
    """Generate DEAD_CODE.md combining dead code + duplicate analysis."""
    lines = [
        f'# Dead Code & Duplicates — {module_name}',
        '',
        f'> Auto-generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
        f'| Metric | Value |',
        f'|---|---|',
        f'| **Dead functions** | {len(dead_funcs)} |',
        f'| **Duplicate clusters** | {len(dup_clusters)} |',
        '',
    ]

    if dead_funcs:
        lines.append('## Dead Functions (Zero Callers)')
        lines.append('')
        lines.append('| Function | File | Line | Visibility |')
        lines.append('|---|---|---|---|')
        for f in dead_funcs:
            lines.append(f'| `{f["name"]}()` | `{os.path.basename(f["file"])}` | L{f["line"]} | {f["visibility"]} |')
        lines.append('')

    if dup_clusters:
        lines.append('## Duplicate Functions')
        lines.append('')
        for i, dup in enumerate(dup_clusters, 1):
            lines.append(f'### Cluster {i} ({dup["similarity"]}% similar)')
            lines.append('')
            lines.append('| Function | File | Line |')
            lines.append('|---|---|---|')
            for f in dup['functions']:
                lines.append(f'| `{f["name"]}()` | `{os.path.basename(f["file"])}` | L{f["line"]} |')
            lines.append('')

    return '\n'.join(lines)


# ── Main Orchestrator ─────────────────────────────────────────────────

def generate_brain(project_root, module_name, output_dir):
    """Generate all Module Brain documents."""
    print(f'Generating Module Brain for: {module_name}', file=sys.stderr)

    # Step 1: Discover files
    print('  [1/7] Discovering module files...', file=sys.stderr)
    files = discover_module_files(project_root, module_name)
    print(f'    Found: {len(files["controllers"])} controllers, '
          f'{len(files["models"])} models, {len(files["views"])} views, '
          f'{len(files["js"])} JS files', file=sys.stderr)

    # Step 2: Extract controller methods
    print('  [2/7] Extracting controller methods...', file=sys.stderr)
    controller_data = []
    for ctrl_file in files['controllers']:
        data = extract_controller_methods(ctrl_file['path'])
        if data['methods']:
            controller_data.append(data)

    # Step 3: Extract model methods with SQL mapping
    print('  [3/7] Extracting model methods + SQL references...', file=sys.stderr)
    model_data = {}
    for model_file in files['models']:
        methods = extract_model_methods(model_file['path'], project_root)
        if methods:
            model_data[model_file['rel_path']] = methods

    # Step 4: Extract AJAX calls
    print('  [4/7] Extracting AJAX calls from JS...', file=sys.stderr)
    ajax_calls = extract_ajax_calls(files['js'], module_name)

    # Step 5: Extract view mapping
    print('  [5/7] Mapping view loads...', file=sys.stderr)
    view_map = extract_view_loads(files['controllers'])

    # Step 6: Collect SQL data for shared deps
    print('  [6/7] Analyzing shared dependencies...', file=sys.stderr)
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
                refs = sql_scan_file(fpath, project_root)
                all_sql_refs.extend(refs)

    # Step 7: Trace call chains
    print('  [7/7] Tracing call chains...', file=sys.stderr)
    chains = trace_call_chains(controller_data, model_data)

    # Compute stats
    total_methods = sum(len(d['methods']) for d in controller_data) + sum(len(m) for m in model_data.values())
    page_endpoints = sum(1 for d in controller_data for m in d['methods'] if m['is_page'])
    ajax_endpoints = sum(1 for d in controller_data for m in d['methods'] if m['is_ajax'])

    module_tables = set()
    shared_count = 0
    all_module_tables = defaultdict(set)
    for ref in all_sql_refs:
        all_module_tables[ref['table']].add(ref['module'])
        if ref['module'] == module_name:
            module_tables.add(ref['table'])
    for t in module_tables:
        if len(all_module_tables[t]) > 1:
            shared_count += 1

    stats = {
        'controller_count': len(files['controllers']),
        'model_count': len(files['models']),
        'view_count': len(files['views']),
        'js_count': len(files['js']),
        'total_methods': total_methods,
        'page_endpoints': page_endpoints,
        'ajax_endpoints': ajax_endpoints,
        'tables_used': len(module_tables),
        'shared_tables': shared_count,
    }

    # Generate documents
    os.makedirs(output_dir, exist_ok=True)
    docs = {
        'CONTROLLER_MAP.md': gen_controller_map(module_name, controller_data),
        'MODEL_MAP.md': gen_model_map(module_name, model_data),
        'AJAX_MAP.md': gen_ajax_map(module_name, ajax_calls),
        'VIEW_MAP.md': gen_view_map(module_name, view_map, files['views']),
        'SHARED_DEPENDENCIES.md': gen_shared_deps(module_name, all_sql_refs, project_root),
        'MODULE_BRAIN.md': gen_module_brain(module_name, controller_data, model_data, stats),
        'DATA_FLOW.md': gen_data_flow(module_name, chains),
    }

    for filename, content in docs.items():
        filepath = os.path.join(output_dir, filename)
        Path(filepath).write_text(content, encoding='utf-8')
        print(f'  Wrote: {filepath}', file=sys.stderr)

    return stats


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Auto Module Brain Generator')
    parser.add_argument('--project-root', required=True, help='Project root directory')
    parser.add_argument('--module', required=True, help='Module name (e.g., billing)')
    parser.add_argument('--output-dir', help='Output directory (default: knowledge_brain/{module})')

    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)
    output_dir = args.output_dir or os.path.join(project_root, 'knowledge_brain', args.module, '_auto')

    print(f'Auto Module Brain Generator', file=sys.stderr)
    print(f'Project: {project_root}', file=sys.stderr)
    print(f'Module:  {args.module}', file=sys.stderr)
    print(f'Output:  {output_dir}', file=sys.stderr)
    print('', file=sys.stderr)

    t_start = time.time()
    stats = generate_brain(project_root, args.module, output_dir)
    elapsed = time.time() - t_start

    print('', file=sys.stderr)
    print(f'Done in {elapsed:.1f}s', file=sys.stderr)
    print(f'  Methods indexed: {stats["total_methods"]}', file=sys.stderr)
    print(f'  Page endpoints:  {stats["page_endpoints"]}', file=sys.stderr)
    print(f'  AJAX endpoints:  {stats["ajax_endpoints"]}', file=sys.stderr)
    print(f'  Tables used:     {stats["tables_used"]}', file=sys.stderr)
    print(f'  Shared tables:   {stats["shared_tables"]}', file=sys.stderr)

    sys.exit(0)


if __name__ == '__main__':
    main()
