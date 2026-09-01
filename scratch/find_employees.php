<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();

$numbers = ['01/TT/MSM/09/2026', '20/TT/MSM/08/2026'];
$numbers_str = "'" . implode("', '", $numbers) . "'";

$sql = "SELECT id, full_name, appointment_number, deleted_at FROM employees WHERE appointment_number IN ($numbers_str)";
$res = $db->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No matching employees.\n";
}
