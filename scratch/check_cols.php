<?php
require 'bootstrap/app.php';
$db = new Database();
$res = $db->query('DESCRIBE departments');
while($row = $res->fetch_assoc()) echo $row['Field'] . ' ';
echo "\n";
$res = $db->query('DESCRIBE companies');
while($row = $res->fetch_assoc()) echo $row['Field'] . ' ';
echo "\n";
