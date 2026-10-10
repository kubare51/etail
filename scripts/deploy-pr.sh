#!/bin/bash
# =============================================================================
# deploy-pr.sh — Create PRs and auto-merge to all target branches
# =============================================================================
# Usage:
#   ./scripts/deploy-pr.sh                       # Auto-reads changeset
#   ./scripts/deploy-pr.sh "PR title"            # Manual title override
#   ./scripts/deploy-pr.sh "PR title" "PR body"  # Full manual override
#
# Changeset auto-generation:
#   If .changes/current.json exists, Title and Body are auto-populated from
#   changeset.py + pr_generator.py. Changeset is archived on success.
# =============================================================================

set -e

ROOT=$(git rev-parse --show-toplevel 2>/dev/null || echo "")
if [[ -z "$ROOT" ]]; then
    echo "❌ Not in a git repository."
    exit 1
fi

CHANGE_PY="$ROOT/logimax-devtools/backend/lca_core/changeset.py"
PR_GEN_PY="$ROOT/logimax-devtools/backend/lca_core/pr_generator.py"
CURRENT_JSON="$ROOT/.changes/current.json"

TARGET_BRANCHES=("Retail_1.1.1.0001" "QA" "support")
TITLE="${1:-}"
BODY="${2:-}"

# ── Detect current branch ─────────────────────────────────────────────────────
CURRENT_BRANCH=$(git branch --show-current)
if [[ -z "$CURRENT_BRANCH" ]]; then
    echo "❌ Not on any branch (detached HEAD?)"
    exit 1
fi

# ── Auto-generate Title / Body from changeset ─────────────────────────────────
if [[ -f "$CHANGE_PY" && -f "$CURRENT_JSON" ]]; then
    if [[ -z "$TITLE" ]]; then
        TITLE=$(python "$CHANGE_PY" commit-msg 2>/dev/null | head -1 || echo "")
    fi
    if [[ -z "$BODY" && -f "$PR_GEN_PY" ]]; then
        BODY=$(python "$PR_GEN_PY" --project-root "$ROOT" 2>/dev/null || echo "")
    fi
fi

# Fallbacks if still empty
if [[ -z "$TITLE" ]]; then
    read -rp "No changeset found and no title given. Enter PR title: " TITLE
fi
if [[ -z "$BODY" ]]; then
    BODY="$TITLE"
fi

echo "═══════════════════════════════════════════"
echo "  Deploy: $CURRENT_BRANCH"
echo "  Title:  $TITLE"
echo "═══════════════════════════════════════════"
echo ""

# ── Push first ────────────────────────────────────────────────────────────────
echo "⬆️  Pushing $CURRENT_BRANCH..."
git push origin "$CURRENT_BRANCH" 2>/dev/null || true
echo ""

declare -A PR_NUMBERS
CREATED=()
MERGED=()
CONFLICTED=()
FAILED=()

# ── Create PRs ────────────────────────────────────────────────────────────────
for BASE in "${TARGET_BRANCHES[@]}"; do
    echo "📋 Creating PR: $CURRENT_BRANCH → $BASE"

    PR_URL=$(gh pr create \
        --base "$BASE" \
        --head "$CURRENT_BRANCH" \
        --title "$TITLE" \
        --body "$BODY" 2>&1) || true

    if echo "$PR_URL" | grep -q "already exists"; then
        echo "   ⚠️  PR already exists for $BASE, finding it..."
        PR_NUM=$(gh pr list --base "$BASE" --head "$CURRENT_BRANCH" --json number -q '.[0].number' 2>/dev/null || echo "")
        if [[ -n "$PR_NUM" ]]; then
            PR_NUMBERS[$BASE]=$PR_NUM
            CREATED+=("$BASE:#$PR_NUM")
            echo "   Found: #$PR_NUM"
        else
            echo "   ❌ Could not find existing PR"
            FAILED+=("$BASE:create-failed")
        fi
        continue
    fi

    PR_NUM=$(echo "$PR_URL" | grep -oP '\d+$' || echo "")
    if [[ -n "$PR_NUM" ]]; then
        PR_NUMBERS[$BASE]=$PR_NUM
        CREATED+=("$BASE:#$PR_NUM")
        echo "   ✅ Created: #$PR_NUM"
    else
        echo "   ❌ Failed to create PR"
        echo "   $PR_URL"
        FAILED+=("$BASE:create-failed")
    fi
