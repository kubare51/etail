-- Migration: Restrict partial weight processing for single-piece (1 Pc) items in QC Issue / QC Receipt
-- Purpose: Supplier Bill Entry items booked as 1 Pc could be issued/received with only part of
--          their weight (e.g. 1 Pc / 50 gms against a 1 Pc / 100 gms line), leaving an invalid
--          balance of 0 Pcs with residual weight that corrupts downstream lot/stock balances.
--          This adds a per-client toggle consumed by admin_ret_purchase_order_model::isSinglePcsRestrictionEnabled()
--          to gate the new server-side + client-side guard in QC Issue and QC Receipt.
--          Default ON (data-integrity fix); clients that deliberately split single-piece lines can set value=0.

-- UP
INSERT INTO `ret_settings` (`name`, `value`, `description`)
  SELECT 'restrict_partial_wt_single_pcs', '1',
    'Restrict partial weight processing for single-piece (1 Pc) items in QC Issue and QC Receipt. 1 = Enabled, 0 = Disabled'
  FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM `ret_settings` WHERE `name` = 'restrict_partial_wt_single_pcs');

-- DOWN
DELETE FROM `ret_settings` WHERE `name` = 'restrict_partial_wt_single_pcs';
