<?php
/**
 * Application Configuration
 */

// Konfigurasi Database (dipisahkan di config/database.php untuk kemudahan maintenance)
require_once __DIR__ . '/database.php';

// Konfigurasi Aplikasi
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Expertise Appointment Letter System');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.0.0');
}

// Konfigurasi Elasticsearch
if (!defined('ELASTICSEARCH_HOST')) {
    define('ELASTICSEARCH_HOST', getenv('ELASTICSEARCH_HOST') ?: 'https://df6c4bcf7e:b1bdce1e5fcf15ae0dca@focused-holly-1rb12wdt.ap-southeast-2.bonsaisearch.net:443');
}
if (!defined('ELASTICSEARCH_ENABLED')) {
    define('ELASTICSEARCH_ENABLED', true);
}
if (!defined('ELASTICSEARCH_INDEX_PREFIX')) {
    define('ELASTICSEARCH_INDEX_PREFIX', getenv('ELASTICSEARCH_INDEX_PREFIX') ?: 'stela_');
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Upload settings
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
}
if (!defined('ALLOWED_IMAGE_TYPES')) {
    define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
}
if (!defined('ALLOWED_DOC_TYPES')) {
    define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx']);
}

// Load Composer Autoloader
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}
