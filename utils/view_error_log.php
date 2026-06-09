<?php
/**
 * View Error Log - Check Notification Debug Messages
 */

echo "=== CHECKING PHP ERROR LOG ===\n\n";

// Common PHP error log locations
$log_files = [
    'C:\laragon\logs\apache_error.log',
    'C:\laragon\logs\php_error.log',
    'C:\xampp\apache\logs\error.log',
    'C:\xampp\php\logs\php_error_log.txt',
    ini_get('error_log')
];

$found_log = null;
foreach ($log_files as $log_file) {
    if ($log_file && file_exists($log_file)) {
        $found_log = $log_file;
        break;
    }
}

if (!$found_log) {
    echo "❌ Error log file not found!\n";
    echo "PHP error_log setting: " . ini_get('error_log') . "\n\n";
    echo "Try checking these locations manually:\n";
    foreach ($log_files as $log) {
        if ($log) echo "  - $log\n";
    }
    exit;
}

echo "✓ Found error log: $found_log\n\n";
echo "Reading last 100 lines with [NOTIFICATION] keyword...\n";
echo str_repeat("=", 80) . "\n\n";

// Read last lines
$file = new SplFileObject($found_log);
$file->seek(PHP_INT_MAX);
$total_lines = $file->key();

$start_line = max(0, $total_lines - 100);
$file->seek($start_line);

$notification_logs = [];
while (!$file->eof()) {
    $line = $file->current();
    if (stripos($line, '[NOTIFICATION]') !== false || stripos($line, 'Notification error') !== false) {
        $notification_logs[] = $line;
    }
    $file->next();
}

if (empty($notification_logs)) {
    echo "No notification logs found in last 100 lines.\n\n";
    echo "This means either:\n";
    echo "1. No notification has been triggered yet\n";
    echo "2. Notifications are not being called\n";
    echo "3. Try actions that should trigger notifications:\n";
    echo "   - Add new employee as company user\n";
    echo "   - Resubmit rejected employee\n";
    echo "   - KTT reject appointment\n\n";
} else {
    echo "Found " . count($notification_logs) . " notification log entries:\n\n";
    foreach ($notification_logs as $log) {
        echo $log;
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "\nTo monitor in real-time, run:\n";
echo "Get-Content '$found_log' -Wait -Tail 50 | Select-String -Pattern 'NOTIFICATION'\n";
echo str_repeat("=", 80) . "\n";

