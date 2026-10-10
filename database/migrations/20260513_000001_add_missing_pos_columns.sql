-- Migration: Add missing est_id and related pos_req columns to ret_pos_requests
-- Date: 13-05-2026
-- Source: Sync with Kalson staging DB schema

-- UP
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_req_est_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_req_est_id` INT DEFAULT NULL AFTER `pos_req_bill_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_req_bill_type');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_req_bill_type` TINYINT DEFAULT NULL AFTER `pos_req_est_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_req_source_ref');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_req_source_ref` VARCHAR(100) DEFAULT NULL AFTER `pos_req_bill_type`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_req_id_branch');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_req_id_branch` INT DEFAULT NULL AFTER `pos_req_source_ref`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_req_createdon');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_req_createdon` DATETIME DEFAULT NULL AFTER `pos_req_id_branch`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_success_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_success_at` DATETIME DEFAULT NULL AFTER `pos_cancelled_at`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_success_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_success_by` INT DEFAULT NULL AFTER `pos_success_at`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'pos_callback_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `pos_callback_at` DATETIME DEFAULT NULL AFTER `pos_success_by`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `ret_pos_requests` DROP COLUMN `pos_callback_at`;
-- ALTER TABLE `ret_pos_requests` DROP COLUMN `pos_success_by`;
-- ALTER TABLE `ret_pos_requests` DROP COLUMN `pos_success_at`;
-- ALTER TABLE `ret_pos_requests` DROP COLUMN `pos_req_createdon`;
-- ALTER TABLE `ret_pos_requests` DROP COLUMN `pos_req_id_branch`;
-- ALTER TABLE `ret_pos_requests` DROP COLUMN `pos_req_source_ref`;
-- ALTER TABLE `ret_pos_requests` DROP COLUMN `pos_req_bill_type`;
-- ALTER TABLE `ret_pos_requests` DROP COLUMN `pos_req_est_id`;
