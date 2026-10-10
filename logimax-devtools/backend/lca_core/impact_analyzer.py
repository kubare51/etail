#!/usr/bin/env python3
"""
Change Impact Analyzer
======================
Analyzes changed files in a PR/diff and predicts what could break.

For each modified function/method in a PR:
  1. Finds all callers across the codebase (PHP controllers, models, helpers)
  2. Finds all views that reference changed functions
  3. Finds all JS files that call AJAX endpoints tied to changed controllers
  4. Flags cross-module impacts (billing change → estimation affected?)

Output: Markdown impact report for PR comment or CLI review.

Usage:
    python impact_analyzer.py --project-root /path/to/project --diff-base origin/main
    python impact_analyzer.py --project-root /path/to/project --changed-files file1.php file2.php
    python impact_analyzer.py --project-root /path/to/project --diff-base origin/main --output report.md --json-output results.json
"""

import argparse
import json
import os
import re
import subprocess
import sys
import time
from collections import defaultdict
from pathlib import Path


# ── Module Detection ──────────────────────────────────────────────────

MODULE_KEYWORDS = {
    'estimation':     ['estimation', 'est_'],
    'billing':        ['billing', 'bill_'],
    'payment':        ['payment', 'pay_'],
    'tagging':        ['tagging', 'taging', 'tag_'],
    'purchase':       ['purchase', 'po_'],
    'reports':        ['reports', 'report_'],
    'catalog':        ['catalog', 'cat_'],
    'stock':          ['stock', 'stock_issue'],
    'dashboard':      ['dashboard', 'dash_'],
    'settings':       ['settings', 'setting_'],
    'customer':       ['customer', 'cus_'],
    'employee':       ['employee', 'emp_'],
    'order':          ['order', 'ord_'],
    'services':       ['services', 'serv_'],
    'gst':            ['gst', 'irn'],
    'branch_transfer': ['brntransfer', 'branch_transfer', 'bt_'],
    'lot':            ['lot_'],
    'scheme':         ['scheme', 'schm_'],
}

CRITICAL_MODULES = {'billing', 'payment', 'gst', 'estimation'}

# Financial models -- changes here can cause monetary errors
FINANCIAL_MODELS = {
    'ret_billing_model', 'payment_model', 'ret_estimation_model',
    'cash_flow_model', 'pos_model', 'ret_purchase_order_model',
    'scheme_model', 'gift_voucher_model', 'wallet_model',
    'ret_emp_incentive_model', 'chit_transaction_model',
}

# Shared models -- used by many controllers, high blast radius
SHARED_MODELS = {
    'curd_model', 'admin_settings_model', 'log_model',
    'email_model', 'sms_model', 'customer_model', 'employee_model',
    'ret_tag_model', 'ret_catalog_model', 'ret_reports_model',
}


def detect_module(filepath):
    """Detect which module a file belongs to."""
    fp_lower = filepath.lower()
    for module, keywords in MODULE_KEYWORDS.items():
        for kw in keywords:
            if kw in fp_lower:
                return module
    return 'unknown'


# ── Function Extraction ───────────────────────────────────────────────

def extract_php_functions(filepath):
    """Extract all function/method names from a PHP file."""
    functions = []
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
        
        # Match: public/private/protected function name(...) or plain function name(...)
        pattern = r'(?:public|private|protected|static)?\s*function\s+(\w+)\s*\('
        for match in re.finditer(pattern, content):
            func_name = match.group(1)
            # Skip constructor and magic methods
            if func_name.startswith('__') and func_name != '__construct':
                continue
            functions.append(func_name)
    except Exception:
        pass
    return functions


