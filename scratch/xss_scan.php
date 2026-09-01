<?php
$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources'));
foreach ($dir as $file) {
    if ($file->getExtension() !== 'php') continue;
    $lines = file($file->getPathname());
    foreach ($lines as $n => $line) {
        if (preg_match('/echo\s+\$_(GET|POST|REQUEST|COOKIE)\[|<\?=\s*\$_(GET|POST|REQUEST|COOKIE)\[/', $line)) {
            echo $file->getPathname() . ':' . ($n+1) . ': ' . trim($line) . "\n";
        }
    }
}
?>
