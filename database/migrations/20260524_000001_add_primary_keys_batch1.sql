-- Migration: Add primary keys to core, customer, payment, and order tables
-- Date: 24-05-2026
-- Safe: Each PK is guarded by INFORMATION_SCHEMA check — idempotent
-- Source: db_pk_fk_relationships_corrected.sql (Section 1, Batch 1)

-- UP

-- === notify_services ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notify_services' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `notify_services` ADD PRIMARY KEY (`id_noti_services`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === employee_settings ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_settings' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `employee_settings` ADD PRIMARY KEY (`id_emp_sett`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === dealer ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dealer' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `dealer` ADD PRIMARY KEY (`id_dealer`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === customer_associated ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_associated' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `customer_associated` ADD PRIMARY KEY (`id_cus_associated`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === customer_edit_log ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_edit_log' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `customer_edit_log` ADD PRIMARY KEY (`id_log`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === customer_feedback_master ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_feedback_master' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `customer_feedback_master` ADD PRIMARY KEY (`id_feedback`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === customer_feedback_response ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_feedback_response' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `customer_feedback_response` ADD PRIMARY KEY (`id_feedback_res`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === customer_reg ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_reg' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `customer_reg` ADD PRIMARY KEY (`id_customer_reg`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === tn_village (varchar PK) ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tn_village' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `tn_village` ADD PRIMARY KEY (`id_village`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === payment_old_metal ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_old_metal' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `payment_old_metal` ADD PRIMARY KEY (`id_old_pay`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === joborder ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'joborder' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `joborder` ADD PRIMARY KEY (`id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === joborder_partial_delivery ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'joborder_partial_delivery' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `joborder_partial_delivery` ADD PRIMARY KEY (`id_jopartial_delivery`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `notify_services` DROP PRIMARY KEY;
-- ALTER TABLE `employee_settings` DROP PRIMARY KEY;
-- ALTER TABLE `dealer` DROP PRIMARY KEY;
-- ALTER TABLE `customer_associated` DROP PRIMARY KEY;
-- ALTER TABLE `customer_edit_log` DROP PRIMARY KEY;
-- ALTER TABLE `customer_feedback_master` DROP PRIMARY KEY;
-- ALTER TABLE `customer_feedback_response` DROP PRIMARY KEY;
-- ALTER TABLE `customer_reg` DROP PRIMARY KEY;
-- ALTER TABLE `tn_village` DROP PRIMARY KEY;
-- ALTER TABLE `payment_old_metal` DROP PRIMARY KEY;
-- ALTER TABLE `joborder` DROP PRIMARY KEY;
-- ALTER TABLE `joborder_partial_delivery` DROP PRIMARY KEY;
