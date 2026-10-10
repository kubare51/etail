-- Migration: Tagging tolerance - support value-based tolerance alongside percentage
-- Author : viveklmx
-- Purpose: Rename the legacy tagging tolerance settings to a consistent, self-describing
--          naming (tag_tolerance_weight / tag_tolerance_pcs) and introduce a per-dimension
--          tolerance type (tag_tolerance_weight_type / tag_tolerance_pcs_type):
--            1 = Percentage, 2 = Value.
--          Defaults reproduce today's behavior exactly (weight = %, pieces = flat count),
--          so there is zero behavior change until an admin opts a client into Value mode.

-- UP

-- Rename the existing value rows (idempotent: UNIQUE name skips if already renamed)
UPDATE IGNORE `ret_settings` SET `name` = 'tag_tolerance_weight',
  `description` = 'Tagging weight tolerance amount (% of lot weight when tag_tolerance_weight_type=1, grams when =2)'
  WHERE `name` = 'weight_per';

UPDATE IGNORE `ret_settings` SET `name` = 'tag_tolerance_pcs',
  `description` = 'Tagging pieces tolerance amount (flat count when tag_tolerance_pcs_type=2, % of lot qty when =1)'
  WHERE `name` = 'allow_tag_pcs';

-- Seed the value rows for any client that never had the legacy rows
INSERT INTO `ret_settings` (`name`, `value`, `description`)
  SELECT 'tag_tolerance_weight', '1', 'Tagging weight tolerance amount (% of lot weight when tag_tolerance_weight_type=1, grams when =2)'
  FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM `ret_settings` WHERE `name` = 'tag_tolerance_weight');

INSERT INTO `ret_settings` (`name`, `value`, `description`)
  SELECT 'tag_tolerance_pcs', '1', 'Tagging pieces tolerance amount (flat count when tag_tolerance_pcs_type=2, % of lot qty when =1)'
  FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM `ret_settings` WHERE `name` = 'tag_tolerance_pcs');

-- Seed the new type rows with backward-compatible defaults:
--   weight defaults to Percentage (1) = today; pieces defaults to Value (2) = today
INSERT INTO `ret_settings` (`name`, `value`, `description`)
  SELECT 'tag_tolerance_weight_type', '1', 'Weight tolerance type: 1=Percentage, 2=Value'
  FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM `ret_settings` WHERE `name` = 'tag_tolerance_weight_type');

INSERT INTO `ret_settings` (`name`, `value`, `description`)
  SELECT 'tag_tolerance_pcs_type', '2', 'Pieces tolerance type: 1=Percentage, 2=Value'
  FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM `ret_settings` WHERE `name` = 'tag_tolerance_pcs_type');

-- DOWN
UPDATE IGNORE `ret_settings` SET `name` = 'weight_per',    `description` = 'In Percentage' WHERE `name` = 'tag_tolerance_weight';
UPDATE IGNORE `ret_settings` SET `name` = 'allow_tag_pcs', `description` = 'Allow Pcs to Add in Tag' WHERE `name` = 'tag_tolerance_pcs';
DELETE FROM `ret_settings` WHERE `name` IN ('tag_tolerance_weight_type', 'tag_tolerance_pcs_type');
