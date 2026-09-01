<?php
$file = 'resources/dept/reports.php';
$content = file_get_contents($file);
$content = str_replace('AND e.department = ?""', 'AND e.department = ?"', $content);
file_put_contents($file, $content);
echo "Fixed syntax error in reports.php\n";
?>
