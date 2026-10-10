#!/usr/bin/env python3
"""
Onboarding Flow Map Generator (C6)
====================================
Generates visual Mermaid flow diagrams for a module.

For a new dev assigned to a module, this produces:
  1. Module overview diagram (controllers → models → tables)
  2. Per-endpoint flow diagrams (entry → model calls → DB writes)
  3. AJAX dependency map (JS function → controller → model → table)

Output: Markdown file with embedded Mermaid diagrams.

Usage:
    python flow_map.py --project-root /path/to/project --module billing
    python flow_map.py --project-root /path/to/project --module billing --output flows.md
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
from auto_brain import (
    discover_module_files,
    extract_controller_methods,
    extract_model_methods,
    extract_ajax_calls,
)
from impact_analyzer import detect_module


# ── Mermaid Sanitizer ─────────────────────────────────────────────────

def mermaid_safe(text):
    """Make text safe for Mermaid node labels."""
    # Replace characters that break Mermaid syntax
    text = text.replace('"', "'")
    text = text.replace('(', '_')
    text = text.replace(')', '_')
    text = text.replace('[', '_')
    text = text.replace(']', '_')
    text = text.replace('{', '_')
    text = text.replace('}', '_')
    text = text.replace('<', '_')
    text = text.replace('>', '_')
    text = text.replace('|', '_')
    text = text.replace('#', '_')
    text = text.replace('&', '_')
    text = text.replace(';', '_')
    return text


def node_id(prefix, name):
    """Create a valid Mermaid node ID."""
    return f'{prefix}_{re.sub(r"[^a-zA-Z0-9_]", "_", name)}'


# ── Diagram Generators ────────────────────────────────────────────────

def gen_overview_diagram(module_name, controller_data, model_data, tables_by_module):
    """Generate module overview Mermaid diagram."""
    lines = ['```mermaid', 'graph LR']
    lines.append(f'    subgraph "{module_name} Module"')

    # Controllers
    for ctrl in controller_data:
        cid = node_id('ctrl', ctrl['class_name'])
        pages = sum(1 for m in ctrl['methods'] if m['is_page'])
        ajax = sum(1 for m in ctrl['methods'] if m['is_ajax'])
        lines.append(f'        {cid}["{ctrl["class_name"]}<br/>{pages} pages, {ajax} AJAX"]')

    # Models
    for model_file, methods in model_data.items():
        basename = os.path.basename(model_file).replace('.php', '')
        mid = node_id('model', basename)
        lines.append(f'        {mid}["{basename}<br/>{len(methods)} methods"]')

    lines.append('    end')

    # Tables (top ones only)
    tables = tables_by_module.get(module_name, {})
    top_tables = sorted(tables.items(), key=lambda x: x[1], reverse=True)[:15]
    if top_tables:
        lines.append(f'    subgraph "Database Tables"')
        for table, count in top_tables:
            tid = node_id('tbl', table)
            lines.append(f'        {tid}[("{table}")]')
        lines.append('    end')

    # Edges: controller → model
    for ctrl in controller_data:
        cid = node_id('ctrl', ctrl['class_name'])
        model_targets = set()
        for method in ctrl['methods']:
            for model_name, _ in method['model_calls']:
                model_targets.add(model_name)
        for mt in model_targets:
            mid = node_id('model', mt.replace('.php', ''))
            lines.append(f'    {cid} --> {mid}')

    # Edges: model → table
    for model_file, methods in model_data.items():
        basename = os.path.basename(model_file).replace('.php', '')
        mid = node_id('model', basename)
        model_tables = set()
        for m in methods:
            for table, op in m['tables'][:5]:
                if table in dict(top_tables):
                    model_tables.add(table)
        for t in list(model_tables)[:8]:
            tid = node_id('tbl', t)
            lines.append(f'    {mid} --> {tid}')

    lines.append('```')
    return '\n'.join(lines)


def gen_endpoint_flows(module_name, controller_data, model_data):
    """Generate per-endpoint flow diagrams for key endpoints."""
    flows = []

    for ctrl in controller_data:
        # Only show page endpoints and important AJAX endpoints
        key_methods = [m for m in ctrl['methods']
                       if (m['is_page'] or (m['is_ajax'] and m['model_calls']))
                       and m['visibility'] == 'public'
                       and not m['name'].startswith('_')]

        for method in key_methods[:20]:  # Limit to 20 most important
            if not method['model_calls'] and not method['view_loads']:
                continue

            flow_lines = ['```mermaid', 'graph TD']
            entry_id = node_id('entry', method['name'])
            flow_type = 'PAGE' if method['is_page'] else 'AJAX'
            flow_lines.append(f'    {entry_id}["{method["name"]}()<br/>{flow_type}"]')

            # Model calls
            for i, (model_name, model_method) in enumerate(method['model_calls']):
                call_id = node_id(f'call{i}', model_method)
                flow_lines.append(f'    {call_id}["{model_name}.{model_method}()"]')
                flow_lines.append(f'    {entry_id} --> {call_id}')

                # Find tables for this model method
                for model_file, model_methods in model_data.items():
                    for mm in model_methods:
                        if mm['name'] == model_method:
                            for j, (table, op) in enumerate(mm['tables'][:4]):
                                tid = node_id(f'tbl{i}_{j}', table)
                                op_label = 'R' if op == 'READ' else 'W'
                                flow_lines.append(f'    {tid}[("{table} [{op_label}]")]')
                                style = '-.->|read|' if op == 'READ' else '==>|write|'
                                flow_lines.append(f'    {call_id} {style} {tid}')
                            break

            # Views
            for i, view in enumerate(method['view_loads'][:3]):
                vid = node_id(f'view{i}', view)
                flow_lines.append(f'    {vid}[/"{view}"/]')
                flow_lines.append(f'    {entry_id} --> {vid}')

            flow_lines.append('```')

            flows.append({
                'name': method['name'],
                'type': flow_type,
                'diagram': '\n'.join(flow_lines),
                'params': method['params'],
            })

    return flows


def gen_ajax_dependency_map(module_name, ajax_calls, controller_data):
    """Generate AJAX dependency diagram showing JS → Controller mapping."""
    lines = ['```mermaid', 'graph LR']

    # Group AJAX calls by JS file
    by_file = defaultdict(list)
    for call in ajax_calls:
        by_file[os.path.basename(call['file'])].append(call)

    # JS files
    lines.append('    subgraph "JavaScript"')
    js_funcs = set()
    for fname, calls in by_file.items():
        for call in calls[:10]:
            if call['js_function']:
                fid = node_id('js', call['js_function'])
                if fid not in js_funcs:
                    lines.append(f'        {fid}["{call["js_function"]}()"]')
                    js_funcs.add(fid)
    lines.append('    end')

    # Controller methods
    ctrl_methods = set()
    lines.append('    subgraph "Controller"')
    for call in ajax_calls:
        if call['method']:
            mid = node_id('ctrl_m', call['method'])
            if mid not in ctrl_methods:
                lines.append(f'        {mid}["{call["method"]}()"]')
                ctrl_methods.add(mid)
    lines.append('    end')

    # Edges: JS → Controller
    edges = set()
    for call in ajax_calls:
        if call['js_function'] and call['method']:
            fid = node_id('js', call['js_function'])
            mid = node_id('ctrl_m', call['method'])
            edge = f'    {fid} --> {mid}'
            if edge not in edges:
                lines.append(edge)
                edges.add(edge)

    lines.append('```')
    return '\n'.join(lines)


# ── Report Generator ──────────────────────────────────────────────────

def generate_flow_map(project_root, module_name, output_path):
    """Generate complete onboarding flow map."""
    print(f'Generating flow maps for: {module_name}', file=sys.stderr)

    # Discover and extract
    files = discover_module_files(project_root, module_name)
    controller_data = []
    for ctrl_file in files['controllers']:
        data = extract_controller_methods(ctrl_file['path'])
        if data['methods']:
            controller_data.append(data)

    model_data = {}
    for model_file in files['models']:
        methods = extract_model_methods(model_file['path'], project_root)
        if methods:
            model_data[model_file['rel_path']] = methods

    ajax_calls = extract_ajax_calls(files['js'], module_name)

    # Build tables-by-module for overview
    tables_by_module = defaultdict(lambda: defaultdict(int))
    for model_file, methods in model_data.items():
        for m in methods:
            for table, op in m['tables']:
                tables_by_module[module_name][table] += 1

    # Generate report
    lines = [
        f'# Onboarding Flow Maps — {module_name}',
        '',
        f'> Auto-generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        f'> Use these diagrams to understand the module before diving into code.',
        '',
        '---',
        '',
        '## Module Overview',
        '',
        '> High-level view: Controllers → Models → Tables',
        '',
        gen_overview_diagram(module_name, controller_data, model_data, tables_by_module),
        '',
        '---',
        '',
    ]

    # AJAX dependency map
    if ajax_calls:
        lines.extend([
            '## AJAX Dependency Map',
            '',
            '> Which JS functions call which controller methods',
            '',
            gen_ajax_dependency_map(module_name, ajax_calls, controller_data),
            '',
            '---',
            '',
        ])

    # Endpoint flows
    flows = gen_endpoint_flows(module_name, controller_data, model_data)
    if flows:
        # Group by type
        page_flows = [f for f in flows if f['type'] == 'PAGE']
        ajax_flows = [f for f in flows if f['type'] == 'AJAX']

        if page_flows:
            lines.extend([
                '## Page Endpoint Flows',
                '',
                '> Entry point → model calls → database tables → view rendered',
                '',
            ])
            for flow in page_flows:
                lines.append(f'### `{flow["name"]}({flow["params"]})`')
                lines.append('')
                lines.append(flow['diagram'])
                lines.append('')

        if ajax_flows:
            lines.extend([
                '## Key AJAX Flows',
                '',
                '> AJAX endpoint → model calls → database reads/writes',
                '',
            ])
            for flow in ajax_flows[:15]:  # Limit
                lines.append(f'### `{flow["name"]}()`')
                lines.append('')
                lines.append(flow['diagram'])
                lines.append('')

    lines.extend([
        '---',
        '*Generated by Logimax Onboarding Flow Maps (LCA)*',
    ])

    report = '\n'.join(lines)

    if output_path:
        Path(output_path).parent.mkdir(parents=True, exist_ok=True)
        Path(output_path).write_text(report, encoding='utf-8')
        print(f'Report written to {output_path}', file=sys.stderr)
    else:
        if sys.platform == 'win32':
            sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        print(report)

    return len(flows)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Onboarding Flow Map Generator')
    parser.add_argument('--project-root', required=True, help='Project root directory')
    parser.add_argument('--module', required=True, help='Module name')
    parser.add_argument('--output', help='Output markdown file')

    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)
    output = args.output or os.path.join(
        project_root, 'knowledge_brain', args.module, '_auto', 'FLOW_MAPS.md'
    )

    t_start = time.time()
    count = generate_flow_map(project_root, args.module, output)
    elapsed = time.time() - t_start

    print(f'Done in {elapsed:.1f}s — {count} flow diagrams generated', file=sys.stderr)
    sys.exit(0)


if __name__ == '__main__':
    main()
