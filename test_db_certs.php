<?php
require_once __DIR__ . '/config/app.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    $conn = new mysqli('127.0.0.1', 'root', '', 'mining_appointment');
}
if ($conn->connect_error) {
    $conn = new mysqli('127.0.0.1', 'root', '', 'u136581265_Toka_STELA');
}
$sql = "SELECT ec.*, 
               c.name as master_cert_name,
               e.full_name as employee_name, e.employee_code, e.contractor_company as company, e.department, e.position
        FROM employee_certifications ec
        LEFT JOIN certifications c ON ec.certification_id = c.id
        LEFT JOIN employees e ON ec.employee_id = e.id
        WHERE 1=1 ORDER BY ec.expiry_date ASC LIMIT ?, ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "Prepare failed: " . $conn->error . "\n";
} else {
    echo "Prepare succeeded!\n";
}
