<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();
$result = $db->query("SELECT * FROM appointments WHERE employee_id = 194");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No appointments found for employee 194.\n";
}
