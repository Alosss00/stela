<?php
require_once 'includes/config.php';

echo "Fixing Broken Appointments - Requires Flags Correction\n";
echo str_repeat('=', 80) . "\n\n";

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Find appointments with incorrect requires flags
$broken = $conn->query("
    SELECT id, appointment_number, status,
           ktt_msm_status, ktt_ttn_status,
           requires_ktt_msm_review, requires_ktt_ttn_review,
           last_rejected_by_ktt,
           admin_approval_action
    FROM appointments
    WHERE status = 'pending'
    AND last_rejected_by_ktt IS NOT NULL
    AND (
        (last_rejected_by_ktt = 'msm' AND requires_ktt_ttn_review = 1)
        OR
        (last_rejected_by_ktt = 'ttn' AND requires_ktt_msm_review = 1)
    )
");

if ($broken && $broken->num_rows > 0) {
    echo "Found {$broken->num_rows} appointment(s) with incorrect requires flags:\n\n";

    while ($row = $broken->fetch_assoc()) {
        echo "Appointment #{$row['id']} - {$row['appointment_number']}\n";
        echo "  Status: {$row['status']}\n";
        echo "  Last Rejected By: {$row['last_rejected_by_ktt']}\n";
        echo "  Current Requires: MSM={$row['requires_ktt_msm_review']}, TTN={$row['requires_ktt_ttn_review']}\n";

        // Fix the flags
        if ($row['last_rejected_by_ktt'] == 'msm') {
            // KTT MSM rejected, should only require MSM review
            $new_msm = 1;
            $new_ttn = 0;
        } else {
            // KTT TTN rejected, should only require TTN review
            $new_msm = 0;
            $new_ttn = 1;
        }

        echo "  Correcting to: MSM={$new_msm}, TTN={$new_ttn}\n";

        $update = $conn->query("
            UPDATE appointments
            SET requires_ktt_msm_review = $new_msm,
                requires_ktt_ttn_review = $new_ttn
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
    echo "No broken appointments found. All requires flags are correct!\n";
}

// Also fix appointments that have status=pending but still have last_rejected_by_ktt set
echo "\nClearing obsolete rejection flags from pending appointments:\n";
echo str_repeat('=', 80) . "\n";

$obsolete = $conn->query("
    SELECT id, appointment_number, last_rejected_by_ktt, admin_approval_action
    FROM appointments
    WHERE status = 'pending'
    AND last_rejected_by_ktt IS NOT NULL
    AND admin_approval_action = 'send_to_ktt'
");

if ($obsolete && $obsolete->num_rows > 0) {
    echo "Found {$obsolete->num_rows} appointment(s) with obsolete rejection flags:\n\n";

    while ($row = $obsolete->fetch_assoc()) {
        echo "Appointment #{$row['id']} - {$row['appointment_number']}\n";
        echo "  Clearing last_rejected_by_ktt = {$row['last_rejected_by_ktt']}\n";

        // Note: We don't clear last_rejected_by_ktt if admin_approval_action = 'send_to_user'
        // because user might resubmit and we need to know which KTT to send to

        // Actually, let's NOT clear it yet - let's keep it for tracking purposes
        echo "  → Keeping for tracking purposes\n";
        echo str_repeat('-', 80) . "\n";
    }
}

echo "\nDone!\n";

$conn->close();
?>

