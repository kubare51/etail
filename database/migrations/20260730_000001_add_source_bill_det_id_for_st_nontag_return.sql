-- Migration: Add ret_bill_details.source_bill_det_id (idempotent)
-- Purpose: Link a Sales Transfer / Sales Return Transfer dispatch line (salesTransType=4,
--          is_non_tag=1) back to the original ret_bill_details row it locks, so that
--          cancel_sales_transfer() can reverse transferred_to_acc_stock on the correct row.
--          Tagged/OG/NT dispatch types already have a real linkage (tag_id / st_bill_id);
--          non-tag Sales Return had none, which allowed the same source row to be
--          dispatched more than once.
-- Target Table: ret_bill_details
-- Date: 2026-07-30

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_bill_details' AND COLUMN_NAME = 'source_bill_det_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_bill_details` ADD COLUMN `source_bill_det_id` INT DEFAULT NULL COMMENT ''Original ret_bill_details.bill_det_id locked by this dispatch line (non-tag Sales Return via ST/SRT only)'' AFTER `transferred_to_acc_stock`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
