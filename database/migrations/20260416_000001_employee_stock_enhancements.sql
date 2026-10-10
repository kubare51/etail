-- ==========================================================
-- Migration: Employee Stock Product Enhancements (Weight & Product Type)
-- Date: 2026-04-16
-- ==========================================================
-- UP

-- 1. Add stock_audit_by setting (1=Product Based, 2=Section Based)
INSERT INTO ret_settings (name, value, description) 
VALUES ('stock_audit_by', '1', 'Stock audit type: 1=Product Based, 2=Section Based')
ON DUPLICATE KEY UPDATE description = 'Stock audit type: 1=Product Based, 2=Section Based';

-- 2. Add weight column for non-tagged product weight entry
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_employee_stock_product' AND COLUMN_NAME = 'weight');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_employee_stock_product` ADD COLUMN `weight` DECIMAL(10,3) DEFAULT 0.000 AFTER `pcs`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Add product_type column (1=tagged, 2=non-tagged)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_employee_stock_product' AND COLUMN_NAME = 'product_type');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_employee_stock_product` ADD COLUMN `product_type` TINYINT(1) DEFAULT 1 AFTER `weight`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
