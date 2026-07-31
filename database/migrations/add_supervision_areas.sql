-- Migration: Add supervision_areas table
-- Date: 2026-02-08
-- Description: Create table to manage supervision areas dynamically

-- Create supervision_areas table
CREATE TABLE IF NOT EXISTS `supervision_areas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `area_name` VARCHAR(255) NOT NULL COMMENT 'Nama area pengawasan',
  `area_code` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Kode singkatan area',
  `description` TEXT NULL DEFAULT NULL COMMENT 'Deskripsi area',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=aktif, 0=nonaktif',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_area_name` (`area_name`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial data
INSERT INTO `supervision_areas` (`area_name`, `area_code`, `description`, `is_active`) VALUES
('PT Meares Soputan Mining (MSM)', 'MSM', 'Area pengawasan PT MSM', 1),
('PT Tambang Tondano Nusajaya (TTN)', 'TTN', 'Area pengawasan PT TTN', 1);
