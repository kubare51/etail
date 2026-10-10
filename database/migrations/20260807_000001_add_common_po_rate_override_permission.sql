-- Migration: Add the Common PO rate-override permission
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-12
-- Safe: INSERT IGNORE style guards; no existing row is modified
--
-- BRS acceptance criterion 17: "Rate/amount override at Supplier Bill Entry is
-- possible only for users with the required permission."
-- BRS section 10: "Rate-override permission is configurable at role level; default
-- is restricted."
--
-- Implemented as a MENU row rather than a new permission table, so it is granted
-- through the Access screen that already exists (Settings > Profile Access) and
-- checked with the same admin_settings_model::get_access() every other screen uses.
-- No parallel permission mechanism to keep in step.
--
-- active = 0 deliberately: the row must be grantable in Access but must NOT appear
-- as a clickable item in the navigation menu — it is a capability, not a screen.
--
-- No `access` rows are created, so every profile starts with no override rights.
-- That satisfies "default is restricted" without an explicit deny list.

-- UP

SET @parent = (SELECT parent FROM menu WHERE link = 'admin_ret_purchase/purchase/list' LIMIT 1);
SET @exists = (SELECT COUNT(*) FROM menu WHERE link = 'admin_ret_purchase/common_po_rate_override');

INSERT INTO menu (label, link, parent, sort, icon, active)
SELECT 'Common PO Rate Override', 'admin_ret_purchase/common_po_rate_override', @parent, 99, 'fa fa-circle-o', 0
FROM DUAL WHERE @exists = 0;

-- DOWN
-- DELETE FROM access WHERE id_menu = (SELECT id_menu FROM menu WHERE link = 'admin_ret_purchase/common_po_rate_override');
-- DELETE FROM menu WHERE link = 'admin_ret_purchase/common_po_rate_override';
