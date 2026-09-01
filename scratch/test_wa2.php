<?php
require_once __DIR__ . '/../app/Services/NotificationService.php';

$service = new NotificationService();
$reflection = new ReflectionClass($service);
$tokenProp = $reflection->getProperty('fonnte_token');
$tokenProp->setAccessible(true);
$token = $tokenProp->getValue($service);
echo "Token from service: " . substr($token, 0, 5) . "...\n";

$method = $reflection->getMethod('sendWhatsApp');
$method->setAccessible(true);

$phone = '081234567890';
$result = $method->invokeArgs($service, [$phone, 'Test User', 'Hello from test!', 'test_type', 1]);
var_dump("Result: ", $result);
