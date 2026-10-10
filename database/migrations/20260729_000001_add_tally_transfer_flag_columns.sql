-- Migration: Add missing Tally transfer flag columns to `ret_billing` and `ret_grn_entry`
-- Fixes: "Error Number: 1054 Unknown column 'b.ispurtransfered' / 'b.issrtransfered'"
--        on the Purchase and Credit Note (Sales Return) Tally APIs, and the same
--        failure on `grn.istransfered` for the GRN source.
-- Why:   application/models/ret_tally_api_model.php has always gated those APIs on
--        these columns (getPurchasaeNewVoucherList, getSalesReturnNewVoucherList)
--        and stamps the matching *_guid / *_updated_on columns after a successful
--        push, but the columns were never in the base schema (dev_structure.sql).
--        admin_tally_transfer/reset_flag surfaces the same error.
-- Types: Modelled on the existing ret_billing.istransfered / tally_guid /
--        tally_updated_on definitions so the new columns match them exactly.
-- Safe:  Every column checked via INFORMATION_SCHEMA before adding.
--
-- WARNING for live client databases: the flag defaults to 0 = "not yet transferred",
-- so after this migration every historical purchase and sales-return bill becomes
-- eligible for the next Tally import. That is correct where the column never existed
-- (nothing was ever pushed through those APIs — the query was erroring), but if the
-- client transferred those vouchers by some other route, backfill before enabling
-- the import, e.g.
--     UPDATE ret_billing SET ispurtransfered = 1 WHERE bill_date < 'YYYY-MM-DD';

-- UP

-- 1. ret_billing.ispurtransfered — Purchase (Old Metal) API gate
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND COLUMN_NAME = 'ispurtransfered');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_billing` ADD COLUMN `ispurtransfered` INT(11) NOT NULL DEFAULT 0 COMMENT ''Purchase voucher sent to Tally? 0 - No, 1 - Yes'' AFTER `istransfered`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. ret_billing.issrtransfered — Credit Note (Sales Return) API gate
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND COLUMN_NAME = 'issrtransfered');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_billing` ADD COLUMN `issrtransfered` INT(11) NOT NULL DEFAULT 0 COMMENT ''Sales return voucher sent to Tally? 0 - No, 1 - Yes'' AFTER `ispurtransfered`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. ret_billing.purtally_guid — stamped after a successful purchase push
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND COLUMN_NAME = 'purtally_guid');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_billing` ADD COLUMN `purtally_guid` VARCHAR(100) NULL DEFAULT NULL AFTER `issrtransfered`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. ret_billing.purtally_updated_on
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND COLUMN_NAME = 'purtally_updated_on');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_billing` ADD COLUMN `purtally_updated_on` DATETIME NULL DEFAULT NULL AFTER `purtally_guid`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. ret_billing.srtally_guid — stamped after a successful sales return push
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND COLUMN_NAME = 'srtally_guid');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_billing` ADD COLUMN `srtally_guid` VARCHAR(100) NULL DEFAULT NULL AFTER `purtally_updated_on`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. ret_billing.srtally_updated_on
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND COLUMN_NAME = 'srtally_updated_on');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_billing` ADD COLUMN `srtally_updated_on` DATETIME NULL DEFAULT NULL AFTER `srtally_guid`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7. ret_grn_entry.istransfered — GRN source of the Purchase API
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_grn_entry' AND COLUMN_NAME = 'istransfered');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_grn_entry` ADD COLUMN `istransfered` INT(11) NOT NULL DEFAULT 0 COMMENT ''GRN entry sent to Tally? 0 - No, 1 - Yes''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 8. ret_grn_entry.tally_guid
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_grn_entry' AND COLUMN_NAME = 'tally_guid');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_grn_entry` ADD COLUMN `tally_guid` VARCHAR(100) NULL DEFAULT NULL AFTER `istransfered`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9. ret_grn_entry.tally_updated_on
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_grn_entry' AND COLUMN_NAME = 'tally_updated_on');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_grn_entry` ADD COLUMN `tally_updated_on` DATETIME NULL DEFAULT NULL AFTER `tally_guid`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN

ALTER TABLE `ret_billing`
    DROP COLUMN `ispurtransfered`,
    DROP COLUMN `issrtransfered`,
    DROP COLUMN `purtally_guid`,
    DROP COLUMN `purtally_updated_on`,
    DROP COLUMN `srtally_guid`,
    DROP COLUMN `srtally_updated_on`;

ALTER TABLE `ret_grn_entry`
    DROP COLUMN `istransfered`,
    DROP COLUMN `tally_guid`,
    DROP COLUMN `tally_updated_on`;
