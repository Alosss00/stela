<?php
require_once __DIR__ . '/bootstrap/app.php';
$db = new Database();

$sql = "CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if ($db->query($sql)) {
    echo "Table 'settings' created successfully or already exists.\n";
} else {
    echo "Error creating table: " . $db->getConnection()->error . "\n";
    exit(1);
}

// Insert default values
$defaults = [
    'app_name' => 'STELA System',
    'app_env' => 'production',
    'maintenance_mode' => '0',
    'support_email' => 'support@stela-app.local',
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => '465',
    'smtp_user' => 'sentry@tokaguard.com',
    'smtp_pass' => 'Tosar123@',
    'fonnte_token' => 'BVru1eLXHL2it4WozxLH',
    'default_pagination' => '20',
    'session_timeout' => '1800',
    'password_policy_strict' => '1',
    'primary_color' => '#2563eb'
];

foreach ($defaults as $key => $val) {
    $key_esc = $db->escapeString($key);
    $val_esc = $db->escapeString($val);
    $db->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('$key_esc', '$val_esc')");
}
echo "Default settings inserted.\n";
