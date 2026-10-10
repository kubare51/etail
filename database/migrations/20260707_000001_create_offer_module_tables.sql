-- ============================================================================
-- Migration: Offer-Based Discount Module — Table Creation & Menu Setup
-- Date: 2026-07-07
-- Module: OFFER-001
-- Description: Creates ret_offer_master (rule engine), ret_offer_age_slab
-- (stock-age slab config), ret_offer_mapping (eligibility rules), and
-- registers the Offer Management menu + access entries.
-- Safe: CREATE TABLE IF NOT EXISTS; menu/access inserts are idempotent
-- (same pattern as 20260324_000001_pos_module_menu_setup.sql).
-- ============================================================================

-- UP
SET SQL_SAFE_UPDATES = 0;

-- ============================================
-- TABLE 1: Offer Master (Rule Engine)
-- status: 1 = Active, 0 = Inactive; is_deleted: 0 = Active, 1 = Soft-deleted
-- Metal rate discount: Percentage OR Flat (mutually exclusive, enforced in UI)
-- Diamond discount: Per Carat OR Flat % (mutually exclusive, enforced in UI)
-- ============================================
CREATE TABLE IF NOT EXISTS `ret_offer_master` (
    `id_offer`               INT(11) NOT NULL AUTO_INCREMENT,
    `offer_name`             VARCHAR(100) NOT NULL,
    `start_date`             DATE NOT NULL,
    `end_date`               DATE NOT NULL,
    `disc_metal_rate_pct`    DECIMAL(10,2) DEFAULT NULL COMMENT 'Metal rate discount %',
    `disc_metal_rate_flat`   DECIMAL(10,2) DEFAULT NULL COMMENT 'Metal rate flat discount per gram (Rs.)',
    `disc_mc_pct`            DECIMAL(10,2) DEFAULT NULL COMMENT 'Making charge discount %',
    `disc_va_pct`            DECIMAL(10,2) DEFAULT NULL COMMENT 'VA/Wastage discount %',
    `disc_diamond_per_ct`    DECIMAL(10,2) DEFAULT NULL COMMENT 'Diamond discount per carat (Rs.)',
    `disc_diamond_flat_pct`  DECIMAL(10,2) DEFAULT NULL COMMENT 'Diamond flat discount %',
    `disc_taxable_value_pct` DECIMAL(10,2) DEFAULT NULL COMMENT 'Product taxable value discount %',
    `status`                 TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    `created_by`             INT(11) DEFAULT NULL,
    `created_at`             DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_by`             INT(11) DEFAULT NULL,
    `updated_at`             DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_deleted`             TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Soft-deleted',
    PRIMARY KEY (`id_offer`),
    INDEX `idx_dates` (`start_date`, `end_date`),
    INDEX `idx_status` (`status`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE 2: Age Slab Definitions
-- max_days = NULL means open-ended (e.g., "365+ days")
-- ============================================
CREATE TABLE IF NOT EXISTS `ret_offer_age_slab` (
    `id_slab`     INT(11) NOT NULL AUTO_INCREMENT,
    `slab_name`   VARCHAR(100) NOT NULL,
    `min_days`    INT(11) NOT NULL DEFAULT 0,
    `max_days`    INT(11) DEFAULT NULL COMMENT 'NULL = open-ended (no upper limit)',
    `status`      TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    `created_by`  INT(11) DEFAULT NULL,
    `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_by`  INT(11) DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_deleted`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Soft-deleted',
    PRIMARY KEY (`id_slab`),
    INDEX `idx_status` (`status`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Age slab definitions for offer mapping';

-- ============================================
-- TABLE 3: Offer Mapping (Eligibility Rules)
-- CSV columns store comma-separated IDs (e.g., "1,5,12") or NULL for "All".
-- Final schema (includes id_design/id_sub_design added post-review, and
-- id_metal_type as CSV TEXT rather than a single INT).
-- ============================================
CREATE TABLE IF NOT EXISTS `ret_offer_mapping` (
    `id_mapping`    INT(11) NOT NULL AUTO_INCREMENT,
    `id_offer`      INT(11) NOT NULL,
    `id_metal_type` TEXT DEFAULT NULL COMMENT 'CSV of metal type IDs',
    `id_category`   TEXT DEFAULT NULL COMMENT 'CSV of category IDs',
    `id_product`    TEXT DEFAULT NULL COMMENT 'CSV of product IDs',
    `id_design`     TEXT DEFAULT NULL COMMENT 'CSV of design IDs from ret_design_master',
    `id_sub_design` TEXT DEFAULT NULL COMMENT 'CSV of sub-design IDs from ret_sub_design_master',
    `id_branch`     TEXT DEFAULT NULL COMMENT 'CSV of branch IDs',
    `id_age_slab`   TEXT DEFAULT NULL COMMENT 'CSV of age slab IDs from ret_offer_age_slab',
    `created_by`    INT(11) DEFAULT NULL,
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_by`    INT(11) DEFAULT NULL,
    `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_deleted`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=Active, 1=Soft-deleted',
    PRIMARY KEY (`id_mapping`),
    INDEX `idx_offer` (`id_offer`),
    INDEX `idx_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- MENU + ACCESS: "Offer Management" parent with Offer Master / Offer Mapping
-- children. Idempotent: only creates rows that don't already exist.
-- ============================================
SET @offer_menu_exists = (SELECT COUNT(*) FROM menu WHERE label = 'Offer Management' AND link = '#');
SET @sql = IF(@offer_menu_exists = 0,
  'INSERT INTO menu (label, link, parent, sort, icon, active) VALUES (''Offer Management'', ''#'', 1, 6, ''fa fa-tags'', 1)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @offer_parent = (SELECT MIN(id_menu) FROM menu WHERE label = 'Offer Management' AND link = '#');

SET @sub_exists = (SELECT COUNT(*) FROM menu WHERE link = 'admin_ret_offer/offer_master/list');
SET @sql = IF(@sub_exists = 0,
  CONCAT('INSERT INTO menu (label, link, parent, sort, icon, active) VALUES (''Offer Master'', ''admin_ret_offer/offer_master/list'', ', @offer_parent, ', 1, ''fa fa-percent'', 1)'),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @offer_master_menu = (SELECT id_menu FROM menu WHERE link = 'admin_ret_offer/offer_master/list' LIMIT 1);

SET @sub_exists = (SELECT COUNT(*) FROM menu WHERE link = 'admin_ret_offer/offer_mapping/list');
SET @sql = IF(@sub_exists = 0,
  CONCAT('INSERT INTO menu (label, link, parent, sort, icon, active) VALUES (''Offer Mapping'', ''admin_ret_offer/offer_mapping/list'', ', @offer_parent, ', 2, ''fa fa-sitemap'', 1)'),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @offer_mapping_menu = (SELECT id_menu FROM menu WHERE link = 'admin_ret_offer/offer_mapping/list' LIMIT 1);

-- Access: profile 1 & 2 = full CRUD, profile 3 = view-only (mirrors POS module setup)
INSERT IGNORE INTO access (id_profile, id_menu, `view`, `add`, `edit`, `delete`)
VALUES (1, @offer_parent, 1, 1, 1, 1), (1, @offer_master_menu, 1, 1, 1, 1), (1, @offer_mapping_menu, 1, 1, 1, 1);

INSERT IGNORE INTO access (id_profile, id_menu, `view`, `add`, `edit`, `delete`)
VALUES (2, @offer_parent, 1, 1, 1, 1), (2, @offer_master_menu, 1, 1, 1, 1), (2, @offer_mapping_menu, 1, 1, 1, 1);

INSERT IGNORE INTO access (id_profile, id_menu, `view`, `add`, `edit`, `delete`)
VALUES (3, @offer_parent, 1, 0, 0, 0), (3, @offer_master_menu, 1, 0, 0, 0), (3, @offer_mapping_menu, 1, 0, 0, 0);

SET SQL_SAFE_UPDATES = 1;

-- DOWN
-- DELETE a FROM access a INNER JOIN menu m ON a.id_menu = m.id_menu WHERE m.parent = (SELECT id_menu FROM menu WHERE label = 'Offer Management' AND link = '#' LIMIT 1);
-- DELETE FROM access WHERE id_menu = (SELECT id_menu FROM menu WHERE label = 'Offer Management' AND link = '#' LIMIT 1);
-- DELETE FROM menu WHERE parent = (SELECT id_menu FROM (SELECT id_menu FROM menu WHERE label = 'Offer Management' AND link = '#' LIMIT 1) t);
-- DELETE FROM menu WHERE label = 'Offer Management' AND link = '#';
-- DROP TABLE IF EXISTS `ret_offer_mapping`;
-- DROP TABLE IF EXISTS `ret_offer_age_slab`;
-- DROP TABLE IF EXISTS `ret_offer_master`;
