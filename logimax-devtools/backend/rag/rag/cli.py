"""
RAG CLI — Ingest codebases, build vector stores, and query them.

Commands:
    rag ingest   — Chunk a codebase and store in ChromaDB
    rag query    — Search for relevant code chunks
    rag export   — Export chunks to JSONL (for external pipelines)
    rag stats    — Show collection statistics
    rag rebuild  — Delete and rebuild the vector store
"""
from __future__ import annotations
import json
import os
import sys
from pathlib import Path
from typing import Optional

import typer
from rich.console import Console
from rich.table import Table
from rich.panel import Panel
from rich.progress import Progress, SpinnerColumn, TextColumn, BarColumn

from .chunker import ingest_directory
from .models import CodeChunk, IngestStats

app = typer.Typer(
    name="rag",
    help="RAG pipeline for CodeIgniter 3 ERP codebases",
    no_args_is_help=True,
)
console = Console()

# Default persist directory
DEFAULT_STORE = os.path.join(os.path.expanduser("~"), ".logimax", "rag_store")


@app.command()
def ingest(
    source: str = typer.Argument(..., help="Source directory to ingest (e.g., c:\\xampp\\htdocs\\etail_v3\\admin)"),
    store_dir: str = typer.Option(DEFAULT_STORE, "--store", "-s", help="ChromaDB persist directory"),
    collection: str = typer.Option("codebase", "--collection", "-c", help="Collection name"),
    output: Optional[str] = typer.Option(None, "--output", "-o", help="Also export chunks to JSONL file"),
    include_docs: bool = typer.Option(True, "--docs/--no-docs", help="Include .md documentation files"),
    include_brains: bool = typer.Option(True, "--brains/--no-brains", help="Include knowledge_brain/ files"),
    no_embed: bool = typer.Option(False, "--no-embed", help="Only export JSONL, skip ChromaDB embedding"),
):
    """Chunk a codebase and store in ChromaDB for vector search."""
    source_path = Path(source)
    if not source_path.exists():
        console.print(f"[red]Error: Source directory not found: {source}[/red]")
        raise typer.Exit(1)

    console.print(f"\n[bold cyan]⚡ RAG — Ingesting Codebase[/bold cyan]")
    console.print(f"  Source: {source}")
    if not no_embed:
        console.print(f"  Store:  {store_dir}")
        console.print(f"  Collection: {collection}")
    console.print()

    # Phase 1: Chunk
    stats = IngestStats()
    all_chunks: list[CodeChunk] = []

    with Progress(
        SpinnerColumn(),
        TextColumn("[progress.description]{task.description}"),
        BarColumn(),
        TextColumn("{task.completed} chunks"),
        console=console,
    ) as progress:
        task = progress.add_task("Chunking files...", total=None)

        for chunk in ingest_directory(source, include_docs, include_brains):
            all_chunks.append(chunk)
            stats.total_chunks += 1

            # Track by type
            ct = chunk.chunk_type.value
            stats.by_type[ct] = stats.by_type.get(ct, 0) + 1

            # Track by module
            stats.by_module[chunk.module] = stats.by_module.get(chunk.module, 0) + 1

            progress.update(task, completed=stats.total_chunks)

    # Count unique files
    stats.total_files = len(set(c.file_path for c in all_chunks))

    console.print(f"\n  [green]✅ Chunked {stats.total_files} files → {stats.total_chunks} chunks[/green]\n")

    # Phase 2: Export to JSONL if requested
    if output:
        from .store import export_chunks_jsonl
        count = export_chunks_jsonl(all_chunks, output)
        console.print(f"  [green]📄 Exported {count} chunks to {output}[/green]")

    # Phase 3: Store in ChromaDB
    if not no_embed:
        try:
            from .store import store_chunks, get_collection_stats
            console.print("  Embedding and storing in ChromaDB...")
            stored = store_chunks(all_chunks, store_dir, collection)
            console.print(f"  [green]🧠 Stored {stored} chunks in ChromaDB[/green]")
        except ImportError as e:
            console.print(f"\n  [yellow]⚠️  ChromaDB not installed. Run: pip install chromadb[/yellow]")
            console.print(f"  [dim]Error: {e}[/dim]")
            if not output:
                console.print("  [yellow]Tip: Use --output chunks.jsonl --no-embed to export without ChromaDB[/yellow]")

    # Print stats
    _print_stats(stats)


