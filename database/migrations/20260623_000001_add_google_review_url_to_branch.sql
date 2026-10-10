-- Migration: Add google_review_url column to `branch`
-- Date: 23-06-2026
-- Purpose: Store the Google Review URL link for each branch
-- Safe: Column checked via INFORMATION_SCHEMA before adding

-- UP

ALTER TABLE `branch` ADD COLUMN `google_review_url` TEXT DEFAULT NULL COMMENT 'Google Review URL for the branch' AFTER `gst_number`;
