-- Migration: Add is_return column to `ret_billing`
-- Safe: Column existence checked before adding
-- Default 0 = normal bill, 1 = return-restricted (blocked from Sales Return / bill-details lookup)

-- UP

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND COLUMN_NAME = 'is_return');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_billing` ADD COLUMN `is_return` TINYINT(1) NOT NULL DEFAULT 0 AFTER `bill_status`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND COLUMN_NAME = 'is_return');
SET @sql = IF(@col_exists > 0, 'ALTER TABLE `ret_billing` DROP COLUMN `is_return`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
