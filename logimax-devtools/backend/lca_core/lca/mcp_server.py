"""MCP Server for Logimax Code Analyzer — v4.0 (Consolidated Super-Tools).

Exposes 5 super-tools to AI agents via Model Context Protocol:
  1. lca_investigate — Find, trace, and extract code patterns
  2. lca_analyze    — Code quality, smells, security, and health
  3. lca_search     — Structural and semantic code search
  4. lca_impact     — Change impact and blast radius analysis
  5. lca_manage     — Index management and setup tasks
"""

import asyncio
import json
import os
import subprocess
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any

from mcp.server import Server
from mcp.server.stdio import stdio_server
from mcp.types import Tool, TextContent

from lca.core import Indexer, ImpactAnalyzer
from lca.storage import save_index, load_index
from lca.storage.symbol_db import SymbolDB
from lca.models import ModuleIndex

# Global state
_indexes: dict[str, ModuleIndex] = {}
_default_index_path: Path | None = None
_symbol_db: SymbolDB | None = None

app = Server("logimax-code-analyzer")


def get_index(index_path: str | None = None) -> ModuleIndex | None:
    """Get an index from cache or load from file."""
    global _indexes, _default_index_path
    
    if index_path:
        path = Path(index_path)
    elif _default_index_path:
        path = _default_index_path
    else:
        return None
    
    path_str = str(path.absolute())
    
    if path_str not in _indexes:
        if path.exists():
            _indexes[path_str] = load_index(path)
        else:
            return None
    
    return _indexes[path_str]


def _text(msg: str) -> list[TextContent]:
    """Shorthand for returning a single text response."""
    return [TextContent(type="text", text=msg)]


def _error(msg: str) -> list[TextContent]:
    """Shorthand for returning an error response."""
    return [TextContent(type="text", text=f"Error: {msg}")]


def _need_index() -> list[TextContent]:
    """Standard error when no index is loaded."""
    return _error("No index loaded. Call lca_manage(action='load_index', path='...') or lca_manage(action='index', path='...', module_name='...') first.")


def _need_symbol_db() -> list[TextContent]:
    """Standard error when no SymbolDB is loaded."""
    return _error("No symbol database loaded. Call lca_manage(action='index_fullstack', path='...') first.")


# ═══════════════════════════════════════════════════════════════════════
# TOOL DEFINITIONS (5 super-tools)
# ═══════════════════════════════════════════════════════════════════════

@app.list_tools()
async def list_tools() -> list[Tool]:
    """List the 5 consolidated LCA super-tools."""
    return [
        # ── 1. lca_investigate ─────────────────────────────────────────
        Tool(
            name="lca_investigate",
            description=(
                "Find, trace, and extract code patterns. Use this to investigate HOW code works: "
                "who calls a function, what it calls, what tables it touches, what views it loads, "
                "what AJAX endpoints exist, and what business logic (formulas, accumulators, "
                "conditionals, magic numbers) is embedded in the code. "
                "Use action='explain' to get a full plain-English business flow narrative."
            ),
            inputSchema={
                "type": "object",
                "properties": {
                    "action": {
                        "type": "string",
                        "description": "What to investigate",
                        "enum": [
                            "callers", "callees", "chain", "table_usage",
                            "view_deps", "ajax_endpoints",
                            "formulas", "accumulators", "conditionals",
                            "magic_numbers", "deep_analyze", "explain"
                        ]
                    },
                    "function_name": {
                        "type": "string",
                        "description": "Function name (for callers/callees/chain/formulas/accumulators/conditionals/magic_numbers/deep_analyze/explain). For explain, partial/fuzzy names work."
                    },
                    "table_name": {
                        "type": "string",
                        "description": "Table name (for action=table_usage)"
                    },
                    "file_path": {
                        "type": "string",
                        "description": "File path (for deep_analyze, or filter for formulas/accumulators/etc.)"
                    },
                    "view_name": {
                        "type": "string",
                        "description": "View name (for action=view_deps reverse lookup)"
                    },
                    "controller": {
                        "type": "string",
                        "description": "Controller name filter (for view_deps forward lookup or ajax_endpoints)"
                    },
                    "variable": {
                        "type": "string",
                        "description": "Variable name filter (for action=conditionals)"
                    },
                    "value": {
                        "type": "string",
                        "description": "Value filter (for action=magic_numbers, exact match)"
                    },
                    "language": {
                        "type": "string",
                        "description": "Language filter: 'php' or 'javascript'"
                    },
                    "limit": {
                        "type": "integer",
                        "description": "Maximum results (default: 20)"
                    }
                },
                "required": ["action"]
            }
        ),

        # ── 2. lca_analyze ─────────────────────────────────────────────
        Tool(
            name="lca_analyze",
            description=(
                "Analyze code quality and find problems. Use this to check health scores, "
                "detect code smells, find security vulnerabilities, N+1 queries, dead code, "
                "duplicate code, get complexity metrics, scan for missing safety guards "
                "(parseFloat, toFixed, transactions, WHERE clauses), or detect validation "
                "gaps on write operations (financial amounts, permissions, duplicates)."
            ),
            inputSchema={
                "type": "object",
                "properties": {
                    "action": {
                        "type": "string",
                        "description": "What to analyze",
                        "enum": [
                            "health", "smells", "security", "n1",
                            "dead_code", "duplicates", "metrics",
                            "hotspots", "quality", "guard", "validate"
                        ]
                    },
                    "path": {
                        "type": "string",
                        "description": "File or directory path (for health/security/n1)"
                    },
                    "function_name": {
                        "type": "string",
                        "description": "Function name (for action=metrics)"
                    },
                    "metric": {
                        "type": "string",
                        "description": "Metric name for hotspots: cyclomatic_complexity, cognitive_complexity, lines_of_code, param_count, max_nesting_depth, fan_in, fan_out, coupling_score",
                        "enum": ["cyclomatic_complexity", "cognitive_complexity", "lines_of_code", "param_count", "max_nesting_depth", "fan_in", "fan_out", "coupling_score"]
                    },
                    "severity": {
                        "type": "string",
                        "description": "Min severity for security: CRITICAL, HIGH, MEDIUM, LOW (default: MEDIUM)"
                    },
                    "smell_type": {
                        "type": "string",
                        "description": "Filter by smell type: god_class, long_method, long_params, high_coupling, deep_nesting, feature_envy, shotgun_surgery",
                        "enum": ["god_class", "long_method", "long_params", "high_coupling", "deep_nesting", "feature_envy", "shotgun_surgery"]
                    },
                    "language": {
                        "type": "string",
                        "description": "Language filter: 'php' or 'javascript'"
                    },
                    "threshold": {
                        "type": "integer",
                        "description": "Minimum value for hotspots (default: 15)"
                    },
                    "limit": {
                        "type": "integer",
                        "description": "Maximum results (default: 20)"
                    }
                },
                "required": ["action"]
            }
        ),

        # ── 3. lca_search ──────────────────────────────────────────────
        Tool(
            name="lca_search",
            description=(
                "Search for code. Two modes: "
                "'functions' = exact name search (fast, structural). "
                "'semantic' = natural language search using AI embeddings (finds conceptually related code even without exact name match). "
                "Use 'functions' when you know the name. Use 'semantic' when you have a concept like 'discount calculation' or 'stock transfer'."
            ),
            inputSchema={
                "type": "object",
                "properties": {
                    "action": {
                        "type": "string",
                        "description": "Search mode",
                        "enum": ["functions", "semantic"]
                    },
                    "query": {
                        "type": "string",
                        "description": "Search term (exact name for 'functions', natural language for 'semantic')"
                    },
                    "module": {
                        "type": "string",
                        "description": "Filter by module: billing, estimation, stock, customer, purchase, accounts, reports, repair, employee, notification, settings, order, scheme, auth, crm"
                    },
                    "chunk_type": {
                        "type": "string",
                        "description": "Filter by code type (semantic only): controller_method, model_method, helper_function, js_function, documentation, whole_file"
                    },
                    "top_n": {
                        "type": "integer",
                        "description": "Number of results (default: 10, max: 20)"
                    },
                    "limit": {
                        "type": "integer",
                        "description": "Maximum results for structural search (default: 20)"
                    }
                },
                "required": ["action", "query"]
            }
        ),

        # ── 4. lca_impact ──────────────────────────────────────────────
        Tool(
            name="lca_impact",
            description=(
                "Analyze what would break if you changed a function or file. "
                "Shows callers, indirect callers, AJAX routes affected, tables accessed, "
                "views loaded, risk level, and blast radius."
            ),
            inputSchema={
                "type": "object",
                "properties": {
                    "action": {
                        "type": "string",
                        "description": "Impact scope",
                        "enum": ["function", "file"]
                    },
                    "function_name": {
                        "type": "string",
                        "description": "Function name (for action=function)"
                    },
                    "file_path": {
                        "type": "string",
                        "description": "File path (for action=file)"
                    },
                    "depth": {
                        "type": "integer",
                        "description": "How many levels of callers to trace (default: 2)"
                    }
                },
                "required": ["action"]
            }
        ),

        # ── 5. lca_manage ──────────────────────────────────────────────
        Tool(
            name="lca_manage",
            description=(
                "Setup and admin: index a module, build full-stack index, load a saved index, "
                "or create a changeset manifest. These are setup tasks, not diagnosis tools."
            ),
            inputSchema={
                "type": "object",
                "properties": {
                    "action": {
                        "type": "string",
                        "description": "Management action",
                        "enum": ["index", "index_fullstack", "load_index", "changeset"]
                    },
                    "path": {
                        "type": "string",
                        "description": "Path to index/scan/project root"
                    },
                    "module_name": {
                        "type": "string",
                        "description": "Module name (for action=index)"
                    },
                    "output_path": {
                        "type": "string",
                        "description": "Output file path (optional)"
                    },
                    "base_ref": {
                        "type": "string",
                        "description": "Git ref for changeset diff (default: HEAD~1)"
                    }
                },
                "required": ["action"]
            }
        ),
    ]


