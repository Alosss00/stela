<?php
require 'bootstrap/app.php';
$db = new Database();

$tables = ['employees', 'appointments', 'employee_certifications', 'certifications'];
foreach ($tables as $t) {
    echo "TABLE: $t\n";
    $res = $db->query("DESCRIBE $t");
    while($row = $res->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "\n";
}
