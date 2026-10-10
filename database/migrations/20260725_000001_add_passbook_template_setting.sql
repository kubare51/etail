-- Migration: Add passbook_template_based setting to ret_settings
-- Controls whether chit passbook print (admin_manage/passbook_print F|B) uses the
-- print template engine (1) or the legacy hardcoded views (0). Default 0 = legacy.

INSERT INTO `ret_settings` (`name`, `value`)
SELECT 'passbook_template_based', '0'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `ret_settings` WHERE `name` = 'passbook_template_based'
);
