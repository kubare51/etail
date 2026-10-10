#!/bin/bash
# =============================================================================
# git-commit.sh — Structured commit using changeset manifest
# =============================================================================
# Usage:
#   ./scripts/git-commit.sh              # Auto-reads .changes/current.json
#   ./scripts/git-commit.sh --no-verify  # Skip pre-commit hooks
#   ./scripts/git-commit.sh --dry-run    # Preview commit message only
#
# If no changeset exists, this script will guide you to create one interactively.
# =============================================================================

set -e

ROOT=$(git rev-parse --show-toplevel 2>/dev/null || echo "")
if [[ -z "$ROOT" ]]; then
    echo "X Not in a git repository."
    exit 1
fi

CHANGE_PY="$ROOT/logimax-devtools/backend/lca_core/changeset.py"
CURRENT_JSON="$ROOT/.changes/current.json"
NO_VERIFY=""
DRY_RUN=false

for arg in "$@"; do
    case "$arg" in
        --no-verify) NO_VERIFY="--no-verify" ;;
        --dry-run)   DRY_RUN=true ;;
    esac
done

if [[ ! -f "$CHANGE_PY" ]]; then
    echo "X changeset.py not found at: $CHANGE_PY"
    exit 1
fi

# ── Check for active changeset ────────────────────────────────────────────────
if [[ ! -f "$CURRENT_JSON" ]]; then
    echo ""
    echo "  No active changeset found."
    echo ""
    echo "  Create one now? You'll need to provide:"
    echo "    type    — fix | feat | refactor | style | perf | docs | chore | test"
    echo "    module  — e.g. billing, estimation, reports"
    echo "    summary — e.g. 'correct discount calculation for tagged items'"
    echo ""

    read -rp "  type   : " cs_type
    read -rp "  module : " cs_module
    read -rp "  summary: " cs_summary

    if [[ -z "$cs_type" || -z "$cs_module" || -z "$cs_summary" ]]; then
        echo "X Aborted — all fields are required."
        exit 1
    fi

    python "$CHANGE_PY" add --type "$cs_type" --module "$cs_module" --summary "$cs_summary"

    while true; do
        read -rp "  Add detail line? (or press ENTER to skip): " detail
        [[ -z "$detail" ]] && break
        python "$CHANGE_PY" detail "$detail"
    done

    read -rp "  Ticket ID? (e.g. EST-057 or ENTER to skip): " ticket
    if [[ -n "$ticket" ]]; then
        python "$CHANGE_PY" ticket "$ticket"
    fi

    echo ""
fi

# ── Generate commit message ────────────────────────────────────────────────────
COMMIT_MSG=$(python "$CHANGE_PY" commit-msg 2>/dev/null || echo "")
if [[ -z "$COMMIT_MSG" ]]; then
    echo "X No changeset data — cannot generate commit message."
    exit 1
fi

# ── Show preview ───────────────────────────────────────────────────────────────
echo ""
echo "═══════════════════════════════════════════"
echo "  Commit Message Preview"
echo "═══════════════════════════════════════════"
echo ""
echo "$COMMIT_MSG"
echo ""

if $DRY_RUN; then
    echo "[DryRun] No commit made."
    exit 0
fi

read -rp "Commit with this message? [y/N]: " confirm
if [[ ! "$confirm" =~ ^[yY]$ ]]; then
    echo "Aborted."
    exit 0
fi

# ── Stage if nothing staged ────────────────────────────────────────────────────
staged=$(git diff --cached --name-only 2>/dev/null || echo "")
if [[ -z "$staged" ]]; then
    echo ""
    read -rp "  Nothing staged. Stage all changes? (git add -A) [y/N]: " add_all
    if [[ "$add_all" =~ ^[yY]$ ]]; then
        git add -A
    else
        echo "  Stage your files manually then re-run."
        exit 0
    fi
fi

# ── Commit ─────────────────────────────────────────────────────────────────────
TMP_FILE=$(mktemp /tmp/lca_commit_msg.XXXXXX)
echo "$COMMIT_MSG" > "$TMP_FILE"

git commit -F "$TMP_FILE" $NO_VERIFY
result=$?

rm -f "$TMP_FILE"

if [[ $result -eq 0 ]]; then
    echo ""
    echo "  ✅ Committed!"
    echo "  Changeset is still active in .changes/current.json"
    echo "  It will be archived automatically when you run deploy-pr.sh"
    echo ""
else
    echo ""
    echo "❌ Commit failed (exit $result)."
    exit $result
fi
