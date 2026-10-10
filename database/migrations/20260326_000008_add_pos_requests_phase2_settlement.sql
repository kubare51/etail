-- Migration: Phase 2 — Add settlement columns to ret_pos_requests
-- Date: 26-03-2026
-- Source: database_queries.sql lines 13125-13137

-- UP

-- ret_pos_requests.settlement_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'settlement_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `settlement_id` INT DEFAULT NULL COMMENT ''FK to pos_settlements'' AFTER `pos_last_checked_at`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ret_pos_requests.settled_at
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_pos_requests' AND COLUMN_NAME = 'settled_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_pos_requests` ADD COLUMN `settled_at` DATE DEFAULT NULL COMMENT ''Date this transaction was settled'' AFTER `settlement_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `ret_pos_requests` DROP COLUMN `settled_at`;
-- ALTER TABLE `ret_pos_requests` DROP COLUMN `settlement_id`;
