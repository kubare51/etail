"""LCA Mind Map Generator — Full-Stack Architecture Visualization.

Generates a self-contained interactive HTML mind map showing:
  JS files → AJAX calls → Controllers → Model calls → Models → DB queries → Tables

Usage: python generate_mindmap.py [--db path/to/index.db] [--output mindmap.html]
"""

import sys, json, argparse
sys.path.insert(0, 'backend/lca_core')
from lca.storage.symbol_db import SymbolDB
from pathlib import Path
from collections import defaultdict
from datetime import datetime


def extract_mindmap_data(db: SymbolDB) -> dict:
    """Extract full-stack flow data from SymbolDB."""
    conn = db._get_conn()
    
    # ── 1. Build file catalog ──
    file_info = {}
    for row in conn.execute('''
        SELECT f.path, f.language, COUNT(s.id) as func_count, 
               COALESCE(SUM(m.lines_of_code),0) as total_loc
        FROM files f
        LEFT JOIN symbols s ON s.file_id = f.id
        LEFT JOIN metrics m ON m.symbol_id = s.id
        GROUP BY f.id
    '''):
        r = dict(row)
        p = r['path'].replace('\\', '/')
        short = p.split('/')[-1]
        cat = 'other'
        if '/controllers/' in p: cat = 'controller'
        elif '/models/' in p: cat = 'model'
        elif '/views/' in p: cat = 'view'
        elif '/helpers/' in p: cat = 'helper'
        elif p.endswith('.js'): cat = 'javascript'
        file_info[p] = {'name': short, 'category': cat, 'lang': r['language'],
                         'funcs': r['func_count'], 'loc': r['total_loc']}
    
    # ── 2. Build symbol → file lookup ──
    sym_file = {}
    for row in conn.execute('SELECT name, file_path FROM symbols'):
        sym_file[row[0]] = row[1].replace('\\', '/')
    
    # ── 3. Extract AJAX calls: JS → Controller ──
    ajax_flows = []  # {js_file, js_func, endpoint, controller_file}
    for row in conn.execute('''
        SELECT source_name, target_name, file_path, metadata
        FROM refs WHERE call_type = 'api_call'
    '''):
        r = dict(row)
        js_file = r['file_path'].replace('\\', '/') if r['file_path'] else ''
        endpoint = r['target_name']
        # Try to resolve controller file from endpoint
        ctrl_file = ''
        # Endpoint format is typically "controller/method"
        parts = endpoint.split('/')
        if len(parts) >= 2:
            ctrl_name = parts[0] if parts[0] else parts[1] if len(parts) > 1 else ''
            # Look for matching controller file
            for fp, info in file_info.items():
                if info['category'] == 'controller' and ctrl_name.lower() in info['name'].lower():
                    ctrl_file = fp
                    break
        
        ajax_flows.append({
            'js_file': js_file,
            'js_func': r['source_name'],
            'endpoint': endpoint,
            'controller_file': ctrl_file,
        })
    
    # ── 4. Extract Controller → Model calls ──
    ctrl_model = defaultdict(set)  # controller_file → {model_file, ...}
    ctrl_model_funcs = defaultdict(list)  # controller_file → [{source, target, target_file}, ...]
    for row in conn.execute('''
        SELECT source_name, target_name, file_path, target_file, call_type
        FROM refs WHERE call_type = 'calls'
    '''):
        r = dict(row)
        src_file = r['file_path'].replace('\\', '/') if r['file_path'] else ''
        tgt_name = r['target_name']
        
        # Controller calling model method?
        if src_file and file_info.get(src_file, {}).get('category') == 'controller':
            # Check if target is in a model file
            if '::' in tgt_name:
                class_name = tgt_name.split('::')[0]
                for fp, info in file_info.items():
                    if info['category'] == 'model' and class_name.lower() in info['name'].lower().replace('_model', '').replace('.php', ''):
                        ctrl_model[src_file].add(fp)
                        ctrl_model_funcs[src_file].append({
                            'source': r['source_name'], 'target': tgt_name, 'model_file': fp
                        })
                        break
            # Also check via symbol lookup
            tgt_file = sym_file.get(tgt_name, '')
            if tgt_file and file_info.get(tgt_file, {}).get('category') == 'model':
                ctrl_model[src_file].add(tgt_file)
    
    # ── 5. Extract DB queries (model → table) ──
    model_tables = defaultdict(set)  # model_file → {table_name, ...}
    for row in conn.execute('''
        SELECT source_name, target_name, file_path, call_type
        FROM refs WHERE call_type = 'db_query'
    '''):
        r = dict(row)
        src_file = r['file_path'].replace('\\', '/') if r['file_path'] else ''
        if not src_file:
            src_file = sym_file.get(r['source_name'], '')
        table = r['target_name']
        if src_file:
            model_tables[src_file].add(table)
    
    # ── 6. Also scan for raw table references in model files ──
    import re
    models_dir = Path(r'C:\xampp\htdocs\etail_v3\admin\application\models')
    table_pattern = re.compile(
        r'''(?:->from|->join|->get|->insert|->update|->delete|->where)\s*\(\s*['"](\w+)['"]''',
        re.IGNORECASE
    )
    if models_dir.exists():
        for php_file in models_dir.glob('*.php'):
            fp = str(php_file).replace('\\', '/')
            # Find matching key in file_info
            matching_key = None
            for k in file_info:
                if php_file.name in k:
                    matching_key = k
                    break
            if not matching_key:
                continue
            try:
                content = php_file.read_text(encoding='utf-8', errors='ignore')
                for m in table_pattern.finditer(content):
                    table = m.group(1)
                    if len(table) > 2 and not table.startswith('$'):
                        model_tables[matching_key].add(table)
            except Exception:
                pass
    
    # ── 7. Build hierarchical mind map structure ──
    # Group: JS files → Controllers → Models → Tables
    
    # Collect all unique tables
    all_tables = set()
    for tables in model_tables.values():
        all_tables.update(tables)
    
    # Build controller nodes with their model + table children
    controllers = {}
    for fp, info in file_info.items():
        if info['category'] != 'controller': continue
        models_used = []
        for model_fp in ctrl_model.get(fp, set()):
            model_info = file_info.get(model_fp, {})
            tables_used = sorted(model_tables.get(model_fp, set()))
            models_used.append({
                'name': model_info.get('name', model_fp.split('/')[-1]),
                'path': model_fp,
                'funcs': model_info.get('funcs', 0),
                'loc': model_info.get('loc', 0),
                'tables': tables_used,
            })
        models_used.sort(key=lambda x: -x['funcs'])
        
        # Find JS files that call this controller
        js_callers = set()
        for af in ajax_flows:
            if af['controller_file'] == fp:
                if af['js_file'] and af['js_file'] in file_info:
                    js_callers.add(af['js_file'])
        
        controllers[fp] = {
            'name': info['name'],
            'path': fp,
            'funcs': info['funcs'],
            'loc': info['loc'],
            'models': models_used,
            'js_callers': sorted(js_callers),
        }
    
    # Build JS nodes
    js_files = {}
    for fp, info in file_info.items():
        if info['category'] != 'javascript': continue
        # What controllers does this JS call?
        called_controllers = set()
        for af in ajax_flows:
            if af['js_file'] == fp and af['controller_file']:
                called_controllers.add(af['controller_file'])
        if called_controllers or info['funcs'] > 10:
            js_files[fp] = {
                'name': info['name'],
                'path': fp,
                'funcs': info['funcs'],
                'loc': info['loc'],
                'controllers': sorted(called_controllers),
            }
    
    # Build models that aren't connected to controllers
    standalone_models = {}
    connected_models = set()
    for c in controllers.values():
        for m in c['models']:
            connected_models.add(m['path'])
    
    for fp, info in file_info.items():
        if info['category'] == 'model' and fp not in connected_models:
            tables_used = sorted(model_tables.get(fp, set()))
            if tables_used or info['funcs'] > 5:
                standalone_models[fp] = {
                    'name': info['name'],
                    'path': fp,
                    'funcs': info['funcs'],
                    'loc': info['loc'],
                    'tables': tables_used,
                }
    
    return {
        'generated_at': datetime.now().isoformat(),
        'controllers': controllers,
        'js_files': js_files,
        'standalone_models': standalone_models,
        'all_tables': sorted(all_tables),
        'ajax_count': len(ajax_flows),
        'stats': {
            'total_files': len(file_info),
            'controllers': sum(1 for v in file_info.values() if v['category'] == 'controller'),
            'models': sum(1 for v in file_info.values() if v['category'] == 'model'),
            'js_files': sum(1 for v in file_info.values() if v['category'] == 'javascript'),
            'tables': len(all_tables),
            'ajax_calls': len(ajax_flows),
        }
    }


