-- Migration: Add Stone Weight Markup chargeable-net columns to `ret_bill_details`
-- Feature: Stone Weight Markup % (gated by ret_settings.stone_markup_enabled)
-- Note: Billing is read-only/auto-filled from estimation for this feature (no entry UI yet).
-- Safe: Each column checked via INFORMATION_SCHEMA before adding

-- UP

-- chargeable_less_wt
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND COLUMN_NAME = 'chargeable_less_wt');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_bill_details` ADD `chargeable_less_wt` DECIMAL(12,3) NULL DEFAULT NULL COMMENT \'Less wt after stone markup; NULL = same as less_wt\' AFTER `less_wt`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- chargeable_net_wt
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND COLUMN_NAME = 'chargeable_net_wt');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_bill_details` ADD `chargeable_net_wt` DECIMAL(12,3) NULL DEFAULT NULL COMMENT \'gross_wt - chargeable_less_wt; NULL = same as net_wt. Pricing only.\' AFTER `net_wt`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
