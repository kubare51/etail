-- Migration: Widen purchase payment / ledger amount columns to decimal(16,2)
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-08 / decision D7
--
-- WHY
-- decimal(10,2) caps at 99,999,999.99 — roughly Rs 9.99 crore. That ceiling was never
-- reached by ornament purchases, but bullion clears it easily: 10 kg of gold at
-- Rs 15,000/g is Rs 15 crore. MySQL in STRICT mode rejects the INSERT; without strict
-- mode it silently clamps to the maximum, which would post a WRONG supplier advance
-- and corrupt the ledger with no error surfaced anywhere.
--
-- SAFETY
-- Widening a DECIMAL is non-destructive: every existing value remains exactly
-- representable, and no application code needs to change. Nullability, defaults and
-- the UNSIGNED attribute on pay_amt are preserved verbatim below — dropping UNSIGNED
-- would let a negative payment through, and dropping NOT NULL would let a payment
-- carry no amount at all.
--
-- NOTE: this rebuilds the tables. On a large production database run it in a
-- maintenance window; ret_po_payment and ret_purchase_order are the biggest of these.
--
-- SCOPE: only columns that carry a transaction VALUE.
--   - tcs_percent / tds_percent are percentages and stay decimal(10,2)
--   - total_payable_wt is a weight, not an amount, and is left alone
--     (it is also decimal(10,2) where every other weight column is (_,3) —
--      a pre-existing inconsistency, deliberately out of scope here)

-- UP

-- Supplier advance / cash ledger balance
ALTER TABLE `ret_karigar_wallet`     MODIFY `amount`            decimal(16,2) NOT NULL DEFAULT '0.00';

-- Payment header and its split rows
ALTER TABLE `ret_po_payment`         MODIFY `pay_amt`           decimal(16,2) unsigned NOT NULL;
ALTER TABLE `ret_po_payment_detail`  MODIFY `payment_amount`    decimal(16,2) NOT NULL DEFAULT '0.00';

-- Supplier bill money columns (advance is adjusted against these in CPO-13)
ALTER TABLE `ret_purchase_order`     MODIFY `tot_purchase_amt`  decimal(16,2) DEFAULT '0.00';
ALTER TABLE `ret_purchase_order`     MODIFY `total_payable_amt` decimal(16,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `ret_purchase_order`     MODIFY `po_othere_charges` decimal(16,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `ret_purchase_order`     MODIFY `po_discount`       decimal(16,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `ret_purchase_order`     MODIFY `tcs_tax_value`     decimal(16,2) DEFAULT '0.00';
ALTER TABLE `ret_purchase_order`     MODIFY `tds_tax_value`     decimal(16,2) DEFAULT '0.00';

-- DOWN
-- Only safe while no stored value exceeds 99,999,999.99. Verify first:
--   SELECT MAX(pay_amt) FROM ret_po_payment;
--   SELECT MAX(amount)  FROM ret_karigar_wallet;
--   SELECT MAX(total_payable_amt) FROM ret_purchase_order;
-- ALTER TABLE `ret_karigar_wallet`    MODIFY `amount`            decimal(10,2) NOT NULL DEFAULT '0.00';
-- ALTER TABLE `ret_po_payment`        MODIFY `pay_amt`           decimal(10,2) unsigned NOT NULL;
-- ALTER TABLE `ret_po_payment_detail` MODIFY `payment_amount`    decimal(10,2) NOT NULL DEFAULT '0.00';
-- ALTER TABLE `ret_purchase_order`    MODIFY `tot_purchase_amt`  decimal(10,2) DEFAULT '0.00';
-- ALTER TABLE `ret_purchase_order`    MODIFY `total_payable_amt` decimal(10,2) NOT NULL DEFAULT '0.00';
-- ALTER TABLE `ret_purchase_order`    MODIFY `po_othere_charges` decimal(10,2) NOT NULL DEFAULT '0.00';
-- ALTER TABLE `ret_purchase_order`    MODIFY `po_discount`       decimal(10,2) NOT NULL DEFAULT '0.00';
-- ALTER TABLE `ret_purchase_order`    MODIFY `tcs_tax_value`     decimal(10,2) DEFAULT '0.00';
-- ALTER TABLE `ret_purchase_order`    MODIFY `tds_tax_value`     decimal(10,2) DEFAULT '0.00';
