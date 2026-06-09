<?php
/**
 * Migration: Fix sub_competency column capacity
 * Change column size from VARCHAR(10) to VARCHAR(255)
 */

require_once 'includes/db.php';

$db = new Database();

echo "<h2>🔄 Fixing sub_competency column capacity</h2>";
echo "<pre style='background: #F9FAFB; padding: 15px; border-radius: 5px; font-family: monospace;'>";

try {
    // Check if column exists
    $check_result = $db->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'employees' AND COLUMN_NAME = 'sub_competency'");
    
    if ($check_result && $check_result->num_rows > 0) {
        $column_info = $check_result->fetch_assoc();
        $current_type = $column_info['COLUMN_TYPE'];
        
        echo "Current column type: $current_type\n\n";
        
        // Alter the column
        $alter_sql = "ALTER TABLE employees MODIFY COLUMN sub_competency VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL";
        
        if ($db->query($alter_sql)) {
            echo "✅ Successfully changed column 'sub_competency' from $current_type to VARCHAR(255)\n";
            echo "✅ The column can now store sub-competency names up to 255 characters\n";
        } else {
            echo "❌ Failed to modify column: " . $db->getConnection()->error . "\n";
        }
    } else {
        echo "⚠️  Column 'sub_competency' does not exist yet in employees table\n";
        echo "Creating the column...\n\n";
        
        $create_sql = "ALTER TABLE employees ADD COLUMN sub_competency VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL AFTER competency_name";
        
        if ($db->query($create_sql)) {
            echo "✅ Successfully created column 'sub_competency' with VARCHAR(255) capacity\n";
        } else {
            echo "❌ Failed to create column: " . $db->getConnection()->error . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p style='margin-top: 20px; color: #2e7d32; font-weight: bold;'>✅ Migration completed! You can now add employees with sub-competencies.</p>";
?>

