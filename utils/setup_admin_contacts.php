<?php
/**
 * Quick Setup: Update Admin Contact Information
 */

require_once 'includes/db.php';

$db = new Database();

echo "=== QUICK SETUP: UPDATE ADMIN CONTACTS ===\n\n";

// Get all admin users
$admins = $db->query("SELECT id, username, full_name, email, phone FROM users WHERE role = 'admin' ORDER BY username");

if (!$admins || $admins->num_rows == 0) {
    echo "❌ No admin users found!\n";
    exit;
}

echo "Current Admin Users:\n";
echo str_repeat("-", 80) . "\n";

$admin_list = [];
while ($admin = $admins->fetch_assoc()) {
    $admin_list[] = $admin;
    echo "ID: {$admin['id']} | Username: {$admin['username']} | Name: {$admin['full_name']}\n";
    echo "  Email: " . ($admin['email'] ?: '(not set)') . "\n";
    echo "  Phone: " . ($admin['phone'] ?: '(not set)') . "\n\n";
}

// Example updates
echo "\n=== QUICK UPDATE EXAMPLES ===\n";
echo str_repeat("-", 80) . "\n\n";

echo "You can run these SQL queries to update admin contacts:\n\n";

foreach ($admin_list as $admin) {
    $sample_email = strtolower($admin['username']) . '@mining-system.com';
    $sample_phone = '6285173023567' . str_pad($admin['id'], 4, '0', STR_PAD_LEFT);
    
    echo "-- Update {$admin['username']}\n";
    echo "UPDATE users SET \n";
    echo "  email = '{$sample_email}',\n";
    echo "  phone = '{$sample_phone}'\n";
    echo "WHERE id = {$admin['id']};\n\n";
}

echo "\n=== OR USE THIS INTERACTIVE SCRIPT ===\n\n";

// Interactive mode (uncomment to use)
/*
foreach ($admin_list as $admin) {
    echo "\nUpdating: {$admin['username']} ({$admin['full_name']})\n";
    echo str_repeat("-", 40) . "\n";
    
    // Get email
    echo "Enter email (current: " . ($admin['email'] ?: 'not set') . "): ";
    $email = trim(fgets(STDIN));
    if (empty($email)) {
        $email = $admin['email'] ?: '';
    }
    
    // Get phone
    echo "Enter phone (62xxx format, current: " . ($admin['phone'] ?: 'not set') . "): ";
    $phone = trim(fgets(STDIN));
    if (empty($phone)) {
        $phone = $admin['phone'] ?: '';
    }
    
    // Update database
    if (!empty($email) || !empty($phone)) {
        $email_escaped = $db->escapeString($email);
        $phone_escaped = $db->escapeString($phone);
        
        $sql = "UPDATE users SET ";
        $updates = [];
        if (!empty($email)) $updates[] = "email = '$email_escaped'";
        if (!empty($phone)) $updates[] = "phone = '$phone_escaped'";
        $sql .= implode(', ', $updates);
        $sql .= " WHERE id = {$admin['id']}";
        
        if ($db->query($sql)) {
            echo "✓ Updated successfully!\n";
        } else {
            echo "✗ Update failed: " . $db->getConnection()->error . "\n";
        }
    } else {
        echo "⊘ Skipped (no data entered)\n";
    }
}
*/

echo "\n";
echo "Format Nomor Telepon yang Benar:\n";
echo "  ✓ 6281234567890 (dengan kode negara 62)\n";
echo "  ✗ 081234567890 (tanpa 62)\n";
echo "  ✗ +62 851 7302 3567 (dengan spasi)\n\n";

echo "Setelah update, jalankan: php test_notifications.php\n";
echo "untuk memverifikasi konfigurasi.\n\n";
?>

