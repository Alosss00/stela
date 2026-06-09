-- ============================================
-- Migration: Add cert_type column to employee_certifications table
-- Date: 2026-02-21
-- Purpose: Store certificate type that user inputs (Attendance/Participant, Competent, Training, etc.)
-- ============================================

-- Add cert_type column after certification_id
ALTER TABLE employee_certifications 
ADD COLUMN cert_type VARCHAR(100) DEFAULT NULL 
AFTER certification_id;

-- Optional: Update existing records with cert_type from certifications master table
-- This is useful for backward compatibility
UPDATE employee_certifications ec
INNER JOIN certifications c ON ec.certification_id = c.id
SET ec.cert_type = c.cert_type
WHERE ec.cert_type IS NULL AND c.cert_type IS NOT NULL;
