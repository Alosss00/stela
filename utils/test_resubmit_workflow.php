<?php
require_once 'includes/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

echo "Testing Appointment Resubmit Workflow\n";
echo str_repeat('=', 100) . "\n\n";

$appointment_id = 40;

echo "BEFORE RESUBMIT:\n";
echo str_repeat('-', 100) . "\n";

$before = $conn->query("
    SELECT a.id, a.appointment_number, a.status,
           a.ktt_msm_status, a.ktt_ttn_status,
           a.requires_ktt_msm_review, a.requires_ktt_ttn_review,
           a.admin_approval_action, a.admin_approved_by,
           e.verification_status, e.resubmit_count
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.id = $appointment_id
")->fetch_assoc();

echo "Appointment: {$before['appointment_number']}\n";
echo "  Status: {$before['status']}\n";
echo "  Admin Action: " . ($before['admin_approval_action'] ?? 'NULL') . "\n";
echo "  Admin Approved By: " . ($before['admin_approved_by'] ?? 'NULL') . "\n";
echo "  KTT MSM: {$before['ktt_msm_status']} | KTT TTN: {$before['ktt_ttn_status']}\n";
echo "  Requires MSM: {$before['requires_ktt_msm_review']} | Requires TTN: {$before['requires_ktt_ttn_review']}\n";
echo "  Employee Verification: {$before['verification_status']}\n";
echo "  Resubmit Count: {$before['resubmit_count']}\n\n";

// Simulate resubmit action
echo "EXECUTING RESUBMIT ACTION:\n";
echo str_repeat('-', 100) . "\n";

// Get which KTT needs to review
$appt_details = $conn->query("
    SELECT requires_ktt_msm_review, requires_ktt_ttn_review,
           ktt1_approved_by, ktt2_approved_by
    FROM appointments
    WHERE id = $appointment_id
")->fetch_assoc();

// Prepare KTT status reset based on which KTT needs to review
$ktt_status_reset = "";
if ($appt_details['requires_ktt_msm_review'] == 1) {
    $ktt_status_reset = ", ktt_msm_status = 'pending', ktt1_approved_by = NULL, ktt1_approved_date = NULL";
    echo "  - Resetting KTT MSM status to pending\n";
}
if ($appt_details['requires_ktt_ttn_review'] == 1) {
    $ktt_status_reset .= ", ktt_ttn_status = 'pending', ktt2_approved_by = NULL, ktt2_approved_date = NULL";
    echo "  - Resetting KTT TTN status to pending\n";
}

$update_sql = "UPDATE appointments SET
              admin_approval_action = NULL,
              admin_approval_notes = NULL,
              admin_approved_by = NULL,
              admin_approved_date = NULL
              $ktt_status_reset
              WHERE id = $appointment_id";

if ($conn->query($update_sql)) {
    echo "✓ Appointment resubmitted successfully!\n";

    // Also delete the rejection records from ktt_approvals for the rejecting KTT
    if ($appt_details['requires_ktt_msm_review'] == 1 && $appt_details['ktt1_approved_by']) {
        $conn->query("DELETE FROM ktt_approvals WHERE appointment_id = $appointment_id AND ktt_user_id = {$appt_details['ktt1_approved_by']}");
        echo "  - Deleted KTT MSM rejection record\n";
    }
    if ($appt_details['requires_ktt_ttn_review'] == 1 && $appt_details['ktt2_approved_by']) {
        $conn->query("DELETE FROM ktt_approvals WHERE appointment_id = $appointment_id AND ktt_user_id = {$appt_details['ktt2_approved_by']}");
        echo "  - Deleted KTT TTN rejection record\n";
    }
    echo "\n";
} else {
    echo "✗ Error: " . $conn->error . "\n\n";
    exit();
}

echo "AFTER RESUBMIT:\n";
echo str_repeat('-', 100) . "\n";

$after = $conn->query("
    SELECT a.id, a.appointment_number, a.status,
           a.ktt_msm_status, a.ktt_ttn_status,
           a.requires_ktt_msm_review, a.requires_ktt_ttn_review,
           a.admin_approval_action, a.admin_approved_by
    FROM appointments a
    WHERE a.id = $appointment_id
")->fetch_assoc();

echo "Appointment: {$after['appointment_number']}\n";
echo "  Status: {$after['status']}\n";
echo "  Admin Action: " . ($after['admin_approval_action'] ?? 'NULL') . "\n";
echo "  Admin Approved By: " . ($after['admin_approved_by'] ?? 'NULL') . "\n";
echo "  KTT MSM: {$after['ktt_msm_status']} | KTT TTN: {$after['ktt_ttn_status']}\n";
echo "  Requires MSM: {$after['requires_ktt_msm_review']} | Requires TTN: {$after['requires_ktt_ttn_review']}\n\n";

// Check if KTT TTN will see this appointment
echo "VISIBILITY CHECK:\n";
echo str_repeat('-', 100) . "\n";

// Simulate KTT TTN query (user_id = 8)
$ktt_ttn_query = "
    SELECT a.id, a.appointment_number, e.full_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status = 'pending'
    AND a.requires_ktt_ttn_review = 1
    AND a.ktt_ttn_status = 'pending'
    AND a.id = $appointment_id
";

$ktt_ttn_result = $conn->query($ktt_ttn_query);

if ($ktt_ttn_result && $ktt_ttn_result->num_rows > 0) {
    $visible = $ktt_ttn_result->fetch_assoc();
    echo "✓ KTT TTN WILL SEE THIS APPOINTMENT\n";
    echo "  - Appointment: {$visible['appointment_number']}\n";
    echo "  - Employee: {$visible['full_name']}\n";
} else {
    echo "✗ KTT TTN WILL NOT SEE THIS APPOINTMENT\n";
    echo "  Problem detected! Appointment should be visible to KTT TTN.\n";
}

echo "\n";

// Simulate KTT MSM query (user_id = 7)
$ktt_msm_query = "
    SELECT a.id, a.appointment_number, e.full_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status = 'pending'
    AND a.requires_ktt_msm_review = 1
    AND a.ktt_msm_status = 'pending'
    AND a.id = $appointment_id
";

$ktt_msm_result = $conn->query($ktt_msm_query);

if ($ktt_msm_result && $ktt_msm_result->num_rows > 0) {
    echo "✗ WARNING: KTT MSM WILL ALSO SEE THIS APPOINTMENT\n";
    echo "  This is WRONG - KTT MSM already approved!\n";
} else {
    echo "✓ KTT MSM WILL NOT SEE THIS APPOINTMENT (Correct - already approved)\n";
}

echo "\n" . str_repeat('=', 100) . "\n";
echo "TEST COMPLETE!\n";

$conn->close();
?>

