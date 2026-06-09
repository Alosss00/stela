-- =====================================================
-- KTT Approval Workflow Enhancement Migration
-- =====================================================
-- Purpose: Add new fields to support enhanced KTT approval workflow
--         - Separate status tracking for KTT MSM and KTT TTN
--         - Visibility control for selective KTT review after resubmit
--         - Track last rejecting KTT and resubmit reason
--
-- Requirements:
--   - Any KTT rejection immediately routes to admin (no waiting for 2nd KTT)
--   - After resubmit, only show to KTT who rejected last
--   - Approved KTT's decision is preserved
--   - Apply to all pending appointments
-- =====================================================

-- Add new fields to appointments table
ALTER TABLE `appointments`
ADD COLUMN `ktt_msm_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'KTT MSM approval status' AFTER `ktt2_approved_date`,
ADD COLUMN `ktt_ttn_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'KTT TTN approval status' AFTER `ktt_msm_status`,
ADD COLUMN `requires_ktt_msm_review` TINYINT(1) DEFAULT 1 COMMENT 'Flag: should KTT MSM review this appointment?' AFTER `ktt_ttn_status`,
ADD COLUMN `requires_ktt_ttn_review` TINYINT(1) DEFAULT 1 COMMENT 'Flag: should KTT TTN review this appointment?' AFTER `requires_ktt_msm_review`,
ADD COLUMN `resubmit_reason` TEXT NULL COMMENT 'Combined admin + KTT rejection notes for user' AFTER `requires_ktt_ttn_review`,
ADD COLUMN `last_rejected_by_ktt` ENUM('msm', 'ttn') NULL COMMENT 'Which KTT type rejected last (msm or ttn)' AFTER `resubmit_reason`;

-- =====================================================
-- Initialize existing pending appointments
-- =====================================================
-- Set default values for existing pending appointments
-- This ensures the new workflow applies to all pending data

UPDATE `appointments`
SET
    `ktt_msm_status` = 'pending',
    `ktt_ttn_status` = 'pending',
    `requires_ktt_msm_review` = 1,
    `requires_ktt_ttn_review` = 1,
    `resubmit_reason` = NULL,
    `last_rejected_by_ktt` = NULL
WHERE `status` = 'pending';

-- For appointments where ktt1_approved_by is set (KTT MSM already approved)
UPDATE `appointments`
SET
    `ktt_msm_status` = 'approved',
    `requires_ktt_msm_review` = 0
WHERE `status` = 'pending'
AND `ktt1_approved_by` IS NOT NULL;

-- For appointments where ktt2_approved_by is set (KTT TTN already approved)
UPDATE `appointments`
SET
    `ktt_ttn_status` = 'approved',
    `requires_ktt_ttn_review` = 0
WHERE `status` = 'pending'
AND `ktt2_approved_by` IS NOT NULL;

-- For rejected_by_ktt appointments, determine which KTT rejected
UPDATE `appointments` a
LEFT JOIN `ktt_approvals` ka ON a.id = ka.appointment_id AND ka.action = 'reject'
SET
    a.`last_rejected_by_ktt` = CASE
        WHEN ka.ktt_user_id = 7 THEN 'msm'  -- user_id 7 = KTT MSM
        WHEN ka.ktt_user_id = 8 THEN 'ttn'  -- user_id 8 = KTT TTN
        ELSE NULL
    END,
    a.`ktt_msm_status` = CASE
        WHEN ka.ktt_user_id = 7 THEN 'rejected'
        WHEN a.ktt1_approved_by IS NOT NULL THEN 'approved'
        ELSE 'pending'
    END,
    a.`ktt_ttn_status` = CASE
        WHEN ka.ktt_user_id = 8 THEN 'rejected'
        WHEN a.ktt2_approved_by IS NOT NULL THEN 'approved'
        ELSE 'pending'
    END
WHERE a.status = 'rejected_by_ktt';

-- For approved appointments, both KTT statuses should be approved
UPDATE `appointments`
SET
    `ktt_msm_status` = 'approved',
    `ktt_ttn_status` = 'approved',
    `requires_ktt_msm_review` = 0,
    `requires_ktt_ttn_review` = 0
WHERE `status` = 'approved';

-- =====================================================
-- Rollback Script (if needed)
-- =====================================================
-- IMPORTANT: Only run this if you need to rollback the migration
-- This will remove the new columns
--
-- ALTER TABLE `appointments`
-- DROP COLUMN `ktt_msm_status`,
-- DROP COLUMN `ktt_ttn_status`,
-- DROP COLUMN `requires_ktt_msm_review`,
-- DROP COLUMN `requires_ktt_ttn_review`,
-- DROP COLUMN `resubmit_reason`,
-- DROP COLUMN `last_rejected_by_ktt`;

-- =====================================================
-- Migration Complete
-- =====================================================
-- After running this migration:
-- 1. All pending appointments will show to both KTT for review
-- 2. Appointments with existing approvals will skip that KTT
-- 3. Rejected appointments will track which KTT rejected
-- 4. New workflow will be active for all appointments
-- =====================================================
