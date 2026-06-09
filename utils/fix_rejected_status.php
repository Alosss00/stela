<?php
require_once 'includes/config.php';

echo "Fixing Inconsistent Rejected Status\n";
echo str_repeat('=', 80) . "\n\n";

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Find appointments with status='rejected_by_ktt' but not both KTT have reviewed
$inconsistent = $conn->query("
    SELECT id, appointment_number, status,
           ktt_msm_status, ktt_ttn_status,
           last_rejected_by_ktt
    FROM appointments
    WHERE status = 'rejected_by_ktt'
    AND (ktt_msm_status = 'pending' OR ktt_ttn_status = 'pending')
");

if ($inconsistent && $inconsistent->num_rows > 0) {
    echo "Found {$inconsistent->num_rows} appointment(s) with inconsistent status:\n\n";

    while ($row = $inconsistent->fetch_assoc()) {
        echo "Appointment #{$row['id']} - {$row['appointment_number']}\n";
        echo "  Current Status: {$row['status']}\n";
        echo "  KTT MSM: {$row['ktt_msm_status']} | KTT TTN: {$row['ktt_ttn_status']}\n";
        echo "  Fixing: Set status back to 'pending' (waiting for other KTT review)\n";

        $update = $conn->query("
            UPDATE appointments
            SET status = 'pending',
                rejected_by_ktt_user_id = NULL,
                approval_notes = NULL
            WHERE id = {$row['id']}
        ");

        if ($update) {
            echo "  ✓ Fixed successfully!\n";
        } else {
            echo "  ✗ Error: " . $conn->error . "\n";
        }

        echo str_repeat('-', 80) . "\n";
    }
} else {
    echo "No inconsistent appointments found. All status are correct!\n";
}

echo "\nDone!\n";

$conn->close();
?>

