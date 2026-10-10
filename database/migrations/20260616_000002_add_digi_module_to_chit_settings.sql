-- Migration: Add digi_module column to chit_settings
-- Date: 2026-06-16
-- Description: Adds a flag to control whether Digi is shown as a separate module
--              0 = don't show digi as separate module
--              1 = need digi as separate module (default)
-- Safe: Column checked via INFORMATION_SCHEMA before adding

-- UP

-- digi_module
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chit_settings' AND COLUMN_NAME = 'digi_module');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `chit_settings` ADD `digi_module` TINYINT(4) NOT NULL DEFAULT \'1\' COMMENT \'0 - digi as combined module, 1 - digi as separate module\' AFTER `maintenance_mode`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
