<?php
/**
 * Script untuk menjalankan migration: Add statement_file column
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

$db = new Database();

echo "=== MIGRATION: Add statement_file column ===\n\n";

// Read SQL file
$sql_file = 'migration_add_statement_file.sql';
if (!file_exists($sql_file)) {
    die("❌ Error: File $sql_file tidak ditemukan!\n");
}

$sql_content = file_get_contents($sql_file);

// Remove comments
$lines = explode("\n", $sql_content);
$clean_lines = [];
foreach ($lines as $line) {
    $line = trim($line);
    // Skip empty lines and comment lines
    if (empty($line) || strpos($line, '--') === 0) {
        continue;
    }
    $clean_lines[] = $line;
}
$sql_content = implode("\n", $clean_lines);

// Split queries by semicolon
$queries = array_filter(array_map('trim', explode(';', $sql_content)));

echo "Total queries found: " . count($queries) . "\n\n";

$success_count = 0;
$error_count = 0;

foreach ($queries as $index => $query) {
    // Skip empty queries
    if (empty($query)) {
        echo "Skipping empty query #" . ($index + 1) . "\n";
        continue;
    }
    
    echo "Executing query #" . ($index + 1) . "...\n";
    echo "Query: " . $query . "\n";
    
    if ($db->query($query)) {
        echo "✅ Success!\n\n";
        $success_count++;
    } else {
        echo "❌ Error: " . $db->getConnection()->error . "\n\n";
        $error_count++;
    }
}

echo "=== MIGRATION COMPLETE ===\n";
echo "✅ Successful queries: $success_count\n";
echo "❌ Failed queries: $error_count\n";

// Verify the column exists
echo "\n=== VERIFICATION ===\n";
$result = $db->query("SHOW COLUMNS FROM employees LIKE 'statement_file'");
if ($result && $result->num_rows > 0) {
    echo "✅ Column 'statement_file' exists in employees table\n";
    $column = $result->fetch_assoc();
    echo "   Type: " . $column['Type'] . "\n";
    echo "   Null: " . $column['Null'] . "\n";
    echo "   Default: " . ($column['Default'] ?? 'NULL') . "\n";
} else {
    echo "❌ Column 'statement_file' NOT found in employees table\n";
}

echo "\nDone!\n";
?>

