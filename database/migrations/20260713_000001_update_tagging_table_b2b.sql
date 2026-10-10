-- Migration: Add b2b columns to `ret_taging`
-- Safe: Column existence checked before adding

-- UP

-- b2b_order_no
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND COLUMN_NAME = 'b2b_order_no');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_taging` ADD `b2b_order_no` VARCHAR(255) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- is_b2b
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND COLUMN_NAME = 'is_b2b');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_taging` ADD `is_b2b` TINYINT(1) NOT NULL DEFAULT ''0'' AFTER `b2b_order_no`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
