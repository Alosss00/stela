<?php
/**
 * REST API Search Endpoint powered by Elasticsearch with MySQL Fallback
 * Returns search results for Employees and Appointments in JSON format.
 */

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
if (file_exists(dirname(__DIR__, 2) . '/app/Services/ElasticsearchService.php')) {
    require_once dirname(__DIR__, 2) . '/app/Services/ElasticsearchService.php';
}

// Authentication check
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthenticated'
    ]);
    exit();
}

$queryText = trim($_GET['q'] ?? $_GET['query'] ?? '');
$target = trim($_GET['target'] ?? 'employees'); // 'employees' or 'appointments'
$company = trim($_GET['company'] ?? '');
$competencyType = trim($_GET['competency_type'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$from = ($page - 1) * $limit;

$es = ElasticsearchService::getInstance();

// 1. Try Elasticsearch Search first
if ($es->isAvailable()) {
    $filters = [];
    if (!empty($company)) $filters['contractor_company'] = $company;
    if (!empty($competencyType)) $filters['competency_type'] = $competencyType;
    if (!empty($status)) {
        if ($target === 'employees') {
            $filters['approval_status'] = $status;
        } else {
            $filters['status'] = $status;
        }
    }

    if ($target === 'appointments') {
        $result = $es->searchAppointments($queryText, $filters, $from, $limit);
    } else {
        $result = $es->searchEmployees($queryText, $filters, $from, $limit);
    }

    if ($result !== false) {
        echo json_encode([
            'status' => 'success',
            'source' => 'elasticsearch',
            'query' => $queryText,
            'page' => $page,
            'limit' => $limit,
            'total' => $result['total'],
            'items' => $result['items']
        ]);
        exit();
    }
}

// 2. MySQL Fallback Search if Elasticsearch is unavailable or fails
$db = new Database();
$items = [];
$total = 0;

if ($target === 'employees') {
    $where = ["is_active = 1"];
    
    if (!empty($queryText)) {
        $safeQ = $db->escapeString($queryText);
        $where[] = "(employee_code LIKE '%$safeQ%' OR full_name LIKE '%$safeQ%' OR position LIKE '%$safeQ%' OR contractor_company LIKE '%$safeQ%' OR department LIKE '%$safeQ%')";
    }
    
    if (!empty($company)) {
        $safeCompany = $db->escapeString($company);
        $where[] = "contractor_company = '$safeCompany'";
    }

    if (!empty($competencyType)) {
        $safeType = $db->escapeString($competencyType);
        $where[] = "competency_type = '$safeType'";
    }

    if (!empty($status)) {
        $safeStatus = $db->escapeString($status);
        $where[] = "approval_status = '$safeStatus'";
    }

    $whereClause = implode(' AND ', $where);

    // Count
    $countRes = $db->query("SELECT COUNT(*) as cnt FROM employees WHERE $whereClause");
    if ($countRes) {
        $total = (int)($countRes->fetch_assoc()['cnt'] ?? 0);
    }

    // Query items
    $sql = "SELECT id, employee_code, full_name, position, department, contractor_company, competency_type, ruang_lingkup, sub_competency, supervision_area, approval_status, created_at 
            FROM employees WHERE $whereClause ORDER BY id DESC LIMIT $from, $limit";
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
    }
} else { // Appointments
    $where = ["1=1"];
    
    if (!empty($queryText)) {
        $safeQ = $db->escapeString($queryText);
        $where[] = "(a.appointment_number LIKE '%$safeQ%' OR e.full_name LIKE '%$safeQ%' OR e.contractor_company LIKE '%$safeQ%')";
    }

    if (!empty($status)) {
        $safeStatus = $db->escapeString($status);
        $where[] = "a.status = '$safeStatus'";
    }

    $whereClause = implode(' AND ', $where);

    $countRes = $db->query("SELECT COUNT(*) as cnt FROM appointments a LEFT JOIN employees e ON a.employee_id = e.id WHERE $whereClause");
    if ($countRes) {
        $total = (int)($countRes->fetch_assoc()['cnt'] ?? 0);
    }

    $sql = "SELECT a.id, a.appointment_number, a.employee_id, e.full_name as employee_name, e.contractor_company, a.competency_type, a.status, a.created_at 
            FROM appointments a 
            LEFT JOIN employees e ON a.employee_id = e.id 
            WHERE $whereClause ORDER BY a.id DESC LIMIT $from, $limit";
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
    }
}

echo json_encode([
    'status' => 'success',
    'source' => 'mysql_fallback',
    'query' => $queryText,
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'items' => $items
]);
