<?php
/**
 * Verify Notification Integration
 * Check all files have proper notification integration
 */

echo "=== NOTIFICATION INTEGRATION CHECK ===\n\n";

$files_to_check = [
    'user_add_employee.php' => 'New employee added',
    'dept_add_employee.php' => 'New employee added (dept)',
    'user_resubmit_employee.php' => 'Employee resubmitted',
    'dept_resubmit_employee.php' => 'Employee resubmitted (dept)',
    'approval.php' => 'Appointment rejected by KTT',
    'employees.php' => 'Employee added by admin (if non-admin user)'
];

foreach ($files_to_check as $file => $scenario) {
    echo "Checking: $file\n";
    echo "Scenario: $scenario\n";
    
    if (!file_exists($file)) {
        echo "  ❌ File not found\n\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check if notifications.php is included
    $has_require = preg_match("/require_once ['\"]includes\/notifications\.php['\"]/", $content);
    
    // Check if NotificationService is instantiated
    $has_service = preg_match("/new NotificationService\(\)/", $content);
    
    // Check if notification method is called
    $has_notify_new = preg_match("/notifyNewEmployeeAdded/", $content);
    $has_notify_reject = preg_match("/notifyAppointmentRejectedForReview/", $content);
    
    echo "  require notifications.php: " . ($has_require ? "✓" : "❌") . "\n";
    echo "  new NotificationService(): " . ($has_service ? "✓" : "❌") . "\n";
    
    if (strpos($file, 'approval') !== false) {
        echo "  notifyAppointmentRejectedForReview(): " . ($has_notify_reject ? "✓" : "❌") . "\n";
    } else {
        echo "  notifyNewEmployeeAdded(): " . ($has_notify_new ? "✓" : "❌") . "\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 80) . "\n\n";

// Test scenarios
echo "TEST SCENARIOS:\n\n";

echo "1. User adds new employee:\n";
echo "   - File: user_add_employee.php\n";
echo "   - Expected: Email sent to all admins\n";
echo "   - Subject: Tenaga Kerja Baru Perlu Verifikasi - [Company]\n\n";

echo "2. Department adds new employee:\n";
echo "   - File: dept_add_employee.php\n";
echo "   - Expected: Email sent to all admins\n";
echo "   - Subject: Tenaga Kerja Baru Perlu Verifikasi - [Company]\n\n";

echo "3. User resubmits employee (after rejection):\n";
echo "   - File: user_resubmit_employee.php ✨ JUST ADDED\n";
echo "   - Expected: Email sent to all admins\n";
echo "   - Subject: Tenaga Kerja Baru Perlu Verifikasi - [Company]\n\n";

echo "4. Department resubmits employee:\n";
echo "   - File: dept_resubmit_employee.php ✨ JUST ADDED\n";
echo "   - Expected: Email sent to all admins\n";
echo "   - Subject: Tenaga Kerja Baru Perlu Verifikasi - [Company]\n\n";

echo "5. KTT rejects appointment:\n";
echo "   - File: approval.php\n";
echo "   - Expected: Email sent to all admins\n";
echo "   - Subject: Surat Penunjukan Ditolak - Perlu Review Admin\n\n";

echo str_repeat("=", 80) . "\n";
echo "READY TO TEST!\n";
echo "Try the scenarios above from the website and check your Gmail inbox.\n";
echo str_repeat("=", 80) . "\n";

