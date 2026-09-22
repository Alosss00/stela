<?php
require_once dirname(__DIR__) . '/bootstrap/app.php';
require_once dirname(__DIR__) . '/bootstrap/app.php';
require_once dirname(__DIR__) . '/app/Helpers/auth_helper.php';

header('Content-Type: application/json');

try {
    // Only superadmin can access this
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
        throw new Exception('Unauthorized access', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // [SECURITY] Validate CSRF Token for state-changing API
    if (!function_exists('verify_csrf_token')) {
        require_once dirname(__DIR__) . '/app/Helpers/csrf_helper.php';
    }
    if (!verify_csrf_token()) {
        throw new Exception('Invalid or missing CSRF token', 403);
    }

    $db = new Database();
    
    // List of allowed setting keys
    $allowed_keys = [
        'app_name', 'app_env', 'maintenance_mode', 'support_email',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass',
        'fonnte_token', 'default_pagination', 'session_timeout',
        'password_policy_strict', 'primary_color'
    ];

    $updated_count = 0;
    $values_sql = [];
    $params = [];
    $types = '';
    
    // Process form data
    foreach ($_POST as $key => $value) {
        if (in_array($key, $allowed_keys)) {
            // For boolean settings (checkboxes)
            if ($key === 'maintenance_mode' || $key === 'password_policy_strict') {
                $value = ($value === 'on' || $value === '1' || $value === true) ? '1' : '0';
            }
            
            // Special handling for passwords
            if ($key === 'smtp_pass' && empty($value)) {
                continue; // Don't update password if empty
            }

            $values_sql[] = "(?, ?)";
            $params[] = $key;
            $params[] = $value;
            $types .= 'ss';
            $updated_count++;
        }
    }
    
    // Handle checkboxes that might not be sent in POST when unchecked
    $checkboxes = ['maintenance_mode', 'password_policy_strict'];
    foreach ($checkboxes as $chk) {
        if (!isset($_POST[$chk])) {
            $values_sql[] = "(?, ?)";
            $params[] = $chk;
            $params[] = '0';
            $types .= 'ss';
            $updated_count++;
        }
    }

    if (!empty($values_sql)) {
        $query = "INSERT INTO settings (setting_key, setting_value) VALUES " . implode(', ', $values_sql) . " 
                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $db->query($query, $params, $types);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Settings updated successfully'
    ]);

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
