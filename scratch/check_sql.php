<?php
$files = [
    'resources/superadmin/employees.php',
    'resources/superadmin/edit_employee.php',
    'resources/superadmin/positions.php',
    'resources/superadmin/supervision_areas.php'
];
foreach ($files as $file) {
    echo "--- $file ---\n";
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (strpos($line, '->query(') !== false && strpos($line, '$') !== false) {
            echo ($i+1) . ": " . trim($line) . "\n";
        }
    }
}
?>
