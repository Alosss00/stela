<?php
/**
 * Application Configuration
 */

// Konfigurasi Database
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'u136581265_Toka_STELA');
define('DB_PASS', 'Hse_Stela01');
define('DB_NAME', 'u136581265_Toka_STELA');

// Konfigurasi Aplikasi
define('SITE_NAME', 'Expertise Appointment Letter System');
define('APP_VERSION', '2.0.0');

// Konfigurasi Elasticsearch
define('ELASTICSEARCH_HOST', getenv('ELASTICSEARCH_HOST') ?: 'http://127.0.0.1:9200');
define('ELASTICSEARCH_ENABLED', true);
define('ELASTICSEARCH_INDEX_PREFIX', getenv('ELASTICSEARCH_INDEX_PREFIX') ?: 'stela_');

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

// Load Composer Autoloader
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}
?>

