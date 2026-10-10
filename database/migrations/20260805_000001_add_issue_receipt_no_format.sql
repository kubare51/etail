-- Issue & Receipt Voucher Number Format Settings
--
-- Display-only number formats for ret_issue_receipt vouchers, mirroring the
-- existing bill_no_format mechanism used for billing documents.
--
-- The stored ret_issue_receipt.bill_no serial is NOT changed by this feature --
-- the template below only controls how that serial is rendered on lists,
-- prints and reports.
--
-- voucher_mode : 1 = Issue  (matches ret_issue_receipt.type = 1)
--                2 = Receipt(matches ret_issue_receipt.type = 2)
-- voucher_type : ret_issue_receipt.issue_type   when voucher_mode = 1
--                ret_issue_receipt.receipt_type when voucher_mode = 2
--                0 = fallback row used by every sub-type without its own row
--
-- Supported tokens: @@short_code@@ @@branch_code@@ @@fin_year@@ @@bill_no@@
--                   @@hyphen@@ @@slash@@  (separators, any number of times)
--
-- Tokens are concatenated with no delimiter between them, matching the
-- chit_settings.custom_ReceiptDisplayFrmt convention, and are decoded with
-- explode('@@', ...) / preg_match_all. Separators are explicit, so the
-- shipped default
--   @@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@
-- renders as
--   VD26-27/ADV00042
--
-- @@fin_year@@ emits ret_financial_year.fin_year_code verbatim -- it is free
-- text (varchar 15) and commonly already contains a dash, as in '26-27'.
-- @@bill_no@@ emits ret_issue_receipt.bill_no exactly as stored; the serial is
-- zero-padded to 5 by bill_no_generate() and is not re-padded for display.

CREATE TABLE IF NOT EXISTS `issue_receipt_no_format` (
  `id_ir_no_format` INT(11) NOT NULL AUTO_INCREMENT,
  `voucher_mode`    TINYINT(1) NOT NULL COMMENT '1=Issue, 2=Receipt',
  `voucher_type`    TINYINT(2) NOT NULL COMMENT 'issue_type / receipt_type; 0 = fallback for all other sub-types',
  `no_format`       VARCHAR(200) NOT NULL,
  `created_on`      DATETIME NOT NULL,
  PRIMARY KEY (`id_ir_no_format`),
  UNIQUE KEY `uk_ir_no_format_mode_type` (`voucher_mode`, `voucher_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed the 7 coded voucher types + 1 fallback row per mode.
-- Idempotent: re-running leaves any format the client has since edited alone.
INSERT INTO `issue_receipt_no_format`
  (`voucher_mode`, `voucher_type`, `no_format`, `created_on`)
VALUES
  -- Receipt Vouchers                                                            renders as
  (2, 2, '@@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@', NOW()), -- VD26-27/ADV00042
  (2, 7, '@@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@', NOW()), -- VD26-27/ADT00042
  (2, 8, '@@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@', NOW()), -- VD26-27/PCR00042
  (2, 1, '@@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@', NOW()), -- VD26-27/CC00042
  (2, 0, '@@branch_code@@@@fin_year@@@@slash@@@@bill_no@@',               NOW()), -- VD26-27/00042  (Others)
  -- Issue Vouchers
  (1, 1, '@@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@', NOW()), -- VD26-27/PCI00042
  (1, 2, '@@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@', NOW()), -- VD26-27/CR00042
  (1, 3, '@@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@', NOW()), -- VD26-27/ADR00042
  (1, 5, '@@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@', NOW()), -- VD26-27/EXP00042
  (1, 6, '@@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@', NOW()), -- VD26-27/NJE00042
  (1, 0, '@@branch_code@@@@fin_year@@@@slash@@@@bill_no@@',               NOW())  -- VD26-27/00042  (Others)
ON DUPLICATE KEY UPDATE `id_ir_no_format` = `id_ir_no_format`;

-- Normalise any row still written in the earlier shape, where the separator was
-- baked into each token as a leading '-' ('-@@short_code@@-@@branch_code@@...')
-- instead of being an explicit @@hyphen@@ entry. The settings screen builds the
-- format from a token list, so a bare '-' would be dropped when the saved value
-- is parsed back into the chosen list.
--
-- Order matters: strip the leading separator FIRST, otherwise the old shape
-- gains a stray leading Hyphen(-). Both statements are idempotent -- '@@hyphen@@'
-- contains no bare dash, so a second run matches nothing.
UPDATE `issue_receipt_no_format` SET `no_format` = TRIM(LEADING '-' FROM `no_format`) WHERE `no_format` LIKE '-%';
UPDATE `issue_receipt_no_format` SET `no_format` = REPLACE(`no_format`, '-', '@@hyphen@@') WHERE `no_format` LIKE '%-%';

-- Move rows that still hold an EARLIER shipped default onto the current one.
-- Matched on the exact previous default, so a format the client has customised
-- is left untouched. Runs after the normalisation above, so a legacy row has
-- already been converted to the hyphen shape by the time it is matched here.
UPDATE `issue_receipt_no_format`
   SET `no_format` = '@@branch_code@@@@fin_year@@@@slash@@@@short_code@@@@bill_no@@'
 WHERE `no_format` = '@@short_code@@@@hyphen@@@@branch_code@@@@hyphen@@@@fin_year@@@@hyphen@@@@bill_no@@';

UPDATE `issue_receipt_no_format`
   SET `no_format` = '@@branch_code@@@@fin_year@@@@slash@@@@bill_no@@'
 WHERE `no_format` = '@@branch_code@@@@hyphen@@@@fin_year@@@@hyphen@@@@bill_no@@';
