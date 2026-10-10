-- Migration: Add single-session exemption flag to `employee`
-- Feature: Single Active Session Login Restriction — employee exemption
-- Safe: Column checked via INFORMATION_SCHEMA before adding
--
-- DB-only control: there is intentionally NO UI for this flag anywhere in
-- the application. Exemptions are granted/revoked only by the support/dev
-- team via direct SQL (support request):
--   UPDATE employee SET single_session_exempt = 1 WHERE id_employee = <id>;  -- grant
--   UPDATE employee SET single_session_exempt = 0 WHERE id_employee = <id>;  -- revoke
--   SELECT id_employee, firstname, single_session_exempt FROM employee WHERE single_session_exempt = 1;  -- audit
-- The flag is read at login time; a change applies from the employee's next login.

-- UP

-- single_session_exempt
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee' AND COLUMN_NAME = 'single_session_exempt');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `employee` ADD `single_session_exempt` TINYINT(1) NOT NULL DEFAULT \'0\' COMMENT \'Exempt from single active session restriction? 0 - No, 1 - Yes\'', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
