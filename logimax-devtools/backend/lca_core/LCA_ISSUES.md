# LCA Issues & Improvement Backlog

> Filed: 2026-06-07 | Source: Real-world audit of `get_section_wise_stock_inout_details()` (1,038-line PHP function)  
> Environment: Windows 11, Python 3.14.4, uv, PowerShell terminal, cp1252 encoding

---

## 🔴 BUG — Unicode Crash on Windows (P0)

**File:** `lca/parsers/crossref.py` L14  
**Symptom:** Running `lca --help` or `lca crossref` crashes with:
```
UnicodeEncodeError: 'charmap' codec can't encode character '\u2192' in position 10
```

**Root Cause:** The `APICall` docstring contains `JS→PHP` (U+2192 RIGHT ARROW). When Rich renders help text on a Windows terminal using cp1252 encoding, this character can't be encoded.

**Also affects:** `lca/cli.py` crossref command help text: `"Extract JS→PHP API calls (cross-language dependencies)."`

**Fix options:**
1. Replace `→` with `->` in all user-facing strings
2. Set `PYTHONIOENCODING=utf-8` in the CLI entry point
3. Use Rich's `Console(force_terminal=True)` with UTF-8

**Workaround:** Run with `$env:PYTHONIOENCODING="utf-8"; $env:PYTHONUTF8="1"` before `uv run lca`

---

## 🟠 BUG — Reaper False Positives on Single-Layer Index (P1)

**File:** `lca/tools/reaper.py` L22-82  
**Symptom:** Running `lca reaper` on a model-only index reports ALL 407 methods as dead code (High confidence), because callers exist in controller files that weren't indexed.

**Root Cause:** Reaper counts incoming calls from the loaded index only. If you index `ret_reports_model.php` alone, no controller calls appear → all methods look dead.

**Evidence:**
```
lca reaper -i ret_reports_fresh_index.json
→ "Reaper Found 407 Zombie Functions" (all model_method, all High confidence)
```

But `lca impact` with the master_index correctly finds 2 callers for the same function.

**Fix:** 
1. **Multi-index support:** Accept `--index` flag multiple times or `--merge-indexes` to load model + controller + JS together
2. **Warning banner:** When all functions in an index are "dead", print a warning: "⚠ This looks like a single-file index. Reaper works best with a full-codebase index."
3. **Controller-aware heuristic:** If a model method's name matches a common pattern (e.g., `get_*`, `save_*`), mark as "Low" confidence when no callers found in a model-only index

---

## 🟠 FEATURE GAP — Metrics Not Surfaced in CLI (P1)

