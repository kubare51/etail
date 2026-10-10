-- ============================================================================
-- Migration: Offer-Based Discount Module — Usage Audit Log
-- Date: 2026-07-16
-- Module: OFFER-003
-- Description: Creates ret_offer_audit_log, which records every time an
-- offer discount is actually persisted against an estimation or a bill.
-- This is a HARD PRE-REQUISITE for enabling the offer feature: the
-- estimation/billing save code logs usage against this table whenever an
-- offer is applied. To avoid coupling a missing/mismatched audit table to
-- the core sale transaction, the application-side logging call is wrapped
-- to be non-fatal (see ret_estimation_model.php / ret_billing_model.php),
-- but this migration should still be applied before the feature goes live
-- on any client so the audit trail is actually captured.
--
-- Columns disc_metal_amt / disc_mc_amt / disc_va_amt / disc_diamond_amt /
-- disc_taxable_amt (per-component breakdown) and msv_override /
-- override_otp_id / override_approved_by (OTP-override trail) are not
-- populated by the initial application code — only id_offer,
-- reference_type/id/no, total_discount_amt, applied_by/at, and id_branch
-- are written. Wiring up the rest is follow-up work.
-- Safe: CREATE TABLE IF NOT EXISTS.
-- ============================================================================

-- UP

CREATE TABLE IF NOT EXISTS `ret_offer_audit_log` (
    `id_log`               INT(11) NOT NULL AUTO_INCREMENT,
    `id_offer`              INT(11) NOT NULL COMMENT 'FK to ret_offer_master.id_offer',
    `reference_type`        ENUM('bill','estimation') NOT NULL COMMENT 'Which flow applied the offer',
    `reference_id`          INT(11) NOT NULL COMMENT 'ret_billing.bill_id or ret_estimation.esti_id',
    `reference_no`          VARCHAR(50) DEFAULT NULL COMMENT 'ret_billing.bill_no or ret_estimation.esti_no',
    `disc_metal_amt`        DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Not yet populated -- component breakdown unavailable at save time',
    `disc_mc_amt`           DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Not yet populated -- component breakdown unavailable at save time',
    `disc_va_amt`           DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Not yet populated -- component breakdown unavailable at save time',
    `disc_diamond_amt`      DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Not yet populated -- diamond discount has no computation path for catalog/custom items',
    `disc_taxable_amt`      DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Not yet populated -- component breakdown unavailable at save time',
    `total_discount_amt`    DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Final, post chit-scaling offer_disc_amt actually persisted for this offer on this reference',
    `msv_override`          TINYINT(1) DEFAULT 0 COMMENT 'Not yet populated -- requires threading the OTP-override flag through to this layer',
    `override_otp_id`       INT(11) DEFAULT NULL COMMENT 'Not yet populated',
    `override_approved_by`  INT(11) DEFAULT NULL COMMENT 'Not yet populated',
    `applied_by`            INT(11) DEFAULT NULL COMMENT 'employee/user id who saved the transaction',
    `applied_at`            DATETIME DEFAULT CURRENT_TIMESTAMP,
    `id_branch`             INT(11) DEFAULT NULL,
    PRIMARY KEY (`id_log`),
    KEY `idx_offer` (`id_offer`),
    KEY `idx_ref` (`reference_type`, `reference_id`),
    KEY `idx_date` (`applied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DOWN
-- DROP TABLE IF EXISTS `ret_offer_audit_log`;
