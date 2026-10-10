-- ============================================================
-- Migration: Add Cash Opening Balance menu entry
-- Date: 2026-05-31
-- Feature: Cash Opening Balance (admin_opening_master/cash_opening)
-- Description: Inserts menu item under 'Opening Master' parent group
--              and grants full access to all existing profiles.
--              If 'Opening Master' parent doesn't exist, creates it first.
-- ============================================================
-- UP

-- Step 1: Create 'Opening Master' parent menu if it doesn't exist
INSERT INTO `menu` (`label`, `link`, `parent`, `sort`, `icon`, `active`)
SELECT 'Opening Master', '#', 1, 10, 'fa fa-folder-open', 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `menu` WHERE `label` = 'Opening Master' AND `link` = '#'
);

-- Step 2: Insert 'Cash Opening' child menu under 'Opening Master'
SET @om_parent = (SELECT id_menu FROM `menu` WHERE `label` = 'Opening Master' AND `link` = '#' LIMIT 1);

INSERT INTO `menu` (`label`, `link`, `parent`, `sort`, `icon`, `active`)
SELECT 'Cash Opening', 'admin_opening_master/cash_opening/list', @om_parent, 1, 'fa fa-money', 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `menu` WHERE `link` = 'admin_opening_master/cash_opening/list'
);

-- Step 3: Grant access to all profiles for both menu entries
-- Parent menu
SET @om_menu_id = (SELECT id_menu FROM `menu` WHERE `label` = 'Opening Master' AND `link` = '#' LIMIT 1);

INSERT INTO `access` (`id_profile`, `id_menu`, `view`, `add`, `edit`, `delete`)
SELECT p.id_profile, @om_menu_id, 1, 1, 1, 1
FROM `profile` p
WHERE @om_menu_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `access` a
    WHERE a.id_profile = p.id_profile AND a.id_menu = @om_menu_id
);

-- Child menu
SET @cash_menu_id = (SELECT id_menu FROM `menu` WHERE `link` = 'admin_opening_master/cash_opening/list' LIMIT 1);

INSERT INTO `access` (`id_profile`, `id_menu`, `view`, `add`, `edit`, `delete`)
SELECT p.id_profile, @cash_menu_id, 1, 1, 1, 1
FROM `profile` p
WHERE @cash_menu_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `access` a
    WHERE a.id_profile = p.id_profile AND a.id_menu = @cash_menu_id
);

-- DOWN (rollback)
-- DELETE FROM `access` WHERE `id_menu` = (SELECT id_menu FROM `menu` WHERE `link` = 'admin_opening_master/cash_opening/list' LIMIT 1);
-- DELETE FROM `menu` WHERE `link` = 'admin_opening_master/cash_opening/list';
-- DELETE FROM `access` WHERE `id_menu` = (SELECT id_menu FROM `menu` WHERE `label` = 'Opening Master' AND `link` = '#' LIMIT 1);
-- DELETE FROM `menu` WHERE `label` = 'Opening Master' AND `link` = '#';
