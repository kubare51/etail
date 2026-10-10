-- ============================================================
-- Migration: Create opening_master table for Cash Opening Balance
-- Date: 2026-05-31
-- Feature: Cash Opening Balance (admin_opening_master/cash_opening)
-- Description: Creates the opening_master table used by the Cash Opening
--              Balance module. Supports multiple opening types (Cash,
--              Supplier, Customer, Karigar, Bank) via the `type` column.
-- ============================================================
-- UP

CREATE TABLE IF NOT EXISTS `opening_master` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `type` TINYINT(4) NOT NULL DEFAULT 1 COMMENT '1=Cash, 2=Supplier, 3=Customer, 4=Karigar, 5=Bank',
    `id_branch` INT(11) DEFAULT NULL COMMENT 'FK → branch.id_branch',
    `ref_id` VARCHAR(50) DEFAULT NULL COMMENT 'Auto-generated reference (e.g. CASH-001)',
    `opening_date` DATE DEFAULT NULL,
    `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `payment_mode` VARCHAR(50) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `status` TINYINT(4) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Deleted',
    `created_by` INT(11) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_by` INT(11) DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_type_status` (`type`, `status`),
    KEY `idx_branch` (`id_branch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- DOWN (rollback)
-- DROP TABLE IF EXISTS `opening_master`;
