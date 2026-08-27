<?php
require 'bootstrap/app.php';
$db = new Database();
$tables = ['companies', 'departments', 'competency_sub_competencies', 'certifications'];
foreach($tables as $t) {
    try {
        $db->query("ALTER TABLE $t ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
        echo "Added deleted_at to $t\n";
    } catch (Exception $e) {
        echo "Error on $t: " . $e->getMessage() . "\n";
    }
}
