-- Migration: Add foreign key constraints across all validated relationships
-- Date: 24-05-2026
-- Safe: Each FK is guarded by INFORMATION_SCHEMA check — idempotent
-- Depends: 20260524_000001-000004 (PKs and type fixes must run first)
-- Source: db_pk_fk_relationships_corrected.sql (Section 3)
-- Note: 7 FKs skipped due to orphan data, 2 FKs skipped due to scheme_account
--       hitting MySQL's 64-key-per-table limit (see bottom comments)

-- UP
SET FOREIGN_KEY_CHECKS = 0;

-- ── CORE / EMPLOYEE ──────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee' AND CONSTRAINT_NAME = 'fk_emp_dept');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `employee` ADD CONSTRAINT `fk_emp_dept` FOREIGN KEY (`dept`) REFERENCES `department`(`id_dept`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee' AND CONSTRAINT_NAME = 'fk_emp_profile');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `employee` ADD CONSTRAINT `fk_emp_profile` FOREIGN KEY (`id_profile`) REFERENCES `profile`(`id_profile`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_devices' AND CONSTRAINT_NAME = 'fk_empdev_emp');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `employee_devices` ADD CONSTRAINT `fk_empdev_emp` FOREIGN KEY (`emp_id`) REFERENCES `employee`(`id_employee`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── CUSTOMER & ADDRESS ───────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer' AND CONSTRAINT_NAME = 'fk_cus_village');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `customer` ADD CONSTRAINT `fk_cus_village` FOREIGN KEY (`id_village`) REFERENCES `village`(`id_village`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'address' AND CONSTRAINT_NAME = 'fk_addr_customer');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `address` ADD CONSTRAINT `fk_addr_customer` FOREIGN KEY (`id_customer`) REFERENCES `customer`(`id_customer`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'address' AND CONSTRAINT_NAME = 'fk_addr_city');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `address` ADD CONSTRAINT `fk_addr_city` FOREIGN KEY (`id_city`) REFERENCES `city`(`id_city`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'address' AND CONSTRAINT_NAME = 'fk_addr_state');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `address` ADD CONSTRAINT `fk_addr_state` FOREIGN KEY (`id_state`) REFERENCES `state`(`id_state`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'address' AND CONSTRAINT_NAME = 'fk_addr_country');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `address` ADD CONSTRAINT `fk_addr_country` FOREIGN KEY (`id_country`) REFERENCES `country`(`id_country`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── SCHEME / CHIT ────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_account' AND CONSTRAINT_NAME = 'fk_sa_customer');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `scheme_account` ADD CONSTRAINT `fk_sa_customer` FOREIGN KEY (`id_customer`) REFERENCES `customer`(`id_customer`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_account' AND CONSTRAINT_NAME = 'fk_sa_scheme');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `scheme_account` ADD CONSTRAINT `fk_sa_scheme` FOREIGN KEY (`id_scheme`) REFERENCES `scheme`(`id_scheme`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_account' AND CONSTRAINT_NAME = 'fk_sa_branch');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `scheme_account` ADD CONSTRAINT `fk_sa_branch` FOREIGN KEY (`id_branch`) REFERENCES `branch`(`id_branch`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- SKIPPED: fk_sa_agent — `scheme_account` is at MySQL's 64-key-per-table limit
-- (ERROR 1069 on 2026-07-29 prod deploy, release 20260729_115431). id_agent has
-- no existing covering index, so the FK requires a brand-new key, pushing the
-- table to 65. This aborted the migration file on every deploy, blocking all
-- pending migrations behind it (mysql CLI stops on first error, no --force).
-- Needs an index audit on scheme_account (drop unused/duplicate indexes) before
-- this and fk_sa_emp_closed below can be re-enabled.
-- SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_account' AND CONSTRAINT_NAME = 'fk_sa_agent');
-- SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `scheme_account` ADD CONSTRAINT `fk_sa_agent` FOREIGN KEY (`id_agent`) REFERENCES `agent`(`id_agent`)', 'SELECT 1');
-- PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- SKIPPED: fk_sa_emp_closed — same reason as fk_sa_agent above (scheme_account
-- at the 64-key ceiling). Never actually attempted in the failed run since the
-- migration aborted on fk_sa_agent first, but would fail identically.
-- SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_account' AND CONSTRAINT_NAME = 'fk_sa_emp_closed');
-- SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `scheme_account` ADD CONSTRAINT `fk_sa_emp_closed` FOREIGN KEY (`employee_closed`) REFERENCES `employee`(`id_employee`)', 'SELECT 1');
-- PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_branch' AND CONSTRAINT_NAME = 'fk_schbr_scheme');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `scheme_branch` ADD CONSTRAINT `fk_schbr_scheme` FOREIGN KEY (`id_scheme`) REFERENCES `scheme`(`id_scheme`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_branch' AND CONSTRAINT_NAME = 'fk_schbr_branch');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `scheme_branch` ADD CONSTRAINT `fk_schbr_branch` FOREIGN KEY (`id_branch`) REFERENCES `branch`(`id_branch`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── PAYMENT ──────────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment' AND CONSTRAINT_NAME = 'fk_pay_sa');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `payment` ADD CONSTRAINT `fk_pay_sa` FOREIGN KEY (`id_scheme_account`) REFERENCES `scheme_account`(`id_scheme_account`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment' AND CONSTRAINT_NAME = 'fk_pay_emp');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `payment` ADD CONSTRAINT `fk_pay_emp` FOREIGN KEY (`id_employee`) REFERENCES `employee`(`id_employee`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment' AND CONSTRAINT_NAME = 'fk_pay_branch');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `payment` ADD CONSTRAINT `fk_pay_branch` FOREIGN KEY (`id_branch`) REFERENCES `branch`(`id_branch`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment' AND CONSTRAINT_NAME = 'fk_pay_status');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `payment` ADD CONSTRAINT `fk_pay_status` FOREIGN KEY (`payment_status`) REFERENCES `payment_status_message`(`id_status_msg`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── CUSTOMER ORDERS ──────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND CONSTRAINT_NAME = 'fk_cod_order');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `customerorderdetails` ADD CONSTRAINT `fk_cod_order` FOREIGN KEY (`id_customerorder`) REFERENCES `customerorder`(`id_customerorder`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── PRODUCT / CATEGORY / PURITY ──────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_category' AND CONSTRAINT_NAME = 'fk_cat_metal');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_category` ADD CONSTRAINT `fk_cat_metal` FOREIGN KEY (`id_metal`) REFERENCES `metal`(`id_metal`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_product_master' AND CONSTRAINT_NAME = 'fk_prod_cat');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_product_master` ADD CONSTRAINT `fk_prod_cat` FOREIGN KEY (`cat_id`) REFERENCES `ret_category`(`id_ret_category`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_product_master' AND CONSTRAINT_NAME = 'fk_prod_metal');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_product_master` ADD CONSTRAINT `fk_prod_metal` FOREIGN KEY (`metal_type`) REFERENCES `metal`(`id_metal`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_metal_purity_rate' AND CONSTRAINT_NAME = 'fk_mpr_metal');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_metal_purity_rate` ADD CONSTRAINT `fk_mpr_metal` FOREIGN KEY (`id_metal`) REFERENCES `metal`(`id_metal`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_metal_purity_rate' AND CONSTRAINT_NAME = 'fk_mpr_purity');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_metal_purity_rate` ADD CONSTRAINT `fk_mpr_purity` FOREIGN KEY (`id_purity`) REFERENCES `ret_purity`(`id_purity`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── TAGGING ──────────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_product');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_taging` ADD CONSTRAINT `fk_tag_product` FOREIGN KEY (`product_id`) REFERENCES `ret_product_master`(`pro_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_design');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_taging` ADD CONSTRAINT `fk_tag_design` FOREIGN KEY (`design_id`) REFERENCES `ret_design_master`(`design_no`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_subdesign');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_taging` ADD CONSTRAINT `fk_tag_subdesign` FOREIGN KEY (`id_sub_design`) REFERENCES `ret_sub_design_master`(`id_sub_design`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_section');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_taging` ADD CONSTRAINT `fk_tag_section` FOREIGN KEY (`id_section`) REFERENCES `ret_section`(`id_section`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_purity');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_taging` ADD CONSTRAINT `fk_tag_purity` FOREIGN KEY (`purity`) REFERENCES `ret_purity`(`id_purity`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_size');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_taging` ADD CONSTRAINT `fk_tag_size` FOREIGN KEY (`size`) REFERENCES `ret_size`(`id_size`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- SKIPPED: fk_tag_branch — 18,005 orphan rows (24%) in current_branch → branch
-- SKIPPED: fk_tag_emp   — 34,176 orphan rows (45%) in created_by → employee

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging_stone' AND CONSTRAINT_NAME = 'fk_tagst_tag');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_taging_stone` ADD CONSTRAINT `fk_tagst_tag` FOREIGN KEY (`tag_id`) REFERENCES `ret_taging`(`tag_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging_stone' AND CONSTRAINT_NAME = 'fk_tagst_stone');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_taging_stone` ADD CONSTRAINT `fk_tagst_stone` FOREIGN KEY (`stone_id`) REFERENCES `ret_stone`(`stone_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging_stone' AND CONSTRAINT_NAME = 'fk_tagst_uom');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_taging_stone` ADD CONSTRAINT `fk_tagst_uom` FOREIGN KEY (`uom_id`) REFERENCES `ret_uom`(`uom_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging_images' AND CONSTRAINT_NAME = 'fk_tagimg_tag');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_taging_images` ADD CONSTRAINT `fk_tagimg_tag` FOREIGN KEY (`tag_id`) REFERENCES `ret_taging`(`tag_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── BILLING ──────────────────────────────────────────────────
-- SKIPPED: fk_bill_customer — 1,624 orphan rows (52%) in bill_cus_id → customer
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND CONSTRAINT_NAME = 'fk_bill_branch');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_billing` ADD CONSTRAINT `fk_bill_branch` FOREIGN KEY (`id_branch`) REFERENCES `branch`(`id_branch`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND CONSTRAINT_NAME = 'fk_bill_emp');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_billing` ADD CONSTRAINT `fk_bill_emp` FOREIGN KEY (`created_by`) REFERENCES `employee`(`id_employee`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND CONSTRAINT_NAME = 'fk_bill_metal');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_billing` ADD CONSTRAINT `fk_bill_metal` FOREIGN KEY (`metal_type`) REFERENCES `metal`(`id_metal`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND CONSTRAINT_NAME = 'fk_bd_bill');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_bill_details` ADD CONSTRAINT `fk_bd_bill` FOREIGN KEY (`bill_id`) REFERENCES `ret_billing`(`bill_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND CONSTRAINT_NAME = 'fk_bd_tag');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_bill_details` ADD CONSTRAINT `fk_bd_tag` FOREIGN KEY (`tag_id`) REFERENCES `ret_taging`(`tag_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- SKIPPED: fk_bd_emp — item_emp_id contains 0 and orphan values (not in employee table); app inserts 0 for "no employee assigned"
-- SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND CONSTRAINT_NAME = 'fk_bd_emp');
-- SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_bill_details` ADD CONSTRAINT `fk_bd_emp` FOREIGN KEY (`item_emp_id`) REFERENCES `employee`(`id_employee`)', 'SELECT 1');
-- PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DROP fk_bd_emp if it was already applied (safe — checks existence first)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND CONSTRAINT_NAME = 'fk_bd_emp');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_bill_details` DROP FOREIGN KEY `fk_bd_emp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing_payment' AND CONSTRAINT_NAME = 'fk_billpay_bill');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_billing_payment` ADD CONSTRAINT `fk_billpay_bill` FOREIGN KEY (`bill_id`) REFERENCES `ret_billing`(`bill_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing_advance' AND CONSTRAINT_NAME = 'fk_billadv_bill');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_billing_advance` ADD CONSTRAINT `fk_billadv_bill` FOREIGN KEY (`bill_id`) REFERENCES `ret_billing`(`bill_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing_item_stones' AND CONSTRAINT_NAME = 'fk_bist_billdet');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_billing_item_stones` ADD CONSTRAINT `fk_bist_billdet` FOREIGN KEY (`bill_det_id`) REFERENCES `ret_bill_details`(`bill_det_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_old_metal_sale_details' AND CONSTRAINT_NAME = 'fk_boms_bill');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_bill_old_metal_sale_details` ADD CONSTRAINT `fk_boms_bill` FOREIGN KEY (`bill_id`) REFERENCES `ret_billing`(`bill_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_return_details' AND CONSTRAINT_NAME = 'fk_brd_bill');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_bill_return_details` ADD CONSTRAINT `fk_brd_bill` FOREIGN KEY (`bill_id`) REFERENCES `ret_billing`(`bill_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_advance_utilized' AND CONSTRAINT_NAME = 'fk_au_bill');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_advance_utilized` ADD CONSTRAINT `fk_au_bill` FOREIGN KEY (`bill_id`) REFERENCES `ret_billing`(`bill_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── PURCHASE ORDER ───────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_purchase_order' AND CONSTRAINT_NAME = 'fk_po_emp');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_purchase_order` ADD CONSTRAINT `fk_po_emp` FOREIGN KEY (`created_by`) REFERENCES `employee`(`id_employee`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── GRN ──────────────────────────────────────────────────────
-- NOTE: Actual FK column is `grn_item_grn_id` (not `id_grn` from original script)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_grn_items' AND CONSTRAINT_NAME = 'fk_grni_grn');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_grn_items` ADD CONSTRAINT `fk_grni_grn` FOREIGN KEY (`grn_item_grn_id`) REFERENCES `ret_grn_entry`(`grn_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── LOT INWARD ───────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_lot_inwards' AND CONSTRAINT_NAME = 'fk_lot_branch');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_lot_inwards` ADD CONSTRAINT `fk_lot_branch` FOREIGN KEY (`lot_received_at`) REFERENCES `branch`(`id_branch`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_lot_inwards_detail' AND CONSTRAINT_NAME = 'fk_lotd_lot');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_lot_inwards_detail` ADD CONSTRAINT `fk_lotd_lot` FOREIGN KEY (`lot_no`) REFERENCES `ret_lot_inwards`(`lot_no`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tag_lot_det_ids' AND CONSTRAINT_NAME = 'fk_taglt_lotdet');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `tag_lot_det_ids` ADD CONSTRAINT `fk_taglt_lotdet` FOREIGN KEY (`id_lot_inward_detail`) REFERENCES `ret_lot_inwards_detail`(`id_lot_inward_detail`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── BRANCH TRANSFER ──────────────────────────────────────────
-- SKIPPED: fk_bt_from — 1,872 orphan rows (32%) in transfer_from_branch → branch
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_branch_transfer' AND CONSTRAINT_NAME = 'fk_bt_to');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_branch_transfer` ADD CONSTRAINT `fk_bt_to` FOREIGN KEY (`transfer_to_branch`) REFERENCES `branch`(`id_branch`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_branch_transfer' AND CONSTRAINT_NAME = 'fk_bt_emp');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_branch_transfer` ADD CONSTRAINT `fk_bt_emp` FOREIGN KEY (`create_by`) REFERENCES `employee`(`id_employee`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_brch_transfer_tag_items' AND CONSTRAINT_NAME = 'fk_btti_bt');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_brch_transfer_tag_items` ADD CONSTRAINT `fk_btti_bt` FOREIGN KEY (`transfer_id`) REFERENCES `ret_branch_transfer`(`branch_transfer_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_brch_transfer_tag_items' AND CONSTRAINT_NAME = 'fk_btti_tag');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_brch_transfer_tag_items` ADD CONSTRAINT `fk_btti_tag` FOREIGN KEY (`tag_id`) REFERENCES `ret_taging`(`tag_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_brch_transfer_old_metal' AND CONSTRAINT_NAME = 'fk_btom_bt');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_brch_transfer_old_metal` ADD CONSTRAINT `fk_btom_bt` FOREIGN KEY (`transfer_id`) REFERENCES `ret_branch_transfer`(`branch_transfer_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_brch_transfer_non_tag_items' AND CONSTRAINT_NAME = 'fk_btnt_bt');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_brch_transfer_non_tag_items` ADD CONSTRAINT `fk_btnt_bt` FOREIGN KEY (`transfer_id`) REFERENCES `ret_branch_transfer`(`branch_transfer_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── STOCK ISSUE ──────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_stock_issue' AND CONSTRAINT_NAME = 'fk_si_customer');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_stock_issue` ADD CONSTRAINT `fk_si_customer` FOREIGN KEY (`id_customer`) REFERENCES `customer`(`id_customer`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_stock_issue' AND CONSTRAINT_NAME = 'fk_si_branch');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_stock_issue` ADD CONSTRAINT `fk_si_branch` FOREIGN KEY (`id_branch`) REFERENCES `branch`(`id_branch`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_stock_issue' AND CONSTRAINT_NAME = 'fk_si_emp');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_stock_issue` ADD CONSTRAINT `fk_si_emp` FOREIGN KEY (`created_by`) REFERENCES `employee`(`id_employee`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_stock_issue_detail' AND CONSTRAINT_NAME = 'fk_sid_si');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_stock_issue_detail` ADD CONSTRAINT `fk_sid_si` FOREIGN KEY (`id_stock_issue`) REFERENCES `ret_stock_issue`(`id_stock_issue`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── ISSUE RECEIPT ────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_receipt' AND CONSTRAINT_NAME = 'fk_ir_customer');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_issue_receipt` ADD CONSTRAINT `fk_ir_customer` FOREIGN KEY (`id_customer`) REFERENCES `customer`(`id_customer`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_receipt' AND CONSTRAINT_NAME = 'fk_ir_emp');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_issue_receipt` ADD CONSTRAINT `fk_ir_emp` FOREIGN KEY (`id_employee`) REFERENCES `employee`(`id_employee`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_receipt' AND CONSTRAINT_NAME = 'fk_ir_branch');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_issue_receipt` ADD CONSTRAINT `fk_ir_branch` FOREIGN KEY (`id_branch`) REFERENCES `branch`(`id_branch`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── LEDGER ───────────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_mapping' AND CONSTRAINT_NAME = 'fk_lm_ledger');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ledger_mapping` ADD CONSTRAINT `fk_lm_ledger` FOREIGN KEY (`id_ledger`) REFERENCES `ledger_master`(`id_ledger`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_ledger_transfer' AND CONSTRAINT_NAME = 'fk_lt_from');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_ledger_transfer` ADD CONSTRAINT `fk_lt_from` FOREIGN KEY (`from_ledger_id`) REFERENCES `ledger_master`(`id_ledger`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_ledger_transfer' AND CONSTRAINT_NAME = 'fk_lt_to');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `ret_ledger_transfer` ADD CONSTRAINT `fk_lt_to` FOREIGN KEY (`to_ledger_id`) REFERENCES `ledger_master`(`id_ledger`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── WALLET ───────────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inter_wallet_trans_detail' AND CONSTRAINT_NAME = 'fk_iwtd_trans');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `inter_wallet_trans_detail` ADD CONSTRAINT `fk_iwtd_trans` FOREIGN KEY (`id_inter_wallet_trans`) REFERENCES `inter_wallet_trans`(`id_inter_wallet_trans`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- DOWN
-- SET FOREIGN_KEY_CHECKS = 0;
-- ALTER TABLE `employee` DROP FOREIGN KEY `fk_emp_dept`, DROP FOREIGN KEY `fk_emp_profile`;
-- ALTER TABLE `employee_devices` DROP FOREIGN KEY `fk_empdev_emp`;
-- ALTER TABLE `customer` DROP FOREIGN KEY `fk_cus_village`;
-- ALTER TABLE `address` DROP FOREIGN KEY `fk_addr_customer`, DROP FOREIGN KEY `fk_addr_city`, DROP FOREIGN KEY `fk_addr_state`, DROP FOREIGN KEY `fk_addr_country`;
-- ALTER TABLE `scheme_account` DROP FOREIGN KEY `fk_sa_customer`, DROP FOREIGN KEY `fk_sa_scheme`, DROP FOREIGN KEY `fk_sa_branch`;
-- ALTER TABLE `scheme_branch` DROP FOREIGN KEY `fk_schbr_scheme`, DROP FOREIGN KEY `fk_schbr_branch`;
-- ALTER TABLE `payment` DROP FOREIGN KEY `fk_pay_sa`, DROP FOREIGN KEY `fk_pay_emp`, DROP FOREIGN KEY `fk_pay_branch`, DROP FOREIGN KEY `fk_pay_status`;
-- ALTER TABLE `customerorderdetails` DROP FOREIGN KEY `fk_cod_order`;
-- ALTER TABLE `ret_category` DROP FOREIGN KEY `fk_cat_metal`;
-- ALTER TABLE `ret_product_master` DROP FOREIGN KEY `fk_prod_cat`, DROP FOREIGN KEY `fk_prod_metal`;
-- ALTER TABLE `ret_metal_purity_rate` DROP FOREIGN KEY `fk_mpr_metal`, DROP FOREIGN KEY `fk_mpr_purity`;
-- ALTER TABLE `ret_taging` DROP FOREIGN KEY `fk_tag_product`, DROP FOREIGN KEY `fk_tag_design`, DROP FOREIGN KEY `fk_tag_subdesign`, DROP FOREIGN KEY `fk_tag_section`, DROP FOREIGN KEY `fk_tag_purity`, DROP FOREIGN KEY `fk_tag_size`;
-- ALTER TABLE `ret_taging_stone` DROP FOREIGN KEY `fk_tagst_tag`, DROP FOREIGN KEY `fk_tagst_stone`, DROP FOREIGN KEY `fk_tagst_uom`;
-- ALTER TABLE `ret_taging_images` DROP FOREIGN KEY `fk_tagimg_tag`;
-- ALTER TABLE `ret_billing` DROP FOREIGN KEY `fk_bill_branch`, DROP FOREIGN KEY `fk_bill_emp`, DROP FOREIGN KEY `fk_bill_metal`;
-- ALTER TABLE `ret_bill_details` DROP FOREIGN KEY `fk_bd_bill`, DROP FOREIGN KEY `fk_bd_tag`;
-- ALTER TABLE `ret_billing_payment` DROP FOREIGN KEY `fk_billpay_bill`;
-- ALTER TABLE `ret_billing_advance` DROP FOREIGN KEY `fk_billadv_bill`;
-- ALTER TABLE `ret_billing_item_stones` DROP FOREIGN KEY `fk_bist_billdet`;
-- ALTER TABLE `ret_bill_old_metal_sale_details` DROP FOREIGN KEY `fk_boms_bill`;
-- ALTER TABLE `ret_bill_return_details` DROP FOREIGN KEY `fk_brd_bill`;
-- ALTER TABLE `ret_advance_utilized` DROP FOREIGN KEY `fk_au_bill`;
-- ALTER TABLE `ret_purchase_order` DROP FOREIGN KEY `fk_po_emp`;
-- ALTER TABLE `ret_grn_items` DROP FOREIGN KEY `fk_grni_grn`;
-- ALTER TABLE `ret_lot_inwards` DROP FOREIGN KEY `fk_lot_branch`;
-- ALTER TABLE `ret_lot_inwards_detail` DROP FOREIGN KEY `fk_lotd_lot`;
-- ALTER TABLE `tag_lot_det_ids` DROP FOREIGN KEY `fk_taglt_lotdet`;
-- ALTER TABLE `ret_branch_transfer` DROP FOREIGN KEY `fk_bt_to`, DROP FOREIGN KEY `fk_bt_emp`;
-- ALTER TABLE `ret_brch_transfer_tag_items` DROP FOREIGN KEY `fk_btti_bt`, DROP FOREIGN KEY `fk_btti_tag`;
-- ALTER TABLE `ret_brch_transfer_old_metal` DROP FOREIGN KEY `fk_btom_bt`;
-- ALTER TABLE `ret_brch_transfer_non_tag_items` DROP FOREIGN KEY `fk_btnt_bt`;
-- ALTER TABLE `ret_stock_issue` DROP FOREIGN KEY `fk_si_customer`, DROP FOREIGN KEY `fk_si_branch`, DROP FOREIGN KEY `fk_si_emp`;
-- ALTER TABLE `ret_stock_issue_detail` DROP FOREIGN KEY `fk_sid_si`;
-- ALTER TABLE `ret_issue_receipt` DROP FOREIGN KEY `fk_ir_customer`, DROP FOREIGN KEY `fk_ir_emp`, DROP FOREIGN KEY `fk_ir_branch`;
-- ALTER TABLE `ledger_mapping` DROP FOREIGN KEY `fk_lm_ledger`;
-- ALTER TABLE `ret_ledger_transfer` DROP FOREIGN KEY `fk_lt_from`, DROP FOREIGN KEY `fk_lt_to`;
-- ALTER TABLE `inter_wallet_trans_detail` DROP FOREIGN KEY `fk_iwtd_trans`;
-- SET FOREIGN_KEY_CHECKS = 1;

/*
  SKIPPED FKs (7 constraints — orphan data, needs cleanup before enabling):
  ──────────────────────────────────────────────────────────────────────────
  fk_tag_branch      | 18,005 (24%) | current_branch → branch
  fk_tag_emp         | 34,176 (45%) | created_by → employee
  fk_bill_customer   |  1,624 (52%) | bill_cus_id → customer
  fk_est_branch      |  1,142 (19%) | id_branch → branch
  fk_est_emp         |    463  (8%) | created_by → employee
  fk_bd_emp          |    731+ NULLs + 0s | item_emp_id → employee (app inserts 0 for no employee)
  fk_bt_from         |  1,872 (32%) | transfer_from_branch → branch

  SKIPPED FKs (2 constraints — scheme_account at MySQL's 64-key-per-table
  limit, ERROR 1069, discovered on 2026-07-29 prod deploy):
  ──────────────────────────────────────────────────────────────────────────
  fk_sa_agent        | id_agent → agent(id_agent)
  fk_sa_emp_closed   | employee_closed → employee(id_employee)
  Needs an index audit on scheme_account (identify/drop unused or duplicate
  indexes) to free up key slots before these two can be re-enabled.
*/
