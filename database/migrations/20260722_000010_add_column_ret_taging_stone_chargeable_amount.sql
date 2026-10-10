-- Migration: Add chargeable_amount column to `ret_taging_stone`
-- Feature: Stone Weight Markup % (gated by ret_settings.stone_markup_enabled)
-- Rationale: `amount` stays ACTUAL (actual_wt x rate) so all reports remain correct with no changes.
--            `chargeable_amount` holds the customer/sell amount (chargeable_wt x rate), used for pricing only.
-- Safe: column checked via INFORMATION_SCHEMA before adding

-- UP

-- chargeable_amount
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging_stone' AND COLUMN_NAME = 'chargeable_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_taging_stone` ADD `chargeable_amount` DECIMAL(10,2) NULL DEFAULT NULL COMMENT \'Stone amount after markup (chargeable_wt x rate); customer/pricing only. NULL = same as amount. amount stays actual for reports.\' AFTER `chargeable_wt`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
