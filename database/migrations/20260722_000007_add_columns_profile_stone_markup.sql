-- Migration: Add `is_stone_markup_allowed` column to `profile`
-- Purpose: Profile-based permission to allow editing Stone Weight Markup % in Estimation
-- Feature: Stone Weight Markup % (gated by ret_settings.stone_markup_enabled)

-- UP

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profile' AND COLUMN_NAME = 'is_stone_markup_allowed');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `profile` ADD `is_stone_markup_allowed` TINYINT(1) NOT NULL DEFAULT \'0\' COMMENT \'1-Yes,0-No -> Allow editing Stone Weight Markup % in Estimation\'', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
