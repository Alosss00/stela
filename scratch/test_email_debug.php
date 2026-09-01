<?php
require_once __DIR__ . '/../bootstrap/app.php';
$autoload_path = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'sentry@tokaguard.com';
    $mail->Password   = 'Tosar123@';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('sentry@tokaguard.com', 'System Notifier');
    $mail->addAddress('testrecipient@mining.com', 'Test User');

    $mail->Subject = 'Test Email with Debug 587';
    $mail->Body    = 'This is a test email to check SMTP response on 587.';

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
