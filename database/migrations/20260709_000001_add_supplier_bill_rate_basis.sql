-- Migration: PUR-2607021534
-- Feature: Supplier GST Bill - Rate/Gm based on GWT/NWT/PWT + Fixed Rate mode
-- Date: 2026-07-09
-- Safe: Column existence checked before adding

-- UP

-- Step 1: Add gst_bill_rate_basis column to ret_purchase_order_items
-- Values: 0=Pure Weight (default, backward compat), 1=GWT, 2=NWT, 3=PWT
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_purchase_order_items' AND COLUMN_NAME = 'gst_bill_rate_basis');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_purchase_order_items` ADD `gst_bill_rate_basis` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''0=PureWt(default/backward-compat), 1=GWT, 2=NWT, 3=PWT — used in Supplier Bill Rate/Gram calculation''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Add purchase_entry_mode column to ret_purchase_order_items
-- Values: 1=Rate per Gram (default), 2=Fixed Amount
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_purchase_order_items' AND COLUMN_NAME = 'purchase_entry_mode');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_purchase_order_items` ADD `purchase_entry_mode` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1=Rate per Gram (default), 2=Fixed Amount (lump-sum from supplier invoice)''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Add gst_bill_taxable_amt column to ret_purchase_order_items
-- Stores the taxable base before GST (item_cost - total_tax), useful for reports
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_purchase_order_items' AND COLUMN_NAME = 'gst_bill_taxable_amt');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_purchase_order_items` ADD `gst_bill_taxable_amt` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT ''Taxable amount before GST for supplier bill entry (item_cost minus tax)''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify columns were added
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'ret_purchase_order_items' 
AND COLUMN_NAME IN ('gst_bill_rate_basis', 'purchase_entry_mode', 'gst_bill_taxable_amt')
ORDER BY COLUMN_NAME;
