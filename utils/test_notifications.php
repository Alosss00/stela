<?php
/**
 * Test Script for Notification System
 * Run this to test if WhatsApp and Email notifications are working
 */

require_once 'includes/db.php';
require_once 'includes/notifications.php';

echo "=== NOTIFICATION SYSTEM TEST ===\n\n";

$db = new Database();

// Check if admin contacts are configured
echo "1. Checking Admin Contacts...\n";
echo str_repeat("-", 80) . "\n";

$admins = $db->query("SELECT id, username, full_name, email, phone, role FROM users WHERE role = 'admin' AND is_active = 1");

if ($admins && $admins->num_rows > 0) {
    $admin_count = 0;
    $admins_with_contacts = 0;
    
    while ($admin = $admins->fetch_assoc()) {
        $admin_count++;
        echo "Admin #{$admin_count}: {$admin['username']} ({$admin['full_name']})\n";
        echo "  Email: " . (!empty($admin['email']) ? $admin['email'] : "❌ NOT SET") . "\n";
        echo "  Phone: " . (!empty($admin['phone']) ? $admin['phone'] : "❌ NOT SET") . "\n";
        
        if (!empty($admin['email']) || !empty($admin['phone'])) {
            $admins_with_contacts++;
        }
        echo "\n";
    }
    
    if ($admins_with_contacts == 0) {
        echo "⚠️  WARNING: No admin has email or phone configured!\n";
        echo "Please update admin contacts using:\n";
        echo "UPDATE users SET email = 'admin@example.com', phone = '6285173023567' WHERE username = 'admin';\n\n";
    } else {
        echo "✓ Found $admins_with_contacts admin(s) with contact information\n\n";
    }
} else {
    echo "❌ No admin users found!\n\n";
    exit;
}

// Test with sample data
echo "2. Testing Notification Functions...\n";
echo str_repeat("-", 80) . "\n\n";

// Get a sample employee for testing
$sample_employee = $db->query("SELECT id, employee_code, full_name, contractor_company FROM employees WHERE is_active = 1 LIMIT 1")->fetch_assoc();

if ($sample_employee) {
    echo "Using sample employee:\n";
    echo "  ID: {$sample_employee['id']}\n";
    echo "  Code: {$sample_employee['employee_code']}\n";
    echo "  Name: {$sample_employee['full_name']}\n";
    echo "  Company: {$sample_employee['contractor_company']}\n\n";
    
    // Test new employee notification (DRY RUN - won't actually send)
    echo "Testing New Employee Notification message format...\n";
    $notificationService = new NotificationService();
    
    // We can't directly test without modifying the class, so we'll just check if it initializes
    echo "✓ NotificationService initialized successfully\n\n";
    
    // Show what the message would look like
    echo "Sample message that would be sent:\n";
    echo str_repeat("-", 80) . "\n";
    echo "🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\n";
    echo "Perusahaan *{$sample_employee['contractor_company']}* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n";
    echo "📋 *Detail Karyawan:*\n";
    echo "• ID BADGE: {$sample_employee['employee_code']}\n";
    echo "• Nama: {$sample_employee['full_name']}\n";
    echo "• Perusahaan: {$sample_employee['contractor_company']}\n\n";
    echo "⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n";
    echo str_repeat("-", 80) . "\n\n";
} else {
    echo "⚠️  No employees found for testing\n\n";
}

