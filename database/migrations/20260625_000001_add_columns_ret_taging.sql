-- Migration: Add columns to `ret_taging`
-- Source: manual request
-- Safe: Column checked via INFORMATION_SCHEMA before adding

-- UP

-- calc_percentage
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND COLUMN_NAME = 'calc_percentage');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `ret_taging` ADD `calc_percentage` DECIMAL(10,2) NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
