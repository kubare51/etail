-- Migration: Add show_va_mc column to `ret_product_master`
-- Safe: Column existence checked before adding
-- Controls whether VA / MC are shown as separate values in the estimation print.
-- Display-only flag — VA/MC always remain part of the item amount calculation.
-- 1 = show VA & MC (default, existing behaviour), 0 = hide, show final amount only

-- UP

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_product_master' AND COLUMN_NAME = 'show_va_mc');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_product_master` ADD COLUMN `show_va_mc` TINYINT(4) NOT NULL DEFAULT 1 COMMENT ''1 -> Show VA & MC in estimation print, 0 -> Hide'' AFTER `display_purity`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


