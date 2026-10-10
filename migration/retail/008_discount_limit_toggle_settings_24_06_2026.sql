-- Migration: Add calc_percentage column to ret_taging table
-- Used by: calculation_based_on = 5 (Fixed Rate based on Metal Rate)
-- Stores the percentage used in the derived rate formula for type 5 tags
-- Date: 2026-06-24

ALTER TABLE ret_taging
ADD COLUMN calc_percentage DECIMAL(10,2) DEFAULT NULL
AFTER sell_rate;
