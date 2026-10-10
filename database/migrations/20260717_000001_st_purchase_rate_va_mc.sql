-- Migration: Sales Transfer — persist Purchase Rate / Purchase VA / Purchase MC per line item (idempotent)
-- Applicable only for Tagged (salesTransType=1) and Non-Tagged (salesTransType=2) items;
-- Purchase items (3=Old Gold, 4=Sales Return, 5=Partly Sold) keep NULL.
-- Sources: ret_lot_inwards_detail.rate / ret_purchase_order_items.item_wastage / ret_purchase_order_items.mc_value
-- (with ret_taging lot_* snapshot fallbacks — same chain as Tag History report).

-- 1. ret_bill_details.purchase_rate
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND COLUMN_NAME = 'purchase_rate');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_bill_details` ADD COLUMN `purchase_rate` DECIMAL(10,2) DEFAULT NULL COMMENT ''Purchase Rate/G from PO (ST tagged/non-tagged only)'' AFTER `rate_per_grm`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. ret_bill_details.purchase_va
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND COLUMN_NAME = 'purchase_va');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_bill_details` ADD COLUMN `purchase_va` DECIMAL(10,2) DEFAULT NULL COMMENT ''Purchase VA/G (wastage %) from PO item (ST tagged/non-tagged only)'' AFTER `purchase_rate`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. ret_bill_details.purchase_mc
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND COLUMN_NAME = 'purchase_mc');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_bill_details` ADD COLUMN `purchase_mc` DECIMAL(10,2) DEFAULT NULL COMMENT ''Purchase MC/G from PO item (ST tagged/non-tagged only)'' AFTER `purchase_va`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
