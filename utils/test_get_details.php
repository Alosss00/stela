<?php
/**
 * Setup Email Configuration
 * Update SMTP password and admin email addresses
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

echo "=== SETUP KONFIGURASI EMAIL ===\n\n";

$db = new Database();

// Update admin email addresses
echo "1. Update Email Admin...\n";
echo "   Current admin emails:\n";

$admins = $db->query("SELECT id, username, full_name, email FROM users WHERE role = 'admin'")->fetch_all(MYSQLI_ASSOC);

foreach ($admins as $admin) {
    echo "   - {$admin['username']}: {$admin['email']}\n";
}

echo "\n";

// Fix typo in admin1 email
$fixed = $db->query("UPDATE users SET email = 'agriawanwiranto05@gmail.com' WHERE username = 'admin1'");
if ($fixed) {
    echo "   ✓ Email admin1 diperbaiki: agriawanwiranto05@gmail.com\n";
}

echo "\n2. Konfigurasi SMTP Gmail:\n";
echo "   File: includes/notifications.php\n";
echo "   Baris 18: private \$smtp_password = '';\n\n";

echo "   📝 CARA MENDAPATKAN APP PASSWORD GMAIL:\n";
echo "   ----------------------------------------\n";
echo "   1. Buka: https://myaccount.google.com/security\n";
echo "   2. Aktifkan '2-Step Verification' jika belum aktif\n";
echo "   3. Buka: https://myaccount.google.com/apppasswords\n";
echo "   4. Pilih 'App': Mail, 'Device': Other (custom name)\n";
echo "   5. Klik 'Generate'\n";
echo "   6. Copy 16-digit password yang muncul (contoh: abcd efgh ijkl mnop)\n";
echo "   7. Paste ke includes/notifications.php:\n";
echo "      private \$smtp_password = 'abcdefghijklmnop'; // (tanpa spasi)\n\n";

echo "3. Testing Setelah Setup:\n";
echo "   php test_send_email.php\n\n";

echo "================================================================================\n";
echo "INFORMASI PENTING:\n";
echo "- Gmail SMTP memerlukan App Password, BUKAN password akun biasa\n";
echo "- App Password format: 16 karakter lowercase tanpa spasi\n";
echo "- Setelah setup, test dengan: php test_send_email.php\n";
echo "- Email akan dikirim ke semua admin saat:\n";
echo "  * Company menambah employee baru\n";
echo "  * Appointment ditolak oleh KTT\n";
echo "================================================================================\n";

