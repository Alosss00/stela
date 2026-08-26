<?php
require 'bootstrap/app.php';
$db = new Database();
$res = $db->query("SELECT ec.status, count(*) as count FROM employee_certifications ec WHERE ec.expiry_date < CURDATE() GROUP BY ec.status");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['status'] . ' - ' . $row['count'] . PHP_EOL;
    }
}
