#!/usr/bin/env powershell
# ─────────────────────────────────────────────────────────────────────────────
# Download ChromaDB from RunPod and verify locally
# Run this AFTER embed_remote.py completes on the pod
# ─────────────────────────────────────────────────────────────────────────────

param(
    [string]$PodHost = "194.68.245.215",
    [int]$PodPort = 22145,
    [string]$SshKey = "$HOME\.ssh\id_ed25519",
    [string]$LocalStore = "$HOME\.logimax\rag_store",
    [string]$ProjectRoot = "c:\xampp\htdocs\etail_v3"
)

Write-Host ""
Write-Host "╔══════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║  LCA Semantic — Download & Verify                      ║" -ForegroundColor Cyan
Write-Host "╚══════════════════════════════════════════════════════════╝" -ForegroundColor Cyan

# Step 1: Check pod is reachable
Write-Host ""
Write-Host "  [1/5] Checking pod connection..." -ForegroundColor Yellow
$testResult = ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 root@$PodHost -p $PodPort -i $SshKey "echo OK" 2>&1
if ($testResult -ne "OK") {
    Write-Host "  ❌ Cannot connect to pod. Is it running?" -ForegroundColor Red
    exit 1
}
Write-Host "  ✅ Pod connected" -ForegroundColor Green

# Step 2: Check embedding output exists
Write-Host ""
Write-Host "  [2/5] Verifying pod output..." -ForegroundColor Yellow
$podCheck = ssh -o StrictHostKeyChecking=no root@$PodHost -p $PodPort -i $SshKey "du -sh ~/data/output/chroma_db/ && cat ~/data/output/chroma_db/manifest.json 2>/dev/null || echo 'NO_MANIFEST'"
Write-Host "  $podCheck"

# Step 3: Clean old store
Write-Host ""
Write-Host "  [3/5] Preparing local store..." -ForegroundColor Yellow
if (Test-Path $LocalStore) {
    $oldSize = [math]::Round((Get-ChildItem -Recurse $LocalStore | Measure-Object Length -Sum).Sum / 1MB, 1)
    Write-Host "  Removing old store ($oldSize MB)..."
    Remove-Item -Recurse -Force $LocalStore
}
New-Item -ItemType Directory -Path $LocalStore -Force | Out-Null
Write-Host "  ✅ Local store ready: $LocalStore" -ForegroundColor Green

# Step 4: Download
Write-Host ""
Write-Host "  [4/5] Downloading from pod..." -ForegroundColor Yellow
$t0 = Get-Date
scp -o StrictHostKeyChecking=no -P $PodPort -i $SshKey -r "root@${PodHost}:~/data/output/chroma_db/*" $LocalStore
$elapsed = ((Get-Date) - $t0).TotalSeconds
$size = [math]::Round((Get-ChildItem -Recurse $LocalStore | Measure-Object Length -Sum).Sum / 1MB, 1)
Write-Host "  ✅ Downloaded $size MB in $([math]::Round($elapsed))s" -ForegroundColor Green

# Step 5: Verify
Write-Host ""
Write-Host "  [5/5] Running verification..." -ForegroundColor Yellow
$verifyScript = Join-Path $ProjectRoot "logimax-devtools\backend\lca_core\lca\semantic\runpod\verify_rag_store.py"
python $verifyScript --store $LocalStore

Write-Host ""
Write-Host "╔══════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║  ✅ Download complete! Test with:                      ║" -ForegroundColor Green
Write-Host "║  lca semantic `"billing discount calculation`"           ║" -ForegroundColor Green
Write-Host "║                                                         ║" -ForegroundColor Green
Write-Host "║  Don't forget to STOP the RunPod pod!                  ║" -ForegroundColor Green
Write-Host "╚══════════════════════════════════════════════════════════╝" -ForegroundColor Green
