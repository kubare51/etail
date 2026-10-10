"""
Harvester — Parse conversation transcripts into training examples.

Reads transcript.jsonl files and extracts structured instruction/input/output
triples suitable for LLM fine-tuning.
"""
from __future__ import annotations
import json
import os
import re
from pathlib import Path
from typing import Generator

from .models import (
    TrainingExample, ConversationMeta, Category, Outcome, Quality
)


# Known tool types that modify files
WRITE_TOOLS = {
    'write_to_file', 'replace_file_content', 'multi_replace_file_content'
}
READ_TOOLS = {'view_file', 'grep_search', 'list_dir'}
COMMAND_TOOLS = {'run_command'}
ALL_TOOLS = WRITE_TOOLS | READ_TOOLS | COMMAND_TOOLS


def find_transcripts(brain_dir: str) -> list[Path]:
    """Find all transcript.jsonl files under the brain directory."""
    brain_path = Path(brain_dir)
    transcripts = []

    if not brain_path.exists():
        return transcripts

    # Pattern: brain/{conv-id}/.system_generated/logs/transcript.jsonl
    for conv_dir in brain_path.iterdir():
        if not conv_dir.is_dir():
            continue
        transcript = conv_dir / '.system_generated' / 'logs' / 'transcript.jsonl'
        if transcript.exists():
            transcripts.append(transcript)

    return sorted(transcripts, key=lambda p: p.stat().st_mtime, reverse=True)


def parse_transcript(transcript_path: Path) -> list[dict]:
    """Parse a transcript.jsonl file into a list of step dicts."""
    steps = []
    with open(transcript_path, 'r', encoding='utf-8', errors='replace') as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            try:
                step = json.loads(line)
                steps.append(step)
            except json.JSONDecodeError:
                # Some lines may have malformed JSON with Windows paths
                # Try fixing common escape issues
                try:
                    # Replace problematic backslash sequences that aren't
                    # valid JSON escapes (e.g. \U, \x, \a in file paths)
                    fixed = line.replace('\\U', '\\\\U').replace('\\x', '\\\\x')
                    fixed = fixed.replace('\\a', '\\\\a').replace('\\P', '\\\\P')
                    step = json.loads(fixed)
                    steps.append(step)
                except (json.JSONDecodeError, Exception):
                    continue
    return steps



def extract_conversation_meta(
    conv_id: str, steps: list[dict]
) -> ConversationMeta:
    """Extract metadata from a parsed conversation."""
    meta = ConversationMeta(conversation_id=conv_id)
    meta.total_steps = len(steps)

    files_read = set()
    files_modified = set()

    for step in steps:
        source = step.get('source', '')
        step_type = step.get('type', '')

        if source == 'USER_EXPLICIT' or step_type == 'USER_INPUT':
            meta.user_messages += 1
            # Use first user message as title hint
            if not meta.title and step.get('content'):
                meta.title = step['content'][:100]

        elif source == 'MODEL':
            meta.model_responses += 1

        # Count tool calls and track files
        tool_calls = step.get('tool_calls', [])
        if isinstance(tool_calls, list):
            for tc in tool_calls:
                meta.tool_calls += 1
                name = tc.get('name', '') if isinstance(tc, dict) else ''
                args = tc.get('args', {}) if isinstance(tc, dict) else {}

                if name == 'view_file':
                    path = args.get('AbsolutePath', '')
                    if path:
                        files_read.add(path)
                elif name in WRITE_TOOLS:
                    path = args.get('TargetFile', '')
                    if path:
                        files_modified.add(path)

    meta.files_read = sorted(files_read)
    meta.files_modified = sorted(files_modified)
    meta.primary_category = _guess_category(steps)
    meta.primary_module = _guess_module(steps)

    # Try to get created_at from first step
    if steps:
        meta.created_at = steps[0].get('timestamp', '')

    return meta


def harvest_examples(
    conv_id: str, steps: list[dict]
) -> list[TrainingExample]:
    """
    Extract training examples from a conversation.

    Strategy: Find user request → model response cycles.
    Each cycle becomes one training example.
    """
    examples = []
    example_idx = 0

    # Group steps into request-response cycles
    cycles = _segment_into_cycles(steps)

    for cycle in cycles:
        user_steps, model_steps = cycle
        if not user_steps or not model_steps:
            continue

        example = _build_example(
            conv_id, example_idx, user_steps, model_steps
        )
        if example and len(example.instruction) > 10 and len(example.output) > 10:
            examples.append(example)
            example_idx += 1

    return examples


