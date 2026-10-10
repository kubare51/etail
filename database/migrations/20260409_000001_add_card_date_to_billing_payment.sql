-- Migration for adding card_date column to payment tables
-- Task-ID: 05d28ff631f2
-- Safe: Non-destructive ALTER (adds nullable columns)

-- UP

-- ret_billing_payment.card_date
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing_payment' AND COLUMN_NAME = 'card_date');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_billing_payment` ADD COLUMN `card_date` DATE NULL DEFAULT NULL AFTER `payment_ref_number`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ret_issue_rcpt_payment.card_date
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_rcpt_payment' AND COLUMN_NAME = 'card_date');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_issue_rcpt_payment` ADD COLUMN `card_date` DATE NULL DEFAULT NULL AFTER `payment_ref_number`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ret_service_bill_payment.card_date
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_service_bill_payment' AND COLUMN_NAME = 'card_date');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_service_bill_payment` ADD COLUMN `card_date` DATE NULL DEFAULT NULL AFTER `payment_ref_number`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
