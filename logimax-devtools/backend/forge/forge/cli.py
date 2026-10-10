"""
Forge CLI — Training Data Forge for AI model fine-tuning.

Extract, curate, and export training data from conversation transcripts.
"""
from __future__ import annotations
import json
import os
from pathlib import Path
from typing import Optional

import typer
from rich.console import Console
from rich.table import Table
from rich.progress import Progress, SpinnerColumn, TextColumn

from . import __version__
from .models import Quality

app = typer.Typer(
    name="forge",
    help="Training Data Forge — Extract AI training data from conversation transcripts",
    no_args_is_help=True,
)
console = Console()


def _get_brain_dir() -> str:
    """Get the default brain directory path."""
    # Antigravity IDE brain directory
    home = os.path.expanduser("~")
    candidates = [
        os.path.join(home, ".gemini", "antigravity-ide", "brain"),
        os.path.join(home, ".gemini", "antigravity", "brain"),
    ]
    for candidate in candidates:
        if os.path.isdir(candidate):
            return candidate
    return candidates[0]  # Default even if doesn't exist


def _version_callback(value: bool):
    if value:
        console.print(f"forge v{__version__}")
        raise typer.Exit()


@app.callback()
def main(
    version: bool = typer.Option(
        False, "--version", help="Show version", callback=_version_callback,
        is_eager=True
    ),
):
    """Training Data Forge — Extract AI training data from conversation transcripts."""
    pass


@app.command()
def harvest(
    brain_dir: Optional[str] = typer.Argument(
        None, help="Path to brain directory (auto-detected if not specified)"
    ),
    output: str = typer.Option(
        "training_data.jsonl", "-o", "--output",
        help="Output file for raw examples"
    ),
    min_quality: str = typer.Option(
        "low", "-q", "--min-quality",
        help="Minimum quality to include (high/medium/low/skip)"
    ),
    limit: Optional[int] = typer.Option(
        None, "-l", "--limit",
        help="Max conversations to process"
    ),
    scrub: bool = typer.Option(
        True, "--scrub/--no-scrub",
        help="Scrub PII from examples"
    ),
):
    """Scan all conversation transcripts and extract training examples."""
    from .harvester import find_transcripts, parse_transcript, harvest_examples, extract_conversation_meta
    from .scrubber import scrub_batch
    from .exporter import export_raw, compute_stats, print_stats

    if brain_dir is None:
        brain_dir = _get_brain_dir()

    console.print(f"\n[bold]⚡ Forge — Harvesting Training Data[/bold]")
    console.print(f"  Brain: {brain_dir}\n")

    transcripts = find_transcripts(brain_dir)
    if not transcripts:
        console.print(f"  [red]No transcripts found in {brain_dir}[/red]")
        raise typer.Exit(1)

    if limit:
        transcripts = transcripts[:limit]

    console.print(f"  Found {len(transcripts)} conversations\n")

    all_examples = []
    quality_threshold = {
        'high': [Quality.HIGH],
        'medium': [Quality.HIGH, Quality.MEDIUM],
        'low': [Quality.HIGH, Quality.MEDIUM, Quality.LOW],
        'skip': [Quality.HIGH, Quality.MEDIUM, Quality.LOW, Quality.SKIP],
    }.get(min_quality, [Quality.HIGH, Quality.MEDIUM, Quality.LOW])

    with Progress(
        SpinnerColumn(),
        TextColumn("[progress.description]{task.description}"),
        console=console,
    ) as progress:
        task = progress.add_task("Processing...", total=len(transcripts))

        for transcript_path in transcripts:
            conv_id = transcript_path.parent.parent.parent.name
            progress.update(task, description=f"Processing {conv_id[:12]}...")

            try:
                steps = parse_transcript(transcript_path)
                if not steps:
                    progress.advance(task)
                    continue

                examples = harvest_examples(conv_id, steps)

                # Filter by quality
                examples = [
                    ex for ex in examples
                    if ex.quality in quality_threshold
                ]

                if scrub:
                    examples = scrub_batch(examples)

                all_examples.extend(examples)
            except Exception as e:
                console.print(f"  [yellow]⚠ {conv_id[:12]}: {e}[/yellow]")

            progress.advance(task)

    # Export
    count = export_raw(all_examples, output)
    console.print(f"\n  ✅ Exported {count} examples to {output}")

    # Stats
    stats = compute_stats(all_examples)
    print_stats(stats)


