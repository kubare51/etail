<#
.SYNOPSIS
    Logimax Deploy Script (Windows/PowerShell)

.DESCRIPTION
    Deploys the application by pulling the latest code, clearing caches,
    and running health checks.

.PARAMETER Branch
    Branch to deploy (default: current branch)

.PARAMETER Rollback
    Rollback to previous commit

.PARAMETER DryRun
    Show what would happen without making changes

.PARAMETER HealthOnly
    Only run health checks

.EXAMPLE
    .\deploy.ps1
    .\deploy.ps1 -Branch QA
    .\deploy.ps1 -Rollback
    .\deploy.ps1 -DryRun
#>

param(
    [string]$Branch = "",
    [switch]$Rollback,
    [switch]$DryRun,
    [switch]$HealthOnly
)

$ErrorActionPreference = "Continue"

# ── Configuration ──
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$DeployRoot = (Resolve-Path (Join-Path $ScriptDir "..")).Path
$DeployUrl = if ($env:DEPLOY_URL) { $env:DEPLOY_URL } else { "http://localhost/admin" }
$PhpPath = if ($env:PHP_PATH) { $env:PHP_PATH } else { "C:\xampp\php\php.exe" }
$LogDir = Join-Path $DeployRoot ".deploy-logs"
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$LogFile = Join-Path $LogDir "deploy_${Timestamp}.log"

# Create log dir
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

# ── Logging Functions ──
function Log-Info($msg)    { $line = "[$(Get-Date -Format 'HH:mm:ss')] $msg"; Write-Host $line -ForegroundColor Cyan; Add-Content $LogFile $line }
function Log-Success($msg) { $line = "[$(Get-Date -Format 'HH:mm:ss')] ✅ $msg"; Write-Host $line -ForegroundColor Green; Add-Content $LogFile $line }
function Log-Warn($msg)    { $line = "[$(Get-Date -Format 'HH:mm:ss')] ⚠️  $msg"; Write-Host $line -ForegroundColor Yellow; Add-Content $LogFile $line }
function Log-Error($msg)   { $line = "[$(Get-Date -Format 'HH:mm:ss')] ❌ $msg"; Write-Host $line -ForegroundColor Red; Add-Content $LogFile $line }

# ── Pre-flight ──
function Invoke-Preflight {
    Log-Info "Running pre-flight checks..."
    
    # Check git
    try { git --version | Out-Null } catch { Log-Error "git not found"; exit 1 }
    
    # Check git repo
    Push-Location $DeployRoot
    try { git rev-parse --git-dir | Out-Null } catch { Log-Error "Not a git repo: $DeployRoot"; exit 1 }
    Pop-Location
    
    # Check uncommitted changes
    $status = git -C $DeployRoot status --porcelain
    if ($status) {
        Log-Warn "Uncommitted changes detected"
        if (-not $DryRun) {
            Log-Error "Cannot deploy with uncommitted changes"
            exit 1
        }
    }
    
    Log-Success "Pre-flight checks passed"
}

# ── Clear Cache ──
function Clear-AppCache {
    Log-Info "Clearing CI3 caches..."
    
    $cacheDir = Join-Path $DeployRoot "admin\application\cache"
    if (Test-Path $cacheDir) {
        Get-ChildItem $cacheDir -Filter "*.php" -File | Remove-Item -Force -ErrorAction SilentlyContinue
        Get-ChildItem $cacheDir -Filter "error_rate_*" -File | Remove-Item -Force -ErrorAction SilentlyContinue
        Log-Success "CI3 cache cleared"
    }
}

