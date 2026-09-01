<?php
$file = 'resources/superadmin/verify_employee.php';
$content = file_get_contents($file);

// Both appointment_number update queries need to pass the value + id params
// Pattern 1: with $appointment_number variable
$old1 = 'UPDATE employees SET appointment_number = ? WHERE id = ?", [$employee_id]);';
$new1 = 'UPDATE employees SET appointment_number = ? WHERE id = ?", [$appointment_number, $employee_id]);';
$count1 = substr_count($content, $old1);
$content = str_replace($old1, $new1, $content);

// Pattern 2: with $existing_number variable (different variable name)
$old2 = 'UPDATE employees SET appointment_number = ? WHERE id = ?", [$employee_id]);';
// After fix1 all occurrences with $appointment_number are changed.
// Now check if there is still an occurrence that was the $existing_number one 
// In the original code it was: "UPDATE employees SET appointment_number = '$existing_number' WHERE id = ?", [$employee_id]
// So both were replaced to same pattern. Let us check if we have two of same pattern remaining.
$count2 = substr_count($content, $old2);
if ($count2 > 0) {
    // Replace all remaining with the $existing_number variant - but we don't know which to use which variable
    // Need to look at context
    // Let's see what remaining one looks like
    $pos = strpos($content, $old2);
    $context = substr($content, max(0, $pos - 200), 400);
    echo "Remaining context:\n$context\n";
}

file_put_contents($file, $content);
echo "Replacements of pattern1: $count1\n";
echo "Remaining pattern2 (should be 0 or 1): $count2\n";
?>
