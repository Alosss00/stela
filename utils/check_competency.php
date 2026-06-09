<?php
require_once 'includes/db.php';
$db = new Database();

echo "=== Struktur kolom competency di tabel employees ===\n";
$result = $db->query('DESCRIBE employees');
while($row = $result->fetch_assoc()) {
    if(strpos($row['Field'], 'competency') !== false) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
}

echo "\n=== Sample data competency (5 records) ===\n";
$result = $db->query('SELECT id, full_name, competency_name, competency_type FROM employees LIMIT 5');
while($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']} | Name: {$row['full_name']}\n";
    echo "  Competency Name: " . ($row['competency_name'] ?: 'NULL/EMPTY') . "\n";
    echo "  Competency Type: " . ($row['competency_type'] ?: 'NULL/EMPTY') . "\n\n";
}

echo "\n=== Cek tabel competencies ===\n";
$check_table = $db->query("SHOW TABLES LIKE 'competencies'");
if ($check_table->num_rows > 0) {
    echo "Tabel competencies EXISTS\n";
    echo "\n=== Sample data dari tabel competencies ===\n";
    $result = $db->query('SELECT id, competency_name, position_type FROM competencies WHERE is_active = 1 LIMIT 5');
    while($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']} | Competency: {$row['competency_name']} | Type: {$row['position_type']}\n";
    }
} else {
    echo "Tabel competencies TIDAK ADA\n";
}
?>

