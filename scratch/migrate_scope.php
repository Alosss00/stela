<?php
require_once __DIR__ . '/../bootstrap/app.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Add column if not exists
    $check = $conn->query("SHOW COLUMNS FROM appointments LIKE 'company_scope'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE appointments ADD COLUMN company_scope ENUM('MSM', 'TTN', 'MSM/TTN') NULL DEFAULT NULL AFTER registration_number");
        echo "Column company_scope added.\n";
    } else {
        $conn->query("ALTER TABLE appointments MODIFY COLUMN company_scope ENUM('MSM', 'TTN', 'MSM/TTN') NULL DEFAULT NULL");
        echo "Column company_scope modified.\n";
    }
    
    // Update existing records based on registration_number
    $stmt = $conn->prepare("UPDATE appointments SET company_scope = ? WHERE registration_number LIKE ?");
    
    // TTN
    $ttnScope = 'TTN';
    $ttnPattern = '%/TTN/%';
    $stmt->bind_param("ss", $ttnScope, $ttnPattern);
    $stmt->execute();
    echo "Updated TTN records: " . $stmt->affected_rows . "\n";
    
    // MSM
    $msmScope = 'MSM';
    $msmPattern = '%/MSM/%';
    $stmt->bind_param("ss", $msmScope, $msmPattern);
    $stmt->execute();
    echo "Updated MSM records: " . $stmt->affected_rows . "\n";
    
    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
