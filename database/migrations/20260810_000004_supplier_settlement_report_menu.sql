-- Migration: Menu and access rows for the Supplier Settlement Report
-- Feature: Partial Supplier Payment & Settlement Management (BRS v1.0)
-- Ticket: SPS-16
--
-- Adds one Purchase Report entry, admin_ret_purchase/supplier_settlement_report/list.
--
-- The parent is resolved by link rather than a hard-coded id, because menu ids differ
-- between client deployments. This mirrors 20260808_000001.
--
-- Access rows follow the convention this application already uses: a row per profile
-- per menu, zeroed when not granted, rather than relying on the absence of a row. Only
-- profile 1 is granted view - a settlement report exposes supplier balances, so it is
-- opened deliberately per client rather than switched on for everybody.
--
-- Idempotent: re-running changes nothing.

-- UP

-- 1. Menu entry ----------------------------------------------------------------
SET @rep_parent = (SELECT parent FROM menu
                    WHERE link = 'admin_ret_purchase/purchase/order_status' LIMIT 1);

SET @rep_exists = (SELECT COUNT(*) FROM menu
                    WHERE link = 'admin_ret_purchase/supplier_settlement_report/list');

INSERT INTO menu (label, link, parent, sort, icon, active)
SELECT 'Supplier Settlement Report', 'admin_ret_purchase/supplier_settlement_report/list',
       @rep_parent, 31, 'fa fa-circle-o', 1
  FROM DUAL
 WHERE @rep_exists = 0 AND @rep_parent IS NOT NULL;

-- 2. Access rows ---------------------------------------------------------------
SET @m_settle = (SELECT id_menu FROM menu
                  WHERE link = 'admin_ret_purchase/supplier_settlement_report/list' LIMIT 1);

INSERT INTO access (id_profile, id_menu, `view`, `add`, `edit`, `delete`)
SELECT p.id_profile, @m_settle, 0, 0, 0, 0
  FROM profile p
 WHERE @m_settle IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM access a
                    WHERE a.id_profile = p.id_profile AND a.id_menu = @m_settle);

UPDATE access SET `view` = 1
 WHERE id_menu = @m_settle AND id_profile = 1 AND `view` <> 1;

-- DOWN
-- DELETE FROM access WHERE id_menu = (SELECT id_menu FROM menu WHERE link = 'admin_ret_purchase/supplier_settlement_report/list');
-- DELETE FROM menu WHERE link = 'admin_ret_purchase/supplier_settlement_report/list';
