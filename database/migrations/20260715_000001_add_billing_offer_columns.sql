-- ============================================================================
-- Migration: Offer-Based Discount Module — Billing Integration Columns
-- Date: 2026-07-15
-- Module: OFFER-002 (Billing)
-- Description: Adds columns to ret_billing / ret_bill_details to store
-- overall and item-wise offer discount amounts.
-- Safe: Each column checked via INFORMATION_SCHEMA before adding (idempotent).
-- No `AFTER` anchors — see 20260713_000001_add_estimation_offer_columns.sql
-- for why this repo appends rather than anchors.
-- ============================================================================

-- UP

-- ret_billing.total_offer_disc_amt (sum of all per-item offer discounts)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND COLUMN_NAME = 'total_offer_disc_amt');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_billing` ADD COLUMN `total_offer_disc_amt` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Sum of all per-item offer discounts''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ret_bill_details.id_offer (CSV of offer IDs — NULL = no offer, inherited from estimation)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND COLUMN_NAME = 'id_offer');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_bill_details` ADD COLUMN `id_offer` VARCHAR(255) DEFAULT NULL COMMENT ''FK-ish CSV to ret_offer_master -- NULL = no offer''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ret_bill_details.offer_disc_amt
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND COLUMN_NAME = 'offer_disc_amt');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_bill_details` ADD COLUMN `offer_disc_amt` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Offer discount amount for this item''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `ret_billing` DROP COLUMN `total_offer_disc_amt`;
-- ALTER TABLE `ret_bill_details` DROP COLUMN `id_offer`;
-- ALTER TABLE `ret_bill_details` DROP COLUMN `offer_disc_amt`;
