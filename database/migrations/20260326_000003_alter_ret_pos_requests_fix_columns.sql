-- Migration: Fix columns + add missing columns on ret_pos_requests (base fixes)
-- Date: 26-03-2026
-- Source: database_queries.sql lines 12632-12700
-- Safe: MODIFY is idempotent, ADD COLUMN uses INFORMATION_SCHEMA check

-- UP

-- MODIFY statements are idempotent by nature (re-runnable without error)
ALTER TABLE `ret_pos_requests`
  MODIFY `pos_mer_id` VARCHAR(50) DEFAULT NULL,
  MODIFY `pos_res_ref_id` VARCHAR(100) DEFAULT NULL,
  MODIFY `pos_utr` VARCHAR(50) DEFAULT NULL COMMENT 'UPI UTR number',
  MODIFY `pos_req_status` TINYINT DEFAULT 1 COMMENT '0=INIT/PENDING, 1=SUCCESS, 2=CANCELLED, 3=FAILED';

-- ADD COLUMN statements wrapped with INFORMATION_SCHEMA checks
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_imie');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_imie` VARCHAR(50) DEFAULT NULL COMMENT ''Device IMEI'' AFTER `pos_mer_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_qr_string');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_qr_string` TEXT DEFAULT NULL COMMENT ''QR data for DQR'' AFTER `id_provider`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'created_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `pos_req_status`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_req_bill_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_req_bill_id` INT DEFAULT NULL COMMENT ''FK to ret_estimation'' AFTER `pos_req_bill_cusid`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_req_payload');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_req_payload` TEXT DEFAULT NULL COMMENT ''Full API request payload (JSON)''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_res_payload');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_res_payload` TEXT DEFAULT NULL COMMENT ''Full API response payload (JSON)''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'idempotency_key');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `idempotency_key` VARCHAR(100) DEFAULT NULL COMMENT ''Idempotency key''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_req_created_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_req_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN
-- No rollback — these columns are part of core POS functionality
