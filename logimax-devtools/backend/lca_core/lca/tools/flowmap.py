"""Flow Map Generator -- Mermaid flow diagrams for module onboarding.

Generates:
  1. Module overview diagram (controllers -> models -> tables)
  2. Per-endpoint flow diagrams (entry -> model calls -> DB writes)
  3. AJAX dependency map (JS function -> controller -> model -> table)

Output: Markdown with embedded Mermaid diagrams.

Ported from standalone flow_map.py into LCA v2 package.
"""

from __future__ import annotations

import os
import re
from collections import defaultdict
from datetime import datetime
from pathlib import Path

from lca.tools.brain import (
    discover_module_files,
    extract_controller_methods,
    extract_model_methods,
    extract_ajax_calls,
)


# ---- Mermaid Helpers -------------------------------------------------

def _mermaid_safe(text: str) -> str:
    """Make text safe for Mermaid node labels."""
    for ch in '"()[]{}|#&;<>':
        text = text.replace(ch, '_')
    return text


def _node_id(prefix: str, name: str) -> str:
    """Create a valid Mermaid node ID."""
    return f'{prefix}_{re.sub(r"[^a-zA-Z0-9_]", "_", name)}'


# ---- Diagram Generators ---------------------------------------------

def gen_overview_diagram(module_name, controller_data, model_data, tables_by_module):
    """Module overview: controllers -> models -> tables."""
    lines = ['```mermaid', 'graph LR']
    lines.append(f'    subgraph "{module_name} Module"')

    for ctrl in controller_data:
        cid = _node_id('ctrl', ctrl['class_name'])
        pages = sum(1 for m in ctrl['methods'] if m['is_page'])
        ajax = sum(1 for m in ctrl['methods'] if m['is_ajax'])
        lines.append(f'        {cid}["{ctrl["class_name"]}<br/>{pages} pages, {ajax} AJAX"]')

    for model_file, methods in model_data.items():
        basename = os.path.basename(model_file).replace('.php', '')
        mid = _node_id('model', basename)
        lines.append(f'        {mid}["{basename}<br/>{len(methods)} methods"]')

    lines.append('    end')

    tables = tables_by_module.get(module_name, {})
    top_tables = sorted(tables.items(), key=lambda x: x[1], reverse=True)[:15]
    if top_tables:
        lines.append('    subgraph "Database Tables"')
        for table, _ in top_tables:
            tid = _node_id('tbl', table)
            lines.append(f'        {tid}[("{table}")]')
        lines.append('    end')

    for ctrl in controller_data:
        cid = _node_id('ctrl', ctrl['class_name'])
        targets = set()
        for method in ctrl['methods']:
            for model_name, _ in method['model_calls']:
                targets.add(model_name)
        for mt in targets:
            mid = _node_id('model', mt.replace('.php', ''))
            lines.append(f'    {cid} --> {mid}')

    for model_file, methods in model_data.items():
        basename = os.path.basename(model_file).replace('.php', '')
        mid = _node_id('model', basename)
        model_tables = set()
        for m in methods:
            for table, op in m['tables'][:5]:
                if table in dict(top_tables):
                    model_tables.add(table)
        for t in list(model_tables)[:8]:
            tid = _node_id('tbl', t)
            lines.append(f'    {mid} --> {tid}')

    lines.append('```')
    return '\n'.join(lines)


def gen_endpoint_flows(controller_data, model_data):
    """Per-endpoint flow diagrams for key endpoints."""
    flows = []
    for ctrl in controller_data:
        key_methods = [
            m for m in ctrl['methods']
            if (m['is_page'] or (m['is_ajax'] and m['model_calls']))
            and m['visibility'] == 'public' and not m['name'].startswith('_')
        ]
        for method in key_methods[:20]:
            if not method['model_calls'] and not method['view_loads']:
                continue

            fl = ['```mermaid', 'graph TD']
            eid = _node_id('entry', method['name'])
            ftype = 'PAGE' if method['is_page'] else 'AJAX'
            fl.append(f'    {eid}["{method["name"]}()<br/>{ftype}"]')

            for i, (model_name, model_method) in enumerate(method['model_calls']):
                cid = _node_id(f'call{i}', model_method)
                fl.append(f'    {cid}["{model_name}.{model_method}()"]')
                fl.append(f'    {eid} --> {cid}')

                for _, model_methods in model_data.items():
                    for mm in model_methods:
                        if mm['name'] == model_method:
                            for j, (table, op) in enumerate(mm['tables'][:4]):
                                tid = _node_id(f'tbl{i}_{j}', table)
                                op_label = 'R' if op == 'READ' else 'W'
                                fl.append(f'    {tid}[("{table} [{op_label}]")]')
                                style = '-.->|read|' if op == 'READ' else '==>|write|'
                                fl.append(f'    {cid} {style} {tid}')
                            break

            for i, view in enumerate(method['view_loads'][:3]):
                vid = _node_id(f'view{i}', view)
                fl.append(f'    {vid}[/"{view}"/]')
                fl.append(f'    {eid} --> {vid}')

            fl.append('```')
            flows.append({
                'name': method['name'], 'type': ftype,
                'diagram': '\n'.join(fl), 'params': method['params'],
            })

    return flows


