-- Migration: Add primary keys to product, tagging, estimation, PO, old metal, lot,
--            issue receipt, karigar, inventory, notices, and gift tables
-- Date: 24-05-2026
-- Safe: Each PK is guarded by INFORMATION_SCHEMA check — idempotent
-- Source: db_pk_fk_relationships_corrected.sql (Section 1, Batch 2)

-- UP

-- === ret_design_images ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_design_images' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_design_images` ADD PRIMARY KEY (`id_image`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_design_karigars ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_design_karigars' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_design_karigars` ADD PRIMARY KEY (`des_kar_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_design_other_materials ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_design_other_materials' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_design_other_materials` ADD PRIMARY KEY (`other_material_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_design_purity ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_design_purity' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_design_purity` ADD PRIMARY KEY (`des_pur_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_design_sizes ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_design_sizes' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_design_sizes` ADD PRIMARY KEY (`design_size_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_design_stone ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_design_stone' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_design_stone` ADD PRIMARY KEY (`des_stone_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_product_sub_product ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_product_sub_product' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_product_sub_product` ADD PRIMARY KEY (`pro_sub_product_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === tag_lot_det_ids (composite PK) ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tag_lot_det_ids' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `tag_lot_det_ids` ADD PRIMARY KEY (`tag_id`, `id_lot_inward_detail`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_est_chit_utilization ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_est_chit_utilization' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_est_chit_utilization` ADD PRIMARY KEY (`chit_ut_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_est_gift_voucher_details ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_est_gift_voucher_details' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_est_gift_voucher_details` ADD PRIMARY KEY (`gift_voucher_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_po_payment_detail ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_po_payment_detail' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_po_payment_detail` ADD PRIMARY KEY (`id_pay_details`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_po_qc_issue_process ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_po_qc_issue_process' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_po_qc_issue_process` ADD PRIMARY KEY (`qc_process_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_purchase_item_stock_summary_log ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_purchase_item_stock_summary_log' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_purchase_item_stock_summary_log` ADD PRIMARY KEY (`id_summary_log`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_old_metal_melting_recd_details ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_old_metal_melting_recd_details' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_old_metal_melting_recd_details` ADD PRIMARY KEY (`id_melting_recd`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_old_metal_polishing_details ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_old_metal_polishing_details' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_old_metal_polishing_details` ADD PRIMARY KEY (`id_polishing_details`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_lot_types ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_lot_types' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_lot_types` ADD PRIMARY KEY (`lot_type_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_issue_receipt ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_receipt' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_issue_receipt` ADD PRIMARY KEY (`id_issue_receipt`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_issue_rcpt_payment ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_issue_rcpt_payment' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_issue_rcpt_payment` ADD PRIMARY KEY (`id_issue_rcpt_pay`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_karigar_advance_payment ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_karigar_advance_payment' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_karigar_advance_payment` ADD PRIMARY KEY (`kar_adv_pay_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_karigar_item_wast_pro_images ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_karigar_item_wast_pro_images' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_karigar_item_wast_pro_images` ADD PRIMARY KEY (`id_pro_image`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_karigar_wallet_transcation ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_karigar_wallet_transcation' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_karigar_wallet_transcation` ADD PRIMARY KEY (`id_transcation`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_acc_stock_process ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_acc_stock_process' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_acc_stock_process` ADD PRIMARY KEY (`id_process`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_other_inventory_purchase_items_log ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_other_inventory_purchase_items_log' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_other_inventory_purchase_items_log` ADD PRIMARY KEY (`id_item_log`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_noticeboard ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_noticeboard' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_noticeboard` ADD PRIMARY KEY (`id_noticeboard`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_noticeboard_view_details ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_noticeboard_view_details' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_noticeboard_view_details` ADD PRIMARY KEY (`view_det_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_stone_discount_master ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_stone_discount_master' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_stone_discount_master` ADD PRIMARY KEY (`id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_gift_master_redeem_prod ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_gift_master_redeem_prod' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_gift_master_redeem_prod` ADD PRIMARY KEY (`id_prod_set`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === ret_view_customer_ledger ===
SET @pk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_view_customer_ledger' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@pk_exists = 0, 'ALTER TABLE `ret_view_customer_ledger` ADD PRIMARY KEY (`cusId`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `ret_design_images` DROP PRIMARY KEY;
-- ALTER TABLE `ret_design_karigars` DROP PRIMARY KEY;
-- ALTER TABLE `ret_design_other_materials` DROP PRIMARY KEY;
-- ALTER TABLE `ret_design_purity` DROP PRIMARY KEY;
-- ALTER TABLE `ret_design_sizes` DROP PRIMARY KEY;
-- ALTER TABLE `ret_design_stone` DROP PRIMARY KEY;
-- ALTER TABLE `ret_product_sub_product` DROP PRIMARY KEY;
-- ALTER TABLE `tag_lot_det_ids` DROP PRIMARY KEY;
-- ALTER TABLE `ret_est_chit_utilization` DROP PRIMARY KEY;
-- ALTER TABLE `ret_est_gift_voucher_details` DROP PRIMARY KEY;
-- ALTER TABLE `ret_po_payment_detail` DROP PRIMARY KEY;
-- ALTER TABLE `ret_po_qc_issue_process` DROP PRIMARY KEY;
-- ALTER TABLE `ret_purchase_item_stock_summary_log` DROP PRIMARY KEY;
-- ALTER TABLE `ret_old_metal_melting_recd_details` DROP PRIMARY KEY;
-- ALTER TABLE `ret_old_metal_polishing_details` DROP PRIMARY KEY;
-- ALTER TABLE `ret_lot_types` DROP PRIMARY KEY;
-- ALTER TABLE `ret_issue_receipt` DROP PRIMARY KEY;
-- ALTER TABLE `ret_issue_rcpt_payment` DROP PRIMARY KEY;
-- ALTER TABLE `ret_karigar_advance_payment` DROP PRIMARY KEY;
-- ALTER TABLE `ret_karigar_item_wast_pro_images` DROP PRIMARY KEY;
-- ALTER TABLE `ret_karigar_wallet_transcation` DROP PRIMARY KEY;
-- ALTER TABLE `ret_acc_stock_process` DROP PRIMARY KEY;
-- ALTER TABLE `ret_other_inventory_purchase_items_log` DROP PRIMARY KEY;
-- ALTER TABLE `ret_noticeboard` DROP PRIMARY KEY;
-- ALTER TABLE `ret_noticeboard_view_details` DROP PRIMARY KEY;
-- ALTER TABLE `ret_stone_discount_master` DROP PRIMARY KEY;
-- ALTER TABLE `ret_gift_master_redeem_prod` DROP PRIMARY KEY;
-- ALTER TABLE `ret_view_customer_ledger` DROP PRIMARY KEY;
