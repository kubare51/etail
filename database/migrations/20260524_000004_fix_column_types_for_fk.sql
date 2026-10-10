-- Migration: Fix column type mismatches to INT UNSIGNED for FK compatibility
-- Date: 24-05-2026
-- Safe: MODIFY COLUMN — non-destructive type widening from INT/SMALLINT to INT UNSIGNED
-- Source: db_pk_fk_relationships_corrected.sql (Section 2)
-- Note: NULL preservation applied where data has significant NULL counts

-- UP

-- === Address tables — widen to INT UNSIGNED for FK compat ===
ALTER TABLE `city` MODIFY COLUMN `id_city` INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `state` MODIFY COLUMN `id_state` INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `country` MODIFY COLUMN `id_country` INT UNSIGNED NOT NULL AUTO_INCREMENT;

-- === payment — 20 NULLs in id_employee, 30 NULLs in id_branch → preserve NULL ===
ALTER TABLE `payment` MODIFY COLUMN `id_employee` INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `payment` MODIFY COLUMN `id_branch` INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `payment` MODIFY COLUMN `payment_status` INT UNSIGNED NOT NULL DEFAULT 0;

-- === ret_billing — 29 NULLs in bill_cus_id (walk-in), 1139 NULLs in metal_type (36%) ===
ALTER TABLE `ret_billing` MODIFY COLUMN `bill_cus_id` INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `ret_billing` MODIFY COLUMN `created_by` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `ret_billing` MODIFY COLUMN `metal_type` INT UNSIGNED NULL DEFAULT NULL;

-- === ret_bill_details — 731 NULLs in item_emp_id ===
ALTER TABLE `ret_bill_details` MODIFY COLUMN `item_emp_id` INT UNSIGNED NULL DEFAULT NULL;

-- === ret_taging — type widening for FK refs ===
ALTER TABLE `ret_taging` MODIFY COLUMN `purity` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `ret_taging` MODIFY COLUMN `current_branch` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `ret_taging` MODIFY COLUMN `created_by` INT UNSIGNED NOT NULL DEFAULT 0;

-- === ret_product_master — 9 NULLs in metal_type → preserve NULL ===
ALTER TABLE `ret_product_master` MODIFY COLUMN `metal_type` INT UNSIGNED NULL DEFAULT NULL;

-- === ret_category ===
ALTER TABLE `ret_category` MODIFY COLUMN `id_metal` INT UNSIGNED NOT NULL DEFAULT 0;

-- === ret_estimation ===
ALTER TABLE `ret_estimation` MODIFY COLUMN `created_by` INT UNSIGNED NOT NULL DEFAULT 0;

-- === ret_purchase_order ===
ALTER TABLE `ret_purchase_order` MODIFY COLUMN `created_by` INT UNSIGNED NOT NULL DEFAULT 0;

-- === ret_branch_transfer ===
ALTER TABLE `ret_branch_transfer` MODIFY COLUMN `create_by` INT UNSIGNED NOT NULL DEFAULT 0;

-- === ret_stock_issue — 26 NULLs in id_customer → preserve NULL ===
ALTER TABLE `ret_stock_issue` MODIFY COLUMN `id_customer` INT UNSIGNED NULL DEFAULT NULL;

-- === ret_issue_receipt — 15 NULLs in id_branch → preserve NULL ===
ALTER TABLE `ret_issue_receipt` MODIFY COLUMN `id_branch` INT UNSIGNED NULL DEFAULT NULL;

-- DOWN
-- ALTER TABLE `city` MODIFY COLUMN `id_city` INT NOT NULL AUTO_INCREMENT;
-- ALTER TABLE `state` MODIFY COLUMN `id_state` INT NOT NULL AUTO_INCREMENT;
-- ALTER TABLE `country` MODIFY COLUMN `id_country` INT NOT NULL AUTO_INCREMENT;
-- ALTER TABLE `payment` MODIFY COLUMN `id_employee` INT(11) DEFAULT NULL;
-- ALTER TABLE `payment` MODIFY COLUMN `id_branch` INT(11) DEFAULT NULL;
-- ALTER TABLE `payment` MODIFY COLUMN `payment_status` INT(11) NOT NULL DEFAULT 0;
-- ALTER TABLE `ret_billing` MODIFY COLUMN `bill_cus_id` INT(11) DEFAULT NULL;
-- ALTER TABLE `ret_billing` MODIFY COLUMN `created_by` INT(11) NOT NULL DEFAULT 0;
-- ALTER TABLE `ret_billing` MODIFY COLUMN `metal_type` INT(11) DEFAULT NULL;
-- ALTER TABLE `ret_bill_details` MODIFY COLUMN `item_emp_id` INT(11) DEFAULT NULL;
-- ALTER TABLE `ret_taging` MODIFY COLUMN `purity` INT(11) NOT NULL DEFAULT 0;
-- ALTER TABLE `ret_taging` MODIFY COLUMN `current_branch` INT(11) NOT NULL DEFAULT 0;
-- ALTER TABLE `ret_taging` MODIFY COLUMN `created_by` INT(11) NOT NULL DEFAULT 0;
-- ALTER TABLE `ret_product_master` MODIFY COLUMN `metal_type` INT(11) DEFAULT NULL;
-- ALTER TABLE `ret_category` MODIFY COLUMN `id_metal` INT(11) NOT NULL DEFAULT 0;
-- ALTER TABLE `ret_estimation` MODIFY COLUMN `created_by` INT(11) NOT NULL DEFAULT 0;
-- ALTER TABLE `ret_purchase_order` MODIFY COLUMN `created_by` INT(11) NOT NULL DEFAULT 0;
-- ALTER TABLE `ret_branch_transfer` MODIFY COLUMN `create_by` INT(11) NOT NULL DEFAULT 0;
-- ALTER TABLE `ret_stock_issue` MODIFY COLUMN `id_customer` INT(11) DEFAULT NULL;
-- ALTER TABLE `ret_issue_receipt` MODIFY COLUMN `id_branch` INT(11) DEFAULT NULL;
