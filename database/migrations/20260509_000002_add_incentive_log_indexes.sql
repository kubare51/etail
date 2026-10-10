-- ============================================================
-- Migration: Add performance indexes to ret_emp_incentive_log
-- Description: Unique constraint + covering indexes for reports
-- Date: 09-05-2026
-- ============================================================
-- UP

-- Unique key: uk_bill_item_emp
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_emp_incentive_log' AND INDEX_NAME = 'uk_bill_item_emp');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `ret_emp_incentive_log` ADD UNIQUE KEY `uk_bill_item_emp` (`bill_id`, `bill_det_id`, `id_employee`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index: idx_date_branch_paid
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_emp_incentive_log' AND INDEX_NAME = 'idx_date_branch_paid');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `ret_emp_incentive_log` ADD KEY `idx_date_branch_paid` (`bill_date`, `id_branch`, `is_paid`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index: idx_report_filter
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_emp_incentive_log' AND INDEX_NAME = 'idx_report_filter');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `ret_emp_incentive_log` ADD KEY `idx_report_filter` (`id_branch`, `id_employee`, `bill_date`, `is_paid`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
