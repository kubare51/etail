# LCA Storage Strategy: SQLite vs JSON

## The Mess We Have Today

There are **4 separate storage systems** that all store "the same" data differently:

### Storage System Map

| # | Location | Format | Schema | Size | Used By | BI Data? |
|---|---|---|---|---|---|---|
| 1 | `.lca/index.json` | JSON | ModuleIndex (Pydantic) | 133 MB | MCP `load_index` | ✅ Yes (22K formulas, 82K magic nums) |
| 2 | `.lca/index.db` | SQLite | `symbols`/`refs`/`metrics` | 77 MB | Nobody (orphaned?) | ❌ No formulas/conditionals |
| 3 | `backend/lca_index.db` | SQLite | `functions`/`calls` (legacy) | 92 MB | SQLiteStorage class | ❌ No BI data |
| 4 | Source files on disk | PHP/JS | N/A | N/A | `deep_analyze` (on-demand) | ✅ Full extraction |

### Three Incompatible SQLite Schemas

**Schema A** (`.lca/index.db` — modern, 77 MB):
```sql
symbols(id, name, file_id, file_path, line, end_line, language, class_name, params, visibility)
refs(id, source_name, target_name, line, file_path, call_type, target_file, metadata)
metrics(id, symbol_id, cyclomatic_complexity, cognitive_complexity, lines_of_code, ...)
-- 14 indexes, proper foreign keys
-- NO formulas/conditionals/magic_numbers tables
```

**Schema B** (`backend/lca_index.db` — legacy, 92 MB):
```sql
indexes(name, path, created_at, updated_at)
functions(id, index_name, name, qualified_name, file, line, end_line, class_name)
calls(id, index_name, caller, callee, line)
-- Basic indexes only
-- NO BI data at all
```

**Schema C** (`backend/lca_core/lca/storage/sqlite.py` — SQLiteStorage class):
```python
# Uses Schema B format
# async/aiosqlite based
# NOT used by MCP server (MCP uses json_store.py)
```

### The JSON Path

**MCP Server** uses `json_store.py::load_index()`:
```
.lca/index.json (133 MB) → json.load() → Pydantic ModuleIndex → in-memory queries
```
- Loads entire 133MB into RAM
- Slow initial load (8 seconds)
- Fast subsequent queries (1s)
- **Until today**: dropped formulas/conditionals/magic_numbers during loading (now fixed)

### The CLI Path

**SDLC discover** uses subprocess:
```
python -m lca impact -i .lca/index.json function_name --depth 2
```
- Spawns new process each time
- Loads 133MB JSON from scratch per invocation
- 30-second timeout
- **This is why you disabled it** — too slow

---

## When to Use What

### Decision Matrix

| Question | Use **SQLite** | Use **JSON** |
|---|---|---|
| Query speed matters? | ✅ Indexed queries in <10ms | ❌ Scans entire dataset |
| Data > 10 MB? | ✅ Streams rows, low memory | ❌ Loads everything into RAM |
| Multiple concurrent readers? | ✅ WAL mode handles this | ❌ File lock contention |
| Partial reads needed? | ✅ `WHERE` clause | ❌ All or nothing |
| Need to diff/review changes? | ❌ Binary, not diffable | ✅ Text, git-friendly |
| Cross-tool portability? | ❌ Needs schema docs | ✅ Universal format |
| Human debugging? | ❌ Needs sqlite3 client | ✅ Open in any editor |
| Module-specific extract (< 1 MB)? | ❌ Overkill | ✅ Simple and fast enough |
| BI layer queries? | ✅ Index on function/file columns | ❌ Linear scan of 82K entries |

### The Rule

```
┌─────────────────────────────────────────────────────────────────┐
│  SQLite = QUERY engine (runtime, MCP, agents)                   │
│  JSON   = EXPORT format (sharing, small modules, human review)  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Proposed Unified Architecture

### Single Source of Truth: SQLite

```
.lca/
  lca.db                    ← ONE database, ALL data
    symbols                 ← functions (from Schema A)
    refs                    ← calls (from Schema A)
    metrics                 ← complexity metrics (from Schema A)
    formulas                ← NEW: FormulaExpr data
    accumulators            ← NEW: Accumulator data
    conditionals            ← NEW: ConditionalBranch data
    magic_numbers           ← NEW: MagicNumber data
    files                   ← indexed file metadata
    meta                    ← index metadata (module, version, timestamp)
```

### Export for portability: JSON

```
.lca/
  exports/
    reports_cashflow.json   ← Small module extract for human review
    billing.json            ← Module-specific extract
