<?php
$file = 'resources/dept/reports.php';
$content = file_get_contents($file);
$content = preg_replace('/AND e\.department = \?"\s*GROUP BY/', "AND e.department = ?\n    GROUP BY", $content);
$content = preg_replace('/AND e\.department = \?"\s*ORDER BY/', "AND e.department = ?\n    ORDER BY", $content);
file_put_contents($file, $content);
echo "Fixed syntax error in reports.php\n";
?>
