<?php
require_once 'includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

echo "Fixing appointment ID 39...\n";
echo str_repeat('=', 80) . "\n";

// Get current state
$result = $conn->query("
    SELECT id, appointment_number, status,
           ktt_msm_status, ktt_ttn_status,
           requires_ktt_msm_review, requires_ktt_ttn_review,
           admin_approval_action
    FROM appointments WHERE id = 39
");

$row = $result->fetch_assoc();
echo "Current state:\n";
echo "  Status: {$row['status']}\n";
echo "  KTT MSM: {$row['ktt_msm_status']} | KTT TTN: {$row['ktt_ttn_status']}\n";
echo "  Requires MSM: {$row['requires_ktt_msm_review']} | Requires TTN: {$row['requires_ktt_ttn_review']}\n";
echo "  Admin Action: {$row['admin_approval_action']}\n\n";

// Since KTT MSM is approved and KTT TTN is pending, KTT TTN needs to review
// KTT MSM should NOT see it (already approved)
echo "Analysis:\n";
echo "  - KTT MSM has APPROVED (status: approved)\n";
echo "  - KTT TTN is PENDING (status: pending)\n";
echo "  - Admin sent back to KTT (admin_action: send_to_ktt)\n";
echo "  - Therefore: KTT TTN must have rejected before, now needs to re-review\n\n";

echo "Fixing: Setting requires_ktt_msm_review=0, requires_ktt_ttn_review=1\n";

$update = $conn->query("
    UPDATE appointments SET
        requires_ktt_msm_review = 0,
        requires_ktt_ttn_review = 1
    WHERE id = 39
");

if ($update) {
    echo "✓ Fixed successfully!\n";

    // Verify
    $verify = $conn->query("SELECT requires_ktt_msm_review, requires_ktt_ttn_review FROM appointments WHERE id = 39")->fetch_assoc();
    echo "\nVerified new state:\n";
    echo "  Requires MSM: {$verify['requires_ktt_msm_review']} | Requires TTN: {$verify['requires_ktt_ttn_review']}\n";
    echo "  Now KTT TTN will see this appointment, KTT MSM will not\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}

$conn->close();
?>

