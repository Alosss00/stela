<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();

$numbers = ['01/TT/MSM/09/2026', '20/TT/MSM/08/2026'];
$numbers_str = "'" . implode("', '", $numbers) . "'";

// Show columns
$res = $db->query("DESCRIBE appointments");
echo "Appointments columns: ";
while($r = $res->fetch_assoc()) {
    echo $r['Field'] . ", ";
}
echo "\n";

// Get appointments
$sql = "SELECT * FROM appointments WHERE appointment_number IN ($numbers_str) OR deleted_at IS NOT NULL";
$result = $db->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        
        // Delete queries
        $db->query("DELETE FROM ktt_approvals WHERE appointment_id = {$row['id']}");
        $db->query("DELETE FROM appointments WHERE id = {$row['id']}");
    }
    echo "Deleted records!\n";
} else {
    echo "No records found matching those numbers or soft deleted.\n";
}
