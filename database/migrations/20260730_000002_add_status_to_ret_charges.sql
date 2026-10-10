-- ============================================================
-- Migration: Add status column to ret_charges (Charges master)
-- Date: 2026-07-30
-- Feature: I-GMSJ-005 — Master Data Validation & Dependency Testing
-- Description: Charges had no status column at all, so its Delete action did
--              a real hard DELETE (masters never hard-delete). Referenced by
--              ret_taging_charges.charge_id and
--              ret_estimation_other_charges.id_charge — deleting a charge
--              still in use on historical tags/estimations would corrupt
--              that data. DEFAULT 1 keeps all existing rows active.
-- ============================================================
-- UP

SET @db_name = DATABASE();

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'ret_charges'
      AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_charges` ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1=Active, 0=Inactive'' AFTER `tag_display`',
    'SELECT ''Column status already exists, skipping'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN (rollback)
-- ALTER TABLE `ret_charges` DROP COLUMN `status`;
