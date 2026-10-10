# =============================================================================
# git-commit.ps1 - Structured commit using changeset manifest
# =============================================================================
# Usage:
#   .\scripts\git-commit.ps1               # Auto-reads .changes/current.json
#   .\scripts\git-commit.ps1 -NoVerify    # Skip pre-commit hooks
#   .\scripts\git-commit.ps1 -DryRun      # Preview commit message only
#
# If no changeset exists, this script will guide you to create one interactively.
# =============================================================================

param(
    [switch]$NoVerify,
    [switch]$DryRun
)

$ROOT = git rev-parse --show-toplevel 2>$null
if (-not $ROOT) {
    Write-Host "X Not in a git repository." -ForegroundColor Red
    exit 1
}

$ChangePy    = Join-Path $ROOT "logimax-devtools\backend\lca_core\changeset.py"
$CurrentJson = Join-Path $ROOT ".changes\current.json"

if (-not (Test-Path $ChangePy)) {
    Write-Host "X changeset.py not found at: $ChangePy" -ForegroundColor Red
    exit 1
}

# --- Check for active changeset ---
if (-not (Test-Path $CurrentJson)) {
    Write-Host ""
    Write-Host "  No active changeset found." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  Create one now? You need to provide:" -ForegroundColor Cyan
    Write-Host "    type    - fix | feat | refactor | style | perf | docs | chore | test"
    Write-Host "    module  - e.g. billing, estimation, reports"
    Write-Host "    summary - e.g. 'correct discount calculation for tagged items'"
    Write-Host ""

    $cs_type    = Read-Host "  type   "
    $cs_module  = Read-Host "  module "
    $cs_summary = Read-Host "  summary"

    if (-not $cs_type -or -not $cs_module -or -not $cs_summary) {
        Write-Host "X Aborted - all fields are required." -ForegroundColor Red
        exit 1
    }

    python $ChangePy add --type $cs_type --module $cs_module --summary $cs_summary
    if ($LASTEXITCODE -ne 0) {
        Write-Host "X Failed to create changeset." -ForegroundColor Red
        exit 1
    }

    $addMore = Read-Host "  Add detail line? (Enter detail or press ENTER to skip)"
    while ($addMore -ne "") {
        python $ChangePy detail $addMore
        $addMore = Read-Host "  Another detail? (or ENTER to skip)"
    }

    $ticket = Read-Host "  Ticket ID? (e.g. EST-057 or ENTER to skip)"
    if ($ticket -ne "") {
        python $ChangePy ticket $ticket
    }
    Write-Host ""
}

# --- Generate commit message ---
$commitMsg = (python $ChangePy commit-msg 2>&1) -join "`n"
if (-not $commitMsg -or $commitMsg.Trim() -eq "") {
    Write-Host "X No changeset data - cannot generate commit message." -ForegroundColor Red
    exit 1
}

# --- Show preview ---
Write-Host ""
Write-Host "=== Commit Message Preview ===" -ForegroundColor Cyan
Write-Host ""
Write-Host $commitMsg -ForegroundColor White
Write-Host ""

if ($DryRun) {
    Write-Host "[DryRun] No commit made." -ForegroundColor Yellow
    exit 0
}

$confirm = Read-Host "Commit with this message? [y/N]"
if ($confirm -notmatch '^[yY]$') {
    Write-Host "Aborted." -ForegroundColor Yellow
    exit 0
}

# --- Stage all if nothing staged ---
$staged = (git diff --cached --name-only 2>$null)
if (-not $staged) {
    Write-Host ""
    Write-Host "  Nothing staged. Stage all changes? (git add -A) [y/N]" -ForegroundColor Yellow
    $addAll = Read-Host "  "
    if ($addAll -match '^[yY]$') {
        git add -A
    } else {
        Write-Host "  Stage your files manually then re-run." -ForegroundColor Gray
        exit 0
    }
}

# --- Write commit message to temp file and commit ---
$TmpFile = Join-Path $env:TEMP "lca_commit_msg.txt"
[System.IO.File]::WriteAllText($TmpFile, $commitMsg, [System.Text.Encoding]::UTF8)

$commitArgs = @("commit", "-F", $TmpFile)
if ($NoVerify) { $commitArgs += "--no-verify" }

git @commitArgs
$result = $LASTEXITCODE

Remove-Item $TmpFile -ErrorAction SilentlyContinue

if ($result -eq 0) {
    Write-Host ""
    Write-Host "  Committed!" -ForegroundColor Green
    Write-Host "  Changeset still active in .changes/current.json" -ForegroundColor Gray
    Write-Host "  It will be archived automatically when you run deploy-pr.ps1" -ForegroundColor Gray
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "X Commit failed (exit $result)." -ForegroundColor Red
    exit $result
}
