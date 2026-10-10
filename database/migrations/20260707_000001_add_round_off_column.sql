-- Date : 07-07-26

-- Description: Added round off column is estimation table.

-- UP

ALTER TABLE ret_estimation ADD COLUMN round_off DECIMAL(10,2) DEFAULT 0.00 AFTER total_cost;