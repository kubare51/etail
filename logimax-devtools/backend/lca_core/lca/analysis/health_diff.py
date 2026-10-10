"""Health Diff — compare two health snapshots and show what changed.

Use cases:
  - CI/CD: Compare base branch health vs PR branch health
  - Local: Compare current health vs last saved baseline
  - Trend: Compare health at two points in time

Usage:
  lca diff-health before.json after.json
  lca diff-health --baseline .lca/history/latest.json --current health.json
"""

import json
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional


@dataclass
class SubScoreDiff:
    """Difference in a single health dimension."""
    name: str
    before: Optional[float]
    after: Optional[float]
    delta: Optional[float]
    before_findings: int
    after_findings: int
    findings_delta: int
    measured_before: bool
    measured_after: bool
    detail_before: str = ''
    detail_after: str = ''


@dataclass
class HealthDiff:
    """Overall health score diff between two snapshots."""
    before_score: float
    after_score: float
    score_delta: float
    before_grade: str
    after_grade: str
    grade_changed: bool
    before_confidence: float
    after_confidence: float
    sub_diffs: List[SubScoreDiff] = field(default_factory=list)
    improved: List[str] = field(default_factory=list)
    degraded: List[str] = field(default_factory=list)
    unchanged: List[str] = field(default_factory=list)
    summary: str = ''


def load_health_json(path: Path) -> dict:
    """Load a health JSON file."""
    try:
        return json.loads(path.read_text(encoding='utf-8'))
    except (json.JSONDecodeError, OSError) as e:
        raise ValueError(f'Failed to load health JSON from {path}: {e}')


def compare_health(before: dict, after: dict) -> HealthDiff:
    """Compare two health snapshots and compute the diff."""
    before_score = before.get('score', 0)
    after_score = after.get('score', 0)
    before_grade = before.get('grade', '?')
    after_grade = after.get('grade', '?')

    diff = HealthDiff(
        before_score=before_score,
        after_score=after_score,
        score_delta=round(after_score - before_score, 1),
        before_grade=before_grade,
        after_grade=after_grade,
        grade_changed=before_grade != after_grade,
        before_confidence=before.get('confidence', 0),
        after_confidence=after.get('confidence', 0),
    )

    # Build sub-score lookup by name
    before_subs = {s['name']: s for s in before.get('sub_scores', [])}
    after_subs = {s['name']: s for s in after.get('sub_scores', [])}

    all_names = list(dict.fromkeys(
        list(before_subs.keys()) + list(after_subs.keys())
    ))

    for name in all_names:
        b = before_subs.get(name, {})
        a = after_subs.get(name, {})

        b_score = b.get('score')
        a_score = a.get('score')
        b_measured = b.get('measured', False)
        a_measured = a.get('measured', False)
        b_findings = b.get('findings_count', 0)
        a_findings = a.get('findings_count', 0)

        # Compute delta only if both measured
        if b_measured and a_measured and b_score is not None and a_score is not None:
            delta = round(a_score - b_score, 1)
        else:
            delta = None

        sub_diff = SubScoreDiff(
            name=name,
            before=b_score,
            after=a_score,
            delta=delta,
            before_findings=b_findings,
            after_findings=a_findings,
            findings_delta=a_findings - b_findings,
            measured_before=b_measured,
            measured_after=a_measured,
            detail_before=b.get('detail', ''),
            detail_after=a.get('detail', ''),
        )
        diff.sub_diffs.append(sub_diff)

        # Classify
        if delta is not None:
            if delta > 0:
                diff.improved.append(name)
            elif delta < 0:
                diff.degraded.append(name)
            else:
                diff.unchanged.append(name)

    # Generate summary
    if diff.score_delta > 0:
        direction = 'improved'
        emoji = '[+]'
    elif diff.score_delta < 0:
        direction = 'degraded'
        emoji = '[-]'
    else:
        direction = 'unchanged'
        emoji = '[=]'

    grade_change = ''
    if diff.grade_changed:
        grade_change = f' (grade: {diff.before_grade} -> {diff.after_grade})'

    diff.summary = (
        f'{emoji} Health {direction} by {abs(diff.score_delta):.1f} points'
        f' ({diff.before_score:.0f} -> {diff.after_score:.0f}){grade_change}'
    )

    return diff


def generate_diff_markdown(diff: HealthDiff) -> str:
    """Generate a markdown report from the health diff."""
    lines = []
    lines.append('### 📊 Health Diff')
    lines.append('')
    lines.append(f'**{diff.summary}**')
    lines.append('')

    # Score table
    lines.append('| Metric | Before | After | Delta |')
    lines.append('|---|---|---|---|')

    delta_icon = '+' if diff.score_delta > 0 else '-' if diff.score_delta < 0 else '='
    lines.append(
        f'| **Overall Score** | {diff.before_score:.0f} | {diff.after_score:.0f} '
        f'| {delta_icon} {diff.score_delta:+.1f} |'
    )
    lines.append(
        f'| **Grade** | {diff.before_grade} | {diff.after_grade} '
        f'| {"Changed!" if diff.grade_changed else "--"} |'
    )
    lines.append(
        f'| **Confidence** | {diff.before_confidence:.0f}% | {diff.after_confidence:.0f}% | -- |'
    )
    lines.append('')

    # Sub-score diffs
    lines.append('| Dimension | Before | After | Findings Δ | Verdict |')
    lines.append('|---|---|---|---|---|')
    for s in diff.sub_diffs:
        b_str = f'{s.before:.0f}' if s.before is not None else 'N/A'
        a_str = f'{s.after:.0f}' if s.after is not None else 'N/A'

        if s.delta is not None and s.delta > 0:
            verdict = f'+{s.delta:.0f} UP'
        elif s.delta is not None and s.delta < 0:
            verdict = f'{s.delta:.0f} DOWN'
        elif s.delta is not None:
            verdict = '--'
        else:
            verdict = 'N/A'

        f_delta = ''
        if s.findings_delta != 0:
            f_delta = f'{s.findings_delta:+d}'

        lines.append(f'| {s.name} | {b_str} | {a_str} | {f_delta} | {verdict} |')
    lines.append('')

    # Summary lists
    if diff.improved:
        lines.append(f'**Improved:** {", ".join(diff.improved)}')
    if diff.degraded:
        lines.append(f'**Degraded:** {", ".join(diff.degraded)}')
    lines.append('')

    return '\n'.join(lines)
