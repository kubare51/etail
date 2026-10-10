-- Migration: Fix ledger views to exclude cancelled opening balance entries
-- Date: 27-05-2026
-- Issue: Cancelled opening balance entries (smith_company_op_balance.status = 2) were appearing
--        in supplier/smith ledger reports, corrupting the opening balance calculations
-- Fix: Added AND pay.status = 1 to all OPENING UNION blocks in 4 ledger views:
--   1. ret_view_supplier_ledger (smith_type 1,4 — amount + weight blocks)
--   2. ret_view_smith_ledger (smith_type 2 — amount + weight blocks)
--   3. ret_view_smith_combined_ledger (smith_type 1,4 + 3 — amount + weight blocks)
--   4. ret_view_supplier_approval_ledger (smith_type 3 — amount + weight blocks)
-- Also adds cancel_remark column to smith_company_op_balance for cancel workflow
--
-- NOTE: View definitions are now managed in database/views/*.sql (canonical files).
--       The migration runner applies them automatically after schema migrations.

-- UP

-- Add cancel_remark column for cancel workflow
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'smith_company_op_balance' AND COLUMN_NAME = 'cancel_remark');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `smith_company_op_balance` ADD COLUMN `cancel_remark` TEXT DEFAULT NULL AFTER `remarks`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Views are applied automatically from database/views/ by run-migrations.sh Phase 2.
-- Changed files:
--   database/views/ret_view_supplier_ledger.sql
--   database/views/ret_view_smith_ledger.sql
--   database/views/ret_view_smith_combined_ledger.sql
--   database/views/ret_view_supplier_approval_ledger.sql
