<?php
/**
 * Migration: Add Rejection History Fields
 * Purpose: Store rejection history in appointments table
 * Date: 2026-02-15
 */

require_once 'includes/db.php';

echo "<h2>Migration: Add Rejection History Fields</h2>";
echo "<hr>";

$db = new Database();
$conn = $db->getConnection();

// Disable mysqli exceptions to handle errors manually
mysqli_report(MYSQLI_REPORT_OFF);

$success_count = 0;
$skip_count = 0;
$error_count = 0;
$messages = [];

// Helper function to check if column exists
function columnExists($db, $table, $column) {
    $result = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($result && $result->num_rows > 0);
}

// Helper function to check if index exists
function indexExists($db, $table, $index) {
    $result = $db->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'");
    return ($result && $result->num_rows > 0);
}

// Migration 1: Add last_rejection_notes column
echo "<p><strong>1. Adding 'last_rejection_notes' column...</strong></p>";
if (!columnExists($db, 'appointments', 'last_rejection_notes')) {
    $sql = "ALTER TABLE `appointments`
            ADD COLUMN `last_rejection_notes` TEXT NULL
            COMMENT 'Stores rejection reason from last KTT rejection'";
    if ($db->query($sql)) {
        echo "<p style='color: green;'>? Successfully added 'last_rejection_notes' column</p>";
        $success_count++;
    } else {
        echo "<p style='color: red;'>? Failed to add 'last_rejection_notes': " . $conn->error . "</p>";
        $error_count++;
    }
} else {
    echo "<p style='color: orange;'>? Column 'last_rejection_notes' already exists (skip)</p>";
    $skip_count++;
}

// Migration 2: Add last_rejection_by_name column
echo "<p><strong>2. Adding 'last_rejection_by_name' column...</strong></p>";
if (!columnExists($db, 'appointments', 'last_rejection_by_name')) {
    $sql = "ALTER TABLE `appointments`
            ADD COLUMN `last_rejection_by_name` VARCHAR(255) NULL
            COMMENT 'Stores name of KTT who rejected'";
    if ($db->query($sql)) {
        echo "<p style='color: green;'>? Successfully added 'last_rejection_by_name' column</p>";
        $success_count++;
    } else {
        echo "<p style='color: red;'>? Failed to add 'last_rejection_by_name': " . $conn->error . "</p>";
        $error_count++;
    }
} else {
    echo "<p style='color: orange;'>? Column 'last_rejection_by_name' already exists (skip)</p>";
    $skip_count++;
}

// Migration 3: Add last_rejection_date column
echo "<p><strong>3. Adding 'last_rejection_date' column...</strong></p>";
if (!columnExists($db, 'appointments', 'last_rejection_date')) {
    $sql = "ALTER TABLE `appointments`
            ADD COLUMN `last_rejection_date` DATETIME NULL
            COMMENT 'Stores date of last rejection'";
    if ($db->query($sql)) {
        echo "<p style='color: green;'>? Successfully added 'last_rejection_date' column</p>";
        $success_count++;
    } else {
        echo "<p style='color: red;'>? Failed to add 'last_rejection_date': " . $conn->error . "</p>";
        $error_count++;
    }
} else {
    echo "<p style='color: orange;'>? Column 'last_rejection_date' already exists (skip)</p>";
    $skip_count++;
}

// Migration 4: Add index on last_rejection_date
echo "<p><strong>4. Adding index 'idx_last_rejection_date'...</strong></p>";
if (!indexExists($db, 'appointments', 'idx_last_rejection_date')) {
    $sql = "CREATE INDEX `idx_last_rejection_date` ON `appointments`(`last_rejection_date`)";
    if ($db->query($sql)) {
        echo "<p style='color: green;'>? Successfully created index 'idx_last_rejection_date'</p>";
        $success_count++;
    } else {
        echo "<p style='color: red;'>? Failed to create index: " . $conn->error . "</p>";
        $error_count++;
    }
} else {
    echo "<p style='color: orange;'>? Index 'idx_last_rejection_date' already exists (skip)</p>";
    $skip_count++;
}

// Verification
echo "<hr>";
echo "<h3>Verification: Checking New Fields</h3>";

$verify_query = "SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'mining_appointment'
AND TABLE_NAME = 'appointments'
AND COLUMN_NAME IN ('last_rejection_notes', 'last_rejection_by_name', 'last_rejection_date')
ORDER BY ORDINAL_POSITION";

$result = $db->query($verify_query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; margin-top: 10px;'>";
    echo "<tr style='background: #f0f0f0;'>
            <th>Column Name</th>
            <th>Data Type</th>
            <th>Nullable</th>
            <th>Comment</th>
          </tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>{$row['COLUMN_NAME']}</strong></td>";
        echo "<td>{$row['DATA_TYPE']}</td>";
        echo "<td>{$row['IS_NULLABLE']}</td>";
        echo "<td style='font-size: 11px;'>{$row['COLUMN_COMMENT']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p style='color: green; font-weight: bold; margin-top: 15px;'>? All rejection history fields verified!</p>";
} else {
    echo "<p style='color: red;'>? Verification failed. Fields may not exist.</p>";
}

// Check index
echo "<h3>Index Verification</h3>";
$index_result = $db->query("SHOW INDEX FROM `appointments` WHERE Key_name = 'idx_last_rejection_date'");
if ($index_result && $index_result->num_rows > 0) {
    echo "<p style='color: green;'>? Index 'idx_last_rejection_date' verified</p>";

    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr style='background: #f0f0f0;'>
            <th>Key Name</th>
            <th>Column Name</th>
            <th>Non Unique</th>
          </tr>";

    $index_row = $index_result->fetch_assoc();
    echo "<tr>";
    echo "<td>{$index_row['Key_name']}</td>";
    echo "<td>{$index_row['Column_name']}</td>";
    echo "<td>{$index_row['Non_unique']}</td>";
    echo "</tr>";
    echo "</table>";
} else {
    echo "<p style='color: red;'>? Index 'idx_last_rejection_date' not found</p>";
}

// Summary
echo "<hr>";
echo "<h3>Migration Summary</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><td><strong>Successfully Added:</strong></td><td style='color: green; font-weight: bold;'>$success_count</td></tr>";
echo "<tr><td><strong>Already Exists (Skipped):</strong></td><td style='color: orange; font-weight: bold;'>$skip_count</td></tr>";
echo "<tr><td><strong>Errors:</strong></td><td style='color: red; font-weight: bold;'>$error_count</td></tr>";
echo "</table>";

if ($error_count == 0) {
    echo "<p style='color: green; font-size: 16px; font-weight: bold; margin-top: 20px;'>? MIGRATION COMPLETED SUCCESSFULLY!</p>";
    echo "<p>You can now use the rejection history feature.</p>";
} else {
    echo "<p style='color: red; font-size: 16px; font-weight: bold; margin-top: 20px;'>? Migration completed with errors!</p>";
    echo "<p>Please check the errors above and fix them manually.</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php' style='padding: 10px 20px; background: #37474F; color: white; text-decoration: none; border-radius: 5px; display: inline-block;'>? Back to Dashboard</a></p>";
?>

