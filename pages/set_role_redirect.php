<?php
/**
 * Role Redirect Handler
 * 
 * Allows superadmin to switch to different role dashboards
 * This mimics the normal login behavior: sets session role, then redirects to dashboard
 * 
 * Usage: /pages/set_role_redirect.php?role=admin
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Initialize database connection
$db = new Database();

// Check if user is logged in and is superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'superadmin') {
    // Not superadmin, redirect to login
    header('Location: ' . AUTH_BASE_URL . '/index.php');
    exit();
}

// Get the target role from GET parameter
$target_role = isset($_GET['role']) ? $_GET['role'] : '';

// Validate target_role is one of the allowed roles
$allowed_roles = ['admin', 'ktt', 'department_user', 'user'];
if (!in_array($target_role, $allowed_roles)) {
    // Invalid role, redirect back to superadmin dashboard
    header('Location: superadmin/dashboard.php');
    exit();
}

// Update session role to match the target role
$_SESSION['role'] = $target_role;

// Clear/set related session variables based on target role
if ($target_role === 'department_user') {
    // For department_user, set a default department if not already set
    if (empty($_SESSION['department'])) {
        try {
            $result = @$db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' LIMIT 1");
            if ($result && $row = $result->fetch_assoc()) {
                $_SESSION['department'] = $row['department'];
            } else {
                $_SESSION['department'] = 'General'; // Fallback default
            }
        } catch (Exception $e) {
            $_SESSION['department'] = 'General'; // Fallback if query fails
        }
    }
    unset($_SESSION['company_name']); // Department users don't have company_name
} elseif ($target_role === 'user') {
    // For user role, handle company_name
    if (empty($_SESSION['company_name'])) {
        $_SESSION['company_name'] = 'All Companies'; // Show all companies view
    }
    unset($_SESSION['department']); // Users don't have department unless they're also department_user
} else {
    // For admin and ktt, clear both department and company_name
    unset($_SESSION['department']);
    unset($_SESSION['company_name']);
}

// Ensure session is written before redirect
session_write_close();

// Redirect to appropriate dashboard based on role
switch ($target_role) {
    case 'admin':
        header('Location: admin/dashboard.php', true, 302);
        break;
    case 'ktt':
        header('Location: ktt/approval.php', true, 302);
        break;
    case 'department_user':
        header('Location: dept/dashboard.php', true, 302);
        break;
    case 'user':
        header('Location: user/dashboard.php', true, 302);
        break;
    default:
        // This shouldn't happen, but just in case
        header('Location: superadmin/dashboard.php', true, 302);
}
exit();
?>
