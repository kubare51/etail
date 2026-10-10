@echo off
echo ============================================
echo  RPT-ST-001: Create branch and commit fix
echo ============================================

echo.
echo [1/5] Checking current branch...
git branch --show-current

echo.
echo [2/5] Creating bugfix branch from current HEAD...
git checkout -b bugfix/fix-sales-transfer-duplicate-key
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Branch creation failed. Branch may already exist.
    echo Trying to switch to existing branch...
    git checkout bugfix/fix-sales-transfer-duplicate-key
)

echo.
echo [3/5] Staging changed files...
git add admin/assets/js/ret_reports.js
git add admin/application/models/ret_reports_model.php

echo.
echo [4/5] Committing...
git commit -m "fix(RPT-ST-001): split sales_transfer JS variable to match dual PHP keys

The cash abstract report was reading data.list.sales_transfer_details
(flat array) for both the Sales and Purchase Download sections.
The Sales section expects a nested-by-category object (data.list.sales_transfer).

Fixed by declaring two separate JS variables - one per section:
  - sales_transfer        = data.list.sales_transfer (nested, Sales section)
  - sales_transfer_details = data.list.sales_transfer_details (flat, Purchase section)

Also initialized 'sales_transfer' key in PHP $return_data (line 686)
to prevent JS undefined when no bill_type=13 records exist in date range.

Files changed:
  admin/assets/js/ret_reports.js (lines 4537-4538, 4783-4784, 4796)
  admin/application/models/ret_reports_model.php (line 686)"

echo.
echo [5/5] Pushing to origin...
git push -u origin bugfix/fix-sales-transfer-duplicate-key

echo.
echo ============================================
echo  Done! Create PR: bugfix/fix-sales-transfer-duplicate-key -^> Retail_1.1.1.0001
echo ============================================
pause
