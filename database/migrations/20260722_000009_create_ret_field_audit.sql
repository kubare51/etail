-- Migration: Create table ret_field_audit
-- Purpose: Generic, reusable audit trail for permission-gated field changes (old value -> new value).
--          First tenant: Stone Weight Markup % changes (module='tag'|'estimation', field_name='stone_markup_percent').
--          Designed to be reused by future old/new-tracking requirements (discount, wastage, rate overrides, etc.)
--          without adding another table - only `module`/`field_name` values change.
-- Safe: Uses IF NOT EXISTS

-- UP
CREATE TABLE IF NOT EXISTS `ret_field_audit` (
  `id_audit` int NOT NULL AUTO_INCREMENT,
  `event_id` varchar(36) NOT NULL COMMENT 'Groups all field-audit rows written by one save action',
  `module` varchar(30) NOT NULL COMMENT 'Business scope, e.g. tag, estimation',
  `table_name` varchar(64) NOT NULL COMMENT 'Physical table the changed row lives in',
  `ref_id` int NOT NULL COMMENT 'Main record id, e.g. tag_id / est_item_id',
  `sub_ref_id` int DEFAULT NULL COMMENT 'Child row id, e.g. tag_stone_id / est_item_stone_id',
  `field_name` varchar(50) NOT NULL COMMENT 'Name of the field that changed, e.g. stone_markup_percent',
  `old_value` varchar(100) DEFAULT NULL,
  `new_value` varchar(100) DEFAULT NULL,
  `changed_by` int NOT NULL COMMENT 'id_employee',
  `changed_on` datetime NOT NULL,
  `branch_id` int DEFAULT NULL,
  PRIMARY KEY (`id_audit`),
  KEY `idx_lookup` (`module`,`field_name`,`changed_on`),
  KEY `idx_record` (`table_name`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
