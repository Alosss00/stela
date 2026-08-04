<?php
$permissions = [
    ['module' => 'dashboard', 'name' => 'dashboard.view', 'desc' => 'View dashboard'],
    ['module' => 'employee', 'name' => 'employee.view', 'desc' => 'View employees'],
    ['module' => 'employee', 'name' => 'employee.create', 'desc' => 'Create employees'],
    ['module' => 'employee', 'name' => 'employee.update', 'desc' => 'Update employees'],
    ['module' => 'employee', 'name' => 'employee.delete', 'desc' => 'Delete employees'],
    ['module' => 'employee', 'name' => 'employee.export', 'desc' => 'Export employees'],
    ['module' => 'appointment', 'name' => 'appointment.view', 'desc' => 'View appointments'],
    ['module' => 'appointment', 'name' => 'appointment.create', 'desc' => 'Create appointments'],
    ['module' => 'appointment', 'name' => 'appointment.update', 'desc' => 'Update appointments'],
    ['module' => 'appointment', 'name' => 'appointment.delete', 'desc' => 'Delete appointments'],
    ['module' => 'appointment', 'name' => 'appointment.approve', 'desc' => 'Approve appointments'],
    ['module' => 'appointment', 'name' => 'appointment.reject', 'desc' => 'Reject appointments'],
    ['module' => 'appointment', 'name' => 'appointment.export', 'desc' => 'Export appointments'],
    ['module' => 'certificate', 'name' => 'certificate.view', 'desc' => 'View certificates'],
    ['module' => 'certificate', 'name' => 'certificate.update', 'desc' => 'Update certificates'],
    ['module' => 'certificate', 'name' => 'certificate.expired', 'desc' => 'View expired certificates'],
    ['module' => 'certificate', 'name' => 'certificate.export', 'desc' => 'Export certificates'],
    ['module' => 'reports', 'name' => 'reports.view', 'desc' => 'View reports'],
    ['module' => 'reports', 'name' => 'reports.export', 'desc' => 'Export reports'],
    ['module' => 'monitoring', 'name' => 'monitoring.view', 'desc' => 'View monitoring logs'],
    ['module' => 'user', 'name' => 'user.view', 'desc' => 'View users'],
    ['module' => 'user', 'name' => 'user.create', 'desc' => 'Create users'],
    ['module' => 'user', 'name' => 'user.update', 'desc' => 'Update users'],
    ['module' => 'user', 'name' => 'user.delete', 'desc' => 'Delete users'],
    ['module' => 'user', 'name' => 'user.resetpassword', 'desc' => 'Reset user passwords'],
    ['module' => 'role', 'name' => 'role.view', 'desc' => 'View roles'],
    ['module' => 'role', 'name' => 'role.create', 'desc' => 'Create roles'],
    ['module' => 'role', 'name' => 'role.update', 'desc' => 'Update roles'],
    ['module' => 'role', 'name' => 'role.delete', 'desc' => 'Delete roles'],
    ['module' => 'permission', 'name' => 'permission.view', 'desc' => 'View permissions'],
    ['module' => 'permission', 'name' => 'permission.create', 'desc' => 'Create permissions'],
    ['module' => 'permission', 'name' => 'permission.update', 'desc' => 'Update permissions'],
    ['module' => 'permission', 'name' => 'permission.delete', 'desc' => 'Delete permissions'],
    ['module' => 'elasticsearch', 'name' => 'elasticsearch.view', 'desc' => 'View elasticsearch settings'],
    ['module' => 'elasticsearch', 'name' => 'elasticsearch.sync', 'desc' => 'Sync elasticsearch'],
    ['module' => 'elasticsearch', 'name' => 'elasticsearch.reindex', 'desc' => 'Reindex elasticsearch'],
    ['module' => 'settings', 'name' => 'settings.view', 'desc' => 'View settings'],
    ['module' => 'settings', 'name' => 'settings.update', 'desc' => 'Update settings'],
    ['module' => 'access', 'name' => 'admin.access', 'desc' => 'Access admin area'],
    ['module' => 'access', 'name' => 'dept.access', 'desc' => 'Access department area'],
    ['module' => 'access', 'name' => 'user.access', 'desc' => 'Access user area'],
    ['module' => 'access', 'name' => 'ktt.access', 'desc' => 'Access KTT area'],
    ['module' => 'backup', 'name' => 'backup.create', 'desc' => 'Create backups'],
    ['module' => 'backup', 'name' => 'backup.restore', 'desc' => 'Restore backups'],
];

echo "INSERT IGNORE INTO `permissions` (`module`, `name`, `description`) VALUES\n";
$values = [];
foreach ($permissions as $p) {
    $values[] = "('".$p['module']."', '".$p['name']."', '".$p['desc']."')";
}
echo implode(",\n", $values) . ";\n\n";

$roles = [
    'admin' => ['admin.access', 'dashboard.view', 'employee.view', 'employee.create', 'employee.update', 'employee.delete', 'employee.export', 'appointment.view', 'appointment.create', 'appointment.update', 'appointment.delete', 'appointment.export', 'certificate.view', 'certificate.update', 'certificate.expired', 'certificate.export', 'reports.view', 'reports.export', 'settings.update', 'user.resetpassword'],
    'ktt' => ['ktt.access', 'dashboard.view', 'appointment.view', 'appointment.approve', 'appointment.reject'],
    'department_user' => ['dept.access', 'dashboard.view', 'employee.view', 'employee.create', 'employee.update', 'appointment.view', 'certificate.view'],
    'user' => ['user.access', 'dashboard.view', 'employee.view', 'appointment.view', 'certificate.view']
];

echo "-- Note: The role_permissions insert uses subqueries assuming roles and permissions tables are populated.\n";
echo "INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES\n";
$rp_values = [];
foreach ($roles as $role_name => $perms) {
    foreach ($perms as $p_name) {
        $rp_values[] = "((SELECT id FROM roles WHERE name='$role_name'), (SELECT id FROM permissions WHERE name='$p_name'))";
    }
}
echo implode(",\n", $rp_values) . ";\n";
