<?php
$admin_file = 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\admin\employees_status.php';
$user_file = 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\user\employees_status.php';

echo "User file matches for 'Detail':\n";
if (file_exists($user_file)) {
    $lines = file($user_file);
    foreach ($lines as $i => $line) {
        if (stripos($line, 'Detail') !== false && stripos($line, '<button') !== false || stripos($line, '<a ') !== false && stripos($line, 'Detail') !== false) {
            echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
        }
    }
}

echo "\nAdmin file matches for 'Detail':\n";
if (file_exists($admin_file)) {
    $lines = file($admin_file);
    foreach ($lines as $i => $line) {
        if (stripos($line, 'Detail') !== false && stripos($line, '<button') !== false || stripos($line, '<a ') !== false && stripos($line, 'Detail') !== false) {
            echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
        }
    }
}
