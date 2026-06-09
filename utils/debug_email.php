<?php
/**
 * Debug Email Delivery
 * Check if email was actually sent and troubleshoot delivery issues
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "=== DEBUG EMAIL DELIVERY ===\n\n";

// 1. Check SMTP configuration
echo "1. SMTP Configuration:\n";
echo "   Host: smtp.gmail.com:587\n";
echo "   Username: agriawanwiranto5@gmail.com\n";
echo "   Password: " . (strlen('oyqhifxaegvrymmr') > 0 ? "✓ Set (" . strlen('oyqhifxaegvrymmr') . " chars)" : "✗ Empty") . "\n\n";

// 2. Check admin email
$db = new Database();
$admin = $db->query("SELECT email, full_name FROM users WHERE username = 'admin1' LIMIT 1")->fetch_assoc();
echo "2. Target Email:\n";
echo "   To: {$admin['email']}\n";
echo "   Name: {$admin['full_name']}\n\n";

// 3. Try sending with detailed error reporting
echo "3. Sending Test Email with Debug Info...\n";
echo str_repeat("-", 80) . "\n";

try {
    $mail = new PHPMailer(true);
    
    // Enable verbose debug output
    $mail->SMTPDebug = 2; // 0=off, 1=client, 2=client+server
    $mail->Debugoutput = function($str, $level) {
        echo "DEBUG [$level]: $str\n";
    };
    
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'agriawanwiranto5@gmail.com';
    $mail->Password   = 'oyqhifxaegvrymmr';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    
    // Recipients
    $mail->setFrom('agriawanwiranto5@gmail.com', 'Mining System - Test');
    $mail->addAddress($admin['email'], $admin['full_name']);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'TEST EMAIL - ' . date('Y-m-d H:i:s');
    $mail->Body    = '<h1>Test Email</h1><p>This is a test email from Mining Appointment System.</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>';
    $mail->AltBody = 'Test email from Mining System - ' . date('Y-m-d H:i:s');
    
    $result = $mail->send();
    
    echo str_repeat("-", 80) . "\n";
    echo "\n✅ Email SENT SUCCESSFULLY!\n\n";
    
    echo "4. Where to Check:\n";
    echo "   - Gmail Inbox: https://mail.google.com/mail/u/0/#inbox\n";
    echo "   - Spam Folder: https://mail.google.com/mail/u/0/#spam\n";
    echo "   - All Mail: https://mail.google.com/mail/u/0/#all\n";
    echo "   - Search: subject:(TEST EMAIL)\n\n";
    
    echo "5. Troubleshooting:\n";
    echo "   • Email might take 1-2 minutes to arrive\n";
    echo "   • Check Spam/Promotions/Updates folders\n";
    echo "   • Search inbox for: from:agriawanwiranto5@gmail.com\n";
    echo "   • Add to contacts to prevent spam filtering\n\n";
    
} catch (Exception $e) {
    echo str_repeat("-", 80) . "\n";
    echo "\n❌ EMAIL FAILED!\n";
    echo "Error: {$mail->ErrorInfo}\n\n";
    
    echo "Common Issues:\n";
    echo "• App Password incorrect or expired\n";
    echo "• 2-Step Verification not enabled\n";
    echo "• Gmail blocking less secure apps\n";
    echo "• Firewall blocking port 587\n";
}

echo "================================================================================\n";

