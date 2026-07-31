<?php
$page_title = 'Reports - ' . ($_SESSION['department'] ?? 'Department');
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Only department_user role or user with department can access this page
if (!hasDepartment() && $_SESSION['role'] != 'department_user') {
    header('Location: ../admin/dashboard.php');
    exit();
}

$db = new Database();
$department = $_SESSION['department'] ?? '';

// Get report data: approved and rejected appointments grouped by department (filtered by user's department)
$report_data = $db->query("
    SELECT 
        e.department,
        SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        COUNT(*) as total_count
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status IN ('approved', 'rejected') AND e.department = '" . $db->escapeString($department) . "'
    GROUP BY e.department
    ORDER BY e.department
");

// Get detailed approved appointments for user's department (include KTT approvals)
$approved_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.department, e.ruang_lingkup, e.supervision_area,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           au.full_name as approved_by_name,
           a.approved_date,
           a.ktt1_approved_date,
           a.ktt2_approved_date,
           ktt1.full_name as ktt1_name,
           ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users au ON a.approved_by = au.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'approved' AND e.department = '" . $db->escapeString($department) . "'
    ORDER BY a.approved_date DESC
");

// Get detailed rejected appointments for user's department
$rejected_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.department, e.ruang_lingkup, e.supervision_area,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           au.full_name as approved_by_name,
           a.approved_date,
           GROUP_CONCAT(
               CONCAT(ktt_u.full_name, ' (', ka.action, '): ', ka.approval_notes)
               SEPARATOR ' | '
           ) as ktt_notes
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users au ON a.approved_by = au.id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id
    LEFT JOIN users ktt_u ON ka.ktt_user_id = ktt_u.id
    WHERE a.status = 'rejected' AND e.department = '" . $db->escapeString($department) . "'
    GROUP BY a.id
    ORDER BY a.approved_date DESC
");

// Get statistics for user's department
$approved_total = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.status = 'approved' AND e.department = '" . $db->escapeString($department) . "'")->fetch_assoc()['count'];
$rejected_total = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.status = 'rejected' AND e.department = '" . $db->escapeString($department) . "'")->fetch_assoc()['count'];
$total_processed = $approved_total + $rejected_total;

// Get request data for user's department
$accepted_requests = $db->query("\n    SELECT e.*, e.created_at as request_date, e.updated_at as verification_date, u.full_name as verified_by_name\n    FROM employees e\n    LEFT JOIN users u ON e.verified_by = u.id\n    WHERE e.verification_status = 'verified' AND e.department = '" . $db->escapeString($department) . "'\n    ORDER BY e.updated_at DESC\n");

$rejected_requests = $db->query("\n    SELECT e.*, e.created_at as request_date, e.updated_at as verification_date, u.full_name as verified_by_name\n    FROM employees e\n    LEFT JOIN users u ON e.verified_by = u.id\n    WHERE e.verification_status = 'rejected' AND e.department = '" . $db->escapeString($department) . "'\n    ORDER BY e.updated_at DESC\n");

$pending_requests = $db->query("\n    SELECT e.*, e.created_at as request_date\n    FROM employees e\n    WHERE e.verification_status = 'pending' AND e.department = '" . $db->escapeString($department) . "'\n    ORDER BY e.created_at DESC\n");

$accepted_requests_count = $accepted_requests ? $accepted_requests->num_rows : 0;
$rejected_requests_count = $rejected_requests ? $rejected_requests->num_rows : 0;
$pending_requests_count = $pending_requests ? $pending_requests->num_rows : 0;
$total_requests_processed = $accepted_requests_count + $rejected_requests_count + $pending_requests_count;

// Get expiring certificates for department (expiring within 60 days)
$expiring_certs = $db->query("
    SELECT e.*, cert.cert_name, ec.cert_number, ec.cert_type, ec.expiry_date,
           CEIL((DATEDIFF(ec.expiry_date, NOW())) / 1) as days_until_expiry
    FROM employee_certifications ec
    JOIN certifications cert ON ec.certification_id = cert.id
    JOIN employees e ON ec.employee_id = e.id
    WHERE DATEDIFF(ec.expiry_date, NOW()) <= 60 
    AND e.department = '" . $db->escapeString($department) . "'
    ORDER BY ec.expiry_date ASC
");

$expiring_certs_count = $expiring_certs ? $expiring_certs->num_rows : 0;

require_once '../../includes/header.php';

// Get all supervision areas for filter
$supervision_areas = $db->query("SELECT * FROM supervision_areas WHERE is_active = 1 ORDER BY area_name");

// Get unique work scopes for filter (from user's department data)
$work_scopes = $db->query("\n    SELECT DISTINCT e.ruang_lingkup\n    FROM appointments a\n    JOIN employees e ON a.employee_id = e.id\n    WHERE a.status IN ('approved', 'rejected') \n    AND e.department = '" . $db->escapeString($department) . "'\n    AND e.ruang_lingkup IS NOT NULL AND e.ruang_lingkup != ''\n    ORDER BY e.ruang_lingkup\n");
?>
<style>
    .reports-container {
        padding: 20px 0 32px;
        max-width: 1440px;
        margin: 0 auto;
    }

    /* Page Header */
    .page-header-reports {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        color: #1f2937;
        padding: 28px 30px;
        border-radius: 16px;
        margin-bottom: 28px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #e5e7eb;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }

    .header-left h2 {
        margin: 0 0 8px 0;
        font-size: 24px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #111827;
    }

    .header-left p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
    }

    .header-date {
        background: #f8fafc;
        padding: 11px 16px;
        border-radius: 10px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #475569;
        border: 1px solid #e5e7eb;
    }

    /* Stats Grid */
    .stats-grid-reports {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }

    .stat-card-report {
        background: white;
        border-radius: 14px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }

    .stat-card-report:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }

    .stat-total { border-left: 4px solid #f59e0b; }
    .stat-approved { border-left: 4px solid #10b981; }
    .stat-rejected { border-left: 4px solid #ef4444; }
    .stat-pending { border-left: 4px solid #d97706; }

    .stat-icon-report {
        font-size: 28px;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        color: white;
    }

    .stat-total .stat-icon-report { background: linear-gradient(135deg, #f59e0b, #f97316); }
    .stat-approved .stat-icon-report { background: linear-gradient(135deg, #10b981, #059669); }
    .stat-rejected .stat-icon-report { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .stat-pending .stat-icon-report { background: linear-gradient(135deg, #d97706, #f59e0b); }

    .request-stats-grid {
        margin-top: -2px;
    }

    .stat-content-report h3 {
        font-size: 26px;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }

    .stat-content-report p {
        color: #6b7280;
        font-size: 13px;
        margin: 5px 0 0 0;
    }

    /* Cards */
    .card-report {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        margin-bottom: 20px;
        border: 1px solid #e5e7eb;
    }

    .card-header-report {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #eef2f7;
    }

    .card-header-report h3 {
        margin: 0;
        font-size: 17px;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-header-report i {
        color: #f59e0b;
    }

    .badge-header {
        background: #111827;
        color: white;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-header.rejected {
        background: #ef4444;
    }

    .badge-header.warning {
        background: #f59e0b;
    }

    .card-body-report {
        padding: 0;
    }

    /* Table */
    .table-report {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    .table-report thead th {
        background: #f9fafb;
        color: #6b7280;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
        padding: 14px 16px;
        text-align: left;
    }

    .report-row,
    .detail-row {
        border-bottom: 1px solid #f3f4f6;
        transition: background-color 0.2s ease;
    }

    .report-row:hover,
    .detail-row:hover {
        background-color: #fafafa;
    }

    .table-report td {
        padding: 14px 16px;
        vertical-align: middle;
        font-size: 13px;
    }

    .table-compact-request .col-number,
    .table-compact-request .col-position,
    .table-compact-request .col-rejected-by {
        display: none;
    }

    .table-compact-request .col-employee {
        width: 32%;
    }

    .table-compact-request .col-date {
        width: 20%;
    }

    .table-compact-request .col-approved-info,
    .table-compact-request .col-notes,
    .table-compact-request .col-rejected-date,
    .table-compact-request .col-code,
    .table-compact-request .col-status,
    .table-compact-request .col-verified-date,
    .table-compact-request .col-verified-by {
        width: auto;
    }

    .table-compact-request td {
        padding: 11px 12px;
    }

    .badge-count {
        display: inline-block;
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
    }

    .approved-badge {
        background: #E8F5E9;
        color: #2E7D32;
    }

    .rejected-badge {
        background: #fee2e2;
        color: #ef4444;
    }

    .company-tag {
        background: #f9fafb;
        color: #6b7280;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        display: inline-block;
    }

    .employee-detail {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .emp-code-detail {
        font-size: 11px;
        color: #999;
    }

    .position-badge-report {
        background: #f9fafb;
        color: #374151;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        display: inline-block;
    }

    .approver-badge {
        background: #ecfdf5;
        color: #047857;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        display: inline-block;
    }

    .rejector-badge {
        background: #fef2f2;
        color: #dc2626;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        display: inline-block;
    }

    .rejected-row {
        border-left: 3px solid #ef4444;
    }

    /* Empty State */
    .empty-state-report {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .empty-state-report i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    .empty-state-report p {
        margin: 0;
        font-size: 16px;
    }

    /* Filter Section */
    .filter-section-report {
        padding: 20px;
        background: #fafafa;
        border-bottom: 1px solid #eef2f7;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
    }

    .filter-group-report {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .filter-action-group {
        margin-left: auto;
    }

    .filter-group-report label {
        font-weight: 600;
        color: #374151;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0;
        font-size: 13px;
    }

    .filter-select-report {
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        font-size: 13px;
        cursor: pointer;
        transition: border-color 0.3s ease;
        min-width: 200px;
    }

    .filter-select-report:hover,
    .filter-select-report:focus {
        border-color: #f59e0b;
        outline: none;
    }

    .table-info-report {
        padding: 12px 20px;
        background: #f9fafb;
        color: #6b7280;
        border-top: 1px solid #eef2f7;
        font-size: 12px;
        font-weight: 500;
    }

    /* Rejection Modal */
    .modal-rejection {
        max-width: 650px;
    }

    .modal-header-rejection {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        padding: 20px;
        border-radius: 14px 14px 0 0;
    }

    .modal-header-rejection h3 {
        margin: 0;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-header-rejection .close {
        color: white;
        opacity: 0.8;
    }

    .modal-header-rejection .close:hover {
        opacity: 1;
    }

    .modal-body-rejection {
        padding: 25px;
    }

    .rejection-info {
        margin-bottom: 20px;
    }

    .info-row {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .info-row label {
        font-weight: 600;
        color: #333;
        width: 150px;
        flex-shrink: 0;
    }

    .info-row span {
        color: #666;
        flex: 1;
    }

    .rejection-notes-section {
        margin-top: 25px;
    }

    .rejection-notes-section h4 {
        margin: 0 0 15px 0;
        font-size: 15px;
        color: #333;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rejection-notes-section i {
        color: #ef4444;
    }

    .rejection-notes-content {
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-height: 400px;
        overflow-y: auto;
    }

    .rejection-note-item {
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        padding: 12px;
        border-radius: 6px;
    }

    .rejection-note-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .note-number {
        background: #ef4444;
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }

    .rejection-note-header i {
        color: #ef4444;
        font-size: 14px;
    }

    .rejection-note-text {
        color: #7f1d1d;
        font-size: 13px;
        line-height: 1.6;
        word-break: break-word;
    }

    .modal-footer-rejection {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding: 15px 25px;
        border-top: 1px solid #e9ecef;
    }

    /* Notes Badge */
    .notes-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: #fef3c7;
        color: #b45309;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .notes-badge:hover {
        background: #fcd34d;
        transform: translateY(-1px);
    }

    /* Button Detail Small */
    .btn-detail-small {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        background: #ECEFF1;
        color: #37474F;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .btn-detail-small:hover {
        background: #37474F;
        color: white;
        transform: translateY(-1px);
    }

    /* Export Button Styles */
    .btn-export-small {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: linear-gradient(135deg, #2E7D32, #1B5E20);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }

    .btn-export-small:hover {
        background: linear-gradient(135deg, #1B5E20, #047857);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    .btn-export-small i {
        font-size: 14px;
    }

    .btn-export-pdf {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .btn-export-pdf:hover {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }

    /* Certificate Expiration Styles */
    .alert-warning-report {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-left: 4px solid #f59e0b;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: #92400e;
    }

    .alert-warning-report i {
        font-size: 20px;
        color: #f59e0b;
    }

    .alert-warning-report span {
        font-size: 14px;
        line-height: 1.5;
    }

    .col-cert-name { width: 20%; }
    .col-cert-number { width: 15%; }
    .col-expiry-date { width: 13%; }
    .col-days-left { width: 12%; }
    .col-status-expiry { width: 15%; }

    .cert-name-badge {
        background: #f9fafb;
        color: #374151;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .days-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    .days-critical {
        background: #fee2e2;
        color: #dc2626;
    }

    .days-urgent {
        background: #fed7aa;
        color: #ea580c;
    }

    .days-warning {
        background: #fef3c7;
        color: #d97706;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .status-critical {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-urgent {
        background: #fed7aa;
        color: #ea580c;
    }

    .status-warning {
        background: #fef3c7;
        color: #d97706;
    }

    /* Request Table Styles */
    .table-requests {
        width: 100%;
    }

    .col-employee {
        width: 25%;
    }

    .col-code {
        width: 12%;
    }

    .col-request-date {
        width: 13%;
    }

    .col-status {
        width: 15%;
    }

    .col-verified-date {
        width: 13%;
    }

    .col-verified-by {
        width: 22%;
    }

    .status-accepted {
        background: #ecfdf5;
        color: #047857;
    }

    .status-rejected-badge {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-pending-badge {
        background: #fef3c7;
        color: #d97706;
    }

    .table-requests .col-code,
    .table-requests .col-status,
    .table-requests .col-verified-date,
    .table-requests .col-verified-by {
        white-space: nowrap;
    }

    /* Action Footer */
    .action-footer {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #eef2f7;
    }

    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 600;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
        color: #111827;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .page-header-reports {
            flex-direction: column;
            gap: 20px;
            text-align: center;
        }

        .col-rejected-by { display: none; }
    }

    @media (max-width: 768px) {
        .page-header-reports {
            padding: 25px 15px;
        }

        .header-left h2 {
            font-size: 22px;
        }

        .stats-grid-reports {
            grid-template-columns: repeat(2, 1fr);
        }

        .col-approved-by { display: none; }
        .col-notes { display: none; }

        .modal-rejection {
            max-width: 90%;
        }

        .rejection-notes-content {
            max-height: 300px;
        }

        .info-row {
            flex-direction: column;
        }

        .info-row label {
            width: 100%;
            margin-bottom: 5px;
        }

        .card-header-report {
            flex-direction: column;
            gap: 15px;
        }

        .filter-section-report {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-action-group {
            margin-left: 0;
            width: 100%;
        }

        .btn-export-small,
        .btn-export-pdf,
        .btn-secondary {
            width: 100%;
            justify-content: center;
        }
    }
    </style>

<div class="reports-container">
    <div class="page-header-reports">
        <div class="header-left">
            <h2><i class="fas fa-chart-bar"></i> <span data-lang="department-report">Department Report</span></h2>
            <p><span data-lang="report-summary">Summary and details of requests, assign letters, and certificates</span> - <?php echo htmlspecialchars($department); ?></p>
        </div>
        <div class="header-date">
            <i class="fas fa-calendar"></i> <?php echo date('d F Y'); ?>
        </div>
    </div>

    <div class="stats-grid-reports">
        <div class="stat-card-report stat-total">
            <div class="stat-icon-report"><i class="fas fa-file"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $total_processed; ?></h3>
                <p data-lang="total-processed">Total Processed</p>
            </div>
        </div>

        <div class="stat-card-report stat-approved">
            <div class="stat-icon-report"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $approved_total; ?></h3>
                <p data-lang="accepted">Accepted</p>
            </div>
        </div>

        <div class="stat-card-report stat-rejected">
            <div class="stat-icon-report"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $rejected_total; ?></h3>
                <p data-lang="rejected">Rejected</p>
            </div>
        </div>
    </div>

    <div class="stats-grid-reports request-stats-grid">
        <div class="stat-card-report stat-total">
            <div class="stat-icon-report"><i class="fas fa-tasks"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $total_requests_processed; ?></h3>
                <p data-lang="total-requests">Total Requests</p>
            </div>
        </div>

        <div class="stat-card-report stat-approved">
            <div class="stat-icon-report"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $accepted_requests_count; ?></h3>
                <p data-lang="accepted-requests">Accepted Requests</p>
            </div>
        </div>

        <div class="stat-card-report stat-rejected">
            <div class="stat-icon-report"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $rejected_requests_count; ?></h3>
                <p data-lang="rejected-requests">Rejected Requests</p>
            </div>
        </div>

        <div class="stat-card-report stat-pending">
            <div class="stat-icon-report"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $pending_requests_count; ?></h3>
                <p data-lang="pending-requests">Pending Requests</p>
            </div>
        </div>
    </div>

    <?php if (($accepted_requests_count + $rejected_requests_count + $pending_requests_count) > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-list"></i> <span data-lang="all-requests-section">All Requests</span></h3>
                <span class="badge-header"><?php echo $accepted_requests_count + $rejected_requests_count + $pending_requests_count; ?></span>
            </div>
        </div>

        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-filter"></i> <span data-lang="status-label">Status:</span></label>
                <select id="statusFilterRequests" class="filter-select-report" onchange="filterRequestTable('requestsTable')">
                    <option value="" data-lang="all-statuses">-- All Statuses --</option>
                    <option value="verified" data-lang="accepted">Accepted</option>
                    <option value="rejected" data-lang="rejected">Rejected</option>
                    <option value="pending" data-lang="pending">Pending</option>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportRequestsToExcel('requestsTable', 'Department_Requests_Report')">
                    <i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span>
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-compact-request table-requests" id="requestsTable">
                    <thead>
                        <tr>
                            <th class="col-employee" data-lang="employee">Employee</th>
                            <th class="col-code" data-lang="employee-code">Code</th>
                </div>
                <div class="table-info-report" id="approvedTableInfo"><span data-lang="showing-all-data">Showing all data</span></div>
            </div>
            <div class="table-info-report" id="approvedTableInfo"><span data-lang="showing-all-data">Showing all data</span></div>
        </div>
                            <th class="col-request-date" data-lang="request-date">Request Date</th>
                            <th class="col-status" data-lang="status">Status</th>
                            <th class="col-verified-date" data-lang="verification-date">Verification Date</th>
                            <th class="col-verified-by" data-lang="verified-by">Verified By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($accepted_requests_count > 0): ?>
                            <?php $accepted_requests->data_seek(0); while ($row = $accepted_requests->fetch_assoc()): ?>
                            <tr class="detail-row" data-status="verified">
                                <td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($row['full_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($row['department']); ?></span></div></td>
                                <td class="col-code"><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                <td class="col-request-date"><?php echo !empty($row['request_date']) ? date('d/m/Y H:i', strtotime($row['request_date'])) : 'N/A'; ?></td>
                                <td class="col-status"><span class="status-badge status-accepted"><i class="fas fa-check-circle"></i> <span data-lang="accepted">Accepted</span></span></td>
                                <td class="col-verified-date"><?php echo !empty($row['verification_date']) ? date('d/m/Y H:i', strtotime($row['verification_date'])) : 'N/A'; ?></td>
                                <td class="col-verified-by"><?php echo htmlspecialchars($row['verified_by_name'] ?: 'N/A'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($rejected_requests_count > 0): ?>
                            <?php $rejected_requests->data_seek(0); while ($row = $rejected_requests->fetch_assoc()): ?>
                            <tr class="detail-row rejected-row" data-status="rejected">
                                <td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($row['full_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($row['department']); ?></span></div></td>
                                <td class="col-code"><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                <td class="col-request-date"><?php echo !empty($row['request_date']) ? date('d/m/Y H:i', strtotime($row['request_date'])) : 'N/A'; ?></td>
                                <td class="col-status"><span class="status-badge status-rejected-badge"><i class="fas fa-times-circle"></i> <span data-lang="rejected">Rejected</span></span></td>
                                <td class="col-verified-date"><?php echo !empty($row['verification_date']) ? date('d/m/Y H:i', strtotime($row['verification_date'])) : 'N/A'; ?></td>
                                <td class="col-verified-by"><span class="rejector-badge"><?php echo htmlspecialchars($row['verified_by_name'] ?: 'N/A'); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($pending_requests_count > 0): ?>
                            <?php $pending_requests->data_seek(0); while ($row = $pending_requests->fetch_assoc()): ?>
                            <tr class="detail-row" data-status="pending">
                                <td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($row['full_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($row['department']); ?></span></div></td>
                                <td class="col-code"><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                <td class="col-request-date"><?php echo !empty($row['request_date']) ? date('d/m/Y H:i', strtotime($row['request_date'])) : 'N/A'; ?></td>
                                <td class="col-status"><span class="status-badge status-pending-badge"><i class="fas fa-hourglass-half"></i> <span data-lang="pending">Pending</span></span></td>
                                <td class="col-verified-date">-</td>
                                <td class="col-verified-by">-</td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="requestsTableInfo"><span data-lang="showing-all-data">Showing all data</span></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($approved_appointments && $approved_appointments->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;"><h3 style="margin: 0;"><i class="fas fa-check-circle"></i> <span data-lang="detail-assign-letter-accepted">Detail Assign Letter Accepted</span></h3><span class="badge-header"><?php echo $approved_appointments->num_rows; ?></span></div>
            <button class="btn btn-export-pdf" onclick="exportApprovedByDepartment()"><i class="fas fa-file-pdf"></i> <span data-lang="export-pdf-report">Export PDF Report</span></button>
        </div>
        <div class="filter-section-report">
            <div class="filter-group-report"><label><i class="fas fa-map-marker-alt"></i> <span data-lang="work-scope-label">Scope:</span></label><select id="scopeFilterApproved" class="filter-select-report" onchange="filterTableByFilters('approvedTable')"><option value="" data-lang="all-scopes">-- All Scopes --</option><option value="MSM">PT MSM</option><option value="TTN">PT TTN</option></select></div>
            <div class="filter-group-report"><label><i class="fas fa-eye"></i> <span data-lang="supervision-area-label">Supervision Area:</span></label><select id="supervisionFilterApproved" class="filter-select-report" onchange="filterTableByFilters('approvedTable')"><option value="" data-lang="all-areas">-- All Areas --</option><?php if ($supervision_areas && $supervision_areas->num_rows > 0) { $supervision_areas->data_seek(0); while ($area = $supervision_areas->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($area['area_name']); ?>"><?php echo htmlspecialchars($area['area_name']); ?></option><?php endwhile; } ?></select></div>
            <div class="filter-action-group"><button class="btn btn-export-small" onclick="exportToExcel('approvedTable', 'Report_Approved_Letters')"><i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span></button></div>
        </div>
        <div class="card-body-report"><div class="table-responsive"><table class="table-report table-approved" id="approvedTable"><thead><tr><th class="col-number" data-lang="assign-letter-no">Assign Letter No.</th><th class="col-employee" data-lang="employee">Employee</th><th class="col-position" data-lang="position">Position</th><th class="col-date" data-lang="effective-date">Effective Date</th><th class="col-approved-date" data-lang="approved">Approved</th><th class="col-approved-by" data-lang="approved-by">Approved By</th></tr></thead><tbody><?php $approved_appointments->data_seek(0); while ($row = $approved_appointments->fetch_assoc()): $scope_raw = $row['ruang_lingkup'] ?: ''; $scope_normalized = ''; if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) { $scope_normalized = 'MSM'; } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) { $scope_normalized = 'TTN'; } $supervision_area = htmlspecialchars($row['supervision_area'] ?: ''); ?><tr class="detail-row" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>"><td class="col-number"><strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong></td><td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($row['employee_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($row['employee_code']); ?></span></div></td><td class="col-position"><span class="position-badge-report"><?php echo htmlspecialchars($row['position_name']); ?></span></td><td class="col-date"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($row['effective_date'])); ?></td><td class="col-approved-date"><i class="fas fa-check"></i> <?php echo date('d/m/Y H:i', strtotime($row['approved_date'])); ?></td><td class="col-approved-by">                                    <div class="approval-info-container">                                        <?php if (!empty($row['ktt1_name']) || !empty($row['ktt2_name'])): ?>                                            <?php if (!empty($row['ktt1_name'])): ?>                                                <div class="approval-item">                                                    <span class="approver-name"><strong>KTT MSM:</strong> <?php echo htmlspecialchars($row['ktt1_name']); ?></span>                                                    <?php if (!empty($row['ktt1_approved_date'])): ?>                                                        <span class="approval-datetime"><?php echo date('d/m/Y', strtotime($row['ktt1_approved_date'])); ?> - <?php echo date('H:i', strtotime($row['ktt1_approved_date'])); ?></span>                                                    <?php endif; ?>                                                </div>                                            <?php endif; ?>                                            <?php if (!empty($row['ktt2_name'])): ?>                                                <div class="approval-item">                                                    <span class="approver-name"><strong>KTT TTN:</strong> <?php echo htmlspecialchars($row['ktt2_name']); ?></span>                                                    <?php if (!empty($row['ktt2_approved_date'])): ?>                                                        <span class="approval-datetime"><?php echo date('d/m/Y', strtotime($row['ktt2_approved_date'])); ?> - <?php echo date('H:i', strtotime($row['ktt2_approved_date'])); ?></span>                                                    <?php endif; ?>                                                </div>                                            <?php endif; ?>                                        <?php elseif (!empty($row['approved_by_name']) && !empty($row['approved_date'])): ?>                                            <div class="approval-item">                                                <span class="approver-name"><?php echo htmlspecialchars($row['approved_by_name']); ?></span>                                                <span class="approval-datetime"><?php echo date('d/m/Y', strtotime($row['approved_date'])); ?> - <?php echo date('H:i', strtotime($row['approved_date'])); ?></span>                                            </div>                                        <?php else: ?>                                            <span class="text-muted">N/A</span>                                        <?php endif; ?>                                    </div>                                </td></tr><?php endwhile; ?></tbody></table></div><div class="table-info-report" id="approvedTableInfo"><span data-lang="showing-all-data">Showing all data</span></div></div>
    </div>
    <?php endif; ?>

    <?php if ($rejected_appointments && $rejected_appointments->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report"><h3><i class="fas fa-times-circle"></i> <span data-lang="detail-assign-letter-rejected">Detail Assign Letter Rejected</span></h3><span class="badge-header rejected"><?php echo $rejected_appointments->num_rows; ?></span></div>
        <div class="filter-section-report">
            <div class="filter-group-report"><label><i class="fas fa-map-marker-alt"></i> <span data-lang="work-scope-label">Scope:</span></label><select id="scopeFilterRejected" class="filter-select-report" onchange="filterTableByFilters('rejectedTable')"><option value="" data-lang="all-scopes">-- All Scopes --</option><option value="MSM">PT MSM</option><option value="TTN">PT TTN</option></select></div>
            <div class="filter-group-report"><label><i class="fas fa-eye"></i> <span data-lang="supervision-area-label">Supervision Area:</span></label><select id="supervisionFilterRejected" class="filter-select-report" onchange="filterTableByFilters('rejectedTable')"><option value="" data-lang="all-areas">-- All Areas --</option><?php if ($supervision_areas && $supervision_areas->num_rows > 0) { $supervision_areas->data_seek(0); while ($area = $supervision_areas->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($area['area_name']); ?>"><?php echo htmlspecialchars($area['area_name']); ?></option><?php endwhile; } ?></select></div>
            <div class="filter-action-group"><button class="btn btn-export-small" onclick="exportToExcel('rejectedTable', 'Report_Rejected_Letters')"><i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span></button></div>
        </div>
        <div class="card-body-report"><div class="table-responsive"><table class="table-report table-rejected" id="rejectedTable"><thead><tr><th class="col-number" data-lang="assign-letter-no">Assign Letter No.</th><th class="col-employee" data-lang="employee">Employee</th><th class="col-position" data-lang="position">Position</th><th class="col-date" data-lang="effective-date">Effective Date</th><th class="col-rejected-date" data-lang="rejected-date">Rejected Date</th><th class="col-rejected-by" data-lang="rejected-by">Rejected By</th><th class="col-notes" data-lang="rejection-notes">Rejection Notes</th><th class="col-action" data-lang="action">Action</th></tr></thead><tbody><?php $rejected_appointments->data_seek(0); while ($row = $rejected_appointments->fetch_assoc()): $scope_raw = $row['ruang_lingkup'] ?: ''; $scope_normalized = ''; if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) { $scope_normalized = 'MSM'; } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) { $scope_normalized = 'TTN'; } $supervision_area = htmlspecialchars($row['supervision_area'] ?: ''); ?><tr class="detail-row rejected-row" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>"><td class="col-number"><strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong></td><td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($row['employee_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($row['employee_code']); ?></span></div></td><td class="col-position"><span class="position-badge-report"><?php echo htmlspecialchars($row['position_name']); ?></span></td><td class="col-date"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($row['effective_date'])); ?></td><td class="col-rejected-date"><i class="fas fa-times"></i> <?php echo date('d/m/Y H:i', strtotime($row['approved_date'])); ?></td><td class="col-rejected-by"><span class="rejector-badge"><?php echo htmlspecialchars($row['approved_by_name'] ?: 'N/A'); ?></span></td><td class="col-notes"><span class="notes-badge" onclick="showRejectionModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['appointment_number']); ?>', '<?php echo htmlspecialchars($row['employee_name']); ?>', '<?php echo htmlspecialchars($row['ktt_notes'] ?? ''); ?>')"><i class="fas fa-eye"></i> View Notes</span></td><td class="col-action"><button class="btn-detail-small" onclick="showRejectionModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['appointment_number']); ?>', '<?php echo htmlspecialchars($row['employee_name']); ?>', '<?php echo htmlspecialchars($row['ktt_notes'] ?? ''); ?>')"><i class="fas fa-info-circle"></i> Details</button></td></tr><?php endwhile; ?></tbody></table></div><div class="table-info-report" id="rejectedTableInfo"><span data-lang="showing-all-data">Showing all data</span></div></div>
    </div>
    <?php endif; ?>

    <?php if ($expiring_certs && $expiring_certs->num_rows > 0): ?>
    <div class="card-report" id="certificate-expiration">
        <div class="card-header-report"><div style="display: flex; align-items: center; gap: 10px; flex: 1;"><h3 style="margin: 0;"><i class="fas fa-exclamation-triangle"></i> <span data-lang="certificate-expiration-2-months">Certificate Expiration (=2 Months)</span></h3><span class="badge-header warning"><?php echo $expiring_certs->num_rows; ?></span></div><button class="btn btn-export-small" onclick="exportExpiringCertsToExcel()"><i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span></button></div>
        <div class="alert-warning-report"><i class="fas fa-info-circle"></i><span data-lang="expiring-certs-renew-immediately">The following is a list of employees with certificates expiring within =2 months. Please renew certificates immediately.</span></div>
        <div class="card-body-report"><div class="table-responsive"><table class="table-report table-expiring" id="expiringCertsTable"><thead><tr><th class="col-employee" data-lang="employee">Employee</th><th class="col-cert-name" data-lang="certificate-name">Certificate Name</th><th class="col-cert-number" data-lang="certificate-number">Certificate Number</th><th class="col-expiry-date" data-lang="expiry-date">Expiry Date</th><th class="col-days-left" data-lang="days-left">Days Left</th><th class="col-status-expiry" data-lang="status">Status</th></tr></thead><tbody><?php $expiring_certs->data_seek(0); while ($cert = $expiring_certs->fetch_assoc()): ?><tr class="detail-row expiring-row"><td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($cert['full_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($cert['employee_code']); ?></span></div></td><td class="col-cert-name"><span class="cert-name-badge"><?php echo htmlspecialchars($cert['cert_name'] ?: 'N/A'); ?></span></td><td class="col-cert-number"><?php echo htmlspecialchars($cert['cert_number'] ?: 'N/A'); ?></td><td class="col-expiry-date"><?php echo !empty($cert['expiry_date']) ? date('d/m/Y', strtotime($cert['expiry_date'])) : 'N/A'; ?></td><td class="col-days-left"><span class="days-badge <?php echo ($cert['days_until_expiry'] <= 30) ? 'days-critical' : (($cert['days_until_expiry'] <= 60) ? 'days-urgent' : 'days-warning'); ?>"><?php echo (int)$cert['days_until_expiry']; ?> days</span></td><td class="col-status-expiry"><span class="status-badge <?php echo ($cert['days_until_expiry'] <= 30) ? 'status-critical' : (($cert['days_until_expiry'] <= 60) ? 'status-urgent' : 'status-warning'); ?>"><?php echo ($cert['days_until_expiry'] <= 30) ? 'Critical' : (($cert['days_until_expiry'] <= 60) ? 'Urgent' : 'Warning'); ?></span></td></tr><?php endwhile; ?></tbody></table></div></div>
    </div>
    <?php endif; ?>
</div>

<div id="requestRejectionModal" class="modal">
    <div class="modal-content modal-rejection">
        <div class="modal-header modal-header-rejection"><h3><i class="fas fa-exclamation-circle"></i> <span data-lang="request-rejection-details">Request Rejection Details</span></h3><span class="close" onclick="closeRequestRejectionModal()">&times;</span></div>
        <div class="modal-body modal-body-rejection"><div class="rejection-info"><div class="info-row"><label data-lang="employee-name-label">Employee Name:</label><span id="reqRejectionEmployeeName"></span></div><div class="info-row"><label data-lang="employee-code-label">Employee Code:</label><span id="reqRejectionEmployeeCode"></span></div><div class="rejection-notes-section"><h4><i class="fas fa-clipboard"></i> <span data-lang="rejection-notes">Rejection Notes</span></h4><div class="rejection-notes-content" id="reqRejectionNotesContent"></div></div></div></div>
        <div class="modal-footer modal-footer-rejection"><button type="button" class="btn btn-secondary" onclick="closeRequestRejectionModal()"><span data-lang="close">Close</span></button></div>
    </div>
</div>

<div id="rejectionModal" class="modal">
    <div class="modal-content modal-rejection">
        <div class="modal-header modal-header-rejection"><h3><i class="fas fa-exclamation-circle"></i> <span data-lang="assign-letter-rejection-details">Assign Letter Rejection Details</span></h3><span class="close" onclick="closeRejectionModal()">&times;</span></div>
        <div class="modal-body modal-body-rejection"><div class="rejection-info"><div class="info-row"><label data-lang="assign-letter-no-label">Assign Letter No.:</label><span id="rejectionAppointmentNumber"></span></div><div class="info-row"><label data-lang="employee-name-label">Employee Name:</label><span id="rejectionEmployeeName"></span></div><div class="rejection-notes-section"><h4><i class="fas fa-clipboard"></i> <span data-lang="rejection-notes-from-ktt">KTT Rejection Notes</span></h4><div class="rejection-notes-content" id="rejectionNotesContent"></div></div></div></div>
        <div class="modal-footer modal-footer-rejection"><button type="button" class="btn btn-secondary" onclick="closeRejectionModal()"><span data-lang="close">Close</span></button></div>
    </div>
</div>

<script>
function exportRequestsToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) {
        alert('Table not found');
        return;
    }

    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const filteredRows = [];
    for (let row of rows) {
        if (row.style.display !== 'none') {
            filteredRows.push(row);
        }
    }

    if (filteredRows.length === 0) {
        alert('No data to export');
        return;
    }

    const departmentName = 'Department';
    const statusFilter = document.getElementById('statusFilterRequests').value;
    const statusLabel = statusFilter ? statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1) : '';

    let excelContent = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    excelContent += '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    excelContent += '<x:Name>Department Requests</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    excelContent += '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';
    excelContent += '<table><tr><td colspan="6"><h2>Department Request Report</h2></td></tr>';
    excelContent += '<tr><td colspan="6">Department: ' + departmentName + '</td></tr>';
    excelContent += '<tr><td colspan="6">Export Date: ' + new Date().toLocaleDateString('en-US') + ' ' + new Date().toLocaleTimeString('en-US') + '</td></tr>';
    if (statusLabel) {
        excelContent += '<tr><td colspan="6">Filter Status: ' + statusLabel + '</td></tr>';
    }
    excelContent += '<tr><td colspan="6">Total Data: ' + filteredRows.length + '</td></tr>';
    excelContent += '<tr><td colspan="6">&nbsp;</td></tr>';
    excelContent += '<tr style="background-color: #37474F; color: white; font-weight: bold;">';
    excelContent += '<td>Employee</td><td>Code</td><td>Request Date</td><td>Status</td><td>Verification Date</td><td>Verified By</td>';
    excelContent += '</tr>';

    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        excelContent += '<tr>';
        excelContent += '<td>' + cells[0].textContent.trim().replace(/\s+/g, ' ') + '</td>';
        excelContent += '<td>' + cells[1].textContent.trim() + '</td>';
        excelContent += '<td>' + cells[2].textContent.trim() + '</td>';
        excelContent += '<td>' + cells[3].textContent.trim().replace(/\s+/g, ' ') + '</td>';
        excelContent += '<td>' + cells[4].textContent.trim() + '</td>';
        excelContent += '<td>' + cells[5].textContent.trim() + '</td>';
        excelContent += '</tr>';
    }

    excelContent += '</table></body></html>';

    const blob = new Blob([excelContent], { type: 'application/vnd.ms-excel' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '_' + departmentName.replace(/[^a-zA-Z0-9]/g, '_') + (statusLabel ? '_' + statusLabel : '') + '_' + new Date().toISOString().slice(0, 10) + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function exportApprovedByDepartment() {
    const table = document.getElementById('approvedTable');
    if (!table) {
        const tableNotFound = window.getLanguageText('');
        alert(tableNotFound);
        return;
    }
    
    // Get all visible rows from the table
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let filteredRows = [];
    
    for (let row of rows) {
        if (row.style.display !== 'none') {
            filteredRows.push(row);
        }
    }
    
    if (filteredRows.length === 0) {
        const noDataToPrint = window.getLanguageText('');
        alert(noDataToPrint);
        return;
    }
    
    const departmentName = 'Department';
    
    // Generate HTML for PDF
    const printTitle = window.getLanguageText('');
    const printedOnText = window.getLanguageText('');
    const timeText = window.getLanguageText('');
    const totalAcceptedText = window.getLanguageText('');
    const footerPrintText = window.getLanguageText('');

    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>${printTitle} - ${departmentName}</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                h1 {
                    text-align: center;
                    color: #37474F;
                    margin-bottom: 5px;
                }
                .department-name {
                    text-align: center;
                    font-size: 16px;
                    color: #37474F;
                    font-weight: 600;
                    margin-bottom: 5px;
                }
                .header-info {
                    text-align: center;
                    margin-bottom: 20px;
                    font-size: 12px;
                    color: #666;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                thead {
                    background-color: #f0f0f0;
                }
                th {
                    padding: 10px;
                    text-align: left;
                    font-weight: 600;
                    border: 1px solid #ddd;
                    font-size: 12px;
                }
                td {
                    padding: 8px 10px;
                    border: 1px solid #ddd;
                    font-size: 11px;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .summary-box {
                    background-color: #E8F5E9;
                    padding: 12px;
                    margin-bottom: 15px;
                    border-radius: 4px;
                    border-left: 4px solid #2E7D32;
                    font-weight: 600;
                    color: #2E7D32;
                }
                .footer {
                    text-align: center;
                    font-size: 10px;
                    color: #999;
                    margin-top: 20px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                }
            </style>
        </head>
        <body>
            <h1>?? ${printTitle}</h1>
            <div class="department-name">${departmentName}</div>
            <div class="header-info">
                ${printedOnText}: ${new Date().toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' })}
                <br>${timeText}: ${new Date().toLocaleTimeString('id-ID')}
            </div>
            <div class="summary-box">
                ? ${totalAcceptedText}: ${filteredRows.length}
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">${window.getLanguageText('')}</th>
                        <th style="width: 22%;">${window.getLanguageText('')}</th>
                        <th style="width: 20%;">${window.getLanguageText('')}</th>
                        <th style="width: 13%;">${window.getLanguageText('')}</th>
                        <th style="width: 13%;">${window.getLanguageText('')}</th>
                        <th style="width: 17%;">${window.getLanguageText('')}</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        htmlContent += `
            <tr>
                <td>${cells[0].textContent.trim()}</td>
                <td>${cells[1].textContent.trim().split('\\n')[0]}</td>
                <td>${cells[2].textContent.trim()}</td>
                <td>${cells[3].textContent.trim()}</td>
                <td>${cells[4].textContent.trim()}</td>
                <td>${cells[5].textContent.trim()}</td>
            </tr>
        `;
    }
    
    htmlContent += `
                </tbody>
            </table>
            <div class="footer">
                ${footerPrintText}
            </div>
        </body>
        </html>
    `;
    
    // Open in new window and print
    const printWindow = window.open('', '_blank');
    printWindow.document.write(htmlContent);
    printWindow.document.close();
    
    // Wait for content to load then print
    setTimeout(() => {
        printWindow.print();
    }, 250);
}

function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) {
        const tableNotFound = window.getLanguageText('');
        alert(tableNotFound);
        return;
    }
    
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let filteredRows = [];
    
    // Get only visible rows
    for (let row of rows) {
        if (row.style.display !== 'none') {
            filteredRows.push(row);
        }
    }
    
    if (filteredRows.length === 0) {
        const noDataToExport = window.getLanguageText('');
        alert(noDataToExport);
        return;
    }
    
    // Get active filter
    const scopeFilter = document.getElementById('scopeFilter' + (tableId === 'approvedTable' ? 'Approved' : 'Rejected')).value;
    
    // Determine if it's approved or rejected table
    const isApproved = tableId === 'approvedTable';
    const departmentName = 'Department';
    
    // Build Excel HTML
    let excelContent = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    excelContent += '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    excelContent += '<x:Name>Laporan</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    excelContent += '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';
    
    const reportTitleText = window.getLanguageText('');
    const acceptedText = window.getLanguageText('');
    const rejectedText = window.getLanguageText('');
    const departmentText = window.getLanguageText('');
    const exportDateText = window.getLanguageText('');
    const scopeFilterText = window.getLanguageText('');
    const totalDataText = window.getLanguageText('');

    // Add title and filters info
    excelContent += '<table><tr><td colspan="7"><h2>' + reportTitleText + ' ' + (isApproved ? acceptedText : rejectedText) + '</h2></td></tr>';
    excelContent += '<tr><td colspan="7">' + departmentText + ': ' + departmentName + '</td></tr>';
    excelContent += '<tr><td colspan="7">' + exportDateText + ': ' + new Date().toLocaleDateString('id-ID') + ' ' + new Date().toLocaleTimeString('id-ID') + '</td></tr>';
    
    if (scopeFilter) {
        const scopeLabel = scopeFilter === 'MSM' ? 'PT MSM' : 'PT TTN';
        excelContent += '<tr><td colspan="7">' + scopeFilterText + ': ' + scopeLabel + '</td></tr>';
    }
    
    excelContent += '<tr><td colspan="7">' + totalDataText + ': ' + filteredRows.length + '</td></tr>';
    excelContent += '<tr><td colspan="7">&nbsp;</td></tr>';
    
    // Add table headers
    excelContent += '<tr style="background-color: #37474F; color: white; font-weight: bold;">';
    excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    excelContent += '<td>' + (isApproved ? acceptedText : rejectedText) + '</td>';
    excelContent += '<td>' + (isApproved ? (window.getLanguageText('')) : (window.getLanguageText(''))) + '</td>';
    if (!isApproved) {
        excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    }
    excelContent += '</tr>';
    
    // Add table data
    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        excelContent += '<tr>';
        
        // No. Surat
        excelContent += '<td>' + cells[0].textContent.trim() + '</td>';
        
        // Nama Karyawan (mengambil hanya nama, tanpa kode)
        const employeeText = cells[1].textContent.trim().split('\n');
        excelContent += '<td>' + employeeText[0].trim() + '</td>';
        
        // Kode Karyawan
        if (employeeText.length > 1) {
            excelContent += '<td>' + employeeText[1].trim() + '</td>';
        } else {
            excelContent += '<td></td>';
        }
        
        // Jabatan
        excelContent += '<td>' + cells[2].textContent.trim() + '</td>';
        
        // Tanggal Efektif
        excelContent += '<td>' + cells[3].textContent.trim() + '</td>';
        
        // Approved/Rejected Date
        excelContent += '<td>' + cells[4].textContent.trim() + '</td>';
        
        // Approved/Rejected By
        excelContent += '<td>' + cells[5].textContent.trim() + '</td>';
        
        // Notes (for rejected only)
        if (!isApproved && cells.length > 6) {
            excelContent += '<td>View Details</td>';
        }
        
        excelContent += '</tr>';
    }
    
    excelContent += '</table></body></html>';
    
    // Create download link
    const blob = new Blob([excelContent], {
        type: 'application/vnd.ms-excel'
    });
    
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    
    // Create filename with filters
    let finalFilename = filename + '_' + departmentName.replace(/[^a-zA-Z0-9]/g, '_');
    if (scopeFilter) {
        const scopeLabel = scopeFilter === 'MSM' ? 'PT_MSM' : 'PT_TTN';
        finalFilename += '_' + scopeLabel;
    }
    finalFilename += '_' + new Date().toISOString().slice(0, 10) + '.xls';
    
    a.download = finalFilename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php require_once '../../includes/footer.php'; ?>