def generate_html(data: dict) -> str:
    """Generate interactive mind map HTML using Canvas (not SVG) for performance."""
    stats = data['stats']
    gen = datetime.fromisoformat(data['generated_at']).strftime('%B %d, %Y')
    data_json = json.dumps(data, default=str)
    
    return f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LCA Architecture Mind Map</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {{
    --bg: #0a0e17; --bg2: #111827; --bg3: #1a2234; --bg4: #0d1321;
    --border: #1e2d45; --text: #e2e8f0; --text2: #94a3b8; --text3: #64748b;
    --blue: #3b82f6; --cyan: #06b6d4; --green: #10b981; --yellow: #f59e0b;
    --red: #ef4444; --purple: #8b5cf6; --orange: #f97316; --pink: #ec4899;
    --teal: #14b8a6;
}}
* {{ margin:0; padding:0; box-sizing:border-box; }}
body {{ font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); height:100vh; overflow:hidden; }}

.app {{ display:grid; grid-template-columns:1fr 320px; grid-template-rows:56px 1fr; height:100vh; }}

.topbar {{ grid-column:1/-1; background:var(--bg2); border-bottom:1px solid var(--border); display:flex; align-items:center; padding:0 20px; gap:16px; z-index:10; }}
.topbar-brand {{ font-weight:800; font-size:16px; display:flex; align-items:center; gap:8px; }}
.topbar-brand span {{ background:linear-gradient(135deg,var(--orange),var(--yellow)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }}
.topbar-divider {{ width:1px; height:24px; background:var(--border); }}
.search-box {{ background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; width:240px; outline:none; }}
.search-box:focus {{ border-color:var(--blue); }}
.topbar-stats {{ margin-left:auto; display:flex; gap:16px; font-size:12px; color:var(--text3); }}
.topbar-stats strong {{ color:var(--text2); font-weight:600; }}
.layer-pills {{ display:flex; gap:6px; }}
.layer-pill {{ padding:4px 12px; border-radius:99px; font-size:11px; font-weight:600; cursor:pointer; border:1px solid var(--border); background:var(--bg3); color:var(--text2); transition:all 0.15s; }}
.layer-pill.active {{ border-color:currentColor; }}
.layer-pill[data-layer="js"] {{ color:var(--yellow); }}
.layer-pill[data-layer="controller"] {{ color:var(--blue); }}
.layer-pill[data-layer="model"] {{ color:var(--purple); }}
.layer-pill[data-layer="table"] {{ color:var(--teal); }}

.canvas-wrap {{ position:relative; overflow:hidden; }}

.detail {{ background:var(--bg2); border-left:1px solid var(--border); overflow-y:auto; padding:0; }}
.detail::-webkit-scrollbar {{ width:4px; }} .detail::-webkit-scrollbar-thumb {{ background:var(--border); border-radius:2px; }}
.detail-header {{ padding:16px; border-bottom:1px solid var(--border); position:sticky; top:0; background:var(--bg2); z-index:5; }}
.detail-header h3 {{ font-size:14px; font-weight:700; font-family:'JetBrains Mono'; word-break:break-all; }}
.detail-badge {{ display:inline-block; padding:2px 8px; border-radius:99px; font-size:10px; font-weight:600; margin-top:6px; }}
.detail-meta {{ display:grid; grid-template-columns:1fr 1fr; gap:8px; padding:12px 16px; border-bottom:1px solid var(--border); }}
.meta-item {{ background:var(--bg3); border-radius:6px; padding:8px 10px; text-align:center; }}
.meta-item .val {{ font-size:18px; font-weight:800; font-family:'JetBrains Mono'; }}
.meta-item .lbl {{ font-size:10px; color:var(--text3); text-transform:uppercase; }}
.detail-section {{ padding:12px 16px; }}
.detail-section h4 {{ font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text3); margin-bottom:8px; }}
.conn-item {{ padding:5px 8px; border-radius:5px; font-size:12px; cursor:pointer; display:flex; align-items:center; gap:6px; transition:background 0.1s; }}
.conn-item:hover {{ background:rgba(59,130,246,0.08); }}
.conn-dot {{ width:8px; height:8px; border-radius:50%; flex-shrink:0; }}
.conn-name {{ font-family:'JetBrains Mono'; font-size:11px; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }}
.conn-count {{ font-family:'JetBrains Mono'; font-size:10px; color:var(--text3); }}
.empty-state {{ padding:40px 20px; text-align:center; color:var(--text3); font-size:13px; }}

