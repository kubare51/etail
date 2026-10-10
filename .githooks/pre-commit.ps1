# ============================================
# Pre-commit Hook for eTail v3 (PowerShell)
# ============================================
# Runs before every commit to verify code quality
# Controlled by .githooks/config → PRE_COMMIT=on|off
# ============================================

# ── Config check ──────────────────────────────
$configFile = Join-Path $PSScriptRoot "config"
if (Test-Path $configFile) {
    $enabled = (Get-Content $configFile | Where-Object { $_ -match '^PRE_COMMIT=' }) -replace 'PRE_COMMIT=', '' -replace '\s', ''
    if ($enabled -ne 'on') {
        exit 0
    }
}

Write-Host ""
Write-Host "Running pre-commit checks..." -ForegroundColor Cyan
Write-Host ""

$exitCode = 0
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)

# Get staged files
$stagedPHP = git diff --cached --name-only --diff-filter=ACM | Where-Object { $_ -match '\.php$' }
$stagedJS = git diff --cached --name-only --diff-filter=ACM | Where-Object { $_ -match '\.js$' -and $_ -notmatch 'node_modules' }
$stagedPY = git diff --cached --name-only --diff-filter=ACM | Where-Object { $_ -match '\.py$' }

# ============================================
# PHP Checks
# ============================================
if ($stagedPHP) {
    $count = ($stagedPHP | Measure-Object).Count
    Write-Host "PHP files staged: $count" -ForegroundColor Yellow
    
    # Syntax check
    Write-Host "  Checking PHP syntax..." -ForegroundColor Gray
    foreach ($file in $stagedPHP) {
        if (Test-Path $file) {
            $output = php -l $file 2>&1
            if ($LASTEXITCODE -ne 0) {
                Write-Host "  Error: Syntax error in: $file" -ForegroundColor Red
                $exitCode = 1
            }
        }
    }
    
    # PHP_CodeSniffer
    $phpcs = Join-Path $root "admin\tests\vendor\bin\phpcs.bat"
    if (Test-Path $phpcs) {
        Write-Host "  Running PHP_CodeSniffer..." -ForegroundColor Gray
        & $phpcs --standard=PSR12 -n $stagedPHP 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0) {
            Write-Host "  Warning: Code style issues found (run 'run_lint.bat fix')" -ForegroundColor Yellow
        }
    }
    
    Write-Host ""
}

# ============================================
# JavaScript Checks
# ============================================
if ($stagedJS) {
    $count = ($stagedJS | Measure-Object).Count
    Write-Host "JavaScript files staged: $count" -ForegroundColor Yellow
    
    # ESLint
    $eslint = Join-Path $root "admin\tests\node_modules\.bin\eslint.cmd"
    if (Test-Path $eslint) {
        Write-Host "  Running ESLint..." -ForegroundColor Gray
        & $eslint $stagedJS 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0) {
            Write-Host "  Warning: ESLint issues found (run 'run_lint.bat js fix')" -ForegroundColor Yellow
        }
    }
    
    Write-Host ""
}

# ============================================
# Python Checks
# ============================================
if ($stagedPY) {
    $count = ($stagedPY | Measure-Object).Count
    Write-Host "Python files staged: $count" -ForegroundColor Yellow
    
    # Syntax check
    Write-Host "  Checking Python syntax..." -ForegroundColor Gray
    foreach ($file in $stagedPY) {
        if (Test-Path $file) {
            python -m py_compile $file 2>&1 | Out-Null
            if ($LASTEXITCODE -ne 0) {
                Write-Host "  Error: Syntax error in: $file" -ForegroundColor Red
                $exitCode = 1
            }
        }
    }
    
    Write-Host ""
}

# ============================================
# Final Result
# ============================================
Write-Host ""
if ($exitCode -eq 0) {
    Write-Host "All pre-commit checks passed!" -ForegroundColor Green
} else {
    Write-Host "Pre-commit checks failed. Fix issues before committing." -ForegroundColor Red
    Write-Host ""
    Write-Host "To bypass (not recommended): git commit --no-verify" -ForegroundColor Gray
}

exit $exitCode
