# LCA MCP Tools — Agent Routing Guide

> **Version:** 4.0 (Consolidated Super-Tools)
> **Last Updated:** 2026-06-11

## Quick Reference

| When you need to... | Tool | Action |
|---|---|---|
| Find who calls a function | `lca_investigate` | `callers` |
| Find what a function calls | `lca_investigate` | `callees` |
| Trace JS → Controller → Model → DB | `lca_investigate` | `chain` |
| Find all CRUD on a table | `lca_investigate` | `table_usage` |
| Map view ↔ controller | `lca_investigate` | `view_deps` |
| List AJAX endpoints | `lca_investigate` | `ajax_endpoints` |
| Find arithmetic formulas | `lca_investigate` | `formulas` |
| Find running totals (+=) | `lca_investigate` | `accumulators` |
| Find if/switch dispatch tables | `lca_investigate` | `conditionals` |
| Find hardcoded magic values | `lca_investigate` | `magic_numbers` |
| Deep-analyze a single file | `lca_investigate` | `deep_analyze` |
| Get health score (A-F) | `lca_analyze` | `health` |
| Find code smells | `lca_analyze` | `smells` |
| Scan for security issues | `lca_analyze` | `security` |
| Find N+1 query patterns | `lca_analyze` | `n1` |
| Find unreachable functions | `lca_analyze` | `dead_code` |
| Find code clones | `lca_analyze` | `duplicates` |
| Get function complexity metrics | `lca_analyze` | `metrics` |
| Find high-complexity functions | `lca_analyze` | `hotspots` |
| Get full quality report | `lca_analyze` | `quality` |
| Search by exact function name | `lca_search` | `functions` |
| Search by natural language | `lca_search` | `semantic` |
| Impact of changing a function | `lca_impact` | `function` |
| Impact of changing a file | `lca_impact` | `file` |
| Index a module | `lca_manage` | `index` |
| Build full-stack index | `lca_manage` | `index_fullstack` |
| Load a saved index | `lca_manage` | `load_index` |
| Create git changeset | `lca_manage` | `changeset` |

---

## Tool Selection Guide

### "I'm fixing a bug — where do I start?"

1. **`lca_search(action="functions", query="<function_name>")`** — Find the function
2. **`lca_investigate(action="chain", function_name="<name>")`** — See full JS→PHP→DB flow
3. **`lca_impact(action="function", function_name="<name>")`** — Check blast radius before changing

### "What does this table do?"

1. **`lca_investigate(action="table_usage", table_name="<name>")`** — Who reads/writes
2. **`lca_investigate(action="formulas", function_name="<writer>")`** — How values are computed

### "Is this code safe to change?"

1. **`lca_impact(action="file", file_path="<path>")`** — Full file impact analysis
2. **`lca_investigate(action="callers", function_name="<name>")`** — All upstream callers

### "Something about billing calculations is wrong"

1. **`lca_search(action="semantic", query="billing discount calculation")`** — Find relevant code
2. **`lca_investigate(action="formulas", function_name="<found_function>")`** — See all formulas
3. **`lca_investigate(action="accumulators", function_name="<found_function>")`** — Find running totals

### "I need to audit code quality"

1. **`lca_analyze(action="health", path="<directory>")`** — Quick health score
2. **`lca_analyze(action="quality")`** — Full metrics report
3. **`lca_analyze(action="hotspots", metric="cyclomatic_complexity", threshold=20)`** — Worst offenders

---

## Migration from Old Tool Names

| Old Tool (DEPRECATED) | New Equivalent |
|---|---|
| `get_callers` | `lca_investigate(action="callers")` |
| `get_callees` | `lca_investigate(action="callees")` |
| `get_full_chain` | `lca_investigate(action="chain")` |
| `get_table_usage` | `lca_investigate(action="table_usage")` |
| `get_view_deps` | `lca_investigate(action="view_deps")` |
| `get_ajax_endpoints` | `lca_investigate(action="ajax_endpoints")` |
| `extract_formulas` | `lca_investigate(action="formulas")` |
| `extract_accumulators` | `lca_investigate(action="accumulators")` |
| `extract_conditionals` | `lca_investigate(action="conditionals")` |
| `extract_magic_numbers` | `lca_investigate(action="magic_numbers")` |
| `scan_hardcoded` | `lca_investigate(action="magic_numbers")` |
| `deep_analyze_file` | `lca_investigate(action="deep_analyze")` |
| `api_docs` | `lca_investigate(action="ajax_endpoints")` |
| `get_health_score` | `lca_analyze(action="health")` |
| `code_smells` | `lca_analyze(action="smells")` |
| `scan_security` | `lca_analyze(action="security")` |
| `scan_n1` | `lca_analyze(action="n1")` |
| `find_dead_code` | `lca_analyze(action="dead_code")` |
| `find_duplicates` | `lca_analyze(action="duplicates")` |
| `duplicates` | `lca_analyze(action="duplicates")` |
| `get_metrics` | `lca_analyze(action="metrics")` |
| `get_hotspots` | `lca_analyze(action="hotspots")` |
| `get_quality_report` | `lca_analyze(action="quality")` |
| `search_functions` | `lca_search(action="functions")` |
| `semantic_search` | `lca_search(action="semantic")` |
| `get_impact` | `lca_impact(action="function")` |
| `change_impact` | `lca_impact(action="file")` |
| `index_module` | `lca_manage(action="index")` |
| `index_fullstack` | `lca_manage(action="index_fullstack")` |
| `load_index` | `lca_manage(action="load_index")` |
| `get_changeset` | `lca_manage(action="changeset")` |

### Removed from MCP (CLI-only)

These tools are no longer available via MCP. Use the CLI instead:

| Old Tool | CLI Command |
|---|---|
| `diff_health` | `lca diff-health <old> <new>` |
| `migration_check` | `lca migration-check <path>` |
| `release_notes` | `lca release-notes <path>` |
| `get_test_coverage` | `lca test-coverage` |
| `git_churn` | (use git log analysis) |
| `todo_scan` | `lca todo-scan <path>` |
| `sql_map` | `lca sql-map <path>` |
