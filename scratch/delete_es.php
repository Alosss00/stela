<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../app/Services/ElasticsearchService.php';

$es = ElasticsearchService::getInstance();
if ($es->isAvailable()) {
    // We don't have the appointment IDs for ES easily if they are deleted,
    // but we can query ES by appointment_number
    $client = $es->getClient();
    $index = ELASTICSEARCH_INDEX_PREFIX . 'appointments';
    
    $params = [
        'index' => $index,
        'body' => [
            'query' => [
                'terms' => [
                    'appointment_number.keyword' => ['01/TT/MSM/09/2026', '20/TT/MSM/08/2026']
                ]
            ]
        ]
    ];
    
    try {
        $response = $client->deleteByQuery($params);
        echo "ES Delete Response: \n";
        print_r($response);
    } catch (Exception $e) {
        echo "ES Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "ES not available.\n";
}
