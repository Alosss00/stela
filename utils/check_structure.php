<?php
require 'includes/db.php';

$db = new Database();

echo "TABLE STRUCTURE - COMPETENCIES\n";
echo "==============================\n";
$result = $db->query("DESCRIBE competencies");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n\nTENAGA TEKNIS COMPETENCIES:\n";
echo "==============================\n";
$result = $db->query("SELECT * FROM competencies WHERE position_type = 'tenaga_teknis'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['competency_name'] . "\n";
    }
} else {
    echo "No tenaga_teknis competencies found\n";
}
?>