# ═══════════════════════════════════════════════════════════════════════
# HANDLER IMPLEMENTATIONS (organized by super-tool)
# ═══════════════════════════════════════════════════════════════════════

# ── lca_investigate handlers ──────────────────────────────────────────

def _handle_callers(args: dict) -> list[TextContent]:
    function_name = args.get("function_name", "")
    if not function_name:
        return _error("function_name is required for action=callers")
    
    idx = get_index()
    if not idx:
        return _need_index()
    
    analyzer = ImpactAnalyzer(idx)
    callers = analyzer.get_callers(function_name)
    
    if not callers:
        return _text(f"No callers found for: {function_name}")
    
    output = f"## Functions that call {function_name}\n\n"
    for caller, line in callers:
        output += f"- {caller} (line {line})\n"
    
    return _text(output)


def _handle_callees(args: dict) -> list[TextContent]:
    function_name = args.get("function_name", "")
    if not function_name:
        return _error("function_name is required for action=callees")
    
    idx = get_index()
    if not idx:
        return _need_index()
    
    analyzer = ImpactAnalyzer(idx)
    callees = analyzer.get_callees(function_name)
    
    if not callees:
        return _text(f"No callees found for: {function_name}")
    
    output = f"## Functions called by {function_name}\n\n"
    for callee, line in callees:
        output += f"- {callee} (line {line})\n"
    
    return _text(output)


def _handle_chain(args: dict) -> list[TextContent]:
    function_name = args.get("function_name", "")
    if not function_name:
        return _error("function_name is required for action=chain")
    
    idx = get_index()
    if not idx:
        return _need_index()
    
    analyzer = ImpactAnalyzer(idx)
    output = f"## Full Chain: {function_name}\n\n"
    
    # Layer 1: JS callers (AJAX calls TO this function)
    ajax_callers = [
        c for c in idx.calls
        if c.call_type == "ajax_call" and c.target == function_name
    ]
    if ajax_callers:
        output += f"### JS Callers (AJAX) [{len(ajax_callers)}]\n"
        for c in ajax_callers[:10]:
            meta = c.metadata or {}
            output += f"- {c.source} [{meta.get('http_method', '?')}] ({Path(c.file).name}:L{c.line})\n"
        output += "\n"
    
    # Layer 2: What this function calls
    callees = analyzer.get_callees(function_name)
    model_deps = [(t, l) for t, l in callees if "_model::" in t.lower() or "::" in t]
    local_deps = [(t, l) for t, l in callees if "::" not in t]
    
    if model_deps:
        output += f"### Model Dependencies [{len(model_deps)}]\n"
        for target, line in model_deps[:15]:
            output += f"- {target} (L{line})\n"
        output += "\n"
    
    if local_deps:
        output += f"### Local Calls [{len(local_deps)}]\n"
        for target, line in local_deps[:10]:
            output += f"- {target} (L{line})\n"
        if len(local_deps) > 10:
            output += f"- ... +{len(local_deps) - 10} more\n"
        output += "\n"
    
    # Layer 3: DB tables touched
    db_calls = [
        c for c in idx.calls
        if c.source == function_name and c.call_type.startswith("db_")
    ]
    if db_calls:
        output += f"### DB Tables [{len(db_calls)}]\n"
        for c in db_calls:
            op = "READ" if c.call_type == "db_read" else "WRITE"
            output += f"- [{op}] {c.target} (L{c.line})\n"
        output += "\n"
    
    # Layer 4: Views loaded
    view_calls = [
        c for c in idx.calls
        if c.source == function_name and c.call_type == "load_view"
    ]
    if view_calls:
        output += f"### Views [{len(view_calls)}]\n"
        for c in view_calls:
            output += f"- {c.target} (L{c.line})\n"
        output += "\n"
    
    if not ajax_callers and not model_deps and not db_calls:
        output += "No chain data found. Ensure the full-stack index is loaded.\n"
    
    return _text(output)


def _handle_table_usage(args: dict) -> list[TextContent]:
    table_name = args.get("table_name", "")
    if not table_name:
        return _error("table_name is required for action=table_usage")
    
    idx = get_index()
    if not idx:
        return _need_index()
    
    table_target = f"TABLE:{table_name}"
    readers, writers, view_refs = [], [], []
    
    for call in idx.calls:
        if call.target == table_target:
            entry = {"func": call.source, "line": call.line, "file": call.file}
            if call.call_type == "db_read":
                readers.append(entry)
            elif call.call_type == "db_write":
                writers.append(entry)
            elif call.call_type == "view_reads":
                view_refs.append(entry)
    
    output = f"## Table Usage: {table_name}\n\n"
    
    if readers:
        output += f"### Readers ({len(readers)})\n"
        for r in readers[:20]:
            output += f"- {r['func']} (L{r['line']} in {Path(r['file']).name})\n"
        if len(readers) > 20:
            output += f"- ... +{len(readers) - 20} more\n"
        output += "\n"
    
    if writers:
        output += f"### Writers ({len(writers)})\n"
        for w in writers[:20]:
            output += f"- {w['func']} (L{w['line']} in {Path(w['file']).name})\n"
        if len(writers) > 20:
            output += f"- ... +{len(writers) - 20} more\n"
        output += "\n"
    
    if view_refs:
        output += f"### SQL Views ({len(view_refs)})\n"
        for v in view_refs:
            output += f"- {v['func']} ({Path(v['file']).name})\n"
        output += "\n"
    
    if not readers and not writers and not view_refs:
        output += "No references found in the index.\n"
    else:
        output += f"**Total:** {len(readers)} reads, {len(writers)} writes, {len(view_refs)} view refs\n"
    
    return _text(output)


