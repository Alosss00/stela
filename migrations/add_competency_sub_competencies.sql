-- Migration: Create competency_sub_competencies table
-- This table stores the relationship between competencies and their sub-competencies

CREATE TABLE IF NOT EXISTS `competency_sub_competencies` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `competency_id` int NOT NULL,
  `sub_competency_name` varchar(255) NOT NULL,
  `sub_competency_level` int DEFAULT 1,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_competency_subcompetency` (`competency_id`, `sub_competency_name`),
  KEY `idx_competency_id` (`competency_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample data for the example in the requirement
-- Example: Petugas Industrial Hygiene with its sub-competencies
-- First, find the competency_id for "Petugas Industrial Hygiene" if it exists

-- You can use this query to insert data after the competency is created:
-- INSERT INTO `competency_sub_competencies` (`competency_id`, `sub_competency_name`, `sub_competency_level`, `description`, `is_active`)
-- SELECT id, 'Ahli Higiene Industri Muda', 1, 'Young Industrial Hygiene Expert', 1 FROM competencies WHERE competency_name = 'Petugas Industrial Hygiene'
-- UNION ALL
-- SELECT id, 'Ahli Higiene Industri Madya', 2, 'Middle Industrial Hygiene Expert', 1 FROM competencies WHERE competency_name = 'Petugas Industrial Hygiene'
-- UNION ALL
-- SELECT id, 'Ahli Higiene Industri Utama', 3, 'Main Industrial Hygiene Expert', 1 FROM competencies WHERE competency_name = 'Petugas Industrial Hygiene';
