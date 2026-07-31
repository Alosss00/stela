-- Migration: Add created_by column to employees table
-- This column tracks which user added the employee record
ALTER TABLE employees ADD COLUMN created_by INT DEFAULT NULL AFTER statement_file;
ALTER TABLE employees ADD INDEX idx_created_by (created_by);
