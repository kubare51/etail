-- Migration: Add chit_metalrate_limit column to chit_settings
-- Date: 2026-08-04
-- Description: Adds chit_metalrate_limit to store the allowed deviation percentage for metal rate editing
-- Safe: Column checked via INFORMATION_SCHEMA before adding

-- UP

-- chit_metalrate_limit
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chit_settings' AND COLUMN_NAME = 'chit_metalrate_limit');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `chit_settings` ADD `chit_metalrate_limit` INT(11) NOT NULL DEFAULT \'5\' AFTER `rate_update`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
