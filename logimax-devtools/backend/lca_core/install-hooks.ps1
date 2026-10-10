<#
.SYNOPSIS
Installs the LCA pre-commit hook in the current Git repository.
#>

$ErrorActionPreference = "Stop"

# Find project root
$projectRoot = git rev-parse --show-toplevel
if ($LASTEXITCODE -ne 0) {
    Write-Error "Not a git repository (could not find .git/hooks)"
    exit 1
}

$hookDir = "$projectRoot\.git\hooks"
$hookFile = "$hookDir\pre-commit"

if (-not (Test-Path $hookDir)) {
    Write-Error "Error: $hookDir does not exist."
    exit 1
}

$hookContent = @"
#!/usr/bin/env pwsh
# LCA v2 Pre-commit Hook

Write-Host "[LCA] Running pre-commit checks..."

`$stagedFiles = git diff --cached --name-only --diff-filter=ACM | Select-String '\.php$' | ForEach-Object { `$_.Line }

if (-not `$stagedFiles) {
    exit 0
}

`$projectRoot = git rev-parse --show-toplevel
`$lcaDir = "`$projectRoot\logimax-devtools\backend\lca_core"

if (-not (Test-Path `$lcaDir)) {
    Write-Host "[LCA] Warning: LCA core not found at `$lcaDir. Skipping checks." -ForegroundColor Yellow
    exit 0
}

Set-Location `$lcaDir
`$failed = `$false

foreach (`$file in `$stagedFiles) {
    Write-Host "[LCA] Scanning `$file for N+1 patterns..."
    uv run lca n1 "`$projectRoot\`$file" --root "`$projectRoot" --fail-on 1
    if (`$LASTEXITCODE -ne 0) { `$failed = `$true }
}

foreach (`$file in `$stagedFiles) {
    Write-Host "[LCA] Scanning `$file for Hardcoded values..."
    uv run lca hardcoded "`$projectRoot\`$file" --root "`$projectRoot" --fail-on 1
    if (`$LASTEXITCODE -ne 0) { `$failed = `$true }
}

if (`$failed) {
    Write-Host ""
    Write-Host "[LCA] ❌ Pre-commit checks failed! Please fix the issues above." -ForegroundColor Red
    Write-Host "To bypass (not recommended): git commit --no-verify" -ForegroundColor Yellow
    exit 1
}

Write-Host "[LCA] ✅ All checks passed." -ForegroundColor Green
exit 0
"@

Set-Content -Path $hookFile -Value $hookContent -Encoding utf8

Write-Host "✅ LCA pre-commit hook installed successfully at .git\hooks\pre-commit" -ForegroundColor Green
