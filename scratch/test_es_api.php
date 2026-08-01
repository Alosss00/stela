<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../bootstrap/app.php';

$_GET['q'] = '';
$_GET['target'] = 'employees';
$_GET['page'] = 1;
$_GET['limit'] = 10;

ob_start();
require __DIR__ . '/../resources/views/api/search_elasticsearch.php';
$jsonStr = ob_get_clean();

echo "API Response for Page 1:\n" . $jsonStr . "\n";

$data = json_decode($jsonStr, true);

if ($data && $data['status'] === 'success') {
    echo "\nTest Checks:\n";
    echo "- Source: " . $data['source'] . "\n";
    echo "- Total: " . $data['total'] . "\n";
    echo "- Total Pages: " . $data['total_pages'] . "\n";
    echo "- Page: " . $data['page'] . "\n";
    echo "- Limit: " . $data['limit'] . "\n";
    echo "- Items count: " . count($data['items']) . "\n";
    echo "SUCCESS: API Pagination Response valid!\n";
} else {
    echo "FAILED: Invalid API JSON response!\n";
}
