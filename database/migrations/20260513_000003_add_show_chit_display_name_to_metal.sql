-- Migration: Add show_chit and display_name columns to metal table
-- Date: 13-05-2026
-- Branch: feature/commodity-based-gateway
-- Safe: ADD COLUMN — uses INFORMATION_SCHEMA check before adding

-- UP
-- metal.show_chit
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'metal' AND COLUMN_NAME = 'show_chit');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `metal` ADD COLUMN `show_chit` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''0 -> No, 1 -> Yes (Show in Chit / Scheme display)'' AFTER `metal_status`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- metal.display_name
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'metal' AND COLUMN_NAME = 'display_name');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `metal` ADD COLUMN `display_name` VARCHAR(100) DEFAULT NULL COMMENT ''Custom display name shown when show_chit = 1'' AFTER `show_chit`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Enable show_chit for the first two metals (Gold & Silver by default)
UPDATE `metal` SET `show_chit` = 1
WHERE `id_metal` IN (
  SELECT `id_metal` FROM (
    SELECT `id_metal` FROM `metal` ORDER BY `id_metal` ASC LIMIT 2
  ) AS t
);

-- DOWN
-- ALTER TABLE `metal`
--   DROP COLUMN `display_name`,
--   DROP COLUMN `show_chit`;
