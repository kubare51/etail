"""Merge LCA sub-indexes into master_index.json."""
import json
import sys
import time

def merge(app_path, js_path, out_path):
    start = time.time()
    with open(app_path, "r") as f:
        app = json.load(f)
    with open(js_path, "r") as f:
        js = json.load(f)

    merged = {
        "module": "master",
        "version": app.get("version", "2.1.0"),
        "indexed_at": app["indexed_at"],
        "functions": [],
        "calls": [],
        "files": {},
        "ui_matches": {},
        "level2": {},
    }
    # functions and calls are lists — concatenate
    merged["functions"] = app.get("functions", []) + js.get("functions", [])
    merged["calls"] = app.get("calls", []) + js.get("calls", [])
    # files, ui_matches, level2 are dicts — merge
    for key in ("files", "ui_matches", "level2"):
        app_val = app.get(key, {})
        js_val = js.get(key, {})
        if isinstance(app_val, dict):
            merged[key].update(app_val)
        if isinstance(js_val, dict):
            merged[key].update(js_val)

    with open(out_path, "w") as f:
        json.dump(merged, f)
    elapsed = time.time() - start
    fn_count = len(merged["functions"])
    call_count = len(merged["calls"])
    print(f"Merged: {fn_count} functions, {call_count} calls in {elapsed:.1f}s")

if __name__ == "__main__":
    app_idx = sys.argv[1] if len(sys.argv) > 1 else "admin_app_index.json"
    js_idx = sys.argv[2] if len(sys.argv) > 2 else "admin_js_index.json"
    out = sys.argv[3] if len(sys.argv) > 3 else "master_index.json"
    merge(app_idx, js_idx, out)
