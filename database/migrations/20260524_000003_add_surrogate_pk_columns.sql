-- Migration: Add auto-increment PK columns to tables with non-INT primary identifiers
-- Date: 24-05-2026
-- Safe: ADD COLUMN — non-destructive; existing rows get sequential IDs
-- Source: db_pk_fk_relationships_corrected.sql (Section 1, new columns)

-- UP

-- === CustomerDetails — first col is 'S No' (varchar), needs surrogate PK ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'CustomerDetails' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `CustomerDetails` ADD COLUMN `id_customer_detail` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === cat_pro_des_subdes — first col is 'Category' (varchar), needs surrogate PK ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cat_pro_des_subdes' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `cat_pro_des_subdes` ADD COLUMN `id_cat_pro_des` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === mrptagitems — first col is 'MRP Tags' (varchar), needs surrogate PK ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mrptagitems' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `mrptagitems` ADD COLUMN `id_mrp` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === branch_tag_details — first col is 'Tag Number' (varchar), needs surrogate PK ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branch_tag_details' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `branch_tag_details` ADD COLUMN `id_branch_tag` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `CustomerDetails` DROP COLUMN `id_customer_detail`;
-- ALTER TABLE `cat_pro_des_subdes` DROP COLUMN `id_cat_pro_des`;
-- ALTER TABLE `mrptagitems` DROP COLUMN `id_mrp`;
-- ALTER TABLE `branch_tag_details` DROP COLUMN `id_branch_tag`;
