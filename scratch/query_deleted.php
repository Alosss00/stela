<?php
require_once __DIR__ . '/../bootstrap/app.php';

$db = new Database();
$result = $db->query("SELECT id, employee_id, appointment_number, document_path FROM appointments WHERE appointment_number IN ('01/TT/MSM/09/2026', '20/TT/MSM/08/2026') OR deleted_at IS NOT NULL");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "No records found in appointments.\n";
}

$result2 = $db->query("SELECT id, employee_code, full_name, document_path, photo_path FROM employees WHERE full_name LIKE '%Windy%' OR full_name LIKE '%Tes bug 2%' OR deleted_at IS NOT NULL");
if ($result2 && $result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "No records found in employees.\n";
}
