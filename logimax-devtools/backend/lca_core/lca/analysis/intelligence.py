"""Code Intelligence Engine — advanced analysis beyond basic metrics.

Provides 6 intelligence features built on top of the SQLite symbol database:

1. Dead Code Detection     — functions with zero callers
2. Duplicate/Clone Detection — AST-hash fingerprinting for near-identical functions
3. Change Impact Preview   — what breaks if you touch a file
4. Auto API Docs           — AJAX endpoint registry generation
5. Git Churn Analysis      — churn × complexity = bug magnet score
6. Code Smell Detector     — structural anti-pattern detection
"""

from __future__ import annotations

import hashlib
import re
import subprocess
from collections import Counter, defaultdict
from dataclasses import dataclass, field
from pathlib import Path


# ── Data Classes ───────────────────────────────────────────────────────

@dataclass
class DeadCodeResult:
    """Result of dead code analysis."""
    dead_functions: list[dict]        # Functions with 0 callers
    dead_files: list[dict]            # Files with all dead functions
    entry_points_excluded: int        # How many entry points were filtered out
    total_functions: int
    dead_percentage: float


@dataclass
class ClonePair:
    """A pair of near-duplicate functions."""
    func_a: str
    file_a: str
    line_a: int
    func_b: str
    file_b: str
    line_b: int
    similarity: float         # 0.0 - 1.0
    shared_lines: int


@dataclass
class ChangeImpact:
    """Impact analysis for a file or function change."""
    target: str               # File or function being changed
    direct_callers: list[dict]
    indirect_callers: list[dict]
    ajax_routes_affected: list[dict]
    tables_affected: list[dict]
    views_affected: list[dict]
    js_functions_affected: list[dict]
    risk_level: str           # LOW / MEDIUM / HIGH / CRITICAL


@dataclass
class APIEndpoint:
    """A discovered AJAX endpoint."""
    url_pattern: str          # e.g., /admin_ret_estimation/ajax_save
    controller: str           # Controller class
    method: str               # Controller method
    http_method: str          # GET/POST (inferred)
    model_calls: list[str]    # Models called
    tables: list[str]         # Tables accessed
    js_callers: list[str]     # JS functions that call this endpoint


@dataclass
class ChurnEntry:
    """Git churn data for a file."""
    file_path: str
    commits: int
    lines_changed: int
    authors: list[str]
    last_changed: str
    complexity: int           # Max CC in file
    bug_magnet_score: float   # churn × complexity


@dataclass
class CodeSmell:
    """A detected code smell."""
    smell_type: str           # god_class, long_params, feature_envy, etc.
    severity: str             # LOW / MEDIUM / HIGH
    location: str             # File or function name
    file: str
    line: int
    detail: str               # Human-readable explanation
    metric_value: int | float


# ── 1. Dead Code Detection ────────────────────────────────────────────

