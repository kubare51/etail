-- Migration: Seed Common PO settings into `ret_settings`
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-02
-- Safe: INSERT IGNORE against the UNIQUE key on `name` - existing client values
--       are never overwritten.
--
-- Config-driven per CLAUDE.md. Defaults chosen to match CPO-01 sign-off:
--   D2 -> tolerance default 0.00 (strict: any variance needs approver confirmation)
--   D1 -> advance on Unfixed PO allowed (value 1)

-- UP
INSERT IGNORE INTO `ret_settings` (`name`, `value`, `description`, `created_on`)
VALUES
  ('common_po_enabled', '0',
   'Enable Common PO (bullion advance rate fixing) on the Purchase Order screen. 0-Disabled, 1-Enabled. Default off so existing clients are unaffected until switched on.',
   NOW()),

  ('common_po_grn_tolerance_percent', '0.00',
   'Allowed +/- variance between Common PO committed weight and GRN received weight, as a percentage. Within tolerance the GRN saves silently; outside it, approver confirmation is required. 0.00 = strict.',
   NOW()),

  ('common_po_advance_on_unfixed', '1',
   'Allow an advance payment against an Unfixed-rate Common PO with a manually entered amount. 0-Block, 1-Allow. (CPO-01 decision D1)',
   NOW()),

  ('common_po_advance_exceed_block', '1',
   'Block an advance payment that exceeds the Fixed-rate Common PO value. 0-Warn only, 1-Block. Does not apply to Unfixed POs, which have no PO value to check against.',
   NOW());

-- DOWN
-- DELETE FROM `ret_settings` WHERE `name` IN (
--   'common_po_enabled',
--   'common_po_grn_tolerance_percent',
--   'common_po_advance_on_unfixed',
--   'common_po_advance_exceed_block'
-- );
