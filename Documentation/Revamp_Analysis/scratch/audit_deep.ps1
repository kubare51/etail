Write-Host "=== UNCOVERED ADMIN CONTROLLERS - FUNCTION COUNT ==="
$uncovered = @(
    "admin_adv_booking.php", "admin_agent.php", "admin_apm.php",
    "admin_emp_incentive.php", "admin_gift_vocuher.php",
    "admin_import_export.php", "admin_invoice_builder.php",
    "admin_irn_settings.php", "Admin_knowledge_base.php",
    "admin_mails.php", "admin_opening_master.php",
    "admin_pan_verification.php", "admin_print_template.php",
    "admin_ret_eda.php", "admin_ret_estimation_konika.php",
    "admin_ret_services.php", "admin_ret_supp_catalog.php",
    "admin_ret_tag_parts.php", "admin_ret_task.php",
    "admin_ret_wishlist.php", "admin_stock_age_master.php",
    "admin_wallet.php"
)
$totalFuncs = 0
$totalSize = 0
foreach ($ctrl in $uncovered) {
    $path = "admin/application/controllers/$ctrl"
    if (Test-Path $path) {
        $size = (Get-Item $path).Length
        $funcs = (Select-String -Path $path -Pattern "function " -AllMatches).Count
        $totalFuncs += $funcs
        $totalSize += $size
        Write-Host ("{0}: {1} functions, {2:N0} bytes" -f $ctrl, $funcs, $size)
    }
}
Write-Host ""
Write-Host "Total uncovered controller functions: $totalFuncs"
Write-Host ("Total uncovered controller size: {0:N0} bytes ({1:N1} KB)" -f $totalSize, ($totalSize/1024))

Write-Host ""
Write-Host "=== NON-ADMIN CONTROLLERS - FUNCTION COUNT ==="
$nonAdmin = Get-ChildItem "admin/application/controllers/*.php" | Where-Object { $_.Name -notmatch "^admin_" }
$naFuncs = 0
$naSize = 0
foreach ($f in $nonAdmin) {
    $funcs = (Select-String -Path $f.FullName -Pattern "function " -AllMatches).Count
    $naFuncs += $funcs
    $naSize += $f.Length
    Write-Host ("{0}: {1} functions, {2:N0} bytes" -f $f.Name, $funcs, $f.Length)
}
Write-Host ""
Write-Host "Total non-admin controller functions: $naFuncs"
Write-Host ("Total non-admin controller size: {0:N0} bytes ({1:N1} KB)" -f $naSize, ($naSize/1024))

Write-Host ""
Write-Host "=== UNCOVERED MODELS ==="
$brainModelRefs = Select-String -Path "knowledge_brain/*/MODULE_BRAIN.md" -Pattern "_model\.php" -ErrorAction SilentlyContinue | ForEach-Object { if ($_ -match '(\w+_model\.php)') { $matches[1] } } | Sort-Object -Unique
$allModels = Get-ChildItem "admin/application/models/*.php" | Select-Object -ExpandProperty Name
$uncoveredModels = 0
foreach ($m in $allModels) {
    if ($m -notmatch "_backup") {
        $found = $false
        foreach ($ref in $brainModelRefs) {
            if ($m -eq $ref) { $found = $true; break }
        }
        if (-not $found) {
            $funcs = (Select-String -Path "admin/application/models/$m" -Pattern "function " -AllMatches).Count
            $size = (Get-Item "admin/application/models/$m").Length
            Write-Host ("  UNCOVERED: {0}: {1} functions, {2:N0} bytes" -f $m, $funcs, $size)
            $uncoveredModels++
        }
    }
}
Write-Host "Total uncovered models: $uncoveredModels"

Write-Host ""
Write-Host "=== VIEW DIRECTORIES ==="
$viewDirs = Get-ChildItem "admin/application/views" -Directory
Write-Host "Total view directories: $($viewDirs.Count)"
$totalViewFiles = 0
foreach ($d in $viewDirs) {
    $count = (Get-ChildItem $d.FullName -Filter "*.php" -Recurse).Count
    $totalViewFiles += $count
}
Write-Host "Total view PHP files: $totalViewFiles"

Write-Host ""
Write-Host "=== JS FILES ==="
$jsFiles = Get-ChildItem "admin/assets/js/*.js" -ErrorAction SilentlyContinue
$jsSize = ($jsFiles | Measure-Object -Property Length -Sum).Sum
Write-Host "Total JS files: $($jsFiles.Count)"
Write-Host ("Total JS size: {0:N0} bytes ({1:N1} MB)" -f $jsSize, ($jsSize/1MB))