def _segment_into_cycles(steps: list[dict]) -> list[tuple[list, list]]:
    """
    Segment a conversation into (user_request, model_response) cycles.

    A cycle starts with a user message and includes all model steps
    until the next user message.
    """
    cycles = []
    current_user = []
    current_model = []

    for step in steps:
        source = step.get('source', '')
        step_type = step.get('type', '')

        is_user = (
            source == 'USER_EXPLICIT' or
            step_type == 'USER_INPUT'
        )
        is_model = source == 'MODEL'
        is_system = source == 'SYSTEM'

        if is_user:
            # If we have a previous cycle, save it
            if current_user and current_model:
                cycles.append((current_user, current_model))
            current_user = [step]
            current_model = []
        elif is_model or is_system:
            current_model.append(step)

    # Don't forget the last cycle
    if current_user and current_model:
        cycles.append((current_user, current_model))

    return cycles


def _build_example(
    conv_id: str,
    idx: int,
    user_steps: list[dict],
    model_steps: list[dict]
) -> TrainingExample | None:
    """Build a single training example from a request-response cycle."""

    # Extract instruction from user message(s)
    instruction_parts = []
    for step in user_steps:
        content = step.get('content', '')
        if content:
            # Clean up system-injected metadata
            content = _clean_user_message(content)
            if content:
                instruction_parts.append(content)

    instruction = '\n'.join(instruction_parts).strip()
    if not instruction:
        return None

    # Extract input context (files viewed before action)
    input_context_parts = []
    files_modified = []
    tools_used = []
    output_parts = []

    for step in model_steps:
        content = step.get('content', '')
        tool_calls = step.get('tool_calls', [])

        # Model's text response
        if content and not tool_calls:
            output_parts.append(content)

        # Process tool calls
        if isinstance(tool_calls, list):
            for tc in tool_calls:
                if not isinstance(tc, dict):
                    continue
                name = tc.get('name', '')
                args = tc.get('args', {})
                result = tc.get('result', '')

                tools_used.append(name)

                if name == 'view_file':
                    # This is input context
                    path = args.get('AbsolutePath', '')
                    if path:
                        basename = os.path.basename(path)
                        # Truncate long file contents
                        file_content = result[:2000] if result else ''
                        if file_content:
                            input_context_parts.append(
                                f"// {basename}\n{file_content}"
                            )

                elif name in WRITE_TOOLS:
                    path = args.get('TargetFile', '')
                    if path:
                        files_modified.append(path)
                    # The replacement content IS the output
                    if name == 'replace_file_content':
                        target = args.get('TargetContent', '')
                        replacement = args.get('ReplacementContent', '')
                        if target and replacement:
                            output_parts.append(
                                f"```diff\n- {target}\n+ {replacement}\n```"
                            )
                    elif name == 'write_to_file':
                        code = args.get('CodeContent', '')
                        if code:
                            output_parts.append(f"```\n{code[:3000]}\n```")

                elif name == 'run_command':
                    cmd = args.get('CommandLine', '')
                    if cmd:
                        output_parts.append(f"$ {cmd}")

        # Also capture model's text alongside tool calls
        if content and tool_calls:
            output_parts.append(content)

    input_context = '\n\n'.join(input_context_parts[:5])  # Max 5 files
    output = '\n\n'.join(output_parts).strip()

    if not output:
        return None

    # Determine step range
    all_steps = user_steps + model_steps
    step_indices = [s.get('step_index', 0) for s in all_steps if 'step_index' in s]
    step_range = (
        min(step_indices) if step_indices else 0,
        max(step_indices) if step_indices else 0
    )

    # Build the example
    example = TrainingExample(
        id=f"{conv_id[:12]}-{idx:03d}",
        conversation_id=conv_id,
        instruction=instruction[:5000],     # Cap lengths
        input_context=input_context[:10000],
        output=output[:15000],
        files_modified=files_modified,
        tools_used=list(set(tools_used)),
        step_range=step_range,
        category=_classify_example(instruction, tools_used, files_modified),
        quality=_assess_quality(instruction, output, tools_used),
    )

    return example


def _clean_user_message(content: str) -> str:
    """Remove system-injected metadata from user messages."""
    # Remove ADDITIONAL_METADATA blocks
    content = re.sub(
        r'<ADDITIONAL_METADATA>.*?</ADDITIONAL_METADATA>',
        '', content, flags=re.DOTALL
    )
    # Remove USER_REQUEST tags
    content = re.sub(r'</?USER_REQUEST>', '', content)
    # Remove system messages
    content = re.sub(
        r'<SYSTEM_MESSAGE>.*?</SYSTEM_MESSAGE>',
        '', content, flags=re.DOTALL
    )
    # Remove EPHEMERAL_MESSAGE
    content = re.sub(
        r'<EPHEMERAL_MESSAGE>.*?</EPHEMERAL_MESSAGE>',
        '', content, flags=re.DOTALL
    )
    return content.strip()


