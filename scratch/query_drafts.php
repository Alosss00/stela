<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();
$result = $db->query("SELECT id, appointment_number, status FROM appointments WHERE status = 'draft'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo $row['id'] . " | " . $row['appointment_number'] . " | " . $row['status'] . "\n";
    }
} else {
    echo "No draft appointments found.\n";
}
