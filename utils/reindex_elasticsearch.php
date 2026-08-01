<?php
/**
 * Utility Script to Setup & Reindex Elasticsearch Data from MySQL
 * Usage: php utils/reindex_elasticsearch.php (CLI) or open in browser (Admin)
 */

require_once __DIR__ . '/../init.php';
if (file_exists(__DIR__ . '/../app/Services/ElasticsearchService.php')) {
    require_once __DIR__ . '/../app/Services/ElasticsearchService.php';
}

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Akses ditolak. Hanya admin yang dapat menjalankan skrip re-index.');
    }
    header('Content-Type: application/json; charset=utf-8');
}

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'error',
    'message' => '',
    'details' => []
];

$es = ElasticsearchService::getInstance();

if (!$es->isAvailable()) {
    $response['message'] = 'Elasticsearch Service tidak tersedia atau offline (Host: ' . ELASTICSEARCH_HOST . '). Fallback ke MySQL tetap aktif.';
    if ($isCli) {
        echo "[ERROR] " . $response['message'] . PHP_EOL;
        exit(1);
    } else {
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
}

// Setup Indices and Mappings
if ($isCli) echo "[INFO] Setting up Elasticsearch indices..." . PHP_EOL;
$setupResult = $es->setupIndices();
$response['details']['setup'] = $setupResult;

// Bulk Index Employees
if ($isCli) echo "[INFO] Re-indexing employees..." . PHP_EOL;
$db = new Database();
$empResult = $es->bulkIndexEmployees($db);
$response['details']['employees'] = $empResult;

if ($isCli) {
    echo "[SUCCESS] Employees re-indexed: " . ($empResult['count'] ?? 0) . PHP_EOL;
}

// Bulk Index Appointments
if ($isCli) echo "[INFO] Re-indexing appointments..." . PHP_EOL;
$appResult = $es->bulkIndexAppointments($db);
$response['details']['appointments'] = $appResult;

if ($isCli) {
    echo "[SUCCESS] Appointments re-indexed: " . ($appResult['count'] ?? 0) . PHP_EOL;
}

$response['status'] = 'success';
$response['message'] = 'Re-index Elasticsearch berhasil diselesaikan.';

if ($isCli) {
    echo "[DONE] " . $response['message'] . PHP_EOL;
} else {
    echo json_encode($response, JSON_PRETTY_PRINT);
}