def _handle_view_deps(args: dict) -> list[TextContent]:
    view_name = args.get("view_name", "")
    controller_filter = args.get("controller", "")
    
    idx = get_index()
    if not idx:
        return _need_index()
    
    view_calls = [c for c in idx.calls if c.call_type == "load_view"]
    
    if view_name:
        view_lower = view_name.lower()
        matches = [c for c in view_calls if view_lower in c.target.lower()]
        if not matches:
            return _text(f"No controllers found that load view: {view_name}")
        output = f"## Controllers loading view: {view_name}\n\n"
        for c in matches:
            output += f"- {c.source} (L{c.line} in {Path(c.file).name}) -> {c.target}\n"
        return _text(output)
    
    elif controller_filter:
        filter_lower = controller_filter.lower()
        matches = [c for c in view_calls if filter_lower in c.source.lower()]
        if not matches:
            return _text(f"No views loaded by controller matching: {controller_filter}")
        output = f"## Views loaded by: {controller_filter}\n\n"
        by_func: dict[str, list] = defaultdict(list)
        for c in matches:
            by_func[c.source].append(c)
        for func, calls_list in sorted(by_func.items()):
            output += f"### {func}\n"
            for c in calls_list:
                output += f"- {c.target} (L{c.line})\n"
            output += "\n"
        return _text(output)
    
    else:
        by_view: dict[str, list] = defaultdict(list)
        for c in view_calls:
            by_view[c.target].append(c.source)
        output = f"## View Usage Summary ({len(by_view)} views)\n\n"
        for view, controllers in sorted(by_view.items()):
            unique = sorted(set(controllers))
            output += f"- **{view}** ← {', '.join(unique[:5])}"
            if len(unique) > 5:
                output += f" +{len(unique)-5} more"
            output += "\n"
        return _text(output)


def _handle_ajax_endpoints(args: dict) -> list[TextContent]:
    controller_filter = args.get("controller", "")
    limit = args.get("limit", 50)
    
    idx = get_index()
    if not idx:
        return _need_index()
    
    ajax_calls = [c for c in idx.calls if c.call_type == "ajax_call"]
    
    if controller_filter:
        filter_lower = controller_filter.lower()
        ajax_calls = [c for c in ajax_calls if filter_lower in c.target.lower()]
    
    if not ajax_calls:
        return _text(f"No AJAX endpoints found{' for: ' + controller_filter if controller_filter else ''}.")
    
    by_target: dict[str, list] = defaultdict(list)
    for c in ajax_calls:
        by_target[c.target].append(c)
    
    output = f"## AJAX Endpoints"
    if controller_filter:
        output += f" (filter: {controller_filter})"
    output += f"\n\n**{len(by_target)} unique endpoints from {len(ajax_calls)} calls**\n\n"
    
    for target, calls_list in sorted(by_target.items())[:limit]:
        meta = calls_list[0].metadata or {}
        http = meta.get("http_method", "?")
        js_callers = set(c.source for c in calls_list)
        js_files = set(Path(c.file).name for c in calls_list)
        output += f"- **{target}** [{http}] ← {', '.join(js_callers)} ({', '.join(js_files)})\n"
    
    if len(by_target) > limit:
        output += f"\n... +{len(by_target) - limit} more endpoints\n"
    
    return _text(output)


def _handle_deep_extract(action: str, args: dict) -> list[TextContent]:
    """Handle formulas, accumulators, conditionals, magic_numbers.
    
    Uses SymbolDB (SQLite) when available for indexed queries,
    falls back to in-memory ModuleIndex for backward compat.
    """
    # Fast path: use SymbolDB if loaded
    if _symbol_db:
        return _handle_deep_extract_sqlite(action, args)
    
    # Fallback: use in-memory index
    idx = get_index()
    if not idx:
        return _need_index()
    
    # Map action to index attribute
    attr_map = {
        "formulas": "formulas",
        "accumulators": "accumulators",
        "conditionals": "conditionals",
        "magic_numbers": "magic_numbers",
    }
    attr = attr_map.get(action)
    if not attr:
        return _error(f"Unknown deep extract action: {action}")
    
    data = getattr(idx, attr, None)
    if not data:
        return _text(f"No {action} found. Re-index with deep_analyze=True (v3.1+).")
    
    results = list(data)
    func_filter = args.get("function_name", "").lower()
    file_filter = args.get("file_path", "").lower()
    lang_filter = args.get("language", "").lower()
    limit = args.get("limit", 50)
    
    if func_filter:
        results = [r for r in results if func_filter in r.function.lower()]
    if file_filter:
        results = [r for r in results if file_filter in r.file.lower()]
    if lang_filter:
        results = [r for r in results if r.language == lang_filter]
    
    # Extra filters for specific types
    if action == "conditionals" and args.get("variable"):
        var_filter = args["variable"].lower()
        results = [r for r in results if var_filter in r.variable.lower()]
    if action == "magic_numbers" and args.get("value"):
        results = [r for r in results if r.value == args["value"]]
    
    results = results[:limit]
    output = json.dumps([r.model_dump() for r in results], indent=2)
    return _text(f"Found {len(results)} {action}:\n{output}")


def _handle_deep_extract_sqlite(action: str, args: dict) -> list[TextContent]:
    """BI query via SQLite — indexed, instant."""
    func_filter = args.get("function_name", "")
    file_filter = args.get("file_path", "")
    limit = args.get("limit", 50)
    
    if action == "formulas":
        results = _symbol_db.get_formulas(
            function=func_filter, file=file_filter,
            variable=args.get("variable", ""), limit=limit,
        )
    elif action == "accumulators":
        results = _symbol_db.get_accumulators(
            function=func_filter,
            variable=args.get("variable", ""), limit=limit,
        )
    elif action == "conditionals":
        results = _symbol_db.get_conditionals(
            variable=args.get("variable", ""),
            value=args.get("value", ""),
            function=func_filter, limit=limit,
        )
    elif action == "magic_numbers":
        results = _symbol_db.get_magic_numbers(
            function=func_filter,
            value=args.get("value", ""),
            variable=args.get("variable", ""), limit=limit,
        )
    else:
        return _error(f"Unknown deep extract action: {action}")
    
    if not results:
        return _text(f"No {action} found matching filters.")
    
    output = json.dumps(results, indent=2)
    return _text(f"Found {len(results)} {action} (SQLite indexed):\n{output}")


