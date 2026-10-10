-- Migration: Add Common PO advance-adjustment column to `ret_purchase_order`
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-02
-- Safe: Column checked via INFORMATION_SCHEMA before adding
--
-- IMPORTANT: No `common_po_ref` column is added here. The PO -> Supplier Bill link
--            ALREADY EXISTS:
--              ret_purchase_order.purchase_type      = 1 (Against Order)
--              ret_purchase_order.purchase_order_no  -> customerorder.id_customerorder
--            A Common PO bill is identified by joining through that existing link:
--              JOIN customerorder co ON co.id_customerorder = po.purchase_order_no
--              WHERE po.purchase_type = 1 AND co.is_common_po = 1
--            Adding a parallel reference column would create two sources of truth.

-- UP

-- advance_adjusted_amt
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_purchase_order' AND COLUMN_NAME = 'advance_adjusted_amt');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_purchase_order` ADD `advance_adjusted_amt` decimal(16,2) NOT NULL DEFAULT ''0.00'' COMMENT ''Total Common PO advance adjusted against this bill'' AFTER `total_payable_wt`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index to support PO -> bill recall and traceability (CPO-17)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_purchase_order' AND INDEX_NAME = 'idx_purchase_order_no');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_purchase_order_no` ON `ret_purchase_order` (`purchase_order_no`, `purchase_type`, `bill_status`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- DROP INDEX `idx_purchase_order_no` ON `ret_purchase_order`;
-- ALTER TABLE `ret_purchase_order` DROP COLUMN `advance_adjusted_amt`;
