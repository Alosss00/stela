<?php
$file = 'resources/views/admin/appointments.php';
$content = file_get_contents($file);
if (substr($content, 0, 2) === "\xFF\xFE") { // UTF-16LE BOM
    $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
    file_put_contents($file, $content);
}
