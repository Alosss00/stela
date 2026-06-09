<?php
/**
 * Application Bootstrap / Initialization File
 * 
 * This file should be included at the top of every page.
 * It handles path configuration and autoloading.
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'init.php') {
    die('Direct access not permitted');
}

// Define root path - the base directory of the application
define('ROOT_PATH', __DIR__);

// Define paths for different directories
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('PAGES_PATH', ROOT_PATH . '/pages');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/assets/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Base URL detection for links (auto-detect)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// Find the application root directory in URL
$root_dir = '';
$current_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);

// Calculate relative path from document root
if (strpos($current_path, $doc_root) === 0) {
    $relative = substr($current_path, strlen($doc_root));
    // Check if we're in a subdirectory (pages/admin, pages/user, etc.)
    if (preg_match('#/pages/(admin|user|dept|ktt|api)$#', $relative)) {
        $root_dir = preg_replace('#/pages/(admin|user|dept|ktt|api)$#', '', $relative);
    } elseif (preg_match('#/api$#', $relative)) {
        $root_dir = preg_replace('#/api$#', '', $relative);
    } else {
        $root_dir = $relative;
    }
}

define('BASE_URL', rtrim($protocol . '://' . $host . $root_dir, '/'));

// Helper function to get URL for assets
function asset_url($path) {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

// Helper function to get URL for pages
function page_url($path) {
    return BASE_URL . '/' . ltrim($path, '/');
}

// Helper function to get include path
function include_path($file) {
    return INCLUDES_PATH . '/' . ltrim($file, '/');
}

// Include core files
require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/i18n.php';
require_once INCLUDES_PATH . '/db.php';

/**
 * Session and Authentication Helpers
 */

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Redirect helper
function redirect($url) {
    header('Location: ' . $url);
    exit();
}

// Redirect to appropriate dashboard based on role
function redirect_to_dashboard() {
    if (!is_logged_in()) {
        redirect(BASE_URL . '/index.php');
    }
    
    $role = $_SESSION['role'] ?? '';
    $department = $_SESSION['department'] ?? '';
    
    switch ($role) {
        case 'ktt':
            redirect(BASE_URL . '/pages/ktt/approval.php');
            break;
        case 'admin':
            redirect(BASE_URL . '/pages/admin/dashboard.php');
            break;
        case 'department_user':
            redirect(BASE_URL . '/pages/dept/dashboard.php');
            break;
        case 'user':
            if (!empty($department)) {
                redirect(BASE_URL . '/pages/dept/dashboard.php');
            } else {
                redirect(BASE_URL . '/pages/user/dashboard.php');
            }
            break;
        default:
            redirect(BASE_URL . '/index.php');
    }
}

// Get current page name
function get_current_page() {
    return basename($_SERVER['PHP_SELF']);
}

// Check if current page matches
function is_current_page($page) {
    return get_current_page() === $page;
}
?>

