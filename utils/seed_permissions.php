<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Models/Database.php';
$db = new Database();

$permissions = [
    // Dashboard
    ['module' => 'dashboard', 'name' => 'dashboard.view', 'desc' => 'View dashboard'],
    
    // Employee
    ['module' => 'employee', 'name' => 'employee.view', 'desc' => 'View employees'],
    ['module' => 'employee', 'name' => 'employee.create', 'desc' => 'Create employees'],
    ['module' => 'employee', 'name' => 'employee.update', 'desc' => 'Update employees'],
    ['module' => 'employee', 'name' => 'employee.delete', 'desc' => 'Delete employees'],
    ['module' => 'employee', 'name' => 'employee.export', 'desc' => 'Export employees'],
    
    // Appointment
    ['module' => 'appointment', 'name' => 'appointment.view', 'desc' => 'View appointments'],
    ['module' => 'appointment', 'name' => 'appointment.create', 'desc' => 'Create appointments'],
    ['module' => 'appointment', 'name' => 'appointment.update', 'desc' => 'Update appointments'],
    ['module' => 'appointment', 'name' => 'appointment.delete', 'desc' => 'Delete appointments'],
    ['module' => 'appointment', 'name' => 'appointment.approve', 'desc' => 'Approve appointments'],
    ['module' => 'appointment', 'name' => 'appointment.reject', 'desc' => 'Reject appointments'],
    ['module' => 'appointment', 'name' => 'appointment.export', 'desc' => 'Export appointments'],
    
    // Certificate
    ['module' => 'certificate', 'name' => 'certificate.view', 'desc' => 'View certificates'],
    ['module' => 'certificate', 'name' => 'certificate.update', 'desc' => 'Update certificates'],
    ['module' => 'certificate', 'name' => 'certificate.expired', 'desc' => 'View expired certificates'],
    ['module' => 'certificate', 'name' => 'certificate.export', 'desc' => 'Export certificates'],
    
    // Reports
    ['module' => 'reports', 'name' => 'reports.view', 'desc' => 'View reports'],
    ['module' => 'reports', 'name' => 'reports.export', 'desc' => 'Export reports'],
    
    // Monitoring
    ['module' => 'monitoring', 'name' => 'monitoring.view', 'desc' => 'View monitoring logs'],
    
    // Users
    ['module' => 'user', 'name' => 'user.view', 'desc' => 'View users'],
    ['module' => 'user', 'name' => 'user.create', 'desc' => 'Create users'],
    ['module' => 'user', 'name' => 'user.update', 'desc' => 'Update users'],
    ['module' => 'user', 'name' => 'user.delete', 'desc' => 'Delete users'],
    ['module' => 'user', 'name' => 'user.resetpassword', 'desc' => 'Reset user passwords'],
    
    // Roles
    ['module' => 'role', 'name' => 'role.view', 'desc' => 'View roles'],
    ['module' => 'role', 'name' => 'role.create', 'desc' => 'Create roles'],
    ['module' => 'role', 'name' => 'role.update', 'desc' => 'Update roles'],
    ['module' => 'role', 'name' => 'role.delete', 'desc' => 'Delete roles'],
    
    // Permissions
    ['module' => 'permission', 'name' => 'permission.view', 'desc' => 'View permissions'],
    ['module' => 'permission', 'name' => 'permission.create', 'desc' => 'Create permissions'],
    ['module' => 'permission', 'name' => 'permission.update', 'desc' => 'Update permissions'],
    ['module' => 'permission', 'name' => 'permission.delete', 'desc' => 'Delete permissions'],
    
    // Elasticsearch
    ['module' => 'elasticsearch', 'name' => 'elasticsearch.view', 'desc' => 'View elasticsearch settings'],
    ['module' => 'elasticsearch', 'name' => 'elasticsearch.sync', 'desc' => 'Sync elasticsearch'],
    ['module' => 'elasticsearch', 'name' => 'elasticsearch.reindex', 'desc' => 'Reindex elasticsearch'],
    
    // Settings
    ['module' => 'settings', 'name' => 'settings.view', 'desc' => 'View settings'],
    ['module' => 'settings', 'name' => 'settings.update', 'desc' => 'Update settings'],
    
    // UI Routing Access
    ['module' => 'access', 'name' => 'admin.access', 'desc' => 'Access admin area'],
    ['module' => 'access', 'name' => 'dept.access', 'desc' => 'Access department area'],
    ['module' => 'access', 'name' => 'user.access', 'desc' => 'Access user area'],
    ['module' => 'access', 'name' => 'ktt.access', 'desc' => 'Access KTT area'],
    
    // Backup
    ['module' => 'backup', 'name' => 'backup.create', 'desc' => 'Create backups'],
    ['module' => 'backup', 'name' => 'backup.restore', 'desc' => 'Restore backups'],
];

echo "Inserting permissions...\n";
$stmt = $db->prepare("INSERT IGNORE INTO permissions (module, name, description) VALUES (?, ?, ?)");
foreach ($permissions as $p) {
    $stmt->bind_param("sss", $p['module'], $p['name'], $p['desc']);
    $stmt->execute();
}
$stmt->close();

// Helper to fetch permission IDs
$perm_ids = [];
$res = $db->query("SELECT id, name FROM permissions");
while ($row = $res->fetch_assoc()) {
    $perm_ids[$row['name']] = $row['id'];
}

// Map roles
$roles = [
    'admin' => [
        'admin.access', 'dashboard.view', 'employee.view', 'employee.create', 'employee.update', 'employee.delete', 'employee.export',
        'appointment.view', 'appointment.create', 'appointment.update', 'appointment.delete', 'appointment.export',
        'certificate.view', 'certificate.update', 'certificate.expired', 'certificate.export',
        'reports.view', 'reports.export', 'settings.update', 'user.resetpassword'
    ],
    'ktt' => [
        'ktt.access', 'dashboard.view', 'appointment.view', 'appointment.approve', 'appointment.reject'
    ],
    'department_user' => [
        'dept.access', 'dashboard.view', 'employee.view', 'employee.create', 'employee.update', 'appointment.view', 'certificate.view'
    ],
    'user' => [
        'user.access', 'dashboard.view', 'employee.view', 'appointment.view', 'certificate.view'
    ]
];

echo "Mapping permissions to roles...\n";
$role_res = $db->query("SELECT id, name FROM roles");
$role_ids = [];
while ($row = $role_res->fetch_assoc()) {
    $role_ids[$row['name']] = $row['id'];
}

$pivot_stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
foreach ($roles as $role_name => $perms) {
    if (isset($role_ids[$role_name])) {
        $r_id = $role_ids[$role_name];
        foreach ($perms as $p_name) {
            if (isset($perm_ids[$p_name])) {
                $p_id = $perm_ids[$p_name];
                $pivot_stmt->bind_param("ii", $r_id, $p_id);
                $pivot_stmt->execute();
            }
        }
    }
}
$pivot_stmt->close();

echo "RBAC permissions successfully seeded!\n";