.legend {{ position:absolute; bottom:16px; left:16px; background:var(--bg2); border:1px solid var(--border); border-radius:10px; padding:12px 16px; display:flex; gap:16px; font-size:11px; z-index:5; }}
.legend-item {{ display:flex; align-items:center; gap:6px; }}
.legend-dot {{ width:10px; height:10px; border-radius:50%; }}

.controls {{ position:absolute; bottom:16px; right:340px; display:flex; gap:6px; z-index:5; }}
.ctrl-btn {{ width:36px; height:36px; border-radius:8px; border:1px solid var(--border); background:var(--bg2); color:var(--text2); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:16px; transition:all 0.15s; }}
.ctrl-btn:hover {{ background:var(--bg3); color:var(--text); border-color:var(--blue); }}

.tooltip {{ position:absolute; background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:10px 14px; font-size:12px; pointer-events:none; display:none; z-index:100; box-shadow:0 8px 32px rgba(0,0,0,0.4); max-width:250px; }}
.tooltip .tt-name {{ font-weight:700; font-family:'JetBrains Mono'; font-size:13px; margin-bottom:4px; }}
.tooltip .tt-row {{ display:flex; justify-content:space-between; gap:12px; color:var(--text2); }}
.tooltip .tt-val {{ font-weight:600; color:var(--text); font-family:'JetBrains Mono'; }}
</style>
</head>
<body>