def extract_modified_functions(filepath, diff_lines=None):
    """
    Extract functions that were actually modified (not just present in file).
    If diff_lines provided, only return functions whose body intersects the diff.
    Otherwise, return all functions in the file.
    """
    if diff_lines is None:
        return extract_php_functions(filepath)
    
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            lines = f.readlines()
    except Exception:
        return extract_php_functions(filepath)
    
    # Find function boundaries
    func_ranges = []
    pattern = r'(?:public|private|protected|static)?\s*function\s+(\w+)\s*\('
    
    for i, line in enumerate(lines, 1):
        match = re.search(pattern, line)
        if match:
            func_ranges.append({'name': match.group(1), 'start': i, 'end': None})
    
    # Set end lines (next function start or EOF)
    for i, fr in enumerate(func_ranges):
        if i + 1 < len(func_ranges):
            fr['end'] = func_ranges[i + 1]['start'] - 1
        else:
            fr['end'] = len(lines)
    
    # Find which functions intersect with diff lines
    modified = []
    for fr in func_ranges:
        for dl in diff_lines:
            if fr['start'] <= dl <= fr['end']:
                if fr['name'] not in modified:
                    modified.append(fr['name'])
                break
    
    return modified if modified else extract_php_functions(filepath)


# ── Caller Search ─────────────────────────────────────────────────────

# Generic method names that exist in many models — require model-qualified search
GENERIC_METHOD_NAMES = {
    'insertData', 'updateData', 'deleteData', 'getData', 'getList',
    'getAll', 'getById', 'getDetails', 'save', 'delete', 'update',
    'insert', 'validate', 'index', 'create', 'edit', 'view',
}


def _derive_model_name(source_file):
    """
    Derive the CI3 model name from a model file path.
    e.g. 'admin/application/models/ret_billing_model.php' → 'ret_billing_model'
    """
    basename = os.path.splitext(os.path.basename(source_file))[0]
    return basename


def find_callers(project_root, function_name, source_file, file_type='other', php_dirs=None):
    """
    Find all files that call a given function.
    
    For model methods, uses model-qualified search:
      $this->model_name->function_name(
    This prevents false positives when generic names like insertData()
    exist in 12+ models.
    
    For controllers/helpers, uses unqualified search:
      ->function_name(
    """
    if php_dirs is None:
        php_dirs = [
            os.path.join(project_root, 'admin', 'application', 'controllers'),
            os.path.join(project_root, 'admin', 'application', 'models'),
            os.path.join(project_root, 'admin', 'application', 'helpers'),
            os.path.join(project_root, 'admin', 'application', 'views'),
        ]
    
    callers = []
    source_basename = os.path.basename(source_file)
    is_model = file_type == 'model'
    is_generic = function_name in GENERIC_METHOD_NAMES
    
    # Build search patterns based on context
    if is_model and is_generic:
        # For generic model methods: ONLY match model-qualified calls
        # e.g., $this->ret_billing_model->insertData(
        model_name = _derive_model_name(source_file)
        patterns = [
            rf'{re.escape(model_name)}\s*->\s*{re.escape(function_name)}\s*\(',
        ]
        # Also try without _model suffix (some code loads models with alias)
        short_name = model_name.replace('_model', '')
        if short_name != model_name:
            patterns.append(
                rf'{re.escape(short_name)}\s*->\s*{re.escape(function_name)}\s*\('
            )
    elif is_model:
        # For non-generic model methods: prefer model-qualified but also
        # catch unqualified calls (the method name is specific enough)
        model_name = _derive_model_name(source_file)
        patterns = [
            rf'->\s*{re.escape(function_name)}\s*\(',
        ]
    else:
        # Controllers/helpers: unqualified search
        patterns = [
            rf'->\s*{re.escape(function_name)}\s*\(',
        ]
    
    combined_pattern = '|'.join(patterns)
    
    for search_dir in php_dirs:
        if not os.path.isdir(search_dir):
            continue
        
        for root, _, files in os.walk(search_dir):
            for fname in files:
                if not fname.endswith('.php'):
                    continue
                
                fpath = os.path.join(root, fname)
                
                # Skip the source file itself
                if os.path.basename(fpath) == source_basename:
                    continue
                
                try:
                    with open(fpath, 'r', encoding='utf-8', errors='ignore') as f:
                        content = f.read()
                    
                    matches = list(re.finditer(combined_pattern, content))
                    if matches:
                        # Get line numbers
                        lines = content[:matches[0].start()].count('\n') + 1
                        rel_path = os.path.relpath(fpath, project_root)
                        callers.append({
                            'file': rel_path,
                            'module': detect_module(rel_path),
                            'call_count': len(matches),
                            'first_line': lines,
                        })
                except Exception:
                    continue
    
    return callers


