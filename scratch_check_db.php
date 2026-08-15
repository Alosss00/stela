<?php
require 'bootstrap/app.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->prepare("SELECT id, username, password, full_name, role, company_name, department, is_active, 0 as failed_login_attempts, NULL as locked_until FROM users WHERE username = ? AND is_active = 1");
if (!$stmt) {
    echo "Prepare failed: " . $conn->error . "\n";
} else {
    echo "Prepare succeeded!\n";
}
