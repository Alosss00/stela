<?php
require_once 'includes/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

echo "Checking appointments after user resubmit:\n";
echo str_repeat('=', 100) . "\n";

// Check appointments that were sent back to user and might have been resubmitted
$result = $conn->query("
    SELECT a.id, a.appointment_number, a.status,
           a.ktt_msm_status, a.ktt_ttn_status,
           a.requires_ktt_msm_review, a.requires_ktt_ttn_review,
           a.last_rejected_by_ktt,
           a.admin_approval_action,
           e.verification_status, e.resubmit_count
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status IN ('rejected', 'pending')
    AND (a.admin_approval_action IS NOT NULL OR e.resubmit_count > 0)
    ORDER BY a.id DESC
    LIMIT 5
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "\nAppointment ID: {$row['id']} - {$row['appointment_number']}\n";
        echo "  Appointment Status: {$row['status']}\n";
        echo "  Employee Verification: {$row['verification_status']}\n";
        echo "  Resubmit Count: {$row['resubmit_count']}\n";
        echo "  KTT MSM: {$row['ktt_msm_status']} | KTT TTN: {$row['ktt_ttn_status']}\n";
        echo "  Requires MSM: {$row['requires_ktt_msm_review']} | Requires TTN: {$row['requires_ktt_ttn_review']}\n";
        echo "  Last Rejected By: " . ($row['last_rejected_by_ktt'] ?? 'NULL') . "\n";
        echo "  Admin Action: " . ($row['admin_approval_action'] ?? 'NULL') . "\n";
        echo str_repeat('-', 100) . "\n";
    }
} else {
    echo "No appointments found.\n";
}

$conn->close();
?>

