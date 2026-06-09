<?php
// Script untuk mengupdate semua nama perusahaan dari "PT." ke "PT" di database
require_once 'includes/db.php';

$db = new Database();

echo "=== Update Company Names: Removing dots after PT ===\n\n";

// Update employees table - contractor_company
$result1 = $db->query("UPDATE employees SET contractor_company = REPLACE(contractor_company, 'PT. ', 'PT ') WHERE contractor_company LIKE 'PT. %'");
$affected1 = $db->getConnection()->affected_rows;
echo "✓ Updated employees.contractor_company: $affected1 rows\n";

// Update employees table - ruang_lingkup
$result2 = $db->query("UPDATE employees SET ruang_lingkup = REPLACE(ruang_lingkup, 'PT. ', 'PT ') WHERE ruang_lingkup LIKE 'PT. %'");
$affected2 = $db->getConnection()->affected_rows;
echo "✓ Updated employees.ruang_lingkup: $affected2 rows\n";

// Update employees table - supervision_area
$result3 = $db->query("UPDATE employees SET supervision_area = REPLACE(supervision_area, 'PT. ', 'PT ') WHERE supervision_area LIKE 'PT. %'");
$affected3 = $db->getConnection()->affected_rows;
echo "✓ Updated employees.supervision_area: $affected3 rows\n";

// Update users table - full_name
$result4 = $db->query("UPDATE users SET full_name = REPLACE(full_name, 'PT. ', 'PT ') WHERE full_name LIKE 'PT. %'");
$affected4 = $db->getConnection()->affected_rows;
echo "✓ Updated users.full_name: $affected4 rows\n";

// Update users table - department
$result5 = $db->query("UPDATE users SET department = REPLACE(department, 'PT. ', 'PT ') WHERE department LIKE 'PT. %'");
$affected5 = $db->getConnection()->affected_rows;
echo "✓ Updated users.department: $affected5 rows\n";

$total = $affected1 + $affected2 + $affected3 + $affected4 + $affected5;
echo "\n=== Total rows updated: $total ===\n";
echo "Database update completed successfully!\n";
?>

