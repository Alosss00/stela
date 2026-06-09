<?php
/**
 * Final Test - Send Real Notification Email
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/notifications.php';

echo "=== FINAL EMAIL TEST ===\n\n";

// Check configuration
echo "1. Configuration Check:\n";
echo "   SMTP: smtp.gmail.com:587\n";
echo "   From: agriawanwiranto09@gmail.com\n";
echo "   Password Length: " . strlen('msoxtvqbgyptkonl') . " chars ✓\n\n";

// Get admin
$db = new Database();
$admin = $db->query("SELECT email, full_name FROM users WHERE username = 'admin1' LIMIT 1")->fetch_assoc();
echo "2. Target Email:\n";
echo "   To: {$admin['email']}\n";
echo "   Name: {$admin['full_name']}\n\n";

// Send notification
echo "3. Sending Email via NotificationService...\n";
echo str_repeat("-", 80) . "\n";

$notif = new NotificationService();

// Get a real employee for testing
$employee = $db->query("SELECT id, employee_code, full_name, contractor_company FROM employees WHERE id = 1")->fetch_assoc();

if ($employee) {
    echo "   Employee: {$employee['full_name']} ({$employee['employee_code']})\n";
    echo "   Company: {$employee['contractor_company']}\n\n";
    
    try {
        $result = $notif->notifyNewEmployeeAdded($employee['id'], $employee['contractor_company']);
        
        if ($result) {
            echo str_repeat("-", 80) . "\n";
            echo "\n✅ EMAIL SENT SUCCESSFULLY!\n\n";
            echo "Where to check:\n";
            echo "1. Gmail Inbox: https://mail.google.com/mail/u/0/#inbox\n";
            echo "2. Search: from:agriawanwiranto09@gmail.com\n";
            echo "3. Subject: Tenaga Kerja Baru Perlu Verifikasi\n";
            echo "4. Check Spam folder if not in Inbox\n\n";
            echo "⏰ Email should arrive within 1-2 minutes\n\n";
        } else {
            echo "\n❌ Email failed to send\n";
            echo "Check error log for details\n\n";
        }
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "❌ No test employee found\n";
}

// Check notification logs
echo "4. Checking Notification Logs:\n";
$logs = $db->query("SELECT * FROM notification_logs ORDER BY created_at DESC LIMIT 3");
if ($logs && $logs->num_rows > 0) {
    while ($log = $logs->fetch_assoc()) {
        echo "   [{$log['created_at']}] {$log['notification_type']} - {$log['company_name']}\n";
    }
} else {
    echo "   No logs yet (table may not exist)\n";
}

echo "\n" . str_repeat("=", 80) . "\n";

