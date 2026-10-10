-- Migration: Add Stone Weight Markup columns to `ret_estimation_item_stones`
-- Feature: Stone Weight Markup % (gated by ret_settings.stone_markup_enabled)
-- Safe: Each column checked via INFORMATION_SCHEMA before adding

-- UP

-- markup_percent
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_estimation_item_stones' AND COLUMN_NAME = 'markup_percent');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_estimation_item_stones` ADD `markup_percent` DECIMAL(6,2) NOT NULL DEFAULT \'0.00\' COMMENT \'Stone weight markup %, +/- \' AFTER `is_apply_in_lwt`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- chargeable_wt
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_estimation_item_stones' AND COLUMN_NAME = 'chargeable_wt');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_estimation_item_stones` ADD `chargeable_wt` DECIMAL(12,4) NULL DEFAULT NULL COMMENT \'Stone wt after markup applied; NULL = same as wt\' AFTER `markup_percent`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
