# =============================================================================
# deploy-pr.ps1 — Create PRs and auto-merge to all target branches
# =============================================================================
# Usage:
#   .\scripts\deploy-pr.ps1                       # Auto-reads changeset
#   .\scripts\deploy-pr.ps1 -Title "PR title"     # Manual title override
#   .\scripts\deploy-pr.ps1 -Title "..." -Body "..."  # Full manual override
#
# Changeset auto-generation:
#   If .changes/current.json exists, Title and Body are auto-populated from
#   changeset.py + pr_generator.py. Changeset is archived on success.
# =============================================================================

param(
    [string]$Title = "",
    [string]$Body  = ""
)

$ROOT = git rev-parse --show-toplevel 2>$null
if (-not $ROOT) {
    Write-Host "X Not in a git repository." -ForegroundColor Red
    exit 1
}

$ChangePy  = Join-Path $ROOT "logimax-devtools\backend\lca_core\changeset.py"
$PRGenPy   = Join-Path $ROOT "logimax-devtools\backend\lca_core\pr_generator.py"
$CurrentJson = Join-Path $ROOT ".changes\current.json"

$TargetBranches = @("Retail_1.1.1.0001", "QA", "support")
$CurrentBranch  = git branch --show-current

if (-not $CurrentBranch) {
    Write-Host "X Not on any branch (detached HEAD?)" -ForegroundColor Red
    exit 1
}

# ── Auto-generate Title / Body from changeset ─────────────────────────────────
$ChangesetsArchived = $false
if ((Test-Path $ChangePy) -and (Test-Path $CurrentJson)) {
    if ($Title -eq "") {
        $Title = python $ChangePy commit-msg 2>$null
        if ($Title) {
            # Take only the first line as title
            $Title = ($Title -split "`n")[0].Trim()
        }
    }
    if ($Body -eq "" -and (Test-Path $PRGenPy)) {
        $Body = python $PRGenPy --project-root $ROOT 2>$null
        if (-not $Body) { $Body = $Title }
    }
}

# Fallbacks if still empty
if ($Title -eq "") {
    $Title = Read-Host "No changeset found and no -Title given. Enter PR title"
}
if ($Body -eq "") {
    $Body = $Title
}

# ── Push first ────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "=== Deploy: $CurrentBranch ===" -ForegroundColor Cyan
Write-Host "    Title:  $Title" -ForegroundColor Cyan
Write-Host ""

Write-Host "Pushing $CurrentBranch..." -ForegroundColor Yellow
git push origin $CurrentBranch 2>$null
Write-Host ""

$PRNumbers  = @{}
$Merged     = @()
$Conflicted = @()
$Failed     = @()

# ── Create PRs ────────────────────────────────────────────────────────────────
foreach ($base in $TargetBranches) {
    Write-Host "Creating PR: $CurrentBranch -> $base" -ForegroundColor White

    $output = gh pr create --base $base --head $CurrentBranch --title $Title --body $Body 2>&1

    if ($output -match "already exists") {
        Write-Host "   PR already exists, finding..." -ForegroundColor Yellow
        $prNum = gh pr list --base $base --head $CurrentBranch --json number -q '.[0].number' 2>$null
        if ($prNum) {
            $PRNumbers[$base] = $prNum
            Write-Host "   Found: #$prNum" -ForegroundColor Green
        } else {
            $Failed += "$base`:find-failed"
            Write-Host "   Could not find existing PR" -ForegroundColor Red
        }
        continue
    }

    if ($output -match '/(\d+)$') {
        $prNum = $Matches[1]
        $PRNumbers[$base] = $prNum
        Write-Host "   Created: #$prNum" -ForegroundColor Green
    } else {
        $Failed += "$base`:create-failed"
        Write-Host "   Failed: $output" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "--- Checking mergeability ---" -ForegroundColor Cyan
Start-Sleep -Seconds 3

# ── Check & Merge ─────────────────────────────────────────────────────────────
foreach ($base in $TargetBranches) {
    $prNum = $PRNumbers[$base]
    if (-not $prNum) { continue }

    Write-Host "Checking #$prNum ($base)..." -ForegroundColor White

    $retries  = 0
    $mergeable = "UNKNOWN"
    $state     = "UNKNOWN"

    while ($retries -lt 3) {
        $info = gh pr view $prNum --json mergeable,mergeStateStatus 2>$null | ConvertFrom-Json
        $mergeable = $info.mergeable
        $state     = $info.mergeStateStatus
        if ($mergeable -ne "UNKNOWN") { break }
        $retries++
        Write-Host "   Pending, retry $retries/3..." -ForegroundColor Yellow
        Start-Sleep -Seconds 3
    }

    if ($mergeable -eq "MERGEABLE" -and $state -eq "CLEAN") {
        Write-Host "   Clean - merging..." -ForegroundColor Green
        $mergeResult = gh pr merge $prNum --merge --admin 2>&1
        if ($LASTEXITCODE -eq 0) {
            $Merged += "$base`:#$prNum"
            Write-Host "   MERGED!" -ForegroundColor Green
        } else {
            $Failed += "$base`:#$prNum`:merge-failed"
            Write-Host "   Merge failed: $mergeResult" -ForegroundColor Red
        }
    } elseif ($mergeable -eq "CONFLICTING") {
        $Conflicted += "$base`:#$prNum"
        Write-Host "   CONFLICT - skipped (resolve manually)" -ForegroundColor Red
    } else {
        $Failed += "$base`:#$prNum`:$mergeable-$state"
        Write-Host "   Status: $mergeable / $state - skipped" -ForegroundColor Yellow
    }
}

# ── Archive changeset on full success ─────────────────────────────────────────
if ($Conflicted.Count -eq 0 -and $Failed.Count -eq 0 -and $Merged.Count -gt 0) {
    if ((Test-Path $ChangePy) -and (Test-Path $CurrentJson)) {
        Write-Host ""
        Write-Host "Archiving changeset..." -ForegroundColor Gray
        python $ChangePy archive 2>$null | Out-Null
        Write-Host "Changeset archived to .changes/history/" -ForegroundColor Gray
    }
}

# ── Summary ───────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "=== DEPLOY SUMMARY ===" -ForegroundColor Cyan
Write-Host ""

if ($Merged.Count -gt 0) {
    Write-Host "  MERGED ($($Merged.Count)):" -ForegroundColor Green
    $Merged | ForEach-Object { Write-Host "     $_" -ForegroundColor Green }
    Write-Host ""
}

if ($Conflicted.Count -gt 0) {
    Write-Host "  CONFLICTS ($($Conflicted.Count)) - resolve manually:" -ForegroundColor Red
    $Conflicted | ForEach-Object { Write-Host "     $_" -ForegroundColor Red }
    Write-Host ""
}

if ($Failed.Count -gt 0) {
    Write-Host "  FAILED ($($Failed.Count)):" -ForegroundColor Red
    $Failed | ForEach-Object { Write-Host "     $_" -ForegroundColor Red }
    Write-Host ""
}

if ($Conflicted.Count -eq 0 -and $Failed.Count -eq 0) {
    Write-Host "  All branches deployed successfully!" -ForegroundColor Green
}
Write-Host ""
