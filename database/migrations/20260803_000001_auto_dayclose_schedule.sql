-- Migration: Auto Day Close Schedule
-- Feature: Makes the automatic day close time DB-driven (global default + per-branch
--          default + today-only override), replacing the old fixed-time crontab window.
--          Adds a cron run log so support can see whether/when the ticker actually ran.
-- Backs: Admin_ret_dayclose, Ret_dayclose_model, Admin_ret_services::autoDayClose()
-- Safe: column/table existence checked before every change; settings use INSERT IGNORE.

-- UP

-- 1. Repurpose the existing per-branch time column (written once at branch creation,
--    never read anywhere) as the per-branch DEFAULT auto day close time.
--    Captured BEFORE the ALTER so the one-time reset below only runs on first apply -
--    re-running must never wipe branch defaults an admin has since configured.
SET @dc_was_not_null = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_day_closing' AND COLUMN_NAME = 'day_close_time' AND IS_NULLABLE = 'NO');

SET @sql = IF(@dc_was_not_null = 1, 'ALTER TABLE `ret_day_closing` MODIFY `day_close_time` TIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Clear the legacy branch-creation clock times: they are meaningless as defaults and
-- would otherwise be read as "this branch closes at the minute it was created".
SET @sql = IF(@dc_was_not_null = 1, 'UPDATE `ret_day_closing` SET `day_close_time` = NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Today-only overrides. Keyed on (id_branch, override_date) where override_date is the
--    branch's CURRENT entry_date (business date) - so the row auto-expires the moment that
--    business day closes, with no cleanup job required.
CREATE TABLE IF NOT EXISTS `ret_dayclose_override` (
  `id_dayclose_override` INT(11) NOT NULL AUTO_INCREMENT,
  `id_branch`     INT(11) NOT NULL,
  `override_date` DATE NOT NULL COMMENT 'ret_day_closing.entry_date being extended',
  `close_time`    TIME NOT NULL,
  `remarks`       VARCHAR(255) DEFAULT NULL,
  `active`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT(11) DEFAULT NULL,
  `created_on` DATETIME DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  `updated_on` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_dayclose_override`),
  UNIQUE KEY `uq_branch_date` (`id_branch`,`override_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 3. Cron run log - every tick of the 30-minute ticker writes one row per branch, so the
--    "did the cron even run?" question has an answer.
CREATE TABLE IF NOT EXISTS `ret_dayclose_cron_log` (
  `id_dayclose_cron_log` INT(11) NOT NULL AUTO_INCREMENT,
  `run_at`               DATETIME NOT NULL,
  `id_branch`            INT(11) DEFAULT NULL,
  `entry_date`           DATE DEFAULT NULL,
  `effective_close_time` TIME DEFAULT NULL,
  `time_source`          VARCHAR(20) DEFAULT NULL COMMENT 'override | branch | global',
  `action`               VARCHAR(30) NOT NULL COMMENT 'closed | reopened_fallback | pending | already_closed | disabled | failed',
  `message`              VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id_dayclose_cron_log`),
  KEY `idx_run` (`run_at`),
  KEY `idx_branch_date` (`id_branch`,`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 4. Global settings. Rows MUST exist - Admin_settings_model::get_ret_settings() does
--    ->row()->value with no null guard, so a missing key fatals every caller.
--    INSERT IGNORE relies on the UNIQUE KEY on ret_settings.name.
INSERT IGNORE INTO `ret_settings` (`name`,`value`,`description`,`created_on`) VALUES
 ('auto_dayclose_time','19:30:00','Default automatic day close time, applies to all branches unless a branch has its own default or a today-only override (30-min slots only)',NOW()),
 ('auto_dayclose_enabled','1','1 = automatic day close ticker active, 0 = disabled',NOW()),
 ('auto_dayclose_unlock_fallback','02:30:00','If the resetDayClose morning cron has not unlocked is_day_closed by this time, the 30-min ticker does it instead and logs action=reopened_fallback',NOW());


