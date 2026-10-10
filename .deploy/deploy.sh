#!/bin/bash
# =============================================================================
# Logimax Deploy Script
# 
# Deploys the application on a target server by pulling the latest code
# from the specified branch, clearing caches, and running health checks.
#
# Usage:
#   ./deploy.sh                     # Deploy current branch
#   ./deploy.sh --branch QA         # Deploy specific branch
#   ./deploy.sh --rollback          # Rollback to previous commit
#   ./deploy.sh --dry-run           # Show what would happen
#   ./deploy.sh --health-only       # Only run health checks
#
# Environment variables:
#   DEPLOY_ROOT    - Project root directory (default: auto-detect)
#   DEPLOY_URL     - Base URL for health checks (default: http://localhost/admin)
#   PHP_PATH       - Path to PHP binary (default: php)
#   SLACK_WEBHOOK  - Slack webhook URL for notifications (optional)
# =============================================================================

set -euo pipefail

# ── Configuration ──────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_ROOT="${DEPLOY_ROOT:-$(cd "$SCRIPT_DIR/.." && pwd)}"
DEPLOY_URL="${DEPLOY_URL:-http://localhost/admin}"
PHP_PATH="${PHP_PATH:-php}"
LOG_DIR="$DEPLOY_ROOT/.deploy-logs"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
LOG_FILE="$LOG_DIR/deploy_${TIMESTAMP}.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ── Functions ──────────────────────────────────────────────────────────

log() {
    echo -e "${BLUE}[$(date '+%H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date '+%H:%M:%S')] ✅ $1${NC}" | tee -a "$LOG_FILE"
}

warn() {
    echo -e "${YELLOW}[$(date '+%H:%M:%S')] ⚠️  $1${NC}" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[$(date '+%H:%M:%S')] ❌ $1${NC}" | tee -a "$LOG_FILE"
}

notify_slack() {
    if [ -n "${SLACK_WEBHOOK:-}" ]; then
        local message="$1"
        curl -s -X POST "$SLACK_WEBHOOK" \
            -H 'Content-type: application/json' \
            -d "{\"text\": \"$message\"}" > /dev/null 2>&1 || true
    fi
}

# ── Pre-flight Checks ─────────────────────────────────────────────────

preflight() {
    log "Running pre-flight checks..."
    
    # Check git
    if ! command -v git &> /dev/null; then
        error "git not found"
        exit 1
    fi
    
    # Check we're in a git repo
    if ! git -C "$DEPLOY_ROOT" rev-parse --git-dir > /dev/null 2>&1; then
        error "Not a git repository: $DEPLOY_ROOT"
        exit 1
    fi
    
    # Check PHP
    if ! command -v "$PHP_PATH" &> /dev/null; then
        warn "PHP not found at $PHP_PATH — skipping PHP checks"
    fi
    
    # Check for uncommitted changes
    if [ -n "$(git -C "$DEPLOY_ROOT" status --porcelain)" ]; then
        warn "Uncommitted changes detected"
        if [ "$DRY_RUN" = "false" ]; then
            error "Cannot deploy with uncommitted changes. Stash or commit first."
            exit 1
        fi
    fi
    
    success "Pre-flight checks passed"
}

# ── Deploy ─────────────────────────────────────────────────────────────

deploy() {
    local branch="$1"
    
    log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    log "🚀 Deploying: $branch"
    log "   Root: $DEPLOY_ROOT"
    log "   Time: $(date)"
    log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    cd "$DEPLOY_ROOT"
    
    # Save current commit for rollback
    local prev_commit=$(git rev-parse HEAD)
    echo "$prev_commit" > "$LOG_DIR/.last_good_commit"
    log "Previous commit: $prev_commit"
    
    # Fetch latest
    log "Fetching latest changes..."
    if [ "$DRY_RUN" = "true" ]; then
        log "[DRY RUN] Would run: git fetch origin $branch"
        log "[DRY RUN] Would run: git checkout $branch && git pull origin $branch"
        return 0
    fi
    
    git fetch origin "$branch" 2>&1 | tee -a "$LOG_FILE"
    
    # Checkout and pull
    log "Checking out $branch..."
    git checkout "$branch" 2>&1 | tee -a "$LOG_FILE"
    git pull origin "$branch" 2>&1 | tee -a "$LOG_FILE"
    
    local new_commit=$(git rev-parse HEAD)
    local commit_msg=$(git log -1 --pretty=format:"%s")
    log "New commit: $new_commit"
    log "Message: $commit_msg"
    
    # Show what changed
    local files_changed=$(git diff --name-only "$prev_commit" "$new_commit" | wc -l)
    log "Files changed: $files_changed"
    
    # Clear CI3 cache
    clear_cache
    
    # Run health checks
    health_check
    
    success "Deploy complete!"
    log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # Notification
    notify_slack "✅ *Deployed $branch* to $(hostname)\n- Commit: \`${new_commit:0:8}\`\n- Message: $commit_msg\n- Files changed: $files_changed"
    
    # Save deploy record
    echo "{\"timestamp\":\"$(date -Iseconds)\",\"branch\":\"$branch\",\"commit\":\"$new_commit\",\"prev_commit\":\"$prev_commit\",\"files_changed\":$files_changed,\"message\":\"$commit_msg\"}" >> "$LOG_DIR/deploy_history.jsonl"
}

