<?php
// Run KTT Workflow Enhancement Migration
require_once 'includes/config.php';

echo "=================================================\n";
echo "KTT Approval Workflow Enhancement Migration\n";
echo "=================================================\n\n";

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "✓ Connected to database: " . DB_NAME . "\n\n";

// Read migration file
$migration_file = __DIR__ . '/migration_ktt_workflow_enhancement.sql';
$sql = file_get_contents($migration_file);

if ($sql === false) {
    die("Error: Could not read migration file\n");
}

echo "✓ Migration file loaded\n\n";
echo "Executing migration...\n";
echo "-------------------------------------------------\n";

// Use multi_query to execute all statements
if ($conn->multi_query($sql)) {
    $stmt_count = 0;
    do {
        $stmt_count++;
        // Store first result set
        if ($result = $conn->store_result()) {
            echo "✓ Statement $stmt_count executed (has results)\n";
            $result->free();
        } else {
            if ($conn->errno) {
                echo "✗ Statement $stmt_count error: " . $conn->error . "\n";
            } else {
                echo "✓ Statement $stmt_count executed successfully\n";
                if ($conn->affected_rows > 0) {
                    echo "  → Affected rows: " . $conn->affected_rows . "\n";
                }
            }
        }
    } while ($conn->more_results() && $conn->next_result());

    echo "\n-------------------------------------------------\n";
    echo "\nMigration completed! Executed $stmt_count statements\n";
} else {
    echo "✗ Migration failed: " . $conn->error . "\n";
}

// Verify new columns exist
echo "\n=================================================\n";
echo "Verification\n";
echo "=================================================\n\n";

// Clear any remaining results
while ($conn->more_results() && $conn->next_result()) {
    if ($result = $conn->store_result()) {
        $result->free();
    }
}

$columns_to_check = [
    'ktt_msm_status',
    'ktt_ttn_status',
    'requires_ktt_msm_review',
    'requires_ktt_ttn_review',
    'resubmit_reason',
    'last_rejected_by_ktt'
];

echo "Checking new columns in appointments table:\n";
$all_exist = true;
foreach ($columns_to_check as $col) {
    $result = $conn->query("SHOW COLUMNS FROM appointments LIKE '$col'");
    if ($result && $result->num_rows > 0) {
        $col_info = $result->fetch_assoc();
        echo "  ✓ $col - EXISTS (Type: {$col_info['Type']})\n";
    } else {
        echo "  ✗ $col - NOT FOUND\n";
        $all_exist = false;
    }
}

// Count pending appointments with new structure
$count_result = $conn->query("
    SELECT COUNT(*) as count
    FROM appointments
    WHERE status = 'pending'
");

if ($count_result) {
    $count = $count_result->fetch_assoc()['count'];
    echo "\n✓ Pending appointments in system: $count records\n";
}

// Show sample of initialized data
if ($all_exist) {
    $sample = $conn->query("
        SELECT id, appointment_number, ktt_msm_status, ktt_ttn_status,
               requires_ktt_msm_review, requires_ktt_ttn_review
        FROM appointments
        WHERE status = 'pending'
        LIMIT 3
    ");

    if ($sample && $sample->num_rows > 0) {
        echo "\nSample initialized data:\n";
        while ($row = $sample->fetch_assoc()) {
            echo "  - Appointment {$row['appointment_number']}: ";
            echo "MSM={$row['ktt_msm_status']}, TTN={$row['ktt_ttn_status']}, ";
            echo "Require MSM={$row['requires_ktt_msm_review']}, Require TTN={$row['requires_ktt_ttn_review']}\n";
        }
    }
}

$conn->close();

echo "\n=================================================\n";
if ($all_exist) {
    echo "✓ Migration successful! All columns created.\n";
} else {
    echo "⚠ Migration completed with issues. Check errors above.\n";
}
echo "=================================================\n";
?>

