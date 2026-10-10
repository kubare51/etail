"""SQLite-backed symbol database for fast code intelligence queries.

Replaces JSON flat-file storage with indexed SQLite for millisecond-level
lookups on 100K+ symbol/reference records. Inspired by Sourcegraph SCIP
and IntelliJ's index architecture.

Schema:
  files      — tracked source files with hash for incremental updates
  symbols    — function/method/class definitions
  references — call relationships (source → target)
  metrics    — per-symbol code quality metrics (populated by Phase 2)
"""

import sqlite3
import json
from pathlib import Path
from datetime import datetime
from contextlib import contextmanager

from lca.models import (
    FunctionDef, FunctionCall, ModuleIndex,
    FormulaExpr, Accumulator, ConditionalBranch, MagicNumber,
)


# ── Schema ─────────────────────────────────────────────────────────────

_SCHEMA_VERSION = 2

_SCHEMA_SQL = """
CREATE TABLE IF NOT EXISTS meta (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS files (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    path       TEXT NOT NULL UNIQUE,
    hash       TEXT,
    size_bytes INTEGER,
    language   TEXT,
    indexed_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS symbols (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    file_id    INTEGER NOT NULL REFERENCES files(id),
    file_path  TEXT NOT NULL,
    line       INTEGER NOT NULL,
    end_line   INTEGER,
    language   TEXT NOT NULL DEFAULT 'php',
    class_name TEXT,
    params     TEXT,            -- JSON array of param names
    visibility TEXT,            -- public/protected/private
    UNIQUE(name, file_path, line)
);

CREATE TABLE IF NOT EXISTS refs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    source_name TEXT NOT NULL,   -- caller function name
    target_name TEXT NOT NULL,   -- callee function name
    line        INTEGER NOT NULL,
    file_path   TEXT NOT NULL,   -- file where the call occurs
    call_type   TEXT NOT NULL DEFAULT 'calls',
    target_file TEXT,            -- resolved target file path
    metadata    TEXT,            -- JSON blob for extra info
    source_id   INTEGER REFERENCES symbols(id),
    target_id   INTEGER REFERENCES symbols(id)
);

CREATE TABLE IF NOT EXISTS metrics (
    id                     INTEGER PRIMARY KEY AUTOINCREMENT,
    symbol_id              INTEGER NOT NULL REFERENCES symbols(id),
    cyclomatic_complexity  INTEGER,
    cognitive_complexity   INTEGER,
    lines_of_code          INTEGER,
    param_count            INTEGER,
    max_nesting_depth      INTEGER,
    fan_in                 INTEGER,
    fan_out                INTEGER,
    coupling_score         INTEGER,
    UNIQUE(symbol_id)
);

-- Indexes for fast lookups
CREATE INDEX IF NOT EXISTS idx_symbols_name ON symbols(name);
CREATE INDEX IF NOT EXISTS idx_symbols_file ON symbols(file_path);
CREATE INDEX IF NOT EXISTS idx_symbols_class ON symbols(class_name);
CREATE INDEX IF NOT EXISTS idx_refs_source ON refs(source_name);
CREATE INDEX IF NOT EXISTS idx_refs_target ON refs(target_name);
CREATE INDEX IF NOT EXISTS idx_refs_call_type ON refs(call_type);
CREATE INDEX IF NOT EXISTS idx_refs_file ON refs(file_path);
CREATE INDEX IF NOT EXISTS idx_refs_source_id ON refs(source_id);
CREATE INDEX IF NOT EXISTS idx_refs_target_id ON refs(target_id);
CREATE INDEX IF NOT EXISTS idx_files_path ON files(path);

-- BI / Deep Analysis tables (Schema v2)
CREATE TABLE IF NOT EXISTS formulas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    variable TEXT NOT NULL,
    expression TEXT NOT NULL,
    operator TEXT NOT NULL,
    line INTEGER NOT NULL,
    function TEXT NOT NULL,
    file_path TEXT NOT NULL,
    precision TEXT,
    language TEXT DEFAULT 'php'
);
CREATE INDEX IF NOT EXISTS idx_formulas_function ON formulas(function);
CREATE INDEX IF NOT EXISTS idx_formulas_file ON formulas(file_path);
CREATE INDEX IF NOT EXISTS idx_formulas_variable ON formulas(variable);

CREATE TABLE IF NOT EXISTS accumulators (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    variable TEXT NOT NULL,
    source_field TEXT NOT NULL,
    wrapper TEXT,
    line INTEGER NOT NULL,
    function TEXT NOT NULL,
    file_path TEXT NOT NULL,
    direction TEXT DEFAULT 'add',
    language TEXT DEFAULT 'php'
);
CREATE INDEX IF NOT EXISTS idx_accum_function ON accumulators(function);
CREATE INDEX IF NOT EXISTS idx_accum_variable ON accumulators(variable);

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
CREATE INDEX IF NOT EXISTS idx_cond_function ON conditionals(function);
CREATE INDEX IF NOT EXISTS idx_cond_variable ON conditionals(variable);
CREATE INDEX IF NOT EXISTS idx_cond_value ON conditionals(value);

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
CREATE INDEX IF NOT EXISTS idx_magic_function ON magic_numbers(function);
CREATE INDEX IF NOT EXISTS idx_magic_value ON magic_numbers(value);
CREATE INDEX IF NOT EXISTS idx_magic_variable ON magic_numbers(variable);
"""


