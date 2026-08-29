<?php
require_once dirname(__DIR__) . '/bootstrap/app.php';
// Included via bootstrap/app.php

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Check if employee_id is provided
if (!isset($_GET['employee_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'employee_id parameter is required']);
    exit();
}

$employee_id = intval($_GET['employee_id']);
$db = new Database();

// Get the earliest expiry date from verified certificates
$result = $db->query("
    SELECT MIN(expiry_date) as earliest_expiry 
    FROM employee_certifications 
    WHERE employee_id = $employee_id 
    AND verification_status = 'verified'
    AND expiry_date IS NOT NULL
");

if ($result) {
    $data = $result->fetch_assoc();
    echo json_encode($data);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed']);
}
?>

