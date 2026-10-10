-- Migration: Common PO menu entries and access rows
-- Date: 08-08-2026
-- Ticket: CPO-19
--
-- Three things:
--
-- 1. FIX to 20260807_000001. That migration created the rate-override capability as a
--    menu row with `active = 0`, reasoning that a capability is not a screen and
--    should not appear in the navigation. That reasoning was wrong in one respect:
--    the Settings > Profile Access grid is built by admin_settings_model::PermissionDB
--    with `Where m.active = 1 And m.id_menu > 1`, so an inactive row never reaches the
--    grid and the permission could never be granted to anybody. It is switched to
--    active = 1 here, and admin_ret_purchase/common_po_rate_override now resolves to a
--    real page describing the capability, so the entry is not a dead link for the
--    profiles that hold it.
--
-- 2. Menu entry for the Common PO Status Report (CPO-18) under Purchase Report.
--
-- 3. Access rows for both, one per profile. The application writes a row per profile
--    per menu (0/0/0/0 when not granted) rather than relying on absent rows, so these
--    follow the same shape. Only the report is granted, and only to profile 1.
--    The rate override is granted to NOBODY - BRS section 10 puts the default at
--    restricted, and _can_override_common_po_rate() treats both an absent row and a
--    zeroed row as a denial.
--
-- The Common PO Trace screen (CPO-17) deliberately gets NO menu row. It is a
-- per-record drill-down reached from the Purchase Order List and the Common PO Status
-- Report, never from navigation, so it shares the report's access entry.
--
-- Parents are resolved by link rather than hard-coded id, because menu ids differ
-- between client deployments.
--
-- Idempotent: re-running changes nothing.

-- UP

-- 1. Make the rate-override capability grantable ------------------------------
UPDATE menu SET active = 1
 WHERE link = 'admin_ret_purchase/common_po_rate_override' AND active <> 1;

-- 2. Common PO Status Report menu ---------------------------------------------
-- Parent resolved from an existing Purchase Report child.
SET @rep_parent = (SELECT parent FROM menu
                    WHERE link = 'admin_ret_purchase/purchase/order_status' LIMIT 1);

SET @rep_exists = (SELECT COUNT(*) FROM menu
                    WHERE link = 'admin_ret_purchase/common_po_report/list');

INSERT INTO menu (label, link, parent, sort, icon, active)
SELECT 'Common PO Report', 'admin_ret_purchase/common_po_report/list', @rep_parent, 30,
       'fa fa-circle-o', 1
  FROM DUAL
 WHERE @rep_exists = 0 AND @rep_parent IS NOT NULL;

-- 3. Access rows ---------------------------------------------------------------
SET @m_report   = (SELECT id_menu FROM menu
                    WHERE link = 'admin_ret_purchase/common_po_report/list' LIMIT 1);
SET @m_override = (SELECT id_menu FROM menu
                    WHERE link = 'admin_ret_purchase/common_po_rate_override' LIMIT 1);

-- One zeroed row per profile for each menu, skipping any that already exist.
INSERT INTO access (id_profile, id_menu, `view`, `add`, `edit`, `delete`)
SELECT p.id_profile, @m_report, 0, 0, 0, 0
  FROM profile p
 WHERE @m_report IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM access a
                    WHERE a.id_profile = p.id_profile AND a.id_menu = @m_report);

INSERT INTO access (id_profile, id_menu, `view`, `add`, `edit`, `delete`)
SELECT p.id_profile, @m_override, 0, 0, 0, 0
  FROM profile p
 WHERE @m_override IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM access a
                    WHERE a.id_profile = p.id_profile AND a.id_menu = @m_override);

-- 4. Grant the report to profile 1 so it is reachable straight after deployment.
--    Every other profile is granted through Settings > Profile Access.
UPDATE access SET `view` = 1, `add` = 1, `edit` = 1, `delete` = 1
 WHERE id_menu = @m_report AND id_profile = 1;

-- NOTE: the report menu is active regardless of the common_po_enabled setting. The
-- menu system has no hook for feature flags, and the screen is harmless when the
-- feature is off - it simply lists no Common POs. Deactivate the row by hand if a
-- client should not see it at all.

-- DOWN
-- UPDATE menu SET active = 0 WHERE link = 'admin_ret_purchase/common_po_rate_override';
-- DELETE FROM access WHERE id_menu = (SELECT id_menu FROM menu WHERE link = 'admin_ret_purchase/common_po_report/list');
-- DELETE FROM menu   WHERE link = 'admin_ret_purchase/common_po_report/list';
