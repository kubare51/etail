"""Logimax Code Analyzer CLI."""

from pathlib import Path
from typing import Optional

import typer
from rich.console import Console
from rich.table import Table
from rich.panel import Panel

from lca.core import Indexer, ImpactAnalyzer
from lca.storage import save_index, load_index
from lca import __version__

app = typer.Typer(
    name="lca",
    help="Logimax Code Analyzer - Code analysis and impact tracking tool",
    add_completion=False,
)
console = Console()

def version_callback(value: bool):
    if value:
        console.print(f"LCA v{__version__}")
        raise typer.Exit()

@app.callback()
def main(
    version: Optional[bool] = typer.Option(
        None, "--version", callback=version_callback, is_eager=True, help="Show version"
    ),
):
    """Logimax Code Analyzer CLI."""
    pass


@app.command()
def init(
    path: Path = typer.Argument(".", help="Project root directory"),
):
    """Initialize a new .lca.toml config file in the project root."""
    from lca.config import generate_default_config

    config_path = path / ".lca.toml"
    if config_path.exists():
        console.print(f"[yellow]Config already exists:[/yellow] {config_path}")
        console.print("[dim]Use --force to overwrite (not implemented yet)[/dim]")
        return

    config_path.write_text(generate_default_config(), encoding='utf-8')
    console.print(f"[green]Created:[/green] {config_path}")
    console.print("[dim]Edit this file to customize health weights, scan exclusions, and whitelists.[/dim]")


@app.command()
def index(
    path: Path = typer.Argument(..., help="Path to file or directory to index"),
    module: str = typer.Option(..., "--module", "-m", help="Module name for the index"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Output JSON file path"),
    recursive: bool = typer.Option(True, "--recursive/--no-recursive", "-r/-R", help="Recurse into subdirectories"),
):
    """Index a file or directory and generate a dependency map."""
    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)
    
    console.print(f"[cyan]Indexing:[/cyan] {path}")
    
    indexer = Indexer()
    
    if path.is_file():
        result = indexer.index_file(path, module)
    else:
        result = indexer.index_directory(path, module, recursive=recursive)
    
    # Determine output path
    if output is None:
        output = Path(f"./{module}_index.json")
    
    save_index(result, output)
    
    # Build SQLite SymbolDB alongside JSON for smells/duplicates/reaper
    db_path = output.with_suffix(".db")
    try:
        from lca.storage.symbol_db import SymbolDB
        sdb = SymbolDB(db_path)
        db_stats = sdb.import_index(result)
        sdb.close()
        db_info = f"\n[green][+][/green] Symbol DB: [cyan]{db_path}[/cyan] ({db_stats.get('metrics', 0)} metrics)"
    except Exception as e:
        db_info = f"\n[yellow][!][/yellow] Symbol DB skipped: {e}"
    
    # Display summary
    console.print(Panel.fit(
        f"[green][+][/green] Indexed [bold]{len(result.functions)}[/bold] functions\n"
        f"[green][+][/green] Found [bold]{len(result.calls)}[/bold] call relationships\n"
        f"[green][+][/green] Output: [cyan]{output}[/cyan]"
        f"{db_info}",
        title=f"Module: {module}",
        border_style="green"
    ))


@app.command()
def update(
    index_file: Path = typer.Argument(..., help="Path to existing index JSON file to update"),
    repo_root: Path = typer.Option(..., "--repo", "-r", help="Git repository root directory"),
    since: str = typer.Option("ORIG_HEAD", "--since", "-s", help="Git ref to diff against (default: ORIG_HEAD = last pull)"),
    files: Optional[str] = typer.Option(None, "--files", "-f", help="Comma-separated file paths to reindex (overrides git diff)"),
):
    """Incrementally update an index -- only re-parse changed files."""
    if not index_file.exists():
        console.print(f"[red]Error:[/red] Index file does not exist: {index_file}")
        raise typer.Exit(1)
    
    from lca.incremental import incremental_reindex, get_changed_files
    
    # Determine which files changed
    if files:
        # Manual file list
        changed = [Path(f.strip()) for f in files.split(",") if Path(f.strip()).exists()]
    else:
        # Auto-detect from git
        changed = get_changed_files(repo_root, since)
    
    if not changed:
        console.print("[green]Index is up-to-date.[/green] No supported files changed.")
        return
    
    # Show what's being updated
    console.print(f"[cyan]Incremental update:[/cyan] {len(changed)} file(s) changed")
    for f in changed[:10]:
        console.print(f"  [dim]->[/dim] {f.name}")
    if len(changed) > 10:
        console.print(f"  [dim]... +{len(changed) - 10} more[/dim]")
    
    # Run incremental reindex
    import time
    start = time.time()
    
    updated, stats = incremental_reindex(index_file, changed)
    save_index(updated, index_file)
    
    elapsed = time.time() - start
    
    # Display results
    console.print(Panel.fit(
        f"[green][+][/green] Updated in [bold]{elapsed:.1f}s[/bold]\n"
        f"[green][+][/green] Files re-parsed: [bold]{stats['files_changed']}[/bold]\n"
        f"[green][+][/green] Functions: -{stats['functions_removed']} +{stats['functions_added']} = [bold]{stats['total_functions']}[/bold]\n"
        f"[green][+][/green] Calls: -{stats['calls_removed']} +{stats['calls_added']} = [bold]{stats['total_calls']}[/bold]",
        title="Incremental Reindex",
        border_style="green"
    ))


@app.command()
def impact(
    function: str = typer.Argument(..., help="Function name to analyze"),
    index_file: Path = typer.Option(..., "--index", "-i", help="Path to index JSON file"),
    depth: int = typer.Option(2, "--depth", "-d", help="Depth of dependency analysis"),
):
    """Analyze the impact of changes to a function."""
    if not index_file.exists():
        console.print(f"[red]Error:[/red] Index file does not exist: {index_file}")
        raise typer.Exit(1)
    
    idx = load_index(index_file)
    analyzer = ImpactAnalyzer(idx)
    result = analyzer.analyze(function, depth=depth)
    
    # Risk badge
    risk_colors = {"Low": "green", "Medium": "yellow", "High": "red"}
    risk_color = risk_colors.get(result.risk_level, "white")
    
    console.print(Panel.fit(
        f"[bold]{result.term}[/bold] | Risk: [{risk_color}]{result.risk_level}[/{risk_color}]",
        title="Impact Analysis",
        border_style="cyan"
    ))
    
    # Callers table
    callers = [c for c in result.calls if c["type"] == "called_by"]
    if callers:
        table = Table(title="Called By (Upstream)", show_header=True)
        table.add_column("Function", style="magenta")
        table.add_column("Line", style="dim")
        for c in callers[:10]:
            table.add_row(c["target"], str(c["line"]))
        if len(callers) > 10:
            table.add_row(f"... +{len(callers) - 10} more", "")
        console.print(table)
    
    # Calls table
    calls = [c for c in result.calls if c["type"] == "calls"]
    if calls:
        table = Table(title="Calls (Downstream)", show_header=True)
        table.add_column("Function", style="blue")
        table.add_column("Line", style="dim")
        for c in calls[:10]:
            table.add_row(c["target"], str(c["line"]))
        if len(calls) > 10:
            table.add_row(f"... +{len(calls) - 10} more", "")
        console.print(table)
    
    # Definition
    if result.functions:
        f = result.functions[0]
        console.print(f"\n[dim]Defined at:[/dim] {f.file}:{f.line}")


@app.command()
def search(
    query: str = typer.Argument(..., help="Search query (supports wildcards)"),
    index_file: Path = typer.Option(..., "--index", "-i", help="Path to index JSON file"),
    limit: int = typer.Option(20, "--limit", "-l", help="Maximum results"),
):
    """Search for functions by name."""
    if not index_file.exists():
        console.print(f"[red]Error:[/red] Index file does not exist: {index_file}")
        raise typer.Exit(1)
    
    idx = load_index(index_file)
    
    # Simple search (case-insensitive contains)
    query_lower = query.lower().replace("*", "")
    matches = [f for f in idx.functions if query_lower in f.name.lower()][:limit]
    
    if not matches:
        console.print(f"[yellow]No functions found matching:[/yellow] {query}")
        return
    
    table = Table(title=f"Functions matching '{query}'", show_header=True)
    table.add_column("Function", style="cyan")
    table.add_column("File", style="dim")
    table.add_column("Line", style="dim")
    table.add_column("Language", style="magenta")
    
    for f in matches:
        table.add_row(f.name, Path(f.file).name, str(f.line), f.language)
    
    console.print(table)


@app.command()
def crossref(
    path: Path = typer.Argument(..., help="Path to JS file or directory"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Output JSON file (optional)"),
):
    """Extract JS->PHP API calls (cross-language dependencies)."""
    from lca.parsers.crossref import extract_api_calls
    import json
    
    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)
    
    console.print(f"[cyan]Analyzing:[/cyan] {path}")
    
    # Collect all JS files
    if path.is_file():
        files = [path] if path.suffix in ('.js', '.jsx', '.mjs') else []
    else:
        files = list(path.rglob("*.js")) + list(path.rglob("*.jsx"))
    
    all_calls = []
    for f in files:
        calls = extract_api_calls(f)
        all_calls.extend(calls)
    
    # Group by controller
    controllers = {}
    for call in all_calls:
        key = call.controller
        if key not in controllers:
            controllers[key] = []
        controllers[key].append(call)
    
    # Display results
    console.print(Panel.fit(
        f"[green][+][/green] Found [bold]{len(all_calls)}[/bold] API calls\n"
        f"[green][+][/green] To [bold]{len(controllers)}[/bold] PHP controllers",
        title="Cross-Reference Analysis",
        border_style="blue"
    ))
    
    for ctrl, calls in sorted(controllers.items()):
        table = Table(title=f"Controller: {ctrl}", show_header=True)
        table.add_column("JS Function", style="cyan")
        table.add_column("PHP Method", style="magenta")
        table.add_column("Line", style="dim")
        table.add_column("HTTP", style="yellow")
        
        for c in calls[:10]:
            table.add_row(c.source_function, c.method, str(c.line), c.http_method)
        if len(calls) > 10:
            table.add_row(f"... +{len(calls) - 10} more", "", "", "")
        console.print(table)
    
    # Save if output specified
    if output:
        data = {
            "api_calls": [
                {
                    "source": c.source_function,
                    "controller": c.controller,
                    "method": c.method,
                    "line": c.line,
                    "file": c.file,
                    "http_method": c.http_method
                }
                for c in all_calls
            ]
        }
        with open(output, "w", encoding="utf-8") as f:
            json.dump(data, f, indent=2)
        console.print(f"[green]✓[/green] Saved to: {output}")


@app.command()
def serve(
    port: int = typer.Option(3100, "--port", "-p", help="Port for MCP server (not used in stdio mode)"),
):
    """Start the MCP server for AI agent integration (stdio mode)."""
    # NOTE: Do NOT print anything to stdout - MCP uses stdio for protocol communication
    # Any output here will corrupt the MCP handshake
    import sys
    sys.stderr.write("LCA MCP Server starting in stdio mode...\n")
    sys.stderr.flush()
    
    from lca.mcp_server import run_server
    run_server()


@app.command()
def watch(
    path: Path = typer.Argument(..., help="Path to file or directory to watch"),
    module: str = typer.Option(..., "--module", "-m", help="Module name for the index"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Output JSON file path"),
    debounce: float = typer.Option(2.0, "--debounce", "-d", help="Seconds to wait before reindexing"),
):
    """Watch files and reindex on changes (live mode)."""
    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)
    
    from lca.watcher import watch_and_index
    
    watch_and_index(
        path=path,
        module_name=module,
        output_path=output,
        debounce_seconds=debounce,
    )



@app.command()
def modules(
    index_file: Path = typer.Option(..., "--index", "-i", help="Path to index JSON file"),
    root: Path = typer.Option(..., "--root", "-r", help="Project root directory (for relative paths)"),
):
    """Analyze high-level architecture: dependencies between modules."""
    if not index_file.exists():
        console.print(f"[red]Error:[/red] Index file does not exist: {index_file}")
        raise typer.Exit(1)
        
    from lca.tools.module_mapper import ModuleMapper
    
    console.print(f"[cyan]Mapping Modules using index:[/cyan] {index_file}")
    
    mapper = ModuleMapper(str(index_file), str(root))
    deps = mapper.analyze()
    mapper.print_report(deps)
    
@app.command()
def reaper(
    index_file: Optional[Path] = typer.Option(None, "--index", "-i", help="Path to index JSON file"),
):
    """Detect dead code (functions with zero usages)."""
    # 1. Default to lca_index.json if not specified
    if not index_file:
        index_file = Path("lca_index.json")

    # 2. Check existence
    if not index_file.exists():
        console.print(f"[red]Error:[/red] Index file not found: {index_file}")
        console.print(f"[yellow]Tip:[/yellow] Run 'lca index ...' first.")
        raise typer.Exit(1)
        
    from lca.tools.reaper import Reaper
    
    console.print(f"[red]Awakening The Reaper...[/red]")
    
    tool = Reaper(str(index_file))
    zombies = tool.scan()
    tool.print_report(zombies)

@app.command()
def ui(
    index_file: Path = typer.Option(..., "--index", "-i", help="Path to index JSON file"),
    port: int = typer.Option(8000, "--port", "-p", help="Server port"),
):
    """Launch the LCA Cockpit web UI."""
    if not index_file.exists():
        console.print(f"[red]Error:[/red] Index file does not exist: {index_file}")
        raise typer.Exit(1)
        
    from lca.cockpit.server import run_server
    
    console.print(f"[cyan]Launching LCA Cockpit...[/cyan]")
    run_server(str(index_file), port=port)

