-- UP
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profile' AND COLUMN_NAME = 'allow_ir_cancel');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `profile` ADD COLUMN `allow_ir_cancel` TINYINT(1) NOT NULL DEFAULT ''0'' COMMENT ''1-Yes,0-No''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profile' AND COLUMN_NAME = 'previous_ir_cancel');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `profile` ADD COLUMN `previous_ir_cancel` TINYINT(1) NOT NULL DEFAULT ''0'' COMMENT ''1-Yes,0-No''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `profile` DROP COLUMN `allow_ir_cancel`;
-- ALTER TABLE `profile` DROP COLUMN `previous_ir_cancel`;
