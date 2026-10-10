"""PR Body Generator -- auto-generates pull request descriptions.

Creates structured PR body with:
  - Summary of changes
  - Files changed with stats
  - Risk assessment
  - Review checklist
  - Testing notes

Ported from standalone pr_generator.py into LCA v2 package.
"""

from __future__ import annotations

from lca.workflow.changeset import Changeset


def generate_pr_body(
    changeset: Changeset,
    template: str = "default",
    key_files: list[str] | None = None,
) -> str:
    """Generate a structured PR description.

    Args:
        changeset: Changeset manifest
        template: "default" or "minimal"
        key_files: Optional list of file paths to highlight as "Key Changes".
                   When provided, only these files appear in the main body;
                   the full file list goes into a collapsible <details> block.

    Returns:
        Markdown PR body
    """
    if template == "minimal":
        return _minimal_pr(changeset)
    return _default_pr(changeset, key_files)


def _default_pr(cs: Changeset, key_files: list[str] | None = None) -> str:
    """Full PR template with optional key-files focus."""
    # File stats
    total_adds = sum(f.additions for f in cs.files)
    total_dels = sum(f.deletions for f in cs.files)
    added = [f for f in cs.files if f.status == "A"]
    modified = [f for f in cs.files if f.status == "M"]
    deleted = [f for f in cs.files if f.status == "D"]

    # Risk badge
    risk_emoji = {"LOW": "[OK]", "MEDIUM": "[!!]", "HIGH": "[!!!]", "CRITICAL": "[XXX]"}
    risk_badge = risk_emoji.get(cs.risk_level, "[?]")

    lines = [
        f"## {cs.title}",
        "",
        f"{cs.description}" if cs.description else "",
        "",
        "### Summary",
        "",
        f"| Metric | Value |",
        f"|---|---|",
        f"| **Risk** | {risk_badge} {cs.risk_level} ({cs.risk_score}/100) |",
        f"| **Files Changed** | {len(cs.files)} |",
        f"| **Lines** | +{total_adds} / -{total_dels} |",
        f"| **Modules** | {', '.join(cs.modules_affected) or 'N/A'} |",
        "",
    ]

    # --- Key Changes (focused) or full file list ---
    if key_files:
        # Normalize paths for matching (forward slashes, no leading ./)
        key_set = {p.replace('\\', '/').lstrip('./') for p in key_files}

        # Split files into key vs rest
        key_modified = [f for f in modified if f.path.replace('\\', '/') in key_set]
        key_added = [f for f in added if f.path.replace('\\', '/') in key_set]
        key_deleted = [f for f in deleted if f.path.replace('\\', '/') in key_set]

        rest_modified = [f for f in modified if f.path.replace('\\', '/') not in key_set]
        rest_added = [f for f in added if f.path.replace('\\', '/') not in key_set]
        rest_deleted = [f for f in deleted if f.path.replace('\\', '/') not in key_set]

        # Key Changes section (always visible)
        lines.append("### Key Changes")
        lines.append("")
        if key_modified:
            lines.append("| File | +/- | Module |")
            lines.append("|---|---|---|")
            for f in key_modified:
                lines.append(f"| `{f.path}` | +{f.additions}/-{f.deletions} | {f.module} |")
            lines.append("")
        if key_added:
            for f in key_added:
                lines.append(f"- **NEW** `{f.path}` (+{f.additions} lines)")
            lines.append("")
        if key_deleted:
            for f in key_deleted:
                lines.append(f"- **DEL** ~~`{f.path}`~~")
            lines.append("")

        # Full file list in collapsible block
        rest_count = len(rest_modified) + len(rest_added) + len(rest_deleted)
        if rest_count > 0:
            lines.append(f"<details><summary>All other files ({rest_count})</summary>")
            lines.append("")
            if rest_modified:
                lines.append("| File | +/- | Module |")
                lines.append("|---|---|---|")
                for f in rest_modified:
                    lines.append(f"| `{f.path}` | +{f.additions}/-{f.deletions} | {f.module} |")
                lines.append("")
            if rest_added:
                for f in rest_added:
                    lines.append(f"- `{f.path}` (+{f.additions} lines)")
                lines.append("")
            if rest_deleted:
                for f in rest_deleted:
                    lines.append(f"- ~~`{f.path}`~~")
                lines.append("")
            lines.append("</details>")
            lines.append("")
    else:
        # No key files — show everything (original behavior)
        if modified:
            lines.append("### Modified Files")
            lines.append("")
            lines.append("| File | +/- | Module |")
            lines.append("|---|---|---|")
            for f in modified:
                lines.append(f"| `{f.path}` | +{f.additions}/-{f.deletions} | {f.module} |")
            lines.append("")

        if added:
            lines.append("### New Files")
            lines.append("")
            for f in added:
                lines.append(f"- `{f.path}` (+{f.additions} lines)")
            lines.append("")

        if deleted:
            lines.append("### Deleted Files")
            lines.append("")
            for f in deleted:
                lines.append(f"- ~~`{f.path}`~~")
            lines.append("")

    # Review checklist
    lines.extend([
        "### Review Checklist",
        "",
        "- [ ] Code follows CI3 conventions",
        "- [ ] No raw SQL without bindings",
        "- [ ] Financial calculations use parseFloat + toFixed(2)",
        "- [ ] AJAX calls have error handlers",
        "- [ ] No hardcoded URLs or credentials",
    ])

    if cs.risk_level in ("HIGH", "CRITICAL"):
        lines.extend([
            "- [ ] Cross-module impact verified",
            "- [ ] Rollback plan documented",
        ])

    lines.extend([
        "",
        "### Testing",
        "",
        "- [ ] Manual testing completed",
        "- [ ] Browser console checked for errors",
    ])

    if cs.modules_affected:
        lines.append(f"- [ ] Modules tested: {', '.join(cs.modules_affected)}")

    lines.extend([
        "",
        "---",
        f"*Generated by LCA v2 | Risk: {cs.risk_score}/100*",
    ])

    return "\n".join(lines)