@app.command()
def gen_test(
    file_path: Path = typer.Argument(..., help="Path to PHP Controller file"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Output file path"),
):
    """Generate a PHPUnit test file skeleton with dependencies mocked."""
    if not file_path.exists():
        console.print(f"[red]Error:[/red] File does not exist: {file_path}")
        raise typer.Exit(1)
        
    from lca.tools.testgen import TestGenerator
    
    console.print(f"[cyan]Generating Test for:[/cyan] {file_path}")
    
    try:
        gen = TestGenerator(str(file_path))
        gen.parse()
        code = gen.generate()
        
        if output:
            with open(output, "w", encoding="utf-8") as f:
                f.write(code)
            console.print(f"[green]SUCCESS[/green] Test generated at: {output}")
        else:
            console.print(code)
            
    except Exception as e:
        console.print(f"[red]Failed:[/red] {e}")

@app.command()
def verify():
    """Run the Project Regression Test Suite (PHPUnit + LCA Audits)."""
    import subprocess
    import os
    
    # Locate run_tests.bat
    current_dir = os.path.dirname(os.path.abspath(__file__)) # .../lca/
    project_root = os.path.dirname(os.path.dirname(current_dir)) # .../etail_v3/
    script_path = os.path.join(project_root, "run_tests.bat")
    
    if not os.path.exists(script_path):
        console.print(f"[red]Error:[/red] Could not find '{script_path}'")
        raise typer.Exit(1)

    console.print(f"[bold cyan]Verifying Code Quality...[/bold cyan]")
    try:
        # Run the batch file
        result = subprocess.run([script_path], cwd=project_root, shell=True)
        if result.returncode != 0:
            console.print("[bold red]VERIFICATION FAILED![/bold red]")
            raise typer.Exit(1)
        else:
            console.print("[bold green]VERIFICATION PASSED![/bold green]")
    except Exception as e:
        console.print(f"[red]Execution Error:[/red] {e}")
        raise typer.Exit(1)


@app.command(name="test-coverage")
def test_coverage(
    tests_dir: Path = typer.Option(None, "--tests", "-t", help="Path to tests/ directory (auto-detected if omitted)"),
    index_file: Optional[Path] = typer.Option(None, "--index", "-i", help="LCA index JSON for cross-reference"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Output JSON file"),
):
    """Analyze E2E test coverage: page objects, test counts, URL coverage, gaps."""
    import json
    import os

    # Auto-detect tests dir
    if not tests_dir:
        current_dir = os.path.dirname(os.path.abspath(__file__))
        project_root = os.path.dirname(os.path.dirname(os.path.dirname(current_dir)))
        tests_dir = Path(project_root) / "tests"

    if not tests_dir.exists():
        console.print(f"[red]Error:[/red] Tests directory not found: {tests_dir}")
        raise typer.Exit(1)

    from lca.analysis.test_coverage import TestCoverageAnalyzer

    # Optionally load LCA index for cross-ref
    index_data = None
    if index_file and index_file.exists():
        with open(index_file, "r", encoding="utf-8") as f:
            index_data = json.load(f)

    analyzer = TestCoverageAnalyzer(str(tests_dir), index_data)
    data = analyzer.to_dict()
    summary = data["summary"]

    # Display summary panel
    console.print(Panel.fit(
        f"[green][+][/green] Page Objects: [bold]{summary['total_page_objects']}[/bold]\n"
        f"[green][+][/green] Test Files: [bold]{summary['total_test_files']}[/bold]\n"
        f"[green][+][/green] Total Tests: [bold]{summary['total_tests']}[/bold]\n"
        f"[green][+][/green] YAML Data Files: [bold]{summary['total_yaml_files']}[/bold]\n"
        f"[green][+][/green] Visual Regression: [bold]{summary['visual_regression_points']}[/bold]\n"
        f"[green][+][/green] Calc Engines: [bold]{summary['calc_engines']}[/bold]\n"
        f"[green][+][/green] Controller Coverage: [bold]{summary['coverage_pct']}%[/bold]",
        title="E2E Test Coverage",
        border_style="cyan"
    ))

    # Page objects table
    table = Table(title="Page Objects", show_header=True)
    table.add_column("Name", style="cyan")
    table.add_column("URLs", style="dim")
    table.add_column("Methods", style="magenta", justify="right")
    table.add_column("Visual", style="green", justify="center")
    table.add_column("Calc", style="yellow", justify="center")

    for po in data["page_objects"]:
        urls = ", ".join(po["urls"][:2]) or "-"
        visual = "✓" if po["visual_regression"] else ""
        calc = "✓" if po["calc_engine"] else ""
        table.add_row(po["name"], urls, str(po["method_count"]), visual, calc)

    console.print(table)

    # Test files table
    table2 = Table(title="Test Files", show_header=True)
    table2.add_column("File", style="cyan")
    table2.add_column("Tests", style="bold", justify="right")
    table2.add_column("Markers", style="dim")
    table2.add_column("Data", style="yellow")

    for tf in data["test_files"]:
        table2.add_row(
            tf["name"], str(tf["test_count"]),
            ", ".join(tf["markers"]), tf["data_file"] or "-"
        )

    console.print(table2)

    # Gaps
    if data["untested_controllers"]:
        console.print(f"\n[yellow]⚠ Untested Controllers ({len(data['untested_controllers'])}):[/yellow]")
        for c in data["untested_controllers"][:10]:
            console.print(f"  [dim]->[/dim] {c}")
        if len(data["untested_controllers"]) > 10:
            console.print(f"  [dim]... +{len(data['untested_controllers']) - 10} more[/dim]")

    # Save
    if output:
        with open(output, "w", encoding="utf-8") as f:
            json.dump(data, f, indent=2)
        console.print(f"\n[green]✓[/green] Saved to: {output}")

@app.command()
def n1(
    path: Path = typer.Argument(..., help="Path to PHP file or directory"),
    project_root: Optional[Path] = typer.Option(None, "--root", "-r", help="Project root (for relative paths)"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Write JSON results to file (for CI/CD)"),
    fail_on: int = typer.Option(0, "--fail-on", help="Exit 1 if findings >= N (0=disabled)"),
):
    """Detect N+1 query patterns — DB calls inside loops."""
    import json as json_mod
    from lca.analysis.n1 import scan_directory, scan_project

    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)

    console.print(f"[cyan]Scanning for N+1 patterns:[/cyan] {path}")

    # Load config whitelist
    from lca.config import load_config
    cfg = load_config(project_root or (path if path.is_dir() else path.parent))
    wl = cfg.n1.whitelist if cfg.n1.whitelist else None
    if wl:
        console.print(f"[dim]Whitelist ({len(wl)} entries from .lca.toml)[/dim]")

    if path.is_dir():
        root = project_root or path
        result = scan_directory(path, root, whitelist=wl)
    else:
        from lca.analysis.n1 import detect_n1_in_file
        result_findings = detect_n1_in_file(path, project_root, whitelist=wl)
        from lca.analysis.n1 import N1ScanResult
        result = N1ScanResult(findings=result_findings, files_scanned=1)

    # JSON output for CI/CD
    if json_output:
        data = {
            "total": len(result.findings),
            "critical": result.critical_count,
            "high": result.high_count,
            "medium": result.medium_count,
            "files_scanned": result.files_scanned,
            "findings": [
                {"severity": f.severity, "function": f.function, "pattern": f.pattern,
                 "file": Path(f.file).name, "loop_type": f.loop_type,
                 "loop_line": f.loop_line, "db_line": f.db_line}
                for f in result.findings
            ],
        }
        json_output.write_text(json_mod.dumps(data, indent=2), encoding='utf-8')

    # Summary
    console.print(Panel.fit(
        f"[bold]{len(result.findings)}[/bold] N+1 patterns found\n"
        f"  CRITICAL: [red]{result.critical_count}[/red]\n"
        f"  HIGH: [yellow]{result.high_count}[/yellow]\n"
        f"  MEDIUM: [dim]{result.medium_count}[/dim]\n"
        f"  Files scanned: {result.files_scanned}",
        title="N+1 Query Detection",
        border_style="red" if result.critical_count > 0 else "yellow" if result.high_count > 0 else "green"
    ))

    if result.findings:
        table = Table(title="N+1 Patterns", show_header=True)
        table.add_column("Sev", style="bold")
        table.add_column("Function", style="cyan")
        table.add_column("Pattern", style="dim")
        table.add_column("File", style="dim")
        table.add_column("Loop", style="dim")
        table.add_column("DB Line", style="dim")

        sev_colors = {"CRITICAL": "red", "HIGH": "yellow", "MEDIUM": "dim"}
        for f in result.findings[:30]:
            color = sev_colors.get(f.severity, "dim")
            table.add_row(
                f"[{color}]{f.severity}[/{color}]",
                f.function,
                f.pattern,
                Path(f.file).name,
                f"{f.loop_type} L{f.loop_line}",
                f"L{f.db_line}",
            )
        console.print(table)

    # Fail gate for CI/CD
    if fail_on > 0 and len(result.findings) >= fail_on:
        raise typer.Exit(1)


@app.command()
def hardcoded(
    path: Path = typer.Argument(..., help="Path to PHP file or directory"),
    project_root: Optional[Path] = typer.Option(None, "--root", "-r", help="Project root"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Write JSON results to file (for CI/CD)"),
    fail_on: int = typer.Option(0, "--fail-on", help="Exit 1 if findings >= N (0=disabled)"),
):
    """Scan for hardcoded values — magic numbers, URLs, IPs, dates."""
    import json as json_mod
    from lca.analysis.hardcoded import scan_directory, scan_project

    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)

    console.print(f"[cyan]Scanning for hardcoded values:[/cyan] {path}")

    # Load config whitelist
    from lca.config import load_config
    cfg = load_config(project_root or (path if path.is_dir() else path.parent))
    wl = cfg.hardcoded.whitelist if cfg.hardcoded.whitelist else None
    if wl:
        console.print(f"[dim]Whitelist ({len(wl)} entries from .lca.toml)[/dim]")

    if path.is_dir():
        root = project_root or path
        result = scan_directory(path, root, whitelist=wl)
    else:
        from lca.analysis.hardcoded import scan_file as hc_scan_file
        result_findings = hc_scan_file(path, project_root, whitelist=wl)
        from lca.analysis.hardcoded import HardcodedScanResult
        result = HardcodedScanResult(findings=result_findings, files_scanned=1)

    # JSON output for CI/CD
    if json_output:
        data = {
            "total": len(result.findings),
            "sql_count": result.sql_count,
            "logic_count": result.logic_count,
            "config_count": result.config_count,
            "files_scanned": result.files_scanned,
            "findings": [
                {"rule_id": f.rule_id, "category": f.category, "function": f.function,
                 "file": Path(f.file).name, "line": f.line, "match": f.match[:100]}
                for f in result.findings
            ],
        }
        json_output.write_text(json_mod.dumps(data, indent=2), encoding='utf-8')

    console.print(Panel.fit(
        f"[bold]{len(result.findings)}[/bold] hardcoded values found\n"
        f"  SQL: {result.sql_count} | Logic: {result.logic_count} | Config: {result.config_count}\n"
        f"  Files scanned: {result.files_scanned}",
        title="Hardcoded Value Scanner",
        border_style="yellow" if result.findings else "green"
    ))

    if result.findings:
        table = Table(title="Hardcoded Values", show_header=True)
        table.add_column("Rule", style="magenta")
        table.add_column("Cat", style="dim")
        table.add_column("Function", style="cyan")
        table.add_column("File", style="dim")
        table.add_column("Line", style="dim")
        table.add_column("Match", style="dim", max_width=50)

        for f in result.findings[:30]:
            table.add_row(f.rule_id, f.category, f.function, Path(f.file).name, str(f.line), f.match[:50])
        console.print(table)

    # Fail gate for CI/CD
    if fail_on > 0 and len(result.findings) >= fail_on:
        raise typer.Exit(1)


@app.command()
def health(
    path: Path = typer.Argument(..., help="Path to project root or directory"),
    save_history: bool = typer.Option(False, "--save", "-s", help="Save score to trend history"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Write JSON results to file (for CI/CD)"),
    fail_below: str = typer.Option("", "--fail-below", help="Exit 1 if grade below this (A/B/C/D/F)"),
):
    """Run all analyses and compute unified health score (0-100, A-F grade)."""
    from lca.analysis.health import (
        compute_health, save_trend,
        scan_complexity_inline, scan_security_inline,
    )
    from lca.analysis.n1 import scan_directory as n1_scan
    from lca.analysis.hardcoded import scan_directory as hc_scan

    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)

    console.print(f"[cyan]Computing health score for:[/cyan] {path}")

    # Run all 4 inline scanners
    console.print("  [dim]Running complexity analysis...[/dim]")
    avg_cc, high_cc, total_funcs = scan_complexity_inline(path)

    console.print("  [dim]Running security scan...[/dim]")
    sec_findings, sec_critical, sec_files = scan_security_inline(path)

    console.print("  [dim]Running N+1 detection...[/dim]")
    n1_result = n1_scan(path, path)

    console.print("  [dim]Running hardcoded scan...[/dim]")
    hc_result = hc_scan(path, path)

    # Compute health (dead code + smells = N/A without fullstack index)
    report = compute_health(
        target=path.name,
        files_analyzed=n1_result.files_scanned + hc_result.files_scanned,
        # Complexity (measured inline)
        avg_cc=avg_cc,
        high_cc_count=high_cc,
        total_functions=total_funcs,
        # Security (measured inline)
        security_findings=sec_findings,
        security_critical=sec_critical,
        security_files=sec_files,
        # Dead code (not measured without index)
        dead_functions=0,
        # N+1 (measured)
        n1_count=len(n1_result.findings),
        n1_critical=n1_result.critical_count,
        # Hardcoded (measured)
        hardcoded_count=len(hc_result.findings),
        hardcoded_sql=hc_result.sql_count,
        # Smells (not measured without index)
        smell_count=-1,
        smell_high=0,
    )

    # Display
    grade_colors = {"A": "green", "B": "cyan", "C": "yellow", "D": "red", "F": "red bold"}
    gc = grade_colors.get(report.grade, "white")
    conf_color = "green" if report.confidence >= 80 else "yellow" if report.confidence >= 50 else "red"

    console.print(Panel.fit(
        f"Score: [bold {gc}]{report.score:.0f}/100[/bold {gc}]  "
        f"Grade: [{gc}]{report.grade}[/{gc}]  "
        f"Confidence: [{conf_color}]{report.confidence:.0f}%[/{conf_color}]\n",
        title=f"Health: {report.target}",
        border_style=gc.split()[0],
    ))

    table = Table(show_header=True)
    table.add_column("Category", style="cyan")
    table.add_column("Score", justify="right")
    table.add_column("Weight", justify="right", style="dim")
    table.add_column("Weighted", justify="right")
    table.add_column("Findings", justify="right", style="dim")
    table.add_column("Detail", style="dim")

    for s in report.sub_scores:
        if not s.measured:
            table.add_row(
                f"[dim]{s.name}[/dim]",
                "[dim]N/A[/dim]",
                f"[dim]{s.weight:.0%}[/dim]",
                "[dim]--[/dim]",
                "[dim]--[/dim]",
                f"[dim]{s.detail}[/dim]",
            )
        else:
            score_color = "green" if s.score >= 80 else "yellow" if s.score >= 60 else "red"
            table.add_row(
                s.name,
                f"[{score_color}]{s.score:.0f}[/{score_color}]",
                f"{s.weight:.0%}",
                f"{s.score * s.weight:.1f}",
                str(s.findings_count),
                s.detail,
            )
    console.print(table)

    if report.confidence < 100:
        unmeasured = [s.name for s in report.sub_scores if not s.measured]
        console.print(f"\n[dim]Tip: Run 'lca index' first to enable: {', '.join(unmeasured)}[/dim]")

    if save_history:
        history_dir = path / ".lca" / "history"
        save_trend(report, history_dir)
        console.print(f"[green]Saved to trend history: {history_dir}[/green]")

    # JSON output for CI/CD
    if json_output:
        import json as json_mod
        data = report.to_dict()
        json_output.write_text(json_mod.dumps(data, indent=2), encoding='utf-8')

    # Quality gate for CI/CD
    if fail_below:
        grade_order = ['F', 'D', 'C', 'B', 'A']
        fail_idx = grade_order.index(fail_below.upper()) if fail_below.upper() in grade_order else -1
        actual_idx = grade_order.index(report.grade) if report.grade in grade_order else 0
        if actual_idx < fail_idx:
            console.print(f"\n[red]BLOCKED:[/red] Grade {report.grade} is below minimum {fail_below.upper()}")
            raise typer.Exit(1)


@app.command()
def report(
    path: Path = typer.Argument(..., help="Path to project root or directory"),
    output: Path = typer.Option(Path("lca_health_report.html"), "--output", "-o", help="Output HTML file"),
):
    """Generate HTML health report with charts and trends."""
    from lca.analysis.health import (
        compute_health, load_trend,
        scan_complexity_inline, scan_security_inline,
    )
    from lca.analysis.n1 import scan_directory as n1_scan
    from lca.analysis.hardcoded import scan_directory as hc_scan
    from lca.reporting.html import generate_html_report

    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)

    console.print(f"[cyan]Generating health report for:[/cyan] {path}")

    # Run all 4 inline scanners
    console.print("  [dim]Running analysis...[/dim]")
    avg_cc, high_cc, total_funcs = scan_complexity_inline(path)
    sec_findings, sec_critical, sec_files = scan_security_inline(path)
    n1_result = n1_scan(path, path)
    hc_result = hc_scan(path, path)

    report_data = compute_health(
        target=path.name,
        files_analyzed=n1_result.files_scanned + hc_result.files_scanned,
        avg_cc=avg_cc,
        high_cc_count=high_cc,
        total_functions=total_funcs,
        security_findings=sec_findings,
        security_critical=sec_critical,
        security_files=sec_files,
        dead_functions=0,
        n1_count=len(n1_result.findings),
        n1_critical=n1_result.critical_count,
        hardcoded_count=len(hc_result.findings),
        hardcoded_sql=hc_result.sql_count,
        smell_count=-1,
        smell_high=0,
    )

    # Load trend data if available
    history_dir = path / ".lca" / "history"
    trend_data = load_trend(path.name, history_dir) if history_dir.exists() else None

    generate_html_report(report_data, trend_data, output)

    console.print(f"[green]Report generated:[/green] {output}")
    console.print(f"  Score: {report_data.score:.0f}/100  Grade: {report_data.grade}  Confidence: {report_data.confidence:.0f}%")


@app.command()
def changeset(
    project_root: Path = typer.Argument(".", help="Project root directory"),
    base: str = typer.Option("HEAD~1", "--base", "-b", help="Git ref to diff against"),
    title: str = typer.Option("", "--title", "-t", help="Changeset title"),
    output_dir: Path = typer.Option(Path(".changes"), "--output", "-o", help="Output directory"),
):
    """Create a structured changeset manifest from git diff."""
    from lca.workflow.changeset import create_from_git, save_changeset

    console.print(f"[cyan]Creating changeset:[/cyan] {project_root} vs {base}")

    cs = create_from_git(project_root, base, title)

    if cs.id == "error":
        console.print(f"[red]Error:[/red] {cs.title}")
        raise typer.Exit(1)

    path = save_changeset(cs, output_dir)

    risk_colors = {"LOW": "green", "MEDIUM": "yellow", "HIGH": "red", "CRITICAL": "red bold"}
    rc = risk_colors.get(cs.risk_level, "white")

    console.print(Panel.fit(
        f"ID: {cs.id}\n"
        f"Files: {len(cs.files)} changed\n"
        f"Modules: {', '.join(cs.modules_affected) or 'N/A'}\n"
        f"Risk: [{rc}]{cs.risk_level} ({cs.risk_score}/100)[/{rc}]",
        title="Changeset Created",
        border_style="green",
    ))

    console.print(f"[green]Saved:[/green] {path}")
    console.print(f"\n[dim]Commit message:[/dim]\n{cs.to_commit_message()}")


@app.command()
def pr(
    project_root: Path = typer.Argument(".", help="Project root directory"),
    base: str = typer.Option("HEAD~1", "--base", "-b", help="Git ref to diff against"),
    title: str = typer.Option("", "--title", "-t", help="PR title"),
    template: str = typer.Option("default", "--template", help="Template: default or minimal"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Output file (default: stdout)"),
    raw: bool = typer.Option(False, "--raw", help="Print raw markdown only (no Rich formatting)"),
    create: bool = typer.Option(False, "--create", help="Create or update PR on GitHub after generating body"),
    draft: bool = typer.Option(False, "--draft", help="Create PR as draft (only with --create)"),
):
    """Generate a structured PR description from git diff.

    Merges .changes/current.json data (title, details, ticket) into
    the generated PR body if the manifest exists. Falls back to pure
    git diff analysis when no changeset manifest is present.

    Use --create to also push the PR to GitHub (requires GITHUB_TOKEN).
    """
    import json as _json
    from lca.workflow.changeset import create_from_git
    from lca.workflow.pr import generate_pr_body

    cs = create_from_git(project_root, base, title)

    if cs.id == "error":
        console.print(f"[red]Error:[/red] {cs.title}")
        raise typer.Exit(1)

    # Merge .changes/current.json if available
    manifest_path = project_root / ".changes" / "current.json"
    if manifest_path.exists():
        try:
            manifest = _json.loads(manifest_path.read_text(encoding='utf-8'))
            ctype = manifest.get('type', 'chore')
            mod = manifest.get('module', '')
            summary = manifest.get('summary', '')
            ticket = manifest.get('ticket', '')
            details = manifest.get('details', [])
            impact_text = manifest.get('impact', '')
            testing_text = manifest.get('testing', '')
            manifest_files = manifest.get('files', [])

            # Override changeset title from manifest
            ref = f' [{ticket}]' if ticket else ''
            cs.title = f"{ctype}({mod}): {summary}{ref}" if mod else f"{ctype}: {summary}{ref}"

            # Build description from manifest details
            if details:
                cs.description = '\n'.join(f'- {d}' for d in details)
            if impact_text:
                cs.description += f'\n\n**Impact:** {impact_text}'
            if testing_text:
                cs.description += f'\n\n**Testing:** {testing_text}'

            if ticket:
                cs.tags = [ticket]
        except Exception:
            manifest_files = []
            pass  # Graceful fallback to git-only data
    else:
        manifest_files = []

    body = generate_pr_body(cs, template, key_files=manifest_files or None)

    # Save to file if requested
    if output:
        output.write_text(body, encoding='utf-8')
        console.print(f"[green]PR body saved:[/green] {output}")

    # Create/update PR on GitHub if --create flag is set
    if create:
        from lca.workflow.github import create_or_update_pr

        pr_title = cs.title.lstrip('# ').strip()
        try:
            result = create_or_update_pr(
                project_root=project_root,
                title=pr_title,
                body=body,
                base_branch=base,
                draft=draft,
            )
            action = result['action']
            icon = '[NEW]' if action == 'created' else '[UPD]'
            console.print(f"\n{icon} PR #{result['number']} {action}: {result['url']}")
        except RuntimeError as e:
            console.print(f"[red]GitHub error:[/red] {e}")
            console.print("[dim]Tip: Set GITHUB_TOKEN env var or run 'gh auth login'[/dim]")
            raise typer.Exit(1)
    elif raw:
        # Raw mode: print plain markdown without Rich markup for piping
        import sys as _sys
        if _sys.platform == 'win32':
            _sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        print(body)
    elif not output:
        console.print(body)


@app.command(name="commit-msg")
def commit_msg(
    project_root: Path = typer.Argument(".", help="Project root directory"),
    msg_file: Optional[Path] = typer.Option(None, "--msg-file", "-f", help="Git commit message file to patch (used by git hook)"),
    base: str = typer.Option("HEAD~1", "--base", "-b", help="Git ref to diff against"),
    raw: bool = typer.Option(False, "--raw", help="Print raw text only (no Rich formatting)"),
):
    """Generate a structured commit message for git hooks.

    Priority order:
      1. .changes/current.json (manual changeset)
      2. Auto-detect from staged git diff (module detection + risk scoring)

    When --msg-file is provided (git hook mode), the commit message file is
    patched in-place. Otherwise the message is printed to stdout.
    """
    import json as _json

    generated_msg = ""

    # Priority 1: Read from .changes/current.json
    manifest_path = project_root / ".changes" / "current.json"
    if manifest_path.exists():
        try:
            manifest = _json.loads(manifest_path.read_text(encoding='utf-8'))
            ctype = manifest.get('type', 'chore')
            mod = manifest.get('module', '')
            summary = manifest.get('summary', '')
            ticket = manifest.get('ticket', '')
            details = manifest.get('details', [])

            # Build subject line
            if mod:
                subject = f"{ctype}({mod}): {summary}"
            else:
                subject = f"{ctype}: {summary}"

            parts = [subject]
            if details:
                parts.append('')
                for d in details:
                    parts.append(f'- {d}')
            if ticket:
                parts.append('')
                parts.append(f'Ref: {ticket}')

            generated_msg = '\n'.join(parts)
        except Exception:
            pass

    # Priority 2: Auto-detect from staged diff
    if not generated_msg:
        from lca.workflow.changeset import create_from_git
        cs = create_from_git(project_root, base)
        if cs.id != "error" and cs.files:
            from lca.analysis.modules import is_critical_module
            modules = cs.modules_affected
            mod_str = modules[0] if len(modules) == 1 else ','.join(modules[:3])
            file_count = len(cs.files)

            # Auto-detect type from file patterns
            ctype = "chore"
            has_model = any('model' in f.path.lower() for f in cs.files)
            has_view = any('views/' in f.path.lower() for f in cs.files)
            has_test = any('test' in f.path.lower() for f in cs.files)
            if has_test and not has_model:
                ctype = "test"
            elif has_view and not has_model:
                ctype = "style"
            elif has_model:
                ctype = "fix"

            subject = f"{ctype}({mod_str}): update {file_count} files"

            risk_note = f"Risk: {cs.risk_level} ({cs.risk_score}/100)"
            parts = [subject, '', risk_note, f'Files: {file_count} changed']
            if modules:
                parts.append(f'Modules: {", ".join(modules)}')

            generated_msg = '\n'.join(parts)

    if not generated_msg:
        if raw or msg_file:
            return  # Silent exit for hook mode
        console.print("[yellow]No changeset or staged changes found.[/yellow]")
        raise typer.Exit(1)

    # Output: patch file or print
    if msg_file and msg_file.exists():
        # Git hook mode: read existing message, check if already structured
        existing = msg_file.read_text(encoding='utf-8', errors='replace')
        import re
        structured_pattern = r'^(fix|feat|refactor|style|perf|docs|chore|test|merge|resolve)\('
        if re.match(structured_pattern, existing):
            # Already structured, don't override
            return
        # Patch: prepend generated message, keep original as body
        original_lines = existing.strip().split('\n')
        if original_lines and original_lines[0].strip():
            # Append original message as additional context
            patched = generated_msg + '\n\n' + 'Original: ' + original_lines[0]
        else:
            patched = generated_msg
        msg_file.write_text(patched, encoding='utf-8')
    elif raw:
        import sys as _sys
        if _sys.platform == 'win32':
            _sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        print(generated_msg)
    else:
        console.print(Panel.fit(generated_msg, title="Commit Message", border_style="green"))


@app.command()
def smells(
    index_file: Path = typer.Option(..., "--index", "-i", help="Path to fullstack index DB"),
    smell_type: Optional[str] = typer.Option(None, "--type", "-t", help="Filter: god_class, long_method, long_params, etc."),
    language: Optional[str] = typer.Option(None, "--lang", help="Filter by language: php or javascript"),
    limit: int = typer.Option(30, "--limit", "-l", help="Maximum results"),
):
    """Detect code smells — god classes, long methods, feature envy, etc."""
    db_path = index_file.with_suffix(".db")
    if not db_path.exists():
        console.print(f"[red]Error:[/red] Symbol database not found: {db_path}")
        console.print("[yellow]Tip:[/yellow] Run 'lca index' with fullstack first.")
        raise typer.Exit(1)

    from lca.storage.symbol_db import SymbolDB
    from lca.analysis.intelligence import detect_code_smells

    db = SymbolDB(db_path)
    smells_list = detect_code_smells(db, language or "")

    if smell_type:
        smells_list = [s for s in smells_list if s.smell_type == smell_type]

    console.print(Panel.fit(
        f"[bold]{len(smells_list)}[/bold] code smells detected",
        title="Code Smell Detection",
        border_style="yellow" if smells_list else "green"
    ))

    if smells_list:
        table = Table(show_header=True)
        table.add_column("Type", style="magenta")
        table.add_column("Severity", style="bold")
        table.add_column("Location", style="cyan")
        table.add_column("Value", justify="right")
        table.add_column("Detail", style="dim", max_width=50)

        sev_colors = {"HIGH": "red", "MEDIUM": "yellow", "LOW": "dim"}
        for s in smells_list[:limit]:
            c = sev_colors.get(s.severity, "dim")
            table.add_row(s.smell_type, f"[{c}]{s.severity}[/{c}]", s.location, str(s.metric_value), s.detail[:50])
        console.print(table)


@app.command()
def quality(
    path: Path = typer.Argument(..., help="Path to PHP file or directory"),
    limit: int = typer.Option(20, "--limit", "-l", help="Max functions to show"),
):
    """Show worst functions by complexity — quick quality overview."""
    from lca.analysis.n1 import _find_function_at_offset
    import re

    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)

    files = [path] if path.is_file() else sorted(path.rglob("*.php"))
    results = []

    for fpath in files:
        try:
            content = fpath.read_text(encoding='utf-8', errors='ignore')
        except Exception:
            continue

        # Simple CC estimation via regex
        func_pattern = re.compile(
            r'(?:public|private|protected|static)?\s*function\s+(\w+)\s*\(', re.IGNORECASE
        )
        for match in func_pattern.finditer(content):
            name = match.group(1)
            # Find function body (simplified)
            start = match.start()
            # Count decision points after this function
            remaining = content[start:start+5000]  # Limit search window
            cc = 1
            for kw in ['if(', 'if (', 'elseif', 'else if', 'case ', '&&', '||',
                       'foreach', 'for(', 'for (', 'while(', 'while (', 'catch']:
                cc += remaining.count(kw)
            loc = remaining.split('}')[0].count('\n') if '}' in remaining else 0

            if cc > 1:
                results.append({
                    'name': name,
                    'file': fpath.name,
                    'cc': min(cc, 200),
                    'loc': loc,
                })

    results.sort(key=lambda r: r['cc'], reverse=True)

    table = Table(title=f"Worst Functions by Complexity", show_header=True)
    table.add_column("#", style="dim")
    table.add_column("Function", style="cyan")
    table.add_column("CC", justify="right", style="bold")
    table.add_column("~LOC", justify="right", style="dim")
    table.add_column("File", style="dim")

    for i, r in enumerate(results[:limit], 1):
        cc_color = "red" if r['cc'] > 20 else "yellow" if r['cc'] > 10 else "green"
        table.add_row(str(i), r['name'], f"[{cc_color}]{r['cc']}[/{cc_color}]", str(r['loc']), r['file'])

    console.print(table)


@app.command()
def brain(
    project_root: Path = typer.Argument(..., help="Project root directory"),
    module: str = typer.Option(..., "--module", "-m", help="Module name (e.g., billing)"),
    output_dir: Optional[Path] = typer.Option(None, "--output", "-o", help="Output directory"),
):
    """Auto-generate Module Brain documentation from code analysis."""
    from lca.tools.brain import generate_brain
    import time

    if not project_root.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {project_root}")
        raise typer.Exit(1)

    out = str(output_dir) if output_dir else str(project_root / "knowledge_brain" / module / "_auto")
    console.print(f"[cyan]Generating Module Brain for:[/cyan] {module}")
    console.print(f"[dim]Output: {out}[/dim]")

    t_start = time.time()
    stats, written = generate_brain(str(project_root), module, out)
    elapsed = time.time() - t_start

    console.print(Panel.fit(
        f"[green][+][/green] Methods: [bold]{stats['total_methods']}[/bold]\n"
        f"[green][+][/green] Pages: [bold]{stats['page_endpoints']}[/bold]\n"
        f"[green][+][/green] AJAX: [bold]{stats['ajax_endpoints']}[/bold]\n"
        f"[green][+][/green] Tables: [bold]{stats['tables_used']}[/bold] ({stats['shared_tables']} shared)\n"
        f"[green][+][/green] Files written: [bold]{len(written)}[/bold]\n"
        f"[dim]Done in {elapsed:.1f}s[/dim]",
        title=f"Module Brain: {module}",
        border_style="green"
    ))

    for fp in written:
        console.print(f"  [dim]->[/dim] {fp}")


@app.command()
def flowmap(
    project_root: Path = typer.Argument(..., help="Project root directory"),
    module: str = typer.Option(..., "--module", "-m", help="Module name"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Output markdown file"),
):
    """Generate Mermaid flow diagrams for module onboarding."""
    from lca.tools.flowmap import generate_flow_map

    if not project_root.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {project_root}")
        raise typer.Exit(1)

    out = str(output) if output else str(project_root / "knowledge_brain" / module / "_auto" / "FLOW_MAPS.md")
    console.print(f"[cyan]Generating flow maps for:[/cyan] {module}")

    content, count = generate_flow_map(str(project_root), module, out)

    console.print(Panel.fit(
        f"[green][+][/green] [bold]{count}[/bold] flow diagrams generated\n"
        f"[green][+][/green] Output: [cyan]{out}[/cyan]",
        title=f"Flow Maps: {module}",
        border_style="blue"
    ))


@app.command(name="client-diff")
def client_diff(
    base: Path = typer.Option(..., "--base", "-b", help="Base/reference project root"),
    target: Path = typer.Option(..., "--target", "-t", help="Target/client project root"),
    module: Optional[str] = typer.Option(None, "--module", "-m", help="Filter to specific module"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Output markdown file"),
):
    """Compare two client codebases -- function-level diff with similarity scoring."""
    from lca.tools.client_diff import diff_codebases, generate_diff_report
    import time

    if not base.exists():
        console.print(f"[red]Error:[/red] Base path does not exist: {base}")
        raise typer.Exit(1)
    if not target.exists():
        console.print(f"[red]Error:[/red] Target path does not exist: {target}")
        raise typer.Exit(1)

    console.print(f"[cyan]Comparing:[/cyan] {base.name} vs {target.name}")
    if module:
        console.print(f"[dim]Module filter: {module}[/dim]")

    t_start = time.time()
    results = diff_codebases(str(base), str(target), module)
    elapsed = time.time() - t_start

    console.print(Panel.fit(
        f"Files compared: [bold]{results['files_compared']}[/bold]\n"
        f"Identical: [green]{results['files_identical']}[/green]\n"
        f"Modified: [yellow]{results['files_modified']}[/yellow]\n"
        f"Base only: {len(results['files_base_only'])}\n"
        f"Target only: {len(results['files_target_only'])}\n"
        f"[dim]Done in {elapsed:.1f}s[/dim]",
        title="Cross-Client Diff",
        border_style="cyan"
    ))

    report = generate_diff_report(results)

    if output:
        output.write_text(report, encoding='utf-8')
        console.print(f"[green]Report saved:[/green] {output}")
    else:
        # Show high-risk summary
        high_risk = [mf for mf in results['modified_files'] if len(mf['modified']) >= 3]
        if high_risk:
            table = Table(title="High-Risk Files", show_header=True)
            table.add_column("File", style="cyan")
            table.add_column("Module", style="dim")
            table.add_column("Added", style="green", justify="right")
            table.add_column("Removed", style="red", justify="right")
            table.add_column("Modified", style="yellow", justify="right")
            for mf in high_risk[:15]:
                table.add_row(
                    Path(mf['path']).name, mf['module'],
                    f"+{len(mf['added'])}", f"-{len(mf['removed'])}", f"~{len(mf['modified'])}"
                )
            console.print(table)


@app.command()
def risk(
    project_root: Path = typer.Argument(".", help="Project root directory"),
    files: Optional[str] = typer.Option(None, "--files", "-f", help="Comma-separated file paths"),
    branch: bool = typer.Option(False, "--branch", help="Analyze current branch diff vs PRODUCTION"),
    base_branch: str = typer.Option("PRODUCTION", "--base", "-b", help="Base branch for diff"),
    months: int = typer.Option(3, "--months", "-m", help="Churn lookback months"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Output report file"),
):
    """Score regression risk for files -- churn + complexity + context."""
    from lca.workflow.regression import (
        get_branch_files, analyze_regression_risk, generate_risk_report,
    )

    if branch:
        file_list = get_branch_files(project_root, base_branch)
    elif files:
        file_list = [f.strip() for f in files.split(",") if f.strip()]
    else:
        console.print("[red]Error:[/red] Provide --files or --branch")
        raise typer.Exit(1)

    # Filter to PHP/JS
    file_list = [f for f in file_list if f.endswith('.php') or f.endswith('.js')]

    if not file_list:
        console.print("[yellow]No PHP/JS files to analyze.[/yellow]")
        return

    console.print(f"[cyan]Analyzing regression risk for {len(file_list)} files...[/cyan]")

    results = analyze_regression_risk(project_root, file_list, months)

    # Summary
    critical = sum(1 for r in results if r['risk_label'] == 'CRITICAL')
    high = sum(1 for r in results if r['risk_label'] == 'HIGH')
    avg = sum(r['risk_score'] for r in results) / max(len(results), 1)

    console.print(Panel.fit(
        f"Files: {len(results)} | "
        f"CRITICAL: [red]{critical}[/red] | HIGH: [yellow]{high}[/yellow] | "
        f"Avg: {avg:.0f}/100",
        title="Regression Risk",
        border_style="red" if critical > 0 else "yellow" if high > 0 else "green"
    ))

    # Table
    table = Table(show_header=True)
    table.add_column("File", style="cyan")
    table.add_column("Churn", justify="right")
    table.add_column("Authors", justify="right")
    table.add_column("CC", justify="right")
    table.add_column("Risk", justify="right", style="bold")
    table.add_column("Score", justify="right")

    sev_colors = {"CRITICAL": "red", "HIGH": "yellow", "MEDIUM": "dim", "LOW": "green"}
    for r in sorted(results, key=lambda x: x['risk_score'], reverse=True)[:20]:
        c = sev_colors.get(r['risk_label'], 'dim')
        table.add_row(
            Path(r['file']).name, str(r['churn']), str(r['authors']),
            f"{r['complexity']}({r['rating']})",
            f"[{c}]{r['risk_label']}[/{c}]", str(r['risk_score']),
        )
    console.print(table)

    if output:
        report = generate_risk_report(results)
        output.write_text(report, encoding='utf-8')
        console.print(f"[green]Report saved:[/green] {output}")


@app.command()
def duplicates(
    path: Path = typer.Argument(..., help="Path to project root"),
    threshold: float = typer.Option(0.80, "--threshold", "-t", help="Similarity threshold (0.0-1.0)"),
    module: Optional[str] = typer.Option(None, "--module", "-m", help="Filter to specific module"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Write JSON results to file"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Write markdown report to file"),
):
    """Find duplicate/near-identical functions across the codebase."""
    import json as json_mod
    from lca.analysis.duplicates import scan_duplicates

    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)

    console.print(f"[cyan]Scanning for duplicate code:[/cyan] {path}")
    console.print(f"[dim]Threshold: {int(threshold*100)}%[/dim]")

    result = scan_duplicates(path, threshold, module)

    console.print(Panel.fit(
        f"[bold]{result.duplicate_clusters}[/bold] duplicate clusters found\n"
        f"  Functions in duplicates: {result.duplicate_functions}\n"
        f"  Cross-module: [red]{result.cross_module_duplicates}[/red]\n"
        f"  Cross-file: {result.cross_file_duplicates}\n"
        f"  Total functions: {result.total_functions} | Files: {result.files_scanned}",
        title="Duplicate Code Finder",
        border_style="red" if result.cross_module_duplicates > 0 else "yellow" if result.duplicate_clusters > 0 else "green"
    ))

    if result.clusters:
        # Show cross-module first (highest risk)
        cross = [c for c in result.clusters if c.cross_module]
        if cross:
            table = Table(title="Cross-Module Duplicates (Highest Risk)", show_header=True)
            table.add_column("Similarity", style="red", justify="right")
            table.add_column("Function", style="cyan")
            table.add_column("File", style="dim")
            table.add_column("Module", style="yellow")
            table.add_column("Line", style="dim")

            for cluster in cross[:10]:
                for i, f in enumerate(cluster.functions):
                    table.add_row(
                        f"{cluster.similarity}%" if i == 0 else "",
                        f.name, Path(f.file).name, f.module, f"L{f.line}",
                    )
                table.add_row("", "", "", "", "")  # separator
            console.print(table)

        # Show same-module
        same = [c for c in result.clusters if not c.cross_module]
        if same:
            table = Table(title=f"Same-Module Duplicates ({len(same)} clusters)", show_header=True)
            table.add_column("Similarity", justify="right")
            table.add_column("Function", style="cyan")
            table.add_column("File", style="dim")
            table.add_column("Module", style="dim")

            for cluster in same[:15]:
                for i, f in enumerate(cluster.functions):
                    table.add_row(
                        f"{cluster.similarity}%" if i == 0 else "",
                        f.name, Path(f.file).name, f.module,
                    )
                table.add_row("", "", "", "")
            console.print(table)

    if json_output:
        data = {
            "files_scanned": result.files_scanned,
            "total_functions": result.total_functions,
            "duplicate_clusters": result.duplicate_clusters,
            "duplicate_functions": result.duplicate_functions,
            "cross_module_duplicates": result.cross_module_duplicates,
            "clusters": [
                {
                    "similarity": c.similarity,
                    "count": c.count,
                    "cross_module": c.cross_module,
                    "functions": [
                        {"name": f.name, "file": f.file, "module": f.module,
                         "line": f.line, "size": f.size}
                        for f in c.functions
                    ],
                }
                for c in result.clusters
            ],
        }
        json_output.write_text(json_mod.dumps(data, indent=2), encoding='utf-8')
        console.print(f"[green]JSON saved:[/green] {json_output}")

    if output:
        # Generate markdown report
        lines = [
            "# Duplicate Code Report", "",
            f"| Metric | Value |", f"|---|---|",
            f"| **Files scanned** | {result.files_scanned} |",
            f"| **Functions analyzed** | {result.total_functions} |",
            f"| **Duplicate clusters** | {result.duplicate_clusters} |",
            f"| **Cross-module duplicates** | {result.cross_module_duplicates} |",
            f"| **Threshold** | {int(threshold*100)}% |", "",
        ]
        for cluster in result.clusters[:30]:
            mods = ", ".join(set(f"`{f.module}`" for f in cluster.functions))
            lines.append(f"### Cluster ({cluster.similarity}% similar, {cluster.count} copies)")
            lines.append(f"Modules: {mods}\n")
            lines.append("| Function | File | Module | Line |")
            lines.append("|---|---|---|---|")
            for f in cluster.functions:
                lines.append(f"| `{f.name}()` | `{Path(f.file).name}` | {f.module} | L{f.line} |")
            lines.append("")
        output.write_text("\n".join(lines), encoding='utf-8')
        console.print(f"[green]Report saved:[/green] {output}")


@app.command(name="sql-map")
def sql_map(
    path: Path = typer.Argument(..., help="Path to project root"),
    table: Optional[str] = typer.Option(None, "--table", "-t", help="Filter to tables containing this string"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Write JSON results to file"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Write markdown report to file"),
):
    """Map which PHP functions touch which database tables."""
    import json as json_mod
    from lca.analysis.sql_map import scan_sql_map

    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)

    console.print(f"[cyan]Mapping SQL table references:[/cyan] {path}")

    result = scan_sql_map(path, table)

    console.print(Panel.fit(
        f"[bold]{result.unique_tables}[/bold] tables found\n"
        f"  Total references: {result.total_references}\n"
        f"  Cross-module tables: [red]{len(result.cross_module_tables)}[/red]\n"
        f"  Write-heavy tables: [yellow]{len(result.write_heavy_tables)}[/yellow]\n"
        f"  Single-reference: [dim]{len(result.orphan_risk_tables)}[/dim]\n"
        f"  Files scanned: {result.files_scanned}",
        title="SQL-to-Code Mapper",
        border_style="cyan",
    ))

    # Cross-module tables (most important)
    if result.cross_module_tables:
        tbl = Table(title="Cross-Module Tables (Shared Data)", show_header=True)
        tbl.add_column("Table", style="cyan")
        tbl.add_column("Modules", style="yellow")
        tbl.add_column("Refs", justify="right")

        for t in sorted(result.cross_module_tables, key=lambda x: x['total_refs'], reverse=True)[:20]:
            tbl.add_row(t['table'], ", ".join(t['modules']), str(t['total_refs']))
        console.print(tbl)

    # Write-heavy tables
    if result.write_heavy_tables:
        tbl = Table(title="Write-Heavy Tables", show_header=True)
        tbl.add_column("Table", style="red")
        tbl.add_column("Writes", justify="right", style="bold")
        tbl.add_column("Modules")

        for t in sorted(result.write_heavy_tables, key=lambda x: x['write_count'], reverse=True)[:10]:
            tbl.add_row(t['table'], str(t['write_count']), ", ".join(t['modules']))
        console.print(tbl)

    if json_output:
        data = {
            "files_scanned": result.files_scanned,
            "total_references": result.total_references,
            "unique_tables": result.unique_tables,
            "cross_module_tables": result.cross_module_tables,
            "write_heavy_tables": result.write_heavy_tables,
            "orphan_risk_tables": result.orphan_risk_tables,
            "tables": {
                k: {"modules": v.modules, "files": v.files, "read_count": v.read_count,
                     "write_count": v.write_count, "total_refs": v.total_refs,
                     "cross_module": v.cross_module}
                for k, v in result.tables.items()
            },
        }
        json_output.write_text(json_mod.dumps(data, indent=2, default=list), encoding='utf-8')
        console.print(f"[green]JSON saved:[/green] {json_output}")

    if output:
        lines = [
            "# SQL-to-Code Mapping Report", "",
            f"| Metric | Value |", f"|---|---|",
            f"| **Files scanned** | {result.files_scanned} |",
            f"| **Total references** | {result.total_references} |",
            f"| **Unique tables** | {result.unique_tables} |",
            f"| **Cross-module tables** | {len(result.cross_module_tables)} |", "",
        ]
        if result.cross_module_tables:
            lines.append("## Cross-Module Tables\n")
            lines.append("| Table | Modules | Refs |")
            lines.append("|---|---|---|")
            for t in sorted(result.cross_module_tables, key=lambda x: x['total_refs'], reverse=True):
                mods = ", ".join(f"`{m}`" for m in t['modules'])
                lines.append(f"| `{t['table']}` | {mods} | {t['total_refs']} |")
            lines.append("")
        output.write_text("\n".join(lines), encoding='utf-8')
        console.print(f"[green]Report saved:[/green] {output}")


@app.command(name="release-notes")
def release_notes_cmd(
    path: Path = typer.Argument(..., help="Path to project root"),
    since: Optional[str] = typer.Option(None, "--since", "-s", help="Since date (YYYY-MM-DD)"),
    last: Optional[int] = typer.Option(None, "--last", "-l", help="Last N changesets"),
    version: Optional[str] = typer.Option(None, "--version", "-v", help="Version label"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Output file"),
):
    """Generate release notes from changeset history."""
    from lca.analysis.release_notes import build_release_notes

    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)

    result = build_release_notes(path, since=since, last_n=last, version=version)

    if not result.entries:
        console.print("[yellow]No changeset history found.[/yellow]")
        console.print("[dim]Tip: Use 'lca changeset --save' to save changesets to history.[/dim]")
        raise typer.Exit(0)

    console.print(Panel.fit(
        f"[bold]{len(result.entries)}[/bold] changes\n"
        f"  Date range: {result.date_range}\n"
        f"  Version: {result.version}",
        title="Release Notes",
        border_style="green",
    ))

    console.print(result.markdown)

    if output:
        output.write_text(result.markdown, encoding='utf-8')
        console.print(f"\n[green]Saved:[/green] {output}")


@app.command(name="diff-health")
def diff_health(
    before: Path = typer.Argument(..., help="Path to baseline health JSON (before)"),
    after: Path = typer.Argument(..., help="Path to current health JSON (after)"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Write JSON diff to file"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Write markdown report to file"),
    fail_on_regression: bool = typer.Option(False, "--fail-on-regression", help="Exit 1 if health score decreased"),
):
    """Compare two health snapshots — show what improved or degraded."""
    import json as json_mod
    from lca.analysis.health_diff import load_health_json, compare_health, generate_diff_markdown

    if not before.exists():
        console.print(f"[red]Error:[/red] Before file not found: {before}")
        raise typer.Exit(1)
    if not after.exists():
        console.print(f"[red]Error:[/red] After file not found: {after}")
        raise typer.Exit(1)

    before_data = load_health_json(before)
    after_data = load_health_json(after)
    diff = compare_health(before_data, after_data)

    # Score display
    delta_color = "green" if diff.score_delta > 0 else "red" if diff.score_delta < 0 else "dim"
    grade_color = "green" if diff.after_grade in ('A', 'B') else "yellow" if diff.after_grade == 'C' else "red"

    console.print(Panel.fit(
        f"{diff.summary}\n"
        f"  Before: {diff.before_score:.0f}/100 ({diff.before_grade})  ->  "
        f"After: [{grade_color}]{diff.after_score:.0f}/100 ({diff.after_grade})[/{grade_color}]  "
        f"[{delta_color}]({diff.score_delta:+.1f})[/{delta_color}]",
        title="Health Diff",
        border_style=delta_color.split()[0],
    ))

    # Sub-score table
    table = Table(show_header=True)
    table.add_column("Dimension", style="cyan")
    table.add_column("Before", justify="right")
    table.add_column("After", justify="right")
    table.add_column("Findings +/-", justify="right")
    table.add_column("Verdict", justify="right")

    for s in diff.sub_diffs:
        b_str = f"{s.before:.0f}" if s.before is not None else "[dim]N/A[/dim]"
        a_str = f"{s.after:.0f}" if s.after is not None else "[dim]N/A[/dim]"

        if s.delta is not None and s.delta > 0:
            verdict = f"[green]+{s.delta:.0f} UP[/green]"
        elif s.delta is not None and s.delta < 0:
            verdict = f"[red]{s.delta:.0f} DOWN[/red]"
        else:
            verdict = "[dim]--[/dim]"

        f_str = f"{s.findings_delta:+d}" if s.findings_delta != 0 else "[dim]--[/dim]"

        table.add_row(s.name, b_str, a_str, f_str, verdict)
    console.print(table)

    # Summary
    if diff.improved:
        console.print(f"\n[green]Improved:[/green] {', '.join(diff.improved)}")
    if diff.degraded:
        console.print(f"[red]Degraded:[/red] {', '.join(diff.degraded)}")

    # JSON output
    if json_output:
        data = {
            "before_score": diff.before_score,
            "after_score": diff.after_score,
            "score_delta": diff.score_delta,
            "before_grade": diff.before_grade,
            "after_grade": diff.after_grade,
            "grade_changed": diff.grade_changed,
            "improved": diff.improved,
            "degraded": diff.degraded,
            "sub_diffs": [
                {"name": s.name, "before": s.before, "after": s.after,
                 "delta": s.delta, "findings_delta": s.findings_delta}
                for s in diff.sub_diffs
            ],
        }
        json_output.write_text(json_mod.dumps(data, indent=2), encoding='utf-8')

    # Markdown report
    if output:
        md = generate_diff_markdown(diff)
        output.write_text(md, encoding='utf-8')
        console.print(f"\n[green]Report saved:[/green] {output}")

    # Quality gate
    if fail_on_regression and diff.score_delta < 0:
        console.print(f"\n[red]BLOCKED:[/red] Health score regressed by {abs(diff.score_delta):.1f} points")
        raise typer.Exit(1)


@app.command(name="migration-check")
def migration_check(
    path: Path = typer.Argument(..., help="Path to project root"),
    tables: str = typer.Argument(..., help="Comma-separated table names to check (e.g., ret_billing,ret_estimation)"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Write JSON results to file"),
):
    """Check all code that touches specified tables -- run BEFORE ALTER TABLE."""
    import json as json_mod
    from lca.analysis.sql_map import scan_sql_map

    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)

    table_list = [t.strip() for t in tables.split(',') if t.strip()]
    console.print(f"[cyan]Migration safety check for:[/cyan] {', '.join(table_list)}")
    console.print(f"[dim]Scanning all code that reads/writes these tables...[/dim]\n")

    # Scan full codebase
    full_result = scan_sql_map(path)

    total_impact = 0
    all_impact = {}

    for table_name in table_list:
        # Find tables matching the filter
        matching = {k: v for k, v in full_result.tables.items()
                    if table_name.lower() in k.lower()}

        if not matching:
            console.print(f"[yellow]WARNING:[/yellow] No code references found for `{table_name}`")
            console.print(f"  [dim]Table might be unused or name doesn't match.[/dim]\n")
            continue

        for tbl_name, info in matching.items():
            total_impact += info.total_refs

            all_impact[tbl_name] = {
                'modules': info.modules,
                'files': info.files,
                'read_count': info.read_count,
                'write_count': info.write_count,
                'total_refs': info.total_refs,
                'cross_module': info.cross_module,
            }

            # Risk assessment
            if info.cross_module:
                risk = "[red]HIGH[/red] -- cross-module table"
            elif info.total_refs > 20:
                risk = "[yellow]MEDIUM[/yellow] -- heavily referenced"
            else:
                risk = "[green]LOW[/green]"

            console.print(Panel.fit(
                f"Table: [bold]{tbl_name}[/bold]\n"
                f"  Risk: {risk}\n"
                f"  Modules: {', '.join(info.modules)}\n"
                f"  Files: {len(info.files)}\n"
                f"  Read refs: {info.read_count} | Write refs: {info.write_count}\n"
                f"  Total references: {info.total_refs}",
                title=f"Impact: {tbl_name}",
                border_style="red" if info.cross_module else "yellow" if info.total_refs > 20 else "green",
            ))

            # Show affected files
            tbl = Table(title=f"Affected files for {tbl_name}", show_header=True)
            tbl.add_column("File", style="cyan")
            tbl.add_column("Module", style="dim")
            for f in info.files[:20]:
                mod = 'unknown'
                for m in info.modules:
                    if m.lower() in f.lower():
                        mod = m
                        break
                tbl.add_row(Path(f).name, mod)
            if len(info.files) > 20:
                tbl.add_row(f"... +{len(info.files) - 20} more", "")
            console.print(tbl)
            console.print()

    # Summary
    if total_impact > 0:
        console.print(Panel.fit(
            f"[bold]{total_impact}[/bold] total code references across {len(all_impact)} tables\n"
            f"  Affected modules: {', '.join(set(m for t in all_impact.values() for m in t['modules']))}\n\n"
            f"  [yellow]Recommendation:[/yellow] Test all listed modules after migration.",
            title="Migration Impact Summary",
            border_style="yellow",
        ))

    if json_output:
        json_output.write_text(json_mod.dumps({
            "tables_checked": table_list,
            "total_impact": total_impact,
            "impact": all_impact,
        }, indent=2, default=list), encoding='utf-8')
        console.print(f"\n[green]JSON saved:[/green] {json_output}")


@app.command(name="todo-scan")
def todo_scan(
    path: Path = typer.Argument(..., help="Path to project root"),
    module: Optional[str] = typer.Option(None, "--module", "-m", help="Filter to specific module"),
    tag: Optional[str] = typer.Option(None, "--tag", "-t", help="Filter to specific tag (TODO, FIXME, HACK, etc.)"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Write JSON results to file"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Write markdown report to file"),
):
    """Find all TODO/FIXME/HACK comments -- track hidden technical debt."""
    import json as json_mod
    from lca.analysis.todo_scan import scan_todos, TAG_LABELS

    if not path.exists():
        console.print(f"[red]Error:[/red] Path does not exist: {path}")
        raise typer.Exit(1)

    console.print(f"[cyan]Scanning for TODO/FIXME/HACK comments:[/cyan] {path}")
    result = scan_todos(path, module_filter=module, tag_filter=tag)

    # Summary panel
    tag_summary = " | ".join(f"{k}: {v}" for k, v in sorted(result.by_tag.items(), key=lambda x: -x[1]))
    priority_summary = " | ".join(
        f"{k}: {v}" for k, v in sorted(result.by_priority.items(),
        key=lambda x: ['CRITICAL', 'HIGH', 'MEDIUM', 'NORMAL', 'INFO'].index(x[0]) if x[0] in ['CRITICAL', 'HIGH', 'MEDIUM', 'NORMAL', 'INFO'] else 5)
    )

    border = "red" if result.by_priority.get('CRITICAL', 0) > 0 else "yellow" if result.by_priority.get('HIGH', 0) > 0 else "green"
    console.print(Panel.fit(
        f"[bold]{result.total_items}[/bold] items found in {result.files_scanned} files\n"
        f"  Tags: {tag_summary}\n"
        f"  Priority: {priority_summary}",
        title="TODO Scanner",
        border_style=border,
    ))

    # By-module breakdown
    if result.by_module:
        tbl = Table(title="Items by Module", show_header=True)
        tbl.add_column("Module", style="yellow")
        tbl.add_column("Count", justify="right", style="bold")
        for mod, count in sorted(result.by_module.items(), key=lambda x: -x[1]):
            tbl.add_row(mod, str(count))
        console.print(tbl)

    # Critical/High items table
    urgent = [i for i in result.items if i.priority >= 3]
    if urgent:
        tbl = Table(title=f"Urgent Items ({len(urgent)})", show_header=True)
        tbl.add_column("Tag", style="red", justify="center")
        tbl.add_column("Text")
        tbl.add_column("File", style="dim")
        tbl.add_column("Line", style="dim", justify="right")
        tbl.add_column("Function", style="cyan")
        tbl.add_column("Module", style="yellow")

        for item in urgent[:30]:
            tbl.add_row(
                item.tag, item.text[:60],
                Path(item.file).name, str(item.line),
                item.context, item.module,
            )
        if len(urgent) > 30:
            tbl.add_row("...", f"+{len(urgent) - 30} more", "", "", "", "")
        console.print(tbl)

    # Normal items (collapsed)
    normal = [i for i in result.items if i.priority < 3]
    if normal:
        tbl = Table(title=f"TODOs ({len(normal)})", show_header=True)
        tbl.add_column("Tag", justify="center")
        tbl.add_column("Text")
        tbl.add_column("File", style="dim")
        tbl.add_column("Line", style="dim", justify="right")
        tbl.add_column("Module", style="dim")

        for item in normal[:30]:
            tbl.add_row(
                item.tag, item.text[:60],
                Path(item.file).name, str(item.line), item.module,
            )
        if len(normal) > 30:
            tbl.add_row("...", f"+{len(normal) - 30} more", "", "", "")
        console.print(tbl)

    # JSON output
    if json_output:
        data = {
            "files_scanned": result.files_scanned,
            "total_items": result.total_items,
            "by_tag": result.by_tag,
            "by_module": result.by_module,
            "by_priority": result.by_priority,
            "items": [
                {"tag": i.tag, "text": i.text, "file": i.file,
                 "line": i.line, "module": i.module, "priority": i.priority,
                 "context": i.context}
                for i in result.items
            ],
        }
        json_output.write_text(json_mod.dumps(data, indent=2), encoding='utf-8')
        console.print(f"\n[green]JSON saved:[/green] {json_output}")

    # Markdown report
    if output:
        lines = [
            "# TODO/FIXME/HACK Report", "",
            f"| Metric | Value |", "|---|---|",
            f"| **Total items** | {result.total_items} |",
            f"| **Files scanned** | {result.files_scanned} |",
        ]
        for t, c in sorted(result.by_tag.items(), key=lambda x: -x[1]):
            lines.append(f"| **{t}** | {c} |")
        lines.append("")

        if urgent:
            lines.append("## Urgent (HACK/FIXME/BUG)")
            lines.append("")
            lines.append("| Tag | Text | File | Line | Module |")
            lines.append("|---|---|---|---|---|")
            for item in urgent:
                lines.append(f"| `{item.tag}` | {item.text[:80]} | `{Path(item.file).name}` | L{item.line} | {item.module} |")
            lines.append("")

        output.write_text("\n".join(lines), encoding='utf-8')
        console.print(f"[green]Report saved:[/green] {output}")


@app.command(name="explain")
def explain_cmd(
    function: str = typer.Argument(..., help="Function name to explain (partial/fuzzy match supported)"),
    path: Path = typer.Option(".", "--path", "-p", help="Project root (auto-finds fullstack_index.json)"),
    depth: int = typer.Option(3, "--depth", "-d", help="Max depth to trace downstream calls"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Save markdown to file"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Save JSON summary to file"),
    diagram: bool = typer.Option(False, "--diagram", help="Show Mermaid diagram only"),
):
    """Explain a function's business flow in plain English.

    Traces the full call chain upstream (who triggers it) and downstream
    (what it calls), annotates each step with formulas, accumulators,
    business rules, and DB operations.

    Examples:
        lca explain calculate_total
        lca explain billing_model::save_bill
        lca explain ajax_calculate --depth 5
    """
    import json as json_mod

    # Auto-find index
    candidates = [
        path / "fullstack_index.json",
        path / "master_index.json",
        path / "lca_index.json",
        path / ".lca" / "fullstack_index.json",
        path / ".lca" / "index.json",
    ]
    idx_file = None
    for c in candidates:
        if c.exists():
            idx_file = c
            break

    if not idx_file:
        console.print("[red]Error:[/red] No LCA index found. Run `lca index` first.")
        console.print(f"[dim]Searched: {', '.join(str(c) for c in candidates)}[/dim]")
        raise typer.Exit(1)

    console.print(f"[dim]Loading index: {idx_file}[/dim]")
    idx = load_index(idx_file)

    from lca.analysis.flow_explainer import FlowExplainer

    explainer = FlowExplainer(idx)
    result = explainer.explain(function, depth=depth)

    if diagram:
        if result.mermaid:
            console.print(result.mermaid)
        else:
            console.print("[yellow]No diagram data available.[/yellow]")
        return

    # Render markdown to console
    from rich.markdown import Markdown
    console.print(Markdown(result.markdown))

    # Summary panel
    console.print(Panel.fit(
        f"[bold]{result.target}[/bold]\n"
        f"  Steps: {len(result.steps)}\n"
        f"  Tables: {', '.join(result.tables_touched) if result.tables_touched else 'none'}\n"
        f"  Callers: {result.total_callers}\n"
        f"  Risk: {result.risk_level}",
        title="Flow Summary",
        border_style="green" if result.risk_level == 'Low' else "yellow" if result.risk_level == 'Medium' else "red",
    ))

    # Save outputs
    if output:
        output.write_text(result.markdown, encoding='utf-8')
        console.print(f"\n[green]Saved:[/green] {output}")

    if json_output:
        data = {
            "target": result.target,
            "file": result.target_file,
            "risk_level": result.risk_level,
            "total_callers": result.total_callers,
            "tables_touched": result.tables_touched,
            "steps": [
                {
                    "order": s.order,
                    "layer": s.layer,
                    "function": s.function_name,
                    "file": s.file,
                    "line": s.line,
                    "direction": s.direction,
                    "formulas": len(s.formulas),
                    "accumulators": len(s.accumulators),
                    "conditionals": len(s.conditionals),
                    "db_operations": s.db_operations,
                }
                for s in result.steps
            ],
            "mermaid": result.mermaid,
        }
        json_output.write_text(json_mod.dumps(data, indent=2), encoding='utf-8')
        console.print(f"[green]JSON saved:[/green] {json_output}")


@app.command(name="guard")
def guard_cmd(
    path: Path = typer.Argument(..., help="Path to file or directory to scan"),
    module: Optional[str] = typer.Option(None, "--module", "-m", help="Filter to specific module/directory name"),
    severity: Optional[str] = typer.Option(None, "--severity", "-s", help="Filter by severity: CRITICAL, HIGH, MEDIUM, LOW"),
    rule: Optional[str] = typer.Option(None, "--rule", "-r", help="Filter by rule ID (e.g., GRD-JS-001)"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Write JSON results to file"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Write markdown report to file"),
):
    """Scan for missing safety checks — parseFloat, toFixed, transactions, WHERE clauses.

    Detects 11 types of business logic safety gaps that cause calculation bugs,
    data corruption, and silent failures.

    Examples:
        lca guard ./admin/application/
        lca guard ./admin/assets/js/ --severity HIGH
        lca guard billing_model.php
    """
    import json as json_mod
    from lca.analysis.guard import GuardScanner, GUARD_RULES

    if not path.exists():
        console.print(f"[red]Error:[/red] Path not found: {path}")
        raise typer.Exit(1)

    scanner = GuardScanner()

    if path.is_file():
        violations = scanner.scan_file(path)
        files_scanned = 1
    else:
        report = scanner.scan_directory(path, module_filter=module)
        violations = report.violations
        files_scanned = report.files_scanned

    # Apply filters
    if severity:
        violations = [v for v in violations if v.severity == severity.upper()]
    if rule:
        violations = [v for v in violations if v.rule_id == rule.upper()]

    # Sort by severity
    sev_order = {'CRITICAL': 0, 'HIGH': 1, 'MEDIUM': 2, 'LOW': 3}
    violations.sort(key=lambda v: sev_order.get(v.severity, 99))

    # Summary
    from collections import Counter
    sev_counts = Counter(v.severity for v in violations)
    rule_counts = Counter(v.rule_id for v in violations)

    border = "red" if sev_counts.get('CRITICAL', 0) > 0 else "yellow" if sev_counts.get('HIGH', 0) > 0 else "green"
    sev_str = " | ".join(f"{k}: {v}" for k, v in sorted(sev_counts.items(), key=lambda x: sev_order.get(x[0], 99)))

    console.print(Panel.fit(
        f"[bold]{len(violations)}[/bold] violations in {files_scanned} files\n"
        f"  {sev_str}",
        title="Guard Scanner",
        border_style=border,
    ))

    if not violations:
        console.print("[green]All clear — no missing safety checks found.[/green]")
        return

    # Rule breakdown table
    tbl = Table(title="By Rule", show_header=True)
    tbl.add_column("Rule", style="yellow")
    tbl.add_column("Title")
    tbl.add_column("Count", justify="right", style="bold")
    tbl.add_column("Severity", justify="center")
    for rule_id, count in rule_counts.most_common():
        info = GUARD_RULES.get(rule_id, {})
        sev_style = "red" if info.get('severity') in ('CRITICAL', 'HIGH') else "yellow"
        tbl.add_row(rule_id, info.get('title', '?'), str(count), f"[{sev_style}]{info.get('severity', '?')}[/{sev_style}]")
    console.print(tbl)

    # Violations table
    tbl = Table(title=f"Violations (top {min(len(violations), 50)})", show_header=True, show_lines=True)
    tbl.add_column("Severity", style="red", justify="center", width=8)
    tbl.add_column("Rule", style="yellow", width=12)
    tbl.add_column("Title", max_width=35)
    tbl.add_column("File", style="dim", max_width=25)
    tbl.add_column("Line", style="dim", justify="right", width=5)
    tbl.add_column("Function", style="cyan", max_width=30)

    for v in violations[:50]:
        sev_style = "red bold" if v.severity in ('CRITICAL', 'HIGH') else "yellow"
        tbl.add_row(
            f"[{sev_style}]{v.severity}[/{sev_style}]",
            v.rule_id, v.title,
            Path(v.file).name, str(v.line), v.function,
        )
    if len(violations) > 50:
        tbl.add_row("...", "", f"+{len(violations) - 50} more", "", "", "")
    console.print(tbl)

    # JSON output
    if json_output:
        data = {
            "files_scanned": files_scanned,
            "total_violations": len(violations),
            "by_severity": dict(sev_counts),
            "by_rule": dict(rule_counts),
            "violations": [
                {
                    "rule_id": v.rule_id, "severity": v.severity,
                    "title": v.title, "description": v.description,
                    "file": v.file, "line": v.line,
                    "function": v.function, "snippet": v.snippet,
                    "recommendation": v.recommendation,
                }
                for v in violations
            ],
        }
        json_output.write_text(json_mod.dumps(data, indent=2), encoding='utf-8')
        console.print(f"\n[green]JSON saved:[/green] {json_output}")

    # Markdown output
    if output:
        lines_out = [
            "# Guard Scanner Report", "",
            f"| Metric | Value |", "|---|---|",
            f"| **Total violations** | {len(violations)} |",
            f"| **Files scanned** | {files_scanned} |",
        ]
        for s, c in sorted(sev_counts.items(), key=lambda x: sev_order.get(x[0], 99)):
            lines_out.append(f"| **{s}** | {c} |")
        lines_out.append("")

        lines_out.append("## Violations\n")
        lines_out.append("| Severity | Rule | Title | File | Line | Function |")
        lines_out.append("|---|---|---|---|---|---|")
        for v in violations:
            lines_out.append(
                f"| {v.severity} | {v.rule_id} | {v.title} | "
                f"`{Path(v.file).name}` | L{v.line} | {v.function} |"
            )
        lines_out.append("")
        output.write_text("\n".join(lines_out), encoding='utf-8')
        console.print(f"[green]Report saved:[/green] {output}")


@app.command(name="validate")
def validate_cmd(
    path: Path = typer.Argument(..., help="Path to file or directory to scan"),
    module: Optional[str] = typer.Option(None, "--module", "-m", help="Filter to specific module/directory name"),
    severity: Optional[str] = typer.Option(None, "--severity", "-s", help="Filter by severity: CRITICAL, HIGH, MEDIUM, LOW"),
    rule: Optional[str] = typer.Option(None, "--rule", "-r", help="Filter by rule ID (e.g., VAL-PHP-001)"),
    json_output: Optional[Path] = typer.Option(None, "--json", "-j", help="Write JSON results to file"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Write markdown report to file"),
):
    """Scan for missing input/data validations on write operations.

    Detects validation gaps where data enters the system without proper checks:
    financial amounts, duplicate keys, status guards, permission checks, XSS, etc.

    Examples:
        lca validate ./admin/application/
        lca validate ./admin/application/models/ --severity HIGH
        lca validate billing_model.php
    """
    import json as json_mod
    from lca.analysis.validate import ValidationScanner, VALIDATE_RULES

    if not path.exists():
        console.print(f"[red]Error:[/red] Path not found: {path}")
        raise typer.Exit(1)

    scanner = ValidationScanner()

    if path.is_file():
        gaps = scanner.scan_file(path)
        files_scanned = 1
    else:
        report = scanner.scan_directory(path, module_filter=module)
        gaps = report.gaps
        files_scanned = report.files_scanned

    # Apply filters
    if severity:
        gaps = [g for g in gaps if g.severity == severity.upper()]
    if rule:
        gaps = [g for g in gaps if g.rule_id == rule.upper()]

    # Sort by severity
    sev_order = {'CRITICAL': 0, 'HIGH': 1, 'MEDIUM': 2, 'LOW': 3}
    gaps.sort(key=lambda g: sev_order.get(g.severity, 99))

    # Summary
    from collections import Counter
    sev_counts = Counter(g.severity for g in gaps)
    rule_counts = Counter(g.rule_id for g in gaps)
    table_counts = Counter(g.table for g in gaps if g.table)

    border = "red" if sev_counts.get('CRITICAL', 0) > 0 else "yellow" if sev_counts.get('HIGH', 0) > 0 else "green"
    sev_str = " | ".join(f"{k}: {v}" for k, v in sorted(sev_counts.items(), key=lambda x: sev_order.get(x[0], 99)))

    console.print(Panel.fit(
        f"[bold]{len(gaps)}[/bold] validation gaps in {files_scanned} files\n"
        f"  {sev_str}",
        title="Validation Gap Scanner",
        border_style=border,
    ))

    if not gaps:
        console.print("[green]All clear — no validation gaps found.[/green]")
        return

    # Rule breakdown table
    tbl = Table(title="By Rule", show_header=True)
    tbl.add_column("Rule", style="yellow")
    tbl.add_column("Title")
    tbl.add_column("Count", justify="right", style="bold")
    tbl.add_column("Severity", justify="center")
    for rule_id, count in rule_counts.most_common():
        info = VALIDATE_RULES.get(rule_id, {})
        sev_style = "red" if info.get('severity') in ('CRITICAL', 'HIGH') else "yellow"
        tbl.add_row(rule_id, info.get('title', '?'), str(count), f"[{sev_style}]{info.get('severity', '?')}[/{sev_style}]")
    console.print(tbl)

    # Tables with most gaps
    if table_counts:
        tbl = Table(title="Most Affected Tables", show_header=True)
        tbl.add_column("Table", style="cyan")
        tbl.add_column("Gaps", justify="right", style="bold red")
        for table, count in table_counts.most_common(10):
            tbl.add_row(table, str(count))
        console.print(tbl)

    # Gaps table
    tbl = Table(title=f"Validation Gaps (top {min(len(gaps), 50)})", show_header=True, show_lines=True)
    tbl.add_column("Severity", style="red", justify="center", width=8)
    tbl.add_column("Rule", style="yellow", width=12)
    tbl.add_column("Title", max_width=35)
    tbl.add_column("File", style="dim", max_width=25)
    tbl.add_column("Line", style="dim", justify="right", width=5)
    tbl.add_column("Function", style="cyan", max_width=30)

    for g in gaps[:50]:
        sev_style = "red bold" if g.severity in ('CRITICAL', 'HIGH') else "yellow"
        tbl.add_row(
            f"[{sev_style}]{g.severity}[/{sev_style}]",
            g.rule_id, g.title,
            Path(g.file).name, str(g.line), g.function,
        )
    if len(gaps) > 50:
        tbl.add_row("...", "", f"+{len(gaps) - 50} more", "", "", "")
    console.print(tbl)

    # JSON output
    if json_output:
        data = {
            "files_scanned": files_scanned,
            "total_gaps": len(gaps),
            "by_severity": dict(sev_counts),
            "by_rule": dict(rule_counts),
            "by_table": dict(table_counts),
            "gaps": [
                {
                    "rule_id": g.rule_id, "severity": g.severity,
                    "title": g.title, "description": g.description,
                    "file": g.file, "line": g.line,
                    "function": g.function, "table": g.table,
                    "column": g.column, "snippet": g.snippet,
                    "recommendation": g.recommendation,
                }
                for g in gaps
            ],
        }
        json_output.write_text(json_mod.dumps(data, indent=2), encoding='utf-8')
        console.print(f"\n[green]JSON saved:[/green] {json_output}")

    # Markdown output
    if output:
        lines_out = [
            "# Validation Gap Scanner Report", "",
            f"| Metric | Value |", "|---|---|",
            f"| **Total gaps** | {len(gaps)} |",
            f"| **Files scanned** | {files_scanned} |",
        ]
        for s, c in sorted(sev_counts.items(), key=lambda x: sev_order.get(x[0], 99)):
            lines_out.append(f"| **{s}** | {c} |")
        lines_out.append("")

        lines_out.append("## Gaps\n")
        lines_out.append("| Severity | Rule | Title | File | Line | Function | Table |")
        lines_out.append("|---|---|---|---|---|---|---|")
        for g in gaps:
            lines_out.append(
                f"| {g.severity} | {g.rule_id} | {g.title} | "
                f"`{Path(g.file).name}` | L{g.line} | {g.function} | {g.table} |"
            )
        lines_out.append("")
        output.write_text("\n".join(lines_out), encoding='utf-8')
        console.print(f"[green]Report saved:[/green] {output}")


@app.command(name="deep-analyze")
def deep_analyze(
    path: Path = typer.Argument(..., help="Path to JS/PHP file or directory to analyze"),
    function: Optional[str] = typer.Option(None, "--function", "-f", help="Filter by function name (contains)"),
    pattern: str = typer.Option("all", "--pattern", "-p", help="Pattern to show: all, formulas, accumulators, conditionals, magic_numbers"),
    limit: int = typer.Option(50, "--limit", "-n", help="Max results per pattern"),
    output_file: Optional[Path] = typer.Option(None, "--output", "-o", help="Save JSON output to file"),
):
    """Run deep analysis on JS/PHP files to extract formulas, accumulators, conditionals, and magic numbers."""
    from lca.analysis.code_extractor import CodeExtractor
    import json

    extractor = CodeExtractor()

    all_formulas = []
    all_accumulators = []
    all_conditionals = []
    all_magic_numbers = []

    # Collect files
    if path.is_file():
        files = [path]
    elif path.is_dir():
        files = []
        for root, dirs, filenames in path.walk():
            dirs[:] = [d for d in dirs if d not in {'.git', 'node_modules', 'vendor', '__pycache__', 'logs', 'cache', 'libraries', 'third_party'}]
            for name in filenames:
                f = root / name
                if f.suffix.lower() in ('.js', '.jsx', '.ts', '.tsx', '.mjs', '.php', '.phtml', '.inc'):
                    files.append(f)
    else:
        console.print(f"[red]Error:[/red] Path not found: {path}")
        raise typer.Exit(1)

    console.print(f"[dim]Analyzing {len(files)} file(s)...[/dim]")

    for fp in files:
        try:
            formulas, accumulators, conditionals, magic_numbers = extractor.analyze_file(fp)
            all_formulas.extend(formulas)
            all_accumulators.extend(accumulators)
            all_conditionals.extend(conditionals)
            all_magic_numbers.extend(magic_numbers)
        except Exception as e:
            console.print(f"[yellow]Warning:[/yellow] {fp.name}: {e}")

    # Apply function filter
    if function:
        fl = function.lower()
        all_formulas = [f for f in all_formulas if fl in f.function.lower()]
        all_accumulators = [a for a in all_accumulators if fl in a.function.lower()]
        all_conditionals = [c for c in all_conditionals if fl in c.function.lower()]
        all_magic_numbers = [m for m in all_magic_numbers if fl in m.function.lower()]

    # Summary panel
    console.print(Panel(
        f"[bold]Formulas:[/bold] {len(all_formulas)}  |  "
        f"[bold]Accumulators:[/bold] {len(all_accumulators)}  |  "
        f"[bold]Conditionals:[/bold] {len(all_conditionals)}  |  "
        f"[bold]Magic Numbers:[/bold] {len(all_magic_numbers)}",
        title="Deep Analysis Results",
        border_style="green",
    ))

    # Show tables based on pattern filter
    show_all = pattern == 'all'

    if show_all or pattern == 'formulas':
        table = Table(title=f"Formulas (top {limit})", show_lines=True)
        table.add_column("Line", style="dim", width=6)
        table.add_column("Function", style="cyan", max_width=30)
        table.add_column("Variable", style="green")
        table.add_column("Expression", max_width=60)
        table.add_column("Operator", style="yellow")
        table.add_column("File", style="dim", max_width=20)
        for f in all_formulas[:limit]:
            table.add_row(str(f.line), f.function, f.variable,
                          f.expression[:60], f.operator, Path(f.file).name)
        console.print(table)

    if show_all or pattern == 'accumulators':
        table = Table(title=f"Accumulators (top {limit})", show_lines=True)
        table.add_column("Line", style="dim", width=6)
        table.add_column("Function", style="cyan", max_width=30)
        table.add_column("Variable", style="green")
        table.add_column("Source Field", max_width=50)
        table.add_column("Wrapper", style="yellow")
        table.add_column("Direction", style="magenta")
        for a in all_accumulators[:limit]:
            table.add_row(str(a.line), a.function, a.variable,
                          a.source_field[:50], a.wrapper or '', a.direction)
        console.print(table)

    if show_all or pattern == 'conditionals':
        # Group by function::variable for dispatch tables
        from collections import defaultdict
        groups: dict[str, list] = defaultdict(list)
        for c in all_conditionals:
            groups[f"{c.function}::{c.variable}"].append(c)

        # Show dispatch tables (3+ branches = interesting)
        dispatch_tables = {k: v for k, v in groups.items() if len(v) >= 3}
        table = Table(title=f"Dispatch Tables ({len(dispatch_tables)} tables with 3+ branches)", show_lines=True)
        table.add_column("Function::Variable", style="cyan", max_width=40)
        table.add_column("Branches", style="yellow", width=8)
        table.add_column("Values", max_width=60)
        shown = 0
        for key, branches in sorted(dispatch_tables.items(), key=lambda x: -len(x[1])):
            if shown >= limit:
                break
            values = ', '.join(b.value[:15] for b in branches[:8])
            if len(branches) > 8:
                values += f' (+{len(branches) - 8} more)'
            table.add_row(key, str(len(branches)), values)
            shown += 1
        console.print(table)

    if show_all or pattern == 'magic_numbers':
        # Aggregate by value for frequency table
        from collections import Counter
        value_counts = Counter(m.value for m in all_magic_numbers)
        table = Table(title=f"Magic Number Frequency (top {limit})", show_lines=True)
        table.add_column("Value", style="red")
        table.add_column("Count", style="yellow", justify="right")
        table.add_column("Example Context", max_width=60)
        for value, count in value_counts.most_common(limit):
            ctx = next(m for m in all_magic_numbers if m.value == value)
            table.add_row(value, str(count), ctx.context[:60])
        console.print(table)

    # Save JSON output
    if output_file:
        result = {
            'summary': {
                'formulas': len(all_formulas),
                'accumulators': len(all_accumulators),
                'conditionals': len(all_conditionals),
                'magic_numbers': len(all_magic_numbers),
            },
            'formulas': [f.model_dump() for f in all_formulas],
            'accumulators': [a.model_dump() for a in all_accumulators],
            'conditionals': [c.model_dump() for c in all_conditionals],
            'magic_numbers': [m.model_dump() for m in all_magic_numbers],
        }
        output_file.write_text(json.dumps(result, indent=2), encoding='utf-8')
        console.print(f"[green]JSON output saved:[/green] {output_file}")


baseline_app = typer.Typer(name="baseline", help="Manage and compare health baselines")
app.add_typer(baseline_app)

@baseline_app.command("save")
def save_baseline(
    version: str = typer.Argument(..., help="Version or tag name to save as (e.g., v1.2.0)"),
    input_file: Path = typer.Option(..., "--input", "-i", help="Health JSON file to save"),
    project_root: Optional[Path] = typer.Option(None, "--root", "-r", help="Project root (default: current dir)"),
):
    """Save a health JSON report as a baseline for future comparison."""
    import shutil
    if not input_file.exists():
        console.print(f"[red]Error:[/red] Input file not found: {input_file}")
        raise typer.Exit(1)
        
    root = project_root or Path.cwd()
    baselines_dir = root / ".lca" / "baselines"
    baselines_dir.mkdir(parents=True, exist_ok=True)
    
    dest = baselines_dir / f"{version}.json"
    shutil.copy2(input_file, dest)
    console.print(f"[green]✓[/green] Saved baseline '{version}' to {dest}")


@baseline_app.command("diff")
def diff_baseline(
    version: str = typer.Argument(..., help="Baseline version to compare against"),
    current_file: Path = typer.Option(..., "--current", "-c", help="Current health JSON file"),
    project_root: Optional[Path] = typer.Option(None, "--root", "-r", help="Project root (default: current dir)"),
):
    """Compare a current health report against a saved baseline."""
    root = project_root or Path.cwd()
    baseline_file = root / ".lca" / "baselines" / f"{version}.json"
    
    if not baseline_file.exists():
        console.print(f"[red]Error:[/red] Baseline not found: {baseline_file}")
        console.print(f"Available baselines:")
        for b in (root / ".lca" / "baselines").glob("*.json"):
            console.print(f"  - {b.stem}")
        raise typer.Exit(1)
        
    # Reuse existing diff_health logic
    from lca.analysis.health_diff import load_health_report, compare_reports, print_diff
    try:
        old_report = load_health_report(baseline_file)
        new_report = load_health_report(current_file)
        diff = compare_reports(old_report, new_report)
        print_diff(diff)
    except Exception as e:
        console.print(f"[red]Failed to compare reports:[/red] {str(e)}")
        raise typer.Exit(1)




# ═══════════════════════════════════════════════════════════════════════════════
# Context Assembly Command (Bug Context Engine)
# ═══════════════════════════════════════════════════════════════════════════════

import os
DEFAULT_SEMANTIC_STORE = os.path.join(os.path.expanduser("~"), ".logimax", "rag_store")


@app.command()
def context(
    query: str = typer.Argument(..., help="Bug description or investigation query"),
    n: int = typer.Option(10, "--top", "-n", help="Number of RAG results to include"),
    module: Optional[str] = typer.Option(None, "--module", "-m", help="Filter to specific module"),
    store_dir: str = typer.Option(DEFAULT_SEMANTIC_STORE, "--store", "-s", help="ChromaDB store"),
    output: Optional[Path] = typer.Option(None, "--output", "-o", help="Save context.md to file"),
    no_brain: bool = typer.Option(False, "--no-brain", help="Skip brain loading"),
    no_recipes: bool = typer.Option(False, "--no-recipes", help="Skip recipe search"),
    no_system: bool = typer.Option(False, "--no-system", help="Skip system brain"),
    code: bool = typer.Option(False, "--code", help="Include full code in context"),
    quiet: bool = typer.Option(False, "--quiet", "-q", help="Only output the context markdown"),
):
    """Assemble rich bug context from RAG, brains, and recipes.

    Given a natural language bug description, gathers:
      1. Relevant code via semantic search (RAG)
      2. Module brain sections (business rules, data flows, danger zones)
      3. System brain cross-module info (shared tables, dependencies)
      4. Matching bug fix recipes

    Output: Structured context.md ready for the Bug Fix Engine.

    Examples:
        lca context "discount calculation wrong on billing type 2"
        lca context "stock issue print CSS width broken" -m stock
        lca context "payment receipt total mismatch" -o context.md
    """
    try:
        from lca.context.assembler import ContextAssembler
    except ImportError as e:
        console.print(f"[red]Error:[/red] {e}")
        raise typer.Exit(1)

    project_root = str(Path.cwd())

    if not quiet:
        console.print(f"\n[bold cyan]🧠 LCA Context Assembly[/bold cyan]")
        console.print(f'  Query:   "{query}"')
        console.print(f"  Project: {project_root}")
        console.print(f"  Store:   {store_dir}\n")

    assembler = ContextAssembler(
        project_root=project_root,
        store_dir=store_dir,
    )

    with console.status("[bold cyan]Assembling context...") if not quiet else _nullcontext():
        ctx = assembler.assemble(
            query=query,
            top_k=n,
            include_code=True,
            include_brain=not no_brain,
            include_system=not no_system,
            include_recipes=not no_recipes,
            module_filter=module,
        )

    # Remove code text if not requested (keep output concise)
    if not code:
        for r in ctx.code_results:
            r.text = ''

    markdown = ctx.to_markdown()

    if output:
        output.write_text(markdown, encoding='utf-8')
        if not quiet:
            console.print(f"[green]✅ Context saved to:[/green] {output}")

    if quiet:
        print(markdown)
    else:
        # Summary
        console.print(f"  [green]✅ Context assembled in {ctx.assembly_time_sec:.1f}s[/green]")
        console.print(f"  Modules: {', '.join(ctx.detected_modules) or 'none detected'}")
        console.print(f"  Code results: {len(ctx.code_results)}")
        console.print(f"  Brain sections: {len(ctx.brain_sections)}")
        console.print(f"  System brain: {len(ctx.system_brain_sections)}")
        console.print(f"  Recipes: {len(ctx.recipes)}")

        if output:
            console.print(f"\n  [dim]Full context written to {output}[/dim]")
        else:
            console.print(f"\n  [dim]Use --output context.md to save, or --quiet for raw markdown[/dim]")

        # Show detected modules and top 5 code results inline
        if ctx.code_results:
            console.print(f"\n[bold]Top code results:[/bold]")
            for i, r in enumerate(ctx.code_results[:5], 1):
                console.print(
                    f"  #{i} {r.summary or r.id}\n"
                    f"      [dim]{r.file_path}:L{r.start_line}-L{r.end_line} | "
                    f"{r.module} | {r.relevance:.0f}%[/dim]"
                )

        if ctx.recipes:
            console.print(f"\n[bold yellow]⚡ Matching recipes found![/bold yellow]")
            for r in ctx.recipes:
                console.print(f"  • {r.title} ({r.module})")

        if ctx.brain_sections:
            console.print(f"\n[bold]Brain sections loaded:[/bold]")
            for s in ctx.brain_sections[:8]:
                console.print(f"  • [{s.module}] {s.section_title} ({s.doc_type})")

        console.print()


class _nullcontext:
    """Minimal context manager for Python 3.6 compat."""
    def __enter__(self): return self
    def __exit__(self, *args): pass


# ═══════════════════════════════════════════════════════════════════════════════
# Semantic Search Commands (ChromaDB-powered natural language code search)
# ═══════════════════════════════════════════════════════════════════════════════



@app.command()
def semantic(
    question: str = typer.Argument(..., help="Natural language query"),
    n: int = typer.Option(5, "--top", "-n", help="Number of results"),
    module: Optional[str] = typer.Option(None, "--module", "-m", help="Filter by module"),
    chunk_type: Optional[str] = typer.Option(None, "--type", "-t", help="Filter by chunk type"),
    store_dir: str = typer.Option(DEFAULT_SEMANTIC_STORE, "--store", "-s", help="ChromaDB store"),
    collection: str = typer.Option("codebase", "--collection", "-c", help="Collection name"),
    show_code: bool = typer.Option(False, "--code", help="Show full code content"),
    expand: bool = typer.Option(False, "--expand", "-e", help="Expand results with LCA call graph (callers/callees/tables)"),
    index_path: Optional[Path] = typer.Option(None, "--index", "-i", help="LCA index JSON for --expand (auto-detected if omitted)"),
    hybrid: bool = typer.Option(True, "--hybrid/--no-hybrid", help="Use hybrid search (vector + keyword fusion). Default: on"),
):
    """Search codebase with natural language — powered by vector embeddings."""
    try:
        from lca.semantic.store import query_chunks, hybrid_query_chunks
    except ImportError:
        console.print("[red]Error:[/red] chromadb not installed. Run: pip install chromadb")
        raise typer.Exit(1)

    where_filter = {}
    if module:
        where_filter["module"] = module
    if chunk_type:
        where_filter["chunk_type"] = chunk_type

    if hybrid:
        results = hybrid_query_chunks(
            question, store_dir, collection,
            n_results=n,
            where_filter=where_filter if where_filter else None,
        )
    else:
        results = query_chunks(
            question, store_dir, collection,
            n_results=n,
            where_filter=where_filter if where_filter else None,
        )

    if not results:
        console.print("[yellow]No results found.[/yellow]")
        console.print("[dim]Tip: Run 'lca semantic-ingest' first to build the vector index.[/dim]")
        raise typer.Exit(0)

    # Load LCA index for graph expansion
    lca_index = None
    if expand:
        idx_file = index_path
        if not idx_file:
            # Auto-detect: look for fullstack_index.json in common locations
            for candidate in [
                Path(".") / "fullstack_index.json",
                Path(".") / ".lca" / "fullstack_index.json",
                Path(".lca") / "index.json",
            ]:
                if candidate.exists():
                    idx_file = candidate
                    break
        if idx_file and idx_file.exists():
            try:
                lca_index = load_index(idx_file)
                console.print(f"[dim]📊 Graph expansion enabled (index: {idx_file})[/dim]")
            except Exception as e:
                console.print(f"[yellow]Warning: Could not load index for expansion: {e}[/yellow]")
        else:
            console.print("[yellow]Warning: --expand requires an LCA index. Run 'lca index' first or pass --index.[/yellow]")

    console.print(f"\n[bold cyan]🔍 Top {len(results)} results for:[/bold cyan] \"{question}\"\n")

    for i, r in enumerate(results, 1):
        meta = r['metadata']
        distance = r['distance']
        relevance = max(0, (1 - distance) * 100)

        title = meta.get('summary', r['id'])
        file_path = meta.get('file_path', '?')
        chunk_t = meta.get('chunk_type', '?')
        mod = meta.get('module', '?')
        lines_info = f"L{meta.get('start_line', '?')}-L{meta.get('end_line', '?')}"

        console.print(f"[bold]#{i}[/bold] {title}")
        console.print(f"  [dim]📁 {file_path}:{lines_info} | 📦 {mod} | 🏷️ {chunk_t} | 🎯 {relevance:.0f}%[/dim]")

        if meta.get('tables'):
            console.print(f"  [cyan]Tables:[/cyan] {meta['tables']}")
        if meta.get('models'):
            console.print(f"  [cyan]Models:[/cyan] {meta['models']}")

        # ── Graph expansion ──
        if expand and lca_index and meta.get('method_name'):
            func_name = meta['method_name']
            class_name = meta.get('class_name', '')
            full_name = f"{class_name}::{func_name}" if class_name else func_name

            analyzer = ImpactAnalyzer(lca_index)
            try:
                callers = analyzer.get_callers(full_name)
                callees = analyzer.get_callees(full_name)

                if callers:
                    caller_names = [c[0] for c in callers[:5]]
                    console.print(f"  [green]⬆ Called by:[/green] {', '.join(caller_names)}")
                if callees:
                    callee_names = [c[0] for c in callees[:5]]
                    console.print(f"  [yellow]⬇ Calls:[/yellow] {', '.join(callee_names)}")

                if not callers and not callees:
                    console.print(f"  [dim]⊘ No graph connections found for {full_name}[/dim]")
            except Exception:
                pass  # Silently skip if function not in index

        if show_code:
            code_lines = r['document'].split('\n')
            code_preview = '\n'.join(code_lines[:30])
            console.print(Panel(code_preview, title="Code", border_style="dim"))
        console.print()


@app.command(name="semantic-ingest")
def semantic_ingest(
    project_root: Path = typer.Argument(".", help="Project root directory"),
    store_dir: str = typer.Option(DEFAULT_SEMANTIC_STORE, "--store", "-s", help="ChromaDB store"),
    collection: str = typer.Option("codebase", "--collection", "-c", help="Collection name"),
    include_docs: bool = typer.Option(True, "--docs/--no-docs", help="Include .md files"),
    include_brains: bool = typer.Option(True, "--brains/--no-brains", help="Include knowledge_brain/"),
    do_rebuild: bool = typer.Option(False, "--rebuild", help="Delete existing collection first"),
):
    """Build the semantic vector index for natural language code search."""
    from rich.progress import Progress, SpinnerColumn, TextColumn, BarColumn

    try:
        from lca.semantic.chunker import ingest_directory
        from lca.semantic.models import IngestStats
        from lca.semantic.store import store_chunks, delete_collection
    except ImportError:
        console.print("[red]Error:[/red] chromadb not installed. Run: pip install chromadb")
        raise typer.Exit(1)

    source = str(project_root)
    if not Path(source).exists():
        console.print(f"[red]Error: Source directory not found: {source}[/red]")
        raise typer.Exit(1)

    console.print(f"\n[bold cyan]⚡ LCA Semantic — Building Vector Index[/bold cyan]")
    console.print(f"  Source: {source}")
    console.print(f"  Store:  {store_dir}")
    console.print(f"  Collection: {collection}\n")

    if do_rebuild:
        console.print("[yellow]  Deleting existing collection...[/yellow]")
        delete_collection(store_dir, collection)

    stats = IngestStats()
    all_chunks = []

    with Progress(
        SpinnerColumn(), TextColumn("[progress.description]{task.description}"),
        BarColumn(), TextColumn("{task.completed} chunks"), console=console,
    ) as progress:
        task = progress.add_task("Chunking files...", total=None)
        for chunk in ingest_directory(source, include_docs, include_brains):
            all_chunks.append(chunk)
            stats.total_chunks += 1
            ct = chunk.chunk_type.value
            stats.by_type[ct] = stats.by_type.get(ct, 0) + 1
            stats.by_module[chunk.module] = stats.by_module.get(chunk.module, 0) + 1
            progress.update(task, completed=stats.total_chunks)

    stats.total_files = len(set(c.file_path for c in all_chunks))
    console.print(f"\n  [green]✅ Chunked {stats.total_files} files → {stats.total_chunks} chunks[/green]")

    console.print("  Embedding and storing in ChromaDB...")
    stored = store_chunks(all_chunks, store_dir, collection)
    console.print(f"  [green]🧠 Stored {stored} chunks in ChromaDB[/green]\n")

    if stats.by_type:
        table = Table(title="By Chunk Type")
        table.add_column("Type", style="cyan")
        table.add_column("Count", justify="right")
        for t, c in sorted(stats.by_type.items(), key=lambda x: -x[1]):
            table.add_row(t, str(c))
        console.print(table)

    if stats.by_module:
        table = Table(title="By Module")
        table.add_column("Module", style="green")
        table.add_column("Count", justify="right")
        for m, c in sorted(stats.by_module.items(), key=lambda x: -x[1]):
            table.add_row(m, str(c))
        console.print(table)


@app.command(name="semantic-stats")
def semantic_stats(
    store_dir: str = typer.Option(DEFAULT_SEMANTIC_STORE, "--store", "-s", help="ChromaDB store"),
    collection: str = typer.Option("codebase", "--collection", "-c", help="Collection name"),
):
    """Show semantic vector index statistics."""
    try:
        from lca.semantic.store import get_collection_stats
    except ImportError:
        console.print("[red]Error:[/red] chromadb not installed. Run: pip install chromadb")
        raise typer.Exit(1)

    info = get_collection_stats(store_dir, collection)
    if "error" in info:
        console.print(f"[red]Error: {info['error']}[/red]")
        raise typer.Exit(1)

    console.print(f"\n[bold cyan]📊 Semantic Index Statistics[/bold cyan]")
    console.print(f"  Collection: {info['collection']}")
    console.print(f"  Store:      {info['persist_dir']}")
    console.print(f"  Total chunks: [bold]{info['total_chunks']}[/bold]\n")

    if info.get('sample_by_type'):
        table = Table(title="By Type (sample)")
        table.add_column("Type", style="cyan")
        table.add_column("Count", justify="right")
        for t, c in sorted(info['sample_by_type'].items(), key=lambda x: -x[1]):
            table.add_row(t, str(c))
        console.print(table)

    if info.get('sample_by_module'):
        table = Table(title="By Module (sample)")
        table.add_column("Module", style="green")
        table.add_column("Count", justify="right")
        for m, c in sorted(info['sample_by_module'].items(), key=lambda x: -x[1]):
            table.add_row(m, str(c))
        console.print(table)


@app.command(name="rag-sync")
def rag_sync(
    project_root: Path = typer.Argument(".", help="Project root directory"),
    store_dir: str = typer.Option(DEFAULT_SEMANTIC_STORE, "--store", "-s", help="ChromaDB store"),
    collection: str = typer.Option("codebase", "--collection", "-c", help="Collection name"),
    include_docs: bool = typer.Option(True, "--docs/--no-docs", help="Include .md files"),
    include_brains: bool = typer.Option(True, "--brains/--no-brains", help="Include knowledge_brain/"),
    force_full: bool = typer.Option(False, "--full", help="Force full rebuild (ignore manifest)"),
    dry_run: bool = typer.Option(False, "--dry-run", help="Show what would change without syncing"),
    output_format: str = typer.Option("text", "-o", "--output", help="Output format: text or json"),
):
    """Incremental sync of the semantic vector index.

    Only re-processes files that changed since the last sync.
    First run does a full build; subsequent runs are incremental.

    Examples:
        lca rag-sync                    # Incremental sync
        lca rag-sync --dry-run          # Preview changes
        lca rag-sync --full             # Force full rebuild
        lca rag-sync --no-docs          # Skip .md files
    """
    try:
        from lca.semantic.sync import incremental_sync
    except ImportError:
        console.print("[red]Error:[/red] chromadb not installed. Run: pip install chromadb")
        raise typer.Exit(1)

    source = str(project_root)
    if not Path(source).exists():
        console.print(f"[red]Error: Source directory not found: {source}[/red]")
        raise typer.Exit(1)

    if dry_run:
        console.print("\n[bold yellow]RAG Sync - DRY RUN (no changes will be made)[/bold yellow]")
    else:
        console.print("\n[bold cyan]RAG Sync - Incremental Vector Index Update[/bold cyan]")
    console.print(f"  Source: {source}")
    console.print(f"  Store:  {store_dir}")
    console.print(f"  Collection: {collection}\n")

    result = incremental_sync(
        source_dir=source,
        persist_dir=store_dir,
        collection_name=collection,
        include_docs=include_docs,
        include_brains=include_brains,
        force_full=force_full,
        dry_run=dry_run,
    )

    if output_format == 'json':
        import json as _json
        print(_json.dumps({
            'added_files': result.added_files,
            'modified_files': result.modified_files,
            'removed_files': result.removed_files,
            'unchanged_files': result.unchanged_files,
            'added_chunks': result.added_chunks,
            'removed_chunks': result.removed_chunks,
            'total_chunks': result.total_chunks,
            'elapsed_seconds': round(result.elapsed_seconds, 2),
            'errors': result.errors,
        }, indent=2))
    else:
        console.print(f"\n[bold green]Sync Complete[/bold green]")
        table = Table(title="Sync Summary")
        table.add_column("Metric", style="cyan")
        table.add_column("Value", justify="right")
        table.add_row("Files added", str(result.added_files))
        table.add_row("Files modified", str(result.modified_files))
        table.add_row("Files removed", str(result.removed_files))
        table.add_row("Files unchanged", str(result.unchanged_files))
        table.add_row("Chunks added", str(result.added_chunks))
        table.add_row("Chunks removed", str(result.removed_chunks))
        table.add_row("Total chunks", str(result.total_chunks))
        table.add_row("Time", f"{result.elapsed_seconds:.1f}s")
        console.print(table)

        if result.errors:
            console.print(f"\n[yellow]Warnings ({len(result.errors)}):[/yellow]")
            for err in result.errors[:10]:
                console.print(f"  [dim]- {err}[/dim]")


@app.command(name="rag-status")
def rag_status(
    project_root: Path = typer.Argument(".", help="Project root directory"),
    store_dir: str = typer.Option(DEFAULT_SEMANTIC_STORE, "--store", "-s", help="ChromaDB store"),
    include_docs: bool = typer.Option(True, "--docs/--no-docs", help="Include .md files"),
    include_brains: bool = typer.Option(True, "--brains/--no-brains", help="Include knowledge_brain/"),
    output_format: str = typer.Option("text", "-o", "--output", help="Output format: text or json"),
):
    """Check RAG index sync status without making changes.

    Shows: last sync time, total indexed files/chunks,
    and pending changes (added/modified/removed files).

    Examples:
        lca rag-status                  # Quick status check
        lca rag-status -o json          # Machine-readable output
    """
    try:
        from lca.semantic.sync import sync_status
    except ImportError:
        console.print("[red]Error:[/red] chromadb not installed. Run: pip install chromadb")
        raise typer.Exit(1)

    source = str(project_root)
    if not Path(source).exists():
        console.print(f"[red]Error: Source directory not found: {source}[/red]")
        raise typer.Exit(1)

    status = sync_status(source, store_dir, include_docs, include_brains)

    if output_format == 'json':
        import json as _json
        print(_json.dumps(status, indent=2))
    else:
        console.print("\n[bold cyan]RAG Index Status[/bold cyan]")

        if not status['manifest_exists']:
            console.print("  [yellow]No manifest found - index has never been synced[/yellow]")
            console.print("  Run: [bold]lca rag-sync[/bold] to build the index\n")
            return

        console.print(f"  Last sync:  {status['last_sync']}")
        console.print(f"  Backend:    {status.get('backend', 'unknown')}")
        console.print(f"  Indexed:    {status['total_indexed_files']} files, {status['total_indexed_chunks']} chunks\n")

        pending = status['pending']
        total_pending = pending['added'] + pending['modified'] + pending['removed']

        if total_pending == 0:
            console.print("  [green][OK] Index is up to date - no changes pending[/green]\n")
        else:
            console.print(f"  [yellow]Pending changes:[/yellow]")
            if pending['added'] > 0:
                console.print(f"    + {pending['added']} new files")
            if pending['modified'] > 0:
                console.print(f"    ~ {pending['modified']} modified files")
            if pending['removed'] > 0:
                console.print(f"    - {pending['removed']} removed files")
            console.print(f"    = {pending['unchanged']} unchanged files")
            console.print(f"\n  Run: [bold]lca rag-sync[/bold] to sync {total_pending} changes\n")


@app.command(name="brain-health")
def brain_health(
    path: Path = typer.Argument(..., help="Project root directory"),
    brain_dir: str = typer.Option("knowledge_brain", "--brain-dir", "-b", help="Brain directory name"),
):
    """Detect stale knowledge brain sections by comparing against source code changes.

    Compares last-modified dates of brain documents vs the source files they reference.
    Reports which modules need brain updates.
    """
    from datetime import datetime

    brain_path = Path(path) / brain_dir
    if not brain_path.exists():
        console.print(f"[red]Error:[/red] Brain directory not found: {brain_path}")
        raise typer.Exit(1)

    console.print(f"\n[bold cyan]🧠 Brain Health Check[/bold cyan]")
    console.print(f"  Brain: {brain_path}\n")

    # Find all module brain directories
    module_dirs = [d for d in brain_path.iterdir() if d.is_dir() and not d.name.startswith('_')]

    if not module_dirs:
        console.print("[yellow]No module brains found.[/yellow]")
        raise typer.Exit(0)

    # Source directories to check for each module
    source_dirs = {
        'controllers': Path(path) / 'application' / 'controllers',
        'models': Path(path) / 'application' / 'models',
        'views': Path(path) / 'application' / 'views',
        'js': Path(path) / 'assets' / 'js',
    }

    results = []
    for mod_dir in sorted(module_dirs):
        module_name = mod_dir.name
        brain_files = list(mod_dir.glob('*.md'))

        if not brain_files:
            continue

        # Latest brain file modification
        brain_mtime = max(f.stat().st_mtime for f in brain_files)
        brain_date = datetime.fromtimestamp(brain_mtime)

        # Find related source files by module name
        changed_files = []
        for src_type, src_dir in source_dirs.items():
            if not src_dir.exists():
                continue
            for src_file in src_dir.rglob('*'):
                if not src_file.is_file():
                    continue
                if module_name.lower() in src_file.name.lower():
                    src_mtime = src_file.stat().st_mtime
                    if src_mtime > brain_mtime:
                        days_stale = (datetime.now() - datetime.fromtimestamp(src_mtime)).days
                        changed_files.append({
                            'file': str(src_file.relative_to(path)),
                            'changed': datetime.fromtimestamp(src_mtime),
                            'days_stale': days_stale,
                        })

        status = "Fresh" if not changed_files else f"Stale ({len(changed_files)} files changed)"
        results.append({
            'module': module_name,
            'brain_docs': len(brain_files),
            'brain_updated': brain_date,
            'changed_files': changed_files,
            'status': status,
        })

    # Display results
    table = Table(title="Module Brain Status")
    table.add_column("Module", style="cyan")
    table.add_column("Brain Docs", justify="center")
    table.add_column("Last Updated", style="dim")
    table.add_column("Status")
    table.add_column("Stale Files", justify="right")

    fresh_count = 0
    stale_count = 0
    for r in results:
        is_stale = len(r['changed_files']) > 0
        if is_stale:
            stale_count += 1
        else:
            fresh_count += 1

        table.add_row(
            r['module'],
            str(r['brain_docs']),
            r['brain_updated'].strftime('%Y-%m-%d'),
            "[red]" + r['status'] + "[/red]" if is_stale else "[green]" + r['status'] + "[/green]",
            str(len(r['changed_files'])) if r['changed_files'] else "-",
        )

    console.print(table)

    # Show details for stale modules
    stale_modules = [r for r in results if r['changed_files']]
    if stale_modules:
        console.print(f"\n[bold yellow]⚠ {stale_count} module(s) need brain refresh:[/bold yellow]\n")
        for r in stale_modules:
            console.print(f"  [bold]{r['module']}[/bold] — {len(r['changed_files'])} source files changed since brain update:")
            for cf in r['changed_files'][:5]:
                console.print(f"    [dim]• {cf['file']} (changed {cf['days_stale']}d ago)[/dim]")
            if len(r['changed_files']) > 5:
                console.print(f"    [dim]  ... and {len(r['changed_files']) - 5} more[/dim]")
        console.print(f"\n[dim]Run 'lca brain <module> --auto-refresh' to update stale brains (coming soon)[/dim]")
    else:
        console.print(f"\n[green]✅ All {fresh_count} module brains are up to date.[/green]")


# ═══════════════════════════════════════════════════════════════════════════════
# ── BRAIN BUILD COMMAND ───────────────────────────────────────────────────────
# ═══════════════════════════════════════════════════════════════════════════════

@app.command(name="brain-build")
def brain_build(
    module: str = typer.Argument(..., help="Module name (e.g., billing, estimation, stock_issue)"),
    path: Path = typer.Option(".", "--path", "-p", help="Project root directory"),
    draft: bool = typer.Option(True, "--draft/--finalize", help="Generate draft (default) or finalize"),
    output_dir: Optional[str] = typer.Option(None, "--output", "-o", help="Output directory override"),
):
    """Build Brain v2.0 for a module — auto-generate + human review.

    Phase 1 (--draft): Auto-analyze code + DB schema → generate 6 brain docs + REVIEW_TRACKER.md
    Phase 2 (--finalize): Read completed REVIEW_TRACKER.md → finalize brain docs

    The draft generates:
      - WIRING.md (95% auto — file/table/route map)
      - STATUS_CODES.md (85% auto — magic values from DB)
      - REVIEW_TRACKER.md (Q&A items for human review)
      - Placeholder RULES.md, FLOWS.md, RISKS.md, PATTERNS.md

    Examples:
        lca brain-build billing
        lca brain-build estimation --path /path/to/project
        lca brain-build stock_issue --finalize
    """
    from lca.brain.builder import BrainBuilder

    project_root = str(Path(path).resolve())

    console.print(f"\n[bold cyan]🧠 Brain Builder v2.0[/bold cyan]")
    console.print(f"  Module:  {module}")
    console.print(f"  Project: {project_root}")
    console.print(f"  Mode:    {'DRAFT' if draft else 'FINALIZE'}\n")

    if draft:
        _brain_build_draft(module, project_root, output_dir)
    else:
        _brain_build_finalize(module, project_root, output_dir)


def _brain_build_draft(module: str, project_root: str, output_dir: Optional[str]):
    """Execute the draft phase of brain building."""
    from rich.progress import Progress, SpinnerColumn, TextColumn
    from lca.brain.builder import BrainBuilder

    builder = BrainBuilder(project_root, module)

    if output_dir:
        builder.brain_dir = Path(output_dir)

    # Progress callback
    steps = []
    def progress_cb(step, detail):
        steps.append((step, detail))
        console.print(f"  [dim]{step}:[/dim] {detail}")

    # Build draft
    console.print("[bold]Phase 1: Code Analysis[/bold]\n")
    result = builder.build_draft(progress_callback=progress_cb)

    # Summary
    console.print(f"\n[bold]Analysis Summary:[/bold]")
    console.print(f"  Methods extracted:    {len(result.methods)}")
    console.print(f"  Tables owned:         {len(result.tables_owned)}")
    console.print(f"  Tables referenced:    {len(result.tables_referenced)}")
    console.print(f"  Review items:         {len(result.review_items)}")

    # Big methods (risk indicators)
    big_methods = [m for m in result.methods if m.line_count > 200]
    if big_methods:
        console.print(f"\n[bold yellow]⚠ Large Methods (risk indicators):[/bold yellow]")
        for m in sorted(big_methods, key=lambda x: x.line_count, reverse=True)[:10]:
            console.print(f"    {m.name}() — {m.line_count} lines ({Path(m.file_path).name}:{m.start_line})")

    # Write docs
    console.print(f"\n[bold]Phase 2: Generating Brain Documents[/bold]\n")
    docs = builder.write_draft()

    for name, content in docs.items():
        lines = content.count('\n')
        console.print(f"  ✅ {name} ({lines} lines)")

    # Final instructions
    brain_dir = builder.brain_dir
    console.print(f"\n[bold green]✅ Draft brain generated at:[/bold green] {brain_dir}")
    console.print(f"\n[bold]Next steps:[/bold]")
    console.print(f"  1. Review [bold]{brain_dir / 'REVIEW_TRACKER.md'}[/bold]")
    console.print(f"  2. Answer the {len(result.review_items)} questions")
    console.print(f"  3. Run: [bold]lca brain-build {module} --finalize[/bold]")

    # Print review items summary by category
    categories = {}
    for item in result.review_items:
        categories.setdefault(item.category, 0)
        categories[item.category] += 1

    console.print(f"\n  Review breakdown:")
    for cat, count in sorted(categories.items()):
        console.print(f"    {cat}: {count} questions")


def _brain_build_finalize(module: str, project_root: str, output_dir: Optional[str]):
    """Execute the finalize phase — read completed tracker and build final docs."""
    brain_dir = Path(project_root) / "knowledge_brain" / f"{module}_v2"
    if output_dir:
        brain_dir = Path(output_dir)

    tracker_path = brain_dir / "REVIEW_TRACKER.md"
    if not tracker_path.exists():
        console.print(f"[red]Error:[/red] No REVIEW_TRACKER.md found at {tracker_path}")
        console.print(f"Run [bold]lca brain-build {module}[/bold] first to generate the draft.")
        raise typer.Exit(1)

    console.print(f"[yellow]Finalize mode is not yet implemented.[/yellow]")
    console.print(f"For now, review the tracker and update brain docs manually.")
    console.print(f"Tracker: {tracker_path}")


# ═══════════════════════════════════════════════════════════════════════════════
# ── CLIENT PROFILE COMMAND ────────────────────────────────────────────────────
# ═══════════════════════════════════════════════════════════════════════════════

@app.command()
def client_profile(
    path: Path = typer.Argument(None, help="Project root (default: auto-detect)"),
    output: Optional[str] = typer.Option(None, "--output", "-o", help="Output format: text, json"),
    save: Optional[Path] = typer.Option(None, "--save", "-s", help="Save report to file"),
):
    """Detect client configuration — modules, features, integrations, and print templates."""
    from lca.analysis.client_profile import ClientProfileScanner, format_profile_text, format_profile_json

    root = path or _find_project_root()
    if not root:
        console.print("[red]Error:[/red] Could not find project root. Pass path explicitly.")
        raise typer.Exit(1)

    root = Path(root)
    console.print(f"[bold]Scanning client profile:[/bold] {root}")

    with console.status("[bold cyan]Analyzing codebase..."):
        scanner = ClientProfileScanner()
        profile = scanner.scan(root)

    if output == 'json':
        import json
        result = format_profile_json(profile)
        json_str = json.dumps(result, indent=2)
        if save:
            save.write_text(json_str)
            console.print(f"[green]Saved to {save}[/green]")
        else:
            console.print(json_str)
    else:
        text = format_profile_text(profile)
        if save:
            save.write_text(text)
            console.print(f"[green]Saved to {save}[/green]")
        else:
            console.print(text)


# ═══════════════════════════════════════════════════════════════════════════════
# ── PRE-RELEASE SAFETY CHECK COMMAND ─────────────────────────────────────────
# ═══════════════════════════════════════════════════════════════════════════════

@app.command()
def pre_release(
    source: str = typer.Argument("HEAD", help="Source ref (branch, tag, or commit)"),
    target: str = typer.Argument("HEAD~1", help="Target ref to compare against"),
    path: Path = typer.Option(None, "--path", "-p", help="Project root"),
    staged: bool = typer.Option(False, "--staged", help="Check staged changes only"),
    output: Optional[str] = typer.Option(None, "--output", "-o", help="Output: text, json"),
    save: Optional[Path] = typer.Option(None, "--save", "-s", help="Save report to file"),
):
    """Pre-release safety check — scan git diff for risks before deployment."""
    from lca.analysis.pre_release import PreReleaseScanner, format_report_text, format_report_json

    root = path or _find_project_root()
    if not root:
        console.print("[red]Error:[/red] Could not find project root.")
        raise typer.Exit(1)

    root = Path(root)
    if staged:
        console.print(f"[bold]Pre-release check:[/bold] staged changes in {root}")
    else:
        console.print(f"[bold]Pre-release check:[/bold] {target} -> {source}")

    with console.status("[bold cyan]Scanning diff for risks..."):
        scanner = PreReleaseScanner()
        report = scanner.scan(root, source, target, staged)

    if output == 'json':
        import json
        result = format_report_json(report)
        json_str = json.dumps(result, indent=2)
        if save:
            save.write_text(json_str)
            console.print(f"[green]Saved to {save}[/green]")
        else:
            console.print(json_str)
    else:
        text = format_report_text(report)
        if save:
            save.write_text(text)
            console.print(f"[green]Saved to {save}[/green]")
        else:
            console.print(text)

    # Exit code based on verdict
    if report.verdict == 'BLOCK':
        raise typer.Exit(2)
    elif report.verdict == 'RISKY':
        raise typer.Exit(1)


# ── SDLC Pipeline Integration ────────────────────────────────────────

@app.command('sdlc-review')
def sdlc_review_cmd(
    project_root: Path = typer.Argument('.', help='Project root directory'),
    files: Optional[str] = typer.Option(None, '--files', '-f', help='Comma-separated file list (auto-detects from git if omitted)'),
    no_brain: bool = typer.Option(False, '--no-brain', help='Skip module brain business rule checks'),
    output: str = typer.Option('text', '--output', '-o', help='Output format: text or json'),
    save: Optional[Path] = typer.Option(None, '--save', '-s', help='Save report to file'),
):
    """SDLC code review -- aggregated quality checks for the pipeline REVIEW phase.

    Combines guard rails, security, hardcoded values, SELECT *, and module brain
    business rules into a single review pass with a verdict.

    Exit codes: 0=PASS, 1=ADVISORIES, 2=ISSUES_FOUND

    Examples:
        lca sdlc-review
        lca sdlc-review --files admin/application/models/Billing_model.php,admin/assets/js/billing.js
        lca sdlc-review --output json --save review.json
    """
    import json as _json

    from lca.analysis.sdlc_review import SDLCReviewer, format_review_text

    root = Path(project_root).resolve()
    reviewer = SDLCReviewer(root)

    changed = None
    if files:
        changed = [f.strip() for f in files.split(',') if f.strip()]

    with console.status('[bold cyan]Running SDLC code review...'):
        report = reviewer.review(changed_files=changed, check_brain=not no_brain)

    # Verdict banner
    verdict_style = {
        'PASS': 'green',
        'PASS_WITH_ADVISORIES': 'yellow',
        'ISSUES_FOUND': 'red',
    }
    style = verdict_style.get(report.verdict, 'white')

    if output == 'json':
        result = _json.dumps(report.to_dict(), indent=2)
        if save:
            save.write_text(result, encoding='utf-8')
            console.print(f'[green]Saved to {save}[/green]')
        else:
            console.print(result)
    else:
        console.print(Panel.fit(
            f'[bold {style}]{report.verdict}[/bold {style}]\n'
            f'Files: {len(report.changed_files)} | '
            f'Findings: {len(report.findings)} '
            f'(C:{report.critical_count} H:{report.high_count} '
            f'W:{report.warning_count} A:{report.advisory_count})',
            title='SDLC Code Review',
            border_style=style,
        ))

        if report.findings:
            table = Table(show_header=True)
            table.add_column('#', style='dim', width=3)
            table.add_column('Sev', style='bold', width=8)
            table.add_column('Rule', style='cyan', width=12)
            table.add_column('File', style='magenta')
            table.add_column('Issue', max_width=40)
            table.add_column('Fix', style='dim', max_width=40)

            sev_colors = {'CRITICAL': 'red', 'HIGH': 'red', 'MEDIUM': 'yellow', 'LOW': 'dim', 'ADVISORY': 'blue'}
            severity_order = {'CRITICAL': 0, 'HIGH': 1, 'MEDIUM': 2, 'LOW': 3, 'ADVISORY': 4}
            sorted_findings = sorted(report.findings, key=lambda f: severity_order.get(f.severity, 5))

            for i, f in enumerate(sorted_findings[:30], 1):
                c = sev_colors.get(f.severity, 'dim')
                table.add_row(
                    str(i),
                    f'[{c}]{f.severity}[/{c}]',
                    f.rule_id,
                    f'{Path(f.file).name}:{f.line}',
                    f.title[:40],
                    f.recommendation[:40],
                )
            console.print(table)
            if len(report.findings) > 30:
                console.print(f'  [dim]... and {len(report.findings) - 30} more findings[/dim]')

        if report.brain_rules_checked:
            console.print(f'\n[bold]Business Rules Checked:[/bold] {len(report.brain_rules_checked)}')
            for rule in report.brain_rules_checked[:10]:
                console.print(f'  [green]OK[/green] {rule[:80]}')

        # Save text version if requested
        if save:
            text = format_review_text(report)
            save.write_text(text, encoding='utf-8')
            console.print(f'\n[green]Saved to {save}[/green]')

    # Exit code
    if report.verdict == 'ISSUES_FOUND':
        raise typer.Exit(2)
    elif report.verdict == 'PASS_WITH_ADVISORIES':
        raise typer.Exit(1)


@app.command('sdlc-plan')
def sdlc_plan_cmd(
    project_root: Path = typer.Argument('.', help='Project root directory'),
    files: str = typer.Option(..., '--files', '-f', help='Comma-separated list of files to analyze'),
    output: str = typer.Option('text', '--output', '-o', help='Output format: text or json'),
    save: Optional[Path] = typer.Option(None, '--save', '-s', help='Save matrix to file'),
):
    """SDLC impact matrix -- trace callers, tables, risk for the PLANNING phase.

    Analyzes target files to show who calls them, what tables they touch,
    risk scores, and affected business rules. Feeds into implementation plans.

    Examples:
        lca sdlc-plan --files admin/application/models/Billing_model.php
        lca sdlc-plan --files Billing_model.php,billing.js --output json
    """
    import json as _json

    from lca.analysis.sdlc_plan import SDLCPlanner, format_plan_text

    root = Path(project_root).resolve()
    target_files = [f.strip() for f in files.split(',') if f.strip()]

    planner = SDLCPlanner(root)

    with console.status('[bold cyan]Analyzing impact...'):
        matrix = planner.analyze(target_files)

    # Complexity color
    complexity_style = {'S': 'green', 'M': 'yellow', 'L': 'red', 'XL': 'bold red'}
    cstyle = complexity_style.get(matrix.complexity, 'white')

    if output == 'json':
        result = _json.dumps(matrix.to_dict(), indent=2)
        if save:
            save.write_text(result, encoding='utf-8')
            console.print(f'[green]Saved to {save}[/green]')
        else:
            console.print(result)
    else:
        console.print(Panel.fit(
            f'Complexity: [{cstyle}]{matrix.complexity}[/{cstyle}] | '
            f'Cross-module: {"[red]YES[/red]" if matrix.cross_module else "[green]No[/green]"} | '
            f'Modules: {", ".join(matrix.modules_affected) or "N/A"}\n'
            f'Callers: {matrix.total_callers} | '
            f'Tables: {matrix.total_tables} | '
            f'Max Risk: {matrix.max_risk:.2f}',
            title='SDLC Impact Matrix',
            border_style=cstyle.replace('bold ', ''),
        ))

        if matrix.danger_zones:
            console.print('\n[bold red]DANGER ZONES:[/bold red]')
            for dz in matrix.danger_zones:
                console.print(f'  [red]![/red] {dz}')

        for fi in matrix.files:
            console.print(f'\n[bold cyan]{Path(fi.file).name}[/bold cyan] (risk: {fi.risk_score:.2f})')

            if fi.functions:
                console.print(f'  Functions: {", ".join(fi.functions[:8])}')
            if fi.tables:
                console.print(f'  Tables: {", ".join(fi.tables)}')

            if fi.callers:
                console.print(f'  [yellow]Callers ({len(fi.callers)}):[/yellow]')
                for c in fi.callers[:10]:
                    console.print(f'    {c["file"]}:{c["line"]} -> {c["function"]}()')
                if len(fi.callers) > 10:
                    console.print(f'    [dim]... and {len(fi.callers) - 10} more[/dim]')

            if fi.business_rules:
                console.print(f'  [magenta]Business Rules:[/magenta]')
                for rule in fi.business_rules:
                    console.print(f'    {rule[:80]}')

        if save:
            text = format_plan_text(matrix)
            save.write_text(text, encoding='utf-8')
            console.print(f'\n[green]Saved to {save}[/green]')


# ── Register Command Groups ──────────────────────────────────────────────────
# This must come AFTER all @app.command() definitions above.
# It re-registers existing functions under grouped sub-apps:
#   lca analyze impact ...   (new)
#   lca impact ...           (old, hidden but still works)
try:
    from lca.commands import register_all
    register_all(app)
    
    # Hide old flat commands that now live in groups.
    # They still work if typed directly — just hidden from --help.
    # Keep: init, validate, baseline (top-level shortcuts)
    _commands_to_hide = {
        # Old flat name → now under group
        'index', 'update', 'watch',                    # → lca index
        'impact', 'search', 'crossref', 'modules',     # → lca analyze
        'sql-map', 'deep-analyze',                      # → lca analyze
        'health', 'report', 'smells',                   # → lca quality
        'n1', 'hardcoded', 'reaper', 'duplicates',      # → lca quality
        'todo-scan', 'guard',                            # → lca quality
        'migration-check', 'diff-health',                # → lca risk
        'gen-test', 'verify', 'test-coverage',           # → lca test
        'changeset', 'pr', 'commit-msg', 'release-notes',# → lca git
        'brain-build', 'brain-health',                   # → lca brain
        'flowmap', 'explain',                            # → lca brain
        'semantic', 'semantic-ingest', 'semantic-stats', # → lca rag
        'rag-sync', 'rag-status',                        # → lca rag
        'sdlc-review', 'sdlc-plan', 'context',           # → lca sdlc
        'serve', 'ui', 'client-profile',                 # → lca ops
        'client-diff', 'pre-release',                    # → lca ops
    }
    
    # Typer uses registered_commands list — mark each as hidden
    for reg_cmd in app.registered_commands:
        # Get the command name from the callback or the name override
        cmd_name = None
        if hasattr(reg_cmd, 'name') and reg_cmd.name:
            cmd_name = reg_cmd.name
        elif reg_cmd.callback:
            cmd_name = reg_cmd.callback.__name__.replace('_', '-')
        
        if cmd_name and cmd_name in _commands_to_hide:
            reg_cmd.hidden = True

except (ImportError, Exception) as e:
    import sys
    sys.stderr.write(f"Warning: Command groups not loaded: {e}\n")





def main():

    app()


if __name__ == "__main__":
    main()

