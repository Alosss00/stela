<?php
require_once 'includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

echo "Current Appointment States:\n";
echo str_repeat('=', 100) . "\n";

$result = $conn->query("
    SELECT id, appointment_number, status,
           ktt_msm_status, ktt_ttn_status,
           requires_ktt_msm_review, requires_ktt_ttn_review,
           last_rejected_by_ktt,
           admin_approval_action
    FROM appointments
    WHERE status IN ('pending', 'rejected', 'rejected_by_ktt')
    ORDER BY id DESC
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "\nID: {$row['id']} | Number: {$row['appointment_number']}\n";
        echo "  Status: {$row['status']}\n";
        echo "  KTT MSM Status: {$row['ktt_msm_status']} | KTT TTN Status: {$row['ktt_ttn_status']}\n";
        echo "  Requires MSM Review: {$row['requires_ktt_msm_review']} | Requires TTN Review: {$row['requires_ktt_ttn_review']}\n";
        echo "  Last Rejected By: " . ($row['last_rejected_by_ktt'] ?? 'NULL') . "\n";
        echo "  Admin Action: " . ($row['admin_approval_action'] ?? 'NULL') . "\n";
        echo str_repeat('-', 100) . "\n";
    }
} else {
    echo "No pending/rejected/rejected_by_ktt appointments found.\n";
}

$conn->close();
?>

