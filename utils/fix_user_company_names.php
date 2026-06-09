<?php
require_once 'includes/db.php';

$db = new Database();

echo "=== FIXING COMPANY NAMES IN USERS TABLE ===\n\n";

// Update company_name in users table
$updates = [
    "UPDATE users SET company_name = REPLACE(company_name, 'PT. ', 'PT ') WHERE company_name LIKE 'PT. %'",
];

$total_updated = 0;

foreach ($updates as $sql) {
    echo "Executing: $sql\n";
    if ($db->query($sql)) {
        $affected = $db->getConnection()->affected_rows;
        echo "✓ Updated: $affected rows\n\n";
        $total_updated += $affected;
    } else {
        echo "✗ Error: " . $db->getConnection()->error . "\n\n";
    }
}

echo "\n=== VERIFICATION ===\n";

// Verify the updates
$result = $db->query("
    SELECT id, username, full_name, company_name 
    FROM users 
    WHERE company_name IS NOT NULL AND company_name != ''
    ORDER BY company_name
");

if ($result && $result->num_rows > 0) {
    echo "\nUpdated company names:\n";
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        echo sprintf("%-20s | %s\n", $row['username'], $row['company_name']);
    }
    
    // Check if any still have "PT."
    $result->data_seek(0);
    $still_has_dot = false;
    while ($row = $result->fetch_assoc()) {
        if (strpos($row['company_name'], 'PT. ') !== false) {
            $still_has_dot = true;
            break;
        }
    }
    
    echo str_repeat("-", 80) . "\n";
    
    if ($still_has_dot) {
        echo "\n⚠️  WARNING: Some companies still have 'PT.' format!\n";
    } else {
        echo "\n✓ All company names successfully updated!\n";
    }
}

echo "\nTotal rows updated: $total_updated\n";
echo "\n✓ Update completed! Users should now be able to see their data.\n";
echo "Note: Users need to logout and login again for changes to take effect.\n";
?>

