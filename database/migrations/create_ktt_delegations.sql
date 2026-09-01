-- ================================================
-- MIGRATION: Create ktt_delegations table
-- Date: 2026-09-01
-- Purpose: Support KTT delegation system — allows a KTT to temporarily
--          delegate their approval authority to another user.
-- ================================================

CREATE TABLE IF NOT EXISTS `ktt_delegations` (
    `id`               INT NOT NULL AUTO_INCREMENT,
    `ktt_user_id`      INT NOT NULL COMMENT 'User ID of the KTT who is delegating (delegator)',
    `ktt_type`         ENUM('msm','ttn') NOT NULL COMMENT 'Which KTT role is being delegated',
    `delegate_user_id` INT NOT NULL COMMENT 'User ID receiving the delegation authority',
    `start_date`       DATE NOT NULL COMMENT 'Delegation valid from this date',
    `end_date`         DATE NOT NULL COMMENT 'Delegation valid until this date (inclusive)',
    `reason`           TEXT NULL COMMENT 'Reason for delegation (leave, official duty, etc.)',
    `status`           ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    `cancelled_by`     INT NULL COMMENT 'User ID who cancelled the delegation',
    `cancelled_at`     DATETIME NULL COMMENT 'When the delegation was cancelled',
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by`       INT NOT NULL COMMENT 'User ID who created this delegation',
    PRIMARY KEY (`id`),
    FOREIGN KEY (`ktt_user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`delegate_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`)       REFERENCES `users`(`id`),
    INDEX `idx_active_delegation`    (`ktt_type`, `status`, `start_date`, `end_date`),
    INDEX `idx_delegate_user`        (`delegate_user_id`, `status`),
    INDEX `idx_ktt_user`             (`ktt_user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks KTT delegation of approval authority to substitute users';

-- ================================================
-- BUSINESS RULES (enforced at application layer):
-- 1. Only 1 active delegation per ktt_type at a time
-- 2. delegate_user_id cannot be the same as ktt_user_id
-- 3. end_date must be >= start_date
-- 4. Delegation auto-expires when end_date < CURDATE()
-- ================================================

-- ================================================
-- ROLLBACK (if needed)
-- ================================================
-- DROP TABLE IF EXISTS `ktt_delegations`;
