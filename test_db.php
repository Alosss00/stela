<?php
require_once __DIR__ . '/bootstrap/app.php';
$db = new Database();
$res = $db->query('SHOW TABLES');
while($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}
