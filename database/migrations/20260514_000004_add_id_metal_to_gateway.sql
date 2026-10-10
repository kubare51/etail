-- Migration: Add id_metal (Commodity) column to gateway table
-- Date: 14-05-2026
-- Branch: feature/commodity-based-gateway
-- Safe: ADD COLUMN — uses INFORMATION_SCHEMA check before adding

-- UP
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gateway' AND COLUMN_NAME = 'id_metal');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `gateway` ADD COLUMN `id_metal` INT(11) DEFAULT NULL COMMENT ''FK -> metal.id_metal (NULL = not commodity-specific)'' AFTER `description`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `gateway`
--   DROP COLUMN `id_metal`;
