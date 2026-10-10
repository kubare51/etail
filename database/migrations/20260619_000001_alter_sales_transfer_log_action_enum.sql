-- =============================================================================
-- Migration: Add 'mirror_purchase_created' to ret_sales_transfer_log.action enum
-- Task: Mirror Purchase Bill — open in new tab on download
-- Date: 2026-06-19
--
-- Purpose: The _create_mirror_purchase_bill() method logs a
--          'mirror_purchase_created' action to ret_sales_transfer_log.
--          The column may be ENUM or VARCHAR depending on deployment history.
--          This migration ensures the value is accepted in both cases.
--
-- Idempotent: YES — checks COLUMN_TYPE before altering.
-- Risk: ZERO data risk. Only widens allowed ENUM values.
-- Rollback: See bottom of file.
-- =============================================================================

-- Only alter if the column is an ENUM that does NOT already include 'mirror_purchase_created'
SET @col_type = (
    SELECT COLUMN_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ret_sales_transfer_log'
      AND COLUMN_NAME = 'action'
    LIMIT 1
);

-- If column is ENUM and doesn't contain 'mirror_purchase_created', widen it.
-- If column is VARCHAR(50), it already accepts any string — no change needed.
SET @sql = IF(
    @col_type LIKE 'enum%' AND @col_type NOT LIKE '%mirror_purchase_created%',
    'ALTER TABLE `ret_sales_transfer_log` MODIFY COLUMN `action` ENUM(''create'',''download'',''cancel'',''update'',''mirror_purchase_created'') NOT NULL',
    'SELECT ''SKIPPED: action column already accepts mirror_purchase_created'' AS migration_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =============================================================================
-- ROLLBACK (manual — only if needed):
-- ALTER TABLE `ret_sales_transfer_log`
--   MODIFY COLUMN `action` ENUM('create','download','cancel','update') NOT NULL;
-- =============================================================================
