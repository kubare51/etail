Write-Host "=== UNCOVERED ADMIN CONTROLLERS - GIT DATES ==="

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

foreach ($ctrl in $uncovered) {
    $path = "admin/application/controllers/$ctrl"
    $result = & git log --format="%ai" --diff-filter=A -- $path 2>&1
    if ($result -and $result -is [string]) {
        $date = $result.Substring(0,10)
        $label = "EXISTED"
        if ($date -ge "2026-03-26") { $label = "NEW    " }
        Write-Host "  ${label}: $ctrl  (added $date)"
    }
    else {
        $result2 = & git log --format="%ai" -- $path 2>&1 | Select-Object -Last 1
        if ($result2 -and $result2 -is [string]) {
            $date = $result2.Substring(0,10)
            Write-Host "  INITIAL: $ctrl  (earliest $date)"
        }
        else {
            Write-Host "  NOHISTORY: $ctrl"
        }
    }
}

Write-Host ""
Write-Host "=== KEY UNCOVERED MODELS - GIT DATES ==="

$models = @(
    "ret_app_api_model.php", "ret_purchase_approval_model.php",
    "syncapi_model.php", "admin_usersms_model.php",
    "print_template_model.php", "pos_model.php",
    "chit_transaction_model.php", "wallet_model.php",
    "gift_voucher_model.php", "ret_tag_parts_model.php",
    "ret_emp_incentive_model.php", "agent_model.php",
    "cash_flow_model.php", "sms_model.php", "ret_eda_model.php",
    "digigold_modal.php", "kyc_model.php", "ret_services_model.php"
)

foreach ($m in $models) {
    $path = "admin/application/models/$m"
    $result = & git log --format="%ai" --diff-filter=A -- $path 2>&1
    if ($result -and $result -is [string]) {
        $date = $result.Substring(0,10)
        $label = "EXISTED"
        if ($date -ge "2026-03-26") { $label = "NEW    " }
        Write-Host "  ${label}: $m  (added $date)"
    }
    else {
        $result2 = & git log --format="%ai" -- $path 2>&1 | Select-Object -Last 1
        if ($result2 -and $result2 -is [string]) {
            $date = $result2.Substring(0,10)
            Write-Host "  INITIAL: $m  (earliest $date)"
        }
        else {
            Write-Host "  NOHISTORY: $m"
        }
    }
}

Write-Host ""
Write-Host "=== HELPERS - GIT DATES ==="

$helpers = Get-ChildItem "admin/application/helpers/*.php"
foreach ($f in $helpers) {
    $result = & git log --format="%ai" --diff-filter=A -- $f.FullName 2>&1
    if ($result -and $result -is [string]) {
        $date = $result.Substring(0,10)
        $label = "EXISTED"
        if ($date -ge "2026-03-26") { $label = "NEW    " }
        Write-Host "  ${label}: $($f.Name)  (added $date)"
    }
    else {
        Write-Host "  INITIAL: $($f.Name)"
    }
}

Write-Host ""
Write-Host "=== NON-ADMIN CONTROLLERS - GIT DATES ==="

$nonAdmin = Get-ChildItem "admin/application/controllers/*.php" | Where-Object { $_.Name -notmatch "^admin_|^Admin_" }
foreach ($f in $nonAdmin) {
    $result = & git log --format="%ai" --diff-filter=A -- $f.FullName 2>&1
    if ($result -and $result -is [string]) {
        $date = $result.Substring(0,10)
        $label = "EXISTED"
        if ($date -ge "2026-03-26") { $label = "NEW    " }
        Write-Host "  ${label}: $($f.Name)  (added $date)"
    }
    else {
        Write-Host "  INITIAL: $($f.Name)"
    }
}
