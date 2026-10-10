-- ============================================================
-- Migration: Add Employee Incentive Report menu under Sales Report
-- Description: Inserts menu under Sales Report (id_menu=136)
--              and grants full access to all profiles.
-- ============================================================
-- UP

-- Step 1: Insert menu entry under Sales Report (idempotent — check by link)
INSERT INTO `menu` (`label`, `link`, `parent`, `sort`, `icon`, `active`)
SELECT 'Emp Incentive Report', 'reports/emp_sales_incentive', 136, 25, 'fa fa-circle-o', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `menu` WHERE `link` = 'reports/emp_sales_incentive');

-- Step 2: Grant access to all profiles (idempotent)
SET @new_menu_id = (SELECT `id_menu` FROM `menu` WHERE `link` = 'reports/emp_sales_incentive' LIMIT 1);

INSERT INTO `access` (`id_profile`, `id_menu`, `view`, `add`, `edit`, `delete`)
SELECT p.id_profile, @new_menu_id, 1, 1, 1, 1
FROM `profile` p
WHERE @new_menu_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM `access` a
    WHERE a.id_profile = p.id_profile AND a.id_menu = @new_menu_id
);
