"""Extract graph data from SymbolDB for interactive visualization.
Handles unresolved target_file by looking up target function definitions."""
import sys, json
sys.path.insert(0, 'backend/lca_core')
from lca.storage.symbol_db import SymbolDB
from pathlib import Path
from collections import defaultdict

db = SymbolDB(Path('backend/lca_core/admin_app_index.db'))
conn = db._get_conn()

# --- Build symbol->file lookup ---
print("Building symbol lookup...")
symbol_file = {}  # function_name -> file_path
for row in conn.execute('SELECT name, file_path FROM symbols'):
    symbol_file[row[0]] = row[1].replace('\\', '/')

# --- NODES: files with aggregated metrics ---
print("Building nodes...")
files = {}
for row in conn.execute('''
    SELECT f.path, f.language, 
           COUNT(s.id) as func_count,
           COALESCE(SUM(m.lines_of_code),0) as total_loc,
           COALESCE(MAX(m.lines_of_code),0) as max_loc,
           COALESCE(AVG(m.lines_of_code),0) as avg_loc
    FROM files f
    LEFT JOIN symbols s ON s.file_id = f.id
    LEFT JOIN metrics m ON m.symbol_id = s.id
    GROUP BY f.id
    ORDER BY func_count DESC
'''):
    p = dict(row)
    path = p['path'].replace('\\', '/')
    cat = 'other'
    if '/controllers/' in path: cat = 'controller'
    elif '/models/' in path: cat = 'model'
    elif '/views/' in path: cat = 'view'
    elif '/helpers/' in path: cat = 'helper'
    elif path.endswith('.js'): cat = 'javascript'
    elif '/config/' in path: cat = 'config'
    elif '/libraries/' in path: cat = 'library'
    
    short = path.split('/')[-1] if '/' in path else path
    files[path] = {
        'id': path,
        'label': short,
        'language': p['language'],
        'category': cat,
        'functions': p['func_count'],
        'total_loc': p['total_loc'],
        'max_loc': p['max_loc'],
        'avg_loc': round(p['avg_loc']),
    }

# --- EDGES: build from refs by resolving source+target to files ---
print("Building edges...")
edge_map = defaultdict(lambda: {'weight': 0, 'types': defaultdict(int)})

# For model_call / load_view / load_model — target_name often contains the class name
# For regular calls — look up target in symbol table
for row in conn.execute('''
    SELECT source_name, target_name, call_type, file_path, target_file
    FROM refs
    WHERE call_type NOT IN ('db_query')
'''):
    r = dict(row)
    src_file = r['file_path'].replace('\\', '/') if r['file_path'] else None
    tgt_file = r['target_file'].replace('\\', '/') if r['target_file'] else None
    
    # Resolve source file from source function name if empty
    if not src_file and r['source_name'] in symbol_file:
        src_file = symbol_file[r['source_name']]
    
    # Resolve target file from target function name
    if not tgt_file and r['target_name'] in symbol_file:
        tgt_file = symbol_file[r['target_name']]
    
    # Skip if we can't resolve either end, or if same file
    if not src_file or not tgt_file or src_file == tgt_file:
        continue
    # Skip PHP builtins
    if src_file not in files or tgt_file not in files:
        continue
    
    key = (src_file, tgt_file)
    edge_map[key]['source'] = src_file
    edge_map[key]['target'] = tgt_file
    edge_map[key]['weight'] += 1
    edge_map[key]['types'][r['call_type']] += 1

edge_list = []
for key, e in edge_map.items():
    e['types'] = dict(e['types'])
    edge_list.append(e)

# --- Function-level data for detail panel ---
print("Collecting function details...")
func_by_file = defaultdict(list)
for row in conn.execute('''
    SELECT s.name, s.file_path, s.line, s.language,
           m.lines_of_code, m.fan_in, m.fan_out, m.param_count, m.coupling_score
    FROM symbols s
    LEFT JOIN metrics m ON m.symbol_id = s.id
    ORDER BY COALESCE(m.lines_of_code, 0) DESC
'''):
    r = dict(row)
    fp = r['file_path'].replace('\\', '/')
    func_by_file[fp].append({
        'name': r['name'],
        'line': r['line'],
        'loc': r['lines_of_code'] or 0,
        'fan_in': r['fan_in'] or 0,
        'fan_out': r['fan_out'] or 0,
        'params': r['param_count'] or 0,
        'coupling': r['coupling_score'] or 0,
    })

db.close()

# Filter: keep only top functions per file (max 20) to reduce JSON size
for fp in func_by_file:
    func_by_file[fp] = func_by_file[fp][:20]

data = {
    'nodes': list(files.values()),
    'edges': edge_list,
    'functions': dict(func_by_file),
}
print(f"Nodes: {len(data['nodes'])}, Edges: {len(data['edges'])}, Function files: {len(data['functions'])}")

with open('docs/graph_data.json', 'w', encoding='utf-8') as f:
    json.dump(data, f)

# Size
size_mb = Path('docs/graph_data.json').stat().st_size / 1024 / 1024
print(f"Saved: docs/graph_data.json ({size_mb:.1f} MB)")
