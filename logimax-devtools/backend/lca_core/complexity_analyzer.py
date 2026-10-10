#!/usr/bin/env python3
"""
Complexity Analyzer (L1)
=========================
Calculates cyclomatic complexity and nesting depth for PHP functions.
Identifies the most complex, hardest-to-maintain functions in the codebase.

Metrics per function:
  - Cyclomatic complexity (branches: if/elseif/else/switch/case/for/foreach/while/catch/&&/||/?)
  - Max nesting depth
  - Lines of code
  - Maintainability rating (A-F)

Usage:
    python complexity_analyzer.py --project-root /path/to/project
    python complexity_analyzer.py --project-root /path/to/project --module billing --output report.md
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
from impact_analyzer import detect_module


# ── Complexity Calculation ────────────────────────────────────────────

BRANCH_KEYWORDS = [
    r'\bif\s*\(',
    r'\belseif\s*\(',
    r'\belse\s*\{',
    r'\bcase\s+',
    r'\bfor\s*\(',
    r'\bforeach\s*\(',
    r'\bwhile\s*\(',
    r'\bcatch\s*\(',
    r'\?\s*[^?]',      # ternary operator
]

LOGICAL_OPS = [r'&&', r'\|\|']


def calculate_complexity(body):
    """Calculate cyclomatic complexity of a function body."""
    complexity = 1  # Base complexity

    for pattern in BRANCH_KEYWORDS:
        complexity += len(re.findall(pattern, body))

    for pattern in LOGICAL_OPS:
        complexity += len(re.findall(pattern, body))

    return complexity


def calculate_nesting_depth(body):
    """Calculate maximum nesting depth."""
    max_depth = 0
    current_depth = 0

    for char in body:
        if char == '{':
            current_depth += 1
            max_depth = max(max_depth, current_depth)
        elif char == '}':
            current_depth = max(0, current_depth - 1)

    return max_depth


def count_lines(body):
    """Count non-empty, non-comment lines."""
    lines = body.split('\n')
    count = 0
    in_block_comment = False
    for line in lines:
        stripped = line.strip()
        if not stripped:
            continue
        if stripped.startswith('/*'):
            in_block_comment = True
            continue
        if '*/' in stripped:
            in_block_comment = False
            continue
        if in_block_comment:
            continue
        if stripped.startswith('//'):
            continue
        count += 1
    return count


def get_rating(complexity, nesting, loc):
    """
    Rate function maintainability A-F.
    A: Simple, easy to maintain
    F: Unmaintainable nightmare
    """
    score = 0

    # Complexity scoring
    if complexity <= 5:
        score += 0
    elif complexity <= 10:
        score += 1
    elif complexity <= 20:
        score += 2
    elif complexity <= 40:
        score += 3
    else:
        score += 4

    # Nesting scoring
    if nesting <= 3:
        score += 0
    elif nesting <= 5:
        score += 1
    elif nesting <= 7:
        score += 2
    else:
        score += 3

    # LOC scoring
    if loc <= 30:
        score += 0
    elif loc <= 60:
        score += 1
    elif loc <= 100:
        score += 2
    elif loc <= 200:
        score += 3
    else:
        score += 4

    ratings = {0: 'A', 1: 'A', 2: 'B', 3: 'B', 4: 'C', 5: 'C',
               6: 'D', 7: 'D', 8: 'E', 9: 'F', 10: 'F', 11: 'F'}
    return ratings.get(min(score, 11), 'F')


# ── Scanner ───────────────────────────────────────────────────────────

def scan_file(filepath, project_root):
    """Analyze all functions in a PHP file."""
    results = []
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
    except Exception:
        return results

    rel_path = os.path.relpath(filepath, project_root)
    module = detect_module(rel_path)

    pattern = r'(?:public|private|protected)?\s*(?:static\s+)?function\s+(\w+)\s*\([^)]*\)\s*\{'
    for match in re.finditer(pattern, content):
        func_name = match.group(1)
        line = content[:match.start()].count('\n') + 1

        # Extract body
        start = match.end()
        depth = 1
        pos = start
        while pos < len(content) and depth > 0:
            if content[pos] == '{':
                depth += 1
            elif content[pos] == '}':
                depth -= 1
            pos += 1

        if depth != 0:
            continue

        body = content[start:pos-1]
        complexity = calculate_complexity(body)
        nesting = calculate_nesting_depth(body)
        loc = count_lines(body)
        rating = get_rating(complexity, nesting, loc)

        results.append({
            'name': func_name,
            'file': rel_path,
            'module': module,
            'line': line,
            'complexity': complexity,
            'nesting': nesting,
            'loc': loc,
            'rating': rating,
        })

    return results


def scan_project(project_root, module_filter=None):
    """Scan entire project for complexity."""
    scan_dirs = [
        os.path.join(project_root, 'admin', 'application', 'controllers'),
        os.path.join(project_root, 'admin', 'application', 'models'),
    ]

    all_results = []
    files_scanned = 0

    for scan_dir in scan_dirs:
        if not os.path.isdir(scan_dir):
            continue
        for root, _, files in os.walk(scan_dir):
            for fname in sorted(files):
                if not fname.endswith('.php'):
                    continue
                fpath = os.path.join(root, fname)
                rel = os.path.relpath(fpath, project_root)
                if module_filter and detect_module(rel) != module_filter:
                    continue
                files_scanned += 1
                all_results.extend(scan_file(fpath, project_root))

    return all_results, files_scanned


# ── Report ────────────────────────────────────────────────────────────

def generate_report(results, files_scanned, module_filter=None):
    """Generate complexity report."""
    lines = [
        '# Complexity Analysis Report',
        '',
        f'> Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}',
        '',
    ]

    # Stats
    total = len(results)
    ratings = defaultdict(int)
    for r in results:
        ratings[r['rating']] += 1

    avg_complexity = sum(r['complexity'] for r in results) / max(total, 1)
    avg_loc = sum(r['loc'] for r in results) / max(total, 1)

    lines.extend([
        '| Metric | Value |',
        '|---|---|',
        f'| **Files scanned** | {files_scanned} |',
        f'| **Functions analyzed** | {total} |',
        f'| **Avg complexity** | {avg_complexity:.1f} |',
        f'| **Avg LOC** | {avg_loc:.0f} |',
        '',
        '### Rating Distribution',
        '',
        '| Rating | Count | % | Meaning |',
        '|---|---|---|---|',
        f'| 🟢 A | {ratings["A"]} | {ratings["A"]*100//max(total,1)}% | Simple, easy to maintain |',
        f'| 🟢 B | {ratings["B"]} | {ratings["B"]*100//max(total,1)}% | Low complexity |',
        f'| 🟡 C | {ratings["C"]} | {ratings["C"]*100//max(total,1)}% | Moderate — consider refactoring |',
        f'| 🟠 D | {ratings["D"]} | {ratings["D"]*100//max(total,1)}% | High — should refactor |',
        f'| 🔴 E | {ratings["E"]} | {ratings["E"]*100//max(total,1)}% | Very high — bug magnet |',
        f'| 💀 F | {ratings["F"]} | {ratings["F"]*100//max(total,1)}% | Unmaintainable |',
        '',
    ])

    # Top 30 worst functions
    worst = sorted(results, key=lambda x: (-x['complexity'], -x['nesting'], -x['loc']))[:30]
    if worst:
        lines.extend([
            '## Top 30 Most Complex Functions',
            '',
            '| # | Function | File | Module | CC | Nest | LOC | Rating |',
            '|---|---|---|---|---|---|---|---|',
        ])
        for i, r in enumerate(worst, 1):
            rating_icon = {'A': '🟢', 'B': '🟢', 'C': '🟡', 'D': '🟠', 'E': '🔴', 'F': '💀'}[r['rating']]
            lines.append(
                f'| {i} | `{r["name"]}()` | `{os.path.basename(r["file"])}` | '
                f'{r["module"]} | **{r["complexity"]}** | {r["nesting"]} | {r["loc"]} | {rating_icon} {r["rating"]} |'
            )
        lines.append('')

    # Per-module summary
    mod_stats = defaultdict(lambda: {'count': 0, 'total_cc': 0, 'worst_cc': 0, 'f_count': 0})
    for r in results:
        s = mod_stats[r['module']]
        s['count'] += 1
        s['total_cc'] += r['complexity']
        s['worst_cc'] = max(s['worst_cc'], r['complexity'])
        if r['rating'] in ('E', 'F'):
            s['f_count'] += 1

    lines.extend([
        '## Module Summary',
        '',
        '| Module | Functions | Avg CC | Worst CC | E/F Rated |',
        '|---|---|---|---|---|',
    ])
    for mod, s in sorted(mod_stats.items(), key=lambda x: -x[1]['worst_cc']):
        avg = s['total_cc'] / max(s['count'], 1)
        lines.append(f'| `{mod}` | {s["count"]} | {avg:.1f} | {s["worst_cc"]} | {s["f_count"]} |')
    lines.append('')

    lines.append('---')
    lines.append('*Generated by Logimax Complexity Analyzer (LCA)*')
    return '\n'.join(lines)


# ── Main ──────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Complexity Analyzer')
    parser.add_argument('--project-root', required=True)
    parser.add_argument('--module', help='Filter to specific module')
    parser.add_argument('--output', help='Output markdown file')

    args = parser.parse_args()
    project_root = os.path.abspath(args.project_root)

    print(f'Analyzing complexity...', file=sys.stderr)
    t_start = time.time()
    results, files_scanned = scan_project(project_root, args.module)
    elapsed = time.time() - t_start

    worst = max((r['complexity'] for r in results), default=0)
    print(f'Done in {elapsed:.1f}s — {len(results)} functions, worst CC={worst}', file=sys.stderr)

    report = generate_report(results, files_scanned, args.module)

    if args.output:
        Path(args.output).write_text(report, encoding='utf-8')
        print(f'Report: {args.output}', file=sys.stderr)
    else:
        if sys.platform == 'win32':
            sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        print(report)

    sys.exit(0)

if __name__ == '__main__':
    main()
