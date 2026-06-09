<?php
// Script to clear all PHP sessions to force users to re-login
// This will refresh their session with updated company names

echo "=== CLEARING ALL USER SESSIONS ===\n\n";

// Get session save path
$session_path = session_save_path();
if (empty($session_path)) {
    $session_path = sys_get_temp_dir();
}

echo "Session path: $session_path\n";
echo "Looking for session files...\n\n";

// Count and list session files
$session_files = glob($session_path . '/sess_*');
$count = 0;

if ($session_files) {
    echo "Found " . count($session_files) . " session files:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($session_files as $file) {
        $filename = basename($file);
        $size = filesize($file);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        
        echo "File: $filename\n";
        echo "  Size: $size bytes\n";
        echo "  Modified: $modified\n";
        
        // Try to delete
        if (unlink($file)) {
            echo "  Status: ✓ DELETED\n";
            $count++;
        } else {
            echo "  Status: ❌ FAILED TO DELETE\n";
        }
        echo "\n";
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "✓ Successfully deleted $count session files\n";
    echo "\nAll users must now re-login with fresh sessions.\n";
    echo "Their sessions will now use the updated company names.\n";
} else {
    echo "No session files found.\n";
    echo "Either no users are currently logged in, or sessions are stored elsewhere.\n";
    echo "\nNote: Users should still logout and login again to refresh their session.\n";
}
?>

