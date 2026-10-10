-- Single Active Session Login Restriction
-- One row per employee = the only session allowed to be active.
-- REQUIRED: run with a DB account that has CREATE privilege. The feature
-- stays fully disabled (and the app unaffected) until this migration and
-- 20260722_000002_add_columns_chit_settings.sql have both been applied.

CREATE TABLE IF NOT EXISTS `active_session` (
  `id_active_session` INT(11) NOT NULL AUTO_INCREMENT,
  `id_employee` INT(11) NOT NULL,
  `session_token` VARCHAR(64) NOT NULL,
  `login_on` DATETIME NOT NULL,
  `last_activity` DATETIME NOT NULL,
  `device_type` VARCHAR(20) DEFAULT NULL,
  `browser` VARCHAR(100) DEFAULT NULL,
  `operating_system` VARCHAR(100) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id_active_session`),
  UNIQUE KEY `uk_active_session_employee` (`id_employee`),
  KEY `idx_active_session_token` (`session_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
