-- ============================================================================
-- Migration: Offer-Based Discount Module — Estimation Integration Columns
-- Date: 2026-07-13
-- Module: OFFER-002
-- Description: Adds columns to ret_estimation / ret_estimation_items to store
-- overall and item-wise offer discount amounts, and a master on/off setting.
-- Safe: Each column checked via INFORMATION_SCHEMA before adding (idempotent,
-- matches this repo's convention). No `AFTER` anchors are used, since this
-- repo's ret_estimation/ret_estimation_items column order has already
-- shifted since the sister codebase this was authored against (see
-- disc_per/bulk_was_disc_per/wastage_slab_id/pure_wt additions) — new
-- columns are appended at the end to avoid anchoring against a stale layout.
-- ============================================================================

-- UP

-- ret_estimation.total_offer_disc_amt
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_estimation' AND COLUMN_NAME = 'total_offer_disc_amt');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_estimation` ADD COLUMN `total_offer_disc_amt` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Total overall offer discount applied to the estimation''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ret_estimation_items.id_offer (CSV of offer IDs, e.g. "4,7" — multi-offer per item)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_estimation_items' AND COLUMN_NAME = 'id_offer');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_estimation_items` ADD COLUMN `id_offer` VARCHAR(255) DEFAULT NULL COMMENT ''CSV of applied offer IDs (e.g. 4,7)''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ret_estimation_items.offer_disc_amt
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_estimation_items' AND COLUMN_NAME = 'offer_disc_amt');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_estimation_items` ADD COLUMN `offer_disc_amt` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Total item-level offer discount applied''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- SETTING: master switch to enable/disable offer system on estimation screen
INSERT INTO `ret_settings` (`name`, `value`, `description`, `created_by`, `created_on`)
SELECT 'is_offer_on_estimation', '1', 'Enable Discount Offer on Estimation (1-Yes, 0-No)', 1, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ret_settings` WHERE `name` = 'is_offer_on_estimation');

-- DOWN
-- ALTER TABLE `ret_estimation` DROP COLUMN `total_offer_disc_amt`;
-- ALTER TABLE `ret_estimation_items` DROP COLUMN `id_offer`;
-- ALTER TABLE `ret_estimation_items` DROP COLUMN `offer_disc_amt`;
-- DELETE FROM `ret_settings` WHERE `name` = 'is_offer_on_estimation';