def _classify_example(
    instruction: str,
    tools: list[str],
    files: list[str]
) -> Category:
    """Auto-classify a training example by category."""
    instr_lower = instruction.lower()

    # Bug fix indicators
    if any(kw in instr_lower for kw in [
        'fix', 'bug', 'error', 'issue', 'broken', 'not working',
        'fails', 'crash', 'wrong', 'incorrect'
    ]):
        return Category.BUG_FIX

    # Code review
    if any(kw in instr_lower for kw in [
        'review', 'audit', 'check', 'analyze', 'smell', 'quality'
    ]):
        return Category.CODE_REVIEW

    # Debugging
    if any(kw in instr_lower for kw in [
        'debug', 'why', 'trace', 'investigate', 'diagnose'
    ]):
        return Category.DEBUGGING

    # Architecture / design
    if any(kw in instr_lower for kw in [
        'architecture', 'design', 'structure', 'refactor', 'restructure',
        'pattern', 'approach'
    ]):
        return Category.ARCHITECTURE

    # Database
    if any(kw in instr_lower for kw in [
        'query', 'sql', 'database', 'table', 'column', 'migration',
        'schema', 'index'
    ]):
        return Category.DATABASE

    # Testing
    if any(kw in instr_lower for kw in [
        'test', 'phpunit', 'coverage', 'assert'
    ]):
        return Category.TESTING

    # UI fix
    if any(kw in instr_lower for kw in [
        'css', 'layout', 'ui', 'display', 'print', 'view', 'html',
        'alignment', 'responsive'
    ]):
        return Category.UI_FIX

    # Explanation / discussion
    if any(kw in instr_lower for kw in [
        'explain', 'how does', 'what is', 'why does', 'show me',
        'understand', 'tell me'
    ]):
        return Category.EXPLANATION

    # Documentation
    if any(kw in instr_lower for kw in [
        'document', 'readme', 'comment', 'brain', 'knowledge'
    ]):
        return Category.DOCUMENTATION

    # Configuration
    if any(kw in instr_lower for kw in [
        'config', 'setup', 'install', 'deploy', 'environment'
    ]):
        return Category.CONFIGURATION

    # Code generation (has writes but no fix keywords)
    if any(t in WRITE_TOOLS for t in tools):
        return Category.CODE_GENERATION

    # Discussion (no writes at all)
    if not any(t in WRITE_TOOLS for t in tools):
        return Category.DISCUSSION

    return Category.UNKNOWN


def _assess_quality(
    instruction: str, output: str, tools: list[str]
) -> Quality:
    """Assess the quality of a training example."""
    # Very short examples are low quality
    if len(instruction) < 20 or len(output) < 50:
        return Quality.LOW

    # System/meta conversations (about the AI itself)
    instr_lower = instruction.lower()
    if any(kw in instr_lower for kw in [
        'context length', 'session', 'checkpoint', 'continue',
        'token', 'conversation'
    ]):
        return Quality.SKIP

    # Has code modifications = likely useful
    has_writes = any(t in WRITE_TOOLS for t in tools)
    has_reads = any(t in READ_TOOLS for t in tools)

    if has_writes and has_reads:
        return Quality.HIGH  # Read code, then modified it
    elif has_writes:
        return Quality.MEDIUM
    elif len(output) > 500:
        return Quality.MEDIUM  # Detailed explanation
    else:
        return Quality.LOW


def _guess_category(steps: list[dict]) -> Category:
    """Guess the primary category from all steps."""
    all_text = ''
    for step in steps:
        content = step.get('content', '')
        if content and step.get('source') == 'USER_EXPLICIT':
            all_text += ' ' + content

    return _classify_example(all_text, [], [])


def _guess_module(steps: list[dict]) -> str | None:
    """Guess the primary module from file paths."""
    module_counts: dict[str, int] = {}

    # Common module patterns in CI3 projects
    module_pattern = re.compile(
        r'(?:controllers|models|views)[/\\](\w+?)(?:_(?:controller|model))?\.php',
        re.IGNORECASE
    )

    for step in steps:
        tool_calls = step.get('tool_calls', [])
        if not isinstance(tool_calls, list):
            continue
        for tc in tool_calls:
            if not isinstance(tc, dict):
                continue
            args = tc.get('args', {})
            for key in ('AbsolutePath', 'TargetFile', 'SearchPath'):
                path = args.get(key, '')
                if path:
                    match = module_pattern.search(path)
                    if match:
                        mod = match.group(1).lower()
                        module_counts[mod] = module_counts.get(mod, 0) + 1

    if module_counts:
        return max(module_counts, key=module_counts.get)
    return None
