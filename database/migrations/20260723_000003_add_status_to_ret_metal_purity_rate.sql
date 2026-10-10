-- ============================================================
-- Migration: Add status column to ret_metal_purity_rate (Metal Purity Rate master)
-- Date: 2026-07-23
-- Feature: I-GMSJ-005 — Master Data Deletion & Dependency Validation
-- Description: Metal Purity Rate had no status column, so the generic
--              master engine's soft-deactivate path (_master_delete /
--              _master_update_status) refused to act, but the list view
--              still rendered a "Delete" button with a delete-sounding
--              confirm dialog — a UI promise the backend could never keep.
--              Adding this column lets the engine deactivate/reactivate
--              Metal Purity Rate the same way as every other master
--              (masters never hard-delete). DEFAULT 1 keeps all existing
--              rows active.
-- ============================================================
-- UP

SET @db_name = DATABASE();

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'ret_metal_purity_rate'
      AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_metal_purity_rate` ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1=Active, 0=Inactive'' AFTER `market_rate_field`',
    'SELECT ''Column status already exists, skipping'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN (rollback)
-- ALTER TABLE `ret_metal_purity_rate` DROP COLUMN `status`;
