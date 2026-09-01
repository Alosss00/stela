<?php
$fixes = [
    // File 1: superadmin/edit_employee.php - line 128
    'resources/superadmin/edit_employee.php' => [
        [
            "old" => "SELECT id FROM employees WHERE deleted_at IS NULL AND employee_code = '\$employee_code'",
            "new" => "SELECT id FROM employees WHERE deleted_at IS NULL AND employee_code = ?"
        ]
    ],
    // File 2: superadmin/verify_employee.php - appointment_number updates
    'resources/superadmin/verify_employee.php' => [
        [
            "old" => "UPDATE employees SET appointment_number = '\$appointment_number' WHERE id = ?",
            "new" => "UPDATE employees SET appointment_number = ? WHERE id = ?"
        ],
        [
            "old" => "UPDATE employees SET appointment_number = '\$existing_number' WHERE id = ?",
            "new" => "UPDATE employees SET appointment_number = ? WHERE id = ?"
        ]
    ],
    // File 3: ktt/approval.php - DELETE with user_id
    'resources/ktt/approval.php' => [
        [
            "old" => "DELETE FROM ktt_approvals WHERE appointment_id = ? AND ktt_user_id = \$current_user_id",
            "new" => "DELETE FROM ktt_approvals WHERE appointment_id = ? AND ktt_user_id = ?"
        ]
    ],
];

foreach ($fixes as $file => $replacements) {
    $content = file_get_contents($file);
    $changed = false;
    foreach ($replacements as $fix) {
        if (strpos($content, $fix['old']) !== false) {
            $content = str_replace($fix['old'], $fix['new'], $content);
            echo "Fixed in $file: " . substr($fix['old'], 0, 60) . "...\n";
            $changed = true;
        } else {
            echo "NOT FOUND in $file: " . substr($fix['old'], 0, 60) . "\n";
        }
    }
    if ($changed) {
        file_put_contents($file, $content);
    }
}
echo "Done\n";
?>
