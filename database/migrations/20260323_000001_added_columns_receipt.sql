-- Migration for adding columns to receipt table
-- Sowmiya
-- Safe: Non-destructive ALTER (adds nullable columns)

-- UP
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_receipt' AND COLUMN_NAME = 'goldrate_18ct');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_issue_receipt` ADD COLUMN `goldrate_18ct` INT(11) NULL AFTER `deposit_type`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_receipt' AND COLUMN_NAME = 'goldrate_22ct');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_issue_receipt` ADD COLUMN `goldrate_22ct` INT(11) NULL AFTER `goldrate_18ct`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_receipt' AND COLUMN_NAME = 'silverrate_1gm');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_issue_receipt` ADD COLUMN `silverrate_1gm` INT(11) NULL AFTER `goldrate_22ct`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;