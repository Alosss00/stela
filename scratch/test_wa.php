<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/wa_error.log');
require_once __DIR__ . '/../app/Services/NotificationService.php';

$service = new NotificationService();
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('sendWhatsApp');
$method->setAccessible(true);

$phone = '081234567890';
$result = $method->invokeArgs($service, [$phone, 'Test User', 'Hello from test!', 'test_type', 1]);
echo "Result: " . ($result ? "Success" : "Failed") . "\n";
echo "Error log contents:\n";
if (file_exists(__DIR__ . '/wa_error.log')) {
    echo file_get_contents(__DIR__ . '/wa_error.log');
}
