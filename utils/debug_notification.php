<?php
/**
 * Debug Notification Calls
 * Add logging to see what's happening
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

echo "=== DEBUG NOTIFICATION ISSUES ===\n\n";

// Check if notification files are properly configured
echo "1. Checking notification system files...\n";

$files = [
    'includes/notifications.php' => 'Notification service',
    'vendor/autoload.php' => 'PHPMailer library'
];

foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        echo "   ✓ $desc: $file\n";
    } else {
        echo "   ❌ $desc: $file NOT FOUND\n";
    }
}

echo "\n2. Testing NotificationService class...\n";
require_once 'includes/notifications.php';

try {
    $notif = new NotificationService();
    echo "   ✓ NotificationService instantiated successfully\n";
} catch (Exception $e) {
    echo "   ❌ Error creating NotificationService: " . $e->getMessage() . "\n";
    exit;
}

echo "\n3. Checking admin contacts...\n";
$db = new Database();
$admins = $db->query("SELECT id, username, email FROM users WHERE role = 'admin' AND is_active = 1")->fetch_all(MYSQLI_ASSOC);

if (empty($admins)) {
    echo "   ❌ No active admins found!\n";
} else {
    echo "   ✓ Found " . count($admins) . " admin(s):\n";
    foreach ($admins as $admin) {
        echo "      - {$admin['username']}: {$admin['email']}\n";
    }
}

echo "\n4. Checking SMTP configuration...\n";
echo "   Host: smtp.gmail.com:587\n";
echo "   Username: agriawanwiranto5@gmail.com\n";
echo "   Password: " . (strlen('msoxtvqbgyptkonl') == 16 ? "✓ Set (16 chars)" : "❌ Invalid") . "\n";

echo "\n5. Testing actual email send...\n";
echo "   Attempting to send test notification...\n";

// Get a test employee
$employee = $db->query("SELECT id, employee_code, full_name, contractor_company FROM employees WHERE id = 1")->fetch_assoc();

if ($employee) {
    echo "   Employee found: {$employee['full_name']} (ID: {$employee['id']})\n";
    
    try {
        echo "   Calling notifyNewEmployeeAdded({$employee['id']}, '{$employee['contractor_company']}')...\n";
        
        $result = $notif->notifyNewEmployeeAdded($employee['id'], $employee['contractor_company']);
        
        if ($result) {
            echo "   ✅ SUCCESS! Email sent.\n";
        } else {
            echo "   ❌ FAILED! Function returned false.\n";
        }
    } catch (Exception $e) {
        echo "   ❌ EXCEPTION: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ❌ No test employee found\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "\nNEXT STEPS:\n";
echo "1. Check the output above for any errors\n";
echo "2. If email sent successfully, check Gmail inbox\n";
echo "3. If failed, check the error messages above\n";
echo "4. Check PHP error log for detailed errors\n";
echo str_repeat("=", 80) . "\n";

