-- Migration: Add superadmin role to users table
-- Date: 2026-05-12
-- Description: Add superadmin role to ENUM field that can access all dashboards

ALTER TABLE users MODIFY COLUMN role ENUM('admin','ktt','user','department_user','superadmin') 
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user';

-- Ensure is_active column exists (in case of older schema)
ALTER TABLE users ADD COLUMN is_active tinyint(1) DEFAULT 1;

-- Create or update the initial superadmin user
INSERT INTO users (id, username, password, full_name, company_name, is_active, email, phone, role, created_at, updated_at, department)
VALUES (44, 'superadmin', '$2y$10$3IwZtgL1w3AEE4X05AP2DuzxuMiyt6HKRTPxKJl9UCyz7GzliSAj2', 'Super Administrator', NULL, 1, 'superadmin@mining.local', NULL, 'superadmin', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
  username = VALUES(username),
  password = VALUES(password),
  full_name = VALUES(full_name),
  company_name = VALUES(company_name),
  is_active = VALUES(is_active),
  email = VALUES(email),
  phone = VALUES(phone),
  role = VALUES(role),
  updated_at = VALUES(updated_at),
  department = VALUES(department);
