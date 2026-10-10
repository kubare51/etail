-- Migration: Add setting to toggle Sales Transfer Amount calculation basis
-- Date: 19-07-2026
-- Description: Tagged items, Per Gram calc type — Amount is currently Gross Wt * Rate.
--              This setting lets it be switched to Pure Wt * Rate instead.
-- Default: disabled (0) — preserves existing Gross Wt * Rate behavior.

-- UP
INSERT IGNORE INTO `ret_settings` (`name`, `value`, `description`) VALUES ('sales_transfer_amount_calc_purewt', '0', 'Sales Transfer (Tagged items, Per Gram) Amount calculation basis: 0-Gross Wt x Rate, 1-Pure Wt x Rate');

-- DOWN
-- DELETE FROM `ret_settings` WHERE `name` = 'sales_transfer_amount_calc_purewt';
