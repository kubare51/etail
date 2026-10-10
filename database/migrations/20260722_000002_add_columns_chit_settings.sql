-- Migration: Add single-session login setting to `chit_settings`
-- Feature: Single Active Session Login Restriction (settings-driven, per client)
-- Safe: Column checked via INFORMATION_SCHEMA before adding
-- REQUIRED: run with a DB account that has ALTER privilege. The feature
-- stays fully disabled (and the app unaffected) until this migration and
-- 20260722_000001_create_active_session.sql have both been applied.

-- UP

-- single_session_login
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chit_settings' AND COLUMN_NAME = 'single_session_login');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `chit_settings` ADD `single_session_login` TINYINT(1) NOT NULL DEFAULT \'0\' COMMENT \'Allow only one active login session per user account? 0 - No, 1 - Yes\' AFTER `isOTPReqToLogin`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
