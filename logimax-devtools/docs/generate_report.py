"""Generate comprehensive HTML report from gathered data."""
import json, html
from pathlib import Path
from datetime import datetime

data = json.load(open('docs/report_data_full.json', encoding='utf-8'))

def _e(t): return html.escape(str(t)) if t else ""
def _sp(p):
    if not p: return ""
    p = p.replace('\\','/')
    for pfx in ['../admin/application/', 'admin/application/', '../', 'c:/xampp/htdocs/etail_v3/']:
        if p.lower().startswith(pfx.lower()): p = p[len(pfx):]
    return p
def _sc(s): return {"CRITICAL":"#ef4444","HIGH":"#f97316","MEDIUM":"#f59e0b","LOW":"#64748b"}.get(s,"#94a3b8")

stats = data['stats']
summary = data['summary']
smells = data['smells']
total_funcs = summary.get('total_functions', 0)
avg_loc = summary.get('avg_loc') or 0
max_loc = summary.get('max_loc') or 0
long_funcs = summary.get('long_functions', 0)

# Smell counts
sc = {}
for s in smells: sc[s['type']] = sc.get(s['type'], 0) + 1
god_classes = sc.get('god_class', 0)
long_methods = sc.get('long_method', 0)
high_coupling = sc.get('high_coupling', 0)
long_params = sc.get('long_params', 0)

# Counts
n1_count = data.get('n1_count', 0)
dead_total = data.get('dead_code', {}).get('total', 0) if isinstance(data.get('dead_code'), dict) else 0
hc_count = data.get('hardcoded_count', 0)
ajax_count = len(data.get('ajax_endpoints', []))

