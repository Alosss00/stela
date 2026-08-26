<?php
/**
 * URL and Path Helper Functions
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', ROOT_PATH . '/config');
}
if (!defined('VIEW_PATH')) {
    define('VIEW_PATH', ROOT_PATH . '/resources');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}
if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', ROOT_PATH . '/public/assets');
}
if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', ROOT_PATH . '/storage/uploads');
}

// Calculate base URL dynamically
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $current_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    
    $root_dir = '';
    if (!empty($doc_root) && strpos($current_path, $doc_root) === 0) {
        $relative = substr($current_path, strlen($doc_root));
        // Remove subdirectories from path detection if accessed from pages/ or resources/
        $relative = preg_replace('#/(pages|resources)/(admin|user|dept|ktt|api|superadmin|auth)$#', '', $relative);
        $relative = preg_replace('#/(api|resources)$#', '', $relative);
        $root_dir = $relative;
    }
    define('BASE_URL', rtrim($protocol . '://' . $host . $root_dir, '/'));
}

if (!defined('AUTH_BASE_URL')) {
    define('AUTH_BASE_URL', BASE_URL);
}

// Helper function to get URL for assets
if (!function_exists('asset_url')) {
    function asset_url($path) {
        $clean_path = ltrim($path, '/');
        if (file_exists(PUBLIC_PATH . '/assets/' . $clean_path)) {
            return BASE_URL . '/public/assets/' . $clean_path;
        }
        return BASE_URL . '/assets/' . $clean_path;
    }
}

// Helper function to get URL for pages
if (!function_exists('page_url')) {
    function page_url($path) {
        return BASE_URL . '/' . ltrim($path, '/');
    }
}

// Helper function to get view path
if (!function_exists('view_path')) {
    function view_path($path) {
        return VIEW_PATH . '/' . ltrim($path, '/');
    }
}

// Helper function to get include path (compatibility wrapper)
if (!function_exists('include_path')) {
    function include_path($file) {
        if (file_exists(VIEW_PATH . '/layouts/' . ltrim($file, '/'))) {
            return VIEW_PATH . '/layouts/' . ltrim($file, '/');
        }
        return ROOT_PATH . '/includes/' . ltrim($file, '/');
    }
}

// Check if user is logged in
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

// Redirect helper
if (!function_exists('redirect')) {
    function redirect($url) {
        header('Location: ' . $url);
        exit();
    }
}

// Redirect to appropriate dashboard based on role
if (!function_exists('redirect_to_dashboard')) {
    function redirect_to_dashboard() {
        if (!is_logged_in()) {
            redirect(BASE_URL . '/index.php');
        }
        
        $role = $_SESSION['role'] ?? '';
        $department = $_SESSION['department'] ?? '';
        
        switch ($role) {
            case 'superadmin':
                redirect(BASE_URL . '/resources/superadmin/dashboard.php');
                break;
            case 'ktt':
                redirect(BASE_URL . '/resources/ktt/approval.php');
                break;
            case 'admin':
                redirect(BASE_URL . '/resources/admin/dashboard.php');
                break;
            case 'department_user':
                redirect(BASE_URL . '/resources/dept/dashboard.php');
                break;
            case 'user':
                if (!empty($department)) {
                    redirect(BASE_URL . '/resources/dept/dashboard.php');
                } else {
                    redirect(BASE_URL . '/resources/user/dashboard.php');
                }
                break;
            default:
                redirect(BASE_URL . '/index.php');
        }
    }
}

// Get current page name
if (!function_exists('get_current_page')) {
    function get_current_page() {
        return basename($_SERVER['PHP_SELF']);
    }
}

// Check if current page matches
if (!function_exists('is_current_page')) {
    function is_current_page($page) {
        return get_current_page() === $page;
    }
}
