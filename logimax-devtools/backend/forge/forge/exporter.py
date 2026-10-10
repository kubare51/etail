"""
Exporter — Output training examples in various fine-tuning formats.

Supported formats:
  - ShareGPT (conversations with roles)
  - Alpaca (instruction/input/output triples)
  - DPO (chosen vs rejected pairs — requires manual curation)
  - Raw JSONL (full example data)
"""
from __future__ import annotations
import json
from pathlib import Path

from .models import TrainingExample, DatasetStats, Quality


def export_sharegpt(
    examples: list[TrainingExample],
    output_path: str,
    system_prompt: str | None = None
) -> int:
    """
    Export in ShareGPT format (used by most fine-tuning frameworks).

    Format:
    {"conversations": [
        {"role": "system", "content": "..."},
        {"role": "user", "content": "..."},
        {"role": "assistant", "content": "..."}
    ]}
    """
    count = 0
    with open(output_path, 'w', encoding='utf-8') as f:
        for ex in examples:
            if ex.quality == Quality.SKIP:
                continue

            conversations = []

            # System prompt (optional)
            if system_prompt:
                conversations.append({
                    "role": "system",
                    "content": system_prompt
                })

            # User message: instruction + optional context
            user_content = ex.instruction
            if ex.input_context:
                user_content += f"\n\n### Context:\n{ex.input_context}"

            conversations.append({
                "role": "user",
                "content": user_content
            })

            # Assistant response
            conversations.append({
                "role": "assistant",
                "content": ex.output
            })

            record = {
                "id": ex.id,
                "conversations": conversations,
                "category": ex.category,
                "module": ex.module,
                "tags": ex.tags,
            }

            f.write(json.dumps(record, ensure_ascii=False) + '\n')
            count += 1

    return count


def export_alpaca(
    examples: list[TrainingExample],
    output_path: str
) -> int:
    """
    Export in Alpaca format (instruction tuning).

    Format:
    {"instruction": "...", "input": "...", "output": "..."}
    """
    count = 0
    with open(output_path, 'w', encoding='utf-8') as f:
        for ex in examples:
            if ex.quality == Quality.SKIP:
                continue

            record = {
                "instruction": ex.instruction,
                "input": ex.input_context or "",
                "output": ex.output,
                "category": ex.category,
                "module": ex.module or "",
                "tags": ex.tags,
            }

            f.write(json.dumps(record, ensure_ascii=False) + '\n')
            count += 1

    return count


def export_raw(
    examples: list[TrainingExample],
    output_path: str
) -> int:
    """Export full example data as JSONL (for custom processing)."""
    count = 0
    with open(output_path, 'w', encoding='utf-8') as f:
        for ex in examples:
            f.write(ex.model_dump_json() + '\n')
            count += 1

    return count


def compute_stats(examples: list[TrainingExample]) -> DatasetStats:
    """Compute dataset statistics."""
    stats = DatasetStats()
    stats.total_examples = len(examples)

    conv_ids = set()
    total_instr_len = 0
    total_output_len = 0

    for ex in examples:
        conv_ids.add(ex.conversation_id)

        cat = ex.category
        stats.by_category[cat] = stats.by_category.get(cat, 0) + 1

        qual = ex.quality
        stats.by_quality[qual] = stats.by_quality.get(qual, 0) + 1

        outcome = ex.outcome
        stats.by_outcome[outcome] = stats.by_outcome.get(outcome, 0) + 1

        if ex.module:
            stats.by_module[ex.module] = stats.by_module.get(ex.module, 0) + 1

        total_instr_len += len(ex.instruction)
        total_output_len += len(ex.output)

    stats.total_conversations = len(conv_ids)

    if examples:
        stats.avg_instruction_length = total_instr_len / len(examples)
        stats.avg_output_length = total_output_len / len(examples)

    return stats


def print_stats(stats: DatasetStats) -> None:
    """Print dataset statistics to console."""
    from rich.console import Console
    from rich.table import Table

    console = Console()

    console.print(f"\n[bold]📊 Dataset Statistics[/bold]\n")
    console.print(f"  Conversations: {stats.total_conversations}")
    console.print(f"  Examples:      {stats.total_examples}")
    console.print(f"  Avg Instruction: {stats.avg_instruction_length:.0f} chars")
    console.print(f"  Avg Output:      {stats.avg_output_length:.0f} chars")

    # Category breakdown
    if stats.by_category:
        table = Table(title="By Category")
        table.add_column("Category", style="cyan")
        table.add_column("Count", justify="right")
        for cat, count in sorted(
            stats.by_category.items(), key=lambda x: -x[1]
        ):
            table.add_row(cat, str(count))
        console.print(table)

    # Quality breakdown
    if stats.by_quality:
        table = Table(title="By Quality")
        table.add_column("Quality", style="green")
        table.add_column("Count", justify="right")
        for qual, count in sorted(
            stats.by_quality.items(), key=lambda x: -x[1]
        ):
            table.add_row(qual, str(count))
        console.print(table)

    # Module breakdown
    if stats.by_module:
        table = Table(title="By Module")
        table.add_column("Module", style="yellow")
        table.add_column("Count", justify="right")
        for mod, count in sorted(
            stats.by_module.items(), key=lambda x: -x[1]
        )[:15]:
            table.add_row(mod, str(count))
        console.print(table)
