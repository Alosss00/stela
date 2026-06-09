-- Migration: Add rejection history fields to appointments table
-- Purpose: Store rejection history even after ktt_approvals records are deleted
-- Date: 2026-02-15

ALTER TABLE appointments
ADD COLUMN IF NOT EXISTS last_rejection_notes TEXT NULL COMMENT 'Stores rejection reason from last KTT rejection',
ADD COLUMN IF NOT EXISTS last_rejection_by_name VARCHAR(255) NULL COMMENT 'Stores name of KTT who rejected',
ADD COLUMN IF NOT EXISTS last_rejection_date DATETIME NULL COMMENT 'Stores date of last rejection';

-- Add index for better query performance
CREATE INDEX IF NOT EXISTS idx_last_rejection_date ON appointments(last_rejection_date);

-- Verification query - Check if fields were added successfully
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'appointments'
AND COLUMN_NAME IN ('last_rejection_notes', 'last_rejection_by_name', 'last_rejection_date')
ORDER BY ORDINAL_POSITION;
