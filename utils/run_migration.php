<?php
require_once dirname(__DIR__) . '/app/Models/Database.php';

$db = new Database();
$conn = $db->getConnection();

$sql_file = dirname(__DIR__) . '/database/migrations/rbac_setup.sql';
$sql_content = file_get_contents($sql_file);

if (empty($sql_content)) {
    die("Error: Could not read SQL file.\n");
}

// Split into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql_content)));

$success = true;
foreach ($statements as $statement) {
    if (!empty($statement)) {
        if (!$conn->query($statement)) {
            echo "Error executing statement: " . $conn->error . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
            $success = false;
        }
    }
}

if ($success) {
    echo "Migration completed successfully!\n";
} else {
    echo "Migration completed with errors.\n";
}
