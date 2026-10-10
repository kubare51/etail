-- Migration: Add pos_req_id FK column to ret_billing_payment
-- Date: 26-03-2026
-- Source: database_queries.sql lines 12840-12845

-- UP
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing_payment' AND COLUMN_NAME = 'pos_req_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_billing_payment` ADD COLUMN `pos_req_id` INT DEFAULT NULL COMMENT ''FK to ret_pos_requests'' AFTER `NB_type`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `ret_billing_payment` DROP COLUMN `pos_req_id`;
