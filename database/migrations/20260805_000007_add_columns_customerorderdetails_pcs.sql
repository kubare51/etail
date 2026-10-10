-- Migration: Add integer piece-count column to `customerorderdetails`
-- Feature: Common Purchase Order - Advance Rate Fixing for Bullion Purchase (BRS v1.0)
-- Ticket: CPO-02 (addendum)
-- Safe: Column checked via INFORMATION_SCHEMA before adding
--
-- BRS 4.1 field 7 "Pieces (Pcs)" is a numeric count. The existing line-level
-- column `totalitems` is varchar(100) and cannot be summed or compared reliably,
-- so a dedicated integer column is added rather than overloading it.
--
-- Data-type standard for Common PO fields:
--   Weight  -> decimal(_,3)   already satisfied: weight / less_wt / net_wt are decimal(10,3)
--   Amount  -> decimal(_,2)   satisfied: taxable_amount / tax_amount / total_amount are decimal(16,2)
--   Pieces  -> int            this migration
--
-- KNOWN INCONSISTENCY (not changed here): `customerorderdetails`.`delivered_wt` is
-- decimal(10,2) while `customerorder`.`delivered_wt` is decimal(10,3). Common PO
-- receipt tracking (CPO-10) uses the HEADER column, which already has 3 decimals,
-- so bullion precision is not lost. Correcting the detail column touches a shared
-- table used by Order and Repair flows and is deliberately left out of scope.

-- UP

-- no_of_pcs
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customerorderdetails' AND COLUMN_NAME = 'no_of_pcs');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `customerorderdetails` ADD `no_of_pcs` int NOT NULL DEFAULT ''0'' COMMENT ''Piece count. Used by Common PO bullion lines (BRS 4.1 field 7). Numeric counterpart of the legacy varchar `totalitems`.'' AFTER `totalitems`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- ALTER TABLE `customerorderdetails` DROP COLUMN `no_of_pcs`;
