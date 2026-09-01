<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();
$res = $db->query("SELECT setting_key, setting_value, LENGTH(setting_value) as len FROM settings WHERE setting_key = 'smtp_user'");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