def _handle_deep_analyze_file(args: dict) -> list[TextContent]:
    """On-demand deep analysis of a single file."""
    from lca.analysis.code_extractor import CodeExtractor
    
    file_path = Path(args.get("file_path", ""))
    if not file_path or not file_path.exists():
        return _error(f"File not found: {file_path}")
    
    extractor = CodeExtractor()
    formulas, accumulators, conditionals, magic_numbers = extractor.analyze_file(file_path)
    
    func_filter = args.get("function_name", "").lower()
    if func_filter:
        formulas = [f for f in formulas if func_filter in f.function.lower()]
        accumulators = [a for a in accumulators if func_filter in a.function.lower()]
        conditionals = [c for c in conditionals if func_filter in c.function.lower()]
        magic_numbers = [m for m in magic_numbers if func_filter in m.function.lower()]
    
    result = {
        "file": str(file_path),
        "summary": {
            "formulas": len(formulas),
            "accumulators": len(accumulators),
            "conditionals": len(conditionals),
            "magic_numbers": len(magic_numbers),
        },
        "formulas": [f.model_dump() for f in formulas[:50]],
        "accumulators": [a.model_dump() for a in accumulators[:50]],
        "conditionals": [c.model_dump() for c in conditionals[:50]],
        "magic_numbers": [m.model_dump() for m in magic_numbers[:50]],
    }
    return _text(json.dumps(result, indent=2))


# ── lca_analyze handlers ─────────────────────────────────────────────

def _handle_health(args: dict) -> list[TextContent]:
    from lca.analysis.health import compute_health
    from lca.analysis.n1 import scan_directory as n1_scan
    from lca.analysis.hardcoded import scan_directory as hc_scan
    
    target = Path(args.get("path", "."))
    if not target.exists():
        return _error(f"Path not found: {target}")
    
    n1_result = n1_scan(target, target)
    hc_result = hc_scan(target, target)
    
    report = compute_health(
        target=target.name,
        files_analyzed=n1_result.files_scanned + hc_result.files_scanned,
        n1_count=len(n1_result.findings),
        n1_critical=n1_result.critical_count,
        hardcoded_count=len(hc_result.findings),
        hardcoded_sql=hc_result.sql_count,
    )
    
    output = f"## Health Score: {report.target}\n\n"
    output += f"**Score:** {report.score:.0f}/100 | **Grade:** {report.grade}\n\n"
    output += "| Category | Score | Weight | Weighted | Findings | Detail |\n|---|---|---|---|---|---|\n"
    for s in report.sub_scores:
        output += f"| {s.name} | {s.score:.0f} | {s.weight:.0%} | {s.score*s.weight:.1f} | {s.findings_count} | {s.detail} |\n"
    
    return _text(output)


def _handle_smells(args: dict) -> list[TextContent]:
    if not _symbol_db:
        return _need_symbol_db()
    
    from lca.analysis.intelligence import detect_code_smells as _smells
    smell_type = args.get("smell_type", "")
    language = args.get("language", "")
    limit = args.get("limit", 30)
    
    smells = _smells(_symbol_db, language)
    if smell_type:
        smells = [s for s in smells if s.smell_type == smell_type]
    
    by_type = Counter(s.smell_type for s in smells)
    by_sev = Counter(s.severity for s in smells)
    
    output = f"## Code Smells\n\n"
    output += f"**Total:** {len(smells)} | **HIGH:** {by_sev.get('HIGH', 0)} | **MEDIUM:** {by_sev.get('MEDIUM', 0)}\n\n"
    
    output += f"| Type | Count |\n|---|---|\n"
    for st, count in by_type.most_common():
        output += f"| {st} | {count} |\n"
    
    output += f"\n### Top Smells\n\n"
    output += f"| Severity | Type | Location | Detail |\n|---|---|---|---|\n"
    for s in smells[:limit]:
        output += f"| {s.severity} | {s.smell_type} | {s.location} | {s.detail[:80]} |\n"
    
    return _text(output)


def _handle_security(args: dict) -> list[TextContent]:
    from lca.analysis.security import scan_file, CI3SecurityScanner
    
    target = Path(args.get("path", ""))
    if not target or not target.exists():
        return _error(f"Path not found: {target}")
    
    min_severity = args.get("severity", "MEDIUM")
    severity_order = ["CRITICAL", "HIGH", "MEDIUM", "LOW"]
    min_idx = severity_order.index(min_severity) if min_severity in severity_order else 2
    
    all_findings = []
    file_count = 0
    
    if target.is_file():
        result = scan_file(target)
        all_findings = result.findings
        file_count = 1
    else:
        for php_file in sorted(target.rglob("*.php")):
            try:
                result = scan_file(php_file)
                all_findings.extend(result.findings)
                file_count += 1
            except Exception:
                pass
    
    filtered = [f for f in all_findings if severity_order.index(f.severity) <= min_idx]
    sev_counts = Counter(f.severity for f in filtered)
    rule_counts = Counter(f.rule_id for f in filtered)
    
    output = f"## Security Scan Results\n\n"
    output += f"**Scanned:** {file_count} files | **Findings:** {len(filtered)} (filter: >= {min_severity})\n\n"
    output += f"| Severity | Count |\n|---|---|\n"
    for sev in severity_order:
        if sev_counts.get(sev, 0) > 0:
            output += f"| {sev} | {sev_counts[sev]} |\n"
    
    output += f"\n| Rule | Count | Description |\n|---|---|---|\n"
    for rule, count in rule_counts.most_common():
        title = next((f.title for f in filtered if f.rule_id == rule), "")
        output += f"| {rule} | {count} | {title} |\n"
    
    critical = [f for f in filtered if f.severity == "CRITICAL"]
    if critical:
        output += f"\n### Critical Findings\n\n"
        for f in critical[:15]:
            output += f"- **[{f.rule_id}]** `{f.function}` ({Path(f.file).name}:L{f.line})\n"
            output += f"  `{f.snippet[:100]}`\n"
            output += f"  -> {f.recommendation}\n\n"
    
    return _text(output)


def _handle_n1(args: dict) -> list[TextContent]:
    from lca.analysis.n1 import scan_directory, detect_n1_in_file
    
    target = Path(args.get("path", ""))
    if not target or not target.exists():
        return _error(f"Path not found: {target}")
    
    root = Path(args.get("project_root", str(target.parent if target.is_file() else target)))
    
    if target.is_file():
        findings = detect_n1_in_file(target, root)
        files = 1
    else:
        result = scan_directory(target, root)
        findings = result.findings
        files = result.files_scanned
    
    output = f"## N+1 Query Detection\n\n"
    output += f"**Scanned:** {files} files | **Findings:** {len(findings)}\n\n"
    
    crit = sum(1 for f in findings if f.severity == "CRITICAL")
    high = sum(1 for f in findings if f.severity == "HIGH")
    med = sum(1 for f in findings if f.severity == "MEDIUM")
    
    output += f"| Severity | Count |\n|---|---|\n"
    if crit: output += f"| CRITICAL | {crit} |\n"
    if high: output += f"| HIGH | {high} |\n"
    if med: output += f"| MEDIUM | {med} |\n"
    output += "\n"
    
    if findings:
        output += "| Sev | Function | Pattern | File | Loop | DB Line |\n|---|---|---|---|---|---|\n"
        for f in findings[:30]:
            output += f"| {f.severity} | {f.function} | {f.pattern} | {Path(f.file).name} | {f.loop_type} L{f.loop_line} | L{f.db_line} |\n"
    
    return _text(output)


