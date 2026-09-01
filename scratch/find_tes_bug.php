<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();
$res = $db->query("SELECT id, full_name, appointment_number, deleted_at FROM employees WHERE full_name LIKE '%Tes bug 2%'");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No matching employees.\n";
}
