Write-Host "=== HELPERS ==="
$helpers = Get-ChildItem "admin/application/helpers/*.php" -ErrorAction SilentlyContinue
foreach ($f in $helpers) {
    $c = (Select-String -Path $f.FullName -Pattern "function " -AllMatches).Count
    Write-Host ("{0}: {1} functions" -f $f.Name, $c)
}
Write-Host "Total helpers: $($helpers.Count)"

Write-Host ""
Write-Host "=== LIBRARIES ==="
$libs = Get-ChildItem "admin/application/libraries/*.php" -ErrorAction SilentlyContinue
foreach ($f in $libs) {
    $c = (Select-String -Path $f.FullName -Pattern "function " -AllMatches).Count
    Write-Host ("{0}: {1} functions" -f $f.Name, $c)
}
Write-Host "Total libraries: $($libs.Count)"

Write-Host ""
Write-Host "=== CORE ==="
$cores = Get-ChildItem "admin/application/core/*.php" -ErrorAction SilentlyContinue
foreach ($f in $cores) {
    $c = (Select-String -Path $f.FullName -Pattern "function " -AllMatches).Count
    Write-Host ("{0}: {1} functions" -f $f.Name, $c)
}
Write-Host "Total core: $($cores.Count)"

Write-Host ""
Write-Host "=== BRAIN MODULES ==="
$brains = Get-ChildItem "knowledge_brain/*/MODULE_BRAIN.md" -ErrorAction SilentlyContinue | Where-Object { $_.DirectoryName -notmatch "_bk_|_TEMPLATE|_SYSTEM" }
foreach ($b in $brains) {
    Write-Host $b.Directory.Name
}
Write-Host "Total brain modules: $($brains.Count)"

Write-Host ""
Write-Host "=== CONTROLLERS NOT IN ANY BRAIN ==="
$controllerFiles = Get-ChildItem "admin/application/controllers/admin_*.php" | Select-Object -ExpandProperty BaseName
$brainNames = $brains | ForEach-Object { $_.Directory.Name }
foreach ($ctrl in $controllerFiles) {
    $found = $false
    foreach ($brain in $brainNames) {
        $brainDir = "knowledge_brain/$brain"
        $files = Get-ChildItem "$brainDir/*.md" -ErrorAction SilentlyContinue
        foreach ($md in $files) {
            if (Select-String -Path $md.FullName -Pattern $ctrl -Quiet -ErrorAction SilentlyContinue) {
                $found = $true
                break
            }
        }
        if ($found) { break }
    }
    if (-not $found) {
        Write-Host "  UNCOVERED: $ctrl.php"
    }
}

Write-Host ""
Write-Host "=== OTHER CONTROLLER FILES (non admin_*) ==="
Get-ChildItem "admin/application/controllers/*.php" | Where-Object { $_.Name -notmatch "^admin_" } | ForEach-Object { Write-Host "  $($_.Name)" }
