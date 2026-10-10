-- Migration: Add line-level rate type to `customerorderdetails`
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-05
-- Safe: Column checked via INFORMATION_SCHEMA before adding
--
-- BRS 4.1 lists "Rate Type" as field 11 of the LINE ITEM set, so a single Common PO
-- can legitimately carry a Fixed line and an Unfixed line together. Migration
-- 20260805_000001 added customerorder.rate_type_fix at header level only, which
-- cannot represent that. This adds the per-line column; the header column is kept
-- as a roll-up so downstream recall (CPO-08/10/11) can filter without a join:
--
--   customerorder.rate_type_fix : 0-NA, 1-All Fixed, 2-All Unfixed, 3-Mixed
--   customerorderdetails.rate_type_fix : 1-Fixed, 2-Unfixed   (authoritative per line)

-- UP

-- rate_type_fix
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'rate_type_fix');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `rate_type_fix` tinyint(1) NOT NULL DEFAULT ''0'' COMMENT ''Common PO line rate type. 0-NA (not a Common PO line), 1-Fixed, 2-Unfixed'' AFTER `rate_per_gram`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- tax_percent (retained so the line can be re-derived and audited later)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'tax_percent');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `tax_percent` decimal(10,2) NOT NULL DEFAULT ''0.00'' COMMENT ''Common PO line GST percentage applied to taxable_amount'' AFTER `rate_type_fix`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update the header column comment to document the Mixed roll-up value
ALTER TABLE `customerorder` MODIFY `rate_type_fix` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-NA (not a Common PO),1-All Fixed,2-All Unfixed,3-Mixed';

-- DOWN
-- ALTER TABLE `customerorder` MODIFY `rate_type_fix` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-NA (not a Common PO),1-Fixed,2-Unfixed';
-- ALTER TABLE `customerorderdetails` DROP COLUMN `tax_percent`;
-- ALTER TABLE `customerorderdetails` DROP COLUMN `rate_type_fix`;
