-- Migration: Add need_our_store column to `branch`
-- Safe: Column existence checked before adding

-- UP

-- need_our_store
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branch' AND COLUMN_NAME = 'need_our_store');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `branch` ADD `need_our_store` TINYINT(4) NOT NULL DEFAULT ''1'' AFTER `active`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
