<?php
/**
 * Test Appointment Rejection Notification
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/notifications.php';

echo "=== TEST APPOINTMENT REJECTION NOTIFICATION ===\n\n";

$db = new Database();

// Get an appointment to test
$appointment = $db->query("
    SELECT id, appointment_number, employee_id, status 
    FROM appointments 
    ORDER BY id DESC 
    LIMIT 1
")->fetch_assoc();

if (!$appointment) {
    echo "❌ No appointments found in database\n";
    echo "   Create an appointment first to test rejection notification.\n";
    exit;
}

echo "Found appointment:\n";
echo "  ID: {$appointment['id']}\n";
echo "  Number: {$appointment['appointment_number']}\n";
echo "  Status: {$appointment['status']}\n\n";

echo "Testing notifyAppointmentRejectedForReview...\n";
echo str_repeat("-", 80) . "\n";

$notif = new NotificationService();

try {
    $result = $notif->notifyAppointmentRejectedForReview($appointment['id']);
    
    if ($result) {
        echo "\n✅ SUCCESS! Rejection notification email sent.\n\n";
        echo "Check Gmail inbox:\n";
        echo "  - Subject: Surat Penunjukan Ditolak - Perlu Review Admin\n";
        echo "  - To: All admin emails\n";
        echo "  - Content: Appointment details with rejection info\n\n";
    } else {
        echo "\n❌ FAILED! Function returned false.\n";
        echo "Check error log for details.\n\n";
    }
} catch (Exception $e) {
    echo "\n❌ EXCEPTION: " . $e->getMessage() . "\n\n";
}

echo str_repeat("=", 80) . "\n";
echo "View detailed logs: php view_error_log.php\n";
echo str_repeat("=", 80) . "\n";