def find_dead_code(db, language: str = "") -> DeadCodeResult:
    """Find functions that are never called (fan_in = 0).
    
    Intelligently excludes:
    - Controller public methods (they're route endpoints)
    - __construct / __destruct (lifecycle methods)
    - GLOBAL scope (file-level code, not a real function)
    - Callback-style names (ajax_*, on_*, handle_*)
    """
    conn = db._get_conn()
    
    # Get all symbols with their metrics
    lang_filter = "AND s.language = ?" if language else ""
    params = (language,) if language else ()
    
    rows = conn.execute(f"""
        SELECT s.name, s.file_path, s.line, s.language, s.class_name,
               COALESCE(m.fan_in, 0) as fan_in,
               COALESCE(m.cyclomatic_complexity, 1) as cc,
               COALESCE(m.lines_of_code, 0) as loc
        FROM symbols s
        LEFT JOIN metrics m ON m.symbol_id = s.id
        WHERE 1=1 {lang_filter}
        ORDER BY s.file_path, s.name
    """, params).fetchall()
    
    total = len(rows)
    dead = []
    entry_points_excluded = 0
    
    # Build set of all called target names for fast lookup
    called_targets = set()
    for row in conn.execute("SELECT DISTINCT target_name FROM refs"):
        called_targets.add(row[0])
    
    for name, file_path, line, lang, class_name, fan_in, cc, loc in rows:
        # Skip lifecycle / infrastructure methods
        if _is_entry_point(name, class_name, file_path):
            entry_points_excluded += 1
            continue
        
        # Check if truly uncalled — both fan_in=0 AND not in refs target list
        if fan_in == 0 and name not in called_targets:
            dead.append({
                "name": name,
                "file": file_path,
                "line": line,
                "language": lang,
                "class": class_name,
                "complexity": cc,
                "loc": loc,
            })
    
    # Find files where ALL functions are dead
    file_dead_counts: dict[str, int] = Counter()
    file_total_counts: dict[str, int] = Counter()
    
    for d in dead:
        file_dead_counts[d["file"]] += 1
    for name, file_path, *_ in rows:
        file_total_counts[file_path] += 1
    
    dead_files = []
    for fpath, dead_count in file_dead_counts.items():
        total_in_file = file_total_counts.get(fpath, 0)
        if total_in_file > 0 and dead_count == total_in_file:
            dead_files.append({
                "file": fpath,
                "dead_functions": dead_count,
                "total_loc": sum(d["loc"] for d in dead if d["file"] == fpath),
            })
    
    # Sort dead functions by LOC (biggest cleanup wins first)
    dead.sort(key=lambda d: d["loc"], reverse=True)
    
    dead_pct = len(dead) * 100 / max(total - entry_points_excluded, 1)
    
    return DeadCodeResult(
        dead_functions=dead,
        dead_files=dead_files,
        entry_points_excluded=entry_points_excluded,
        total_functions=total,
        dead_percentage=dead_pct,
    )


def _is_entry_point(name: str, class_name: str | None, file_path: str) -> bool:
    """Check if a function is an entry point (shouldn't be flagged as dead)."""
    # GLOBAL scope
    if name == "GLOBAL":
        return True
    
    # PHP Lifecycle
    bare_name = name.split("::")[-1] if "::" in name else name
    if bare_name.startswith("__"):
        return True
    
    # Controller public methods are route endpoints
    if class_name and ("controller" in file_path.lower() or
                       class_name.startswith("Admin_") or
                       class_name.startswith("Api_")):
        return True
    
    # CI3 callback patterns
    if bare_name.startswith(("ajax_", "callback_", "cron_")):
        return True
    
    # JS event handlers and callbacks
    if bare_name.startswith(("on_", "handle_", "$.")):
        return True
    
    # Anonymous / arrow functions
    if bare_name in ("anonymous", "arrow_function"):
        return True
    
    return False


# ── 2. Duplicate / Clone Detection ─────────────────────────────────────

