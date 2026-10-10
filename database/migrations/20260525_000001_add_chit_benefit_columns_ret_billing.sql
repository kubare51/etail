-- Migration: Add rate_benefit columns for chit adjustment billing
-- Purpose: Store the rate benefit amount that is deducted from taxable value BEFORE GST calculation
-- Ticket: [2042] Chit Adjustment Bill – Apply Rate Benefit Before GST Calculation
-- Date: 2026-05-25

-- ret_billing_chit_utilization.rate_benefit
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing_chit_utilization' AND COLUMN_NAME = 'rate_benefit');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_billing_chit_utilization` ADD COLUMN `rate_benefit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Scheme benefit amount deducted from taxable value BEFORE GST calculation'' AFTER `rate_per_gram`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ret_billing.chit_benefit_before_gst
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND COLUMN_NAME = 'chit_benefit_before_gst');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_billing` ADD COLUMN `chit_benefit_before_gst` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Total chit/scheme benefit deducted from taxable value BEFORE GST calculation'' AFTER `tot_amt_received`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
