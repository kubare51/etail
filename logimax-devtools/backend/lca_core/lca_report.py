#!/usr/bin/env python3
"""
LCA HTML Report Generator — Beautiful self-contained health reports
====================================================================
Runs all LCA tools and generates a self-contained HTML report with
charts, collapsible sections, and dark theme. Like Playwright reports.

Usage:
    python lca_report.py --project-root . --module billing
    python lca_report.py --project-root . --module billing --output billing.html
    python lca_report.py --project-root . --all --output-dir ./reports
"""

import argparse
import json
import os
import sys
import time
import importlib.util
from datetime import datetime
from pathlib import Path


def load_module_from_file(name, filepath):
    """Dynamically import a Python module from file path."""
    spec = importlib.util.spec_from_file_location(name, filepath)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def get_lca_dir():
    return os.path.dirname(os.path.abspath(__file__))


def run_complexity(project_root, module):
    """Run complexity analyzer and return data."""
    try:
        lca = get_lca_dir()
        mod = load_module_from_file('complexity_analyzer', os.path.join(lca, 'complexity_analyzer.py'))
        results, _ = mod.scan_project(project_root, module)
        ratings = {}
        for r in results:
            grade = r.get('rating', 'A')
            ratings[grade] = ratings.get(grade, 0) + 1
        worst = max((r.get('complexity', 0) for r in results), default=0)
        ef_count = ratings.get('E', 0) + ratings.get('F', 0)
        # Normalize field names for HTML template
        top10 = sorted(results, key=lambda x: x.get('complexity', 0), reverse=True)[:10]
        for item in top10:
            item['function'] = item.get('name', item.get('function', '?'))
        return {
            'total': len(results),
            'ratings': ratings,
            'worst_cc': worst,
            'ef_count': ef_count,
            'top10': top10,
        }
    except Exception as e:
        return {'error': str(e), 'total': 0, 'ratings': {}, 'worst_cc': 0, 'ef_count': 0, 'top10': []}


def run_security(project_root, module):
    """Run security scanner and return data."""
    try:
        lca = get_lca_dir()
        mod = load_module_from_file('security_scanner', os.path.join(lca, 'security_scanner.py'))
        results, _ = mod.scan_project(project_root, module)
        by_severity = {}
        by_rule = {}
        for r in results:
            sev = r.get('severity', 'MEDIUM')
            by_severity[sev] = by_severity.get(sev, 0) + 1
            rule = r.get('rule', 'unknown')
            by_rule[rule] = by_rule.get(rule, 0) + 1
        return {
            'total': len(results),
            'by_severity': by_severity,
            'by_rule': by_rule,
            'critical': by_severity.get('CRITICAL', 0),
            'high': by_severity.get('HIGH', 0),
            'top10': results[:10],
        }
    except Exception as e:
        return {'error': str(e), 'total': 0, 'by_severity': {}, 'by_rule': {}, 'critical': 0, 'high': 0, 'top10': []}


def run_n1(project_root, module):
    """Run N+1 detector and return data."""
    try:
        lca = get_lca_dir()
        mod = load_module_from_file('n1_detector', os.path.join(lca, 'n1_detector.py'))
        results, _ = mod.scan_project(project_root, module)
        by_severity = {}
        for r in results:
            sev = r.get('severity', 'MEDIUM')
            by_severity[sev] = by_severity.get(sev, 0) + 1
        return {
            'total': len(results),
            'by_severity': by_severity,
            'critical': by_severity.get('CRITICAL', 0),
            'high': by_severity.get('HIGH', 0),
            'top10': results[:10],
        }
    except Exception as e:
        return {'error': str(e), 'total': 0, 'by_severity': {}, 'critical': 0, 'high': 0, 'top10': []}


def run_hardcoded(project_root, module):
    """Run hardcoded scanner and return data."""
    try:
        lca = get_lca_dir()
        mod = load_module_from_file('hardcoded_scanner', os.path.join(lca, 'hardcoded_scanner.py'))
        results, _ = mod.scan_project(project_root, module)
        by_type = {}
        for r in results:
            t = r.get('rule', 'unknown')
            by_type[t] = by_type.get(t, 0) + 1
        return {
            'total': len(results),
            'by_type': by_type,
            'top10': results[:10],
        }
    except Exception as e:
        return {'error': str(e), 'total': 0, 'by_type': {}, 'top10': []}


