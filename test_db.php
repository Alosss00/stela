<?php
require 'config/app.php';
$db = new Database();
$res = $db->query("SELECT a.id, a.employee_id, e.department FROM appointments a LEFT JOIN employees e ON a.employee_id = e.id");
while($row = $res->fetch_assoc()) {
    echo $row['id'] . " - " . $row['department'] . "\n";
}
