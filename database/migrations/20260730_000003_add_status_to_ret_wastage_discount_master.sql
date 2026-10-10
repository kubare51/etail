-- ============================================================
-- Migration: Add status column to ret_wastage_discount_master (Discount master)
-- Date: 2026-07-30
-- Feature: I-GMSJ-005 — Master Data Validation & Dependency Testing
-- Description: Wastage Discount had no status column at all, so its Delete
--              action did a real hard DELETE (masters never hard-delete).
--              Referenced by ret_estimation_items.wastage_slab_id — deleting a
--              wastage slab still used on a historical estimation would
--              corrupt that data. DEFAULT 1 keeps all existing rows active.
-- ============================================================
-- UP

SET @db_name = DATABASE();

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'ret_wastage_discount_master'
      AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_wastage_discount_master` ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1=Active, 0=Inactive'' AFTER `value`',
    'SELECT ''Column status already exists, skipping'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN (rollback)
-- ALTER TABLE `ret_wastage_discount_master` DROP COLUMN `status`;
