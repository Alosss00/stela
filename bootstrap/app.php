<?php
/**
 * Application Bootstrap / Initialization File
 * 
 * Central bootstrapper that loads configuration, database models,
 * helpers, services, and session settings.
 */

// Prevent direct script execution if accessed via URL
if (basename($_SERVER['PHP_SELF'] ?? '') === 'app.php') {
    die('Direct access not permitted');
}

// 1. Load Configurations
require_once dirname(__DIR__) . '/config/app.php';

// 2. Load Helpers
require_once dirname(__DIR__) . '/app/Helpers/url_helper.php';
require_once dirname(__DIR__) . '/app/Helpers/i18n_helper.php';
require_once dirname(__DIR__) . '/app/Helpers/upload_helper.php';

// 3. Load Models
require_once dirname(__DIR__) . '/app/Models/Database.php';

// 4. Load Services
require_once dirname(__DIR__) . '/app/Services/NotificationService.php';
if (file_exists(dirname(__DIR__) . '/app/Services/ElasticsearchService.php')) {
    require_once dirname(__DIR__) . '/app/Services/ElasticsearchService.php';
}

// Note: auth_helper.php is included on pages that require authentication checks.

// Auto-login if remember_me cookie exists and not logged in yet
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $cookie_parts = explode(':', $_COOKIE['remember_me']);
    if (count($cookie_parts) === 2) {
        list($selector, $validator) = $cookie_parts;
        
        $stmt = $conn->prepare("SELECT user_tokens.hashed_validator, user_tokens.user_id, users.username, users.full_name, users.role, users.company_name, users.department FROM user_tokens JOIN users ON user_tokens.user_id = users.id WHERE user_tokens.selector = ? AND user_tokens.expires_at > NOW() AND users.is_active = 1");
        if ($stmt) {
            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows == 1) {
                $tokenData = $result->fetch_assoc();
                if (hash_equals($tokenData['hashed_validator'], hash('sha256', $validator))) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $tokenData['user_id'];
                    $_SESSION['username'] = $tokenData['username'];
                    $_SESSION['full_name'] = $tokenData['full_name'];
                    $_SESSION['role'] = $tokenData['role'];
                    $_SESSION['company_name'] = $tokenData['company_name'];
                    $_SESSION['department'] = $tokenData['department'];
                    $_SESSION['last_activity'] = time();
                    
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
                }
            }
            $stmt->close();
        }
    }
}
