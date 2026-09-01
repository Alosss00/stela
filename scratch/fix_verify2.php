<?php
$file = 'resources/superadmin/verify_employee.php';
$content = file_get_contents($file);

// The second occurrence (line ~497) should use $existing_number not $appointment_number
// We can identify it by its surrounding context: it comes after "verified-existing-updated-reset-ktt"
$old = "// Update appointment_number in employees table for tracking
                            \$db->query(\"UPDATE employees SET appointment_number = ? WHERE id = ?\", [\$appointment_number, \$employee_id]);
                        }";
$new = "// Update appointment_number in employees table for tracking
                            \$db->query(\"UPDATE employees SET appointment_number = ? WHERE id = ?\", [\$existing_number, \$employee_id]);
                        }";
if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new, $content);
    echo "Fixed existing_number reference\n";
} else {
    echo "Pattern not found, checking manually\n";
    // Check how many times appointment_number = ? appears
    echo "Count of appt_number param: " . substr_count($content, "[$appointment_number, $employee_id]") . "\n";
}

file_put_contents($file, $content);
echo "Done\n";
?>
