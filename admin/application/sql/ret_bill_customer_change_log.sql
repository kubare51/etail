-- ================================================================
-- Customer Change Audit Log Table
-- Feature: Customer Reassignment on Bill Edit (Task c6d6a23d)
-- Created: 2026-06-04
-- ================================================================

CREATE TABLE IF NOT EXISTS `ret_bill_customer_change_log` (
  `id_change_log` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bill_id` INT NOT NULL,
  `bill_no` VARCHAR(50) DEFAULT NULL COMMENT 'Bill number for display',
  `old_cus_id` INT NOT NULL,
  `old_cus_name` VARCHAR(200) DEFAULT NULL,
  `old_cus_mobile` VARCHAR(20) DEFAULT NULL,
  `old_cus_gst` VARCHAR(50) DEFAULT NULL,
  `old_cus_pan` VARCHAR(20) DEFAULT NULL,
  `old_billing_for` TINYINT DEFAULT NULL COMMENT '1=B2C, 2=B2B',
  `new_cus_id` INT NOT NULL,
  `new_cus_name` VARCHAR(200) DEFAULT NULL,
  `new_cus_mobile` VARCHAR(20) DEFAULT NULL,
  `new_cus_gst` VARCHAR(50) DEFAULT NULL,
  `new_cus_pan` VARCHAR(20) DEFAULT NULL,
  `new_billing_for` TINYINT DEFAULT NULL COMMENT '1=B2C, 2=B2B',
  `reason` TEXT DEFAULT NULL COMMENT 'Reason for customer change',
  `cascaded_tables` TEXT DEFAULT NULL COMMENT 'JSON array of tables that were updated',
  `impact_snapshot` TEXT DEFAULT NULL COMMENT 'JSON of impact preview at time of change',
  `changed_by` INT NOT NULL COMMENT 'Employee ID who made the change',
  `changed_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_branch` INT DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  INDEX `idx_bill_id` (`bill_id`),
  INDEX `idx_old_cus` (`old_cus_id`),
  INDEX `idx_new_cus` (`new_cus_id`),
  INDEX `idx_changed_on` (`changed_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Audit trail for customer reassignment on bills';

-- Register customer_change URL in form_logger (manual step — already done in hooks/formlogger.php)
-- array("form" => "Bill Customer Change", "operation" => "update", "url" => "admin_ret_billing/paymentmode_edit/customer_change"),
