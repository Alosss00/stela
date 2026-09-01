<?php
$token = 'YOUR_FONNTE_TOKEN'; // We'll get it from settings

// Let's grab the token from DB
require_once __DIR__ . '/../app/Models/Database.php';
$db = new Database();
$res = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'fonnte_token'");
$token = $res->fetch_assoc()['setting_value'] ?? getenv('FONNTE_TOKEN');

echo "Token: " . substr($token, 0, 5) . "...\n";

$phone = '081234567890';
$message = 'Hello test';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://api.fonnte.com/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => [
        'target'      => $phone,
        'message'     => $message,
        'delay'       => '30-60',
        'typing'      => true,
        'countryCode' => '62',
    ],
    CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false, // try setting false just in case ssl is failing
]);

$response  = curl_exec($ch);
$curl_err  = curl_error($ch);
curl_close($ch);

echo "Response: $response\n";
echo "Curl Error: $curl_err\n";

