<?php
require 'bootstrap/app.php';
$db = new Database();
$res = $db->query('SHOW COLUMNS FROM employee_certifications');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . PHP_EOL;
}