# ── Health Check ──
function Invoke-HealthCheck {
    Log-Info "Running health checks..."
    $passed = 0
    $failed = 0
    
    # PHP syntax on critical files
    if (Test-Path $PhpPath) {
        $criticalFiles = @(
            "admin\application\controllers\admin_ret_estimation.php",
            "admin\application\controllers\admin_ret_billing.php",
            "admin\application\controllers\admin_payment.php",
            "admin\application\models\ret_estimation_model.php",
            "admin\application\models\ret_billing_model.php"
        )
        
        foreach ($f in $criticalFiles) {
            $fullPath = Join-Path $DeployRoot $f
            if (Test-Path $fullPath) {
                $null = & $PhpPath -l $fullPath 2>$null
                if ($LASTEXITCODE -eq 0) { $passed++ }
                else { Log-Error "PHP syntax error in $f"; $failed++ }
            }
        }
        Log-Info "PHP syntax: $passed passed, $failed failed"
    }
    
    # Critical files exist
    $requiredFiles = @(
        "admin\application\config\database.php",
        "admin\application\config\config.php",
        "admin\index.php",
        "admin\assets\js\general.js"
    )
    
    foreach ($f in $requiredFiles) {
        $fullPath = Join-Path $DeployRoot $f
        if (Test-Path $fullPath) { $passed++ }
        else { Log-Error "Missing: $f"; $failed++ }
    }
    
    # HTTP health check
    if ($DeployUrl) {
        try {
            $response = Invoke-WebRequest -Uri $DeployUrl -TimeoutSec 10 -UseBasicParsing -ErrorAction Stop
            $code = $response.StatusCode
            if ($code -eq 200 -or $code -eq 302) {
                Log-Success "HTTP check: $DeployUrl → $code"
                $passed++
            } else {
                Log-Error "HTTP check: $DeployUrl → $code"
                $failed++
            }
        } catch {
            Log-Warn "HTTP check: Server not reachable at $DeployUrl"
        }
    }
    
    if ($failed -gt 0) {
        Log-Error "Health check: $passed passed, $failed FAILED"
        return $false
    }
    
    Log-Success "Health check: $passed passed, 0 failed"
    return $true
}

# ── Deploy ──
function Invoke-Deploy($targetBranch) {
    Log-Info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    Log-Info "🚀 Deploying: $targetBranch"
    Log-Info "   Root: $DeployRoot"
    Log-Info "   Time: $(Get-Date)"
    Log-Info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    Push-Location $DeployRoot
    
    # Save rollback point
    $prevCommit = git rev-parse HEAD
    Set-Content (Join-Path $LogDir ".last_good_commit") $prevCommit
    Log-Info "Previous commit: $prevCommit"
    
    if ($DryRun) {
        Log-Info "[DRY RUN] Would: git fetch origin $targetBranch"
        Log-Info "[DRY RUN] Would: git checkout $targetBranch && git pull"
        Pop-Location
        return
    }
    
    # Fetch and pull
    Log-Info "Fetching latest..."
    git fetch origin $targetBranch 2>&1 | ForEach-Object { Log-Info $_ }
    
    Log-Info "Checking out $targetBranch..."
    git checkout $targetBranch 2>&1 | ForEach-Object { Log-Info $_ }
    git pull origin $targetBranch 2>&1 | ForEach-Object { Log-Info $_ }
    
    $newCommit = git rev-parse HEAD
    $commitMsg = git log -1 --pretty=format:"%s"
    $filesChanged = (git diff --name-only $prevCommit $newCommit | Measure-Object -Line).Lines
    
    Log-Info "New commit: $newCommit"
    Log-Info "Message: $commitMsg"
    Log-Info "Files changed: $filesChanged"
    
    Pop-Location
    
    # Clear cache
    Clear-AppCache
    
    # Health check
    $healthy = Invoke-HealthCheck
    if (-not $healthy) {
        Log-Error "Health check failed! Consider rolling back."
    }
    
    Log-Success "Deploy complete!"
    
    # Save deploy record
    $record = @{
        timestamp = (Get-Date -Format "o")
        branch = $targetBranch
        commit = $newCommit
        prev_commit = $prevCommit
        files_changed = $filesChanged
        message = $commitMsg
    } | ConvertTo-Json -Compress
    Add-Content (Join-Path $LogDir "deploy_history.jsonl") $record
}

# ── Rollback ──
function Invoke-Rollback {
    Log-Info "🔄 Rolling back..."
    
    $rollbackFile = Join-Path $LogDir ".last_good_commit"
    if (-not (Test-Path $rollbackFile)) {
        Log-Error "No rollback point found"
        exit 1
    }
    
    $prevCommit = Get-Content $rollbackFile
    $currentCommit = git -C $DeployRoot rev-parse HEAD
    
    Log-Info "Current: $currentCommit"
    Log-Info "Rolling back to: $prevCommit"
    
    if ($DryRun) {
        Log-Info "[DRY RUN] Would: git checkout $prevCommit"
        return
    }
    
    Push-Location $DeployRoot
    git checkout $prevCommit 2>&1 | ForEach-Object { Log-Info $_ }
    Pop-Location
    
    Clear-AppCache
    Invoke-HealthCheck | Out-Null
    
    Log-Success "Rollback complete to $prevCommit"
}

# ── Main ──
if ($HealthOnly) {
    Invoke-HealthCheck
} elseif ($Rollback) {
    Invoke-Preflight
    Invoke-Rollback
} else {
    if (-not $Branch) {
        $Branch = git -C $DeployRoot rev-parse --abbrev-ref HEAD
    }
    Invoke-Preflight
    Invoke-Deploy $Branch
}
