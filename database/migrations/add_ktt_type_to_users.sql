-- ================================================
-- MIGRATION: Add ktt_type column to users table
-- Date: 2026-09-01
-- Purpose: Remove hardcoded user ID 7/8 for KTT MSM/TTN detection.
--          Each KTT user now carries their type in the database.
-- ================================================

-- 1. Add ktt_type column to users table
ALTER TABLE `users`
ADD COLUMN `ktt_type` ENUM('msm','ttn') NULL
COMMENT 'KTT designation: msm = KTT MSM, ttn = KTT TTN. NULL if not KTT.'
AFTER `role`;

-- 2. Set ktt_type for existing KTT users based on known user IDs
--    IMPORTANT: Update these IDs if your KTT users have different IDs!
UPDATE `users` SET `ktt_type` = 'msm' WHERE `id` = 7 AND `role` = 'ktt';
UPDATE `users` SET `ktt_type` = 'ttn' WHERE `id` = 8 AND `role` = 'ktt';

-- 3. Add index for fast lookup of KTT users by type
CREATE INDEX `idx_users_ktt_type` ON `users` (`ktt_type`, `role`);

-- ================================================
-- VERIFICATION
-- ================================================
-- Run this to verify:
-- SELECT id, username, full_name, role, ktt_type FROM users WHERE role = 'ktt';

-- ================================================
-- ROLLBACK (if needed)
-- ================================================
-- ALTER TABLE `users` DROP INDEX `idx_users_ktt_type`;
-- ALTER TABLE `users` DROP COLUMN `ktt_type`;
