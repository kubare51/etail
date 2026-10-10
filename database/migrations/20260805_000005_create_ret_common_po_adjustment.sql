-- Migration: Create table `ret_common_po_adjustment`
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-02
-- Safe: Uses IF NOT EXISTS
--
-- Audit trail for every advance-to-invoice adjustment (BRS section 5 step 4,
-- acceptance criteria 18 and 19).
--
-- Design decision (CPO-01 / D3): one row per (payment, bill) adjustment pair.
-- Amounts are NEVER netted in place — a partial advance spread across bills
-- produces multiple rows. This makes the CPO-15 cancel reversal exact: reverse
-- the rows, restore ret_po_payment.advance_adjusted, done. Netting in place
-- would make reversal unrecoverable.
--
-- Rows are soft-reversed (adjustment_status = 2), never deleted, so the audit
-- trail survives cancellation.

-- UP
CREATE TABLE IF NOT EXISTS `ret_common_po_adjustment` (
  `id_common_po_adjustment` int NOT NULL AUTO_INCREMENT,
  `common_po_ref` int NOT NULL COMMENT 'customerorder.id_customerorder',
  `pay_id` int NOT NULL COMMENT 'ret_po_payment.pay_id - the advance being adjusted',
  `po_id` int NOT NULL COMMENT 'ret_purchase_order.po_id - the bill it is adjusted against',
  `adjusted_amt` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'Amount adjusted in this row',
  `adjustment_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1-Live,2-Reversed',
  `created_on` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `reversed_on` datetime DEFAULT NULL,
  `reversed_by` int DEFAULT NULL,
  `reverse_reason` text,
  PRIMARY KEY (`id_common_po_adjustment`),
  KEY `common_po_ref` (`common_po_ref`),
  KEY `pay_id` (`pay_id`),
  KEY `po_id` (`po_id`),
  KEY `adjustment_status` (`adjustment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DOWN
-- DROP TABLE IF EXISTS `ret_common_po_adjustment`;
