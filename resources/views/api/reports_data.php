<?php
/**
 * API Endpoint for Reports Data, Search, Pagination, & Exports
 */
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/app/Services/ReportService.php';

// Authenticate session
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user = $_SESSION;
$userRole = strtolower(trim((string)($user['role'] ?? 'user')));
$userCompany = trim((string)($user['contractor_company'] ?? ''));
$userDepartment = trim((string)($user['department'] ?? ''));

$reportService = ReportService::getInstance();

$action = $_REQUEST['action'] ?? 'get_report_data';

if ($action === 'get_counts') {
    header('Content-Type: application/json');
    $counts = $reportService->getSummaryCounts($userRole, $userCompany, $userDepartment);
    echo json_encode(['success' => true, 'counts' => $counts]);
    exit;
}

if ($action === 'get_report_data') {
    header('Content-Type: application/json');
    $reportType = $_REQUEST['report_type'] ?? 'accepted_requests';
    $search = $_REQUEST['search'] ?? '';
    $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
    $perPage = isset($_REQUEST['per_page']) ? (int)$_REQUEST['per_page'] : 10;
    $sortCol = $_REQUEST['sort_col'] ?? 'id';
    $sortDir = $_REQUEST['sort_dir'] ?? 'desc';

    $filters = [
        'company'          => $_REQUEST['company'] ?? '',
        'department'       => $_REQUEST['department'] ?? '',
        'scope'            => $_REQUEST['scope'] ?? '',
        'supervision_area' => $_REQUEST['supervision_area'] ?? ''
    ];

    $result = $reportService->getReportData($reportType, $userRole, $userCompany, $userDepartment, $search, $filters, $sortCol, $sortDir, $page, $perPage);
    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

if ($action === 'export_excel' || $action === 'export_pdf') {
    $reportType = $_REQUEST['report_type'] ?? 'accepted_requests';
    $search = $_REQUEST['search'] ?? '';
    $sortCol = $_REQUEST['sort_col'] ?? 'id';
    $sortDir = $_REQUEST['sort_dir'] ?? 'desc';

    $filters = [
        'company'          => $_REQUEST['company'] ?? '',
        'department'       => $_REQUEST['department'] ?? '',
        'scope'            => $_REQUEST['scope'] ?? '',
        'supervision_area' => $_REQUEST['supervision_area'] ?? ''
    ];

    // Fetch all records for export (perPage = 5000)
    $result = $reportService->getReportData($reportType, $userRole, $userCompany, $userDepartment, $search, $filters, $sortCol, $sortDir, 1, 5000);
    $items = $result['items'] ?? [];

    if ($action === 'export_excel') {
        $filename = "STELA_Report_" . $reportType . "_" . date('Ymd_His') . ".xls";
        header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="UTF-8"><style>th{background-color:#1e293b;color:#fff;padding:8px;} td{padding:6px;border:1px solid #cbd5e1;}</style></head><body>';
        echo '<h2>STELA System Report: ' . str_replace('_', ' ', strtoupper($reportType)) . '</h2>';
        echo '<p>Export Date: ' . date('d/m/Y H:i:s') . ' | Total Records: ' . count($items) . '</p>';
        echo '<table border="1">';
        renderExportTableHeaders($reportType);
        foreach ($items as $item) {
            renderExportTableRow($reportType, $item);
        }
        echo '</table></body></html>';
        exit;
    }

    if ($action === 'export_pdf') {
        header("Content-Type: text/html; charset=UTF-8");
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>STELA PDF Report</title>';
        echo '<style>body{font-family:Arial,sans-serif;font-size:12px;margin:20px;} h2{color:#0f172a;margin-bottom:5px;} table{width:100%;border-collapse:collapse;margin-top:15px;} th{background:#334155;color:#fff;padding:8px;text-align:left;} td{padding:7px;border-bottom:1px solid #e2e8f0;}</style>';
        echo '</head><body onload="window.print()">';
        echo '<h2>STELA System Report - ' . str_replace('_', ' ', strtoupper($reportType)) . '</h2>';
        echo '<p>Printed on: ' . date('d/m/Y H:i:s') . ' | Total Records: ' . count($items) . '</p>';
        echo '<table>';
        renderExportTableHeaders($reportType);
        foreach ($items as $item) {
            renderExportTableRow($reportType, $item);
        }
        echo '</table></body></html>';
        exit;
    }
}

function renderExportTableHeaders($type) {
    echo '<thead><tr>';
    switch ($type) {
        case 'accepted_requests':
        case 'rejected_requests':
        case 'waiting_requests':
            echo '<th>Employee Name</th><th>ID Badge</th><th>Company</th><th>Department</th><th>Position</th><th>Competency Type</th><th>Request Date</th><th>Status</th>';
            break;
        case 'accepted_assign_letters':
        case 'rejected_assign_letters':
            echo '<th>Assign Letter No.</th><th>Employee Name</th><th>Company</th><th>Department</th><th>Position</th><th>Competency Type</th><th>Effective/Request Date</th><th>Status</th>';
            break;
        case 'expired_certificates':
            echo '<th>Employee Name</th><th>ID Badge</th><th>Company</th><th>Department</th><th>Cert Name</th><th>Cert Number</th><th>Expiry Date</th><th>Days Left</th>';
            break;
    }
    echo '</tr></thead>';
}

function renderExportTableRow($type, $item) {
    echo '<tr>';
    switch ($type) {
        case 'accepted_requests':
        case 'rejected_requests':
        case 'waiting_requests':
            echo '<td>' . htmlspecialchars($item['full_name'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['employee_code'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['contractor_company'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['department'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['position'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['competency_type'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($item['request_date'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars(ucfirst($item['verification_status'] ?? '')) . '</td>';
            break;
        case 'accepted_assign_letters':
        case 'rejected_assign_letters':
            echo '<td>' . htmlspecialchars($item['appointment_number'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['employee_name'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['contractor_company'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['department'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['position_name'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['position_type'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($item['effective_date'] ?? $item['request_date'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars(ucfirst($item['status'] ?? '')) . '</td>';
            break;
        case 'expired_certificates':
            echo '<td>' . htmlspecialchars($item['employee_name'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['employee_code'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['contractor_company'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['department'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['cert_name'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['cert_number'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($item['expiry_date'] ?? '') . '</td>';
            echo '<td>' . (int)($item['days_left'] ?? 0) . ' days</td>';
            break;
    }
    echo '</tr>';
}
