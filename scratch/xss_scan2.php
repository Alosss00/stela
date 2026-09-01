<?php
// Scan for DB-value echoed directly without htmlspecialchars
$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources'));
$risky = [];
foreach ($dir as $file) {
    if ($file->getExtension() !== 'php') continue;
    $lines = file($file->getPathname());
    foreach ($lines as $n => $line) {
        // Look for <?= $var that is NOT wrapped in htmlspecialchars
        if (preg_match('/<\?=\s*\$(?!_|row\b|message\b|error\b|page_title\b|csrf|search_query\b)/', $line)) {
            if (strpos($line, 'htmlspecialchars') === false && strpos($line, 'intval') === false && strpos($line, 'date(') === false && strpos($line, '?? 0') === false) {
                $risky[] = basename($file->getPathname()) . ':' . ($n+1) . ': ' . trim($line);
            }
        }
    }
}
echo count($risky) . " potential unescaped output(s) found:\n";
foreach (array_slice($risky, 0, 30) as $r) {
    echo $r . "\n";
}
?>
