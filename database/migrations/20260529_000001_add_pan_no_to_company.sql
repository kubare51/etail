-- Migration: Add column `pan_no` to `company`
-- Safe: column checked via INFORMATION_SCHEMA before adding

-- UP

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company' AND COLUMN_NAME = 'pan_no');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `company` ADD `pan_no` VARCHAR(20) NULL DEFAULT NULL COMMENT \'Company PAN number\' AFTER `gst_number`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
