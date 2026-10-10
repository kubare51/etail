-- Deemed Sales Transfer — Schema Migration
-- Run this on retail_dev (and all client databases)
-- Adds salesTransType column to ret_bill_details to track transfer item type

-- ret_bill_details.salesTransType
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND COLUMN_NAME = 'salesTransType');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_bill_details` ADD COLUMN `salesTransType` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1=Tagged, 2=Non-Tagged, 3=Old Gold, 4=Sales Return, 5=Partly Sold'' AFTER `item_type`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ret_nontag_item.is_st_locked
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_nontag_item' AND COLUMN_NAME = 'is_st_locked');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_nontag_item` ADD COLUMN `is_st_locked` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ret_nontag_item.st_bill_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_nontag_item' AND COLUMN_NAME = 'st_bill_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_nontag_item` ADD COLUMN `st_bill_id` INT(11) DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ret_bill_old_metal_sale_details.is_transferred
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_old_metal_sale_details' AND COLUMN_NAME = 'is_transferred');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_bill_old_metal_sale_details` ADD COLUMN `is_transferred` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ret_bill_old_metal_sale_details.st_bill_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_old_metal_sale_details' AND COLUMN_NAME = 'st_bill_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_bill_old_metal_sale_details` ADD COLUMN `st_bill_id` INT(11) DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