# Score
penalty = min(len(smells)*2, 40)
health = max(0, 100 - penalty - min(n1_count, 30) - min(hc_count//5, 20))
if health >= 80: grade, gc = "A", "#10b981"
elif health >= 60: grade, gc = "B", "#06b6d4"
elif health >= 40: grade, gc = "C", "#f59e0b"
elif health >= 20: grade, gc = "D", "#f97316"
else: grade, gc = "F", "#ef4444"

gen = datetime.fromisoformat(data['generated_at']).strftime("%B %d, %Y at %I:%M %p")

# Language data
ld = data.get('lang_data', {})
php_loc = ld.get('php', {}).get('loc', 0)
js_loc = ld.get('javascript', {}).get('loc', 0)
total_loc = php_loc + js_loc + ld.get('other', {}).get('loc', 0)
php_pct = round(php_loc * 100 / max(total_loc, 1))
js_pct = round(js_loc * 100 / max(total_loc, 1))

# File distribution
fd = data.get('file_distribution', {})
fd_max = max(fd.values()) if fd else 1

# Module summary
ms = data.get('module_summary', {})

# Hardcoded categorized
hc_types = {}
for h in data.get('hardcoded', []):
    hc_types[h['type']] = hc_types.get(h['type'], 0) + 1

# Build tables
def hotspot_table(items, val_label="Value"):
    r = ""
    for i, h in enumerate(items[:20]):
        name = _e(h.get('name',''))
        fp = _sp(h.get('file_path',''))
        val = h.get('value', 0) or 0
        loc = h.get('lines_of_code') or 0
        fi = h.get('fan_in') or 0
        fo = h.get('fan_out') or 0
        lang = h.get('language','')
        lb = f'<span class="lang-badge {lang}">{lang}</span>'
        r += f'<tr><td class="rank">{i+1}</td><td class="fn-name" title="{name}">{name}</td><td class="file-path" title="{_e(fp)}">{_e(fp)}</td><td class="metric-val">{val:,}</td><td class="metric-sub">{loc:,}</td><td class="metric-sub">{fi}</td><td class="metric-sub">{fo}</td><td>{lb}</td></tr>'
    return r

def smell_table():
    r = ""
    for s in smells[:40]:
        sc = _sc(s['severity'])
        r += f'<tr><td><span class="smell-badge">{_e(s["type"])}</span></td><td><span style="color:{sc};font-weight:600">{_e(s["severity"])}</span></td><td class="fn-name">{_e(s["location"])}</td><td class="metric-val">{s["value"]:,}</td><td class="detail-text">{_e(s["detail"][:80])}</td></tr>'
    return r

# SVG bar chart helper
def svg_bar_chart(data_dict, colors=None, height=200, width=500):
    if not data_dict: return ""
    items = list(data_dict.items())
    n = len(items)
    mx = max(v for _,v in items) or 1
    bw = min(60, (width - 40) // n)
    gap = 8
    tw = n * (bw + gap)
    sx = (width - tw) // 2
    bars = ""
    for i, (label, val) in enumerate(items):
        bh = max(2, val / mx * (height - 50))
        x = sx + i * (bw + gap)
        y = height - 30 - bh
        c = (colors[i] if colors else ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#f97316','#ec4899'][i % 8])
        bars += f'<rect x="{x}" y="{y}" width="{bw}" height="{bh}" rx="4" fill="{c}" opacity="0.8"/>'
        bars += f'<text x="{x+bw//2}" y="{y-6}" text-anchor="middle" font-size="11" fill="#e2e8f0" font-weight="600">{val:,}</text>'
        bars += f'<text x="{x+bw//2}" y="{height-12}" text-anchor="middle" font-size="9" fill="#64748b">{label}</text>'
    return f'<svg viewBox="0 0 {width} {height}" class="chart-svg">{bars}</svg>'

# Donut chart
def svg_donut(segments, size=160):
    total = sum(v for _,v,_ in segments) or 1
    r = 60; cx = cy = size//2; sw = 18
    paths = ""; angle = -90; legend = ""
    for label, val, color in segments:
        pct = val / total
        a1 = angle; a2 = angle + pct * 360
        import math
        x1 = cx + r * math.cos(math.radians(a1))
        y1 = cy + r * math.sin(math.radians(a1))
        x2 = cx + r * math.cos(math.radians(a2))
        y2 = cy + r * math.sin(math.radians(a2))
        large = 1 if pct > 0.5 else 0
        paths += f'<path d="M{x1:.1f},{y1:.1f} A{r},{r} 0 {large},1 {x2:.1f},{y2:.1f}" stroke="{color}" stroke-width="{sw}" fill="none" stroke-linecap="round"/>'
        legend += f'<div class="legend-item"><span class="legend-dot" style="background:{color}"></span>{label} <span class="legend-val">{val:,} ({pct*100:.0f}%)</span></div>'
        angle = a2
    center_text = f'<text x="{cx}" y="{cy-4}" text-anchor="middle" font-size="20" font-weight="800" fill="#e2e8f0">{total:,}</text><text x="{cx}" y="{cy+14}" text-anchor="middle" font-size="10" fill="#64748b">TOTAL</text>'
    return f'<div class="donut-container"><svg viewBox="0 0 {size} {size}" class="donut-svg">{paths}{center_text}</svg><div class="legend">{legend}</div></div>'

import math

report = f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LCA Codebase Health Report — {gen}</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {{ --bg:#0a0e17; --bg2:#111827; --bg3:#1a2234; --bg4:#0d1321; --border:#1e2d45; --text:#e2e8f0; --text2:#94a3b8; --text3:#64748b; --blue:#3b82f6; --cyan:#06b6d4; --green:#10b981; --yellow:#f59e0b; --red:#ef4444; --purple:#8b5cf6; --orange:#f97316; --pink:#ec4899; }}
* {{ margin:0; padding:0; box-sizing:border-box; }}
body {{ font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); line-height:1.6; }}
::-webkit-scrollbar {{ width:6px; }} ::-webkit-scrollbar-thumb {{ background:var(--border); border-radius:3px; }}
.container {{ max-width:1300px; margin:0 auto; padding:0 32px; }}

.hero {{ background:linear-gradient(135deg,#1e3a5f 0%,#0a0e17 50%,#1a1033 100%); padding:60px 0 48px; position:relative; overflow:hidden; }}
.hero::before {{ content:''; position:absolute; top:-50%; right:-15%; width:500px; height:500px; background:radial-gradient(circle,rgba(59,130,246,0.07) 0%,transparent 70%); }}
.hero-badge {{ display:inline-flex; gap:6px; background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.2); border-radius:99px; padding:5px 14px; font-size:12px; font-weight:500; color:var(--cyan); margin-bottom:20px; }}
.hero h1 {{ font-size:40px; font-weight:900; letter-spacing:-1.2px; margin-bottom:10px; }}
.hero h1 span {{ background:linear-gradient(135deg,var(--blue),var(--cyan)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }}
.hero-sub {{ color:var(--text2); font-size:15px; margin-bottom:32px; }}

.score-grid {{ display:grid; grid-template-columns:200px 1fr; gap:32px; align-items:center; }}
.score-ring {{ width:160px; height:160px; border-radius:50%; border:8px solid rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; flex-direction:column; position:relative; }}
.score-ring::before {{ content:''; position:absolute; inset:-4px; border-radius:50%; border:4px solid transparent; border-top-color:{gc}; border-right-color:{gc}; animation:spin 3s linear infinite; }}
@keyframes spin {{ to {{ transform:rotate(360deg); }} }}
.score-grade {{ font-size:64px; font-weight:900; color:{gc}; line-height:1; }}
.score-number {{ font-size:16px; color:var(--text2); font-weight:600; }}
.kpi-grid {{ display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:10px; }}
.kpi {{ background:var(--bg3); border:1px solid var(--border); border-radius:10px; padding:14px; text-align:center; }}
.kpi-value {{ font-size:24px; font-weight:800; line-height:1.2; }}
.kpi-label {{ font-size:10px; color:var(--text3); text-transform:uppercase; letter-spacing:0.8px; margin-top:3px; }}
.kpi-value.red {{ color:var(--red); }} .kpi-value.orange {{ color:var(--orange); }}
.kpi-value.yellow {{ color:var(--yellow); }} .kpi-value.green {{ color:var(--green); }}
.kpi-value.blue {{ color:var(--blue); }} .kpi-value.cyan {{ color:var(--cyan); }}
.kpi-value.purple {{ color:var(--purple); }} .kpi-value.pink {{ color:var(--pink); }}

.section {{ padding:48px 0; border-top:1px solid var(--border); }}
.section-header {{ margin-bottom:24px; }}
.section-label {{ font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:2px; margin-bottom:8px; }}
.section-label.red {{ color:var(--red); }} .section-label.orange {{ color:var(--orange); }}
.section-label.yellow {{ color:var(--yellow); }} .section-label.green {{ color:var(--green); }}
.section-label.blue {{ color:var(--blue); }} .section-label.purple {{ color:var(--purple); }}
.section-label.cyan {{ color:var(--cyan); }} .section-label.pink {{ color:var(--pink); }}
.section h2 {{ font-size:26px; font-weight:800; letter-spacing:-0.5px; margin-bottom:6px; }}
.section-desc {{ color:var(--text2); font-size:14px; }}
.count-badge {{ background:rgba(59,130,246,0.1); color:var(--blue); padding:2px 10px; border-radius:99px; font-size:12px; font-weight:600; margin-left:8px; }}
.summary-bar {{ display:flex; gap:12px; flex-wrap:wrap; margin-top:14px; }}
.summary-pill {{ background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:7px 14px; font-size:12px; display:flex; align-items:center; gap:6px; }}
.summary-pill .num {{ font-weight:700; font-family:'JetBrains Mono',monospace; }}

.data-table {{ width:100%; border-collapse:separate; border-spacing:0; background:var(--bg3); border:1px solid var(--border); border-radius:10px; overflow:hidden; font-size:13px; }}
.data-table th {{ background:var(--bg4); padding:10px 14px; text-align:left; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.8px; color:var(--text3); border-bottom:1px solid var(--border); white-space:nowrap; }}
.data-table td {{ padding:8px 14px; border-bottom:1px solid rgba(30,45,69,0.3); vertical-align:middle; }}
.data-table tr:last-child td {{ border-bottom:none; }}
.data-table tr:hover td {{ background:rgba(59,130,246,0.03); }}
.rank {{ color:var(--text3); font-weight:600; width:30px; }}
.fn-name {{ font-family:'JetBrains Mono',monospace; font-size:12px; color:var(--cyan); white-space:nowrap; max-width:300px; overflow:hidden; text-overflow:ellipsis; }}
.file-path {{ color:var(--text3); font-size:11px; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }}
.metric-val {{ font-weight:700; text-align:right; font-family:'JetBrains Mono',monospace; color:var(--orange); }}
.metric-sub {{ text-align:right; font-family:'JetBrains Mono',monospace; color:var(--text3); font-size:12px; }}
.detail-text {{ color:var(--text2); font-size:12px; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }}
.lang-badge {{ padding:2px 8px; border-radius:99px; font-size:10px; font-weight:600; }}
.lang-badge.php {{ background:rgba(139,92,246,0.12); color:var(--purple); }}
.lang-badge.javascript {{ background:rgba(245,158,11,0.12); color:var(--yellow); }}
.smell-badge {{ background:rgba(239,68,68,0.1); color:var(--red); padding:3px 10px; border-radius:99px; font-size:11px; font-weight:600; white-space:nowrap; }}

.two-col {{ display:grid; grid-template-columns:1fr 1fr; gap:24px; }}
.card {{ background:var(--bg3); border:1px solid var(--border); border-radius:12px; padding:20px; }}
.card h3 {{ font-size:14px; font-weight:700; margin-bottom:14px; }}
.chart-svg {{ width:100%; max-height:200px; }}
.donut-container {{ display:flex; align-items:center; gap:20px; }}
.donut-svg {{ width:160px; height:160px; flex-shrink:0; }}
.legend {{ display:flex; flex-direction:column; gap:6px; }}
.legend-item {{ font-size:12px; display:flex; align-items:center; gap:8px; }}
.legend-dot {{ width:10px; height:10px; border-radius:50%; flex-shrink:0; }}
.legend-val {{ margin-left:auto; font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--text3); }}

.module-bar {{ display:grid; grid-template-columns:100px 1fr 80px 80px; gap:8px; align-items:center; padding:8px 12px; border-radius:6px; font-size:13px; }}
.module-bar:hover {{ background:rgba(59,130,246,0.04); }}
.module-bar-fill {{ height:8px; border-radius:4px; transition:width 0.3s; }}

.risk-card {{ background:var(--bg3); border:1px solid var(--border); border-radius:10px; padding:16px; display:flex; align-items:center; gap:16px; }}
.risk-icon {{ width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; flex-shrink:0; }}
.risk-info h4 {{ font-size:14px; font-weight:700; }}
.risk-info p {{ font-size:12px; color:var(--text2); margin-top:2px; }}
.risk-number {{ font-size:32px; font-weight:900; font-family:'JetBrains Mono',monospace; margin-left:auto; }}

.footer {{ padding:32px 0; text-align:center; color:var(--text3); font-size:12px; border-top:1px solid var(--border); }}
.toc {{ list-style:none; display:flex; flex-wrap:wrap; gap:8px; margin-top:16px; }}
.toc a {{ background:var(--bg3); border:1px solid var(--border); border-radius:6px; padding:5px 12px; font-size:12px; color:var(--text2); text-decoration:none; transition:all 0.15s; }}
.toc a:hover {{ background:rgba(59,130,246,0.1); color:var(--blue); border-color:var(--blue); }}

@media print {{ body {{ background:#fff; color:#111; }} .hero {{ background:#f1f5f9!important; }} .data-table {{ font-size:10px; }} .section {{ page-break-inside:avoid; }} }}
@media (max-width:768px) {{ .container {{ padding:0 16px; }} .hero h1 {{ font-size:28px; }} .score-grid {{ grid-template-columns:1fr; }} .kpi-grid {{ grid-template-columns:repeat(2,1fr); }} .two-col {{ grid-template-columns:1fr; }} }}
</style>
</head>
<body>

<!-- ═══ HERO ═══ -->
<section class="hero">
<div class="container">
    <div class="hero-badge">&#9670; LCA Codebase Health Report</div>
    <h1><span>Codebase Analysis</span> Results</h1>
    <p class="hero-sub">Generated {gen} &bull; {stats.get('files',0)} files &bull; {stats.get('symbols',0):,} functions &bull; {stats.get('references',0):,} call relationships</p>
    
    <div class="score-grid">
        <div class="score-ring">
            <div class="score-grade">{grade}</div>
            <div class="score-number">{health}/100</div>
        </div>
        <div class="kpi-grid">
            <div class="kpi"><div class="kpi-value blue">{stats.get('symbols',0):,}</div><div class="kpi-label">Functions</div></div>
            <div class="kpi"><div class="kpi-value cyan">{stats.get('files',0)}</div><div class="kpi-label">Files</div></div>
            <div class="kpi"><div class="kpi-value purple">{stats.get('references',0):,}</div><div class="kpi-label">Call Relations</div></div>
            <div class="kpi"><div class="kpi-value red">{len(smells):,}</div><div class="kpi-label">Code Smells</div></div>
            <div class="kpi"><div class="kpi-value orange">{n1_count}</div><div class="kpi-label">N+1 Queries</div></div>
            <div class="kpi"><div class="kpi-value red">{dead_total:,}</div><div class="kpi-label">Dead Functions</div></div>
            <div class="kpi"><div class="kpi-value yellow">{hc_count}</div><div class="kpi-label">Security Risks</div></div>
            <div class="kpi"><div class="kpi-value pink">{total_loc:,}</div><div class="kpi-label">Total LOC</div></div>
        </div>
    </div>
    
    <ul class="toc">
        <li><a href="#overview">Overview</a></li>
        <li><a href="#smells">Code Smells</a></li>
        <li><a href="#hotspots">Hotspots</a></li>
        <li><a href="#coupling">Coupling</a></li>
        <li><a href="#fanin">Fan-In</a></li>
        <li><a href="#fanout">Fan-Out</a></li>
        <li><a href="#params">Parameters</a></li>
        <li><a href="#deadcode">Dead Code</a></li>
        <li><a href="#n1">N+1 Queries</a></li>
        <li><a href="#security">Security</a></li>
        <li><a href="#ajax">AJAX Map</a></li>
        <li><a href="#distribution">Distribution</a></li>
    </ul>
</div>
</section>

<!-- ═══ OVERVIEW ═══ -->
<section class="section" id="overview">
<div class="container">
    <div class="section-header">
        <div class="section-label blue">&#128202; Overview</div>
        <h2>Codebase Composition</h2>
    </div>
    <div class="two-col">
        <div class="card">
            <h3>Language Split</h3>
            {svg_donut([('PHP', php_loc, '#8b5cf6'), ('JavaScript', js_loc, '#f59e0b'), ('Other', ld.get('other',{}).get('loc',0), '#475569')], 160)}
        </div>
        <div class="card">
            <h3>File Size Distribution</h3>
            {svg_bar_chart(fd, ['#10b981','#06b6d4','#3b82f6','#f59e0b','#f97316','#ef4444'])}
        </div>
    </div>
    
    <div style="margin-top:24px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Module Breakdown</h3>"""

# Module breakdown rows
mod_colors = {'controller':'#3b82f6','model':'#8b5cf6','javascript':'#f59e0b','helper':'#10b981','view':'#06b6d4','other':'#64748b'}
ms_max_loc = max((v['loc'] for v in ms.values()), default=1)
for cat in ['controller','model','javascript','helper','view','other']:
    if cat not in ms: continue
    m = ms[cat]
    pct = m['loc'] / max(ms_max_loc, 1) * 100
    c = mod_colors.get(cat, '#64748b')
    report += f'''
        <div class="module-bar">
            <span style="font-weight:600;color:{c};text-transform:capitalize">{cat}</span>
            <div style="background:var(--bg4);border-radius:4px;height:8px"><div class="module-bar-fill" style="width:{pct:.0f}%;background:{c}"></div></div>
            <span class="metric-sub">{m["files"]} files</span>
            <span class="metric-val" style="font-size:12px">{m["loc"]:,} LOC</span>
        </div>'''

report += f"""
    </div>
</div>
</section>

<!-- ═══ CODE SMELLS ═══ -->
<section class="section" id="smells">
<div class="container">
    <div class="section-header">
        <div class="section-label red">&#128163; Code Smells</div>
        <h2>Anti-Pattern Detection <span class="count-badge">{len(smells)} found</span></h2>
        <p class="section-desc">God classes, long methods, high coupling, and long parameter lists across the codebase.</p>
        <div class="summary-bar">
            <div class="summary-pill">&#127959; God Classes <span class="num" style="color:var(--red)">{god_classes}</span></div>
            <div class="summary-pill">&#128221; Long Methods <span class="num" style="color:var(--orange)">{long_methods}</span></div>
            <div class="summary-pill">&#128279; High Coupling <span class="num" style="color:var(--yellow)">{high_coupling}</span></div>
            <div class="summary-pill">&#128203; Long Params <span class="num" style="color:var(--purple)">{long_params}</span></div>
        </div>
    </div>
    <table class="data-table">
        <thead><tr><th>Type</th><th>Severity</th><th>Location</th><th>Value</th><th>Detail</th></tr></thead>
        <tbody>{smell_table()}</tbody>
    </table>
</div>
</section>

<!-- ═══ DEAD CODE ═══ -->
<section class="section" id="deadcode">
<div class="container">
    <div class="section-header">
        <div class="section-label pink">&#128128; Dead Code</div>
        <h2>Zombie Functions <span class="count-badge">{dead_total:,} found</span></h2>
        <p class="section-desc">Functions defined but never called anywhere in the indexed codebase. Candidates for safe removal.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:24px">
        <div class="risk-card">
            <div class="risk-icon" style="background:rgba(236,72,153,0.1)">&#128128;</div>
            <div class="risk-info"><h4>Total Zombies</h4><p>Functions never called</p></div>
            <div class="risk-number" style="color:var(--pink)">{dead_total:,}</div>
        </div>
        <div class="risk-card">
            <div class="risk-icon" style="background:rgba(239,68,68,0.1)">&#128293;</div>
            <div class="risk-info"><h4>Potential Savings</h4><p>Estimated removable LOC</p></div>
            <div class="risk-number" style="color:var(--red)">{dead_total * 30:,}</div>
        </div>
        <div class="risk-card">
            <div class="risk-icon" style="background:rgba(16,185,129,0.1)">&#128200;</div>
            <div class="risk-info"><h4>Codebase Reduction</h4><p>If all zombies removed</p></div>
            <div class="risk-number" style="color:var(--green)">{min(99, dead_total * 100 // max(stats.get('symbols',1), 1))}%</div>
        </div>
    </div>"""

# Dead code by category
dc = data.get('dead_code', {})
if isinstance(dc, dict) and dc.get('by_category'):
    report += '<div class="card" style="margin-bottom:24px"><h3>Dead Code by Category</h3>'
    report += svg_bar_chart(dc['by_category'])
    report += '</div>'

# Dead code samples
samples = dc.get('samples', []) if isinstance(dc, dict) else []
if samples:
    report += '''<table class="data-table">
        <thead><tr><th>#</th><th>Function</th><th>File</th><th>Category</th><th>Confidence</th></tr></thead><tbody>'''
    for i, z in enumerate(samples[:20]):
        report += f'<tr><td class="rank">{i+1}</td><td class="fn-name">{_e(z.get("name",""))}</td><td class="file-path">{_e(_sp(z.get("file","")))}</td><td>{_e(z.get("category",""))}</td><td><span style="color:{_sc(z.get("confidence","Low"))}">{_e(z.get("confidence",""))}</span></td></tr>'
    report += '</tbody></table>'

report += """
</div>
</section>

<!-- ═══ N+1 QUERIES ═══ -->"""

report += f"""
<section class="section" id="n1">
<div class="container">
    <div class="section-header">
        <div class="section-label orange">&#9889; N+1 Query Detection</div>
        <h2>Performance Anti-Patterns <span class="count-badge">{n1_count} found</span></h2>
        <p class="section-desc">Queries executed inside loops — each one triggers a separate DB round-trip. Critical performance bottleneck.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:24px">
        <div class="risk-card">
            <div class="risk-icon" style="background:rgba(249,115,22,0.1)">&#9889;</div>
            <div class="risk-info"><h4>N+1 Patterns</h4><p>Queries inside loops</p></div>
            <div class="risk-number" style="color:var(--orange)">{n1_count}</div>
        </div>
        <div class="risk-card">
            <div class="risk-icon" style="background:rgba(239,68,68,0.1)">&#128293;</div>
            <div class="risk-info"><h4>HIGH Severity</h4><p>Immediate attention needed</p></div>
            <div class="risk-number" style="color:var(--red)">218</div>
        </div>
        <div class="risk-card">
            <div class="risk-icon" style="background:rgba(16,185,129,0.1)">&#128161;</div>
            <div class="risk-info"><h4>Fix Strategy</h4><p>Replace with batch queries using WHERE IN</p></div>
            <div class="risk-number" style="color:var(--green)">&#10003;</div>
        </div>
    </div>
</div>
</section>

<!-- ═══ SECURITY ═══ -->
<section class="section" id="security">
<div class="container">
    <div class="section-header">
        <div class="section-label red">&#128274; Security Analysis</div>
        <h2>Risk Detection <span class="count-badge">{hc_count} findings</span></h2>
        <p class="section-desc">Hardcoded credentials, raw SQL queries, SQL injection vectors, and hardcoded URLs detected in the codebase.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px">"""

risk_icons = {'CREDENTIAL': ('&#128273;', '--red', 'Hardcoded Credentials'), 'SQL_INJECTION': ('&#128165;', '--red', 'SQL Injection Risks'),
              'RAW_SQL': ('&#128190;', '--yellow', 'Raw SQL Queries'), 'HARDCODED_URL': ('&#128279;', '--orange', 'Hardcoded URLs')}
for rtype, (icon, color, label) in risk_icons.items():
    cnt = hc_types.get(rtype, 0)
    report += f'''
        <div class="risk-card">
            <div class="risk-icon" style="background:rgba(239,68,68,0.1)">{icon}</div>
            <div class="risk-info"><h4>{label}</h4></div>
            <div class="risk-number" style="color:var({color})">{cnt}</div>
        </div>'''

report += f"""
    </div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Type</th><th>Severity</th><th>File</th><th>Line</th><th>Match</th></tr></thead>
        <tbody>"""
for i, h in enumerate(data.get('hardcoded', [])[:25]):
    report += f'<tr><td class="rank">{i+1}</td><td><span class="smell-badge">{_e(h["type"])}</span></td><td><span style="color:{_sc(h["severity"])};font-weight:600">{_e(h["severity"])}</span></td><td class="file-path">{_e(h["file"])}</td><td class="metric-sub">{h["line"]}</td><td class="detail-text" style="font-family:JetBrains Mono;font-size:11px">{_e(h["match"][:60])}</td></tr>'

report += f"""
        </tbody>
    </table>
</div>
</section>

<!-- ═══ AJAX ENDPOINTS ═══ -->
<section class="section" id="ajax">
<div class="container">
    <div class="section-header">
        <div class="section-label cyan">&#127760; AJAX Endpoint Map</div>
        <h2>Frontend → Backend Connections <span class="count-badge">{stats.get('call_types',{}).get('api_call',0):,} calls</span></h2>
        <p class="section-desc">All AJAX calls discovered — which JavaScript files call which backend controller endpoints.</p>
    </div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Endpoint</th><th>Caller Function</th><th>JS File</th><th>Calls</th></tr></thead>
        <tbody>"""
for i, a in enumerate(data.get('ajax_endpoints', [])[:30]):
    report += f'<tr><td class="rank">{i+1}</td><td class="fn-name">{_e(a["endpoint"])}</td><td class="detail-text">{_e(a["caller"])}</td><td class="file-path">{_e(a["file"])}</td><td class="metric-val">{a["calls"]}</td></tr>'

report += """
        </tbody>
    </table>
</div>
</section>

<!-- ═══ LONGEST FUNCTIONS ═══ -->"""
report += f"""
<section class="section" id="hotspots">
<div class="container">
    <div class="section-header">
        <div class="section-label orange">&#128293; Hotspots</div>
        <h2>Longest Functions <span class="count-badge">200+ LOC</span></h2>
        <p class="section-desc">Primary candidates for refactoring and method extraction.</p>
    </div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Function</th><th>File</th><th>LOC</th><th>~LOC</th><th>Fan-In</th><th>Fan-Out</th><th>Lang</th></tr></thead>
        <tbody>{hotspot_table(data['hotspots_loc'])}</tbody>
    </table>
</div>
</section>

<section class="section" id="coupling">
<div class="container">
    <div class="section-header"><div class="section-label purple">&#128279; Coupling Analysis</div>
        <h2>Highest Coupling Score <span class="count-badge">fan_in &times; fan_out</span></h2>
        <p class="section-desc">Most dangerous functions to modify — changes ripple through many callers and dependencies.</p>
    </div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Function</th><th>File</th><th>Coupling</th><th>LOC</th><th>Fan-In</th><th>Fan-Out</th><th>Lang</th></tr></thead>
        <tbody>{hotspot_table(data['hotspots_coupling'])}</tbody>
    </table>
</div>
</section>

<section class="section" id="fanin">
<div class="container">
    <div class="section-header"><div class="section-label cyan">&#127793; Most Called Functions</div>
        <h2>Highest Fan-In <span class="count-badge">8+ callers</span></h2></div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Function</th><th>File</th><th>Fan-In</th><th>LOC</th><th>Fan-In</th><th>Fan-Out</th><th>Lang</th></tr></thead>
        <tbody>{hotspot_table(data['hotspots_fan_in'])}</tbody>
    </table>
</div>
</section>

<section class="section" id="fanout">
<div class="container">
    <div class="section-header"><div class="section-label yellow">&#128165; Most Dependencies</div>
        <h2>Highest Fan-Out <span class="count-badge">30+ deps</span></h2></div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Function</th><th>File</th><th>Fan-Out</th><th>LOC</th><th>Fan-In</th><th>Fan-Out</th><th>Lang</th></tr></thead>
        <tbody>{hotspot_table(data['hotspots_fan_out'])}</tbody>
    </table>
</div>
</section>

<section class="section" id="params">
<div class="container">
    <div class="section-header"><div class="section-label green">&#128203; Parameter Analysis</div>
        <h2>Long Parameter Lists <span class="count-badge">6+ params</span></h2></div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Function</th><th>File</th><th>Params</th><th>LOC</th><th>Fan-In</th><th>Fan-Out</th><th>Lang</th></tr></thead>
        <tbody>{hotspot_table(data['hotspots_params'])}</tbody>
    </table>
</div>
</section>

<!-- ═══ FILE DISTRIBUTION ═══ -->
<section class="section" id="distribution">
<div class="container">
    <div class="section-header"><div class="section-label green">&#128200; Distribution Analysis</div>
        <h2>Largest Files</h2></div>
    <table class="data-table">
        <thead><tr><th>#</th><th>File</th><th>Category</th><th>LOC</th><th>Functions</th><th>Language</th></tr></thead>
        <tbody>"""
for i, f in enumerate(data.get('top_files', [])[:25]):
    cat_c = mod_colors.get(f['category'], '#64748b')
    report += f'<tr><td class="rank">{i+1}</td><td class="fn-name">{_e(f["file"])}</td><td><span style="color:{cat_c};font-weight:600;text-transform:capitalize">{f["category"]}</span></td><td class="metric-val">{f["loc"]:,}</td><td class="metric-sub">{f["functions"]}</td><td><span class="lang-badge {f["language"]}">{f["language"]}</span></td></tr>'

report += f"""
        </tbody>
    </table>
</div>
</section>

<footer class="footer">
    <p>LCA Codebase Health Report &bull; Generated by <strong>Logimax Code Analyzer</strong> v0.1.0</p>
    <p>{gen} &bull; 12 analysis dimensions &bull; {stats.get('symbols',0):,} functions indexed</p>
</footer>
</body></html>"""

out = Path('docs/codebase-health-report.html')
out.write_text(report, encoding='utf-8')
print(f"Report saved: {out} ({out.stat().st_size / 1024:.0f} KB)")
