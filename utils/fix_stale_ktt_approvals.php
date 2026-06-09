<?php
/**
 * Fix: Clean up old ktt_approvals records for appointments that are currently pending
 * This fixes the "You have already made a decision" error for resubmitted appointments
 * Run this once to fix existing data, then delete this file.
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isAdmin()) {
    die('Admin access required.');
}

$db = new Database();

// Find appointments that are pending but still have old ktt_approvals records
$stale = $db->query("
    SELECT DISTINCT a.id, a.appointment_number, a.status, a.ktt_msm_status, a.ktt_ttn_status,
           e.full_name, e.employee_code,
           (SELECT COUNT(*) FROM ktt_approvals ka WHERE ka.appointment_id = a.id) as old_decisions
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status = 'pending'
    AND EXISTS (SELECT 1 FROM ktt_approvals ka WHERE ka.appointment_id = a.id)
");

if ($stale->num_rows == 0) {
    echo "<h3>No stale ktt_approvals records found. Everything is clean!</h3>";
} else {
    echo "<h3>Found " . $stale->num_rows . " appointment(s) with stale KTT decisions:</h3>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr><th>Appointment</th><th>Employee</th><th>Status</th><th>MSM Status</th><th>TTN Status</th><th>Old Decisions</th><th>Action</th></tr>";
    
    while ($row = $stale->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['appointment_number']}</td>";
        echo "<td>{$row['full_name']} ({$row['employee_code']})</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['ktt_msm_status']}</td>";
        echo "<td>{$row['ktt_ttn_status']}</td>";
        echo "<td>{$row['old_decisions']}</td>";
        echo "<td>Will be cleaned</td>";
        echo "</tr>";
    }
    echo "</table><br>";

    if (isset($_GET['fix']) && $_GET['fix'] == '1') {
        // Reset stale data
        $stale->data_seek(0);
        $fixed = 0;
        while ($row = $stale->fetch_assoc()) {
            $appt_id = intval($row['id']);
            
            // Delete old ktt_approvals
            $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $appt_id");
            
            // Reset KTT statuses
            $db->query("UPDATE appointments SET 
                        ktt_msm_status = 'pending',
                        ktt_ttn_status = 'pending',
                        ktt1_approved_by = NULL,
                        ktt1_approved_date = NULL,
                        ktt2_approved_by = NULL,
                        ktt2_approved_date = NULL,
                        requires_ktt_msm_review = 1,
                        requires_ktt_ttn_review = 1
                        WHERE id = $appt_id AND status = 'pending'");
            $fixed++;
        }
        echo "<div style='padding: 20px; background: #d1fae5; border-radius: 8px; margin: 20px 0;'>";
        echo "<strong>Done!</strong> Cleaned $fixed appointment(s). KTT can now approve/reject these again.";
        echo "</div>";
        echo "<p><a href='appointments.php'>Back to Appointments</a></p>";
    } else {
        echo "<p><a href='fix_stale_ktt_approvals.php?fix=1' style='padding: 10px 20px; background: #7C3AED; color: white; border-radius: 6px; text-decoration: none; font-weight: bold;'>Click here to fix all</a></p>";
    }
}
?>

