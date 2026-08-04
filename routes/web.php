<?php
/**
 * Web Routes Definitions & Dispatcher
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

$route_map = [
    '/' => 'auth/login.php',
    '/login' => 'auth/login.php',
    '/logout' => 'logout.php',
    '/admin/dashboard' => 'admin/dashboard.php',
    '/admin/employees' => 'admin/employees.php',
    '/admin/appointments' => 'admin/appointments.php',
    '/admin/reports' => 'admin/reports.php',
    '/dept/dashboard' => 'dept/dashboard.php',
    '/dept/employees' => 'dept/employees.php',
    '/dept/appointments' => 'dept/appointments.php',
    '/user/dashboard' => 'user/dashboard.php',
    '/user/employees' => 'user/employees.php',
    '/user/appointments' => 'user/appointments.php',
    '/ktt/approval' => 'ktt/approval.php',
    '/superadmin/dashboard' => 'superadmin/dashboard.php',
    '/superadmin/users' => 'superadmin/users.php',
    '/superadmin/roles_permissions' => 'superadmin/roles_permissions.php',
    '/superadmin/companies' => 'superadmin/companies.php',
    '/superadmin/departments' => 'superadmin/departments.php',
    '/superadmin/master_data' => 'superadmin/master_data.php',
    '/superadmin/settings' => 'superadmin/settings.php',
    '/superadmin/monitoring_logs' => 'superadmin/monitoring_logs.php',
    '/superadmin/elasticsearch_manage' => 'superadmin/elasticsearch_manage.php',
    '/superadmin/backup_restore' => 'superadmin/backup_restore.php',
];

function dispatch_route($path) {
    global $route_map;
    $clean_path = rtrim($path, '/');
    if (isset($route_map[$clean_path])) {
        $view = VIEW_PATH . '/' . ltrim($route_map[$clean_path], '/');
        if (file_exists($view)) {
            require_once $view;
            return true;
        }
    }
    return false;
}
