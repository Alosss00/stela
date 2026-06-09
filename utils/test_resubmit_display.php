<?php
/**
 * Test Script: Setup Resubmit Display
 * 
 * This will create test data to show the "Resubmitted" badge in approval.php
 */

require_once 'includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Setting up test data for Resubmit System Display</h2>";
echo "<hr>";

// Test Case 1: Appointment resubmitted 2 times (rejected by KTT TTN)
echo "<h3>Test Case 1: Appointment ID 44</h3>";
$sql1 = "UPDATE appointments SET 
         resubmit_count = 2,
         requires_ktt_msm_review = 1,
         requires_ktt_ttn_review = 0,
         last_rejected_by_ktt = 'ttn',
         status = 'pending',
         ktt_msm_status = 'pending',
         ktt_ttn_status = 'approved'
         WHERE id = 44";

if ($conn->query($sql1)) {
    echo "<p style='color: green;'>? Appointment 44 set as: <strong>Resubmitted 2 times</strong></p>";
    echo "<ul>";
    echo "<li>Status: pending</li>";
    echo "<li>Last rejected by: KTT TTN</li>";
    echo "<li>Requires MSM Review: YES (because TTN rejected)</li>";
    echo "<li>Requires TTN Review: NO (because TTN already approved)</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>? Error: " . $conn->error . "</p>";
}

echo "<hr>";

// Test Case 2: Appointment resubmitted 1 time (rejected by KTT MSM)
echo "<h3>Test Case 2: Appointment ID 43</h3>";
$sql2 = "UPDATE appointments SET 
         resubmit_count = 1,
         requires_ktt_msm_review = 0,
         requires_ktt_ttn_review = 1,
         last_rejected_by_ktt = 'msm',
         status = 'pending',
         ktt_msm_status = 'approved',
         ktt_ttn_status = 'pending'
         WHERE id = 43";

if ($conn->query($sql2)) {
    echo "<p style='color: green;'>? Appointment 43 set as: <strong>Resubmitted 1 time</strong></p>";
    echo "<ul>";
    echo "<li>Status: pending</li>";
    echo "<li>Last rejected by: KTT MSM</li>";
    echo "<li>Requires MSM Review: NO (because MSM already approved)</li>";
    echo "<li>Requires TTN Review: YES (because MSM rejected)</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>? Error: " . $conn->error . "</p>";
}

echo "<hr>";

// Verification
echo "<h3>Verification - Current Data:</h3>";
$verify = $conn->query("
    SELECT id, appointment_number, resubmit_count, 
           requires_ktt_msm_review, requires_ktt_ttn_review,
           last_rejected_by_ktt, status,
           ktt_msm_status, ktt_ttn_status
    FROM appointments 
    WHERE id IN (43, 44)
    ORDER BY id DESC
");

if ($verify && $verify->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Number</th>";
    echo "<th>Resubmit Count</th>";
    echo "<th>MSM Review</th>";
    echo "<th>TTN Review</th>";
    echo "<th>Last Rejected By</th>";
    echo "<th>Status</th>";
    echo "</tr>";
    
    while ($row = $verify->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['appointment_number']}</td>";
        echo "<td style='font-weight: bold; color: ".($row['resubmit_count'] > 0 ? 'orange' : 'black')."'>{$row['resubmit_count']}</td>";
        echo "<td>".($row['requires_ktt_msm_review'] ? '<span style="color: red;">YES</span>' : 'NO')."</td>";
        echo "<td>".($row['requires_ktt_ttn_review'] ? '<span style="color: red;">YES</span>' : 'NO')."</td>";
        echo "<td>".($row['last_rejected_by_ktt'] ? strtoupper($row['last_rejected_by_ktt']) : '-')."</td>";
        echo "<td>{$row['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Login as KTT MSM</strong> (user_id 7) and go to approval.php</li>";
echo "<li>You should see <strong>Appointment 44</strong> with <span style='background: #fff7ed; color: #c2410c; padding: 4px 10px; border-radius: 12px;'><i class='fas fa-redo'></i> Resubmitted</span> badge (orange colored)</li>";
echo "<li><strong>Login as KTT TTN</strong> (user_id 8) and go to approval.php</li>";
echo "<li>You should see <strong>Appointment 43</strong> with the resubmitted badge</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='approval.php' style='padding: 10px 20px; background: #37474F; color: white; text-decoration: none; border-radius: 6px;'>Go to Approval Page</a></p>";
echo "<p><a href='dashboard.php' style='padding: 10px 20px; background: #2E7D32; color: white; text-decoration: none; border-radius: 6px;'>Back to Dashboard</a></p>";

$conn->close();
?>

