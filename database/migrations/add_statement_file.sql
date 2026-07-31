-- Migration: Add statement_file column to employees table
-- Date: 2026-02-02
-- Description: Replace signature_file with statement_file for mandatory statement letter upload

-- Add new column statement_file
ALTER TABLE `employees` 
ADD COLUMN `statement_file` VARCHAR(255) NULL DEFAULT NULL 
COMMENT 'Path to employee statement letter (PDF with wet signature)' 
AFTER `cv_file`;
