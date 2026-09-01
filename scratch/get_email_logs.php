<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();
$res = $db->query("SELECT * FROM notification_email_logs ORDER BY id DESC LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
