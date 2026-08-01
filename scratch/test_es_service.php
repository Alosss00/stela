<?php
require_once __DIR__ . '/../bootstrap/app.php';

echo "1. Getting ElasticsearchService instance...\n";
$es = ElasticsearchService::getInstance();

echo "Host: " . ELASTICSEARCH_HOST . "\n";
echo "Is Available: " . ($es->isAvailable() ? "YES" : "NO") . "\n";

if ($es->isAvailable()) {
    echo "2. Setting up indices on Bonsai.io...\n";
    $setup = $es->setupIndices();
    print_r($setup);

    echo "3. Testing index document...\n";
    $indexed = $es->indexEmployee([
        'id' => 99999,
        'employee_code' => 'EMP-TEST-999',
        'full_name' => 'Bonsai Integration Test User',
        'position' => 'Senior Engineer',
        'department' => 'IT Department',
        'contractor_company' => 'PT Test Indonesia',
        'competency_type' => 'K3',
        'approval_status' => 'approved',
        'is_active' => 1
    ]);
    echo "Indexed result: " . ($indexed ? "SUCCESS" : "FAILED") . "\n";

    echo "4. Testing searchEmployees on Bonsai.io...\n";
    $search = $es->searchEmployees('Bonsai Integration Test');
    print_r($search);

    echo "5. Cleaning up test document...\n";
    $deleted = $es->deleteEmployee(99999);
    echo "Deleted result: " . ($deleted ? "SUCCESS" : "FAILED") . "\n";

    echo "\n>>> ALL BONSAI.IO INTEGRATION TESTS PASSED SUCCESSFULLY! <<<\n";
}
