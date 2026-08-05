<?php
require_once __DIR__ . '/app/Models/Database.php';

$db = new Database();
$conn = $db->getConnection();

echo "Starting database migration for security features...\n";

// 1. Add columns to users table
$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'failed_login_attempts'");
if ($checkColumns->num_rows === 0) {
    echo "Adding failed_login_attempts and locked_until to users table...\n";
    $conn->query("ALTER TABLE users 
                  ADD COLUMN failed_login_attempts INT DEFAULT 0,
                  ADD COLUMN locked_until DATETIME NULL");
    echo "Columns added successfully.\n";
} else {
    echo "Columns already exist in users table.\n";
}

// 2. Create user_tokens table
echo "Creating user_tokens table...\n";
$createTableQuery = "
CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    selector VARCHAR(255) NOT NULL,
    hashed_validator VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

if ($conn->query($createTableQuery) === TRUE) {
    echo "Table user_tokens created successfully.\n";
} else {
    echo "Error creating user_tokens table: " . $conn->error . "\n";
}

echo "Migration completed.\n";
?>