def gen_ajax_dep_map(ajax_calls):
    """AJAX dependency diagram: JS -> Controller."""
    lines = ['```mermaid', 'graph LR']
    by_file = defaultdict(list)
    for call in ajax_calls:
        by_file[os.path.basename(call['file'])].append(call)

    lines.append('    subgraph "JavaScript"')
    js_funcs = set()
    for _, calls in by_file.items():
        for call in calls[:10]:
            if call['js_function']:
                fid = _node_id('js', call['js_function'])
                if fid not in js_funcs:
                    lines.append(f'        {fid}["{call["js_function"]}()"]')
                    js_funcs.add(fid)
    lines.append('    end')

    lines.append('    subgraph "Controller"')
    ctrl_methods = set()
    for call in ajax_calls:
        if call['method']:
            mid = _node_id('ctrl_m', call['method'])
            if mid not in ctrl_methods:
                lines.append(f'        {mid}["{call["method"]}()"]')
                ctrl_methods.add(mid)
    lines.append('    end')

    edges = set()
    for call in ajax_calls:
        if call['js_function'] and call['method']:
            fid = _node_id('js', call['js_function'])
            mid = _node_id('ctrl_m', call['method'])
            edge = f'    {fid} --> {mid}'
            if edge not in edges:
                lines.append(edge)
                edges.add(edge)

    lines.append('```')
    return '\n'.join(lines)


# ---- Main Generator --------------------------------------------------

def generate_flow_map(
    project_root: str,
    module_name: str,
    output_path: str | None = None,
) -> tuple[str, int]:
    """Generate complete onboarding flow map.

    Args:
        project_root: Project root path
        module_name: Module name
        output_path: Output markdown file (None = return string)

    Returns:
        (markdown_content, flow_count)
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

    tables_by_module = defaultdict(lambda: defaultdict(int))
    for _, methods in model_data.items():
        for m in methods:
            for table, op in m['tables']:
                tables_by_module[module_name][table] += 1

    ts = datetime.now().strftime("%Y-%m-%d %H:%M")
    lines = [
        f'# Flow Maps -- {module_name}', '',
        f'> Auto-generated on {ts}', '',
        '---', '', '## Module Overview', '',
        gen_overview_diagram(module_name, controller_data, model_data, tables_by_module),
        '', '---', '',
    ]

    if ajax_calls:
        lines.extend([
            '## AJAX Dependency Map', '',
            gen_ajax_dep_map(ajax_calls),
            '', '---', '',
        ])

    flows = gen_endpoint_flows(controller_data, model_data)
    if flows:
        page_flows = [f for f in flows if f['type'] == 'PAGE']
        ajax_flows = [f for f in flows if f['type'] == 'AJAX']

        if page_flows:
            lines.extend(['## Page Endpoint Flows', ''])
            for flow in page_flows:
                lines.extend([f'### `{flow["name"]}({flow["params"]})`', '', flow['diagram'], ''])

        if ajax_flows:
            lines.extend(['## Key AJAX Flows', ''])
            for flow in ajax_flows[:15]:
                lines.extend([f'### `{flow["name"]}()`', '', flow['diagram'], ''])

    lines.extend(['---', '*Generated by LCA v2*'])
    content = '\n'.join(lines)

    if output_path:
        Path(output_path).parent.mkdir(parents=True, exist_ok=True)
        Path(output_path).write_text(content, encoding='utf-8')

    return content, len(flows)
