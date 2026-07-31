<?php
/**
 * Role Redirect Handler
 * 
 * Allows superadmin to switch to different role dashboards
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';
require_once APP_PATH . '/Helpers/auth_helper.php';

$db = new Database();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'superadmin') {
    redirect(BASE_URL . '/index.php');
}

$target_role = isset($_GET['role']) ? $_GET['role'] : '';
$allowed_roles = ['admin', 'ktt', 'department_user', 'user'];

if (!in_array($target_role, $allowed_roles)) {
    redirect(BASE_URL . '/pages/superadmin/dashboard.php');
}

$_SESSION['role'] = $target_role;

if ($target_role === 'department_user') {
    if (empty($_SESSION['department'])) {
        try {
            $result = @$db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' LIMIT 1");
            if ($result && $row = $result->fetch_assoc()) {
                $_SESSION['department'] = $row['department'];
            } else {
                $_SESSION['department'] = 'General';
            }
        } catch (Exception $e) {
            $_SESSION['department'] = 'General';
        }
    }
    unset($_SESSION['company_name']);
} elseif ($target_role === 'user') {
    if (empty($_SESSION['company_name'])) {
        $_SESSION['company_name'] = 'All Companies';
    }
    unset($_SESSION['department']);
} else {
    unset($_SESSION['department']);
    unset($_SESSION['company_name']);
}

session_write_close();

switch ($target_role) {
    case 'admin':
        redirect(BASE_URL . '/pages/admin/dashboard.php');
        break;
    case 'ktt':
        redirect(BASE_URL . '/pages/ktt/approval.php');
        break;
    case 'department_user':
        redirect(BASE_URL . '/pages/dept/dashboard.php');
        break;
    case 'user':
        redirect(BASE_URL . '/pages/user/dashboard.php');
        break;
    default:
        redirect(BASE_URL . '/pages/superadmin/dashboard.php');
}
