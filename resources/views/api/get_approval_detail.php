<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is KTT
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ktt') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID required']);
    exit;
}

// Database connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$id = (int)$_GET['id'];

// Get appointment details
$stmt = $db->prepare("
    SELECT 
        a.*,
        e.employee_code, e.full_name as employee_name, e.position as employee_position, e.position, e.phone, e.email,
        e.contractor_company, e.cv_file, e.address,
        p.position_name as appointment_position_name, p.position_name, p.position_code,
        COALESCE(u.full_name, u.username) as created_by_name,
        (SELECT COUNT(*) FROM employee_certifications ec 
         WHERE ec.employee_id = a.employee_id AND ec.verification_status = 'verified') as verified_certs,
        (SELECT COUNT(*) FROM employee_certifications ec 
         WHERE ec.employee_id = a.employee_id) as total_certs
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    JOIN users u ON a.created_by = u.id
    WHERE a.id = ? AND a.status = 'pending'
");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Appointment not found']);
    exit;
}

$appointment = $result->fetch_assoc();
$emp_id = $appointment['employee_id'];

// Get certifications
$certs = $db->query("
    SELECT ec.*, c.cert_name, c.cert_code
    FROM employee_certifications ec
    JOIN certifications c ON ec.certification_id = c.id
    WHERE ec.employee_id = $emp_id 
    ORDER BY ec.verification_status DESC, ec.expiry_date DESC
");

$certifications = [];
while ($cert = $certs->fetch_assoc()) {
    $certifications[] = $cert;
}

$appointment['certifications'] = $certifications;

header('Content-Type: application/json');
echo json_encode($appointment);

