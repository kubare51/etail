-- Migration: Add Common PO advance columns to `ret_po_payment`
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-02
-- Safe: Each column checked via INFORMATION_SCHEMA before adding
--
-- NOTE: `bill_type` already exists (1-For Bill, 2-For Receipt, 3-For Approval).
--       Common PO advance uses a NEW value 4 = 'For Common PO Advance'.
--       No separate is_advance flag is added — the existing discriminator is reused.
--       The column COMMENT is updated below to document value 4.
-- NOTE: `pay_status` already exists (1-Live, 2-Cancel) and carries cancel handling
--       for CPO-15. No new cancel columns needed.
-- NOTE: `ret_po_payment_detail.type` already carries '3-Advance Adj'. Reuse it.

-- UP

-- common_po_ref
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_po_payment' AND COLUMN_NAME = 'common_po_ref');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_po_payment` ADD `common_po_ref` int DEFAULT NULL COMMENT ''customerorder.id_customerorder - Common PO this advance is paid against'' AFTER `id_supplier_rate_cut`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- advance_adjusted
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_po_payment' AND COLUMN_NAME = 'advance_adjusted');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_po_payment` ADD `advance_adjusted` decimal(16,2) NOT NULL DEFAULT ''0.00'' COMMENT ''Portion of pay_amt already adjusted against supplier bills. Unadjusted = pay_amt - advance_adjusted'' AFTER `common_po_ref`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Document bill_type = 4 on the existing column
ALTER TABLE `ret_po_payment` MODIFY `bill_type` tinyint(1) DEFAULT NULL COMMENT '1-For Bill,2-For Receipt,3-For Approval,4-For Common PO Advance';

-- Index for advance lookup during bill adjustment (FIFO by pay_create_on)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_po_payment' AND INDEX_NAME = 'idx_common_po_advance');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_common_po_advance` ON `ret_po_payment` (`common_po_ref`, `pay_status`, `pay_create_on`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- DROP INDEX `idx_common_po_advance` ON `ret_po_payment`;
-- ALTER TABLE `ret_po_payment` MODIFY `bill_type` tinyint(1) DEFAULT NULL COMMENT '1-For Bill,2-For REceipt 3-> For Approval';
-- ALTER TABLE `ret_po_payment` DROP COLUMN `advance_adjusted`;
-- ALTER TABLE `ret_po_payment` DROP COLUMN `common_po_ref`;
