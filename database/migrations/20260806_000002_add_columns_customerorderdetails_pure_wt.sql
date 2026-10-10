-- Migration: Add purity value and rate basis to Common PO lines
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-05b (rate basis correction)
-- Safe: Each column checked via INFORMATION_SCHEMA before adding
--
-- WHY
-- BRS 4.1 field 13 specifies "Taxable Amount = Net Weight x Rate per Gram". That
-- conflicts with both global bullion practice and this system's own behaviour:
--
--   Supplier Bill Entry computes  item_cost = pure_wt * rate_per_gram
--     (ret_purchase_order.js:20239), where
--     pure_wt = net_wt * (purity + wastage) / 100   (ret_purchase_order.js:17315)
--   The rate field is labelled "Rate (Per Gram / PureWt)" and its basis selector
--   gst_bill_rate_basis defaults to 0 = PureWt.
--   ret_karigar.karigar_calc_type = 2 ("Purchase Touch") is the same fine-weight rule.
--
-- Bullion is traded on FINE weight worldwide (LBMA/COMEX per ounce of fine gold,
-- IBJA/MCX per 10g of 995/999). Leaving the PO on net weight makes the PO and the
-- supplier bill raised against it disagree for any purity below 100%:
--
--   100.000 g @ Rs 12,000, purity 99.50
--     PO on net                = 12,00,000.00
--     Bill on pure (99.500 g)  = 11,94,000.00      -> Rs 6,000 permanent residue
--
-- The advance is raised on the PO figure and the invoice on the bill figure, so
-- BRS 5 step 11 ("ledger reflects a nullified balance") could never be satisfied.
--
-- NO pure-weight column is added: customerorderdetails.pure_wt already exists as
-- decimal(10,3) and carries exactly this meaning (the Order module writes it for its
-- own lines). A Common PO line and an order line never share a row, so there is no
-- collision — and a second fine-weight column would create two sources of truth.
--
-- purity_value and rate_basis are stored per line rather than assumed, so a PO
-- recalled months later reproduces its original arithmetic even if the supplier's
-- karigar_calc_type or the product defaults have changed since.

-- UP

-- purity_value (the numeric purity applied, so pure_wt can be re-derived and audited)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'purity_value');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `purity_value` decimal(10,4) NOT NULL DEFAULT ''0.0000'' COMMENT ''ret_purity.purity applied to this Common PO line when the PO was raised. pure_wt = net_wt * purity_value / 100'' AFTER `pure_wt`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- rate_basis
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'rate_basis');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `rate_basis` tinyint(1) NOT NULL DEFAULT ''0'' COMMENT ''Weight the rate is applied to. Mirrors gst_bill_rate_basis on Supplier Bill Entry: 0-PureWt (default), 1-GrossWt, 2-NetWt'' AFTER `purity_value`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `customerorderdetails` DROP COLUMN `rate_basis`;
-- ALTER TABLE `customerorderdetails` DROP COLUMN `purity_value`;
