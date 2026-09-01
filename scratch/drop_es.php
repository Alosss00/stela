<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../app/Services/ElasticsearchService.php';

$es = ElasticsearchService::getInstance();
if ($es->isAvailable()) {
    $client = $es->getClient();
    $indexAppointments = ELASTICSEARCH_INDEX_PREFIX . 'appointments';
    $indexEmployees = ELASTICSEARCH_INDEX_PREFIX . 'employees';
    
    try {
        $client->indices()->delete(['index' => $indexAppointments]);
        echo "Deleted appointments index\n";
    } catch (\Throwable $e) {
        echo "Error deleting appointments index: " . $e->getMessage() . "\n";
    }

    try {
        $client->indices()->delete(['index' => $indexEmployees]);
        echo "Deleted employees index\n";
    } catch (\Throwable $e) {
        echo "Error deleting employees index: " . $e->getMessage() . "\n";
    }

    $es->setupIndices();
    echo "Recreated indices!\n";
}
