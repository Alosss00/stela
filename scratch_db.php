<?php
require 'bootstrap/app.php';
$db = new Database();
$res = $db->query("SELECT id, full_name, employee_status, contractor_company FROM employees WHERE employee_status = 'resign'");
$count = $res ? $res->num_rows : 0;
echo "Count of resigned: " . $count . "\n";
if ($count > 0) {
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
