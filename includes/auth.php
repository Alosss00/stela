<?php
require_once __DIR__ . '/config.php';

// Calculate base URL dynamically for redirects
$script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// Determine how deep we are in the folder structure
$depth = 0;
if (preg_match('#/pages/(admin|user|dept|ktt)$#', $script_path)) {
    $depth = 2;
} elseif (preg_match('#/(api|utils|migrations)$#', $script_path)) {
    $depth = 1;
}

// Calculate base path by going up $depth levels
$base_path = $script_path;
for ($i = 0; $i < $depth; $i++) {
    $base_path = dirname($base_path);
}
// Normalize: remove trailing slash but keep single "/" if at root
$base_path = rtrim($base_path, '/');
if ($base_path === '' || $base_path === '\\') {
    $base_path = '';
}

// Define AUTH_BASE_URL for redirects
if (!defined('AUTH_BASE_URL')) {
    define('AUTH_BASE_URL', $base_path);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . AUTH_BASE_URL . '/index.php');
    exit();
}

// Function to check if user has permission to access a page
function checkPageAccess($allowed_roles = []) {
    if (empty($allowed_roles)) {
        return true; // No restriction
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        global $base_path;
        header('Location: ' . AUTH_BASE_URL . '/pages/admin/dashboard.php');
        exit();
    }
    return true;
}

// Function to check if user is admin
function isAdmin() {
    return $_SESSION['role'] == 'admin';
}

// Function to check if user is KTT
function isKTT() {
    return $_SESSION['role'] == 'ktt';
}

// Function to check if user is company user
function isUser() {
    return $_SESSION['role'] == 'user';
}

// Function to check if user is department user
function isDepartmentUser() {
    return $_SESSION['role'] == 'department_user' || (!empty($_SESSION['department']) && empty($_SESSION['company_name']));
}

// Function to check if user is superadmin
function isSuperadmin() {
    return $_SESSION['role'] == 'superadmin';
}

// Function to check if user has department (for filtering)
function hasDepartment() {
    return !empty($_SESSION['department']);
}

// Function to get current department
function getCurrentDepartment() {
    return $_SESSION['department'] ?? null;
}

// Function to get current company
function getCurrentCompany() {
    return $_SESSION['company_name'] ?? null;
}

// Function to get all departments list
function getDepartmentsList() {
    return [
        'HCBP',
        'Mining Operation',
        'Principal Mining',
        'Mining Tech Service',
        'Process Plant',
        'Maintenance',
        'Metallurgy',
        'Project',
        'OHS',
        'Environmental',
        'HSE&Formalities',
        'Exploration',
        'Underground',
        'CSR',
        'Compliance',
        'Commercial',
        'Finance&Accounting',
        'IT'
    ];
}
?>