def find_js_callers(project_root, controller_name, method_name):
    """
    Find JS files that make AJAX calls to a changed controller method.
    Searches for URL patterns like: base_url + "controller/method"
    """
    js_dir = os.path.join(project_root, 'admin', 'assets', 'js')
    if not os.path.isdir(js_dir):
        return []
    
    callers = []
    # Clean controller name (remove admin_ prefix for URL matching)
    url_controller = controller_name.replace('admin_', '')
    
    patterns = [
        rf'{re.escape(url_controller)}/{re.escape(method_name)}',
        rf'{re.escape(controller_name)}/{re.escape(method_name)}',
    ]
    
    for root, _, files in os.walk(js_dir):
        for fname in files:
            if not fname.endswith('.js'):
                continue
            
            fpath = os.path.join(root, fname)
            try:
                with open(fpath, 'r', encoding='utf-8', errors='ignore') as f:
                    content = f.read()
                
                for pattern in patterns:
                    if re.search(pattern, content, re.IGNORECASE):
                        rel_path = os.path.relpath(fpath, project_root)
                        callers.append({
                            'file': rel_path,
                            'module': detect_module(rel_path),
                            'type': 'ajax_call',
                        })
                        break
            except Exception:
                continue
    
    return callers


# ── Diff Parsing ──────────────────────────────────────────────────────

def get_changed_files_from_diff(project_root, diff_base):
    """Get list of changed files from git diff."""
    try:
        result = subprocess.run(
            ['git', 'diff', '--name-only', '--diff-filter=ACMR', diff_base + '...HEAD'],
            capture_output=True, text=True, cwd=project_root
        )
        if result.returncode != 0:
            # Fallback: try without ...HEAD
            result = subprocess.run(
                ['git', 'diff', '--name-only', '--diff-filter=ACMR', diff_base],
                capture_output=True, text=True, cwd=project_root
            )
        
        files = [f.strip() for f in result.stdout.strip().split('\n') if f.strip()]
        return [f for f in files if f.endswith('.php')]
    except Exception as e:
        print(f"Error getting diff: {e}", file=sys.stderr)
        return []


def get_diff_lines(project_root, filepath, diff_base):
    """Get the line numbers that changed in a file."""
    try:
        result = subprocess.run(
            ['git', 'diff', '-U0', diff_base, '--', filepath],
            capture_output=True, text=True, cwd=project_root
        )
        
        changed_lines = []
        for line in result.stdout.split('\n'):
            # Parse unified diff headers: @@ -a,b +c,d @@
            match = re.match(r'^@@ .* \+(\d+)(?:,(\d+))? @@', line)
            if match:
                start = int(match.group(1))
                count = int(match.group(2) or 1)
                changed_lines.extend(range(start, start + count))
        
        return changed_lines
    except Exception:
        return None


# ── Analysis Engine ───────────────────────────────────────────────────

