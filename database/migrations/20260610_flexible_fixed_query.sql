-- Migration: Add topup_slab_calc_type column to scheme table (idempotent)
-- Fixes: Flexible slab calculation support for Advance Schemes

-- 1. scheme.topup_slab_calc_type
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme' AND COLUMN_NAME = 'topup_slab_calc_type');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `scheme` ADD COLUMN `topup_slab_calc_type` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1=Fixed (booking-based), 2=Flexible (balance-based)''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
