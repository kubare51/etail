#!/usr/bin/env python3
"""
API Surface Mapper — Auto-document all HTTP endpoints
=======================================================
Scans CI3 controllers to extract all public page endpoints
and AJAX endpoints with their parameters.

Usage:
    python api_mapper.py --project-root . --module billing
    python api_mapper.py --project-root . --all --output api_surface.md
"""

import argparse
import os
import re
import sys
from datetime import datetime


def discover_controllers(project_root, module=None):
    """Find controller files."""
    ctrl_dir = os.path.join(project_root, 'admin', 'application', 'controllers')
    if not os.path.exists(ctrl_dir):
        return []

    controllers = []
    for f in os.listdir(ctrl_dir):
        if not f.endswith('.php'):
            continue
        if module:
            if module.lower() not in f.lower():
                continue
        controllers.append(os.path.join(ctrl_dir, f))

    return sorted(controllers)


def extract_endpoints(filepath):
    """Extract public methods and their details from a controller."""
    with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
        content = f.read()

    basename = os.path.basename(filepath).replace('.php', '')

    endpoints = []

    # Find class name
    class_match = re.search(r'class\s+(\w+)', content)
    class_name = class_match.group(1) if class_match else basename

    # Find all public functions
    func_pattern = re.compile(
        r'(?:public\s+)?function\s+(\w+)\s*\([^)]*\)',
        re.MULTILINE
    )

    lines = content.split('\n')

    for match in func_pattern.finditer(content):
        func_name = match.group(1)

        # Skip magic methods and private-like
        if func_name.startswith('_') or func_name == '__construct':
            continue

        # Get line number
        pos = match.start()
        line_num = content[:pos].count('\n') + 1

        # Detect if AJAX (look for json_encode in the function body)
        # Find the function body (up to next function or end)
        func_start = pos
        next_func = func_pattern.search(content, match.end())
        func_end = next_func.start() if next_func else len(content)
        func_body = content[func_start:func_end]

        is_ajax = bool(re.search(r'json_encode|echo\s+json|header.*application/json', func_body))

        # Detect HTTP method hints
        has_post = bool(re.search(r'\$this->input->post|is_post|POST', func_body))
        has_get = bool(re.search(r'\$this->input->get|\$_GET', func_body))

        if has_post:
            method = 'POST'
        elif has_get:
            method = 'GET'
        else:
            method = 'GET' if not is_ajax else 'POST'

        # Extract input parameters
        params = []
        post_params = re.findall(r"\$this->input->post\(['\"](\w+)['\"]\)", func_body)
        get_params = re.findall(r"\$this->input->get\(['\"](\w+)['\"]\)", func_body)
        params = list(set(post_params + get_params))

        # Detect auth check
        has_auth = bool(re.search(r'logged_in|is_logged|check_login|session|access_right', func_body))

        # Detect view load (page endpoint vs AJAX)
        loads_view = bool(re.search(r'\$this->load->view', func_body))

        endpoint = {
            'controller': basename,
            'class': class_name,
            'function': func_name,
            'url': f'/admin/index.php/{basename}/{func_name}',
            'method': method,
            'is_ajax': is_ajax,
            'is_page': loads_view and not is_ajax,
            'params': sorted(params),
            'auth_required': has_auth,
            'line': line_num,
        }

        endpoints.append(endpoint)

    return endpoints


def detect_module(controller_name):
    """Detect module from controller name."""
    name = controller_name.lower()
    module_map = {
        'billing': 'billing', 'estimation': 'estimation',
        'purchase': 'purchase', 'stock': 'stock',
        'report': 'reports', 'payment': 'payment',
        'customer': 'customer', 'tagging': 'tagging',
        'order': 'order', 'setting': 'settings',
        'employee': 'employee', 'dashboard': 'dashboard',
        'branch_transfer': 'branch_transfer',
    }
    for key, mod in module_map.items():
        if key in name:
            return mod
    return 'other'


