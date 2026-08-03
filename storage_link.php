<?php
/**
 * STELA-2 Storage Link Script (Laravel-style php artisan storage:link for Hostinger / Shared Hosting)
 * Creates persistent storage directory and symbolic link to assets/uploads.
 */

header('Content-Type: text/html; charset=UTF-8');

$rootDir = __DIR__;
$storageUploadsDir = $rootDir . '/storage/uploads';
$assetsUploadsLink = $rootDir . '/assets/uploads';
$publicAssetsUploadsLink = $rootDir . '/public/assets/uploads';

// Sub-folders to ensure exist
$subFolders = ['cv', 'statements', 'certifications', 'qrcodes', 'appointments'];

echo "<h2>STELA-2 Storage Link Generator</h2>";
echo "<p>RootDir: <code>{$rootDir}</code></p>";

// 1. Create persistent storage directories
echo "<h3>1. Checking & Creating Persistent Storage Directories...</h3><ul>";
foreach ($subFolders as $folder) {
    $dirPath = $storageUploadsDir . '/' . $folder;
    if (!file_exists($dirPath)) {
        if (mkdir($dirPath, 0755, true)) {
            echo "<li style='color:green;'>[CREATED] Storage folder: <code>{$dirPath}</code></li>";
        } else {
            echo "<li style='color:red;'>[ERROR] Failed to create folder: <code>{$dirPath}</code></li>";
        }
    } else {
        echo "<li style='color:blue;'>[EXISTS] Storage folder: <code>{$dirPath}</code></li>";
    }
}
echo "</ul>";

// Helper function to create symlink safely
function createStorageSymlink($linkPath, $targetPath) {
    echo "<h4>Linking <code>{$linkPath}</code> &rarr; <code>{$targetPath}</code></h4>";
    
    // Check if linkPath exists
    if (is_link($linkPath)) {
        $existingTarget = readlink($linkPath);
        echo "<p style='color:blue;'>[EXISTS] Symlink already points to: <code>{$existingTarget}</code></p>";
        return;
    }

    if (file_exists($linkPath)) {
        if (is_dir($linkPath)) {
            // Check if directory is empty before removing
            $files = array_diff(scandir($linkPath), array('.','..'));
            if (empty($files)) {
                rmdir($linkPath);
                echo "<p style='color:orange;'>[REMOVED] Empty directory <code>{$linkPath}</code> removed to create symlink.</p>";
            } else {
                echo "<p style='color:red;'>[WARNING] Directory <code>{$linkPath}</code> is not empty! Please backup and delete files inside before creating symlink.</p>";
                return;
            }
        } else {
            unlink($linkPath);
        }
    }

    // Try creating symlink
    if (@symlink($targetPath, $linkPath)) {
        echo "<p style='color:green; font-weight:bold;'>[SUCCESS] Symlink created successfully!</p>";
    } else {
        echo "<p style='color:red;'>[FAILED] Could not create symlink using PHP symlink().</p>";
        echo "<p>Alternative options for Hostinger:</p>";
        echo "<ul>";
        echo "<li><strong>SSH Hostinger:</strong> Run command:<br><code>ln -s " . escapeshellarg($targetPath) . " " . escapeshellarg($linkPath) . "</code></li>";
        echo "<li><strong>Cron Job Hostinger:</strong> Create 1-time cron job command:<br><code>ln -s " . escapeshellarg($targetPath) . " " . escapeshellarg($linkPath) . "</code></li>";
        echo "</ul>";
    }
}

// 2. Link assets/uploads -> storage/uploads
echo "<h3>2. Creating Symbolic Links...</h3>";
createStorageSymlink($assetsUploadsLink, $storageUploadsDir);

if (file_exists($rootDir . '/public/assets')) {
    createStorageSymlink($publicAssetsUploadsLink, $storageUploadsDir);
}

echo "<hr><p style='color:green;'><strong>Process Finished!</strong> Check status messages above.</p>";
