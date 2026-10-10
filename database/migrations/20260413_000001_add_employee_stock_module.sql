-- ==========================================================
-- Migration: Add Employee Stock Product & Report
-- Date: 2026-04-13
-- ==========================================================
-- UP
-- 1. Create ret_employee_stock table
CREATE TABLE IF NOT EXISTS `ret_employee_stock` (
    `id_emp_stock` INT(11) NOT NULL AUTO_INCREMENT,
    `id_emp` INT(11) NOT NULL,
    `id_branch` INT(11) NOT NULL,
    `date` DATE NOT NULL,
    `created_on` DATETIME DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_emp_stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 2. Create ret_employee_stock_product table
CREATE TABLE IF NOT EXISTS `ret_employee_stock_product` (
    `id_emp_stock_product` INT(11) NOT NULL AUTO_INCREMENT,
    `id_emp_stock` INT(11) NOT NULL,
    `id_product` INT(11) NOT NULL,
    `id_section` INT(11) DEFAULT NULL,
    `pcs` INT(11) DEFAULT 0,
    `is_updated` TINYINT(1) DEFAULT 0,
    `updated_on` DATE DEFAULT NULL,
    `created_on` DATETIME DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id_emp_stock_product`),
    KEY `id_emp_stock` (`id_emp_stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 3. Add Menu Items (idempotent — check by link before inserting)

-- Add "Employee Stock Product" under "Retail Catalog"
INSERT INTO menu (label, link, parent, sort, icon, active) 
SELECT 'Employee Stock Product', 'admin_ret_catalog/employee_stock_product/add',
    (SELECT id_menu FROM (SELECT id_menu FROM menu WHERE label = 'Retail Catalog' LIMIT 1) AS t),
    99, 'fa fa-users', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM menu WHERE link = 'admin_ret_catalog/employee_stock_product/add');

SET @menu_id_1 = (SELECT id_menu FROM menu WHERE link = 'admin_ret_catalog/employee_stock_product/add' LIMIT 1);

-- Add "Employee Product Report" under "Stock Report"
INSERT INTO menu (label, link, parent, sort, icon, active) 
SELECT 'Employee Product Report', 'admin_ret_reports/employee_product_report/list',
    (SELECT id_menu FROM (SELECT id_menu FROM menu WHERE label = 'Stock Report' LIMIT 1) AS t),
    99, 'fa fa-bar-chart', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM menu WHERE link = 'admin_ret_reports/employee_product_report/list');

SET @menu_id_2 = (SELECT id_menu FROM menu WHERE link = 'admin_ret_reports/employee_product_report/list' LIMIT 1);

-- 4. Grant Access (all profiles, idempotent)
INSERT INTO access (id_profile, id_menu, `view`, `add`, `edit`, `delete`)
SELECT p.id_profile, @menu_id_1, 1, 1, 1, 1
FROM `profile` p
WHERE @menu_id_1 IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM `access` a WHERE a.id_profile = p.id_profile AND a.id_menu = @menu_id_1
);

INSERT INTO access (id_profile, id_menu, `view`, `add`, `edit`, `delete`)
SELECT p.id_profile, @menu_id_2, 1, 1, 1, 1
FROM `profile` p
WHERE @menu_id_2 IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM `access` a WHERE a.id_profile = p.id_profile AND a.id_menu = @menu_id_2
);