```

### Who Uses What

| Consumer | Reads | Why |
|---|---|---|
| **MCP Server** (`mcp_server.py`) | `lca.db` (SQLite) | Fast queries, low memory, BI layer included |
| **SDLC discover** (`cli.py`) | Not used (agents query MCP directly) | Avoid subprocess overhead |
| **Human review** | `.lca/exports/*.json` | Readable, diffable |
| **Cross-client sync** | `.lca/exports/*.json` | Portable between repos |
| **Brain auto-refresh** | `lca.db` (SQLite) | Compare timestamps |

### New SQLite Tables for BI

```sql
CREATE TABLE IF NOT EXISTS formulas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    variable TEXT NOT NULL,
    expression TEXT NOT NULL,
    operator TEXT NOT NULL,          -- 'addition', 'sql_aggregate', etc.
    line INTEGER NOT NULL,
    function TEXT NOT NULL,
    file_path TEXT NOT NULL,
    precision TEXT,
    language TEXT DEFAULT 'php',
    FOREIGN KEY (file_path) REFERENCES files(path)
);
CREATE INDEX idx_formulas_function ON formulas(function);
CREATE INDEX idx_formulas_file ON formulas(file_path);
CREATE INDEX idx_formulas_variable ON formulas(variable);

CREATE TABLE IF NOT EXISTS accumulators (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    variable TEXT NOT NULL,
    source_field TEXT NOT NULL,
    wrapper TEXT,
    line INTEGER NOT NULL,
    function TEXT NOT NULL,
    file_path TEXT NOT NULL,
    direction TEXT DEFAULT 'add',    -- 'add' or 'subtract'
    language TEXT DEFAULT 'php'
);
CREATE INDEX idx_accum_function ON accumulators(function);

CREATE TABLE IF NOT EXISTS conditionals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    variable TEXT NOT NULL,
    value TEXT NOT NULL,
    operator TEXT DEFAULT '==',
    action_summary TEXT,
    line INTEGER NOT NULL,
    function TEXT NOT NULL,
    file_path TEXT NOT NULL,
    branch_type TEXT DEFAULT 'if',
    language TEXT DEFAULT 'php'
);
CREATE INDEX idx_cond_function ON conditionals(function);
CREATE INDEX idx_cond_variable ON conditionals(variable);
CREATE INDEX idx_cond_value ON conditionals(value);

CREATE TABLE IF NOT EXISTS magic_numbers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    value TEXT NOT NULL,
    variable TEXT NOT NULL,
    operator TEXT DEFAULT '==',
    line INTEGER NOT NULL,
    function TEXT NOT NULL,
    file_path TEXT NOT NULL,
    context TEXT,
    language TEXT DEFAULT 'php'
);
CREATE INDEX idx_magic_function ON magic_numbers(function);
CREATE INDEX idx_magic_value ON magic_numbers(value);
CREATE INDEX idx_magic_variable ON magic_numbers(variable);
```

### Query Examples (what becomes possible)

```sql
-- "What does bill_type 11 mean?" → find all branches that check for it
SELECT function, action_summary, file_path, line
FROM conditionals
WHERE variable LIKE '%bill_type%' AND value = '11';

-- "All financial formulas in billing module"
SELECT function, variable, expression, line
FROM formulas
WHERE file_path LIKE '%billing%'
  AND variable IN ('total', 'amount', 'tax', 'discount', 'grand_total', 'balance');

-- "Functions with highest magic number density" (code smell)
SELECT function, COUNT(*) as magic_count
FROM magic_numbers
WHERE value NOT IN ('0', '1', "''")
GROUP BY function
ORDER BY magic_count DESC
LIMIT 20;

-- "All accumulation patterns for 'amount'" 
SELECT function, variable, source_field, direction, file_path
FROM accumulators
WHERE source_field LIKE '%amount%';
```

---

## Migration Steps

| Step | What | Effort |
|---|---|---|
| 1 | Add BI tables to `.lca/index.db` schema (Schema A) | 30 min |
| 2 | Update the indexer to write BI data to SQLite during `lca index build` | 2 hours |
| 3 | Update `mcp_server.py` to use SQLite instead of `json_store.py` | 2 hours |
| 4 | Add JSON export command: `lca export --module billing --output billing.json` | 1 hour |
| 5 | Delete legacy `backend/lca_index.db` (Schema B) and `SQLiteStorage` class | 30 min |
| 6 | Delete `.lca/index.json` (133 MB) — replaced by SQLite | 1 min |
| 7 | Update SDLC config doc to reflect new storage strategy | 15 min |

### Phase 1 (Quick Win): Steps 1-3 = ~5 hours
- SQLite becomes the query engine with BI data
- MCP server queries are instant
- 133MB JSON still exists as fallback

### Phase 2 (Cleanup): Steps 4-7 = ~2 hours
- JSON becomes export-only
- Legacy DB deleted
- Single source of truth

---

## What NOT to Change

- **Small module-specific JSONs** (< 1 MB like `reports_cashflow.json`): Keep as JSON. They're small enough that loading is instant, and they're useful for human review.
- **`deep_analyze` on-demand**: Keep. It reads source files directly — useful when the index is stale.
- **Pydantic models** (`schema.py`): Keep. They define the data structure regardless of storage backend.

## Open Questions

1. **Who maintains the `.lca/index.db` Schema A currently?** Is it the same indexer that writes JSON, or a separate process?
2. **Is the `backend/lca_index.db` (92 MB, Schema B) used by anything?** If not, it's 92 MB of waste to delete.
3. **Should we start with the MCP migration (Step 3) or the BI tables (Step 1)?** MCP migration gives speed, BI tables give intelligence.