done

echo ""
echo "───────────────────────────────────────────"
echo "  Checking mergeability..."
echo "───────────────────────────────────────────"
echo ""

sleep 3

# ── Check & Merge ─────────────────────────────────────────────────────────────
for BASE in "${TARGET_BRANCHES[@]}"; do
    PR_NUM=${PR_NUMBERS[$BASE]:-}
    if [[ -z "$PR_NUM" ]]; then
        continue
    fi

    echo "🔍 Checking #$PR_NUM ($BASE)..."

    RETRIES=0
    while [[ $RETRIES -lt 3 ]]; do
        MERGE_INFO=$(gh pr view "$PR_NUM" --json mergeable,mergeStateStatus 2>/dev/null || echo '{}')
        MERGEABLE=$(echo "$MERGE_INFO" | grep -oP '"mergeable":"[^"]*"' | cut -d'"' -f4)
        STATE=$(echo "$MERGE_INFO" | grep -oP '"mergeStateStatus":"[^"]*"' | cut -d'"' -f4)

        if [[ "$MERGEABLE" != "UNKNOWN" ]]; then
            break
        fi

        RETRIES=$((RETRIES + 1))
        echo "   ⏳ Merge status pending, retry $RETRIES/3..."
        sleep 3
    done

    if [[ "$MERGEABLE" == "MERGEABLE" && "$STATE" == "CLEAN" ]]; then
        echo "   ✅ Clean — merging..."
        if gh pr merge "$PR_NUM" --merge --admin 2>/dev/null; then
            MERGED+=("$BASE:#$PR_NUM")
            echo "   ✅ Merged!"
        else
            echo "   ❌ Merge command failed"
            FAILED+=("$BASE:#$PR_NUM:merge-cmd-failed")
        fi
    elif [[ "$MERGEABLE" == "CONFLICTING" ]]; then
        echo "   ⚠️  CONFLICT — skipped (resolve manually)"
        CONFLICTED+=("$BASE:#$PR_NUM")
    else
        echo "   ⚠️  Status: mergeable=$MERGEABLE state=$STATE — skipped"
        FAILED+=("$BASE:#$PR_NUM:status-$MERGEABLE-$STATE")
    fi
done

# ── Archive changeset on full success ─────────────────────────────────────────
if [[ ${#CONFLICTED[@]} -eq 0 && ${#FAILED[@]} -eq 0 && ${#MERGED[@]} -gt 0 ]]; then
    if [[ -f "$CHANGE_PY" && -f "$CURRENT_JSON" ]]; then
        echo ""
        echo "📁 Archiving changeset..."
        python "$CHANGE_PY" archive 2>/dev/null || true
        echo "   Changeset archived to .changes/history/"
    fi
fi

# ── Summary ───────────────────────────────────────────────────────────────────
echo ""
echo "═══════════════════════════════════════════"
echo "  DEPLOY SUMMARY"
echo "═══════════════════════════════════════════"
echo ""

if [[ ${#MERGED[@]} -gt 0 ]]; then
    echo "  ✅ MERGED (${#MERGED[@]}):"
    for item in "${MERGED[@]}"; do echo "     $item"; done
    echo ""
fi

if [[ ${#CONFLICTED[@]} -gt 0 ]]; then
    echo "  ⚠️  CONFLICTS (${#CONFLICTED[@]}) — resolve manually:"
    for item in "${CONFLICTED[@]}"; do echo "     $item"; done
    echo ""
fi

if [[ ${#FAILED[@]} -gt 0 ]]; then
    echo "  ❌ FAILED (${#FAILED[@]}):"
    for item in "${FAILED[@]}"; do echo "     $item"; done
    echo ""
fi

if [[ ${#CONFLICTED[@]} -eq 0 && ${#FAILED[@]} -eq 0 ]]; then
    echo "  🎉 All branches deployed successfully!"
fi
echo ""