def generate_report(all_endpoints, module_filter=None):
    """Generate markdown report."""
    lines = []
    lines.append('# API Surface Report')
    lines.append('')
    lines.append(f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}')
    lines.append('')

    pages = [e for e in all_endpoints if e['is_page']]
    ajax = [e for e in all_endpoints if e['is_ajax']]
    other = [e for e in all_endpoints if not e['is_page'] and not e['is_ajax']]

    lines.append(f'| Metric | Value |')
    lines.append(f'|---|---|')
    lines.append(f'| **Total endpoints** | {len(all_endpoints)} |')
    lines.append(f'| **Page endpoints** | {len(pages)} |')
    lines.append(f'| **AJAX endpoints** | {len(ajax)} |')
    lines.append(f'| **Other** | {len(other)} |')
    lines.append(f'| **Controllers** | {len(set(e["controller"] for e in all_endpoints))} |')
    lines.append('')

    # Page endpoints
    if pages:
        lines.append('## Page Endpoints')
        lines.append('')
        lines.append('| URL | Controller | Method | Auth | Params |')
        lines.append('|---|---|---|---|---|')
        for e in sorted(pages, key=lambda x: x['url']):
            auth = '🔒' if e['auth_required'] else '🔓'
            params = ', '.join(e['params'][:5]) or '-'
            lines.append(f'| `{e["url"]}` | {e["controller"]} | {e["method"]} | {auth} | {params} |')
        lines.append('')

    # AJAX endpoints
    if ajax:
        lines.append('## AJAX Endpoints')
        lines.append('')
        lines.append('| URL | Controller | Method | Auth | Params |')
        lines.append('|---|---|---|---|---|')
        for e in sorted(ajax, key=lambda x: x['url']):
            auth = '🔒' if e['auth_required'] else '🔓'
            params = ', '.join(e['params'][:5]) or '-'
            if len(e['params']) > 5:
                params += f' +{len(e["params"])-5}'
            lines.append(f'| `{e["url"]}` | {e["controller"]} | {e["method"]} | {auth} | {params} |')
        lines.append('')

    # Module breakdown
    modules = {}
    for e in all_endpoints:
        mod = detect_module(e['controller'])
        modules.setdefault(mod, {'pages': 0, 'ajax': 0, 'total': 0})
        modules[mod]['total'] += 1
        if e['is_page']:
            modules[mod]['pages'] += 1
        if e['is_ajax']:
            modules[mod]['ajax'] += 1

    if len(modules) > 1:
        lines.append('## By Module')
        lines.append('')
        lines.append('| Module | Pages | AJAX | Total |')
        lines.append('|---|---|---|---|')
        for mod in sorted(modules, key=lambda m: modules[m]['total'], reverse=True):
            m = modules[mod]
            lines.append(f'| `{mod}` | {m["pages"]} | {m["ajax"]} | {m["total"]} |')
        lines.append('')

    lines.append('---')
    lines.append('*Generated by Logimax API Mapper (LCA)*')

    return '\n'.join(lines)


def main():
    parser = argparse.ArgumentParser(description='API Surface Mapper')
    parser.add_argument('--project-root', required=True)
    parser.add_argument('--module', help='Filter to module')
    parser.add_argument('--all', action='store_true', help='All controllers')
    parser.add_argument('--output', help='Output file')
    parser.add_argument('--json-output', help='JSON output')
    args = parser.parse_args()

    project_root = os.path.abspath(args.project_root)

    controllers = discover_controllers(project_root, args.module)

    if not controllers:
        print('No controllers found.')
        sys.exit(1)

    print(f'Scanning {len(controllers)} controllers...', file=sys.stderr)

    all_endpoints = []
    for ctrl in controllers:
        endpoints = extract_endpoints(ctrl)
        all_endpoints.extend(endpoints)

    print(f'Done: {len(all_endpoints)} endpoints found', file=sys.stderr)

    report = generate_report(all_endpoints, args.module)

    if sys.platform == 'win32':
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    print(report)

    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            f.write(report)

    if args.json_output:
        with open(args.json_output, 'w', encoding='utf-8') as f:
            json.dump(all_endpoints, f, indent=2)


if __name__ == '__main__':
    main()
