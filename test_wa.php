<?php
require_once __DIR__ . '/bootstrap/app.php';

// Only superadmin
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    die("Access denied. Superadmin only.");
}

$db = new Database();
$settings_query = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'fonnte_token'");
$token = $settings_query->fetch_assoc()['setting_value'] ?? '';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? '';
    $msg = $_POST['message'] ?? 'Test message from STELA System!';
    
    if (empty($phone)) {
        $status = 'error';
        $message = 'Please enter a phone number.';
    } else {
        // Format phone
        $phone = preg_replace('/\D/', '', $phone);
        if (substr($phone, 0, 2) !== '62') {
            if (substr($phone, 0, 1) === '0') {
                $phone = '62' . substr($phone, 1);
            } else {
                $phone = '62' . $phone;
            }
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'target'      => $phone,
                'message'     => $msg,
                'countryCode' => '62',
            ],
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($err) {
            $status = 'error';
            $message = 'cURL Error: ' . $err;
        } else if (isset($data['status']) && $data['status'] === true) {
            $status = 'success';
            $message = 'WhatsApp message sent successfully to ' . $phone . '!';
        } else {
            $status = 'error';
            $message = 'Fonnte Error: ' . ($data['reason'] ?? $response);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Fonnte WhatsApp</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f0f2f5; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #2563eb; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .token-info { font-size: 0.85em; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Test WhatsApp (Fonnte)</h2>
        
        <?php if ($status === 'success'): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="e.g. 081234567890" required>
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" rows="4" required>Test notification from STELA System.</textarea>
            </div>
            <button type="submit">Send WhatsApp Message</button>
        </form>
        <div class="token-info">
            Current Fonnte Token: <strong><?php echo substr($token, 0, 4) . '***' . substr($token, -4); ?></strong>
        </div>
        <div style="margin-top:20px; font-size:0.9em;">
            <a href="pages/superadmin/settings.php">Back to Settings</a>
        </div>
    </div>
</body>
</html>
