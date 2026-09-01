<?php
require_once dirname(__DIR__) . '/bootstrap/app.php';
require_once dirname(__DIR__) . '/app/Helpers/ktt_helper.php';

// Check if user is logged in and is KTT (or an active delegatee)
$_current_user_id = $_SESSION['user_id'] ?? 0;
if (!$_current_user_id || (!hasPermission('ktt.access') && !isActiveDelegatee($_current_user_id, new Database()))) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID required']);
    exit;
}

// Use the shared Database wrapper (already bootstrapped above)
$db = new Database();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID required']);
    exit;
}

// Get appointment details using Database wrapper
$appointment = $db->query("
    SELECT 
        a.*,
        e.employee_code, e.full_name as employee_name,
        e.position as employee_position, e.position, e.phone, e.email,
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
    WHERE a.id = ?
", [$id])->fetch_assoc();

if (!$appointment) {
    http_response_code(404);
    echo json_encode(['error' => 'Appointment not found']);
    exit;
}

$emp_id = (int)$appointment['employee_id'];

// Get certifications
$certs = $db->query("
    SELECT ec.*, c.cert_name, c.cert_code
    FROM employee_certifications ec
    JOIN certifications c ON ec.certification_id = c.id
    WHERE ec.employee_id = ?
    ORDER BY ec.verification_status DESC, ec.expiry_date DESC
", [$emp_id]);

$certifications = [];
while ($cert = $certs->fetch_assoc()) {
    $certifications[] = $cert;
}

$appointment['certifications'] = $certifications;

header('Content-Type: application/json');
echo json_encode($appointment);