def analyze_impact(project_root, changed_files, diff_base=None):
    """
    Main analysis: for each changed file, find modified functions,
    then find all callers across the codebase.
    """
    results = {
        'files_analyzed': 0,
        'functions_changed': 0,
        'total_callers': 0,
        'cross_module_impacts': 0,
        'critical_impacts': 0,
        'impacts': [],
        'cross_module_summary': defaultdict(set),
        'risk_score': 0,
    }
    
    for filepath in changed_files:
        full_path = os.path.join(project_root, filepath)
        if not os.path.exists(full_path):
            continue
        
        # Only analyze PHP files in controllers/models/helpers
        if not any(d in filepath for d in ['controllers/', 'models/', 'helpers/']):
            continue
        
        results['files_analyzed'] += 1
        source_module = detect_module(filepath)
        
        # Determine file type
        if 'controllers/' in filepath:
            file_type = 'controller'
        elif 'models/' in filepath:
            file_type = 'model'
        elif 'helpers/' in filepath:
            file_type = 'helper'
        else:
            file_type = 'other'
        
        # Get modified functions
        diff_lines = None
        if diff_base:
            diff_lines = get_diff_lines(project_root, filepath, diff_base)
        
        modified_funcs = extract_modified_functions(full_path, diff_lines)
        
        # Deduplicate: track (source_file, function) pairs already processed
        seen_funcs = set()
        
        for func_name in modified_funcs:
            # Skip duplicate function entries in same file
            func_key = (filepath, func_name)
            if func_key in seen_funcs:
                continue
            seen_funcs.add(func_key)
            
            results['functions_changed'] += 1
            
            # Find PHP callers (model-aware for generic names)
            callers = find_callers(project_root, func_name, full_path, file_type)
            
            # Find JS callers (if it's a controller method)
            js_callers = []
            if file_type == 'controller':
                controller_name = os.path.splitext(os.path.basename(filepath))[0]
                js_callers = find_js_callers(project_root, controller_name, func_name)
            
            all_callers = callers + js_callers
            results['total_callers'] += len(all_callers)
            
            # Detect cross-module impacts
            cross_module = []
            for caller in all_callers:
                caller_module = caller['module']
                if caller_module != source_module and caller_module != 'unknown':
                    cross_module.append(caller)
                    results['cross_module_summary'][f"{source_module} → {caller_module}"].add(func_name)
            
            results['cross_module_impacts'] += len(cross_module)
            
            # Check if any caller is in a critical module
            critical = [c for c in all_callers if c['module'] in CRITICAL_MODULES]
            results['critical_impacts'] += len(critical)
            
            if all_callers:  # Only report functions that have callers
                results['impacts'].append({
                    'source_file': filepath,
                    'source_module': source_module,
                    'file_type': file_type,
                    'function': func_name,
                    'callers': all_callers,
                    'cross_module': cross_module,
                    'critical': critical,
                    'caller_count': len(all_callers),
                })
    
    # -- Calculate enhanced risk score (0-100) --
    risk = 0
    risk_factors = []

    # Factor 1: Caller blast radius (up to 20 points)
    caller_score = min(results['total_callers'] * 2, 20)
    if caller_score > 0:
        risk_factors.append(f"{results['total_callers']} downstream callers (+{caller_score})")
    risk += caller_score

    # Factor 2: Cross-module impacts (up to 20 points)
    cross_score = min(results['cross_module_impacts'] * 4, 20)
    if cross_score > 0:
        risk_factors.append(f"{results['cross_module_impacts']} cross-module impacts (+{cross_score})")
    risk += cross_score

    # Factor 3: Critical module involvement (up to 15 points)
    critical_score = min(results['critical_impacts'] * 5, 15)
    if critical_score > 0:
        risk_factors.append(f"{results['critical_impacts']} critical module impacts (+{critical_score})")
    risk += critical_score

    # Factor 4: Financial model changes (+15 flat)
    financial_files = []
    for filepath in changed_files:
        basename = os.path.splitext(os.path.basename(filepath))[0]
        if basename in FINANCIAL_MODELS:
            financial_files.append(basename)
    if financial_files:
        risk += 15
        risk_factors.append(f"financial model changed: {', '.join(financial_files)} (+15)")

    # Factor 5: Shared model changes (+12 flat)
    shared_files = []
    for filepath in changed_files:
        basename = os.path.splitext(os.path.basename(filepath))[0]
        if basename in SHARED_MODELS:
            shared_files.append(basename)
    if shared_files:
        risk += 12
        risk_factors.append(f"shared model changed: {', '.join(shared_files)} (+12)")

    # Factor 6: Helper file changes (+10 flat -- affects entire app)
    helper_files = [f for f in changed_files if 'helpers/' in f]
    if helper_files:
        risk += 10
        risk_factors.append(f"{len(helper_files)} helper file(s) changed (+10)")

    # Factor 7: Config file changes (+8 flat)
    config_files = [f for f in changed_files if 'config/' in f]
    if config_files:
        risk += 8
        risk_factors.append(f"{len(config_files)} config file(s) changed (+8)")

    # Factor 8: Function count (up to 10 points)
    func_score = min(results['functions_changed'] * 2, 10)
    if results['functions_changed'] > 3:
        risk_factors.append(f"{results['functions_changed']} functions changed (+{func_score})")
    risk += func_score

    # Factor 9: Test coverage bonus (-5 if tests exist for changed files)
    test_dirs = [
        os.path.join(project_root, 'tests'),
        os.path.join(project_root, 'admin', 'tests'),
    ]
    has_tests = False
    for td in test_dirs:
        if os.path.isdir(td):
            for filepath in changed_files:
                basename = os.path.splitext(os.path.basename(filepath))[0]
                for root_dir, _, filenames in os.walk(td):
                    for fn in filenames:
                        if basename.lower() in fn.lower():
                            has_tests = True
                            break
                    if has_tests:
                        break
                if has_tests:
                    break
    if has_tests:
        risk -= 5
        risk_factors.append('related tests found (-5)')

    results['risk_score'] = max(0, min(risk, 100))
    results['risk_factors'] = risk_factors

    # Review gate recommendation
    if results['risk_score'] >= 80:
        results['review_gate'] = 'TL_APPROVAL'
    elif results['risk_score'] >= 50:
        results['review_gate'] = 'SENIOR_REVIEW'
    elif results['risk_score'] >= 25:
        results['review_gate'] = 'PEER_REVIEW'
    else:
        results['review_gate'] = 'SELF_MERGE'
    
    # Convert sets to lists for JSON serialization
    results['cross_module_summary'] = {
        k: list(v) for k, v in results['cross_module_summary'].items()
    }
    
    return results


