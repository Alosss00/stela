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
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
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

// ============================================================
// KONFIGURASI PATH UPLOAD FILE
// ============================================================
// UPLOAD_PHYSICAL_PATH : Path absolut di server tempat file disimpan.
//   - Disimpan di LUAR folder project agar tidak hilang saat git pull.
//   - Ubah path ini sesuai environment Anda:
//       * Hostinger : '/home/u123456789/uploads_stela'
//                     (ganti u123456789 dengan username akun Anda)
//       * XAMPP local: 'C:/xampp/htdocs/uploads_stela'
//       * Laragon   : 'C:/laragon/www/uploads_stela'
//
// UPLOAD_URL : URL publik untuk mengakses file yang diupload.
//   - Harus mengarah ke folder yang sama dengan UPLOAD_PHYSICAL_PATH.
//   - Ubah URL ini sesuai domain/subdomain Anda:
//       * Hostinger : 'https://yourdomain.com/uploads_stela'
//                     (symlink /public_html/uploads_stela -> /home/.../uploads_stela)
//       * Local     : 'http://localhost/uploads_stela'
// ============================================================

if (!defined('UPLOAD_PHYSICAL_PATH')) {
    // Default: satu level di atas root project (keluar dari folder git repo)
    // Contoh: jika project ada di /public_html/stela-2/
    //         maka uploads masuk ke /public_html/uploads_stela/
    define('UPLOAD_PHYSICAL_PATH', dirname(dirname(__DIR__)) . '/uploads_stela');
}

if (!defined('UPLOAD_URL')) {
    // Bangun URL otomatis berdasarkan host dan posisi UPLOAD_PHYSICAL_PATH
    // relatif terhadap DOCUMENT_ROOT
    $__protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $__host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $__doc_root  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $__upload_p  = rtrim(str_replace('\\', '/', UPLOAD_PHYSICAL_PATH), '/');

    if (!empty($__doc_root) && strpos($__upload_p, $__doc_root) === 0) {
        // Upload folder berada di dalam web root — langsung bisa diakses
        $__rel = substr($__upload_p, strlen($__doc_root));
        define('UPLOAD_URL', $__protocol . '://' . $__host . $__rel);
    } else {
        // Upload folder di LUAR web root — perlu symlink atau konfigurasi manual
        // Fallback: asumsikan diakses via /uploads_stela di domain yang sama
        define('UPLOAD_URL', $__protocol . '://' . $__host . '/uploads_stela');
    }
}

// Load Composer Autoloader
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}