def _handle_dead_code(args: dict) -> list[TextContent]:
    if not _symbol_db:
        return _need_symbol_db()
    
    from lca.analysis.intelligence import find_dead_code as _find_dead
    language = args.get("language", "")
    limit = args.get("limit", 30)
    
    result = _find_dead(_symbol_db, language)
    
    output = f"## Dead Code Analysis\n\n"
    output += f"**Dead functions:** {len(result.dead_functions)} / {result.total_functions} ({result.dead_percentage:.1f}%)\n"
    output += f"**Entry points excluded:** {result.entry_points_excluded}\n"
    output += f"**Fully dead files:** {len(result.dead_files)}\n\n"
    
    if result.dead_files:
        output += f"### Dead Files (all functions uncalled)\n\n"
        output += f"| File | Functions | LOC |\n|---|---|---|\n"
        for df in result.dead_files[:10]:
            output += f"| {Path(df['file']).name} | {df['dead_functions']} | {df['total_loc']} |\n"
        output += "\n"
    
    output += f"### Top Dead Functions (by LOC)\n\n"
    output += f"| # | Function | LOC | CC | File |\n|---|---|---|---|---|\n"
    for i, d in enumerate(result.dead_functions[:limit], 1):
        output += f"| {i} | {d['name']} | {d['loc']} | {d['complexity']} | {Path(d['file']).name} |\n"
    
    return _text(output)


def _handle_duplicates(args: dict) -> list[TextContent]:
    if not _symbol_db:
        return _need_symbol_db()
    
    from lca.analysis.intelligence import find_duplicates as _find_dupes
    min_lines = args.get("min_lines", 20)
    similarity = args.get("similarity", 0.7)
    limit = args.get("limit", 20)
    
    clones = _find_dupes(_symbol_db, min_lines, similarity)
    
    output = f"## Clone Detection\n\n"
    output += f"**Clone pairs found:** {len(clones)}\n"
    output += f"**Total duplicated lines:** {sum(c.shared_lines for c in clones)}\n\n"
    
    output += f"| # | Similarity | Lines | Function A | Function B |\n|---|---|---|---|---|\n"
    for i, c in enumerate(clones[:limit], 1):
        output += f"| {i} | {c.similarity:.0%} | {c.shared_lines} | {c.func_a} ({Path(c.file_a).name}) | {c.func_b} ({Path(c.file_b).name}) |\n"
    
    return _text(output)


def _handle_metrics(args: dict) -> list[TextContent]:
    if not _symbol_db:
        return _need_symbol_db()
    
    function_name = args.get("function_name", "")
    if not function_name:
        return _error("function_name is required for action=metrics")
    
    m = _symbol_db.get_metrics(function_name)
    if not m:
        return _text(f"No metrics found for: {function_name}")
    
    output = f"## Metrics: {function_name}\n\n"
    output += f"| Metric | Value |\n|---|---|\n"
    output += f"| Cyclomatic Complexity | {m.get('cyclomatic_complexity', '?')} |\n"
    output += f"| Cognitive Complexity | {m.get('cognitive_complexity', '?')} |\n"
    output += f"| Lines of Code | {m.get('lines_of_code', '?')} |\n"
    output += f"| Parameters | {m.get('param_count', '?')} |\n"
    output += f"| Max Nesting Depth | {m.get('max_nesting_depth', '?')} |\n"
    output += f"| Fan-In (callers) | {m.get('fan_in', '?')} |\n"
    output += f"| Fan-Out (callees) | {m.get('fan_out', '?')} |\n"
    output += f"| Coupling Score | {m.get('coupling_score', '?')} |\n"
    output += f"\n**File:** {m.get('file_path', '?')} L{m.get('line', '?')}\n"
    
    cc = m.get('cyclomatic_complexity', 0) or 0
    if cc > 50: risk = 'CRITICAL'
    elif cc > 25: risk = 'HIGH'
    elif cc > 15: risk = 'MEDIUM'
    else: risk = 'LOW'
    output += f"\n**Risk Level:** {risk}\n"
    
    return _text(output)


def _handle_hotspots(args: dict) -> list[TextContent]:
    if not _symbol_db:
        return _need_symbol_db()
    
    metric = args.get("metric", "")
    if not metric:
        return _error("metric is required for action=hotspots")
    
    threshold = args.get("threshold", 15)
    language = args.get("language", "")
    limit = args.get("limit", 20)
    
    hotspots = _symbol_db.get_hotspots(metric, threshold, limit, language)
    
    if not hotspots:
        return _text(f"No functions with {metric} >= {threshold}")
    
    output = f"## Hotspots: {metric} >= {threshold}"
    if language:
        output += f" ({language})"
    output += f"\n\n| # | Value | CC | Cog | LOC | Fan | Function |\n|---|---|---|---|---|---|---|\n"
    
    for i, h in enumerate(hotspots, 1):
        output += f"| {i} | **{h.get('value', '?')}** | {h.get('cyclomatic_complexity', '?')} | {h.get('cognitive_complexity', '?')} | {h.get('lines_of_code', '?')} | {h.get('fan_in', 0) or 0}←{h.get('fan_out', 0) or 0} | {h['name']} |\n"
    
    return _text(output)


def _handle_quality(args: dict) -> list[TextContent]:
    if not _symbol_db:
        return _need_symbol_db()
    
    language = args.get("language", "")
    summary = _symbol_db.get_metrics_summary(language)
    stats = _symbol_db.get_stats()
    
    high_cc = summary.get("high_complexity", 0) or 0
    high_cog = summary.get("high_cognitive", 0) or 0
    long_funcs = summary.get("long_functions", 0) or 0
    total = summary.get("total_functions", 1) or 1
    
    issue_ratio = (high_cc * 3 + high_cog * 2 + long_funcs) / (total * 6)
    if issue_ratio < 0.05: grade = 'A'
    elif issue_ratio < 0.10: grade = 'B'
    elif issue_ratio < 0.20: grade = 'C'
    elif issue_ratio < 0.35: grade = 'D'
    else: grade = 'F'
    
    output = f"## Quality Report"
    if language:
        output += f" ({language})"
    output += f" — Grade: **{grade}**\n\n"
    
    output += f"### Metrics Summary\n"
    output += f"| Metric | Avg | Max | Threshold Exceeded |\n|---|---|---|---|\n"
    output += f"| Cyclomatic Complexity | {summary.get('avg_cyclomatic', 0):.1f} | {summary.get('max_cyclomatic', 0)} | {high_cc} functions > 20 |\n"
    output += f"| Cognitive Complexity | {summary.get('avg_cognitive', 0):.1f} | {summary.get('max_cognitive', 0)} | {high_cog} functions > 25 |\n"
    output += f"| Lines of Code | {summary.get('avg_loc', 0):.1f} | {summary.get('max_loc', 0)} | {long_funcs} functions > 100 |\n"
    output += f"| Nesting Depth | {summary.get('avg_nesting', 0):.1f} | {summary.get('max_nesting', 0)} | — |\n\n"
    
    output += f"### Index Stats\n"
    output += f"- **Functions:** {stats.get('symbols', 0)}\n"
    output += f"- **Call References:** {stats.get('references', 0)}\n"
    output += f"- **Resolved:** {stats.get('resolved', 0)} ({stats.get('resolution_rate', '?')})\n"
    output += f"- **Files:** {stats.get('files', 0)}\n\n"
    
    ct = stats.get('call_types', {})
    if ct:
        output += f"### Call Types\n"
        output += f"| Type | Count |\n|---|---|\n"
        for ctype, count in ct.items():
            output += f"| {ctype} | {count} |\n"
    
    return _text(output)


# ── lca_search handlers ──────────────────────────────────────────────

