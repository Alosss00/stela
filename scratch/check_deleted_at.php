<?php
require 'bootstrap/app.php';
$db = new Database();
$tables = ['users', 'employees', 'appointments', 'positions', 'supervision_areas', 'competencies'];
foreach($tables as $t) {
    $res = $db->query('DESCRIBE ' . $t);
    $cols = [];
    if($res) {
        while($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    echo $t . ': ' . (in_array('deleted_at', $cols) ? 'YES' : 'NO') . "\n";
}
