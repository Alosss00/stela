<?php
// Scan for potential SQL Injection (variable interpolation inside query)
$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources'));
$sqlRisks = [];
foreach ($dir as $file) {
    if ($file->getExtension() !== 'php') continue;
    $lines = file($file->getPathname());
    foreach ($lines as $n => $line) {
        if (preg_match('/->query\(\s*["\'].*\$.*["\']\s*\)/i', $line)) {
            // exclude count queries where the $ is just a table name or something safe
            if (strpos($line, 'COUNT') === false && strpos($line, 'WHERE') !== false) {
                 $sqlRisks[] = basename($file->getPathname()) . ':' . ($n+1) . ': ' . trim($line);
            }
        }
    }
}
echo "Remaining SQL injection risks in resources:\n";
foreach (array_slice($sqlRisks, 0, 20) as $r) {
    echo $r . "\n";
}

// Check for unserialize
$unserializeRisks = [];
$dirAll = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.'));
foreach ($dirAll as $file) {
    if ($file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());
    if (strpos($content, 'unserialize') !== false && strpos($file->getPathname(), 'vendor') === false) {
         $unserializeRisks[] = $file->getPathname();
    }
}
echo "\nUnserialize found in:\n";
print_r($unserializeRisks);
?>
