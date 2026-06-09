<?php
/**
 * Test SMTP Authentication Directly
 */

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "=== TEST SMTP AUTHENTICATION ===\n\n";

// Test configuration
$configs = [
    'Config 1 - agriawanwiranto09 (dengan 09)' => [
        'username' => 'agriawanwiranto09@gmail.com',
        'password' => 'msoxtvqbgyptkonl'
    ],
    'Config 2 - Check if password has spaces' => [
        'username' => 'agriawanwiranto09@gmail.com',
        'password' => str_replace(' ', '', 'msox tvqb gypt konl')
    ]
];

foreach ($configs as $name => $config) {
    echo "Testing: $name\n";
    echo "  Username: {$config['username']}\n";
    echo "  Password length: " . strlen($config['password']) . " chars\n";
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = 0; // No debug output
        
        $mail->setFrom($config['username'], 'Test');
        $mail->addAddress('agriawanwiranto09@gmail.com', 'Test');
        $mail->Subject = 'Test SMTP Auth';
        $mail->Body = 'Testing authentication';
        
        $result = $mail->send();
        
        if ($result) {
            echo "  ✅ SUCCESS! Authentication working.\n\n";
            break; // Exit loop if successful
        }
    } catch (Exception $e) {
        echo "  ❌ FAILED: " . $e->getMessage() . "\n";
        echo "  Error Info: " . $mail->ErrorInfo . "\n\n";
    }
}

echo str_repeat("=", 80) . "\n";
echo "\nIf all failed, possible causes:\n";
echo "1. App Password expired or revoked\n";
echo "2. Generate new App Password: https://myaccount.google.com/apppasswords\n";
echo "3. Update password in includes/notifications.php line 21\n";
echo str_repeat("=", 80) . "\n";