@app.command()
def export(
    input_file: str = typer.Argument(
        "training_data.jsonl", help="Input raw examples file"
    ),
    format: str = typer.Option(
        "sharegpt", "-f", "--format",
        help="Output format: sharegpt, alpaca, raw"
    ),
    output: str = typer.Option(
        None, "-o", "--output",
        help="Output file (auto-named if not specified)"
    ),
    system_prompt: Optional[str] = typer.Option(
        None, "-s", "--system-prompt",
        help="System prompt to include (ShareGPT only)"
    ),
    min_quality: str = typer.Option(
        "medium", "-q", "--min-quality",
        help="Minimum quality to include"
    ),
    category: Optional[str] = typer.Option(
        None, "-c", "--category",
        help="Filter to specific category"
    ),
):
    """Export harvested examples in a fine-tuning format."""
    from .models import TrainingExample
    from .exporter import export_sharegpt, export_alpaca, export_raw

    if not os.path.exists(input_file):
        console.print(f"[red]Input file not found: {input_file}[/red]")
        raise typer.Exit(1)

    # Load examples
    examples = []
    with open(input_file, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line:
                data = json.loads(line)
                examples.append(TrainingExample(**data))

    # Filter by quality
    quality_threshold = {
        'high': ['high'],
        'medium': ['high', 'medium'],
        'low': ['high', 'medium', 'low'],
    }.get(min_quality, ['high', 'medium'])

    examples = [ex for ex in examples if ex.quality in quality_threshold]

    # Filter by category
    if category:
        examples = [ex for ex in examples if ex.category == category]

    # Auto-name output
    if output is None:
        output = f"dataset_{format}.jsonl"

    # Export
    if format == 'sharegpt':
        count = export_sharegpt(examples, output, system_prompt)
    elif format == 'alpaca':
        count = export_alpaca(examples, output)
    elif format == 'raw':
        count = export_raw(examples, output)
    else:
        console.print(f"[red]Unknown format: {format}[/red]")
        raise typer.Exit(1)

    console.print(f"\n  ✅ Exported {count} examples in {format} format to {output}")


@app.command()
def stats(
    input_file: str = typer.Argument(
        "training_data.jsonl", help="Input raw examples file"
    ),
):
    """Show dataset statistics."""
    from .models import TrainingExample
    from .exporter import compute_stats, print_stats

    if not os.path.exists(input_file):
        console.print(f"[red]Input file not found: {input_file}[/red]")
        raise typer.Exit(1)

    examples = []
    with open(input_file, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line:
                data = json.loads(line)
                examples.append(TrainingExample(**data))

    stats_data = compute_stats(examples)
    print_stats(stats_data)


@app.command()
def conversations(
    brain_dir: Optional[str] = typer.Argument(
        None, help="Path to brain directory"
    ),
    limit: int = typer.Option(20, "-l", "--limit", help="Max to show"),
):
    """List available conversations with metadata."""
    from .harvester import find_transcripts, parse_transcript, extract_conversation_meta

    if brain_dir is None:
        brain_dir = _get_brain_dir()

    transcripts = find_transcripts(brain_dir)
    if not transcripts:
        console.print(f"[red]No transcripts found in {brain_dir}[/red]")
        raise typer.Exit(1)

    table = Table(title=f"Conversations ({len(transcripts)} total)")
    table.add_column("ID", style="cyan", max_width=14)
    table.add_column("User Msgs", justify="right")
    table.add_column("Tool Calls", justify="right")
    table.add_column("Files Modified", justify="right")
    table.add_column("Category", style="yellow")
    table.add_column("Size", justify="right")
    table.add_column("Title", max_width=40)

    for transcript_path in transcripts[:limit]:
        conv_id = transcript_path.parent.parent.parent.name
        size_kb = transcript_path.stat().st_size / 1024

        try:
            steps = parse_transcript(transcript_path)
            meta = extract_conversation_meta(conv_id, steps)
            table.add_row(
                conv_id[:12] + "…",
                str(meta.user_messages),
                str(meta.tool_calls),
                str(len(meta.files_modified)),
                meta.primary_category or "—",
                f"{size_kb:.0f}KB",
                (meta.title or "—")[:40],
            )
        except Exception:
            table.add_row(conv_id[:12] + "…", "?", "?", "?", "?", f"{size_kb:.0f}KB", "—")

    console.print(table)


@app.command()
def inspect(
    input_file: str = typer.Argument(
        "training_data.jsonl", help="Input raw examples file"
    ),
    index: int = typer.Option(0, "-i", "--index", help="Example index to inspect"),
    category: Optional[str] = typer.Option(None, "-c", "--category"),
):
    """Inspect a specific training example in detail."""
    from .models import TrainingExample

    if not os.path.exists(input_file):
        console.print(f"[red]Input file not found: {input_file}[/red]")
        raise typer.Exit(1)

    examples = []
    with open(input_file, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line:
                data = json.loads(line)
                ex = TrainingExample(**data)
                if category and ex.category != category:
                    continue
                examples.append(ex)

    if index >= len(examples):
        console.print(f"[red]Index {index} out of range (0-{len(examples)-1})[/red]")
        raise typer.Exit(1)

    ex = examples[index]

    console.print(f"\n[bold]📋 Example: {ex.id}[/bold]")
    console.print(f"  Category:  {ex.category}")
    console.print(f"  Quality:   {ex.quality}")
    console.print(f"  Module:    {ex.module or '—'}")
    console.print(f"  Outcome:   {ex.outcome}")
    console.print(f"  Conv:      {ex.conversation_id[:12]}…")
    console.print(f"  Steps:     {ex.step_range[0]}–{ex.step_range[1]}")
    console.print(f"  Tools:     {', '.join(ex.tools_used) or '—'}")
    console.print(f"  Files:     {', '.join(os.path.basename(f) for f in ex.files_modified) or '—'}")
    console.print(f"  Tags:      {', '.join(ex.tags) or '—'}")

    console.print(f"\n[bold cyan]── Instruction ──[/bold cyan]")
    console.print(ex.instruction[:1000])

    if ex.input_context:
        console.print(f"\n[bold yellow]── Input Context ──[/bold yellow]")
        console.print(ex.input_context[:1000])

    console.print(f"\n[bold green]── Output ──[/bold green]")
    console.print(ex.output[:2000])


if __name__ == "__main__":
    app()
