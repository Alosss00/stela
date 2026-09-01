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
    $mail->SMTPDebug = 0; 
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'sentry@tokaguard.com';
    $mail->Password   = 'Tosar123@';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->SMTPKeepAlive = true; // Use Keep-Alive

    $mail->setFrom('sentry@tokaguard.com', 'System Notifier');

    $start = microtime(true);
    for ($i = 1; $i <= 3; $i++) {
        $mail->clearAllRecipients();
        $mail->addAddress('test' . $i . '@mining.com', 'Test User ' . $i);
        $mail->Subject = 'Test Email ' . $i;
        $mail->Body    = 'This is test email ' . $i;
        try {
            $mail->send();
            echo "Email $i sent.\n";
        } catch (Exception $e) {
            echo "Email $i failed: {$mail->ErrorInfo}\n";
        }
    }
    $mail->smtpClose();
    $time = microtime(true) - $start;
    echo "Total time: $time seconds\n";

} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
