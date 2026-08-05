<?php
require_once dirname(__DIR__, 2) . '/config/app.php';

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
    // Attempt auto-login via remember_me cookie
    if (isset($_COOKIE['remember_me'])) {
        require_once dirname(__DIR__) . '/Models/Database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
        
        $stmt = $conn->prepare("SELECT user_tokens.hashed_validator, user_tokens.user_id, users.username, users.full_name, users.role, users.company_name, users.department FROM user_tokens JOIN users ON user_tokens.user_id = users.id WHERE user_tokens.selector = ? AND user_tokens.expires_at > NOW() AND users.is_active = 1");
        if ($stmt) {
            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows == 1) {
                $tokenData = $result->fetch_assoc();
                if (hash_equals($tokenData['hashed_validator'], hash('sha256', $validator))) {
                    // Valid token, log them in
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $tokenData['user_id'];
                    $_SESSION['username'] = $tokenData['username'];
                    $_SESSION['full_name'] = $tokenData['full_name'];
                    $_SESSION['role'] = $tokenData['role'];
                    $_SESSION['company_name'] = $tokenData['company_name'];
                    $_SESSION['department'] = $tokenData['department'];
                    $_SESSION['last_activity'] = time();
                    
                    // Fetch permissions
                    $perm_stmt = $conn->prepare("SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN roles r ON rp.role_id = r.id WHERE r.name = ?");
                    if ($perm_stmt) {
                        $perm_stmt->bind_param("s", $tokenData['role']);
                        $perm_stmt->execute();
                        $perm_result = $perm_stmt->get_result();
                        $permissions = [];
                        if ($perm_result) {
                            while ($p_row = $perm_result->fetch_assoc()) {
                                $permissions[] = $p_row['name'];
                            }
                        }
                        $_SESSION['permissions'] = $permissions;
                        $perm_stmt->close();
                    } else {
                        $_SESSION['permissions'] = [];
                    }
                } else {
                    // Invalid validator
                    header('Location: ' . AUTH_BASE_URL . '/index.php');
                    exit();
                }
            } else {
                // Token not found or expired
                header('Location: ' . AUTH_BASE_URL . '/index.php');
                exit();
            }
            $stmt->close();
        }
    } else {
        header('Location: ' . AUTH_BASE_URL . '/index.php');
        exit();
    }
}

// Session Idle Timeout Logic (30 minutes = 1800 seconds)
$timeout_duration = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: ' . AUTH_BASE_URL . '/index.php?error=timeout');
    exit();
}
$_SESSION['last_activity'] = time();


// Function to check if user has a specific permission
function hasPermission($permission_name) {
    if (isSuperadmin()) {
        return true;
    }
    $user_permissions = $_SESSION['permissions'] ?? [];
    return in_array($permission_name, $user_permissions);
}

// Helper to strictly require a permission. Halts execution if not met.
function requirePermission($permission_name) {
    if (!hasPermission($permission_name)) {
        header('HTTP/1.1 403 Forbidden');
        // If not AJAX, redirect to their dashboard or an error page
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
            header('Location: ' . AUTH_BASE_URL . '/index.php');
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access. Missing permission: ' . $permission_name]);
        }
        exit();
    }
}

// Function to check if user has permission to access a page
function checkPageAccess($allowed_roles = [], $required_permission = null) {
    if ($required_permission && !hasPermission($required_permission)) {
        global $base_path;
        header('Location: ' . AUTH_BASE_URL . '/pages/admin/dashboard.php');
        exit();
    }

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


