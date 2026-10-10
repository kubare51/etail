-- Migration: Add chit_metalrate_min_limit and chit_metalrate_max_limit columns to chit_settings
-- Date: 2026-08-05
-- Description: Renames chit_metalrate_limit to chit_metalrate_min_limit and adds chit_metalrate_max_limit for metal rate variation limits

-- UP

-- chit_metalrate_min_limit
SET @has_old = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chit_settings' AND COLUMN_NAME = 'chit_metalrate_limit');
SET @has_min = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chit_settings' AND COLUMN_NAME = 'chit_metalrate_min_limit');

SET @sql = IF(@has_old > 0, 
    'ALTER TABLE `chit_settings` CHANGE COLUMN `chit_metalrate_limit` `chit_metalrate_min_limit` INT(11) NOT NULL DEFAULT \'0\'',
    IF(@has_min = 0, 'ALTER TABLE `chit_settings` ADD `chit_metalrate_min_limit` INT(11) NOT NULL DEFAULT \'0\' AFTER `rate_update`', 'SELECT 1')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- chit_metalrate_max_limit
SET @has_max = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chit_settings' AND COLUMN_NAME = 'chit_metalrate_max_limit');
SET @sql = IF(@has_max = 0, 'ALTER TABLE `chit_settings` ADD `chit_metalrate_max_limit` INT(11) NOT NULL DEFAULT \'5\' AFTER `chit_metalrate_min_limit`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
