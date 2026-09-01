<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();
$res = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