# ── Report Generation ─────────────────────────────────────────────────

def generate_report(results):
    """Generate markdown impact report."""
    lines = []
    
    # Header
    risk = results['risk_score']
    gate = results.get('review_gate', 'PEER_REVIEW')
    gate_labels = {
        'TL_APPROVAL': '\u26d4 Requires **TL approval** before merge',
        'SENIOR_REVIEW': '\U0001f7e1 Requires **senior developer review**',
        'PEER_REVIEW': '\U0001f7e2 Peer review recommended',
        'SELF_MERGE': '\u2705 Safe to self-merge',
    }
    if risk >= 80:
        risk_emoji = '\U0001f6a8'
        risk_label = 'CRITICAL'
    elif risk >= 50:
        risk_emoji = '\u26a0\ufe0f'
        risk_label = 'HIGH'
    elif risk >= 25:
        risk_emoji = '\U0001f7e1'
        risk_label = 'MEDIUM'
    else:
        risk_emoji = '\u2705'
        risk_label = 'LOW'
    
    lines.append(f'### \U0001f4a5 Change Impact Analysis')
    lines.append('')
    lines.append(f'| Metric | Value |')
    lines.append(f'|---|---|')
    lines.append(f'| **Impact Risk** | {risk_emoji} {risk_label} (score: {risk}/100) |')
    lines.append(f'| **Review Gate** | {gate_labels.get(gate, gate)} |')
    lines.append(f'| **Functions Changed** | {results["functions_changed"]} |')
    lines.append(f'| **Total Callers Found** | {results["total_callers"]} |')
    lines.append(f'| **Cross-Module Impacts** | {results["cross_module_impacts"]} |')
    lines.append(f'| **Critical Module Impacts** | {results["critical_impacts"]} |')
    lines.append('')

    # Risk factors breakdown
    if results.get('risk_factors'):
        lines.append('**Risk factors:**')
        for factor in results['risk_factors']:
            lines.append(f'- {factor}')
        lines.append('')
    
    # Cross-module summary (most important section)
    if results['cross_module_summary']:
        lines.append('#### 🔗 Cross-Module Dependencies')
        lines.append('')
        lines.append('| Change In | Affects | Functions |')
        lines.append('|---|---|---|')
        for edge, funcs in sorted(results['cross_module_summary'].items()):
            func_list = ', '.join(f'`{f}`' for f in sorted(funcs)[:5])
            if len(funcs) > 5:
                func_list += f' +{len(funcs)-5} more'
            lines.append(f'| {edge} | | {func_list} |')
        lines.append('')
    
    # Detailed impacts (sorted by caller count, highest first)
    if results['impacts']:
        # Only show functions with callers
        sorted_impacts = sorted(results['impacts'], key=lambda x: x['caller_count'], reverse=True)
        
        lines.append('<details>')
        lines.append(f'<summary>📋 Detailed Impact: {len(sorted_impacts)} functions with callers</summary>')
        lines.append('')
        
        for impact in sorted_impacts[:20]:  # Cap at 20
            cross_badge = ' 🔗' if impact['cross_module'] else ''
            critical_badge = ' 🚨' if impact['critical'] else ''
            
            lines.append(f'**`{impact["function"]}()`** in `{os.path.basename(impact["source_file"])}` '
                        f'({impact["source_module"]}/{impact["file_type"]}){cross_badge}{critical_badge}')
            lines.append('')
            lines.append(f'| Caller | Module | Calls |')
            lines.append(f'|---|---|---|')
            
            for caller in impact['callers'][:10]:
                module_badge = ''
                if caller['module'] in CRITICAL_MODULES:
                    module_badge = ' 🚨'
                if caller['module'] != impact['source_module'] and caller['module'] != 'unknown':
                    module_badge += ' 🔗'
                
                calls = caller.get('call_count', 1)
                lines.append(f'| `{os.path.basename(caller["file"])}` | {caller["module"]}{module_badge} | {calls} |')
            
            if len(impact['callers']) > 10:
                lines.append(f'| ... +{len(impact["callers"])-10} more | | |')
            lines.append('')
        
        lines.append('</details>')
        lines.append('')
    
    # No impacts
    if not results['impacts'] and results['files_analyzed'] > 0:
        lines.append('✅ No downstream callers found for changed functions.')
        lines.append('')
    
    if results['files_analyzed'] == 0:
        lines.append('ℹ️ No PHP controllers/models/helpers were changed in this PR.')
        lines.append('')
    
    # Legend
    lines.append('---')
    lines.append('🔗 = cross-module dependency | 🚨 = critical module (billing/payment/gst/estimation)')
    
    return '\n'.join(lines)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Change Impact Analyzer')
    parser.add_argument('--project-root', required=True, help='Project root directory')
    parser.add_argument('--diff-base', help='Git diff base (e.g., origin/main)')
    parser.add_argument('--changed-files', nargs='+', help='Explicit list of changed files')
    parser.add_argument('--output', help='Output markdown report file')
    parser.add_argument('--json-output', help='Output JSON results file')
    
    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)
    
    # Get changed files
    if args.changed_files:
        changed_files = [f for f in args.changed_files if f.endswith('.php')]
    elif args.diff_base:
        changed_files = get_changed_files_from_diff(project_root, args.diff_base)
    else:
        print("Error: provide --diff-base or --changed-files", file=sys.stderr)
        sys.exit(1)
    
    if not changed_files:
        print("No PHP files changed.")
        if args.output:
            with open(args.output, 'w') as f:
                f.write('### 💥 Change Impact Analysis\n\nNo PHP files changed.\n')
        sys.exit(0)
    
    print(f"Analyzing impact of {len(changed_files)} changed PHP files...")
    
    # Run analysis
    start = time.time()
    results = analyze_impact(project_root, changed_files, args.diff_base)
    elapsed = time.time() - start
    
    print(f"Done in {elapsed:.1f}s — {results['functions_changed']} functions, "
          f"{results['total_callers']} callers, {results['cross_module_impacts']} cross-module")
    
    # Generate report
    report = generate_report(results)
    if sys.platform == 'win32':
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    print('\n' + report)
    
    # Write outputs
    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            f.write(report)
    
    if args.json_output:
        with open(args.json_output, 'w', encoding='utf-8') as f:
            json.dump(results, f, indent=2, default=str)
    
    # Exit code: non-zero if risk requires TL approval
    if results.get('review_gate') == 'TL_APPROVAL':
        sys.exit(2)  # Critical -- TL must review
    
    sys.exit(0)


if __name__ == '__main__':
    main()
