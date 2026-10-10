-- =============================================================================
-- Error Monitor — Database Table
-- Creates the error_log table for storing PHP and JS errors
-- Run this on the target database before enabling the error monitor
-- =============================================================================

CREATE TABLE IF NOT EXISTS `error_log` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `error_hash` VARCHAR(32) NOT NULL COMMENT 'MD5 hash of type+file+line+message for deduplication',
    `error_type` ENUM('php_fatal','php_error','php_warning','php_exception','js_error','js_ajax','db_error') NOT NULL,
    `severity` ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
    `message` TEXT NOT NULL,
    `file` VARCHAR(500) DEFAULT NULL,
    `line` INT(11) DEFAULT NULL,
    `stack_trace` TEXT DEFAULT NULL,
    `url` VARCHAR(1000) DEFAULT NULL COMMENT 'Page URL where error occurred',
    `controller` VARCHAR(100) DEFAULT NULL,
    `method` VARCHAR(100) DEFAULT NULL,
    `user_id` INT(11) DEFAULT NULL COMMENT 'Logged-in user ID',
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `request_data` TEXT DEFAULT NULL COMMENT 'POST/GET data (sanitized)',
    `occurrence_count` INT(11) NOT NULL DEFAULT 1 COMMENT 'Times this exact error occurred',
    `first_seen` DATETIME NOT NULL,
    `last_seen` DATETIME NOT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `resolved_by` INT(11) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `is_acknowledged` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_error_hash` (`error_hash`),
    KEY `idx_type` (`error_type`),
    KEY `idx_severity` (`severity`),
    KEY `idx_last_seen` (`last_seen`),
    KEY `idx_resolved` (`resolved_at`),
    KEY `idx_controller` (`controller`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Application error tracking for PHP and JS errors';