@app.command()
def query(
    question: str = typer.Argument(..., help="Natural language query"),
    store_dir: str = typer.Option(DEFAULT_STORE, "--store", "-s", help="ChromaDB persist directory"),
    collection: str = typer.Option("codebase", "--collection", "-c", help="Collection name"),
    n: int = typer.Option(5, "--top", "-n", help="Number of results"),
    module: Optional[str] = typer.Option(None, "--module", "-m", help="Filter by module"),
    chunk_type: Optional[str] = typer.Option(None, "--type", "-t", help="Filter by chunk type"),
    show_code: bool = typer.Option(False, "--code", help="Show full code content"),
):
    """Search the vector store for relevant code chunks."""
    from .store import query_chunks

    where_filter = {}
    if module:
        where_filter["module"] = module
    if chunk_type:
        where_filter["chunk_type"] = chunk_type

    results = query_chunks(
        question,
        store_dir,
        collection,
        n_results=n,
        where_filter=where_filter if where_filter else None,
    )

    if not results:
        console.print("[yellow]No results found.[/yellow]")
        raise typer.Exit(0)

    console.print(f"\n[bold cyan]🔍 Top {len(results)} results for:[/bold cyan] \"{question}\"\n")

    for i, r in enumerate(results, 1):
        meta = r['metadata']
        distance = r['distance']
        relevance = max(0, (1 - distance) * 100)

        # Build header
        title = meta.get('summary', r['id'])
        file_path = meta.get('file_path', '?')
        chunk_t = meta.get('chunk_type', '?')
        mod = meta.get('module', '?')
        lines = f"L{meta.get('start_line', '?')}-L{meta.get('end_line', '?')}"

        header = f"[bold]#{i}[/bold] {title}"
        sub = f"  📁 {file_path}:{lines} | 📦 {mod} | 🏷️ {chunk_t} | 🎯 {relevance:.0f}%"

        console.print(header)
        console.print(f"[dim]{sub}[/dim]")

        if meta.get('tables'):
            console.print(f"  [cyan]Tables:[/cyan] {meta['tables']}")
        if meta.get('models'):
            console.print(f"  [cyan]Models:[/cyan] {meta['models']}")

        if show_code:
            # Show first 20 lines of code
            code_lines = r['document'].split('\n')
            code_preview = '\n'.join(code_lines[:30])
            console.print(Panel(code_preview, title="Code", border_style="dim"))

        console.print()


@app.command()
def stats(
    store_dir: str = typer.Option(DEFAULT_STORE, "--store", "-s", help="ChromaDB persist directory"),
    collection: str = typer.Option("codebase", "--collection", "-c", help="Collection name"),
):
    """Show collection statistics."""
    from .store import get_collection_stats

    info = get_collection_stats(store_dir, collection)

    if "error" in info:
        console.print(f"[red]Error: {info['error']}[/red]")
        raise typer.Exit(1)

    console.print(f"\n[bold cyan]📊 RAG Store Statistics[/bold cyan]")
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


@app.command()
def rebuild(
    source: str = typer.Argument(..., help="Source directory to re-ingest"),
    store_dir: str = typer.Option(DEFAULT_STORE, "--store", "-s", help="ChromaDB persist directory"),
    collection: str = typer.Option("codebase", "--collection", "-c", help="Collection name"),
):
    """Delete and rebuild the vector store from scratch."""
    from .store import delete_collection

    console.print(f"\n[yellow]⚠️  Deleting collection '{collection}'...[/yellow]")
    delete_collection(store_dir, collection)
    console.print("[green]  Deleted.[/green]\n")

    # Re-run ingest
    ingest(
        source=source,
        store_dir=store_dir,
        collection=collection,
        output=None,
        include_docs=True,
        include_brains=True,
        no_embed=False,
    )


@app.command(name="export")
def export_cmd(
    source: str = typer.Argument(..., help="Source directory to chunk"),
    output: str = typer.Option("chunks.jsonl", "--output", "-o", help="Output JSONL file"),
    include_docs: bool = typer.Option(True, "--docs/--no-docs"),
    include_brains: bool = typer.Option(True, "--brains/--no-brains"),
):
    """Export chunks to JSONL without embedding (for external pipelines)."""
    ingest(
        source=source,
        store_dir=DEFAULT_STORE,
        collection="codebase",
        output=output,
        no_embed=True,
        include_docs=include_docs,
        include_brains=include_brains,
    )


def _print_stats(stats: IngestStats):
    """Print ingest statistics."""
    # By type
    if stats.by_type:
        table = Table(title="By Chunk Type")
        table.add_column("Type", style="cyan")
        table.add_column("Count", justify="right")
        for t, c in sorted(stats.by_type.items(), key=lambda x: -x[1]):
            table.add_row(t, str(c))
        console.print(table)

    # By module
    if stats.by_module:
        table = Table(title="By Module")
        table.add_column("Module", style="green")
        table.add_column("Count", justify="right")
        for m, c in sorted(stats.by_module.items(), key=lambda x: -x[1]):
            table.add_row(m, str(c))
        console.print(table)

    console.print()


if __name__ == "__main__":
    app()
