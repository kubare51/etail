-- Migration: Add chargeable_amount column to `ret_billing_item_stones`
-- Feature: Stone Weight Markup % (Billing) — mirrors ret_estimation_item_stones.chargeable_amount.
-- amount (price) stays ACTUAL (reports); chargeable_amount = chargeable_wt x rate (customer/pricing). NULL = same as price.
-- Billing is read-only/auto-filled from estimation for this feature. Safe: column checked via INFORMATION_SCHEMA before adding.

-- UP
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing_item_stones' AND COLUMN_NAME = 'chargeable_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_billing_item_stones` ADD `chargeable_amount` DECIMAL(10,2) NULL DEFAULT NULL COMMENT \'Stone amount after markup (chargeable_wt x rate); customer/pricing only. NULL = same as price.\' AFTER `chargeable_wt`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
