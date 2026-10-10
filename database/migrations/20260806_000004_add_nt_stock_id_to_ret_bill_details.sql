-- =============================================================================
-- VEDA-087 — Bind each Non-Tag Sales Transfer line to its source stock row
-- =============================================================================
--
-- Runs automatically on deploy. The accompanying code guards the write with
-- field_exists(), so code deployed ahead of this migration degrades to the old
-- behaviour rather than breaking dispatch.
--
-- WHY
-- Non-Tag transfers previously recorded their source stock row by stamping
-- ret_nontag_item.st_bill_id, and the download re-matched bill lines to stock
-- rows on product + design + sub_design. Two failure modes:
--
--   1. st_bill_id holds ONE value. Dispatching a second transfer from the same
--      stock row before the first is downloaded overwrote the first bill's
--      stamp, and the first download then matched nothing.
--
--   2. Two lines in one bill sharing product + design + sub_design (the same
--      product held in different sections) fanned out in the join, deducting
--      and logging N x M times.
--
-- Storing the source id_nontag_item on the bill line makes the relationship
-- 1:1 and explicit. st_bill_id is still written, because the cancel paths
-- (unlock_nt_stock_by_bill, reverse_nt_stock_transfer) rely on it.
--
-- Bills dispatched before this migration have nt_stock_id NULL; the download
-- keeps a fallback to the old st_bill_id + product/design matching for those,
-- so no backfill is required for correctness.
-- =============================================================================

-- UP

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME   = 'ret_bill_details'
                     AND COLUMN_NAME  = 'nt_stock_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ret_bill_details` ADD COLUMN `nt_stock_id` INT(11) DEFAULT NULL COMMENT ''ret_nontag_item.id_nontag_item this Non-Tag Sales Transfer line was dispatched from'' AFTER `salesTransType`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index: the download joins ret_bill_details -> ret_nontag_item on this column.
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME   = 'ret_bill_details'
                     AND INDEX_NAME   = 'idx_nt_stock_id');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `ret_bill_details` ADD INDEX `idx_nt_stock_id` (`nt_stock_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DOWN

ALTER TABLE `ret_bill_details` DROP INDEX `idx_nt_stock_id`;
ALTER TABLE `ret_bill_details` DROP COLUMN `nt_stock_id`;
