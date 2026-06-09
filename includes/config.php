<?php
/**
 * Application Configuration
 */

// Konfigurasi Database
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'u136581265_MineTokaSTELA');
define('DB_PASS', 'Hse_Stela01');
define('DB_NAME', 'u136581265_MineTokaSTELA');

// Konfigurasi Aplikasi
define('SITE_NAME', 'Expertise Appointment Letter System');
define('APP_VERSION', '2.0.0');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Upload settings
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx']);
?>

