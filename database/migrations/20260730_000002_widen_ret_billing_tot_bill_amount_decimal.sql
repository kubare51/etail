-- Fix: `ret_billing.tot_bill_amount` was `decimal(10,0)` — silently truncated
-- every amount to whole rupees on save, dropping paise with no round-off
-- entry to account for it.
--
-- Tracked as VAL-041 (knowledge_brain/_SYSTEM/VALIDATION_GAPS.md) and
-- XMOD-029 (CROSS_MODULE_BUGS.md). Confirmed against local data: 30 Sales
-- Transfer (bill_type=13) bills where SUM(ret_bill_details.item_cost) has a
-- non-zero decimal part but the saved tot_bill_amount is rounded to an
-- integer (e.g. bill SR-26-SA-00002: actual 82561.26, stored as 82561).
--
-- This column is shared across every bill_type on ret_billing (Sales,
-- Purchase, Order Advance, Chit Pre-Close, etc.), not just Sales Transfer.
-- Widening it to DECIMAL(12,2) is backward-compatible: existing whole-rupee
-- values are unaffected, and other bill types that intentionally round to
-- the nearest rupee via a separate round_off_amt adjustment are unaffected
-- too — this only removes the truncation ceiling for bill types (like Sales
-- Transfer) that have no round-off step and were losing money silently.
--
-- Paired code fix: admin_ret_sales_transfer.php save handler for bill_type=13
-- now formats the amount with number_format(...,2,'.','') before saving,
-- matching what the bill_type=14 (return transfer) path already does.
--
-- Does NOT backfill already-saved bills whose amount was already rounded on
-- save — that data is gone (never stored), not recoverable from this table.
--
-- Idempotent: MODIFY to an already-matching column definition is a safe
-- no-op in MySQL.

-- UP
ALTER TABLE ret_billing MODIFY tot_bill_amount DECIMAL(12,2) NULL;

-- DOWN
ALTER TABLE ret_billing MODIFY tot_bill_amount DECIMAL(10,0) NULL;
