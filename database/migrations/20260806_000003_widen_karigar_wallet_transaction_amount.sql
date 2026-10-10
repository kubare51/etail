-- Migration: Widen ret_karigar_wallet_transcation.amount to decimal(16,2)
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-09 (completes decision D7)
--
-- Missed by 20260806_000001, which swept ret_karigar_wallet but not its transaction
-- table. Both carry the same advance amount, so leaving this at decimal(10,2) would
-- cap the ledger line at Rs 9,99,99,999.99 while the balance column accepted more —
-- the transaction log would silently disagree with the balance it is meant to explain.
--
-- Widening a DECIMAL is non-destructive; NOT NULL and the default are preserved.

-- UP
ALTER TABLE `ret_karigar_wallet_transcation` MODIFY `amount` decimal(16,2) NOT NULL DEFAULT '0.00';

-- DOWN
-- Safe only while no stored value exceeds 99,999,999.99:
--   SELECT MAX(amount) FROM ret_karigar_wallet_transcation;
-- ALTER TABLE `ret_karigar_wallet_transcation` MODIFY `amount` decimal(10,2) NOT NULL DEFAULT '0.00';
