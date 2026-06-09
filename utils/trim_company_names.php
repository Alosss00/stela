<?php
require_once 'includes/db.php';

$db = new Database();

echo "Trimming whitespace from company names...\n";

// Trim spaces from users table
$db->query("UPDATE users SET company_name = TRIM(company_name) WHERE company_name IS NOT NULL");
$users_updated = $db->getConnection()->affected_rows;
echo "Users table: $users_updated rows updated\n";

// Trim spaces from employees table
$db->query("UPDATE employees SET contractor_company = TRIM(contractor_company) WHERE contractor_company IS NOT NULL");
$employees_updated = $db->getConnection()->affected_rows;
echo "Employees table: $employees_updated rows updated\n";

echo "\n✓ Whitespace trimming completed!\n";
?>

