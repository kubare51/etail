#!/bin/bash
# Install LCA pre-commit hooks

PROJECT_ROOT=$(git rev-parse --show-toplevel)
HOOK_DIR="$PROJECT_ROOT/.git/hooks"
HOOK_FILE="$HOOK_DIR/pre-commit"

if [ ! -d "$HOOK_DIR" ]; then
    echo "Error: Not a git repository (could not find .git/hooks)"
    exit 1
fi

cat > "$HOOK_FILE" << 'EOF'
#!/bin/bash
# LCA v2 Pre-commit Hook

echo "[LCA] Running pre-commit checks..."

# Get list of staged PHP files
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)

if [ -z "$STAGED_FILES" ]; then
    exit 0
fi

# Run LCA scanners on staged files
PROJECT_ROOT=$(git rev-parse --show-toplevel)
LCA_DIR="$PROJECT_ROOT/logimax-devtools/backend/lca_core"

if [ ! -d "$LCA_DIR" ]; then
    echo "[LCA] Warning: LCA core not found at $LCA_DIR. Skipping checks."
    exit 0
fi

cd "$LCA_DIR" || exit 0

FAILED=0

# Check N+1
for FILE in $STAGED_FILES; do
    echo "[LCA] Scanning $FILE for N+1 patterns..."
    uv run lca n1 "$PROJECT_ROOT/$FILE" --root "$PROJECT_ROOT" --fail-on 1 || FAILED=1
done

# Check Hardcoded values
for FILE in $STAGED_FILES; do
    echo "[LCA] Scanning $FILE for Hardcoded values..."
    uv run lca hardcoded "$PROJECT_ROOT/$FILE" --root "$PROJECT_ROOT" --fail-on 1 || FAILED=1
done

if [ $FAILED -ne 0 ]; then
    echo ""
    echo "[LCA] ❌ Pre-commit checks failed! Please fix the issues above."
    echo "To bypass (not recommended): git commit --no-verify"
    exit 1
fi

echo "[LCA] ✅ All checks passed."
exit 0
EOF

chmod +x "$HOOK_FILE"

echo "✅ LCA pre-commit hook installed successfully at .git/hooks/pre-commit"
