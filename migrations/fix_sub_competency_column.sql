-- Migration: Fix sub_competency column capacity
-- This migration changes the sub_competency column from VARCHAR(10) to VARCHAR(255)
-- to accommodate full sub-competency names

-- Check and modify the column definition
ALTER TABLE employees MODIFY COLUMN sub_competency VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;

-- Verify the change was successful
DESCRIBE employees;
