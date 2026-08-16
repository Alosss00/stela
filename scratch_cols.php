<?php
require 'bootstrap/app.php';
$db = new Database();
$res = $db->query('SHOW COLUMNS FROM employees');
while($row = $res->fetch_assoc()){
    echo $row['Field'] . "\n";
}
