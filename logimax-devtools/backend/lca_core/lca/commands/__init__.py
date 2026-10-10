"""
LCA CLI Command Groups — Registration Module

Registers existing cli.py functions under grouped sub-commands.
Old flat commands remain as hidden aliases for backward compat.

After:
    lca index build .              # new grouped form
    lca index .                    # old form still works (hidden)
    
    lca analyze impact func        # new grouped form
    lca impact func                # old form still works (hidden)
"""
import sys
import typer
from rich.console import Console

console = Console()

# ── Group Typer apps ──────────────────────────────────────────────────────────

index_app = typer.Typer(name="index", help="Indexing: build, update, watch")
analyze_app = typer.Typer(name="analyze", help="Code analysis: impact, search, crossref, modules, sql-map, deep")
quality_app = typer.Typer(name="quality", help="Quality: health, smells, n1, hardcoded, dead-code, duplicates, todos, guard")
risk_app = typer.Typer(name="risk", help="Risk: score, migration, diff, baseline")
test_app = typer.Typer(name="test", help="Testing: generate, run, coverage")
git_app = typer.Typer(name="git", help="Git tools: changeset, pr, commit-msg, release-notes")
brain_app = typer.Typer(name="brain", help="Knowledge brain: build, generate, health, flowmap, explain")
rag_app = typer.Typer(name="rag", help="Semantic search: search, ingest, stats, sync, status")
sdlc_app = typer.Typer(name="sdlc", help="Pipeline: review, plan, context")
ops_app = typer.Typer(name="ops", help="Operations: serve, ui, client-profile, client-diff, pre-release")


def _deprecation_notice(old: str, new: str):
    """Print a one-line deprecation notice to stderr."""
    sys.stderr.write(f"⚠️  '{old}' is deprecated. Use '{new}' instead.\n")


def register_all(app: typer.Typer):
    """Register all command groups + backward-compat aliases.
    
    Must be called AFTER all @app.command() functions are defined in cli.py.
    """
    # Import the cli module to access the command functions
    from lca import cli as cli_mod

    # ── Register group apps ───────────────────────────────────────────────
    app.add_typer(index_app)
    app.add_typer(analyze_app)
    app.add_typer(quality_app)
    app.add_typer(risk_app)
    app.add_typer(test_app)
    app.add_typer(git_app)
    app.add_typer(brain_app)
    app.add_typer(rag_app)
    app.add_typer(sdlc_app)
    app.add_typer(ops_app)

    # ── Index group ───────────────────────────────────────────────────────
    index_app.command("build")(cli_mod.index)
    index_app.command("update")(cli_mod.update)
    index_app.command("watch")(cli_mod.watch)

    # ── Analyze group ─────────────────────────────────────────────────────
    analyze_app.command("impact")(cli_mod.impact)
    analyze_app.command("search")(cli_mod.search)
    analyze_app.command("crossref")(cli_mod.crossref)
    analyze_app.command("modules")(cli_mod.modules)
    analyze_app.command("sql-map")(cli_mod.sql_map)
    analyze_app.command("deep")(cli_mod.deep_analyze)

    # ── Quality group ─────────────────────────────────────────────────────
    quality_app.command("health")(cli_mod.health)
    quality_app.command("report")(cli_mod.report)
    quality_app.command("smells")(cli_mod.smells)
    quality_app.command("rank")(cli_mod.quality)
    quality_app.command("n1")(cli_mod.n1)
    quality_app.command("hardcoded")(cli_mod.hardcoded)
    quality_app.command("dead-code")(cli_mod.reaper)
    quality_app.command("duplicates")(cli_mod.duplicates)
    quality_app.command("todos")(cli_mod.todo_scan)
    quality_app.command("guard")(cli_mod.guard_cmd)

    # ── Risk group ────────────────────────────────────────────────────────
    risk_app.command("score")(cli_mod.risk)
    risk_app.command("migration")(cli_mod.migration_check)
    risk_app.command("diff")(cli_mod.diff_health)
    # baseline is already a sub-typer — we'll add it under risk too
    
    # ── Test group ────────────────────────────────────────────────────────
    test_app.command("generate")(cli_mod.gen_test)
    test_app.command("run")(cli_mod.verify)
    test_app.command("coverage")(cli_mod.test_coverage)

    # ── Git group ─────────────────────────────────────────────────────────
    git_app.command("changeset")(cli_mod.changeset)
    git_app.command("pr")(cli_mod.pr)
    git_app.command("commit-msg")(cli_mod.commit_msg)
    git_app.command("release-notes")(cli_mod.release_notes_cmd)

    # ── Brain group ───────────────────────────────────────────────────────
    brain_app.command("generate")(cli_mod.brain)
    brain_app.command("build")(cli_mod.brain_build)
    brain_app.command("health")(cli_mod.brain_health)
    brain_app.command("flowmap")(cli_mod.flowmap)
    brain_app.command("explain")(cli_mod.explain_cmd)

    # ── RAG group ─────────────────────────────────────────────────────────
    rag_app.command("search")(cli_mod.semantic)
    rag_app.command("ingest")(cli_mod.semantic_ingest)
    rag_app.command("stats")(cli_mod.semantic_stats)
    rag_app.command("sync")(cli_mod.rag_sync)
    rag_app.command("status")(cli_mod.rag_status)

    # ── SDLC group ────────────────────────────────────────────────────────
    sdlc_app.command("review")(cli_mod.sdlc_review_cmd)
    sdlc_app.command("plan")(cli_mod.sdlc_plan_cmd)
    sdlc_app.command("context")(cli_mod.context)

    # ── Ops group ─────────────────────────────────────────────────────────
    ops_app.command("serve")(cli_mod.serve)
    ops_app.command("ui")(cli_mod.ui)
    ops_app.command("client-profile")(cli_mod.client_profile)
    ops_app.command("client-diff")(cli_mod.client_diff)
    ops_app.command("pre-release")(cli_mod.pre_release)