<div class="app">
    <header class="topbar">
        <div class="topbar-brand">&#9670; <span>LCA Mind Map</span></div>
        <div class="topbar-divider"></div>
        <input type="text" class="search-box" id="search" placeholder="Search... (Ctrl+K)">
        <div class="topbar-divider"></div>
        <div class="layer-pills">
            <div class="layer-pill active" data-layer="js" onclick="toggleLayer('js')">JS ({stats['js_files']})</div>
            <div class="layer-pill active" data-layer="controller" onclick="toggleLayer('controller')">Controllers ({stats['controllers']})</div>
            <div class="layer-pill active" data-layer="model" onclick="toggleLayer('model')">Models ({stats['models']})</div>
            <div class="layer-pill active" data-layer="table" onclick="toggleLayer('table')">Tables ({stats['tables']})</div>
        </div>
        <div class="topbar-stats">
            <span><strong>{stats['ajax_calls']:,}</strong> AJAX calls</span>
            <span><strong>{stats['total_files']}</strong> files</span>
        </div>
    </header>

    <div class="canvas-wrap" id="canvasWrap">
        <canvas id="c"></canvas>
        <div class="tooltip" id="tooltip"></div>
        <div class="legend">
            <div class="legend-item"><div class="legend-dot" style="background:var(--yellow)"></div>JavaScript</div>
            <div class="legend-item"><div class="legend-dot" style="background:var(--blue)"></div>Controller</div>
            <div class="legend-item"><div class="legend-dot" style="background:var(--purple)"></div>Model</div>
            <div class="legend-item"><div class="legend-dot" style="background:var(--teal)"></div>Table</div>
        </div>
        <div class="controls">
            <button class="ctrl-btn" onclick="zoomIn()" title="Zoom In">+</button>
            <button class="ctrl-btn" onclick="zoomOut()" title="Zoom Out">&#8722;</button>
            <button class="ctrl-btn" onclick="resetView()" title="Reset">&#10226;</button>
        </div>
    </div>

    <aside class="detail" id="detail">
        <div class="empty-state">
            <p>&#128205; Click any node to explore<br>its connections across layers</p>
        </div>
    </aside>
</div>

