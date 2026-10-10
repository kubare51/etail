-- ============================================================
-- Migration: Add versioning columns to ret_emp_incentive_config
-- Description: Adds parent_id, deleted_on, deleted_by to support
--              version-based editing. When a config is modified,
--              the old record is soft-deleted (status=2) and a
--              new record is created with parent_id pointing to
--              the original. This preserves historical config_id
--              references in ret_emp_incentive_log.
-- Date: 09-05-2026
-- ============================================================
-- UP

-- Add parent_id column (safe to re-run)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_emp_incentive_config' AND COLUMN_NAME = 'parent_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_emp_incentive_config` ADD COLUMN `parent_id` INT(11) DEFAULT NULL COMMENT ''Previous version config ID (for version-based edit)'' AFTER `id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add deleted_on column (safe to re-run)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_emp_incentive_config' AND COLUMN_NAME = 'deleted_on');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_emp_incentive_config` ADD COLUMN `deleted_on` DATETIME DEFAULT NULL COMMENT ''Soft-delete timestamp when config is versioned'' AFTER `updated_on`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add deleted_by column (safe to re-run)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_emp_incentive_config' AND COLUMN_NAME = 'deleted_by');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_emp_incentive_config` ADD COLUMN `deleted_by` INT(11) DEFAULT NULL COMMENT ''User who versioned/deleted this config'' AFTER `deleted_on`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add index for parent lookup (safe to re-run)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_emp_incentive_config' AND INDEX_NAME = 'idx_parent_id');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `ret_emp_incentive_config` ADD KEY `idx_parent_id` (`parent_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN (rollback)
-- ALTER TABLE `ret_emp_incentive_config`
--     DROP COLUMN `parent_id`,
--     DROP COLUMN `deleted_on`,
--     DROP COLUMN `deleted_by`,
--     DROP KEY `idx_parent_id`;