class SymbolDB:
    """SQLite-backed symbol database for code intelligence queries."""

    def __init__(self, db_path: Path):
        self.db_path = db_path
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        self._conn: sqlite3.Connection | None = None
        self._init_db()

    def _init_db(self) -> None:
        """Create tables and indexes if they don't exist."""
        conn = self._get_conn()
        conn.executescript(_SCHEMA_SQL)
        # Set schema version
        conn.execute(
            "INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)",
            ("schema_version", str(_SCHEMA_VERSION)),
        )
        conn.execute(
            "INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)",
            ("created_at", datetime.now().isoformat()),
        )
        conn.commit()

    def _get_conn(self) -> sqlite3.Connection:
        if self._conn is None:
            self._conn = sqlite3.connect(str(self.db_path))
            self._conn.row_factory = sqlite3.Row
            # Performance tuning
            self._conn.execute("PRAGMA journal_mode=WAL")
            self._conn.execute("PRAGMA synchronous=NORMAL")
            self._conn.execute("PRAGMA cache_size=-64000")  # 64MB cache
            self._conn.execute("PRAGMA temp_store=MEMORY")
        return self._conn

    @contextmanager
    def transaction(self):
        """Context manager for batch operations."""
        conn = self._get_conn()
        try:
            yield conn
            conn.commit()
        except Exception:
            conn.rollback()
            raise

    def close(self) -> None:
        if self._conn:
            self._conn.close()
            self._conn = None

    # ── Bulk Import ────────────────────────────────────────────────────

    def import_index(self, index: ModuleIndex) -> dict:
        """Import a ModuleIndex into the database. Returns import stats."""
        conn = self._get_conn()

        # Clear existing data for fresh import
        conn.execute("DELETE FROM metrics")
        conn.execute("DELETE FROM refs")
        conn.execute("DELETE FROM symbols")
        conn.execute("DELETE FROM files")

        # Track unique files
        file_ids: dict[str, int] = {}
        now = datetime.now().isoformat()

        # 1. Insert files
        file_paths = set()
        for f in index.functions:
            file_paths.add(f.file)
        for c in index.calls:
            file_paths.add(c.file)
            if c.target_file:
                file_paths.add(c.target_file)

        for path in file_paths:
            lang = "php" if path.endswith(".php") else "javascript" if path.endswith(".js") else "sql" if path.endswith(".sql") else "other"
            cursor = conn.execute(
                "INSERT OR IGNORE INTO files(path, language, indexed_at) VALUES (?, ?, ?)",
                (path, lang, now),
            )
            if cursor.lastrowid:
                file_ids[path] = cursor.lastrowid
            else:
                row = conn.execute("SELECT id FROM files WHERE path = ?", (path,)).fetchone()
                if row:
                    file_ids[path] = row[0]

        # 2. Insert symbols (batch)
        symbol_ids: dict[str, int] = {}  # name → id (last definition wins)
        symbol_data = []
        for f in index.functions:
            class_name = None
            if "::" in f.name:
                class_name = f.name.split("::")[0]
            symbol_data.append((
                f.name,
                file_ids.get(f.file, 0),
                f.file,
                f.line,
                f.end_line,
                f.language,
                class_name,
                json.dumps(f.params) if f.params else None,
            ))

        conn.executemany(
            """INSERT OR REPLACE INTO symbols
               (name, file_id, file_path, line, end_line, language, class_name, params)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
            symbol_data,
        )

        # Build symbol_id lookup
        for row in conn.execute("SELECT id, name FROM symbols"):
            symbol_ids[row[1]] = row[0]

        # 3. Insert references (batch)
        ref_data = []
        for c in index.calls:
            ref_data.append((
                c.source,
                c.target,
                c.line,
                c.file,
                c.call_type,
                c.target_file,
                json.dumps(c.metadata) if c.metadata else None,
                symbol_ids.get(c.source),
                symbol_ids.get(c.target),
            ))

        conn.executemany(
            """INSERT INTO refs
               (source_name, target_name, line, file_path, call_type, target_file, metadata, source_id, target_id)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)""",
            ref_data,
        )

        conn.commit()

        # Update module meta
        conn.execute(
            "INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)",
            ("module", index.module),
        )
        conn.execute(
            "INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)",
            ("indexed_at", now),
        )
        conn.commit()

        # 4. Insert metrics — use pre-computed if available, else derive from index data
        metrics_count = 0
        for f in index.functions:
            if f.name not in symbol_ids:
                continue
            sid = symbol_ids[f.name]
            if f.metrics:
                # Pre-computed metrics from analysis engine
                m = f.metrics
                conn.execute(
                    """INSERT OR REPLACE INTO metrics
                       (symbol_id, cyclomatic_complexity, cognitive_complexity,
                        lines_of_code, param_count, max_nesting_depth)
                       VALUES (?, ?, ?, ?, ?, ?)""",
                    (sid, m.get("cyclomatic_complexity"),
                     m.get("cognitive_complexity"),
                     m.get("lines_of_code"),
                     m.get("param_count"),
                     m.get("max_nesting_depth")),
                )
            else:
                # Derive baseline metrics from index data
                loc = (f.end_line - f.line + 1) if f.end_line and f.end_line > f.line else None
                param_count = len(f.params) if f.params else 0
                conn.execute(
                    """INSERT OR REPLACE INTO metrics
                       (symbol_id, lines_of_code, param_count)
                       VALUES (?, ?, ?)""",
                    (sid, loc, param_count),
                )
            metrics_count += 1

        # 5. Compute fan-in / fan-out from refs for ALL symbols with metrics
        conn.execute(
            """UPDATE metrics SET fan_out = (
                   SELECT COUNT(*) FROM refs WHERE refs.source_name = (
                       SELECT name FROM symbols WHERE symbols.id = metrics.symbol_id
                   )
               )"""
        )
        conn.execute(
            """UPDATE metrics SET fan_in = (
                   SELECT COUNT(*) FROM refs WHERE refs.target_name = (
                       SELECT name FROM symbols WHERE symbols.id = metrics.symbol_id
                   )
               )"""
        )
        # Coupling = fan_in * fan_out
        conn.execute(
            "UPDATE metrics SET coupling_score = COALESCE(fan_in, 0) * COALESCE(fan_out, 0)"
        )
        conn.commit()

        return {
            "files": len(file_ids),
            "symbols": len(symbol_data),
            "references": len(ref_data),
            "metrics": metrics_count,
            "formulas": self._import_bi_data(conn, index),
        }

    def _import_bi_data(self, conn, index: ModuleIndex) -> dict:
        """Import BI deep-analysis data (formulas, accumulators, conditionals, magic_numbers)."""
        # Clear existing BI data
        conn.execute("DELETE FROM formulas")
        conn.execute("DELETE FROM accumulators")
        conn.execute("DELETE FROM conditionals")
        conn.execute("DELETE FROM magic_numbers")

        counts = {}

        # Formulas
        if index.formulas:
            conn.executemany(
                """INSERT INTO formulas
                   (variable, expression, operator, line, function, file_path, precision, language)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
                [(f.variable, f.expression, f.operator, f.line, f.function,
                  f.file, f.precision, f.language) for f in index.formulas],
            )
            counts['formulas'] = len(index.formulas)

        # Accumulators
        if index.accumulators:
            conn.executemany(
                """INSERT INTO accumulators
                   (variable, source_field, wrapper, line, function, file_path, direction, language)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
                [(a.variable, a.source_field, a.wrapper, a.line, a.function,
                  a.file, a.direction, a.language) for a in index.accumulators],
            )
            counts['accumulators'] = len(index.accumulators)

        # Conditionals
        if index.conditionals:
            conn.executemany(
                """INSERT INTO conditionals
                   (variable, value, operator, action_summary, line, function, file_path, branch_type, language)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)""",
                [(c.variable, c.value, c.operator, c.action_summary, c.line,
                  c.function, c.file, c.branch_type, c.language) for c in index.conditionals],
            )
            counts['conditionals'] = len(index.conditionals)

        # Magic numbers
        if index.magic_numbers:
            conn.executemany(
                """INSERT INTO magic_numbers
                   (value, variable, operator, line, function, file_path, context, language)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
                [(m.value, m.variable, m.operator, m.line, m.function,
                  m.file, m.context, m.language) for m in index.magic_numbers],
            )
            counts['magic_numbers'] = len(index.magic_numbers)

        conn.commit()
        return counts

    # ── Query API ──────────────────────────────────────────────────────

    def find_symbol(self, name: str, exact: bool = True) -> list[dict]:
        """Find symbol definitions by name."""
        conn = self._get_conn()
        if exact:
            rows = conn.execute(
                "SELECT * FROM symbols WHERE name = ?", (name,)
            ).fetchall()
        else:
            rows = conn.execute(
                "SELECT * FROM symbols WHERE name LIKE ? LIMIT 50",
                (f"%{name}%",),
            ).fetchall()
        return [dict(r) for r in rows]

    def get_callers(self, function_name: str) -> list[dict]:
        """Get all functions that call the given function."""
        conn = self._get_conn()
        rows = conn.execute(
            """SELECT source_name, line, file_path, call_type, metadata
               FROM refs WHERE target_name = ?
               ORDER BY file_path, line""",
            (function_name,),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_callees(self, function_name: str) -> list[dict]:
        """Get all functions called by the given function."""
        conn = self._get_conn()
        rows = conn.execute(
            """SELECT target_name, line, file_path, call_type, target_file, metadata
               FROM refs WHERE source_name = ?
               ORDER BY line""",
            (function_name,),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_by_file(self, file_path: str) -> list[dict]:
        """Get all symbols defined in a file."""
        conn = self._get_conn()
        rows = conn.execute(
            "SELECT * FROM symbols WHERE file_path = ? ORDER BY line",
            (file_path,),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_refs_in_file(self, file_path: str) -> list[dict]:
        """Get all references (calls) made from a file."""
        conn = self._get_conn()
        rows = conn.execute(
            "SELECT * FROM refs WHERE file_path = ? ORDER BY line",
            (file_path,),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_by_call_type(self, call_type: str, limit: int = 100) -> list[dict]:
        """Get all references of a given call type."""
        conn = self._get_conn()
        rows = conn.execute(
            "SELECT * FROM refs WHERE call_type = ? LIMIT ?",
            (call_type, limit),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_table_usage(self, table_name: str) -> dict:
        """Get all functions that read/write a given DB table."""
        conn = self._get_conn()
        target = f"TABLE:{table_name}"

        readers = conn.execute(
            """SELECT source_name, line, file_path FROM refs
               WHERE target_name = ? AND call_type = 'db_read'""",
            (target,),
        ).fetchall()

        writers = conn.execute(
            """SELECT source_name, line, file_path FROM refs
               WHERE target_name = ? AND call_type = 'db_write'""",
            (target,),
        ).fetchall()

        view_refs = conn.execute(
            """SELECT source_name, line, file_path FROM refs
               WHERE target_name = ? AND call_type = 'view_reads'""",
            (target,),
        ).fetchall()

        return {
            "readers": [dict(r) for r in readers],
            "writers": [dict(r) for r in writers],
            "view_refs": [dict(r) for r in view_refs],
        }

    def get_ajax_endpoints(self, controller_filter: str = "", limit: int = 100) -> list[dict]:
        """Get AJAX endpoint calls, optionally filtered by controller."""
        conn = self._get_conn()
        if controller_filter:
            rows = conn.execute(
                """SELECT target_name, source_name, line, file_path, metadata
                   FROM refs WHERE call_type = 'ajax_call'
                   AND target_name LIKE ? LIMIT ?""",
                (f"%{controller_filter}%", limit),
            ).fetchall()
        else:
            rows = conn.execute(
                """SELECT target_name, source_name, line, file_path, metadata
                   FROM refs WHERE call_type = 'ajax_call' LIMIT ?""",
                (limit,),
            ).fetchall()
        return [dict(r) for r in rows]

    def get_view_deps(self, view_name: str = "", controller: str = "") -> list[dict]:
        """Get view dependency data."""
        conn = self._get_conn()
        if view_name:
            rows = conn.execute(
                """SELECT source_name, target_name, line, file_path
                   FROM refs WHERE call_type = 'load_view'
                   AND target_name LIKE ?""",
                (f"%{view_name}%",),
            ).fetchall()
        elif controller:
            rows = conn.execute(
                """SELECT source_name, target_name, line, file_path
                   FROM refs WHERE call_type = 'load_view'
                   AND source_name LIKE ?""",
                (f"%{controller}%",),
            ).fetchall()
        else:
            rows = conn.execute(
                """SELECT source_name, target_name, line, file_path
                   FROM refs WHERE call_type = 'load_view'""",
            ).fetchall()
        return [dict(r) for r in rows]

    def get_full_chain(self, function_name: str) -> dict:
        """Get the full dependency chain for a function."""
        conn = self._get_conn()

        # AJAX callers
        ajax_callers = conn.execute(
            """SELECT source_name, line, file_path, metadata
               FROM refs WHERE call_type = 'ajax_call' AND target_name = ?""",
            (function_name,),
        ).fetchall()

        # Callees (model calls, local calls)
        callees = conn.execute(
            """SELECT target_name, line, call_type, target_file
               FROM refs WHERE source_name = ?
               ORDER BY line""",
            (function_name,),
        ).fetchall()

        # DB tables
        db_calls = conn.execute(
            """SELECT target_name, line, call_type
               FROM refs WHERE source_name = ?
               AND call_type IN ('db_read', 'db_write')""",
            (function_name,),
        ).fetchall()

        # Views
        view_calls = conn.execute(
            """SELECT target_name, line
               FROM refs WHERE source_name = ?
               AND call_type = 'load_view'""",
            (function_name,),
        ).fetchall()

        return {
            "ajax_callers": [dict(r) for r in ajax_callers],
            "callees": [dict(r) for r in callees],
            "db_calls": [dict(r) for r in db_calls],
            "view_calls": [dict(r) for r in view_calls],
        }

    # ── Metrics Queries ────────────────────────────────────────────────

    def get_metrics(self, function_name: str) -> dict | None:
        """Get metrics for a specific function."""
        conn = self._get_conn()
        row = conn.execute(
            """SELECT s.name, s.file_path, s.line, s.language,
                      m.cyclomatic_complexity, m.cognitive_complexity,
                      m.lines_of_code, m.param_count, m.max_nesting_depth,
                      m.fan_in, m.fan_out, m.coupling_score
               FROM metrics m
               JOIN symbols s ON s.id = m.symbol_id
               WHERE s.name = ?""",
            (function_name,),
        ).fetchone()
        return dict(row) if row else None

    def get_hotspots(
        self,
        metric: str = "cyclomatic_complexity",
        threshold: int = 10,
        limit: int = 30,
        language: str = "",
    ) -> list[dict]:
        """Get functions exceeding a metric threshold (highest first).

        Args:
            metric: Column name in metrics table
            threshold: Minimum value to include
            limit: Maximum results
            language: Optional language filter ('php', 'javascript')
        """
        conn = self._get_conn()
        # Validate metric name to prevent SQL injection
        valid_metrics = {
            "cyclomatic_complexity", "cognitive_complexity",
            "lines_of_code", "param_count", "max_nesting_depth",
            "fan_in", "fan_out", "coupling_score",
        }
        if metric not in valid_metrics:
            return []

        query = f"""SELECT s.name, s.file_path, s.line, s.language,
                           m.{metric} as value,
                           m.cyclomatic_complexity, m.cognitive_complexity,
                           m.lines_of_code, m.fan_in, m.fan_out, m.coupling_score
                    FROM metrics m
                    JOIN symbols s ON s.id = m.symbol_id
                    WHERE m.{metric} >= ?"""
        params: list = [threshold]

        if language:
            query += " AND s.language = ?"
            params.append(language)

        query += f" ORDER BY m.{metric} DESC LIMIT ?"
        params.append(limit)

        rows = conn.execute(query, params).fetchall()
        return [dict(r) for r in rows]

    def get_metrics_summary(self, language: str = "") -> dict:
        """Get aggregate metrics across all functions."""
        conn = self._get_conn()
        lang_filter = ""
        params: list = []
        if language:
            lang_filter = "WHERE s.language = ?"
            params = [language]

        row = conn.execute(
            f"""SELECT
                    COUNT(*) as total_functions,
                    AVG(m.cyclomatic_complexity) as avg_cyclomatic,
                    MAX(m.cyclomatic_complexity) as max_cyclomatic,
                    AVG(m.cognitive_complexity) as avg_cognitive,
                    MAX(m.cognitive_complexity) as max_cognitive,
                    AVG(m.lines_of_code) as avg_loc,
                    MAX(m.lines_of_code) as max_loc,
                    AVG(m.max_nesting_depth) as avg_nesting,
                    MAX(m.max_nesting_depth) as max_nesting,
                    SUM(CASE WHEN m.cyclomatic_complexity > 20 THEN 1 ELSE 0 END) as high_complexity,
                    SUM(CASE WHEN m.cognitive_complexity > 25 THEN 1 ELSE 0 END) as high_cognitive,
                    SUM(CASE WHEN m.lines_of_code > 100 THEN 1 ELSE 0 END) as long_functions
                FROM metrics m
                JOIN symbols s ON s.id = m.symbol_id
                {lang_filter}""",
            params,
        ).fetchone()
        return dict(row) if row else {}

    # ── Stats ──────────────────────────────────────────────────────────

    def get_stats(self) -> dict:
        """Get database statistics."""
        conn = self._get_conn()
        symbols = conn.execute("SELECT COUNT(*) FROM symbols").fetchone()[0]
        refs = conn.execute("SELECT COUNT(*) FROM refs").fetchone()[0]
        files = conn.execute("SELECT COUNT(*) FROM files").fetchone()[0]
        resolved = conn.execute(
            "SELECT COUNT(*) FROM refs WHERE target_file IS NOT NULL"
        ).fetchone()[0]

        # Call type distribution
        type_dist = conn.execute(
            "SELECT call_type, COUNT(*) as cnt FROM refs GROUP BY call_type ORDER BY cnt DESC"
        ).fetchall()

        return {
            "files": files,
            "symbols": symbols,
            "references": refs,
            "resolved": resolved,
            "resolution_rate": f"{resolved * 100 // max(refs, 1)}%",
            "call_types": {r[0]: r[1] for r in type_dist},
        }

    # ── BI / Deep Analysis Queries ─────────────────────────────────────

    def get_formulas(self, function: str = "", file: str = "",
                     variable: str = "", limit: int = 200) -> list[dict]:
        """Query formula expressions. Filter by function, file, or variable."""
        conn = self._get_conn()
        where, params = [], []
        if function:
            where.append("function LIKE ?")
            params.append(f"%{function}%")
        if file:
            where.append("file_path LIKE ?")
            params.append(f"%{file}%")
        if variable:
            where.append("variable LIKE ?")
            params.append(f"%{variable}%")
        clause = f"WHERE {' AND '.join(where)}" if where else ""
        rows = conn.execute(
            f"SELECT * FROM formulas {clause} ORDER BY file_path, line LIMIT ?",
            params + [limit],
        ).fetchall()
        return [dict(r) for r in rows]

    def get_accumulators(self, function: str = "", variable: str = "",
                         limit: int = 200) -> list[dict]:
        """Query accumulator patterns."""
        conn = self._get_conn()
        where, params = [], []
        if function:
            where.append("function LIKE ?")
            params.append(f"%{function}%")
        if variable:
            where.append("(variable LIKE ? OR source_field LIKE ?)")
            params.extend([f"%{variable}%", f"%{variable}%"])
        clause = f"WHERE {' AND '.join(where)}" if where else ""
        rows = conn.execute(
            f"SELECT * FROM accumulators {clause} ORDER BY file_path, line LIMIT ?",
            params + [limit],
        ).fetchall()
        return [dict(r) for r in rows]

    def get_conditionals(self, variable: str = "", value: str = "",
                          function: str = "", limit: int = 200) -> list[dict]:
        """Query conditional branches. Great for: 'what does bill_type 11 mean?'"""
        conn = self._get_conn()
        where, params = [], []
        if variable:
            where.append("variable LIKE ?")
            params.append(f"%{variable}%")
        if value:
            where.append("value = ?")
            params.append(value)
        if function:
            where.append("function LIKE ?")
            params.append(f"%{function}%")
        clause = f"WHERE {' AND '.join(where)}" if where else ""
        rows = conn.execute(
            f"SELECT * FROM conditionals {clause} ORDER BY file_path, line LIMIT ?",
            params + [limit],
        ).fetchall()
        return [dict(r) for r in rows]

    def get_magic_numbers(self, function: str = "", value: str = "",
                          variable: str = "", limit: int = 200) -> list[dict]:
        """Query magic numbers. Find hardcoded constants."""
        conn = self._get_conn()
        where, params = [], []
        if function:
            where.append("function LIKE ?")
            params.append(f"%{function}%")
        if value:
            where.append("value = ?")
            params.append(value)
        if variable:
            where.append("variable LIKE ?")
            params.append(f"%{variable}%")
        clause = f"WHERE {' AND '.join(where)}" if where else ""
        rows = conn.execute(
            f"SELECT * FROM magic_numbers {clause} ORDER BY file_path, line LIMIT ?",
            params + [limit],
        ).fetchall()
        return [dict(r) for r in rows]

    def get_bi_summary(self) -> dict:
        """Get summary counts of all BI data."""
        conn = self._get_conn()
        return {
            "formulas": conn.execute("SELECT COUNT(*) FROM formulas").fetchone()[0],
            "accumulators": conn.execute("SELECT COUNT(*) FROM accumulators").fetchone()[0],
            "conditionals": conn.execute("SELECT COUNT(*) FROM conditionals").fetchone()[0],
            "magic_numbers": conn.execute("SELECT COUNT(*) FROM magic_numbers").fetchone()[0],
        }

    # ── Export ─────────────────────────────────────────────────────────

    def export_to_index(self) -> ModuleIndex:
        """Export SQLite data back to ModuleIndex (for JSON export)."""
        conn = self._get_conn()

        module = "unknown"
        row = conn.execute("SELECT value FROM meta WHERE key = 'module'").fetchone()
        if row:
            module = row[0]

        functions = []
        for r in conn.execute("SELECT * FROM symbols"):
            functions.append(FunctionDef(
                name=r["name"],
                file=r["file_path"],
                line=r["line"],
                end_line=r["end_line"],
                params=json.loads(r["params"]) if r["params"] else [],
                language=r["language"],
            ))

        calls = []
        for r in conn.execute("SELECT * FROM refs"):
            calls.append(FunctionCall(
                source=r["source_name"],
                target=r["target_name"],
                line=r["line"],
                file=r["file_path"],
                call_type=r["call_type"],
                target_file=r["target_file"],
                metadata=json.loads(r["metadata"]) if r["metadata"] else None,
            ))

        return ModuleIndex(
            module=module,
            functions=functions,
            calls=calls,
            formulas=self._export_formulas(conn),
            accumulators=self._export_accumulators(conn),
            conditionals=self._export_conditionals(conn),
            magic_numbers=self._export_magic_numbers(conn),
        )

    def _export_formulas(self, conn) -> list[FormulaExpr]:
        rows = conn.execute("SELECT * FROM formulas").fetchall()
        return [FormulaExpr(
            variable=r["variable"], expression=r["expression"], operator=r["operator"],
            line=r["line"], function=r["function"], file=r["file_path"],
            precision=r["precision"], language=r["language"],
        ) for r in rows]

    def _export_accumulators(self, conn) -> list[Accumulator]:
        rows = conn.execute("SELECT * FROM accumulators").fetchall()
        return [Accumulator(
            variable=r["variable"], source_field=r["source_field"], wrapper=r["wrapper"],
            line=r["line"], function=r["function"], file=r["file_path"],
            direction=r["direction"], language=r["language"],
        ) for r in rows]

    def _export_conditionals(self, conn) -> list[ConditionalBranch]:
        rows = conn.execute("SELECT * FROM conditionals").fetchall()
        return [ConditionalBranch(
            variable=r["variable"], value=r["value"], operator=r["operator"],
            action_summary=r["action_summary"], line=r["line"], function=r["function"],
            file=r["file_path"], branch_type=r["branch_type"], language=r["language"],
        ) for r in rows]

    def _export_magic_numbers(self, conn) -> list[MagicNumber]:
        rows = conn.execute("SELECT * FROM magic_numbers").fetchall()
        return [MagicNumber(
            value=r["value"], variable=r["variable"], operator=r["operator"],
            line=r["line"], function=r["function"], file=r["file_path"],
            context=r["context"], language=r["language"],
        ) for r in rows]