def _handle_search_functions(args: dict) -> list[TextContent]:
    query = args.get("query", "")
    if not query:
        return _error("query is required")
    
    limit = args.get("limit", 20)
    
    idx = get_index()
    if not idx:
        return _need_index()
    
    query_lower = query.lower().replace("*", "")
    matches = [f for f in idx.functions if query_lower in f.name.lower()][:limit]
    
    if not matches:
        return _text(f"No functions found matching: {query}")
    
    output = f"## Functions matching '{query}'\n\n"
    for f in matches:
        output += f"- **{f.name}** ({Path(f.file).name}:{f.line}) [{f.language}]\n"
    
    return _text(output)


def _handle_semantic_search(args: dict) -> list[TextContent]:
    try:
        from lca.semantic.store import query_chunks, hybrid_query_chunks
    except ImportError:
        return _error("chromadb not installed. Run: pip install chromadb")
    
    query = args.get("query", "")
    if not query:
        return _error("query is required")
    
    store_dir = os.path.join(os.path.expanduser("~"), ".logimax", "rag_store_bge")
    collection = args.get("collection", "etail_v3")
    top_n = min(args.get("top_n", 5), 20)
    
    where_filter = {}
    if args.get("module"):
        where_filter["module"] = args["module"]
    if args.get("chunk_type"):
        where_filter["chunk_type"] = args["chunk_type"]
    
    results = hybrid_query_chunks(
        query, store_dir, collection,
        n_results=top_n,
        where_filter=where_filter if where_filter else None,
    )
    
    if not results:
        return _text(f"No semantic results for: {query}\nTip: Run 'lca semantic-ingest' to build the vector index.")
    
    output = f"## Semantic Search: \"{query}\" (hybrid: vector + keyword)\n\n"
    for i, r in enumerate(results, 1):
        meta = r['metadata']
        relevance = max(0, (1 - r['distance']) * 100)
        title = meta.get('summary', r['id'])
        fp = meta.get('file_path', '?')
        mod = meta.get('module', '?')
        ct = meta.get('chunk_type', '?')
        sl = meta.get('start_line', '?')
        el = meta.get('end_line', '?')
        
        output += f"### #{i} {title} ({relevance:.0f}% relevant)\n"
        output += f"- **File:** {fp}:L{sl}-L{el}\n"
        output += f"- **Module:** {mod} | **Type:** {ct}\n"
        if meta.get('class_name'):
            output += f"- **Class:** {meta['class_name']}\n"
        if meta.get('method_name'):
            output += f"- **Method:** {meta['method_name']}\n"
        if meta.get('tables'):
            output += f"- **Tables:** {meta['tables']}\n"
        if meta.get('models'):
            output += f"- **Models:** {meta['models']}\n"
        
        doc = r.get('document', '')
        code_start = doc.find('Code:\n')
        if code_start >= 0:
            code = doc[code_start + 6:]
            code_lines = code.split('\n')[:30]
            code_text = '\n'.join(code_lines)
            output += f"\n```php\n{code_text}\n```\n"
        output += "\n"
    
    return _text(output)


# ── lca_impact handlers ──────────────────────────────────────────────

def _handle_impact_function(args: dict) -> list[TextContent]:
    function_name = args.get("function_name", "")
    if not function_name:
        return _error("function_name is required for action=function")
    
    depth = args.get("depth", 2)
    
    idx = get_index()
    if not idx:
        return _need_index()
    
    analyzer = ImpactAnalyzer(idx)
    result = analyzer.analyze(function_name, depth=depth)
    
    callers = [c for c in result.calls if c["type"] == "called_by"]
    callees = [c for c in result.calls if c["type"] == "calls"]
    
    output = f"## Impact Analysis: {function_name}\n\n"
    output += f"**Risk Level:** {result.risk_level}\n\n"
    
    if callers:
        output += f"### Called By ({len(callers)} callers)\n"
        for c in callers[:10]:
            output += f"- {c['target']} (line {c['line']})\n"
        if len(callers) > 10:
            output += f"- ... +{len(callers) - 10} more\n"
        output += "\n"
    
    if callees:
        output += f"### Calls ({len(callees)} dependencies)\n"
        for c in callees[:10]:
            output += f"- {c['target']} (line {c['line']})\n"
        if len(callees) > 10:
            output += f"- ... +{len(callees) - 10} more\n"
        output += "\n"
    
    if result.functions:
        f = result.functions[0]
        output += f"**Defined at:** {f.file}:{f.line}\n"
    
    return _text(output)


def _handle_impact_file(args: dict) -> list[TextContent]:
    if not _symbol_db:
        return _need_symbol_db()
    
    from lca.analysis.intelligence import analyze_change_impact as _impact
    file_path = args.get("file_path", "")
    if not file_path:
        return _error("file_path is required for action=file")
    
    impact = _impact(_symbol_db, file_path)
    
    output = f"## Change Impact: {Path(file_path).name}\n\n"
    output += f"**Risk Level: {impact.risk_level}**\n\n"
    output += f"| Category | Count |\n|---|---|\n"
    output += f"| Direct callers | {len(impact.direct_callers)} |\n"
    output += f"| Indirect callers | {len(impact.indirect_callers)} |\n"
    output += f"| AJAX routes affected | {len(impact.ajax_routes_affected)} |\n"
    output += f"| Tables accessed | {len(impact.tables_affected)} |\n"
    output += f"| Views affected | {len(impact.views_affected)} |\n"
    output += f"| JS functions affected | {len(impact.js_functions_affected)} |\n\n"
    
    if impact.direct_callers:
        output += f"### Direct Callers\n\n"
        for c in impact.direct_callers[:20]:
            output += f"- `{c['function']}` ({Path(c['file']).name}:L{c['line']})\n"
        output += "\n"
    
    if impact.tables_affected:
        output += f"### Tables\n\n"
        for t in impact.tables_affected[:20]:
            output += f"- {t['table']} ({t['operation']})\n"
    
    return _text(output)


# ── lca_manage handlers ──────────────────────────────────────────────

def _handle_manage_index(args: dict) -> list[TextContent]:
    global _indexes, _default_index_path
    
    path = Path(args.get("path", ""))
    module_name = args.get("module_name", "")
    if not path or not module_name:
        return _error("path and module_name are required for action=index")
    
    if not path.exists():
        return _error(f"Path does not exist: {path}")
    
    indexer = Indexer()
    if path.is_file():
        result = indexer.index_file(path, module_name)
    else:
        result = indexer.index_directory(path, module_name, recursive=True)
    
    output_path = args.get("output_path")
    out_path = Path(output_path) if output_path else Path(f"./{module_name}_index.json")
    save_index(result, out_path)
    
    _indexes[str(out_path.absolute())] = result
    _default_index_path = out_path.absolute()
    
    return _text(
        f"✓ Indexed {len(result.functions)} functions and {len(result.calls)} call relationships.\n"
        f"Output: {out_path}"
    )


