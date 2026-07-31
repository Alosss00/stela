<?php
/**
 * Run Database Migration
 * Script untuk menjalankan migration membuat table competency_sub_competencies
 */

require_once 'includes/db.php';

$db = new Database();

// Read SQL file
$sql_file = 'migration_create_competency_sub_competencies.sql';
if (!file_exists($sql_file)) {
    die("❌ File $sql_file tidak ditemukan!");
}

// Read the SQL content
$sql_content = file_get_contents($sql_file);

// Split by semicolon and execute each statement
$statements = explode(';', $sql_content);

$success_count = 0;
$error_count = 0;
$errors = [];

echo "<h2>🔄 Running Database Migration</h2>";
echo "<pre style='background: #F9FAFB; padding: 15px; border-radius: 5px; font-family: monospace;'>";

foreach ($statements as $statement) {
    $statement = trim($statement);
    
    // Skip empty statements and comments
    if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
        continue;
    }
    
    // Remove comments
    $statement = preg_replace('/--.*$/m', '', $statement);
    $statement = trim($statement);
    
    if (empty($statement)) {
        continue;
    }
    
    echo "Executing: " . substr($statement, 0, 80) . "...\n";
    
    if ($db->query($statement)) {
        echo "✅ Success\n\n";
        $success_count++;
    } else {
        $db_error = $db->getConnection()->error;
        echo "❌ Failed: " . $db_error . "\n\n";
        $error_count++;
        $errors[] = $db_error;
    }
}

echo "</pre>";

echo "<div style='margin-top: 20px; padding: 15px; border-radius: 5px; border-left: 4px solid #4CAF50;'>";
echo "<h3 style='margin-top: 0;'>📊 Migration Summary</h3>";
echo "<p><strong>✅ Success:</strong> $success_count statements</p>";
echo "<p><strong>❌ Failed:</strong> $error_count statements</p>";

if ($error_count === 0) {
    echo "<p style='color: #4CAF50; font-weight: bold;'>🎉 Migration completed successfully!</p>";
} else {
    echo "<p style='color: #f44336; font-weight: bold;'>Errors encountered:</p>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
}

echo "</div>";

// Verify table exists
echo "<div style='margin-top: 20px; padding: 15px; border-radius: 5px; border-left: 4px solid #2196F3;'>";
echo "<h3 style='margin-top: 0;'>🔍 Verification</h3>";

$check_table = $db->query("SHOW TABLES LIKE 'competency_sub_competencies'");
if ($check_table && $check_table->num_rows > 0) {
    echo "<p style='color: #4CAF50; font-weight: bold;'>✅ Table 'competency_sub_competencies' created successfully!</p>";
    
    // Show table structure
    echo "<p><strong>Table Structure:</strong></p>";
    echo "<pre>";
    $describe = $db->query("DESCRIBE competency_sub_competencies");
    while ($row = $describe->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ") " . ($row['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    echo "</pre>";
} else {
    echo "<p style='color: #f44336; font-weight: bold;'>❌ Table 'competency_sub_competencies' was not created!</p>";
}

echo "</div>";
?>

