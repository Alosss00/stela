<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();

$now = date('Y-m-d H:i:s');
// Soft delete employee 194 (Windy) and 145 (Tes bug 2)
$db->query("UPDATE employees SET deleted_at = '$now', deleted_by = 1 WHERE id IN (194, 145)");

echo "Soft deleted employees 194 and 145.\n";
