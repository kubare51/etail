"""Changeset Manifest — structured commit/change tracking.

Creates a JSON changeset file for each logical change:
  - Changed files with diff stats
  - Impact analysis (risk score 0-100)
  - Affected modules
  - Commit message suggestion

Used by PR generator and release notes.

Ported from standalone changeset.py into LCA v2 package.
"""

from __future__ import annotations

import json
import subprocess
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path


@dataclass
class FileChange:
    """A single file change in a changeset."""
    path: str
    status: str          # A (added), M (modified), D (deleted), R (renamed)
    additions: int = 0
    deletions: int = 0
    module: str = ""


@dataclass
class Changeset:
    """A structured changeset manifest."""
    id: str                               # Short hash or UUID
    title: str = ""
    description: str = ""
    author: str = ""
    timestamp: str = field(default_factory=lambda: datetime.now().isoformat())
    files: list[FileChange] = field(default_factory=list)
    modules_affected: list[str] = field(default_factory=list)
    risk_score: int = 0                   # 0-100
    risk_level: str = "LOW"               # LOW, MEDIUM, HIGH, CRITICAL
    tags: list[str] = field(default_factory=list)

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "title": self.title,
            "description": self.description,
            "author": self.author,
            "timestamp": self.timestamp,
            "files": [
                {"path": f.path, "status": f.status,
                 "additions": f.additions, "deletions": f.deletions,
                 "module": f.module}
                for f in self.files
            ],
            "modules_affected": self.modules_affected,
            "risk_score": self.risk_score,
            "risk_level": self.risk_level,
            "tags": self.tags,
        }

    def to_commit_message(self) -> str:
        """Generate a structured commit message."""
        parts = []
        if self.tags:
            parts.append(f"[{', '.join(self.tags)}]")
        parts.append(self.title)
        msg = " ".join(parts) + "\n"

        if self.description:
            msg += f"\n{self.description}\n"

        if self.modules_affected:
            msg += f"\nModules: {', '.join(self.modules_affected)}"
        msg += f"\nRisk: {self.risk_level} ({self.risk_score}/100)"
        msg += f"\nFiles: {len(self.files)} changed"

        stats = {"A": 0, "M": 0, "D": 0}
        for f in self.files:
            stats[f.status] = stats.get(f.status, 0) + 1
        stat_parts = []
        if stats.get("A"): stat_parts.append(f"{stats['A']} added")
        if stats.get("M"): stat_parts.append(f"{stats['M']} modified")
        if stats.get("D"): stat_parts.append(f"{stats['D']} deleted")
        if stat_parts:
            msg += f" ({', '.join(stat_parts)})"

        return msg


def _detect_module(path: str) -> str:
    """Detect module from file path (delegates to centralized detector)."""
    from lca.analysis.modules import detect_module
    return detect_module(path)


def _compute_risk(files: list[FileChange]) -> tuple[int, str]:
    """Compute risk score from file changes."""
    score = 0

    for f in files:
        # High-risk paths
        if "config" in f.path.lower():
            score += 20
        elif "model" in f.path.lower():
            score += 10
        elif "controller" in f.path.lower():
            score += 8
        elif ".js" in f.path.lower():
            score += 5
        else:
            score += 3

        # Large changes are riskier
        total_lines = f.additions + f.deletions
        if total_lines > 200:
            score += 15
        elif total_lines > 50:
            score += 5

    # Multi-module changes are riskier
    modules = set(f.module for f in files if f.module)
    if len(modules) > 2:
        score += 20

    score = min(score, 100)

    if score >= 80:
        level = "CRITICAL"
    elif score >= 50:
        level = "HIGH"
    elif score >= 25:
        level = "MEDIUM"
    else:
        level = "LOW"

    return score, level


def create_from_git(
    project_root: Path,
    base_ref: str = "HEAD~1",
    title: str = "",
) -> Changeset:
    """Create a changeset from git diff.

    Uses three-dot diff (merge-base) when base_ref looks like a branch name,
    so the changeset only includes files changed on OUR branch, not files
    changed on the base branch by other people.
    """
    # Use three-dot (merge-base) for branch refs, two-dot for HEAD~N
    if base_ref.startswith('HEAD') or base_ref.startswith('~'):
        diff_ref = base_ref
    else:
        # Branch name: use merge-base so we only see OUR changes
        diff_ref = f"{base_ref}...HEAD"

    # Get diff stats
    try:
        result = subprocess.run(
            ["git", "diff", "--numstat", "--name-status", diff_ref],
            cwd=str(project_root),
            capture_output=True, text=True, timeout=15,
        )
        if result.returncode != 0:
            return Changeset(id="error", title="Git diff failed")
    except Exception as e:
        return Changeset(id="error", title=str(e))

    # Get short hash
    try:
        hash_result = subprocess.run(
            ["git", "rev-parse", "--short", "HEAD"],
            cwd=str(project_root),
            capture_output=True, text=True, timeout=5,
        )
        short_hash = hash_result.stdout.strip() if hash_result.returncode == 0 else "unknown"
    except Exception:
        short_hash = "unknown"

    # Get author
    try:
        author_result = subprocess.run(
            ["git", "log", "-1", "--format=%an"],
            cwd=str(project_root),
            capture_output=True, text=True, timeout=5,
        )
        author = author_result.stdout.strip() if author_result.returncode == 0 else ""
    except Exception:
        author = ""

    # Parse numstat for additions/deletions
    numstat_result = subprocess.run(
        ["git", "diff", "--numstat", diff_ref],
        cwd=str(project_root),
        capture_output=True, text=True, timeout=15,
    )

    file_stats = {}
    for line in numstat_result.stdout.strip().split("\n"):
        if not line.strip():
            continue
        parts = line.split("\t")
        if len(parts) >= 3:
            adds = int(parts[0]) if parts[0] != "-" else 0
            dels = int(parts[1]) if parts[1] != "-" else 0
            file_stats[parts[2]] = (adds, dels)

    # Parse name-status for status
    name_result = subprocess.run(
        ["git", "diff", "--name-status", diff_ref],
        cwd=str(project_root),
        capture_output=True, text=True, timeout=15,
    )

    files = []
    for line in name_result.stdout.strip().split("\n"):
        if not line.strip():
            continue
        parts = line.split("\t")
        if len(parts) >= 2:
            status = parts[0][0]  # First char: A, M, D, R
            path = parts[-1]     # Last element for renames
            adds, dels = file_stats.get(path, (0, 0))
            module = _detect_module(path)
            files.append(FileChange(
                path=path,
                status=status,
                additions=adds,
                deletions=dels,
                module=module,
            ))

    modules = sorted(set(f.module for f in files if f.module and f.module != "other"))
    risk_score, risk_level = _compute_risk(files)

    return Changeset(
        id=short_hash,
        title=title or f"Changes from {base_ref}",
        author=author,
        files=files,
        modules_affected=modules,
        risk_score=risk_score,
        risk_level=risk_level,
    )


def save_changeset(changeset: Changeset, output_dir: Path) -> Path:
    """Save changeset to JSON file."""
    output_dir.mkdir(parents=True, exist_ok=True)
    filename = f"{changeset.timestamp[:10]}_{changeset.id}.json"
    path = output_dir / filename
    path.write_text(
        json.dumps(changeset.to_dict(), indent=2),
        encoding='utf-8',
    )
    return path
