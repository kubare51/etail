-- Migration: Update ret_billing metal_type to NULL where it is 0
-- Reason: metal_type is optional (based on ret_settings metal-wise bill number).
-- Existing records with 0 will fail the fk_bill_metal constraint. We convert them to NULL.

UPDATE `ret_billing` SET `metal_type` = NULL WHERE `metal_type` = 0;
