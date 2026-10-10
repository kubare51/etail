-- Migration: Add embedded round-off and fix TCS mapping in supplier ledger views
-- Date: 11-07-2026
-- Issue: Supplier bill final total does not match Supplier Ledger Report due to missing round-off and incorrect TCS trans_type
-- Fix: Embed round-off values directly into first item of PO and PO Returns inside ret_view_supplier_ledger view; Correct TCS trans_type to Credit (1) in both ret_view_supplier_ledger and ret_view_smith_combined_ledger views.
-- UP
DROP VIEW IF EXISTS ret_view_supplier_ledger_2026_07_11;
CREATE VIEW ret_view_supplier_ledger_2026_07_11 AS SELECT * FROM ret_view_supplier_ledger;
DROP VIEW IF EXISTS ret_view_smith_combined_ledger_2026_07_11;
CREATE VIEW ret_view_smith_combined_ledger_2026_07_11 AS SELECT * FROM ret_view_smith_combined_ledger;
-- View definitions will be fully re-applied by run-migrations.sh via database/views/ret_view_supplier_ledger.sql and ret_view_smith_combined_ledger.sql