def _minimal_pr(cs: Changeset) -> str:
    """Minimal PR template."""
    total_adds = sum(f.additions for f in cs.files)
    total_dels = sum(f.deletions for f in cs.files)

    lines = [
        f"## {cs.title}",
        "",
        f"**{len(cs.files)} files** | +{total_adds}/-{total_dels} | "
        f"Risk: {cs.risk_level} ({cs.risk_score}/100)",
        "",
    ]

    if cs.modules_affected:
        lines.append(f"Modules: {', '.join(cs.modules_affected)}")
        lines.append("")

    for f in cs.files:
        status_icon = {"A": "+", "M": "~", "D": "-"}.get(f.status, "?")
        lines.append(f"- [{status_icon}] `{f.path}`")

    return "\n".join(lines)


def generate_release_notes(
    changesets: list[Changeset],
    version: str = "",
) -> str:
    """Generate release notes from multiple changesets.

    Args:
        changesets: List of changesets in this release
        version: Version string (e.g., "1.2.0")

    Returns:
        Markdown release notes
    """
    lines = [
        f"# Release Notes{f' - {version}' if version else ''}",
        "",
    ]

    # Group by module
    by_module: dict[str, list[Changeset]] = {}
    for cs in changesets:
        for mod in cs.modules_affected or ["other"]:
            by_module.setdefault(mod, []).append(cs)

    # Stats
    total_files = sum(len(cs.files) for cs in changesets)
    total_adds = sum(f.additions for cs in changesets for f in cs.files)
    total_dels = sum(f.deletions for cs in changesets for f in cs.files)

    lines.extend([
        "## Overview",
        "",
        f"| Metric | Value |",
        f"|---|---|",
        f"| **Changesets** | {len(changesets)} |",
        f"| **Files Changed** | {total_files} |",
        f"| **Lines** | +{total_adds} / -{total_dels} |",
        f"| **Modules** | {len(by_module)} |",
        "",
    ])

    # Per-module breakdown
    for mod, mod_changesets in sorted(by_module.items()):
        lines.append(f"### {mod.title()}")
        lines.append("")
        for cs in mod_changesets:
            lines.append(f"- {cs.title} ({cs.id})")
        lines.append("")

    lines.extend([
        "---",
        "*Generated by LCA v2*",
    ])

    return "\n".join(lines)