def calculate_score(complexity, security, n1, hardcoded):
    """Calculate 0-100 health score."""
    score = 100

    # Complexity penalties
    ef = complexity.get('ef_count', 0)
    if ef > 0:
        score -= min(ef * 3, 20)

    # Security penalties
    critical = security.get('critical', 0)
    high = security.get('high', 0)
    score -= min(critical * 10, 30)
    score -= min(high // 20, 10)

    # N+1 penalties
    n1_high = n1.get('high', 0)
    n1_crit = n1.get('critical', 0)
    score -= min(n1_crit * 8, 20)
    score -= min(n1_high // 3, 10)

    # Hardcoded penalties
    hc_total = hardcoded.get('total', 0)
    score -= min(hc_total // 5, 10)

    return max(0, min(100, score))


def get_grade(score):
    if score >= 90: return 'A', '#22c55e', 'Excellent'
    if score >= 75: return 'B', '#84cc16', 'Good'
    if score >= 60: return 'C', '#eab308', 'Fair'
    if score >= 40: return 'D', '#f97316', 'Poor'
    if score >= 20: return 'E', '#ef4444', 'Critical'
    return 'F', '#dc2626', 'Failing'


def load_trend_history(project_root, module):
    """Load trend history if available."""
    history_path = os.path.join(project_root, '.lca', 'history', f'{module}.json')
    if os.path.exists(history_path):
        with open(history_path, 'r', encoding='utf-8') as f:
            return json.load(f)
    return []


def save_trend_entry(project_root, module, score, grade):
    """Save current score to trend history."""
    history_dir = os.path.join(project_root, '.lca', 'history')
    os.makedirs(history_dir, exist_ok=True)
    history_path = os.path.join(history_dir, f'{module}.json')

    history = load_trend_history(project_root, module)
    history.append({
        'date': datetime.now().isoformat(),
        'score': score,
        'grade': grade,
    })
    # Keep last 50 entries
    history = history[-50:]

    with open(history_path, 'w', encoding='utf-8') as f:
        json.dump(history, f, indent=2)


def generate_html(module, score, grade, grade_color, grade_label,
                  complexity, security, n1, hardcoded, trend_history):
    """Generate self-contained HTML report."""

    # Prepare chart data
    ratings = complexity.get('ratings', {})
    rating_labels = ['A', 'B', 'C', 'D', 'E', 'F']
    rating_values = [ratings.get(r, 0) for r in rating_labels]
    rating_colors = ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444', '#dc2626']

    # Category scores for bar chart
    categories = {
        'Complexity': max(0, 100 - complexity.get('ef_count', 0) * 3),
        'Security': max(0, 100 - security.get('critical', 0) * 10 - security.get('high', 0) // 2),
        'N+1 Queries': max(0, 100 - n1.get('critical', 0) * 8 - n1.get('high', 0) * 3),
        'Hardcoded': max(0, 100 - hardcoded.get('total', 0) * 2),
    }

    # Trend data
    trend_dates = json.dumps([e.get('date', '')[:10] for e in trend_history[-10:]])
    trend_scores = json.dumps([e.get('score', 0) for e in trend_history[-10:]])

    # Build findings tables
    def complexity_rows():
        rows = ''
        for item in complexity.get('top10', []):
            func = item.get('function', '?')
            file = item.get('file', '?')
            cc = item.get('complexity', 0)
            rating = item.get('rating', 'A')
            loc = item.get('loc', 0)
            color = {'A': '#22c55e', 'B': '#84cc16', 'C': '#eab308', 'D': '#f97316', 'E': '#ef4444', 'F': '#dc2626'}.get(rating, '#888')
            rows += f'<tr><td><code>{func}</code></td><td>{file}</td><td><strong>{cc}</strong></td><td>{loc}</td><td style="color:{color};font-weight:700">{rating}</td></tr>\n'
        return rows

    def security_rows():
        rows = ''
        for item in security.get('top10', []):
            rule = item.get('rule', '?')
            file = item.get('file', '?')
            func = item.get('function', '?')
            line = item.get('line', 0)
            sev = item.get('severity', '?')
            color = '#dc2626' if sev == 'CRITICAL' else '#ef4444' if sev == 'HIGH' else '#eab308'
            rows += f'<tr><td><code>{rule}</code></td><td>{file}</td><td>{func}</td><td>{line}</td><td style="color:{color}">{sev}</td></tr>\n'
        return rows

    def n1_rows():
        rows = ''
        for item in n1.get('top10', []):
            func = item.get('function', '?')
            file = item.get('file', '?')
            sev = item.get('severity', '?')
            loop_line = item.get('loop_line', 0)
            db_line = item.get('db_line', 0)
            color = '#dc2626' if sev == 'CRITICAL' else '#ef4444' if sev == 'HIGH' else '#eab308'
            rows += f'<tr><td><code>{func}</code></td><td>{file}</td><td>{loop_line}</td><td>{db_line}</td><td style="color:{color}">{sev}</td></tr>\n'
        return rows

    def hardcoded_rows():
        rows = ''
        for item in hardcoded.get('top10', []):
            rule = item.get('rule', '?')
            file = item.get('file', '?')
            func = item.get('function', '?')
            line = item.get('line', 0)
            match = item.get('match', '?')[:50]
            rows += f'<tr><td><code>{rule}</code></td><td>{file}</td><td>{func}</td><td>{line}</td><td><code>{match}</code></td></tr>\n'
        return rows

    now = datetime.now().strftime('%Y-%m-%d %H:%M')

    html = f'''<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LCA Report — {module}</title>
<style>
*{{margin:0;padding:0;box-sizing:border-box}}
body{{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;line-height:1.6;padding:2rem}}
.container{{max-width:1200px;margin:0 auto}}
.header{{text-align:center;padding:2rem 0;border-bottom:1px solid #1e293b;margin-bottom:2rem}}
.header h1{{font-size:1.8rem;color:#f1f5f9;margin-bottom:.5rem}}
.header .subtitle{{color:#94a3b8;font-size:.9rem}}
.score-card{{display:flex;align-items:center;justify-content:center;gap:2rem;padding:2rem;margin:1.5rem 0}}
.score-circle{{width:140px;height:140px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;border:4px solid {grade_color};background:rgba({",".join(str(int(grade_color.lstrip("#")[i:i+2],16)) for i in (0,2,4))},0.1)}}
.score-circle .grade{{font-size:3rem;font-weight:800;color:{grade_color}}}
.score-circle .score{{font-size:.9rem;color:#94a3b8}}
.score-meta{{text-align:left}}
.score-meta .label{{font-size:1.1rem;color:{grade_color};font-weight:600}}
.score-meta .desc{{color:#94a3b8;font-size:.85rem;margin-top:.25rem}}
.stats-grid{{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin:1.5rem 0}}
.stat-card{{background:#1e293b;border-radius:10px;padding:1.2rem;text-align:center;border:1px solid #334155}}
.stat-card .value{{font-size:1.6rem;font-weight:700;color:#f1f5f9}}
.stat-card .label{{font-size:.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-top:.3rem}}
.charts{{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin:2rem 0}}
.chart-box{{background:#1e293b;border-radius:10px;padding:1.5rem;border:1px solid #334155}}
.chart-box h3{{font-size:.9rem;color:#94a3b8;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.05em}}
canvas{{max-height:250px}}
.section{{background:#1e293b;border-radius:10px;margin:1rem 0;border:1px solid #334155;overflow:hidden}}
.section-header{{padding:1rem 1.5rem;cursor:pointer;display:flex;justify-content:space-between;align-items:center;user-select:none;transition:background .2s}}
.section-header:hover{{background:#334155}}
.section-header h3{{font-size:.95rem;color:#f1f5f9}}
.section-header .badge{{background:rgba(239,68,68,.15);color:#ef4444;padding:.2rem .6rem;border-radius:20px;font-size:.75rem;font-weight:600}}
.section-header .badge.green{{background:rgba(34,197,94,.15);color:#22c55e}}
.section-header .badge.yellow{{background:rgba(234,179,8,.15);color:#eab308}}
.section-header .arrow{{color:#64748b;transition:transform .2s;font-size:.8rem}}
.section-header.active .arrow{{transform:rotate(90deg)}}
.section-body{{max-height:0;overflow:hidden;transition:max-height .3s ease-out}}
.section-body.open{{max-height:2000px}}
table{{width:100%;border-collapse:collapse}}
th{{text-align:left;padding:.6rem 1rem;color:#94a3b8;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #334155}}
td{{padding:.5rem 1rem;border-bottom:1px solid #1e293b;font-size:.85rem}}
tr:hover{{background:#0f172a}}
code{{background:#0f172a;padding:.15rem .4rem;border-radius:4px;font-size:.8rem;color:#7dd3fc}}
.footer{{text-align:center;color:#475569;font-size:.75rem;padding:2rem 0;margin-top:2rem;border-top:1px solid #1e293b}}
@media(max-width:768px){{.charts{{grid-template-columns:1fr}}.score-card{{flex-direction:column}}.stats-grid{{grid-template-columns:repeat(2,1fr)}}}}
@media print{{body{{background:#fff;color:#333}}th{{color:#666}}td{{color:#333}}.section-body{{max-height:none!important}}.chart-box{{break-inside:avoid}}}}
</style>
</head>
<body>
<div class="container">
<div class="header">
<h1>Code Health Report</h1>
<div class="subtitle">Module: <strong>{module}</strong> &middot; Generated: {now}</div>
</div>

<div class="score-card">
<div class="score-circle">
<span class="grade">{grade}</span>
<span class="score">{score}/100</span>
</div>
<div class="score-meta">
<div class="label">{grade_label}</div>
<div class="desc">{complexity.get('total',0)} functions analyzed<br>{security.get('total',0)} security findings<br>{n1.get('total',0)} N+1 patterns</div>
</div>
</div>

<div class="stats-grid">
<div class="stat-card"><div class="value">{complexity.get('total',0)}</div><div class="label">Functions</div></div>
<div class="stat-card"><div class="value">{complexity.get('ef_count',0)}</div><div class="label">E/F Rated</div></div>
<div class="stat-card"><div class="value">{security.get('critical',0)}</div><div class="label">Critical Vulns</div></div>
<div class="stat-card"><div class="value">{n1.get('total',0)}</div><div class="label">N+1 Patterns</div></div>
<div class="stat-card"><div class="value">{hardcoded.get('total',0)}</div><div class="label">Hardcoded</div></div>
<div class="stat-card"><div class="value">{complexity.get('worst_cc',0)}</div><div class="label">Worst CC</div></div>
</div>

<div class="charts">
<div class="chart-box">
<h3>Complexity Distribution</h3>
<canvas id="ratingChart"></canvas>
</div>
<div class="chart-box">
<h3>Category Scores</h3>
<canvas id="categoryChart"></canvas>
</div>
</div>

{"<div class='chart-box' style='margin:1rem 0'><h3>Score Trend</h3><canvas id='trendChart'></canvas></div>" if len(trend_history) > 1 else ""}

<div class="section">
<div class="section-header" onclick="toggle(this)">
<h3>Complexity — {complexity.get('total',0)} functions</h3>
<div style="display:flex;gap:.5rem;align-items:center">
<span class="badge {'green' if complexity.get('ef_count',0)==0 else 'yellow' if complexity.get('ef_count',0)<3 else ''}">{complexity.get('ef_count',0)} E/F rated</span>
<span class="arrow">&#9654;</span>
</div>
</div>
<div class="section-body">
<table>
<tr><th>Function</th><th>File</th><th>CC</th><th>LOC</th><th>Rating</th></tr>
{complexity_rows()}
</table>
</div>
</div>

<div class="section">
<div class="section-header" onclick="toggle(this)">
<h3>Security — {security.get('total',0)} findings</h3>
<div style="display:flex;gap:.5rem;align-items:center">
<span class="badge">{security.get('critical',0)} critical</span>
<span class="arrow">&#9654;</span>
</div>
</div>
<div class="section-body">
<table>
<tr><th>Rule</th><th>File</th><th>Function</th><th>Line</th><th>Severity</th></tr>
{security_rows()}
</table>
</div>
</div>

<div class="section">
<div class="section-header" onclick="toggle(this)">
<h3>N+1 Queries — {n1.get('total',0)} patterns</h3>
<div style="display:flex;gap:.5rem;align-items:center">
<span class="badge {'green' if n1.get('total',0)==0 else 'yellow' if n1.get('total',0)<5 else ''}">{n1.get('high',0)} high</span>
<span class="arrow">&#9654;</span>
</div>
</div>
<div class="section-body">
<table>
<tr><th>Function</th><th>File</th><th>Loop Line</th><th>DB Line</th><th>Severity</th></tr>
{n1_rows()}
</table>
</div>
</div>

<div class="section">
<div class="section-header" onclick="toggle(this)">
<h3>Hardcoded Values — {hardcoded.get('total',0)} findings</h3>
<div style="display:flex;gap:.5rem;align-items:center">
<span class="badge {'green' if hardcoded.get('total',0)==0 else 'yellow'}">{hardcoded.get('total',0)}</span>
<span class="arrow">&#9654;</span>
</div>
</div>
<div class="section-body">
<table>
<tr><th>Rule</th><th>File</th><th>Function</th><th>Line</th><th>Match</th></tr>
{hardcoded_rows()}
</table>
</div>
</div>

<div class="footer">
Generated by Logimax Code Analysis (LCA) Suite &middot; {now}
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function toggle(el){{
el.classList.toggle('active');
el.nextElementSibling.classList.toggle('open');
}}

// Rating donut
new Chart(document.getElementById('ratingChart'),{{
type:'doughnut',
data:{{
labels:{json.dumps(rating_labels)},
datasets:[{{data:{json.dumps(rating_values)},backgroundColor:{json.dumps(rating_colors)},borderWidth:0}}]
}},
options:{{responsive:true,plugins:{{legend:{{position:'right',labels:{{color:'#94a3b8',font:{{size:12}}}}}}}}}}
}});

// Category bars
new Chart(document.getElementById('categoryChart'),{{
type:'bar',
data:{{
labels:{json.dumps(list(categories.keys()))},
datasets:[{{data:{json.dumps(list(categories.values()))},backgroundColor:['#3b82f6','#ef4444','#f97316','#eab308'],borderRadius:6,borderWidth:0}}]
}},
options:{{indexAxis:'y',responsive:true,plugins:{{legend:{{display:false}}}},scales:{{x:{{max:100,grid:{{color:'#1e293b'}},ticks:{{color:'#94a3b8'}}}},y:{{grid:{{display:false}},ticks:{{color:'#e2e8f0'}}}}}}}}
}});

// Trend line
{'var trendEl=document.getElementById("trendChart");if(trendEl){new Chart(trendEl,{type:\"line\",data:{labels:'+trend_dates+',datasets:[{data:'+trend_scores+',borderColor:\"'+grade_color+'\",backgroundColor:\"rgba(59,130,246,0.1)\",fill:true,tension:0.3,pointRadius:4,pointBackgroundColor:\"'+grade_color+'\"}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0,max:100,grid:{color:\"#1e293b\"},ticks:{color:\"#94a3b8\"}},x:{grid:{display:false},ticks:{color:\"#94a3b8\"}}}}});}' if len(trend_history) > 1 else ''}
</script>
</body>
</html>'''
    return html


def run_report(project_root, module, output=None, track=True):
    """Run all tools and generate HTML report for a module."""
    print(f'Scanning {module}...', file=sys.stderr)

    t0 = time.time()
    complexity = run_complexity(project_root, module)
    security = run_security(project_root, module)
    n1_data = run_n1(project_root, module)
    hardcoded_data = run_hardcoded(project_root, module)

    score = calculate_score(complexity, security, n1_data, hardcoded_data)
    grade, grade_color, grade_label = get_grade(score)

    # Load and save trend
    trend_history = load_trend_history(project_root, module)
    if track:
        save_trend_entry(project_root, module, score, grade)
        trend_history = load_trend_history(project_root, module)

    html = generate_html(module, score, grade, grade_color, grade_label,
                         complexity, security, n1_data, hardcoded_data, trend_history)

    elapsed = time.time() - t0
    print(f'Done in {elapsed:.1f}s — {module}: {score}/100 (Grade {grade})', file=sys.stderr)

    # Write output
    if output:
        with open(output, 'w', encoding='utf-8') as f:
            f.write(html)
        print(f'Report: {output}', file=sys.stderr)
    else:
        out_name = f'{module}_health.html'
        with open(out_name, 'w', encoding='utf-8') as f:
            f.write(html)
        print(f'Report: {out_name}', file=sys.stderr)

    return {'module': module, 'score': score, 'grade': grade}


def main():
    parser = argparse.ArgumentParser(description='LCA HTML Report Generator')
    parser.add_argument('--project-root', required=True)
    parser.add_argument('--module', help='Module to analyze')
    parser.add_argument('--all', action='store_true', help='Analyze all modules')
    parser.add_argument('--output', help='Output HTML file')
    parser.add_argument('--output-dir', help='Output directory for --all mode')
    parser.add_argument('--no-track', action='store_true', help='Skip trend tracking')
    args = parser.parse_args()

    project_root = os.path.abspath(args.project_root)

    if args.all:
        # Discover modules
        ctrl_dir = os.path.join(project_root, 'admin', 'application', 'controllers')
        modules = set()
        if os.path.exists(ctrl_dir):
            for f in os.listdir(ctrl_dir):
                if f.startswith('admin_ret_') and f.endswith('.php'):
                    mod = f.replace('admin_ret_', '').replace('.php', '')
                    modules.add(mod)

        out_dir = args.output_dir or '.'
        os.makedirs(out_dir, exist_ok=True)

        results = []
        for mod in sorted(modules):
            out_path = os.path.join(out_dir, f'{mod}_health.html')
            r = run_report(project_root, mod, out_path, not args.no_track)
            results.append(r)

        print(f'\n{"="*50}', file=sys.stderr)
        print(f'All modules scanned: {len(results)}', file=sys.stderr)
        for r in sorted(results, key=lambda x: x['score']):
            print(f'  {r["grade"]} {r["score"]:3d}/100  {r["module"]}', file=sys.stderr)
    else:
        if not args.module:
            parser.error('--module or --all required')
        run_report(project_root, args.module, args.output, not args.no_track)


if __name__ == '__main__':
    main()
