-- Migration untuk menambah sistem approval dengan admin review
-- File ini dijalankan setelah versi lama untuk upgrade database

-- Modifikasi tabel appointments untuk menambah field admin approval
ALTER TABLE `appointments` 
MODIFY `status` enum('draft','pending','approved','rejected','expired','verified','rejected_by_ktt','pending_admin_review') DEFAULT 'draft',
ADD COLUMN `admin_approved_by` int(11) DEFAULT NULL AFTER `approved_by`,
ADD COLUMN `admin_approved_date` datetime DEFAULT NULL AFTER `admin_approved_by`,
ADD COLUMN `admin_approval_action` enum('send_to_user','send_to_ktt') DEFAULT NULL AFTER `admin_approved_date`,
ADD COLUMN `admin_approval_notes` text DEFAULT NULL AFTER `admin_approval_action`,
ADD COLUMN `rejected_by_ktt_user_id` int(11) DEFAULT NULL AFTER `admin_approval_notes`;

-- Tambah index untuk admin review tracking
ALTER TABLE `appointments`
ADD INDEX `idx_admin_approved` (`admin_approved_by`, `status`);

-- Create table untuk tracking alasan rejection dari KTT (optional, untuk historical record)
-- Jika ingin lebih detail tracking per KTT rejection
CREATE TABLE IF NOT EXISTS `ktt_rejections` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` int(11) NOT NULL,
  `ktt_user_id` int(11) NOT NULL,
  `rejection_notes` text DEFAULT NULL,
  `rejection_date` datetime DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`ktt_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
