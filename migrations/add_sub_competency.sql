-- Migration: Add sub_competency column to employees table
-- This column is used for Tenaga Teknis competency type sub-classification
-- Changed from VARCHAR(10) to VARCHAR(255) to accommodate full sub-competency names

ALTER TABLE employees ADD COLUMN sub_competency VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL AFTER competency_name;

-- Add index for faster queries
ALTER TABLE employees ADD INDEX idx_sub_competency (sub_competency);
