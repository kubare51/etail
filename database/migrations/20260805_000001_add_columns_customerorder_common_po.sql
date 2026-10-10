-- Migration: Add Common PO columns to `customerorder`
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-02
-- Safe: Each column checked via INFORMATION_SCHEMA before adding
--
-- NOTE: `rate_type` already exists on this table (1-order rate, 2-delivery rate).
--       It is NOT overloaded. `rate_type_fix` is a separate column for Fixed/Unfixed.
-- NOTE: Received weight/qty reuse the existing `delivered_wt` / `delivered_qty`
--       columns. No new columns are added for GRN receipt tracking.

-- UP

-- is_common_po
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorder' AND COLUMN_NAME = 'is_common_po');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorder` ADD `is_common_po` tinyint(1) NOT NULL DEFAULT ''0'' COMMENT ''0-No,1-Yes (Common PO for bullion advance rate fixing)'' AFTER `is_against_approval_stock`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- rate_type_fix
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorder' AND COLUMN_NAME = 'rate_type_fix');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorder` ADD `rate_type_fix` tinyint(1) NOT NULL DEFAULT ''0'' COMMENT ''0-NA (not a Common PO),1-Fixed,2-Unfixed'' AFTER `rate_type`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- common_po_status
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorder' AND COLUMN_NAME = 'common_po_status');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorder` ADD `common_po_status` tinyint(1) NOT NULL DEFAULT ''0'' COMMENT ''0-Open,1-Advance Paid,2-Received,3-Billed,4-Closed,5-Cancelled'' AFTER `rate_type_fix`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for Common PO lookup (payment / GRN / bill recall dropdowns)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorder' AND INDEX_NAME = 'idx_common_po');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_common_po` ON `customerorder` (`is_common_po`, `common_po_status`, `id_karigar`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- DROP INDEX `idx_common_po` ON `customerorder`;
-- ALTER TABLE `customerorder` DROP COLUMN `common_po_status`;
-- ALTER TABLE `customerorder` DROP COLUMN `rate_type_fix`;
-- ALTER TABLE `customerorder` DROP COLUMN `is_common_po`;
