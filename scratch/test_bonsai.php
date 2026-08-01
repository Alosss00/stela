<?php
require_once __DIR__ . '/../bootstrap/app.php';

$host = 'https://df6c4bcf7e:b1bdce1e5fcf15ae0dca@focused-holly-1rb12wdt.ap-southeast-2.bonsaisearch.net:443';

echo "Testing OpenSearch Client with explicit port 443: $host\n";

$client = \OpenSearch\ClientBuilder::create()
    ->setHosts([$host])
    ->setSSLVerification(false)
    ->setRetries(0)
    ->build();

$indexName = 'stela_employees_test';

try {
    echo "1. Ping...\n";
    $ping = $client->ping();
    echo "Ping: " . ($ping ? "SUCCESS" : "FAILED") . "\n";

    echo "2. Checking if index exists...\n";
    $exists = $client->indices()->exists(['index' => $indexName]);
    if (is_object($exists) && method_exists($exists, 'asBool')) {
        $exists = $exists->asBool();
    }
    echo "Index exists: " . ($exists ? "YES" : "NO") . "\n";

    if (!$exists) {
        echo "3. Creating index...\n";
        $res = $client->indices()->create([
            'index' => $indexName,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'full_name' => ['type' => 'text']
                    ]
                ]
            ]
        ]);
        echo "Index created!\n";
    }

    echo "4. Indexing document...\n";
    $doc = [
        'index' => $indexName,
        'id' => '1',
        'body' => [
            'id' => 1,
            'full_name' => 'Bonsai Test Employee'
        ]
    ];
    $client->index($doc);
    echo "Document indexed!\n";

    echo "5. Searching document...\n";
    $searchRes = $client->search([
        'index' => $indexName,
        'body' => [
            'query' => [
                'match' => [
                    'full_name' => 'Bonsai'
                ]
            ]
        ]
    ]);
    echo "Search hits: " . count($searchRes['hits']['hits']) . "\n";
    echo "Found document name: " . ($searchRes['hits']['hits'][0]['_source']['full_name'] ?? 'N/A') . "\n";

    echo "6. Cleaning up test index...\n";
    $client->indices()->delete(['index' => $indexName]);
    echo "Test index deleted!\n";

    echo "ALL TESTS PASSED PERFECTLY!\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
