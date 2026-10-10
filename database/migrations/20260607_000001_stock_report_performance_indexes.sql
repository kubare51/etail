-- =============================================================================
-- Migration: Stock Report Performance Indexes
-- Task: PERF: Stock In/Out Report & Section-wise Stock Report - Slow Query Optimization
-- Branch: bugfix/slow-query-stock-report
-- Date: 2026-06-07
-- 
-- Purpose: Add composite indexes to support the stock in/out report query
--          which uses self-join anti-pattern on ret_section_tag_status_log
--          (8 subqueries, each doing full table scans)
--
-- Risk: ZERO code risk. Read-only index additions.
-- Rollback: DROP INDEX statements at bottom of file.
--
-- Idempotent: YES — checks information_schema before each CREATE INDEX.
-- Non-blocking: YES — InnoDB performs online ADD INDEX by default (ALGORITHM=INPLACE
--               removed: not supported inside PREPARE/EXECUTE on all MySQL versions).
-- =============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS _migration_add_index_if_not_exists$$

CREATE PROCEDURE _migration_add_index_if_not_exists(
    IN p_schema_name VARCHAR(128),
    IN p_table_name  VARCHAR(128),
    IN p_index_name  VARCHAR(128),
    IN p_index_ddl   TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = p_schema_name
          AND TABLE_NAME   = p_table_name
          AND INDEX_NAME   = p_index_name
        LIMIT 1
    ) THEN
        SET @sql = p_index_ddl;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('CREATED: ', p_table_name, '.', p_index_name) AS migration_status;
    ELSE
        SELECT CONCAT('SKIPPED (exists): ', p_table_name, '.', p_index_name) AS migration_status;
    END IF;
END$$

DELIMITER ;

-- Use the current database name
SET @db = DATABASE();

-- -----------------------------------------------------------------------------
-- 1. ret_section_tag_status_log indexes
-- -----------------------------------------------------------------------------

-- Index 1: Covers the self-join pattern (used 8+ times in stock report)
-- Pattern: m1.tag_id = m2.tag_id AND m1.id < m2.id AND date(m2.date) <= X
CALL _migration_add_index_if_not_exists(@db, 'ret_section_tag_status_log', 'idx_tag_status_date',
    'ALTER TABLE ret_section_tag_status_log ADD INDEX idx_tag_status_date (tag_id, id_sec_tag_status_log, `date`)');

-- Index 2: Covers to_branch + status + date filtering
-- Used in: opening balance, inward, closing subqueries
CALL _migration_add_index_if_not_exists(@db, 'ret_section_tag_status_log', 'idx_to_branch_status_date',
    'ALTER TABLE ret_section_tag_status_log ADD INDEX idx_to_branch_status_date (to_branch, status, `date`)');

-- Index 3: Covers from_branch + status + date filtering
-- Used in: branch-out, section-out subqueries
CALL _migration_add_index_if_not_exists(@db, 'ret_section_tag_status_log', 'idx_from_branch_status_date',
    'ALTER TABLE ret_section_tag_status_log ADD INDEX idx_from_branch_status_date (from_branch, status, `date`)');

-- Index 4: Covers to_section filtering for section-specific queries
CALL _migration_add_index_if_not_exists(@db, 'ret_section_tag_status_log', 'idx_to_section_status_date',
    'ALTER TABLE ret_section_tag_status_log ADD INDEX idx_to_section_status_date (to_section, status, `date`)');

-- -----------------------------------------------------------------------------
-- 2. ret_billing indexes
-- -----------------------------------------------------------------------------

-- Index 5: Covers sold subquery (bill_status + bill_date + id_branch)
CALL _migration_add_index_if_not_exists(@db, 'ret_billing', 'idx_status_date_branch',
    'ALTER TABLE ret_billing ADD INDEX idx_status_date_branch (bill_status, bill_date, id_branch)');

-- -----------------------------------------------------------------------------
-- 3. ret_bill_details indexes
-- -----------------------------------------------------------------------------

-- Index 6: Covers bill_details join pattern
CALL _migration_add_index_if_not_exists(@db, 'ret_bill_details', 'idx_tag_bill_product',
    'ALTER TABLE ret_bill_details ADD INDEX idx_tag_bill_product (tag_id, bill_id, product_id)');

-- -----------------------------------------------------------------------------
-- 4. ret_home_section_item_log indexes (for home-section stock report)
-- -----------------------------------------------------------------------------

-- Index 7: Same self-join pattern as ret_section_tag_status_log
CALL _migration_add_index_if_not_exists(@db, 'ret_home_section_item_log', 'idx_tag_log_date',
    'ALTER TABLE ret_home_section_item_log ADD INDEX idx_tag_log_date (tag_id, id_home_section_itm_log, `date`)');

-- Index 8: Covers to_branch + status + date for home section queries
CALL _migration_add_index_if_not_exists(@db, 'ret_home_section_item_log', 'idx_to_branch_status_date',
    'ALTER TABLE ret_home_section_item_log ADD INDEX idx_to_branch_status_date (to_branch, status, `date`)');

-- -----------------------------------------------------------------------------
-- Cleanup: drop the helper procedure
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS _migration_add_index_if_not_exists;


-- =============================================================================
-- ROLLBACK (run these to undo if needed)
-- =============================================================================
-- ALTER TABLE ret_section_tag_status_log DROP INDEX idx_tag_status_date;
-- ALTER TABLE ret_section_tag_status_log DROP INDEX idx_to_branch_status_date;
-- ALTER TABLE ret_section_tag_status_log DROP INDEX idx_from_branch_status_date;
-- ALTER TABLE ret_section_tag_status_log DROP INDEX idx_to_section_status_date;
-- ALTER TABLE ret_billing DROP INDEX idx_status_date_branch;
-- ALTER TABLE ret_bill_details DROP INDEX idx_tag_bill_product;
-- ALTER TABLE ret_home_section_item_log DROP INDEX idx_tag_log_date;
-- ALTER TABLE ret_home_section_item_log DROP INDEX idx_to_branch_status_date;
