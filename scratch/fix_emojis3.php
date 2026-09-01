<?php
$file = 'app/Services/NotificationService.php';
$content = file_get_contents($file);

$content = preg_replace('/"[^"]*\*NEW EMPLOYEE NOTIFICATION\*/', '"🔔 *NEW EMPLOYEE NOTIFICATION*', $content);

file_put_contents($file, $content);
echo "Fixed NEW EMPLOYEE";
?>