def _handle_manage_fullstack(args: dict) -> list[TextContent]:
    global _indexes, _default_index_path, _symbol_db
    
    project_root = Path(args.get("path", ""))
    if not project_root or not project_root.exists():
        return _error(f"Project root not found: {project_root}")
    
    indexer = Indexer()
    targets = [
        (project_root / "admin" / "application" / "controllers", "controllers"),
        (project_root / "admin" / "application" / "models", "models"),
        (project_root / "admin" / "assets" / "js", "js"),
        (project_root / "database" / "views", "sql_views"),
    ]
    
    all_functions = []
    all_calls = []
    parts = []
    
    for dir_path, name in targets:
        if not dir_path.exists():
            parts.append(f"⚠ {name}: directory not found")
            continue
        result = indexer.index_directory(dir_path, name)
        all_functions.extend(result.functions)
        all_calls.extend(result.calls)
        parts.append(f"✓ {name}: {len(result.functions)} functions, {len(result.calls)} calls")
    
    indexer._resolve_cross_file_calls(all_functions, all_calls)
    
    combined = ModuleIndex(
        module="fullstack",
        functions=all_functions,
        calls=all_calls,
        files=[],
        ui_matches=[],
    )
    
    output_path = args.get("output_path")
    out = Path(output_path) if output_path else Path("./fullstack_index.json")
    save_index(combined, out)
    
    _indexes[str(out.absolute())] = combined
    _default_index_path = out.absolute()
    
    resolved = sum(1 for c in all_calls if c.target_file)
    ajax_count = sum(1 for c in all_calls if c.call_type == "ajax_call")
    model_count = sum(1 for c in all_calls if c.call_type == "model_call")
    db_count = sum(1 for c in all_calls if c.call_type.startswith("db_"))
    
    output = f"✓ Full-stack index built\n\n"
    output += "\n".join(parts) + "\n\n"
    output += f"**Totals:** {len(all_functions)} functions, {len(all_calls)} calls\n"
    output += f"**Resolved:** {resolved} ({resolved*100//max(len(all_calls),1)}%)\n"
    output += f"**AJAX:** {ajax_count} | **Model:** {model_count} | **DB:** {db_count}\n"
    output += f"**Output:** {out}\n"
    
    db_path = out.with_suffix(".db")
    _symbol_db = SymbolDB(db_path)
    db_stats = _symbol_db.import_index(combined)
    output += f"\n**SQLite DB:** {db_path} ({db_stats.get('metrics', 0)} metrics computed)\n"
    
    return _text(output)


def _handle_manage_load(args: dict) -> list[TextContent]:
    global _indexes, _default_index_path, _symbol_db
    
    index_path = args.get("path", "")
    if not index_path:
        return _error("path is required for action=load_index")
    
    path = Path(index_path)
    
    # Support both .json and .db paths
    json_path = path.with_suffix(".json") if path.suffix == ".db" else path
    db_path = path.with_suffix(".db") if path.suffix == ".json" else path
    
    # Strategy: prefer SQLite if it exists and is at least as new as JSON
    use_sqlite = False
    if db_path.exists():
        if not json_path.exists():
            use_sqlite = True
        elif db_path.stat().st_mtime >= json_path.stat().st_mtime:
            use_sqlite = True
    
    if use_sqlite:
        # Fast path: load from SQLite directly
        _symbol_db = SymbolDB(db_path)
        stats = _symbol_db.get_stats()
        bi = _symbol_db.get_bi_summary()
        
        # Also export to in-memory ModuleIndex for handlers that still use it
        idx = _symbol_db.export_to_index()
        _indexes[str(db_path.absolute())] = idx
        _default_index_path = db_path.absolute()
        
        return _text(
            f"✓ Loaded index from SQLite: {db_path.name}\n"
            f"  - {stats['symbols']} functions, {stats['references']} calls\n"
            f"  - BI: {bi.get('formulas', 0)} formulas, {bi.get('conditionals', 0)} conditionals, "
            f"{bi.get('magic_numbers', 0)} magic numbers\n"
            f"  - Resolution: {stats.get('resolution_rate', '?')}"
        )
    
    # Fallback: load from JSON
    if not json_path.exists():
        return _error(f"Index file not found: {path}")
    
    idx = load_index(json_path)
    _indexes[str(json_path.absolute())] = idx
    _default_index_path = json_path.absolute()
    
    # Auto-migrate: build SQLite from JSON for future fast loads
    _symbol_db = SymbolDB(db_path)
    db_stats = _symbol_db.import_index(idx)
    bi_counts = db_stats.get('formulas', {})
    
    return _text(
        f"✓ Loaded index: {idx.module}\n"
        f"  - {len(idx.functions)} functions\n"
        f"  - {len(idx.calls)} call relationships\n"
        f"  - SQLite DB created: {db_path.name} (future loads will be instant)\n"
        f"  - BI imported: {bi_counts}"
    )


def _handle_manage_changeset(args: dict) -> list[TextContent]:
    from lca.workflow.changeset import create_from_git
    
    root = Path(args.get("path", ""))
    if not root:
        return _error("path is required for action=changeset")
    
    base = args.get("base_ref", "HEAD~1")
    cs = create_from_git(root, base)
    
    output = f"## Changeset: {cs.id}\n\n"
    output += f"| Metric | Value |\n|---|---|\n"
    output += f"| Files | {len(cs.files)} |\n"
    output += f"| Risk | {cs.risk_level} ({cs.risk_score}/100) |\n"
    output += f"| Modules | {', '.join(cs.modules_affected) or 'N/A'} |\n"
    output += f"| Author | {cs.author} |\n\n"
    
    if cs.files:
        output += "| Status | File | +/- | Module |\n|---|---|---|---|\n"
        for f in cs.files[:30]:
            output += f"| {f.status} | `{f.path}` | +{f.additions}/-{f.deletions} | {f.module} |\n"
    
    output += f"\n### Suggested Commit Message\n```\n{cs.to_commit_message()}\n```\n"
    
    return _text(output)


# ── lca_investigate: explain handler ──────────────────────────────────

def _handle_explain(args: dict) -> list[TextContent]:
    """Generate a plain-English business flow explanation for a function."""
    function_name = args.get("function_name", "")
    if not function_name:
        return _error("function_name is required for action=explain")

    idx = get_index()
    if not idx:
        return _need_index()

    from lca.analysis.flow_explainer import FlowExplainer

    explainer = FlowExplainer(idx)
    depth = args.get("limit", 3)  # Reuse limit param as depth
    result = explainer.explain(function_name, depth=depth)

    # Return structured markdown + JSON summary for programmatic use
    output = result.markdown

    # Append JSON summary at the end for agents
    summary_json = json.dumps({
        "target": result.target,
        "file": result.target_file,
        "steps": len(result.steps),
        "tables": result.tables_touched,
        "callers": result.total_callers,
        "risk": result.risk_level,
        "layers": list(set(s.layer for s in result.steps)),
    }, indent=2)
    output += f"\n\n<details><summary>JSON Summary</summary>\n\n```json\n{summary_json}\n```\n</details>"

    return _text(output)


# ── lca_analyze: guard handler ────────────────────────────────────────

