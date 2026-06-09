<?php
/**
 * Test Email Sending with PHPMailer
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/notifications.php';

echo "=== TEST EMAIL DENGAN PHPMAILER ===\n\n";

// Get notification service
$notif = new NotificationService();

// Get admin email
$db = new Database();
$admin = $db->query("SELECT email, full_name FROM users WHERE username = 'admin1' LIMIT 1")->fetch_assoc();

if (!$admin || empty($admin['email'])) {
    echo "❌ Admin email tidak ditemukan!\n";
    exit;
}

echo "📧 Mengirim test email ke: {$admin['email']} ({$admin['full_name']})\n\n";

// Create test employee notification
$test_employee = [
    'id' => 999,
    'employee_code' => 'TEST-001',
    'full_name' => 'Test Employee',
    'position' => 'Test Position',
    'contractor_company' => 'PT Test Company'
];

echo "⏳ Mengirim email...\n";

// Try to send notification
try {
    $result = $notif->notifyNewEmployeeAdded(1, 'PT DNX Indonesia');
    
    if ($result) {
        echo "\n✅ Email berhasil dikirim!\n";
        echo "   Silakan cek inbox email Anda.\n\n";
    } else {
        echo "\n❌ Email gagal dikirim.\n";
        echo "   Periksa konfigurasi SMTP di includes/notifications.php\n\n";
    }
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
}

echo "================================================================================\n";
echo "PENTING: Untuk menggunakan Gmail SMTP, Anda perlu:\n";
echo "1. Aktifkan 2-Step Verification di akun Gmail\n";
echo "2. Generate App Password di: https://myaccount.google.com/apppasswords\n";
echo "3. Masukkan App Password (16 karakter) ke includes/notifications.php\n";
echo "   pada property: private \$smtp_password = 'xxxx xxxx xxxx xxxx';\n";
echo "================================================================================\n";