// Test appointment rejection notification
$sample_appointment = $db->query("
    SELECT a.id, a.appointment_number, 
           e.full_name, e.employee_code, e.contractor_company,
           p.position_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    WHERE a.status = 'rejected_by_ktt'
    LIMIT 1
")->fetch_assoc();

if ($sample_appointment) {
    echo "Testing Appointment Rejection Notification message format...\n";
    echo "Using sample appointment:\n";
    echo "  No. Surat: {$sample_appointment['appointment_number']}\n";
    echo "  Karyawan: {$sample_appointment['full_name']}\n\n";
    
    echo "Sample message that would be sent:\n";
    echo str_repeat("-", 80) . "\n";
    echo "⚠️ *NOTIFIKASI SURAT DITOLAK*\n\n";
    echo "Surat penunjukan telah ditolak oleh KTT dan memerlukan review admin:\n\n";
    echo "📋 *Detail Surat:*\n";
    echo "• No. Surat: {$sample_appointment['appointment_number']}\n";
    echo "• Karyawan: {$sample_appointment['full_name']} ({$sample_appointment['employee_code']})\n";
    echo "• Jabatan: {$sample_appointment['position_name']}\n";
    echo "• Perusahaan: {$sample_appointment['contractor_company']}\n";
    echo str_repeat("-", 80) . "\n\n";
}

// Check notification logs table
echo "3. Checking Notification Logs...\n";
echo str_repeat("-", 80) . "\n";

$check_table = $db->query("SHOW TABLES LIKE 'notification_logs'");
if ($check_table && $check_table->num_rows > 0) {
    $log_count = $db->query("SELECT COUNT(*) as count FROM notification_logs")->fetch_assoc()['count'];
    echo "✓ Notification logs table exists\n";
    echo "  Total logs: $log_count\n\n";
    
    if ($log_count > 0) {
        echo "Recent notifications:\n";
        $recent_logs = $db->query("SELECT * FROM notification_logs ORDER BY sent_at DESC LIMIT 5");
        while ($log = $recent_logs->fetch_assoc()) {
            echo "  - [{$log['notification_type']}] {$log['company_name']} at {$log['sent_at']}\n";
        }
        echo "\n";
    }
} else {
    echo "ℹ️  Notification logs table doesn't exist yet (will be created on first notification)\n\n";
}

echo "4. Checking Email Delivery Logs...\n";
echo str_repeat("-", 80) . "\n";

$check_email_table = $db->query("SHOW TABLES LIKE 'notification_email_logs'");
if ($check_email_table && $check_email_table->num_rows > 0) {
    $email_log_count = $db->query("SELECT COUNT(*) as count FROM notification_email_logs")->fetch_assoc()['count'];
    echo "✓ Email delivery logs table exists\n";
    echo "  Total email logs: $email_log_count\n\n";

    if ($email_log_count > 0) {
        echo "Recent email delivery logs:\n";
        $recent_email_logs = $db->query("SELECT recipient_email, email_is_valid, email_sent, error_message, created_at FROM notification_email_logs ORDER BY created_at DESC LIMIT 5");
        while ($log = $recent_email_logs->fetch_assoc()) {
            $valid = $log['email_is_valid'] ? 'valid' : 'invalid';
            $sent = $log['email_sent'] ? 'sent' : 'not sent';
            echo "  - {$log['recipient_email']} | {$valid} | {$sent} | {$log['created_at']}\n";
        }
        echo "\n";
    }
} else {
    echo "ℹ️  Email delivery logs table doesn't exist yet (will be created on first email attempt)\n\n";
}

echo str_repeat("=", 80) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 80) . "\n\n";

// Configuration check
echo "Configuration Status:\n";
echo "  ✓ NotificationService class loaded\n";
echo "  ✓ Database connection working\n";

// Check if config is set
$config_file = file_get_contents('includes/notifications.php');
if (strpos($config_file, 'YOUR_WHATSAPP_API_KEY') !== false) {
    echo "  ⚠️  WhatsApp API key not configured yet\n";
    echo "     Edit includes/notifications.php and set your API key\n";
} else {
    echo "  ✓ WhatsApp API key configured\n";
}

if (strpos($config_file, 'noreply@mining-system.com') !== false) {
    echo "  ⚠️  Email sender not configured yet\n";
    echo "     Edit includes/notifications.php and set your email\n";
} else {
    echo "  ✓ Email sender configured\n";
}

echo "\n";
echo "Next Steps:\n";
echo "1. Update admin contacts (email and phone) in database\n";
echo "2. Configure WhatsApp API key in includes/notifications.php\n";
echo "3. Configure email sender in includes/notifications.php\n";
echo "4. Test by adding a new employee as company user\n";
echo "5. Monitor notification_logs table for sent notifications\n\n";

echo "For detailed setup instructions, see: NOTIFICATION_SETUP_GUIDE.md\n\n";
?>

