-- Create roles table
CREATE TABLE IF NOT EXISTS `roles` (
    `id` int NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL UNIQUE,
    `description` varchar(255) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create permissions table
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` int NOT NULL AUTO_INCREMENT,
    `module` varchar(50) NOT NULL,
    `name` varchar(100) NOT NULL UNIQUE,
    `description` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create role_permissions table
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` int NOT NULL,
    `permission_id` int NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default roles (matching existing enum)
INSERT IGNORE INTO `roles` (`name`, `description`) VALUES
('superadmin', 'Super Administrator with full access'),
('admin', 'Administrator for operational tasks'),
('ktt', 'KTT for approval workflow'),
('department_user', 'Department user'),
('user', 'Regular user');

-- Seed basic permissions for superadmin
INSERT IGNORE INTO `permissions` (`module`, `name`, `description`) VALUES
('user_management', 'view_users', 'View users list'),
('user_management', 'create_users', 'Create new users'),
('user_management', 'edit_users', 'Edit existing users'),
('user_management', 'delete_users', 'Delete users'),
('user_management', 'manage_roles', 'Manage roles and permissions'),
('company_management', 'manage_companies', 'Manage companies'),
('department_management', 'manage_departments', 'Manage departments'),
('master_data', 'manage_master_data', 'Manage master data (positions, competencies)'),
('system', 'manage_settings', 'Manage system settings'),
('system', 'view_logs', 'View audit and error logs'),
('system', 'manage_elasticsearch', 'Manage Elasticsearch'),
('system', 'backup_restore', 'Backup and restore database'),
('operational', 'view_reports', 'View operational reports');

-- Assign all permissions to superadmin
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p WHERE r.name = 'superadmin';

-- Assign some basic permissions to admin for reports
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p WHERE r.name = 'admin' AND p.name IN ('view_reports');