def _handle_guard(args: dict) -> list[TextContent]:
    """Scan for missing safety checks in business logic code."""
    path = args.get("path", "")
    if not path:
        return _error("path is required for action=guard")

    from lca.analysis.guard import GuardScanner

    p = Path(path)
    if not p.exists():
        return _error(f"Path not found: {path}")

    scanner = GuardScanner()
    module_filter = args.get("language", "")  # Reuse language as module filter

    if p.is_file():
        violations = scanner.scan_file(p)
        files_count = 1
    else:
        report = scanner.scan_directory(p, module_filter=module_filter or None)
        violations = report.violations
        files_count = report.files_scanned

    if not violations:
        return _text(f"No guard violations found in {path} ({files_count} files scanned).")

    limit = args.get("limit", 30)
    severity_filter = args.get("severity", "").upper()
    if severity_filter:
        violations = [v for v in violations if v.severity == severity_filter]

    # Sort by severity
    sev_order = {'CRITICAL': 0, 'HIGH': 1, 'MEDIUM': 2, 'LOW': 3}
    violations.sort(key=lambda v: sev_order.get(v.severity, 99))

    output = f"## Guard Scan: {Path(path).name}\n\n"
    output += f"**{len(violations)} violations** found in {files_count} files\n\n"

    # Summary by rule
    from collections import Counter
    rule_counts = Counter(v.rule_id for v in violations)
    output += "| Rule | Count | Severity |\n|---|---|---|\n"
    for rule_id, count in rule_counts.most_common():
        sev = next(v.severity for v in violations if v.rule_id == rule_id)
        output += f"| {rule_id} | {count} | {sev} |\n"
    output += "\n"

    # Details
    for v in violations[:limit]:
        output += f"### {v.rule_id} [{v.severity}] — {v.title}\n"
        fname = Path(v.file).name
        output += f"Location: `{fname}:L{v.line}` in `{v.function}`\n"
        if v.snippet:
            output += f"```\n{v.snippet}\n```\n"
        output += f"{v.description}\n"
        if v.recommendation:
            output += f"**Fix:** {v.recommendation}\n"
        output += "\n"

    if len(violations) > limit:
        output += f"\n... +{len(violations) - limit} more violations\n"

    return _text(output)


# ── lca_analyze: validate handler ─────────────────────────────────────

def _handle_validate(args: dict) -> list[TextContent]:
    """Scan for missing input/data validations on write operations."""
    path = args.get("path", "")
    if not path:
        return _error("path is required for action=validate")

    from lca.analysis.validate import ValidationScanner

    p = Path(path)
    if not p.exists():
        return _error(f"Path not found: {path}")

    scanner = ValidationScanner()
    module_filter = args.get("language", "")  # Reuse language as module filter

    if p.is_file():
        gaps = scanner.scan_file(p)
        files_count = 1
    else:
        report = scanner.scan_directory(p, module_filter=module_filter or None)
        gaps = report.gaps
        files_count = report.files_scanned

    if not gaps:
        return _text(f"No validation gaps found in {path} ({files_count} files scanned).")

    limit = args.get("limit", 30)
    severity_filter = args.get("severity", "").upper()
    if severity_filter:
        gaps = [g for g in gaps if g.severity == severity_filter]

    # Sort by severity
    sev_order = {'CRITICAL': 0, 'HIGH': 1, 'MEDIUM': 2, 'LOW': 3}
    gaps.sort(key=lambda g: sev_order.get(g.severity, 99))

    output = f"## Validation Gap Scan: {Path(path).name}\n\n"
    output += f"**{len(gaps)} validation gaps** found in {files_count} files\n\n"

    # Summary by rule
    from collections import Counter
    rule_counts = Counter(g.rule_id for g in gaps)
    table_counts = Counter(g.table for g in gaps if g.table)

    output += "| Rule | Count | Severity |\n|---|---|---|\n"
    for rule_id, count in rule_counts.most_common():
        sev = next(g.severity for g in gaps if g.rule_id == rule_id)
        output += f"| {rule_id} | {count} | {sev} |\n"
    output += "\n"

    if table_counts:
        output += "### Most Affected Tables\n"
        output += "| Table | Gaps |\n|---|---|\n"
        for table, count in table_counts.most_common(10):
            output += f"| {table} | {count} |\n"
        output += "\n"

    # Details
    for g in gaps[:limit]:
        output += f"### {g.rule_id} [{g.severity}] — {g.title}\n"
        fname = Path(g.file).name
        output += f"Location: `{fname}:L{g.line}` in `{g.function}`\n"
        if g.table:
            output += f"Table: `{g.table}`\n"
        if g.snippet:
            output += f"```\n{g.snippet}\n```\n"
        output += f"{g.description}\n"
        if g.recommendation:
            output += f"**Fix:** {g.recommendation}\n"
        output += "\n"

    if len(gaps) > limit:
        output += f"\n... +{len(gaps) - limit} more gaps\n"

    return _text(output)


# ═══════════════════════════════════════════════════════════════════════
# MAIN DISPATCHER
# ═══════════════════════════════════════════════════════════════════════

# Action dispatch tables
_INVESTIGATE_DISPATCH = {
    "callers": _handle_callers,
    "callees": _handle_callees,
    "chain": _handle_chain,
    "table_usage": _handle_table_usage,
    "view_deps": _handle_view_deps,
    "ajax_endpoints": _handle_ajax_endpoints,
    "deep_analyze": _handle_deep_analyze_file,
    "explain": _handle_explain,
}

_ANALYZE_DISPATCH = {
    "health": _handle_health,
    "smells": _handle_smells,
    "security": _handle_security,
    "n1": _handle_n1,
    "dead_code": _handle_dead_code,
    "duplicates": _handle_duplicates,
    "metrics": _handle_metrics,
    "hotspots": _handle_hotspots,
    "quality": _handle_quality,
    "guard": _handle_guard,
    "validate": _handle_validate,
}

_SEARCH_DISPATCH = {
    "functions": _handle_search_functions,
    "semantic": _handle_semantic_search,
}

_IMPACT_DISPATCH = {
    "function": _handle_impact_function,
    "file": _handle_impact_file,
}

_MANAGE_DISPATCH = {
    "index": _handle_manage_index,
    "index_fullstack": _handle_manage_fullstack,
    "load_index": _handle_manage_load,
    "changeset": _handle_manage_changeset,
}


@app.call_tool()
async def call_tool(name: str, arguments: dict[str, Any]) -> list[TextContent]:
    """Route tool calls to the appropriate handler."""
    try:
        action = arguments.get("action", "")
        
        if name == "lca_investigate":
            # Deep extract actions use a shared handler
            if action in ("formulas", "accumulators", "conditionals", "magic_numbers"):
                return _handle_deep_extract(action, arguments)
            handler = _INVESTIGATE_DISPATCH.get(action)
            
        elif name == "lca_analyze":
            handler = _ANALYZE_DISPATCH.get(action)
            
        elif name == "lca_search":
            handler = _SEARCH_DISPATCH.get(action)
            
        elif name == "lca_impact":
            handler = _IMPACT_DISPATCH.get(action)
            
        elif name == "lca_manage":
            handler = _MANAGE_DISPATCH.get(action)
            
        else:
            return _error(f"Unknown tool: {name}")
        
        if not handler:
            valid = {
                "lca_investigate": list(_INVESTIGATE_DISPATCH.keys()) + ["formulas", "accumulators", "conditionals", "magic_numbers", "explain"],
                "lca_analyze": list(_ANALYZE_DISPATCH.keys()),
                "lca_search": list(_SEARCH_DISPATCH.keys()),
                "lca_impact": list(_IMPACT_DISPATCH.keys()),
                "lca_manage": list(_MANAGE_DISPATCH.keys()),
            }
            valid_actions = valid.get(name, [])
            return _error(f"Unknown action '{action}' for {name}. Valid actions: {', '.join(valid_actions)}")
        
        return handler(arguments)
    
    except Exception as e:
        return _error(str(e))


# ═══════════════════════════════════════════════════════════════════════
# SERVER ENTRY POINT
# ═══════════════════════════════════════════════════════════════════════

async def main():
    """Run the MCP server."""
    async with stdio_server() as (read_stream, write_stream):
        await app.run(read_stream, write_stream, app.create_initialization_options())


def run_server():
    """Entry point for running the server."""
    asyncio.run(main())


if __name__ == "__main__":
    run_server()
