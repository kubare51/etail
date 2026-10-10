-- Migration: Allow NULL on `ret_po_payment_detail`.`payee_name`
-- Feature: Partial Supplier Payment (BRS v1.0)
-- Ticket: SPS-18
--
-- SYMPTOM: saving a supplier payment by CASH (or net banking) failed with
--
--     {"status":false,"message":"Unable to proceed the requested process. ..."}
--
-- which is CodeIgniter's error_db.php - a database failure, not a validation refusal.
-- The underlying error is
--
--     ERROR 1364 (HY000): Field 'payee_name' doesn't have a default value
--
-- CAUSE: `payee_name` is varchar(50) NOT NULL with NO default. It is the name written
-- on a CHEQUE, so only the cheque branch of admin_ret_purchase::supplier_po_payment
-- supplies it. Every other payment mode omits the column entirely:
--
--     cash          insertData($cash_payment_data, ...)        - no payee_name
--     net banking   insertBatchData($netBankingPaymentData,...) - no payee_name
--     sales adj     insertBatchData($SalesPaymentData, ...)     - no payee_name
--
-- Under STRICT_TRANS_TABLES (the MySQL 8 default, and what this deployment runs) an
-- omitted NOT NULL column with no default is an error rather than a silent ''. So no
-- cash or net-banking supplier payment could be saved at all.
--
-- The cheque branch is affected too: it writes
--     'payee_name' => ($chq_pay['payee_name'] != '' ? $chq_pay['payee_name'] : NULL)
-- so a cheque entered without a payee sends an explicit NULL into a NOT NULL column,
-- which is ERROR 1048. That path is fixed by this migration as well.
--
-- FIX HERE RATHER THAN IN THE CALLERS: the NOT NULL is simply wrong. Three of the four
-- payment modes have no payee by nature, and there are eight separate insert sites
-- across admin_ret_purchase.php - several in code paths unrelated to this feature.
-- Widening the column fixes all of them at once, and widening a constraint cannot break
-- an existing row or an existing query.
--
-- SAFE FOR READERS: every consumer is already NULL-tolerant - ret_reports_model selects
-- the column raw, and print/cheque.php uses !empty() and falls back to the supplier
-- name. No query filters on payee_name = '', so rows written as NULL from now on are
-- not excluded from anything that used to match ''.

-- UP
SET @is_nullable = (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME   = 'ret_po_payment_detail'
                       AND COLUMN_NAME  = 'payee_name');

SET @sql = IF(@is_nullable = 'NO',
    'ALTER TABLE `ret_po_payment_detail` MODIFY `payee_name` varchar(50) DEFAULT NULL COMMENT ''Name on the cheque. NULL for cash / net banking / adjustment rows, which have no payee.''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- Restoring NOT NULL would re-break cash and net-banking payments. If it must be done,
-- backfill first:
--   UPDATE `ret_po_payment_detail` SET `payee_name` = '' WHERE `payee_name` IS NULL;
--   ALTER TABLE `ret_po_payment_detail` MODIFY `payee_name` varchar(50) NOT NULL;
