<?php
$rootDir = dirname(__DIR__);

function scanDirCheck($dir, &$errors) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === 'vendor' || $file === '.git') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            scanDirCheck($path, $errors);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $output = [];
            $returnCode = 0;
            exec("php -l " . escapeshellarg($path), $output, $returnCode);
            if ($returnCode !== 0) {
                $errors[] = implode("\n", $output);
            }
        }
    }
}

$errors = [];
scanDirCheck($rootDir, $errors);

if (empty($errors)) {
    echo "SUCCESS: ALL PHP files passed syntax check with 0 errors!\n";
} else {
    echo "ERRORS FOUND:\n" . implode("\n---\n", $errors) . "\n";
}
