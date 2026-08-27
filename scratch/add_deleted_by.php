<?php
require 'bootstrap/app.php';
$db = new Database();
$tables = ['appointments', 'employees', 'users', 'positions', 'supervision_areas', 'competencies', 'certifications', 'companies', 'departments', 'competency_sub_competencies'];
foreach($tables as $t) {
    try {
        $db->query("ALTER TABLE $t ADD COLUMN deleted_by INT NULL DEFAULT NULL");
        echo "Added deleted_by to $t\n";
    } catch (Exception $e) {
        echo "Error on $t: " . $e->getMessage() . "\n";
    }
}
