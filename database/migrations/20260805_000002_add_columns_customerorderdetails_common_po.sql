-- Migration: Add Common PO amount columns to `customerorderdetails`
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-02
-- Safe: Each column checked via INFORMATION_SCHEMA before adding
--
-- BRS 4.1 field mapping to EXISTING columns (no new columns needed for these):
--   Purity        -> id_purity
--   Product       -> id_product
--   Design        -> design_no
--   Sub Design    -> id_sub_design
--   Size          -> size
--   Pieces        -> totalitems
--   Gross Weight  -> weight
--   Stone Weight  -> less_wt
--   Net Weight    -> net_wt
--   Rate per Gram -> rate_per_gram
--
-- Only Category and the three amount columns are genuinely new.
--
-- NOTE: amounts use decimal(16,2), NOT the decimal(10,2) used elsewhere in this
--       schema. decimal(10,2) caps at 99,999,999.99 (~Rs 10 crore). Bullion line
--       values exceed that (10kg gold @ Rs 15,000/g = Rs 15 crore). See CPO-02 notes.

-- UP

-- id_category
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'id_category');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `id_category` int DEFAULT NULL COMMENT ''ret_category.id_ret_category - bullion category on Common PO'' AFTER `id_product`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- taxable_amount
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'taxable_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `taxable_amount` decimal(16,2) NOT NULL DEFAULT ''0.00'' COMMENT ''Common PO: net_wt x rate_per_gram'' AFTER `rate_per_gram`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- tax_amount
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'tax_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `tax_amount` decimal(16,2) NOT NULL DEFAULT ''0.00'' COMMENT ''Common PO: total_cgst + total_sgst + total_igst'' AFTER `taxable_amount`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- total_amount
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'total_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `total_amount` decimal(16,2) NOT NULL DEFAULT ''0.00'' COMMENT ''Common PO: taxable_amount + tax_amount'' AFTER `tax_amount`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `customerorderdetails` DROP COLUMN `total_amount`;
-- ALTER TABLE `customerorderdetails` DROP COLUMN `tax_amount`;
-- ALTER TABLE `customerorderdetails` DROP COLUMN `taxable_amount`;
-- ALTER TABLE `customerorderdetails` DROP COLUMN `id_category`;
