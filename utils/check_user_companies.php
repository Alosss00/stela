<?php
require_once 'includes/db.php';

$db = new Database();

echo "=== CHECKING COMPANY NAMES IN USERS TABLE ===\n\n";

$result = $db->query("
    SELECT id, username, full_name, company_name, department, role 
    FROM users 
    WHERE company_name IS NOT NULL AND company_name != ''
    ORDER BY company_name
");

if ($result && $result->num_rows > 0) {
    echo "Found " . $result->num_rows . " company users:\n";
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "Username: " . $row['username'] . "\n";
        echo "Full Name: " . $row['full_name'] . "\n";
        echo "Company: " . $row['company_name'] . "\n";
        echo "Role: " . $row['role'] . "\n";
        echo str_repeat("-", 80) . "\n";
    }
    
    // Check for companies with "PT."
    $result->data_seek(0);
    $companies_with_dot = [];
    while ($row = $result->fetch_assoc()) {
        if (strpos($row['company_name'], 'PT. ') !== false) {
            $companies_with_dot[] = $row;
        }
    }
    
    if (count($companies_with_dot) > 0) {
        echo "\n⚠️  WARNING: Found " . count($companies_with_dot) . " users with 'PT.' format:\n";
        foreach ($companies_with_dot as $user) {
            echo "  - " . $user['username'] . " (" . $user['company_name'] . ")\n";
        }
        echo "\nThese need to be updated to match the new format!\n";
    } else {
        echo "\n✓ All company names are in correct format (without 'PT.')\n";
    }
} else {
    echo "No company users found.\n";
}
?>

