-- Migration: Store resolved tax group / calculation type on Common PO lines
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-05a
-- Safe: Each column checked via INFORMATION_SCHEMA before adding
--
-- Tax percentage on a Common PO line is DERIVED from the product master, following
-- the same chain Supplier Bill Entry uses:
--
--   ret_product_master.tax_group_id  ->  ret_taxgroupitems.tgi_tgrpcode
--                                    ->  ret_taxmaster.tax_percentage
--
-- The resolved percentage is already stored in customerorderdetails.tax_percent
-- (migration 20260805_000008). These two columns record WHICH group produced it and
-- HOW it was applied, so a PO recalled months later at Supplier Bill Entry (CPO-11)
-- reproduces the original figure even if the master has since been re-rated.
-- Without this, a master rate change silently rewrites the history of every open PO.

-- UP

-- tax_group_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'tax_group_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `tax_group_id` int DEFAULT NULL COMMENT ''ret_taxgroupmaster.tgrp_id the Common PO line tax percentage was resolved from'' AFTER `tax_percent`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- tax_calc_type
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'tax_calc_type');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `tax_calc_type` tinyint(1) NOT NULL DEFAULT ''2'' COMMENT ''From ret_product_master.purchase_tax_type. 1-Inclusive, 2-Exclusive'' AFTER `tax_group_id`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `customerorderdetails` DROP COLUMN `tax_calc_type`;
-- ALTER TABLE `customerorderdetails` DROP COLUMN `tax_group_id`;
