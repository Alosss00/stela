<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();
$result = $db->query("SELECT a.id, a.appointment_number, a.status, e.full_name FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE e.full_name LIKE '%Windy%' OR e.full_name LIKE '%Tes bug 2%'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo implode(" | ", $row) . "\n";
    }
} else {
    echo "No matching appointments found.\n";
}
