-- UP
INSERT INTO `ret_settings` (`name`, `value`, `description`) VALUES
('enable_emp_disc_limit', '0', 'Enable staff discount limit check (1=enforce, 0=bypass)'),
('enable_mc_va_disc_limit', '0', 'Enable MC/VA discount limit check (1=enforce, 0=bypass)'),
('enable_disc_blw_metal_rate', '0', 'Enable disc-below-metal-rate check (1=enforce, 0=bypass)')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- DOWN
DELETE FROM `ret_settings` WHERE `name` IN ('enable_emp_disc_limit', 'enable_mc_va_disc_limit', 'enable_disc_blw_metal_rate');
