-- Migration: Add missing columns for Sales Transfer Detailed Print columns (idempotent)
-- Target Tables: ret_bill_details, ret_taging
-- Date: 2026-07-20

-- 1. ret_bill_details.calculation_based_on
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND COLUMN_NAME = 'calculation_based_on');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_bill_details` ADD COLUMN `calculation_based_on` INT DEFAULT NULL AFTER `gross_wt`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. ret_taging.retail_max_wastage_percent
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND COLUMN_NAME = 'retail_max_wastage_percent');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_taging` ADD COLUMN `retail_max_wastage_percent` DECIMAL(10,2) DEFAULT NULL AFTER `net_wt`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. ret_taging.tag_mc_value
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND COLUMN_NAME = 'tag_mc_value');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_taging` ADD COLUMN `tag_mc_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `retail_max_wastage_percent`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. ret_taging.tag_mc_type
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND COLUMN_NAME = 'tag_mc_type');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_taging` ADD COLUMN `tag_mc_type` TINYINT(1) DEFAULT 1 COMMENT ''1-> Per Pc 2->Per Grm 3-> % on price'' AFTER `tag_mc_value`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