<script>
const DATA = {data_json};
const COLORS = {{ js:'#f59e0b', controller:'#3b82f6', model:'#8b5cf6', table:'#14b8a6', other:'#64748b' }};
const activeLayers = new Set(['js','controller','model','table']);

// ── Build nodes & links ──
const nodes = [], links = [], nodeMap = {{}};

(function buildGraph() {{
    Object.values(DATA.controllers).forEach(c => {{
        const id = 'ctrl:' + c.name;
        nodeMap[id] = {{ id, name:c.name, type:'controller', funcs:c.funcs, loc:c.loc, data:c }};
        nodes.push(nodeMap[id]);

        // Models
        c.models.forEach(m => {{
            const mid = 'model:' + m.name;
            if (!nodeMap[mid]) {{
                const mn = {{ id: mid, name: m.name, type: 'model', funcs: m.funcs, loc: m.loc, data: m }};
                nodes.push(mn);
                nodeMap[mid] = mn;
            }}
            links.push({{ source: id, target: mid, type: 'ctrl_model' }});

            // Tables
            (m.tables || []).forEach(t => {{
                const tid = 'table:' + t;
                if (!nodeMap[tid]) {{
                    const tn = {{ id: tid, name: t, type: 'table', funcs: 0, loc: 0, data: {{ name: t }} }};
                    nodes.push(tn);
                    nodeMap[tid] = tn;
                }}
                links.push({{ source: mid, target: tid, type: 'model_table' }});
            }});
        }});

        // JS callers
        (c.js_callers || []).forEach(jsPath => {{
            const jsInfo = DATA.js_files[jsPath];
            if (!jsInfo) return;
            const jid = 'js:' + jsInfo.name;
            if (!nodeMap[jid]) {{
                const jn = {{ id: jid, name: jsInfo.name, type: 'js', funcs: jsInfo.funcs, loc: jsInfo.loc, data: jsInfo }};
                nodes.push(jn);
                nodeMap[jid] = jn;
            }}
            links.push({{ source: jid, target: id, type: 'js_ctrl' }});
        }});
    }});

    // Standalone models
    Object.values(DATA.standalone_models).forEach(m => {{
        const mid = 'model:' + m.name;
        if (!nodeMap[mid]) {{
            const mn = {{ id: mid, name: m.name, type: 'model', funcs: m.funcs, loc: m.loc, data: m }};
            nodes.push(mn);
            nodeMap[mid] = mn;
        }}
        (m.tables || []).forEach(t => {{
            const tid = 'table:' + t;
            if (!nodeMap[tid]) {{
                const tn = {{ id: tid, name: t, type: 'table', funcs: 0, loc: 0, data: {{ name: t }} }};
                nodes.push(tn);
                nodeMap[tid] = tn;
            }}
            links.push({{ source: mid, target: tid, type: 'model_table' }});
        }});
    }})();

// ── Pre-computed layered layout (no simulation needed) ──
const layerY = {{ js:-300, controller:-80, model:120, table:350 }};
const byType = {{ js:[], controller:[], model:[], table:[] }};
nodes.forEach(n => {{ n.radius = Math.max(4, Math.min(22, Math.sqrt(n.funcs||1)*2)); if (byType[n.type]) byType[n.type].push(n); }});

Object.entries(byType).forEach(([type, list]) => {{
    list.sort((a,b) => (b.funcs||0) - (a.funcs||0));
    const baseY = layerY[type] || 0;
    const spread = Math.max(800, list.length * 28);
    list.forEach((n, i) => {{
        n.x = (i - list.length/2) * (spread / list.length);
        n.y = baseY + (Math.random()-0.5) * 60;
    }});
}});

// Quick spring relaxation (runs once at startup, not per-frame)
for (let iter = 0; iter < 80; iter++) {{
    const alpha = 1 - iter/80;
    links.forEach(l => {{
        const a = nodeMap[l.source], b = nodeMap[l.target];
        if (!a || !b) return;
        let dx = b.x-a.x, dy = b.y-a.y;
        let d = Math.sqrt(dx*dx+dy*dy) || 1;
        let f = (d-100)*0.002*alpha;
        a.x += dx/d*f; a.y += dy/d*f;
        b.x -= dx/d*f; b.y -= dy/d*f;
    }});
    nodes.forEach(n => {{
        const ty = layerY[n.type]||0;
        n.y += (ty - n.y) * 0.05 * alpha;
    }});
    Object.values(byType).forEach(list => {{
        for (let i=0; i<list.length; i++) {{
            for (let j=i+1; j<Math.min(i+8, list.length); j++) {{
                const a=list[i], b=list[j];
                let dx=b.x-a.x, dy=b.y-a.y;
                let d=Math.sqrt(dx*dx+dy*dy)||1;
                if (d < 40) {{
                    let f = (40-d)*0.3*alpha;
                    a.x -= dx/d*f; a.y -= dy/d*f;
                    b.x += dx/d*f; b.y += dy/d*f;
                }}
            }}
        }}
    }});
}}

// ── Canvas rendering ──
const canvas = document.getElementById('c');
const ctx = canvas.getContext('2d');
const wrap = document.getElementById('canvasWrap');
let camX=0, camY=0, camZ=0.7;
let selectedNode=null, hoveredNode=null, searchTerm='';

function resize() {{
    const dpr = window.devicePixelRatio||1;
    canvas.width = wrap.clientWidth*dpr;
    canvas.height = wrap.clientHeight*dpr;
    canvas.style.width = wrap.clientWidth+'px';
    canvas.style.height = wrap.clientHeight+'px';
    camX = wrap.clientWidth/2;
    camY = wrap.clientHeight/2;
}}
resize();
window.addEventListener('resize', resize);

function s2w(sx,sy) {{ return {{ x:(sx-camX)/camZ, y:(sy-camY)/camZ }}; }}
function nodeAt(wx,wy) {{
    for (let i=nodes.length-1;i>=0;i--) {{
        const n=nodes[i]; if (!activeLayers.has(n.type)) continue;
        if (Math.hypot(n.x-wx, n.y-wy) < n.radius+3) return n;
    }}
    return null;
}}

function render() {{
    const dpr = window.devicePixelRatio||1;
    ctx.clearRect(0,0,canvas.width,canvas.height);
    ctx.save();
    ctx.scale(dpr,dpr);
    ctx.translate(camX,camY);
    ctx.scale(camZ,camZ);

    // Layer labels
    ctx.font = '600 11px Inter';
    ctx.textAlign = 'left';
    const labels = [['js','JavaScript Layer',-300],['controller','Controller Layer',-80],['model','Model Layer',120],['table','Database Tables',350]];
    labels.forEach(([type,label,y]) => {{
        if (!activeLayers.has(type)) return;
        ctx.fillStyle = COLORS[type]+'40';
        ctx.fillText(label, -500, y-20);
        ctx.strokeStyle = COLORS[type]+'15';
        ctx.lineWidth = 0.5;
        ctx.beginPath(); ctx.moveTo(-600,y); ctx.lineTo(600,y); ctx.stroke();
    }});

    // Links
    links.forEach(l => {{
        const a=nodeMap[l.source], b=nodeMap[l.target];
        if (!a||!b||!activeLayers.has(a.type)||!activeLayers.has(b.type)) return;
        const hl = selectedNode && (l.source===selectedNode.id||l.target===selectedNode.id);
        ctx.beginPath(); ctx.moveTo(a.x,a.y); ctx.lineTo(b.x,b.y);
        const tc = {{js_ctrl:'#f59e0b',ctrl_model:'#3b82f6',model_table:'#14b8a6'}};
        ctx.strokeStyle = hl ? (tc[l.type]||'#3b82f6') : '#1e2d45';
        ctx.lineWidth = hl ? 1.5 : 0.3;
        ctx.globalAlpha = hl ? 0.6 : 0.12;
        ctx.stroke();
        ctx.globalAlpha = 1;
    }});

    // Nodes
    nodes.forEach(n => {{
        if (!activeLayers.has(n.type)) return;
        const sel = selectedNode===n;
        const conn = selectedNode && links.some(l=>(l.source===selectedNode.id&&l.target===n.id)||(l.target===selectedNode.id&&l.source===n.id));
        const dim = selectedNode && !sel && !conn;
        const sm = searchTerm && n.name.toLowerCase().includes(searchTerm);

        ctx.beginPath();
        ctx.arc(n.x,n.y, sm?n.radius+3:n.radius, 0, Math.PI*2);
        ctx.fillStyle = dim ? COLORS[n.type]+'18' : COLORS[n.type]+(sel?'cc':'70');
        ctx.fill();
        if (sel||sm||n===hoveredNode) {{
            ctx.strokeStyle = COLORS[n.type];
            ctx.lineWidth = sel?3:2;
            ctx.stroke();
        }}

        // Label
        if ((n.radius>6||sel||conn||sm) && camZ>0.35) {{
            ctx.font = (sel?'600 ':'400 ') + Math.max(8,10)+'px Inter';
            ctx.textAlign = 'center';
            ctx.fillStyle = dim ? '#64748b20' : '#94a3b8';
            const lbl = n.name.replace('.php','').replace('.js','');
            ctx.fillText(lbl.length>25?lbl.slice(0,22)+'...':lbl, n.x, n.y+n.radius+14);
        }}
    }});

    ctx.restore();
    requestAnimationFrame(render);
}}
render();

// ── Mouse events ──
let dragging=false, dragN=null, lx,ly;
canvas.addEventListener('mousedown', function(e) {{
    const w = s2w(e.offsetX,e.offsetY);
    const hit = nodeAt(w.x,w.y);
    if (hit) {{ dragN=hit; selectNode(hit); }} else {{ dragging=true; }}
    lx=e.offsetX; ly=e.offsetY;
}});
canvas.addEventListener('mousemove', function(e) {{
    if (dragging) {{ camX+=e.offsetX-lx; camY+=e.offsetY-ly; }}
    else if (dragN) {{ const d=s2w(e.offsetX,e.offsetY); dragN.x=d.x; dragN.y=d.y; }}
    else {{
        const w=s2w(e.offsetX,e.offsetY);
        const n=nodeAt(w.x,w.y);
        hoveredNode=n;
        canvas.style.cursor=n?'pointer':'grab';
        const tt=document.getElementById('tooltip');
        if (n) {{
            tt.style.display='block';
            tt.style.left=(e.offsetX+16)+'px';
            tt.style.top=(e.offsetY-10)+'px';
            tt.innerHTML='<div class="tt-name" style="color:'+COLORS[n.type]+'">'+n.name+'</div><div class="tt-row"><span>Functions</span><span class="tt-val">'+(n.funcs||0)+'</span></div><div class="tt-row"><span>Type</span><span class="tt-val">'+n.type+'</span></div><div class="tt-row"><span>LOC</span><span class="tt-val">'+(n.loc||0).toLocaleString()+'</span></div>';
        }} else {{ tt.style.display='none'; }}
    }}
    lx=e.offsetX; ly=e.offsetY;
}});
canvas.addEventListener('mouseup', function(){{ dragging=false; dragN=null; }});
canvas.addEventListener('wheel', function(e) {{
    e.preventDefault();
    var f=e.deltaY>0?0.9:1.1;
    camX=e.offsetX-(e.offsetX-camX)*f;
    camY=e.offsetY-(e.offsetY-camY)*f;
    camZ=Math.max(0.08,Math.min(4,camZ*f));
}}, {{passive:false}});

function zoomIn() {{ camZ=Math.min(4,camZ*1.3); }}
function zoomOut() {{ camZ=Math.max(0.08,camZ*0.7); }}
function resetView() {{ camX=wrap.clientWidth/2; camY=wrap.clientHeight/2; camZ=0.7; selectedNode=null; document.getElementById('detail').innerHTML='<div class="empty-state"><p>Click any node to explore</p></div>'; }}

// ── Selection & Detail ──
function selectNode(n) {{
    selectedNode=n;
    var panel=document.getElementById('detail');
    var bc={{js:'background:rgba(245,158,11,0.15);color:#f59e0b',controller:'background:rgba(59,130,246,0.15);color:#3b82f6',model:'background:rgba(139,92,246,0.15);color:#8b5cf6',table:'background:rgba(20,184,166,0.15);color:#14b8a6'}};
    var inc=[],out=[];
    links.forEach(function(l){{
        if (l.target===n.id&&nodeMap[l.source]&&activeLayers.has(nodeMap[l.source].type)) inc.push(nodeMap[l.source]);
        if (l.source===n.id&&nodeMap[l.target]&&activeLayers.has(nodeMap[l.target].type)) out.push(nodeMap[l.target]);
    }});
    var html='<div class="detail-header"><h3 style="color:'+COLORS[n.type]+'">'+n.name+'</h3><span class="detail-badge" style="'+(bc[n.type]||'')+'">'+n.type+'</span></div>';
    html+='<div class="detail-meta"><div class="meta-item"><div class="val" style="color:var(--cyan)">'+(n.funcs||0)+'</div><div class="lbl">Functions</div></div><div class="meta-item"><div class="val" style="color:var(--orange)">'+(n.loc||0).toLocaleString()+'</div><div class="lbl">LOC</div></div><div class="meta-item"><div class="val" style="color:var(--blue)">'+inc.length+'</div><div class="lbl">Incoming</div></div><div class="meta-item"><div class="val" style="color:var(--purple)">'+out.length+'</div><div class="lbl">Outgoing</div></div></div>';
    if (inc.length) {{
        html+='<div class="detail-section"><h4>Called By ('+inc.length+')</h4>';
        inc.forEach(function(c){{ html+='<div class="conn-item" onclick="jumpTo(\''+c.id+'\')"><div class="conn-dot" style="background:'+COLORS[c.type]+'"></div><span class="conn-name">'+c.name+'</span><span class="conn-count">'+c.type+'</span></div>'; }});
        html+='</div>';
    }}
    if (out.length) {{
        html+='<div class="detail-section"><h4>Depends On ('+out.length+')</h4>';
        out.forEach(function(c){{ html+='<div class="conn-item" onclick="jumpTo(\''+c.id+'\')"><div class="conn-dot" style="background:'+COLORS[c.type]+'"></div><span class="conn-name">'+c.name+'</span><span class="conn-count">'+c.type+'</span></div>'; }});
        html+='</div>';
    }}
    panel.innerHTML=html;
}}
function jumpTo(id) {{
    var n=nodeMap[id]; if(!n) return;
    selectNode(n);
    camX=wrap.clientWidth/2 - n.x*camZ;
    camY=wrap.clientHeight/2 - n.y*camZ;
}}

// ── Layer Toggle ──
function toggleLayer(layer) {{
    var p=document.querySelector('[data-layer="'+layer+'"]');
    if (activeLayers.has(layer)) {{ activeLayers.delete(layer); p.classList.remove('active'); }}
    else {{ activeLayers.add(layer); p.classList.add('active'); }}
}}

// ── Search ──
var searchEl=document.getElementById('search');
searchEl.addEventListener('input', function(e) {{
    searchTerm=e.target.value.toLowerCase().trim();
    if (searchTerm) {{
        var m=nodes.find(function(n){{ return activeLayers.has(n.type)&&n.name.toLowerCase().includes(searchTerm); }});
        if (m) jumpTo(m.id);
    }}
}});
document.addEventListener('keydown', function(e) {{
    if ((e.ctrlKey||e.metaKey)&&e.key==='k') {{ e.preventDefault(); searchEl.focus(); searchEl.select(); }}
    if (e.key==='Escape') {{ searchEl.blur(); searchTerm=''; searchEl.value=''; resetView(); }}
}});
</script>
</body>
</html>"""


def main():
    parser = argparse.ArgumentParser(description="Generate LCA Architecture Mind Map")
    parser.add_argument("--db", default="backend/lca_core/admin_app_index.db", help="Path to SymbolDB")
    parser.add_argument("--output", "-o", default="docs/mindmap.html", help="Output HTML file")
    args = parser.parse_args()

    db_path = Path(args.db)
    if not db_path.exists():
        print(f"Error: {db_path} not found. Run: lca index first.")
        sys.exit(1)

    print(f"Reading: {db_path}")
    db = SymbolDB(db_path)
    data = extract_mindmap_data(db)
    db.close()

    html_content = generate_html(data)
    out = Path(args.output)
    out.write_text(html_content, encoding='utf-8')
    print(f"Mind map saved: {out} ({out.stat().st_size / 1024:.0f} KB)")
    print(f"  {data['stats']['controllers']} controllers")
    print(f"  {data['stats']['models']} models")
    print(f"  {data['stats']['js_files']} JS files")
    print(f"  {data['stats']['tables']} tables")
    print(f"  {data['stats']['ajax_calls']} AJAX calls")


if __name__ == "__main__":
    main()
