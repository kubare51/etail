-- Migration: Record Common PO approval independently of order_status
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-08a
-- Safe: Each column checked via INFORMATION_SCHEMA before adding
--
-- WHY
-- Advance and billing eligibility keyed on customerorder.order_status = 11
-- (Approved by MD). That column is shared and rewritten by several unrelated flows:
--
--   10 / 11  MD approval          (ret_purchase_approval_model::md_approve_order)
--   5        delivery / GRN       (admin_ret_purchase_approval.php:837)
--   6        cancel
--   8        rejection
--
-- So the moment a PO was delivered it silently dropped out of the advance list, even
-- though it had been approved. This is the same class of defect as the bill-entry
-- dropdown filtering order_status <= 3, which hid every MD-approved PO from Supplier
-- Bill Entry: a shared status column asked to answer a question it does not own.
--
-- common_po_approved records the fact of approval once and is never rewritten by
-- another flow, so eligibility can be expressed as
--   common_po_approved = 1 AND common_po_status IN (...)
-- using only columns this feature owns.
--
-- The backfill at the end marks Common POs that are already approved (11) or have
-- moved past approval (5 delivered / 2 received / 3 billed / 4 closed), so existing
-- rows keep working. Cancelled (6) and rejected (8) are deliberately excluded.

-- UP

-- common_po_approved
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorder' AND COLUMN_NAME = 'common_po_approved');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorder` ADD `common_po_approved` tinyint(1) NOT NULL DEFAULT ''0'' COMMENT ''Common PO has been approved. 0-No, 1-Yes. Set once at approval and never rewritten by delivery/cancel, unlike order_status.'' AFTER `common_po_status`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- common_po_approved_on
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorder' AND COLUMN_NAME = 'common_po_approved_on');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorder` ADD `common_po_approved_on` datetime DEFAULT NULL COMMENT ''When the Common PO was approved'' AFTER `common_po_approved`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill: Common POs already approved or past approval.
UPDATE `customerorder`
SET `common_po_approved` = 1,
    `common_po_approved_on` = IFNULL(`common_po_approved_on`, `order_date`)
WHERE `is_common_po` = 1
  AND `common_po_approved` = 0
  AND `order_status` IN (11, 5, 2, 3, 4);

-- Index for the eligibility lookup (supplier + approved + lifecycle status)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorder' AND INDEX_NAME = 'idx_common_po_approved');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_common_po_approved` ON `customerorder` (`is_common_po`, `common_po_approved`, `common_po_status`, `id_karigar`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- DROP INDEX `idx_common_po_approved` ON `customerorder`;
-- ALTER TABLE `customerorder` DROP COLUMN `common_po_approved_on`;
-- ALTER TABLE `customerorder` DROP COLUMN `common_po_approved`;
