<?php
require 'includes/db.php';
$db = new Database();
$admin = $db->query("SELECT username, email FROM users WHERE username='admin1'")->fetch_assoc();
echo "Current email: {$admin['email']}\n";

// Cek apakah email salah
if ($admin['email'] == 'agriawanwiranto05@gmail.com') {
    echo "Email salah, update ke agriawanwiranto5@gmail.com\n";
    $db->query("UPDATE users SET email = 'agriawanwiranto5@gmail.com' WHERE username = 'admin1'");
    echo "✓ Email berhasil diupdate!\n";
} else {
    echo "✓ Email sudah benar: agriawanwiranto5@gmail.com\n";
}

