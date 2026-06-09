<?php
// Verifikasi hasil update
require_once 'includes/db.php';

$db = new Database();

echo "=== Verifikasi Nama Perusahaan di Database ===\n\n";

echo "Companies in employees table:\n";
$result = $db->query("SELECT DISTINCT contractor_company FROM employees ORDER BY contractor_company");
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['contractor_company'] . "\n";
}

echo "\n\nRuang Lingkup in employees table:\n";
$result2 = $db->query("SELECT DISTINCT ruang_lingkup FROM employees WHERE ruang_lingkup IS NOT NULL AND ruang_lingkup != '' ORDER BY ruang_lingkup");
while ($row = $result2->fetch_assoc()) {
    echo "- " . $row['ruang_lingkup'] . "\n";
}

echo "\n\nUsers with PT in name:\n";
$result3 = $db->query("SELECT id, username, full_name, department FROM users WHERE full_name LIKE '%PT%' OR department LIKE '%PT%'");
while ($row = $result3->fetch_assoc()) {
    echo "- [{$row['username']}] {$row['full_name']} (Dept: {$row['department']})\n";
}
?>

