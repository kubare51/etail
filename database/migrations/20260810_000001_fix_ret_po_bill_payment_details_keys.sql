-- Migration: Give `ret_po_bill_payment_details` a primary key, indexes and audit columns
-- Feature: Partial Supplier Payment & Settlement Management (BRS v1.0)
-- Ticket: SPS-01
--
-- The table was created by 20251222_000001 with NO primary key, NO auto-increment and
-- NO indexes, and the insert in admin_ret_purchase::supplier_po_payment never supplies
-- `pay_bill_id`. Every existing row therefore carries pay_bill_id = 0.
--
-- Today the table holds at most one row per bill per payment. Once partial payments are
-- allowed it holds one row per bill per *instalment*, so it grows without bound and is
-- read by every outstanding-balance query. It needs real keys.
--
-- Ordering matters below: existing rows are renumbered BEFORE the primary key is added,
-- otherwise the all-zero column collides on the first row. The renumber is ordered by
-- pay_id so the surrogate key follows chronological payment order.
--
-- Idempotent: guarded on the presence of the PRIMARY index, so a re-run is a no-op and
-- will not renumber rows a second time.

-- UP

SET @has_pk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'ret_po_bill_payment_details'
                  AND INDEX_NAME   = 'PRIMARY');

-- 1. Renumber the existing all-zero surrogate keys (only on the first run) ------
SET @rn = 0;
SET @sql = IF(@has_pk = 0,
    'UPDATE `ret_po_bill_payment_details` SET `pay_bill_id` = (@rn := @rn + 1) ORDER BY `pay_id`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Primary key + auto-increment ---------------------------------------------
-- ADD PRIMARY KEY is listed first: MySQL requires an AUTO_INCREMENT column to be
-- part of a key, and clauses inside one ALTER are applied in the order written.
SET @sql = IF(@has_pk = 0,
    'ALTER TABLE `ret_po_bill_payment_details` ADD PRIMARY KEY (`pay_bill_id`), MODIFY `pay_bill_id` int NOT NULL AUTO_INCREMENT',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Lookup indexes ------------------------------------------------------------
-- idx_bpd_pay_id  : reversing / reprinting one payment
-- idx_bpd_po_id   : the `paid` subquery in getPending_payment_po_bills_for_payment,
--                   which runs on every open of the Supplier Payment screen
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'ret_po_bill_payment_details'
               AND INDEX_NAME   = 'idx_bpd_pay_id');
SET @sql = IF(@idx = 0,
    'CREATE INDEX `idx_bpd_pay_id` ON `ret_po_bill_payment_details` (`pay_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'ret_po_bill_payment_details'
               AND INDEX_NAME   = 'idx_bpd_po_id');
SET @sql = IF(@idx = 0,
    'CREATE INDEX `idx_bpd_po_id` ON `ret_po_bill_payment_details` (`po_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Audit columns (AC #8 - reconciliation and supplier account verification) ---
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'ret_po_bill_payment_details'
               AND COLUMN_NAME  = 'created_on');
SET @sql = IF(@col = 0,
    'ALTER TABLE `ret_po_bill_payment_details` ADD `created_on` datetime DEFAULT NULL COMMENT ''When this allocation row was written''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'ret_po_bill_payment_details'
               AND COLUMN_NAME  = 'created_by');
SET @sql = IF(@col = 0,
    'ALTER TABLE `ret_po_bill_payment_details` ADD `created_by` int DEFAULT NULL COMMENT ''employee.id_employee who recorded the allocation''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `ret_po_bill_payment_details` DROP COLUMN `created_by`;
-- ALTER TABLE `ret_po_bill_payment_details` DROP COLUMN `created_on`;
-- DROP INDEX `idx_bpd_po_id` ON `ret_po_bill_payment_details`;
-- DROP INDEX `idx_bpd_pay_id` ON `ret_po_bill_payment_details`;
-- ALTER TABLE `ret_po_bill_payment_details` MODIFY `pay_bill_id` int NOT NULL, DROP PRIMARY KEY;