def find_duplicates(db, min_lines: int = 10, similarity_threshold: float = 0.8
                    ) -> list[ClonePair]:
    """Find near-duplicate functions using structural fingerprinting.
    
    Uses line-count + complexity + param-count as a lightweight fingerprint.
    Functions with identical fingerprints are candidate clones — then we
    compare their names for semantic similarity.
    
    Args:
        db: SymbolDB instance
        min_lines: Minimum LOC to consider (skip tiny functions)
        similarity_threshold: 0.0-1.0, how similar names must be
    """
    conn = db._get_conn()
    
    # Get all functions with metrics
    rows = conn.execute("""
        SELECT s.name, s.file_path, s.line, s.language, s.class_name,
               COALESCE(m.cyclomatic_complexity, 0) as cc,
               COALESCE(m.cognitive_complexity, 0) as cog,
               COALESCE(m.lines_of_code, 0) as loc,
               COALESCE(m.param_count, 0) as params,
               COALESCE(m.max_nesting_depth, 0) as nesting
        FROM symbols s
        LEFT JOIN metrics m ON m.symbol_id = s.id
        WHERE COALESCE(m.lines_of_code, 0) >= ?
    """, (min_lines,)).fetchall()
    
    # Group by structural fingerprint: (loc_bucket, cc, params, nesting)
    # LOC bucket: round to nearest 5 to catch near-matches
    fingerprints: dict[tuple, list] = defaultdict(list)
    
    for name, file_path, line, lang, class_name, cc, cog, loc, params, nesting in rows:
        loc_bucket = (loc // 5) * 5  # Round to nearest 5
        fp = (loc_bucket, cc, params, nesting)
        fingerprints[fp].append({
            "name": name,
            "file": file_path,
            "line": line,
            "language": lang,
            "class": class_name,
            "cc": cc,
            "cog": cog,
            "loc": loc,
            "params": params,
        })
    
    clones: list[ClonePair] = []
    
    for fp, funcs in fingerprints.items():
        if len(funcs) < 2:
            continue
        
        # Compare pairs within same fingerprint group
        for i in range(len(funcs)):
            for j in range(i + 1, len(funcs)):
                a, b = funcs[i], funcs[j]
                
                # Skip if in same file and same class (likely overloads)
                if a["file"] == b["file"]:
                    continue
                
                # Check name similarity (strip class prefix)
                name_a = a["name"].split("::")[-1] if "::" in a["name"] else a["name"]
                name_b = b["name"].split("::")[-1] if "::" in b["name"] else b["name"]
                
                sim = _name_similarity(name_a, name_b)
                
                # Also check structural similarity
                loc_sim = 1 - abs(a["loc"] - b["loc"]) / max(a["loc"], b["loc"], 1)
                cc_sim = 1 - abs(a["cc"] - b["cc"]) / max(a["cc"], b["cc"], 1)
                
                # Combined similarity
                total_sim = (sim * 0.4 + loc_sim * 0.4 + cc_sim * 0.2)
                
                if total_sim >= similarity_threshold:
                    clones.append(ClonePair(
                        func_a=a["name"],
                        file_a=a["file"],
                        line_a=a["line"],
                        func_b=b["name"],
                        file_b=b["file"],
                        line_b=b["line"],
                        similarity=round(total_sim, 2),
                        shared_lines=min(a["loc"], b["loc"]),
                    ))
    
    # Sort by shared lines (biggest duplication first)
    clones.sort(key=lambda c: c.shared_lines, reverse=True)
    
    return clones


def _name_similarity(a: str, b: str) -> float:
    """Compute Jaccard similarity between two function names."""
    # Tokenize by underscores and camelCase
    tokens_a = set(_tokenize_name(a))
    tokens_b = set(_tokenize_name(b))
    
    if not tokens_a or not tokens_b:
        return 0.0
    
    intersection = tokens_a & tokens_b
    union = tokens_a | tokens_b
    
    return len(intersection) / len(union)


def _tokenize_name(name: str) -> list[str]:
    """Split a function name into tokens."""
    # Split by underscore
    parts = name.lower().split("_")
    # Split camelCase
    result = []
    for part in parts:
        result.extend(re.findall(r'[a-z]+', part))
    return result


# ── 3. Change Impact Preview ──────────────────────────────────────────

def analyze_change_impact(db, file_path: str) -> ChangeImpact:
    """Analyze what would be affected by changing a file.
    
    Traces the reference graph to find:
    - Direct callers (functions that call functions in this file)
    - Indirect callers (2nd-degree callers)
    - AJAX routes affected
    - Tables accessed by this file
    - Views loaded by this file
    - JS functions that call endpoints in this file
    """
    conn = db._get_conn()
    
    # Normalize path for matching
    file_path_normalized = file_path.replace("\\", "/")
    
    # Get all symbols in this file
    file_symbols = set()
    for row in conn.execute(
        "SELECT name FROM symbols WHERE file_path LIKE ?",
        (f"%{Path(file_path).name}",)
    ):
        file_symbols.add(row[0])
    
    if not file_symbols:
        # Try with full path
        for row in conn.execute(
            "SELECT name FROM symbols WHERE file_path = ?", (file_path,)
        ):
            file_symbols.add(row[0])
    
    # Direct callers: who calls functions in this file?
    direct_callers = []
    direct_caller_names = set()
    for symbol in file_symbols:
        for row in conn.execute("""
            SELECT DISTINCT r.source_name, r.file_path, r.call_type, r.line
            FROM refs r
            WHERE r.target_name = ? AND r.file_path NOT LIKE ?
        """, (symbol, f"%{Path(file_path).name}")):
            caller = {
                "function": row[0],
                "file": row[1],
                "call_type": row[2],
                "line": row[3],
            }
            direct_callers.append(caller)
            direct_caller_names.add(row[0])
    
    # Indirect callers (2nd degree): who calls the direct callers?
    indirect_callers = []
    for caller_name in list(direct_caller_names)[:50]:  # Limit to prevent explosion
        for row in conn.execute("""
            SELECT DISTINCT r.source_name, r.file_path, r.call_type, r.line
            FROM refs r
            WHERE r.target_name = ?
            LIMIT 10
        """, (caller_name,)):
            if row[0] not in direct_caller_names and row[0] not in file_symbols:
                indirect_callers.append({
                    "function": row[0],
                    "file": row[1],
                    "via": caller_name,
                })
    
    # AJAX routes: JS→PHP ajax calls targeting this file's functions
    ajax_routes = []
    for row in conn.execute("""
        SELECT r.source_name, r.target_name, r.file_path, r.line
        FROM refs r
        WHERE r.call_type = 'ajax_call' AND r.target_name IN ({})
    """.format(",".join("?" * len(file_symbols))), list(file_symbols)):
        ajax_routes.append({
            "js_function": row[0],
            "endpoint": row[1],
            "js_file": row[2],
            "line": row[3],
        })
    
    # Tables accessed by this file
    tables = []
    for row in conn.execute("""
        SELECT DISTINCT r.target_name, r.call_type
        FROM refs r
        WHERE r.file_path LIKE ? AND r.call_type IN ('db_read', 'db_write')
    """, (f"%{Path(file_path).name}",)):
        tables.append({
            "table": row[0],
            "operation": row[1],
        })
    
    # Views loaded by this file
    views = []
    for row in conn.execute("""
        SELECT DISTINCT r.target_name, r.source_name, r.line
        FROM refs r
        WHERE r.file_path LIKE ? AND r.call_type = 'load_view'
    """, (f"%{Path(file_path).name}",)):
        views.append({
            "view": row[0],
            "loaded_by": row[1],
            "line": row[2],
        })
    
    # JS functions that call any AJAX endpoint in this file
    js_affected = []
    for row in conn.execute("""
        SELECT DISTINCT r.source_name, r.file_path, r.target_name
        FROM refs r
        WHERE r.call_type = 'ajax_call' 
          AND r.target_name IN (
              SELECT DISTINCT s.name FROM symbols s 
              WHERE s.file_path LIKE ?
          )
    """, (f"%{Path(file_path).name}",)):
        js_affected.append({
            "function": row[0],
            "file": row[1],
            "calls_endpoint": row[2],
        })
    
    # Compute risk level
    risk = _compute_risk(
        len(direct_callers), len(indirect_callers),
        len(ajax_routes), len(tables)
    )
    
    return ChangeImpact(
        target=file_path,
        direct_callers=direct_callers,
        indirect_callers=indirect_callers,
        ajax_routes_affected=ajax_routes,
        tables_affected=tables,
        views_affected=views,
        js_functions_affected=js_affected,
        risk_level=risk,
    )


def _compute_risk(direct: int, indirect: int, ajax: int, tables: int) -> str:
    """Compute change risk level."""
    score = direct * 2 + indirect + ajax * 3 + tables * 2
    if score > 100:
        return "CRITICAL"
    elif score > 50:
        return "HIGH"
    elif score > 20:
        return "MEDIUM"
    return "LOW"


# ── 4. Auto API Docs ──────────────────────────────────────────────────

def generate_api_docs(db) -> list[APIEndpoint]:
    """Generate API endpoint documentation from AJAX call references.
    
    Discovers all JS→PHP AJAX routes and documents:
    - URL pattern
    - Controller/method
    - Model calls made
    - Tables accessed
    - JS callers
    """
    conn = db._get_conn()
    
    # Get all AJAX calls
    ajax_calls = conn.execute("""
        SELECT r.source_name, r.target_name, r.file_path, r.line
        FROM refs r
        WHERE r.call_type = 'ajax_call'
        ORDER BY r.target_name
    """).fetchall()
    
    # Group by target (endpoint)
    endpoints_map: dict[str, list] = defaultdict(list)
    for source, target, file_path, line in ajax_calls:
        endpoints_map[target].append({
            "js_function": source,
            "js_file": file_path,
            "line": line,
        })
    
    endpoints: list[APIEndpoint] = []
    
    for endpoint_name, js_callers in endpoints_map.items():
        # Parse endpoint: usually "Controller::method" or "controller/method"
        controller = ""
        method = ""
        if "::" in endpoint_name:
            controller, method = endpoint_name.split("::", 1)
        else:
            parts = endpoint_name.split("/")
            if len(parts) >= 2:
                controller = parts[0]
                method = parts[1]
            else:
                method = endpoint_name
        
        # URL pattern
        url_pattern = f"/{controller.lower()}/{method}" if controller else f"/{method}"
        
        # Find what models this endpoint calls
        model_calls = []
        for row in conn.execute("""
            SELECT DISTINCT r.target_name
            FROM refs r
            WHERE r.source_name = ? AND r.call_type = 'model_call'
        """, (endpoint_name,)):
            model_calls.append(row[0])
        
        # Find tables this endpoint touches
        tables = []
        # Direct DB access
        for row in conn.execute("""
            SELECT DISTINCT r.target_name, r.call_type
            FROM refs r
            WHERE r.source_name = ? AND r.call_type IN ('db_read', 'db_write')
        """, (endpoint_name,)):
            tables.append(row[0])
        
        # Indirect via model calls
        for model_call in model_calls:
            for row in conn.execute("""
                SELECT DISTINCT r.target_name
                FROM refs r
                WHERE r.source_name = ? AND r.call_type IN ('db_read', 'db_write')
            """, (model_call,)):
                if row[0] not in tables:
                    tables.append(row[0])
        
        # Infer HTTP method from name
        http_method = "POST"
        if any(kw in method.lower() for kw in ("get", "list", "search", "fetch", "load", "view")):
            http_method = "GET"
        
        endpoints.append(APIEndpoint(
            url_pattern=url_pattern,
            controller=controller,
            method=method,
            http_method=http_method,
            model_calls=model_calls,
            tables=tables,
            js_callers=[f"{c['js_function']} ({Path(c['js_file']).name}:L{c['line']})"
                        for c in js_callers],
        ))
    
    # Sort by controller, then method
    endpoints.sort(key=lambda e: (e.controller, e.method))
    
    return endpoints


# ── 5. Git Churn Analysis ─────────────────────────────────────────────

def analyze_git_churn(db, project_root: Path, months: int = 6
                      ) -> list[ChurnEntry]:
    """Analyze git history to find bug magnets (high churn × high complexity).
    
    Args:
        db: SymbolDB instance
        project_root: Git repository root
        months: How many months of history to analyze
    """
    conn = db._get_conn()
    
    # Get git log for PHP/JS files
    try:
        cmd = [
            "git", "log",
            f"--since={months} months ago",
            "--pretty=format:%H|%an|%ai",
            "--name-only",
            "--diff-filter=M",  # Only modified files
        ]
        result = subprocess.run(
            cmd, cwd=str(project_root), capture_output=True, text=True, timeout=30
        )
        if result.returncode != 0:
            return []
    except Exception:
        return []
    
    # Parse git log output
    file_commits: dict[str, dict] = defaultdict(lambda: {
        "commits": 0, "authors": set(), "last_changed": ""
    })
    
    current_commit = None
    current_author = None
    current_date = None
    
    for line in result.stdout.split("\n"):
        line = line.strip()
        if not line:
            continue
        
        if "|" in line and len(line.split("|")) == 3:
            # Commit header
            parts = line.split("|")
            current_commit = parts[0]
            current_author = parts[1]
            current_date = parts[2]
        elif line.endswith((".php", ".js")):
            # File name
            data = file_commits[line]
            data["commits"] += 1
            data["authors"].add(current_author)
            if not data["last_changed"] or current_date > data["last_changed"]:
                data["last_changed"] = current_date
    
    # Combine with complexity data from DB
    churn_entries: list[ChurnEntry] = []
    
    for file_rel, data in file_commits.items():
        # Get max complexity for this file
        file_name = Path(file_rel).name
        row = conn.execute("""
            SELECT MAX(COALESCE(m.cyclomatic_complexity, 0))
            FROM symbols s
            LEFT JOIN metrics m ON m.symbol_id = s.id
            WHERE s.file_path LIKE ?
        """, (f"%{file_name}",)).fetchone()
        
        max_cc = row[0] if row and row[0] else 0
        
        # Bug magnet score = commits × sqrt(complexity)
        import math
        score = data["commits"] * math.sqrt(max(max_cc, 1))
        
        churn_entries.append(ChurnEntry(
            file_path=file_rel,
            commits=data["commits"],
            lines_changed=0,  # Would need --stat parsing
            authors=list(data["authors"]),
            last_changed=data["last_changed"],
            complexity=max_cc,
            bug_magnet_score=round(score, 1),
        ))
    
    # Sort by bug magnet score
    churn_entries.sort(key=lambda e: e.bug_magnet_score, reverse=True)
    
    return churn_entries


# ── 6. Code Smell Detector ────────────────────────────────────────────

def detect_code_smells(db, language: str = "") -> list[CodeSmell]:
    """Detect structural anti-patterns in the codebase.
    
    Smells detected:
    - god_class: File with 1000+ total LOC across all functions
    - long_method: Function with 200+ LOC
    - long_params: Function with 8+ parameters
    - high_coupling: Function with fan_out > 30
    - deep_nesting: Function with nesting depth > 6
    - feature_envy: Model accessing 5+ different tables
    - shotgun_surgery: Function called from 20+ different files
    """
    conn = db._get_conn()
    smells: list[CodeSmell] = []
    
    lang_filter = "AND s.language = ?" if language else ""
    params = (language,) if language else ()
    
    # 1. God Class — files with massive total LOC
    file_locs = conn.execute(f"""
        SELECT s.file_path, SUM(COALESCE(m.lines_of_code, 0)) as total_loc,
               COUNT(*) as func_count
        FROM symbols s
        LEFT JOIN metrics m ON m.symbol_id = s.id
        WHERE 1=1 {lang_filter}
        GROUP BY s.file_path
        HAVING total_loc > 1000
        ORDER BY total_loc DESC
    """, params).fetchall()
    
    for file_path, total_loc, func_count in file_locs:
        smells.append(CodeSmell(
            smell_type="god_class",
            severity="HIGH" if total_loc > 3000 else "MEDIUM",
            location=Path(file_path).name,
            file=file_path,
            line=1,
            detail=f"{total_loc} total LOC across {func_count} functions — split into smaller classes",
            metric_value=total_loc,
        ))
    
    # 2. Long Method — functions over 200 LOC
    long_methods = conn.execute(f"""
        SELECT s.name, s.file_path, s.line, m.lines_of_code, m.cyclomatic_complexity
        FROM symbols s
        JOIN metrics m ON m.symbol_id = s.id
        WHERE m.lines_of_code > 200 {lang_filter}
        ORDER BY m.lines_of_code DESC
    """, params).fetchall()
    
    for name, file_path, line, loc, cc in long_methods:
        severity = "HIGH" if loc > 500 else "MEDIUM"
        smells.append(CodeSmell(
            smell_type="long_method",
            severity=severity,
            location=name,
            file=file_path,
            line=line,
            detail=f"{loc} lines, CC={cc} — extract sub-methods to reduce complexity",
            metric_value=loc,
        ))
    
    # 3. Long Parameter List — 8+ params
    long_params = conn.execute(f"""
        SELECT s.name, s.file_path, s.line, m.param_count
        FROM symbols s
        JOIN metrics m ON m.symbol_id = s.id
        WHERE m.param_count >= 8 {lang_filter}
        ORDER BY m.param_count DESC
    """, params).fetchall()
    
    for name, file_path, line, param_count in long_params:
        smells.append(CodeSmell(
            smell_type="long_params",
            severity="MEDIUM",
            location=name,
            file=file_path,
            line=line,
            detail=f"{param_count} parameters — use an options array or data object instead",
            metric_value=param_count,
        ))
    
    # 4. High Coupling — fan_out > 30
    high_coupling = conn.execute(f"""
        SELECT s.name, s.file_path, s.line, m.fan_out, m.fan_in
        FROM symbols s
        JOIN metrics m ON m.symbol_id = s.id
        WHERE m.fan_out > 30 {lang_filter}
        ORDER BY m.fan_out DESC
    """, params).fetchall()
    
    for name, file_path, line, fan_out, fan_in in high_coupling:
        smells.append(CodeSmell(
            smell_type="high_coupling",
            severity="HIGH" if fan_out > 50 else "MEDIUM",
            location=name,
            file=file_path,
            line=line,
            detail=f"Calls {fan_out} functions (called by {fan_in}) — too many dependencies, extract intermediaries",
            metric_value=fan_out,
        ))
    
    # 5. Deep Nesting — nesting > 6
    deep_nesting = conn.execute(f"""
        SELECT s.name, s.file_path, s.line, m.max_nesting_depth
        FROM symbols s
        JOIN metrics m ON m.symbol_id = s.id
        WHERE m.max_nesting_depth > 6 {lang_filter}
        ORDER BY m.max_nesting_depth DESC
    """, params).fetchall()
    
    for name, file_path, line, depth in deep_nesting:
        smells.append(CodeSmell(
            smell_type="deep_nesting",
            severity="HIGH" if depth > 8 else "MEDIUM",
            location=name,
            file=file_path,
            line=line,
            detail=f"Nesting depth {depth} — use early returns, guard clauses, or extract methods",
            metric_value=depth,
        ))
    
    # 6. Feature Envy — model/function accessing 5+ different tables
    feature_envy = conn.execute("""
        SELECT r.source_name, r.file_path, COUNT(DISTINCT r.target_name) as table_count
        FROM refs r
        WHERE r.call_type IN ('db_read', 'db_write')
        GROUP BY r.source_name, r.file_path
        HAVING table_count >= 5
        ORDER BY table_count DESC
    """).fetchall()
    
    for name, file_path, table_count in feature_envy:
        smells.append(CodeSmell(
            smell_type="feature_envy",
            severity="HIGH" if table_count > 8 else "MEDIUM",
            location=name,
            file=file_path,
            line=0,
            detail=f"Accesses {table_count} different tables — violates single responsibility, split by domain",
            metric_value=table_count,
        ))
    
    # 7. Shotgun Surgery — called from 20+ different files
    shotgun = conn.execute("""
        SELECT r.target_name, COUNT(DISTINCT r.file_path) as caller_files
        FROM refs r
        WHERE r.call_type IN ('calls', 'model_call')
        GROUP BY r.target_name
        HAVING caller_files >= 20
        ORDER BY caller_files DESC
    """).fetchall()
    
    for name, caller_files in shotgun:
        # Get file for this function
        row = conn.execute(
            "SELECT file_path, line FROM symbols WHERE name = ? LIMIT 1", (name,)
        ).fetchone()
        file_path = row[0] if row else "unknown"
        line = row[1] if row else 0
        
        smells.append(CodeSmell(
            smell_type="shotgun_surgery",
            severity="HIGH",
            location=name,
            file=file_path,
            line=line,
            detail=f"Called from {caller_files} different files — changes here ripple across the entire codebase",
            metric_value=caller_files,
        ))
    
    # Sort by severity then metric value
    severity_order = {"HIGH": 0, "MEDIUM": 1, "LOW": 2}
    smells.sort(key=lambda s: (severity_order.get(s.severity, 3), -s.metric_value))
    
    return smells
