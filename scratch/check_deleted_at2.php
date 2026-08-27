<?php
require 'bootstrap/app.php';
$db = new Database();
$tables = ['companies', 'departments', 'competency_sub_competencies', 'certifications'];
foreach($tables as $t) {
    try {
        $res = $db->query('DESCRIBE ' . $t);
        $cols = [];
        if($res) {
            while($row = $res->fetch_assoc()) {
                $cols[] = $row['Field'];
            }
        }
        echo $t . ': ' . (in_array('deleted_at', $cols) ? 'YES' : 'NO') . "\n";
    } catch (Exception $e) {
        echo $t . ': ERROR ' . $e->getMessage() . "\n";
    }
}
