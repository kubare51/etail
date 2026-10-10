-- Migration: Add KYC integration type, rule logic columns to kyc_settings and logic column to kyc_rules
-- Date: 05-06-2026
-- Purpose: Support KYC integration type selection and rule logic configuration (All Required vs At Least One)
-- Safe: Each column checked via INFORMATION_SCHEMA before adding

-- UP

-- kyc_settings.kyc_integration_type
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kyc_settings' AND COLUMN_NAME = 'kyc_integration_type');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE kyc_settings ADD COLUMN kyc_integration_type TINYINT(1) DEFAULT 0 AFTER kyc_mode', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- kyc_settings.kyc_scheme_rule_logic
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kyc_settings' AND COLUMN_NAME = 'kyc_scheme_rule_logic');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE kyc_settings ADD COLUMN kyc_scheme_rule_logic TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- kyc_settings.kyc_customer_rule_logic
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kyc_settings' AND COLUMN_NAME = 'kyc_customer_rule_logic');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE kyc_settings ADD COLUMN kyc_customer_rule_logic TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- kyc_rules.logic
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kyc_rules' AND COLUMN_NAME = 'logic');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `kyc_rules` ADD `logic` TINYINT(1) NOT NULL DEFAULT ''0'' COMMENT ''0: All Required, 1: At Least One'' AFTER `amount`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
