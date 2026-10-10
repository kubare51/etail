-- Migration: Add stone_markup_enabled setting to ret_settings
-- Description: Master switch for the Stone Weight Markup % feature (tagging/estimation/billing).
--              Default OFF (0) so existing client deployments are unaffected until explicitly enabled
--              via Settings > Retail Settings.

-- UP

INSERT IGNORE INTO `ret_settings` (`name`, `value`, `description`)
VALUES ('stone_markup_enabled', '0', 'Enable Stone Weight Markup % feature in Tagging/Estimation/Billing (0=Off,1=On)');
