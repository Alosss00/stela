<?php
require_once __DIR__ . '/../bootstrap/app.php';

$db = new Database();

$files = [
    __DIR__ . '/../database/migrations/add_ktt_type_to_users.sql',
    __DIR__ . '/../database/migrations/create_ktt_delegations.sql'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "Running: " . basename($file) . "\n";
        $sql = file_get_contents($file);
        
        // mysqli multi_query
        if ($db->getConnection()->multi_query($sql)) {
            do {
                if ($result = $db->getConnection()->store_result()) {
                    $result->free();
                }
            } while ($db->getConnection()->next_result());
            echo "Success: " . basename($file) . "\n";
        } else {
            echo "Error running " . basename($file) . ": " . $db->getConnection()->error . "\n";
        }
    } else {
        echo "File not found: $file\n";
    }
}
echo "Done.\n";