**File:** `lca/analysis/metrics.py` (computes), `lca/cli.py` (doesn't display)  
**Symptom:** LCA computes cyclomatic complexity, cognitive complexity, max nesting depth, and LOC per function during indexing, but these metrics are NOT shown by:
- `lca search` — only shows name, file, line, language
- `lca impact` — only shows callers/callees
- No dedicated `lca metrics <function>` command exists

**Evidence:** The `FunctionDef` model has `metrics: dict | None = None` and `compute_metrics()` populates it during indexing, but no CLI command reads it.

**Fix:**
1. Add `--metrics` flag to `lca search` to show complexity columns
2. Add new `lca quality <file|function>` command that shows:
   - Top 20 most complex functions (by cyclomatic + cognitive)
   - Nesting depth hotspots
   - Functions exceeding thresholds (e.g., CC > 10, LOC > 200)
3. Include metrics in `lca impact` output panel

---

## 🟠 FEATURE GAP — No SQL Security Analysis (P1)

**File:** `lca/parsers/php.py` L258-429, `lca/parsers/sql.py`  
**Symptom:** LCA parses `$this->db->query("SQL...")` and extracts table references, but does NOT detect:
- SQL injection patterns: `"... WHERE id = " . $data['user_input']`  
- Non-parameterized queries
- Missing `escape_str()` calls

**Evidence:** The stock report function has **64 raw `$data[]` concatenations** into SQL strings. LCA's PHP parser sees the `query()` call and extracts table names, but doesn't flag the injection risk.

**Fix:**
1. Extend `_extract_tables_from_raw_sql()` to also detect variable interpolation patterns
2. New call_type: `"sql_injection_risk"` when a `query()` or raw SQL contains `$data[`, `$_POST[`, `$_GET[` etc.
3. Add a `lca security <file>` command that scans for injection patterns, XSS, CSRF bypass

---

## 🟡 BUG — Master Index Goes Stale (P2)

**File:** `lca/core/indexer.py`, `lca/cli.py`  
**Symptom:** The `master_index.json` (pre-built full codebase index) shows old line numbers after file modifications. Running `lca impact` with master_index showed L21821 instead of actual L23136.

**Root Cause:** There's an `lca update` command for incremental reindex (using git diff), but:
1. It's not run automatically after code changes
2. No warning is shown when the index is stale (modification timestamps not checked)
3. `lca watch` exists but isn't integrated into dev workflow

**Fix:**
1. Add `--check-staleness` flag to `lca search`/`lca impact` that compares file mtime with `indexed_at` timestamp
2. Print a warning: "⚠ Index is X hours old. Source file modified since last index. Run `lca update` for accurate results."
3. Auto-merge fresh per-file indexes into master_index when running `lca index` on a single file

---

## 🟡 FEATURE GAP — No `lca quality` / Code Health Command (P2)

**Symptom:** No way to ask "show me the worst functions in this file" or "what's the complexity score of this function?"

**Desired behavior:**
```bash
# Show top 10 most complex functions in a file
lca quality admin/application/models/ret_reports_model.php --top 10

# Output:
# ┌──────────────────────────────┬──────┬─────────┬───────────┬─────────┬──────┐
# │ Function                     │ LOC  │ CC      │ Cognitive │ Nesting │ Risk │
# ├──────────────────────────────┼──────┼─────────┼───────────┼─────────┼──────┤
# │ get_section_wise_stock_in... │ 1038 │ 87      │ 342       │ 6       │ 🔴   │
# │ get_stock_details            │ 456  │ 42      │ 180       │ 5       │ 🟠   │
# └──────────────────────────────┴──────┴─────────┴───────────┴─────────┴──────┘
```

**Fix:** Create `lca/cli.py::quality()` command that reads metrics from index and ranks functions.

---

## 🟡 FEATURE GAP — Impact Picks First Match, Not Exact (P2)

**File:** `lca/core/impact.py` L37-56  
**Symptom:** When searching for `get_section_wise_stock_inout_details`, impact analysis picks L22286 (first partial match) instead of L23136 (the actual active function).

**Root Cause:** The `analyze()` method does exact match first (`if term in self.func_map`), but `self.func_map` uses full qualified names like `Ret_reports_model::get_section_wise_stock_inout_details`. User passes unqualified name → exact match fails → falls through to partial search → picks first hit.

**Fix:**
1. Try exact match on both qualified AND unqualified names
2. If multiple matches, show disambiguation prompt instead of silently picking first
3. Accept `ClassName::method` syntax in CLI

---

## 🔵 FEATURE GAP — No Cross-Layer Reaper (P3)

**Symptom:** Reaper cannot detect dead code across PHP model + PHP controller + JS layers simultaneously.

**Desired:**
```bash
lca reaper --index model_index.json --index controller_index.json --index js_index.json
```

This would correctly identify that a model method with 0 controller callers AND 0 JS references is truly dead.

**Fix:** Allow `Reaper.__init__()` to accept multiple index paths and merge them before scanning.

---

## 🔵 ENHANCEMENT — Incremental Update Should Auto-Detect Changed Files (P3)

**File:** `lca/cli.py` L59-109  
**Symptom:** `lca update` requires `--repo` and `--since` flags. Could auto-detect from `.git` in parent directories.

**Fix:** Default `--repo` to git root of the index file's location. Default `--since` to the `indexed_at` timestamp.

---

## 🔵 ENHANCEMENT — Crossref Parser Needs Base URL Detection (P3)

**File:** `lca/parsers/crossref.py` L32-50  
**Symptom:** The URL pattern only matches `index.php/controller/method`. In real Logimax JS code, URLs are constructed with string concatenation:
```javascript
url: base_url + "index.php/admin_ret_reports/section_stock_inout/ajax?nocache=" + ...
```
The `base_url +` prefix breaks the regex match in some edge cases.

**Fix:** Pre-process concatenated URL strings to extract the CI path before regex matching.

---

## Summary Table

| # | Type | Priority | Issue | File(s) |
|---|---|---|---|---|
| 1 | 🐛 Bug | P0 | Unicode crash on Windows | `cli.py`, `crossref.py` |
| 2 | 🐛 Bug | P1 | Reaper false positives (single-layer) | `reaper.py` |
| 3 | 📊 Gap | P1 | Metrics computed but not surfaced | `cli.py`, `metrics.py` |
| 4 | 🔒 Gap | P1 | No SQL injection detection | `php.py`, `sql.py` |
| 5 | 🐛 Bug | P2 | Master index staleness | `indexer.py`, `cli.py` |
| 6 | 📊 Gap | P2 | No `lca quality` command | `cli.py` |
| 7 | 🐛 Bug | P2 | Impact picks wrong match | `impact.py` |
| 8 | 📊 Gap | P3 | No cross-layer reaper | `reaper.py` |
| 9 | ✨ Enh | P3 | Auto-detect changed files | `cli.py` |
| 10 | ✨ Enh | P3 | Better crossref URL parsing | `crossref.py` |

---

## Fresh Re-Index Results (2026-06-07)

Full codebase re-index completed:

```
Indexed admin_app: 1044 parsed, 0 cached, 0 errors
→ 7,555 functions, 74,878 call relationships (40.7s)
→ Output: admin_app_index_fresh.json
```

### Confirmed Findings from Fresh Index

Using the qualified name `Ret_reports_model::get_section_wise_stock_inout_details`:

**3 callers found** (stale master_index only showed 2):
1. `Admin_ret_reports::section_stock_inout` — L7579 (AJAX report)
2. `Admin_ret_reports::stock_details_print` — L7655 (PDF print) ← **MISSED by stale index**
3. `Admin_ret_reports::ho_daily_stock_book` — L9021 (HO daily book)

**Risk level upgraded from Low → HIGH** with the fresh full index.

This confirms that Issues #5 (staleness) and #7 (wrong match) have real production impact — an optimization change that only updates 2 of 3 callers would leave the PDF print function running the slow query.

