-- Migration: Revert ALL foreign key constraints added in 20260524_000005_add_foreign_keys.sql
-- Date: 05-06-2026
-- Reason: FK constraints broke existing application flows that send 0 as fallback
--         for FK columns (id_branch, id_scheme, id_customer, etc.)
--         Application code must be fixed BEFORE re-enabling FKs.
-- Safe: Each DROP is guarded by INFORMATION_SCHEMA check — idempotent
-- Reverts: 20260524_000005_add_foreign_keys.sql (all 56 constraints)

-- UP (this drops all FKs)
SET FOREIGN_KEY_CHECKS = 0;

-- ── CORE / EMPLOYEE ──────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee' AND CONSTRAINT_NAME = 'fk_emp_dept');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `employee` DROP FOREIGN KEY `fk_emp_dept`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee' AND CONSTRAINT_NAME = 'fk_emp_profile');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `employee` DROP FOREIGN KEY `fk_emp_profile`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_devices' AND CONSTRAINT_NAME = 'fk_empdev_emp');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `employee_devices` DROP FOREIGN KEY `fk_empdev_emp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── CUSTOMER & ADDRESS ───────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer' AND CONSTRAINT_NAME = 'fk_cus_village');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `customer` DROP FOREIGN KEY `fk_cus_village`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'address' AND CONSTRAINT_NAME = 'fk_addr_customer');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `address` DROP FOREIGN KEY `fk_addr_customer`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'address' AND CONSTRAINT_NAME = 'fk_addr_city');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `address` DROP FOREIGN KEY `fk_addr_city`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'address' AND CONSTRAINT_NAME = 'fk_addr_state');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `address` DROP FOREIGN KEY `fk_addr_state`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'address' AND CONSTRAINT_NAME = 'fk_addr_country');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `address` DROP FOREIGN KEY `fk_addr_country`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── SCHEME / CHIT ────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_account' AND CONSTRAINT_NAME = 'fk_sa_customer');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `scheme_account` DROP FOREIGN KEY `fk_sa_customer`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_account' AND CONSTRAINT_NAME = 'fk_sa_scheme');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `scheme_account` DROP FOREIGN KEY `fk_sa_scheme`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_account' AND CONSTRAINT_NAME = 'fk_sa_branch');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `scheme_account` DROP FOREIGN KEY `fk_sa_branch`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_account' AND CONSTRAINT_NAME = 'fk_sa_agent');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `scheme_account` DROP FOREIGN KEY `fk_sa_agent`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_account' AND CONSTRAINT_NAME = 'fk_sa_emp_closed');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `scheme_account` DROP FOREIGN KEY `fk_sa_emp_closed`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_branch' AND CONSTRAINT_NAME = 'fk_schbr_scheme');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `scheme_branch` DROP FOREIGN KEY `fk_schbr_scheme`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheme_branch' AND CONSTRAINT_NAME = 'fk_schbr_branch');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `scheme_branch` DROP FOREIGN KEY `fk_schbr_branch`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── PAYMENT ──────────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment' AND CONSTRAINT_NAME = 'fk_pay_sa');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `payment` DROP FOREIGN KEY `fk_pay_sa`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment' AND CONSTRAINT_NAME = 'fk_pay_emp');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `payment` DROP FOREIGN KEY `fk_pay_emp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment' AND CONSTRAINT_NAME = 'fk_pay_branch');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `payment` DROP FOREIGN KEY `fk_pay_branch`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment' AND CONSTRAINT_NAME = 'fk_pay_status');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `payment` DROP FOREIGN KEY `fk_pay_status`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── CUSTOMER ORDERS ──────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND CONSTRAINT_NAME = 'fk_cod_order');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `customerorderdetails` DROP FOREIGN KEY `fk_cod_order`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── PRODUCT / CATEGORY / PURITY ──────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_category' AND CONSTRAINT_NAME = 'fk_cat_metal');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_category` DROP FOREIGN KEY `fk_cat_metal`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_product_master' AND CONSTRAINT_NAME = 'fk_prod_cat');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_product_master` DROP FOREIGN KEY `fk_prod_cat`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_product_master' AND CONSTRAINT_NAME = 'fk_prod_metal');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_product_master` DROP FOREIGN KEY `fk_prod_metal`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_metal_purity_rate' AND CONSTRAINT_NAME = 'fk_mpr_metal');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_metal_purity_rate` DROP FOREIGN KEY `fk_mpr_metal`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_metal_purity_rate' AND CONSTRAINT_NAME = 'fk_mpr_purity');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_metal_purity_rate` DROP FOREIGN KEY `fk_mpr_purity`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── TAGGING ──────────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_product');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_taging` DROP FOREIGN KEY `fk_tag_product`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_design');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_taging` DROP FOREIGN KEY `fk_tag_design`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_subdesign');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_taging` DROP FOREIGN KEY `fk_tag_subdesign`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_section');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_taging` DROP FOREIGN KEY `fk_tag_section`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_purity');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_taging` DROP FOREIGN KEY `fk_tag_purity`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging' AND CONSTRAINT_NAME = 'fk_tag_size');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_taging` DROP FOREIGN KEY `fk_tag_size`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging_stone' AND CONSTRAINT_NAME = 'fk_tagst_tag');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_taging_stone` DROP FOREIGN KEY `fk_tagst_tag`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging_stone' AND CONSTRAINT_NAME = 'fk_tagst_stone');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_taging_stone` DROP FOREIGN KEY `fk_tagst_stone`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging_stone' AND CONSTRAINT_NAME = 'fk_tagst_uom');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_taging_stone` DROP FOREIGN KEY `fk_tagst_uom`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_taging_images' AND CONSTRAINT_NAME = 'fk_tagimg_tag');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_taging_images` DROP FOREIGN KEY `fk_tagimg_tag`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── BILLING ──────────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND CONSTRAINT_NAME = 'fk_bill_branch');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_billing` DROP FOREIGN KEY `fk_bill_branch`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND CONSTRAINT_NAME = 'fk_bill_emp');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_billing` DROP FOREIGN KEY `fk_bill_emp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing' AND CONSTRAINT_NAME = 'fk_bill_metal');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_billing` DROP FOREIGN KEY `fk_bill_metal`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND CONSTRAINT_NAME = 'fk_bd_bill');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_bill_details` DROP FOREIGN KEY `fk_bd_bill`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND CONSTRAINT_NAME = 'fk_bd_tag');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_bill_details` DROP FOREIGN KEY `fk_bd_tag`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND CONSTRAINT_NAME = 'fk_bd_emp');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_bill_details` DROP FOREIGN KEY `fk_bd_emp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing_payment' AND CONSTRAINT_NAME = 'fk_billpay_bill');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_billing_payment` DROP FOREIGN KEY `fk_billpay_bill`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing_advance' AND CONSTRAINT_NAME = 'fk_billadv_bill');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_billing_advance` DROP FOREIGN KEY `fk_billadv_bill`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_billing_item_stones' AND CONSTRAINT_NAME = 'fk_bist_billdet');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_billing_item_stones` DROP FOREIGN KEY `fk_bist_billdet`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_old_metal_sale_details' AND CONSTRAINT_NAME = 'fk_boms_bill');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_bill_old_metal_sale_details` DROP FOREIGN KEY `fk_boms_bill`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_return_details' AND CONSTRAINT_NAME = 'fk_brd_bill');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_bill_return_details` DROP FOREIGN KEY `fk_brd_bill`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_advance_utilized' AND CONSTRAINT_NAME = 'fk_au_bill');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_advance_utilized` DROP FOREIGN KEY `fk_au_bill`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── PURCHASE ORDER ───────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_purchase_order' AND CONSTRAINT_NAME = 'fk_po_emp');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_purchase_order` DROP FOREIGN KEY `fk_po_emp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── GRN ──────────────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_grn_items' AND CONSTRAINT_NAME = 'fk_grni_grn');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_grn_items` DROP FOREIGN KEY `fk_grni_grn`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── LOT INWARD ───────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_lot_inwards' AND CONSTRAINT_NAME = 'fk_lot_branch');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_lot_inwards` DROP FOREIGN KEY `fk_lot_branch`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_lot_inwards_detail' AND CONSTRAINT_NAME = 'fk_lotd_lot');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_lot_inwards_detail` DROP FOREIGN KEY `fk_lotd_lot`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tag_lot_det_ids' AND CONSTRAINT_NAME = 'fk_taglt_lotdet');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `tag_lot_det_ids` DROP FOREIGN KEY `fk_taglt_lotdet`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── BRANCH TRANSFER ──────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_branch_transfer' AND CONSTRAINT_NAME = 'fk_bt_to');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_branch_transfer` DROP FOREIGN KEY `fk_bt_to`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_branch_transfer' AND CONSTRAINT_NAME = 'fk_bt_emp');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_branch_transfer` DROP FOREIGN KEY `fk_bt_emp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_brch_transfer_tag_items' AND CONSTRAINT_NAME = 'fk_btti_bt');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_brch_transfer_tag_items` DROP FOREIGN KEY `fk_btti_bt`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_brch_transfer_tag_items' AND CONSTRAINT_NAME = 'fk_btti_tag');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_brch_transfer_tag_items` DROP FOREIGN KEY `fk_btti_tag`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_brch_transfer_old_metal' AND CONSTRAINT_NAME = 'fk_btom_bt');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_brch_transfer_old_metal` DROP FOREIGN KEY `fk_btom_bt`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_brch_transfer_non_tag_items' AND CONSTRAINT_NAME = 'fk_btnt_bt');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_brch_transfer_non_tag_items` DROP FOREIGN KEY `fk_btnt_bt`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── STOCK ISSUE ──────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_stock_issue' AND CONSTRAINT_NAME = 'fk_si_customer');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_stock_issue` DROP FOREIGN KEY `fk_si_customer`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_stock_issue' AND CONSTRAINT_NAME = 'fk_si_branch');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_stock_issue` DROP FOREIGN KEY `fk_si_branch`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_stock_issue' AND CONSTRAINT_NAME = 'fk_si_emp');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_stock_issue` DROP FOREIGN KEY `fk_si_emp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_stock_issue_detail' AND CONSTRAINT_NAME = 'fk_sid_si');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_stock_issue_detail` DROP FOREIGN KEY `fk_sid_si`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── ISSUE RECEIPT ────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_receipt' AND CONSTRAINT_NAME = 'fk_ir_customer');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_issue_receipt` DROP FOREIGN KEY `fk_ir_customer`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_receipt' AND CONSTRAINT_NAME = 'fk_ir_emp');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_issue_receipt` DROP FOREIGN KEY `fk_ir_emp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_receipt' AND CONSTRAINT_NAME = 'fk_ir_branch');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_issue_receipt` DROP FOREIGN KEY `fk_ir_branch`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── LEDGER ───────────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_mapping' AND CONSTRAINT_NAME = 'fk_lm_ledger');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ledger_mapping` DROP FOREIGN KEY `fk_lm_ledger`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_ledger_transfer' AND CONSTRAINT_NAME = 'fk_lt_from');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_ledger_transfer` DROP FOREIGN KEY `fk_lt_from`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_ledger_transfer' AND CONSTRAINT_NAME = 'fk_lt_to');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `ret_ledger_transfer` DROP FOREIGN KEY `fk_lt_to`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── WALLET ───────────────────────────────────────────────────
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inter_wallet_trans_detail' AND CONSTRAINT_NAME = 'fk_iwtd_trans');
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE `inter_wallet_trans_detail` DROP FOREIGN KEY `fk_iwtd_trans`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- ══════════════════════════════════════════════════════════════
-- VERIFICATION: Run after migration to confirm all FKs are dropped
-- ══════════════════════════════════════════════════════════════
-- SELECT CONSTRAINT_NAME, TABLE_NAME
-- FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND CONSTRAINT_TYPE = 'FOREIGN KEY'
--   AND CONSTRAINT_NAME LIKE 'fk_%';
-- Expected: 0 rows
