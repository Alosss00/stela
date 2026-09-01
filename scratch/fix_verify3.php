<?php
$file = 'resources/superadmin/verify_employee.php';
$content = file_get_contents($file);
// Replace the second occurrence - find it by line context
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (strpos($line, 'appointment_number = ? WHERE id = ?') !== false && strpos($line, '$appointment_number, $employee_id') !== false) {
        // Check if previous non-empty line mentions existing_number/existing context
        $prevContext = implode("\n", array_slice($lines, max(0, $i-10), 10));
        if (strpos($prevContext, 'existing_number') !== false || strpos($prevContext, 'verified-existing') !== false) {
            $lines[$i] = str_replace('[$appointment_number, $employee_id]', '[$existing_number, $employee_id]', $line);
            echo "Fixed line " . ($i+1) . "\n";
        } else {
            echo "Kept $appointment_number at line " . ($i+1) . "\n";
        }
    }
}
$content = implode("\n", $lines);
file_put_contents($file, $content);
echo "Done\n";
?>
