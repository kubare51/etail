-- Digi Gold & Digi Silver — Dashboard Menu entries
-- Adds the two dashboard menus so they appear under Settings > User Access > Dashboard Menu
-- and render as tabs on the admin dashboard. The report itself is code; this is the only
-- schema/data change required. Idempotent: safe to re-run (matches on label, no hardcoded ids).

INSERT INTO dashboard_menu (label, link, parent, sort, icon, active)
SELECT 'Digi Gold', 'digigold', 1, 10, 'fa-money', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM dashboard_menu WHERE label = 'Digi Gold');

INSERT INTO dashboard_menu (label, link, parent, sort, icon, active)
SELECT 'Digi Silver', 'digisilver', 1, 11, 'fa-money', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM dashboard_menu WHERE label = 'Digi Silver');

-- Note: after running this, an admin must tick "View" for the relevant profiles under
-- Settings > User Access > Dashboard Menu (writes to dashboard_access) for the tabs to show.
