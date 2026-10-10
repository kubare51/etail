-- ============================================================
-- Migration: Add status and updatedby columns to smith_company_op_balance
-- Date: 2026-06-01
-- Feature: Smith/Company Opening Balance cancel/edit workflow
-- Description: The cancel workflow sets status=2 and updatedby for cancelled
--              records, but both columns were added manually in dev DB and
--              never captured in a migration.
--              DEFAULT 1 on status ensures all existing rows remain active.
-- ============================================================
-- UP

-- Safe ADD COLUMN: skip if column already exists (MySQL compatible)
SET @db_name = DATABASE();

-- 1. Add status column
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'smith_company_op_balance'
      AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `smith_company_op_balance` ADD COLUMN `status` TINYINT(4) NOT NULL DEFAULT 1 COMMENT ''1=Active, 2=Cancelled'' AFTER `id_smith_company_op_balance`',
    'SELECT ''Column status already exists, skipping'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add updatedby column
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'smith_company_op_balance'
      AND COLUMN_NAME = 'updatedby'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `smith_company_op_balance` ADD COLUMN `updatedby` INT(11) DEFAULT NULL COMMENT ''User who last updated/cancelled''',
    'SELECT ''Column updatedby already exists, skipping'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN (rollback)
-- ALTER TABLE `smith_company_op_balance` DROP COLUMN `status`;
-- ALTER TABLE `smith_company_op_balance` DROP COLUMN `updatedby`;