# ── Rollback ───────────────────────────────────────────────────────────

rollback() {
    log "🔄 Rolling back to previous commit..."
    
    cd "$DEPLOY_ROOT"
    
    if [ ! -f "$LOG_DIR/.last_good_commit" ]; then
        error "No rollback point found. Cannot rollback."
        exit 1
    fi
    
    local prev_commit=$(cat "$LOG_DIR/.last_good_commit")
    local current_commit=$(git rev-parse HEAD)
    
    log "Current: $current_commit"
    log "Rolling back to: $prev_commit"
    
    if [ "$DRY_RUN" = "true" ]; then
        log "[DRY RUN] Would run: git checkout $prev_commit"
        return 0
    fi
    
    git checkout "$prev_commit" 2>&1 | tee -a "$LOG_FILE"
    clear_cache
    health_check
    
    success "Rollback complete to $prev_commit"
    notify_slack "🔄 *Rolled back* on $(hostname) to \`${prev_commit:0:8}\`"
}

# ── Cache Clear ────────────────────────────────────────────────────────

clear_cache() {
    log "Clearing CI3 caches..."
    
    local cache_dir="$DEPLOY_ROOT/admin/application/cache"
    if [ -d "$cache_dir" ]; then
        # Remove cache files but keep directory and index.html
        find "$cache_dir" -type f -name "*.php" -delete 2>/dev/null || true
        find "$cache_dir" -type f -name "error_rate_*" -delete 2>/dev/null || true
        success "CI3 cache cleared"
    fi
    
    # Clear OPcache if PHP available
    if command -v "$PHP_PATH" &> /dev/null; then
        $PHP_PATH -r "if(function_exists('opcache_reset')){opcache_reset();echo 'OPcache cleared';}" 2>/dev/null || true
    fi
}

# ── Health Check ───────────────────────────────────────────────────────

health_check() {
    log "Running health checks..."
    local passed=0
    local failed=0
    
    # Check 1: PHP syntax on critical files
    if command -v "$PHP_PATH" &> /dev/null; then
        local critical_files=(
            "admin/application/controllers/admin_ret_estimation.php"
            "admin/application/controllers/admin_ret_billing.php"
            "admin/application/controllers/admin_payment.php"
            "admin/application/models/ret_estimation_model.php"
            "admin/application/models/ret_billing_model.php"
        )
        
        for f in "${critical_files[@]}"; do
            if [ -f "$DEPLOY_ROOT/$f" ]; then
                if $PHP_PATH -l "$DEPLOY_ROOT/$f" > /dev/null 2>&1; then
                    ((passed++))
                else
                    error "PHP syntax error in $f"
                    ((failed++))
                fi
            fi
        done
        log "PHP syntax: $passed passed, $failed failed"
    fi
    
    # Check 2: Critical files exist
    local required_files=(
        "admin/application/config/database.php"
        "admin/application/config/config.php"
        "admin/index.php"
        "admin/assets/js/general.js"
    )
    
    for f in "${required_files[@]}"; do
        if [ -f "$DEPLOY_ROOT/$f" ]; then
            ((passed++))
        else
            error "Missing critical file: $f"
            ((failed++))
        fi
    done
    
    # Check 3: HTTP health check (if URL configured)
    if [ -n "$DEPLOY_URL" ]; then
        local http_code=$(curl -s -o /dev/null -w "%{http_code}" \
            --max-time 10 "$DEPLOY_URL" 2>/dev/null || echo "000")
        
        if [ "$http_code" = "200" ] || [ "$http_code" = "302" ]; then
            success "HTTP check: $DEPLOY_URL → $http_code"
            ((passed++))
        elif [ "$http_code" = "000" ]; then
            warn "HTTP check: Server not reachable at $DEPLOY_URL"
        else
            error "HTTP check: $DEPLOY_URL → $http_code"
            ((failed++))
        fi
    fi
    
    # Summary
    if [ $failed -gt 0 ]; then
        error "Health check: $passed passed, $failed FAILED"
        notify_slack "⚠️ *Health check failed* on $(hostname) — $failed checks failed"
        return 1
    else
        success "Health check: $passed passed, 0 failed"
    fi
}

# ── Main ───────────────────────────────────────────────────────────────

# Parse arguments
BRANCH=""
ROLLBACK=false
DRY_RUN=false
HEALTH_ONLY=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --branch|-b)    BRANCH="$2"; shift 2 ;;
        --rollback|-r)  ROLLBACK=true; shift ;;
        --dry-run|-d)   DRY_RUN=true; shift ;;
        --health-only)  HEALTH_ONLY=true; shift ;;
        --help|-h)
            echo "Usage: ./deploy.sh [options]"
            echo "  --branch BRANCH   Deploy specific branch"
            echo "  --rollback        Rollback to previous commit"
            echo "  --dry-run         Show what would happen"
            echo "  --health-only     Only run health checks"
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Create log directory
mkdir -p "$LOG_DIR"

# Execute
if [ "$HEALTH_ONLY" = "true" ]; then
    health_check
elif [ "$ROLLBACK" = "true" ]; then
    preflight
    rollback
else
    # Default to current branch if not specified
    if [ -z "$BRANCH" ]; then
        BRANCH=$(git -C "$DEPLOY_ROOT" rev-parse --abbrev-ref HEAD)
    fi
    preflight
    deploy "$BRANCH"
fi
