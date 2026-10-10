-- ============================================================
-- Migration: Add AUTO_INCREMENT to Design master's 5 child-table primary keys
-- Date: 2026-07-30
-- Feature: I-GMSJ-005 — Master Data Validation & Dependency Testing
-- Description: ret_design_purity, ret_design_karigars,
--              ret_design_other_materials, ret_design_sizes and
--              ret_design_stone each have a `NOT NULL` int PRIMARY KEY
--              (des_pur_id / des_kar_id / other_material_id / design_size_id
--              / des_stone_id) that was never marked AUTO_INCREMENT.
--              admin_ret_catalog.php's ret_design 'save' case never supplies
--              a value for these columns on insert (correctly expecting the
--              DB to generate one), so every insert falls back to the
--              column's implicit default of 0 — meaning only ONE row could
--              ever exist across ALL designs system-wide before every
--              subsequent purity/karigar/material/size/stone save hit
--              "Error 1062: Duplicate entry '0' for key 'PRIMARY'". This is
--              what QA saw when saving Design/Sub Design (not a missing
--              input-validation gap — the reported "blank input" trigger was
--              incidental to when they happened to test the second save).
--              Confirmed on dev: ret_design_purity/karigars/other_materials
--              each stuck at exactly 1 row; sizes/stone at 0.
-- ============================================================
-- UP

SET @db_name = DATABASE();

SET @col_extra = (
    SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ret_design_purity' AND COLUMN_NAME = 'des_pur_id'
);
SET @sql = IF(@col_extra NOT LIKE '%auto_increment%',
    'ALTER TABLE `ret_design_purity` MODIFY `des_pur_id` INT NOT NULL AUTO_INCREMENT',
    'SELECT ''ret_design_purity.des_pur_id already auto_increment, skipping'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_extra = (
    SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ret_design_karigars' AND COLUMN_NAME = 'des_kar_id'
);
SET @sql = IF(@col_extra NOT LIKE '%auto_increment%',
    'ALTER TABLE `ret_design_karigars` MODIFY `des_kar_id` INT NOT NULL AUTO_INCREMENT',
    'SELECT ''ret_design_karigars.des_kar_id already auto_increment, skipping'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_extra = (
    SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ret_design_other_materials' AND COLUMN_NAME = 'other_material_id'
);
SET @sql = IF(@col_extra NOT LIKE '%auto_increment%',
    'ALTER TABLE `ret_design_other_materials` MODIFY `other_material_id` INT NOT NULL AUTO_INCREMENT',
    'SELECT ''ret_design_other_materials.other_material_id already auto_increment, skipping'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_extra = (
    SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ret_design_sizes' AND COLUMN_NAME = 'design_size_id'
);
SET @sql = IF(@col_extra NOT LIKE '%auto_increment%',
    'ALTER TABLE `ret_design_sizes` MODIFY `design_size_id` INT NOT NULL AUTO_INCREMENT',
    'SELECT ''ret_design_sizes.design_size_id already auto_increment, skipping'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_extra = (
    SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ret_design_stone' AND COLUMN_NAME = 'des_stone_id'
);
SET @sql = IF(@col_extra NOT LIKE '%auto_increment%',
    'ALTER TABLE `ret_design_stone` MODIFY `des_stone_id` INT UNSIGNED NOT NULL AUTO_INCREMENT',
    'SELECT ''ret_design_stone.des_stone_id already auto_increment, skipping'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN (rollback)
-- ALTER TABLE `ret_design_purity` MODIFY `des_pur_id` INT NOT NULL;
-- ALTER TABLE `ret_design_karigars` MODIFY `des_kar_id` INT NOT NULL;
-- ALTER TABLE `ret_design_other_materials` MODIFY `other_material_id` INT NOT NULL;
-- ALTER TABLE `ret_design_sizes` MODIFY `design_size_id` INT NOT NULL;
-- ALTER TABLE `ret_design_stone` MODIFY `des_stone_id` INT UNSIGNED NOT NULL;
