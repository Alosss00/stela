<?php
require_once __DIR__ . '/bootstrap/app.php';
$db = new Database();
$res = $db->query("ALTER TABLE employees MODIFY COLUMN supervision_area VARCHAR(100) NULL");
if ($res) {
    echo 'Success';
} else {
    echo $db->getConnection()->error;
}
