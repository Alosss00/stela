<?php
/**
 * Migration Script: Add email and phone columns to users table
 */

require_once 'includes/db.php';

$db = new Database();

echo "=== ADDING EMAIL AND PHONE COLUMNS TO USERS TABLE ===\n\n";

// Check if columns exist
$columns_result = $db->query("SHOW COLUMNS FROM users LIKE 'email'");
$email_exists = $columns_result && $columns_result->num_rows > 0;

$columns_result = $db->query("SHOW COLUMNS FROM users LIKE 'phone'");
$phone_exists = $columns_result && $columns_result->num_rows > 0;

if (!$email_exists) {
    echo "Adding 'email' column to users table...\n";
    $sql = "ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER username";
    if ($db->query($sql)) {
        echo "✓ Email column added successfully\n";
    } else {
        echo "✗ Failed to add email column: " . $db->getConnection()->error . "\n";
    }
} else {
    echo "✓ Email column already exists\n";
}

if (!$phone_exists) {
    echo "\nAdding 'phone' column to users table...\n";
    $sql = "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email";
    if ($db->query($sql)) {
        echo "✓ Phone column added successfully\n";
    } else {
        echo "✗ Failed to add phone column: " . $db->getConnection()->error . "\n";
    }
} else {
    echo "✓ Phone column already exists\n";
}

echo "\n=== SAMPLE DATA INSERTION ===\n\n";
echo "You can now update admin users with their contact information:\n\n";
echo "Example SQL:\n";
echo "UPDATE users SET email = 'admin@example.com', phone = '081234567890' WHERE username = 'admin';\n";
echo "UPDATE users SET email = 'admin2@example.com', phone = '081234567891' WHERE username = 'admin2';\n";

echo "\n=== MIGRATION COMPLETED ===\n";
?>

