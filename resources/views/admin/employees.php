<?php
$page_title = 'Contractor Workforce Data';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php
// Included via bootstrap/app.php
// Included via bootstrap/app.php

requirePermission('admin.access');
requirePermission('employee.view');

$db = new Database();
$message = '';
$error = '';

// Check if competencies table exists and get competencies by type
$competencies_table_exists = false;
$check_table = $db->query("SHOW TABLES LIKE 'competencies'");
if ($check_table && $check_table->num_rows > 0) {
    $competencies_table_exists = true;
}

$competencies_by_type = [];
$competencies_with_id = []; // Store competencies with ID for JavaScript
if ($competencies_table_exists) {
    $competencies_result = $db->query("SELECT id, competency_name, position_type FROM competencies ORDER BY position_type, competency_name");
    while ($comp = $competencies_result->fetch_assoc()) {
        $type = $comp['position_type'];
        if (!isset($competencies_by_type[$type])) {
            $competencies_by_type[$type] = [];
        }
        $competencies_by_type[$type][] = $comp;
        // Store all competencies with ID for JavaScript use
        $competencies_with_id[] = $comp;
    }
}

// Handle form submission to add data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- IMPLEMENTASI ANTI-CSRF ---
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('Akses Ditolak: Token CSRF tidak valid atau kedaluwarsa. Silakan muat ulang halaman.');
    }
    // ------------------------------

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $employee_code = $db->escapeString($_POST['employee_code']);
        $full_name = $db->escapeString($_POST['full_name']);
        $position = $db->escapeString($_POST['position']);
        $department = $db->escapeString($_POST['department']);
        $contractor_company = $db->escapeString($_POST['contractor_company']);
        $competency_type = $db->escapeString($_POST['competency_type']);
        $ruang_lingkup = !empty($_POST['ruang_lingkup']) ? $db->escapeString($_POST['ruang_lingkup']) : '';
        $competency_name = !empty($_POST['competency_name']) ? $db->escapeString($_POST['competency_name']) : '';
        $sub_competency = !empty($_POST['sub_competency']) ? $db->escapeString($_POST['sub_competency']) : '';
        $supervision_area = !empty($_POST['supervision_area']) ? $db->escapeString($_POST['supervision_area']) : '';
        
        // Validasi khusus untuk company internal MSM/TTN
        $is_internal = in_array(strtoupper($contractor_company), ['MSM', 'TTN']);

        if (empty($employee_code) || empty($full_name) || empty($position) || empty($department) || empty($contractor_company) || empty($competency_type)) {
            $error = stela_t('all-required-fields-must-be-filled');
        }
        // Cek duplikat employee_code
        elseif ($db->query("SELECT employee_code FROM employees WHERE employee_code = '$employee_code' AND is_active = 1")->num_rows > 0) {
            $error = stela_t('employee-code-already-registered');
        } elseif (in_array($competency_type, ['pengawas_teknis', 'pengawas_operasional']) && empty($ruang_lingkup)) {
            $error = stela_t('scope-required-tech-and-ops-supervisor');
        } elseif (empty($competency_name)) {
            $error = stela_t('competency-required-all-types');
        } elseif ($competency_type == 'pengawas_operasional' && empty($supervision_area)) {
            $error = stela_t('supervision-area-required-operational-supervisor');
        } elseif ($competency_type == 'tenaga_teknis' && empty($sub_competency)) {
            $error = stela_t('sub-competency-required-technical-personnel');
        } elseif (!isset($_FILES['cv_file']) || $_FILES['cv_file']['error'] != 0) {
            $error = stela_t('cv-upload-required');
        } elseif (!isset($_FILES['statement_file']) || $_FILES['statement_file']['error'] != 0) {
            $error = stela_t('statement-upload-required');
        }

        // Jika internal, pastikan validasi dan proses sama seperti eksternal
        // Jika ada validasi khusus eksternal, tambahkan pengecualian di sini
        
        if (empty($error)) {
            // Handle CV upload
            $cv_file = '';
            $file_size = $_FILES['cv_file']['size'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_ext = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'doc', 'docx'];
            
            if (!in_array($file_ext, $allowed_extensions)) {
                $error = stela_t('cv-file-type-not-allowed-pdf-doc-docx');
            } elseif ($file_size > $max_size) {
                $error = stela_t('cv-file-size-max-5mb');
            } else {
                $upload_dir = upload_physical_dir('cv');
                
                $cv_file = 'cv_' . $employee_code . '_' . time() . '.' . $file_ext;
                
                if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_dir . $cv_file)) {
                    $cv_file = 'cv/' . $cv_file;
                } else {
                    $error = stela_t('failed-upload-cv-file');
                }
            }
            
            // Handle Statement upload (required)
            $statement_file = '';
            if (!$error) {
                $statement_size = $_FILES['statement_file']['size'];
                $statement_max_size = 5 * 1024 * 1024; // 5MB
                $statement_ext = strtolower(pathinfo($_FILES['statement_file']['name'], PATHINFO_EXTENSION));
                $statement_allowed_ext = ['pdf', 'doc', 'docx'];
                
                if (!in_array($statement_ext, $statement_allowed_ext)) {
                    $error = stela_t('statement-file-type-not-allowed-pdf-doc-docx');
                } elseif ($statement_size > $statement_max_size) {
                    $error = stela_t('statement-file-size-max-5mb');
                } else {
                    $statement_upload_dir = upload_physical_dir('statements');
                    
                    $statement_filename = 'statement_' . $employee_code . '_' . time() . '.' . $statement_ext;
                    if (!move_uploaded_file($_FILES['statement_file']['tmp_name'], $statement_upload_dir . $statement_filename)) {
                        $error = stela_t('failed-upload-statement-file');
                    } else {
                        $statement_file = 'statements/' . $statement_filename;
                    }
                }
            }
            
            // Cek struktur tabel employees terlebih dahulu
            $columns_result = $db->query("SHOW COLUMNS FROM employees");
            $available_columns = [];
            while ($col = $columns_result->fetch_assoc()) {
                $available_columns[] = $col['Field'];
            }
            
            // Buat query INSERT dinamis berdasarkan kolom yang tersedia
            $insert_fields = ['employee_code', 'full_name', 'position', 'department', 'competency_type', 'contractor_company', 'cv_file', 'verification_status', 'is_active'];
            $insert_values = ["'$employee_code'", "'$full_name'", "'$position'", "'$department'", "'$competency_type'", "'$contractor_company'", "'$cv_file'", "'pending'", "1"];
            
            // Add optional fields if they exist in the table
            if (in_array('ruang_lingkup', $available_columns) && !empty($ruang_lingkup)) {
                $insert_fields[] = 'ruang_lingkup';
                $insert_values[] = "'$ruang_lingkup'";
            }
            
            if (in_array('competency_name', $available_columns) && !empty($competency_name)) {
                $insert_fields[] = 'competency_name';
                $insert_values[] = "'$competency_name'";
            }

            if (in_array('sub_competency', $available_columns) && !empty($sub_competency)) {
                $insert_fields[] = 'sub_competency';
                $insert_values[] = "'$sub_competency'";
            }

            if (in_array('supervision_area', $available_columns) && !empty($supervision_area)) {
                $insert_fields[] = 'supervision_area';
                $insert_values[] = "'$supervision_area'";
            }
            
            if (in_array('statement_file', $available_columns) && !empty($statement_file)) {
                $insert_fields[] = 'statement_file';
                $insert_values[] = "'$statement_file'";
            }
            
            // Track who created this employee record
            if (in_array('created_by', $available_columns)) {
                $insert_fields[] = 'created_by';
                $insert_values[] = "'" . intval($_SESSION['user_id']) . "'";
            }
            
            $sql = "INSERT INTO employees (" . implode(', ', $insert_fields) . ") 
                    VALUES (" . implode(', ', $insert_values) . ")";
            
            if ($db->query($sql)) {
                $employee_id = $db->lastInsertId();
                
                // Sync to Elasticsearch
                if (class_exists('ElasticsearchService')) {
                    ElasticsearchService::getInstance()->indexEmployee([
                        'id' => $employee_id,
                        'employee_code' => $_POST['employee_code'] ?? '',
                        'full_name' => $_POST['full_name'] ?? '',
                        'position' => $_POST['position'] ?? '',
                        'department' => $_POST['department'] ?? '',
                        'contractor_company' => $_POST['contractor_company'] ?? '',
                        'competency_type' => $_POST['competency_type'] ?? '',
                        'ruang_lingkup' => $_POST['ruang_lingkup'] ?? '',
                        'sub_competency' => $_POST['sub_competency'] ?? '',
                        'supervision_area' => $_POST['supervision_area'] ?? '',
                        'approval_status' => 'pending',
                        'is_active' => 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                // Handle multiple certification uploads
                if (isset($_FILES['certifications']) && !empty($_FILES['certifications']['name'][0])) {
                    $upload_dir = upload_physical_dir('certifications');
                    
                    $cert_ids = $_POST['certification_ids'] ?? [];
                    $cert_numbers = $_POST['cert_numbers'] ?? [];
                    $cert_issuers = $_POST['cert_issuers'] ?? [];
                    $issue_dates = $_POST['issue_dates'] ?? [];
                    $expiry_dates = $_POST['expiry_dates'] ?? [];
                    $no_expiry = $_POST['no_expiry'] ?? [];
                    $expiry_reasons = $_POST['expiry_reasons'] ?? [];
                    
                    foreach ($_FILES['certifications']['tmp_name'] as $key => $tmp_name) {
                        if (isset($_FILES['certifications']['error'][$key]) && $_FILES['certifications']['error'][$key] == 0) {
                            $file_ext = pathinfo($_FILES['certifications']['name'][$key], PATHINFO_EXTENSION);
                            $cert_file = $employee_code . '_cert_' . $key . '_' . time() . '.' . $file_ext;
                            
                            if (move_uploaded_file($tmp_name, $upload_dir . $cert_file)) {
                                $cert_path = 'certifications/' . $cert_file;
                                $cert_id = intval($cert_ids[$key] ?? 0);
                                $cert_number = $db->escapeString($cert_numbers[$key] ?? '');
                                $cert_issuer = $db->escapeString($cert_issuers[$key] ?? '');
                                $issue_date = $db->escapeString($issue_dates[$key] ?? '');
                                $expiry_date = $db->escapeString($expiry_dates[$key] ?? '');
                                $reason = $db->escapeString($expiry_reasons[$key] ?? '');
                                
                                // Check if expired
                                $today = date('Y-m-d');
                                $status = ($expiry_date && $expiry_date < $today) ? 'expired' : 'pending';
                                
                                $sql_cert = "INSERT INTO employee_certifications 
                                            (employee_id, certification_id, cert_number, cert_issuer, issue_date, expiry_date, 
                                             document_file, status, verification_status, expiry_reason) 
                                            VALUES ($employee_id, $cert_id, '$cert_number', '$cert_issuer', '$issue_date', '$expiry_date', 
                                                    '$cert_path', '$status', 'pending', '$reason')";
                                
                                if (!$db->query($sql_cert)) {
                                    error_log("Error inserting certification: " . $db->getConnection()->error);
                                }
                            }
                        }
                    }
                }
                
                // Send notification to admin (if added by non-admin)
                if (!isAdmin()) {
                    try {
                        $notificationService = new NotificationService();
                        $notificationService->notifyNewEmployeeAdded($employee_id, $contractor_company);
                    } catch (Exception $e) {
                        error_log("Notification error: " . $e->getMessage());
                    }
                }
                
                $message = stela_t('workforce-added-waiting-verification');
            } else {
                $error = stela_t('failed-add-workforce-data');
            }
        }
    }
}

// Get filter and search query from URL
$filter = isset($_GET['filter']) ? $db->escapeString($_GET['filter']) : '';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['search']) ? trim($_GET['search']) : '');

// Build WHERE clause for filter & search
$where_clause = "e.is_active = 1";
if (!empty($filter)) {
    $where_clause .= " AND e.verification_status = '$filter'";
}

$esService = class_exists('ElasticsearchService') ? ElasticsearchService::getInstance() : null;
$es_used = false;
$es_total = 0;
$order_clause = "ORDER BY e.verification_status, e.created_at DESC";

if (!empty($search_query)) {
    if ($esService && $esService->isAvailable()) {
        $filters = [];
        if (!empty($filter)) {
            $filters['approval_status'] = $filter;
        }
        $esResult = $esService->searchEmployees($search_query, $filters, 0, 500);
        if ($esResult !== false && !empty($esResult['items'])) {
            $es_used = true;
            $es_total = $esResult['total'];
            $matching_ids = array_column($esResult['items'], 'id');
            $matching_ids_str = implode(',', array_map('intval', $matching_ids));
            $where_clause .= " AND e.id IN ($matching_ids_str)";
            $order_clause = "ORDER BY FIELD(e.id, $matching_ids_str)";
        } else {
            // Fallback MySQL search if no ES hits
            $safe_search = $db->escapeString($search_query);
            $where_clause .= " AND (e.full_name LIKE '%$safe_search%' OR e.employee_code LIKE '%$safe_search%' OR e.contractor_company LIKE '%$safe_search%' OR e.position LIKE '%$safe_search%')";
        }
    } else {
        // Fallback MySQL search if ES is unavailable
        $safe_search = $db->escapeString($search_query);
        $where_clause .= " AND (e.full_name LIKE '%$safe_search%' OR e.employee_code LIKE '%$safe_search%' OR e.contractor_company LIKE '%$safe_search%' OR e.position LIKE '%$safe_search%')";
    }
}

// Get all employees with verification status and KTT rejection awareness
$employees = $db->query("
    SELECT e.*,
           CASE
               WHEN e.verification_status = 'pending' THEN 'warning'
               WHEN e.verification_status = 'verified' THEN 'success'
               WHEN e.verification_status = 'rejected' THEN 'danger'
           END as status_class,
           u.full_name as verified_by_name,
           e.verified_date,
           CASE
               WHEN e.competency_type = 'pengawas_operasional' THEN 'Pengawas Operasional'
               WHEN e.competency_type = 'pengawas_teknis' THEN 'Pengawas Teknis'
               WHEN e.competency_type = 'tenaga_teknis' THEN 'Tenaga Teknis'
               ELSE e.competency_type
           END as competency_type_display,
           MAX(a.status) as appointment_status,
           MAX(a.approval_notes) as ktt_rejection_notes,
           MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) as has_ktt_rejection,
           CASE
               WHEN MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) = 1 AND e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL THEN 'pending'
               WHEN MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) = 1 THEN 'rejected'
               WHEN MAX(a.status) = 'rejected' THEN 'rejected'
               WHEN e.verification_status = 'rejected' THEN 'rejected'
               WHEN e.verification_status = 'draft' THEN 'draft'
               ELSE e.verification_status
           END as combined_status
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    LEFT JOIN appointments a ON e.id = a.employee_id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id
    WHERE $where_clause
    GROUP BY e.id
    $order_clause
");

// Get certifications for dropdown
$certifications = $db->query("SELECT * FROM certifications ORDER BY cert_name");
$certifications_data = [];
if ($certifications && $certifications->num_rows > 0) {
    $certifications->data_seek(0);
    while ($cert = $certifications->fetch_assoc()) {
        $certifications_data[$cert['id']] = $cert;
    }
}

// Get positions grouped by position_type for competency selection
$positions = $db->query("SELECT * FROM positions ORDER BY position_type, position_name");
$positions_by_type = [];
if ($positions && $positions->num_rows > 0) {
    while ($pos = $positions->fetch_assoc()) {
        $type = $pos['position_type'];
        if (!isset($positions_by_type[$type])) {
            $positions_by_type[$type] = [];
        }
        $positions_by_type[$type][] = $pos;
    }
}

// Get supervision areas from database
$supervision_areas = $db->query("SELECT * FROM supervision_areas ORDER BY area_name");

require_once dirname(__DIR__) . '/layouts/header.php';

// Get unique companies for filter (moved here before statistics calculation)
$companies = $db->query("
    SELECT DISTINCT contractor_company
    FROM employees
    WHERE is_active = 1
    ORDER BY contractor_company
");

// Get statistics
$total_employees = $employees->num_rows;
$pending_verification = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'pending' AND is_active = 1")->fetch_assoc()['count'];
// Count only verified/rejected by current logged-in admin
$current_user_id = $_SESSION['user_id'];
$verified_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'verified' AND is_active = 1 AND verified_by = '$current_user_id'")->fetch_assoc()['count'];
$rejected_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'rejected' AND is_active = 1 AND verified_by = '$current_user_id'")->fetch_assoc()['count'];

// Get statistics per company
$companies_stats = [];
if ($companies && $companies->num_rows > 0) {
    $companies->data_seek(0);
    while ($comp = $companies->fetch_assoc()) {
        $company_name = $comp['contractor_company'];
        $stats = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM employees 
            WHERE contractor_company = '" . $db->escapeString($company_name) . "' AND is_active = 1
        ")->fetch_assoc();
        
        $companies_stats[$company_name] = $stats;
    }
}

// Count rejected employees that need resubmission (only those created by current admin)
$rejected_resubmit_count = $db->query("
    SELECT COUNT(DISTINCT e.id) as count
    FROM employees e
    LEFT JOIN appointments a ON e.id = a.employee_id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id AND ka.action = 'reject'
    WHERE e.is_active = 1
    AND e.created_by = '$current_user_id'
    AND (
        (e.verification_status = 'rejected')
        OR
        (ka.id IS NOT NULL AND NOT (e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL))
        OR
        (a.status = 'rejected' AND NOT (e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL))
    )
")->fetch_assoc()['count'];

// Reset companies pointer for filter dropdown
$companies = $db->query("
    SELECT DISTINCT contractor_company
    FROM employees
    WHERE is_active = 1
    ORDER BY contractor_company
");
?>

<div class="employees-admin-container">
    <!-- Page Header -->
    <div class="page-header-emp-admin">
        <div class="header-left">
            <h2><i class="fas fa-building"></i> <span data-lang="contractor-workforce-data">Contractor Workforce Data</span></h2>
            <p data-lang="manage-verify-workforce">Manage and verify contractor workforce data</p>
        </div>
        <a href="add_employee.php" class="btn btn-primary btn-lg-emp">
            <i class="fas fa-plus-circle"></i> <span data-lang="new-request">New Request</span>
        </a>
    </div>
    
    <?php if ($rejected_resubmit_count > 0): ?>
    <div class="alert alert-warning alert-custom-emp alert-resubmit-emp">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong data-lang="data-rejected">Data Ditolak!</strong>
            <p><span data-lang="rejected-employees-message-1">Terdapat</span> <strong><?php echo $rejected_resubmit_count; ?></strong> <span data-lang="rejected-employees-message-2">data karyawan yang ditolak dan perlu diperbaiki. Klik tombol</span> <strong>"Resubmit"</strong> <span data-lang="rejected-employees-message-3">pada kolom aksi untuk mengupload koreksi data.</span></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($filter)): ?>
    <div class="alert alert-info alert-custom-emp">
        <i class="fas fa-filter"></i>
        <div>
            <strong data-lang="active-filter">Active Filter:</strong>
            <p><span data-lang="displaying-employees-status">Displaying employees with status:</span> <strong>
                <?php
                $filter_labels = [
                    'pending' => 'Pending',
                    'verified' => 'Verified',
                    'rejected' => 'Rejected',
                    'draft' => 'Draft'
                ];
                echo $filter_labels[$filter] ?? $filter;
                ?>
            </strong></p>
        </div>
        <a href="employees.php" class="btn btn-sm btn-secondary" style="margin-left: auto;">
            <i class="fas fa-times"></i> <span data-lang="remove-filter">Remove Filter</span>
        </a>
    </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-emp">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-emp">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards - Overall -->
    <div class="stats-section-title">
        <h4><span data-lang="overall-statistics">Overall Statistics</span></h4>
    </div>
    <div class="stats-grid-emp">
        <div class="stat-box-emp stat-total">
            <div class="stat-icon-emp"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $total_employees; ?></div>
                <div class="stat-text" data-lang="total-employees">Total Employees</div>
            </div>
        </div>

        <div class="stat-box-emp stat-pending">
            <div class="stat-icon-emp"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $pending_verification; ?></div>
                <div class="stat-text" data-lang="pending">Pending</div>
            </div>
        </div>

        <div class="stat-box-emp stat-verified">
            <div class="stat-icon-emp"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $verified_count; ?></div>
                <div class="stat-text" data-lang="accept">Accept</div>
            </div>
        </div>

        <div class="stat-box-emp stat-rejected">
            <div class="stat-icon-emp"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-text" data-lang="reject">Reject</div>
            </div>
        </div>

        <?php
        $draft_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'draft' AND is_active = 1")->fetch_assoc()['count'];
        ?>
        <div class="stat-box-emp stat-draft" style="border-left-color: #6c757d; background: linear-gradient(to right, #f8f9fa, #ffffff);">
            <div class="stat-icon-emp" style="color: #6c757d; background: rgba(108,117,125,0.1);"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <div class="stat-number" style="color: #6c757d;"><?php echo $draft_count; ?></div>
                <div class="stat-text">Draft</div>
            </div>
        </div>
    </div>
    
    <!-- Bonsai.io Pagination Search Section -->
    

    <div class="card-emp es-search-card" style="margin-bottom: 16px; padding: 16px 20px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e9ecef; position: relative; z-index: 1050; overflow: visible;">
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Search Input -->
            <div style="flex: 1; min-width: 220px; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 14px; z-index: 2;"></i>
                <input type="text" id="esSearchInput" autocomplete="off"
                       placeholder="Cari Nama, ID Badge, Perusahaan, Posisi..."
                       style="width:100%; padding-left: 40px; padding-right: 38px; height: 42px; border-radius: 8px; border: 1px solid #ced4da; font-size: 14px;">
                <button type="button" id="esClearBtn" title="Bersihkan"
                        style="display:none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #a0aec0; cursor: pointer; font-size: 17px; padding: 0; z-index: 2;">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
            <!-- Company Filter -->
            <select id="filterCompany" style="height:42px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:160px;">
                <option value="">Semua Perusahaan</option>
                <?php
                $companiesOpt = $db->query("SELECT DISTINCT contractor_company FROM employees WHERE is_active = 1 ORDER BY contractor_company");
                if ($companiesOpt) { while ($co = $companiesOpt->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($co['contractor_company']); ?>"><?php echo htmlspecialchars($co['contractor_company']); ?></option>
                <?php endwhile; } ?>
            </select>
            <!-- Competency Type Filter -->
            <select id="filterCompetencyType" style="height:42px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:180px;">
                <option value="">Semua Kompetensi</option>
                <option value="pengawas_operasional">Pengawas Operasional</option>
                <option value="pengawas_teknis">Pengawas Teknis</option>
                <option value="tenaga_teknis">Tenaga Teknis</option>
            </select>
            <!-- Status Filter -->
            <select id="filterStatus" style="height:42px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:130px;">
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="rejected">Rejected</option>
                <option value="draft">Draft</option>
            </select>
            <!-- Page Size -->
            <select id="bonsaiPageLimit" style="height:42px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:90px;">
                <option value="10">10 / hal</option>
                <option value="25">25 / hal</option>
                <option value="50">50 / hal</option>
                <option value="100">100 / hal</option>
            </select>
        </div>
        <!-- Result info -->
        <div id="bonsaiInfoContainer" style="margin-top: 10px; font-size: 13px; color: #6c757d;"></div>
    </div>


    <!-- Employees Table -->
    <div class="card-emp">
        <div class="card-header-emp">
            <h3><i class="fas fa-list"></i> <span data-lang="complete-workforce-list">Complete Workforce List</span></h3>
        </div>

        <div class="card-body-emp">
            <div class="table-responsive">
                <table class="table-emp" id="employeesTable">
                    <thead>
                        <tr>
                            <th class="col-code" data-lang="id-badge">ID BADGE</th>
                            <th class="col-name" data-lang="name">Name</th>
                            <th class="col-position no-required-marker" data-lang="position">Position</th>
                            <th class="col-company no-required-marker" data-lang="company">Company</th>
                            <th class="col-competency-type no-required-marker" data-lang="competency-type">Competency Type</th>
                            <th class="col-competency no-required-marker" data-lang="competency">Competency</th>
                            <th class="col-status" data-lang="status">Status</th>
                            <th class="col-verified-by" data-lang="verified-by">Verified By</th>
                            <th class="col-action" data-lang="action">Action</th>
                        </tr>
                    </thead>
                    <tbody id="empTbody">
                        <tr><td colspan="9" style="text-align:center;padding:32px;color:#a0aec0;"><i class="fas fa-circle-notch fa-spin"></i> Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination controls -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; flex-wrap:wrap; gap:8px;">
                <div id="bonsaiInfoContainerTable" style="font-size:13px; color:#6c757d;"></div>
                <div id="bonsaiPaginationContainer"></div>
            </div>

            <script src="../../assets/js/bonsai_pagination.js?v=<?php echo time(); ?>"></script>
            <script>
            (function() {
                const currentUserId = <?php echo intval($_SESSION['user_id'] ?? 0); ?>;
                const baseUrl = '../../api/search_elasticsearch.php';

                const competencyLabels = {
                    'pengawas_operasional': 'Operational Supervisor',
                    'pengawas_teknis': 'Technical Supervisor',
                    'tenaga_teknis': 'Technical Personnel'
                };
                const statusColors = {
                    'verified': 'success',
                    'pending': 'warning',
                    'rejected': 'danger',
                    'draft': 'secondary'
                };

                window.empPagination = new BonsaiPagination({
                    apiUrl: baseUrl,
                    target: 'employees',
                    tableSelector: '#employeesTable',
                    tbodySelector: '#empTbody',
                    searchInputSelector: '#esSearchInput',
                    clearBtnSelector: '#esClearBtn',
                    paginationContainerSelector: '#bonsaiPaginationContainer',
                    infoContainerSelector: '#bonsaiInfoContainer',
                    limitSelector: '#bonsaiPageLimit',
                    filterSelectors: {
                        company: '#filterCompany',
                        competency_type: '#filterCompetencyType',
                        status: '#filterStatus'
                    },
                    defaultLimit: 10,
                    renderRow: function(item, index, rowNum) {
                        const status = item.approval_status || 'pending';
                        const statusClass = statusColors[status] || 'secondary';
                        const compType = item.competency_type || '';
                        const compLabel = competencyLabels[compType] || compType;
                        function formatVerifiedDateTime(dateStr) {
                            if (!dateStr) return '';
                            const d = new Date(dateStr);
                            if (isNaN(d.getTime())) return dateStr;
                            const day = String(d.getDate()).padStart(2, '0');
                            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            const month = months[d.getMonth()];
                            const year = d.getFullYear();
                            const hours = String(d.getHours()).padStart(2, '0');
                            const mins = String(d.getMinutes()).padStart(2, '0');
                            return `${day} ${month} ${year}, ${hours}:${mins} WIB`;
                        }

                        let verifiedBy = '<span class="text-muted">-</span>';
                        if (status.toLowerCase() !== 'pending' && item.verified_by_name) {
                            const dateFormatted = formatVerifiedDateTime(item.verified_date);
                            verifiedBy = `<strong>${item.verified_by_name}</strong>${dateFormatted ? `<br><small class="text-muted">${dateFormatted}</small>` : ''}`;
                        }

                        const resubmitBtn = (status.toLowerCase() === 'rejected')
                            ? `<a href="resubmit_employee.php?id=${escapeHtml(item.id)}" class="btn-action-emp resubmit-btn" title="Resubmit"><i class="fas fa-upload"></i></a>`
                            : '';
                        const compVal = item.competency_name || item.sub_competency || '';
                        const compDisplay = compVal ? `<span class="competency-tag">${escapeHtml(compVal)}</span>` : '<span class="text-muted">-</span>';
                        return `<tr class="emp-row" data-id="${escapeHtml(item.id)}">
                            <td class="col-code"><span class="code-badge">${escapeHtml(item.employee_code || '-')}</span></td>
                            <td class="col-name"><strong>${escapeHtml(item.full_name || '-')}</strong></td>
                            <td class="col-position"><span class="position-tag-emp">${escapeHtml(item.position || '-')}</span></td>
                            <td class="col-company"><span class="company-tag-emp">${escapeHtml(item.contractor_company || '-')}</span></td>
                            <td class="col-competency-type"><span class="competency-type-badge competency-${compType}">${compLabel}</span></td>
                            <td class="col-competency">${compDisplay}</td>
                            <td class="col-status"><span class="badge-status badge-${statusClass}">${status.toUpperCase()}</span></td>
                            <td class="col-verified-by">${verifiedBy}</td>
                            <td class="col-action">
                                <div class="action-buttons-emp">
                                    ${status.toLowerCase() === 'draft' ? 
                                        `<a href="edit_employee.php?id=${item.id}" class="btn-action-emp edit-btn" title="Edit Draft"><i class="fas fa-edit"></i></a>` :
                                        `<a href="verify_employee.php?id=${item.id}" class="btn-action-emp open-btn" title="Open"><i class="fas fa-folder-open"></i></a>`
                                    }
                                    ${resubmitBtn}
                                </div>
                            </td>
                        </tr>`;
                    }
                });

                // Also sync info to table-level container
                const origRenderInfo = window.empPagination.renderInfo.bind(window.empPagination);
                window.empPagination.renderInfo = function() {
                    origRenderInfo();
                    const src = document.querySelector('#bonsaiInfoContainer');
                    const dest = document.querySelector('#bonsaiInfoContainerTable');
                    if (src && dest) dest.innerHTML = src.innerHTML;
                };
            })();
            </script>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content modal-large-emp">
        <div class="modal-header modal-header-emp">
            <h3><i class="fas fa-plus-circle"></i> <span data-lang="add-contractor-workforce">Add Contractor Workforce Data</span></h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
            <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <h4 class="section-title-modal" data-lang="identity-competency-data">Identity & Competency Data</h4>
                <div class="form-row-modal">
                    <div class="form-group-modal">
                        <label><span data-lang="id-badge">ID BADGE</span> <span class="text-danger">*</span></label>
                        <input type="text" name="employee_code" class="form-control-modal" required placeholder="Example: BADGE001" data-lang="example-badge" value="<?php echo isset($_POST['employee_code']) ? htmlspecialchars($_POST['employee_code']) : ''; ?>">
                        <small class="form-hint" data-lang="unique-id-badge">Unique ID for employee badge identification</small>
                    </div>
                    <div class="form-group-modal">
                        <label><span data-lang="full-name">Full Name</span> <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control-modal" required placeholder="Employee full name" data-lang="employee-full-name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group-modal full-width">
                    <label><span data-lang="position">Position</span></label>
                    <input type="text" name="position" class="form-control-modal" required placeholder="Example: Rigger, HSE Superintendent" data-lang="example-position" value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>">
                </div>

                <input type="hidden" name="department" value="General">

                <div class="form-group-modal full-width">
                    <label><span data-lang="competency-type">Competency Type</span></label>
                    <select name="competency_type" class="form-control-modal" id="addCompetencyType" onchange="toggleCompetencyField()" required>
                        <option value="" data-lang="select-competency-type">-- Select Competency Type --</option>
                        <option value="pengawas_operasional" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'pengawas_operasional') ? 'selected' : ''; ?>>Pengawas Operasional</option>
                        <option value="pengawas_teknis" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'pengawas_teknis') ? 'selected' : ''; ?>>Pengawas Teknis</option>
                        <option value="tenaga_teknis" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'tenaga_teknis') ? 'selected' : ''; ?>>Tenaga Teknis</option>
                    </select>
                </div>

                <div class="form-group-modal full-width" id="ruang_lingkup_group" style="display: none;">
                    <label id="scopeLabelEmp"><span data-lang="scope-of-work">Scope of Work</span> <span class="text-danger">*</span></label>
                    <select name="ruang_lingkup" id="scopeSelectEmp" class="form-control-modal">
                        <option value="" data-lang="select-scope-of-work">-- Select Scope of Work --</option>
                        <option value="PT Meares Soputan Mining (MSM)" <?php echo (isset($_POST['ruang_lingkup']) && $_POST['ruang_lingkup'] == 'PT Meares Soputan Mining (MSM)') ? 'selected' : ''; ?>>PT MSM</option>
                        <option value="PT Tambang Tondano Nusajaya (TTN)" <?php echo (isset($_POST['ruang_lingkup']) && $_POST['ruang_lingkup'] == 'PT Tambang Tondano Nusajaya (TTN)') ? 'selected' : ''; ?>>PT TTN</option>
                    </select>
                </div>

                <div class="form-group-modal full-width" id="competency_group" style="display: none;">
                    <label><span data-lang="competency">Competency</span></label>
                    <select name="competency_name" class="form-control-modal" id="addCompetencyName" onchange="loadSubCompetencies()">
                        <option value="" data-lang="select-competency">-- Select Competency --</option>
                        <?php
                        // Populate competencies for pengawas_operasional
                        if (!empty($competencies_by_type['pengawas_operasional'])) {
                            foreach ($competencies_by_type['pengawas_operasional'] as $comp):
                        ?>
                            <option value="<?php echo htmlspecialchars($comp['competency_name']); ?>" data-id="<?php echo $comp['id']; ?>" data-type="pengawas_operasional" <?php echo (isset($_POST['competency_name']) && $_POST['competency_name'] == $comp['competency_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($comp['competency_name']); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        // Populate competencies for pengawas_teknis
                        if (!empty($competencies_by_type['pengawas_teknis'])) {
                            foreach ($competencies_by_type['pengawas_teknis'] as $comp):
                        ?>
                            <option value="<?php echo htmlspecialchars($comp['competency_name']); ?>" data-id="<?php echo $comp['id']; ?>" data-type="pengawas_teknis" <?php echo (isset($_POST['competency_name']) && $_POST['competency_name'] == $comp['competency_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($comp['competency_name']); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        // Populate competencies for tenaga_teknis
                        if (!empty($competencies_by_type['tenaga_teknis'])) {
                            foreach ($competencies_by_type['tenaga_teknis'] as $comp):
                        ?>
                            <option value="<?php echo htmlspecialchars($comp['competency_name']); ?>" data-id="<?php echo $comp['id']; ?>" data-type="tenaga_teknis" <?php echo (isset($_POST['competency_name']) && $_POST['competency_name'] == $comp['competency_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($comp['competency_name']); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group-modal full-width" id="sub_competency_group" style="display: none;">
                    <label><span data-lang="sub-competency">Sub Competency</span> <span class="text-danger">*</span></label>
                    <select name="sub_competency" class="form-control-modal" id="addSubCompetency">
                        <option value="">-- Pilih Sub Competency --</option>
                    </select>
                </div>

                <div class="form-group-modal full-width" id="supervision_area_group" style="display: none;">
                    <label>Supervision Area <span class="text-danger">*</span></label>
                    <select name="supervision_area" class="form-control-modal" id="addSupervisionArea">
                        <option value="">-- Select Supervision Area --</option>
                        <?php
                        if ($supervision_areas && $supervision_areas->num_rows > 0) {
                            $supervision_areas->data_seek(0);
                            while ($area = $supervision_areas->fetch_assoc()):
                                $selected = (isset($_POST['supervision_area']) && $_POST['supervision_area'] == $area['area_name']) ? 'selected' : '';
                        ?>
                        <option value="<?php echo htmlspecialchars($area['area_name']); ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($area['area_name']); ?>
                        </option>
                        <?php
                            endwhile;
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group-modal full-width">
                    <label>Company</label>
                    <select name="contractor_company" class="form-control-modal" id="contractorCompanyEmp" required>
                        <option value="">-- Select Company --</option>

                            <optgroup label="INTERNAL COMPANIES">
                                <option value="PT Meares Soputan Mining" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Meares Soputan Mining') ? 'selected' : ''; ?>>PT Meares Soputan Mining</option>
                                <option value="PT Tambang Tondano Nusajaya" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Tambang Tondano Nusajaya') ? 'selected' : ''; ?>>PT Tambang Tondano Nusajaya</option>
                            </optgroup>

                            <optgroup label="CONTRACTOR COMPANIES (EXTERNAL)">
                                <option value="G4S Security Services" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'G4S Security Services') ? 'selected' : ''; ?>>G4S Security Services</option>
                                <option value="PT Part Sentra Indomandiri" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Part Sentra Indomandiri') ? 'selected' : ''; ?>>PT Part Sentra Indomandiri</option>
                                <option value="PT Aneka Kimia Raya Corporindo" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Aneka Kimia Raya Corporindo') ? 'selected' : ''; ?>>PT Aneka Kimia Raya Corporindo</option>
                                <option value="PT Saribuana Manado" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Saribuana Manado') ? 'selected' : ''; ?>>PT Saribuana Manado</option>
                                <option value="PT Maxidrill Indonesia" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Maxidrill Indonesia') ? 'selected' : ''; ?>>PT Maxidrill Indonesia</option>
                                <option value="PT Tata Wisata" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Tata Wisata') ? 'selected' : ''; ?>>PT Tata Wisata</option>
                                <option value="PT Arlie Labora Utama" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Arlie Labora Utama') ? 'selected' : ''; ?>>PT Arlie Labora Utama</option>
                                <option value="PT Tou Maesa Sejahtera" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Tou Maesa Sejahtera') ? 'selected' : ''; ?>>PT Tou Maesa Sejahtera</option>
                                <option value="PT DNX Indonesia" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT DNX Indonesia') ? 'selected' : ''; ?>>PT DNX Indonesia</option>
                                <option value="PT Mandara Fasilitas Indonesia" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Mandara Fasilitas Indonesia') ? 'selected' : ''; ?>>PT Mandara Fasilitas Indonesia</option>
                                <option value="PT Aptekindo Mitra Solusitama" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Aptekindo Mitra Solusitama') ? 'selected' : ''; ?>>PT Aptekindo Mitra Solusitama</option>
                                <option value="PT Geopersada Mulai Abadi" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Geopersada Mulai Abadi') ? 'selected' : ''; ?>>PT Geopersada Mulai Abadi</option>
                                <option value="PT Hidup Baru Sukses Mandiri" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Hidup Baru Sukses Mandiri') ? 'selected' : ''; ?>>PT Hidup Baru Sukses Mandiri</option>
                                <option value="PT Intertek Utama Services" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Intertek Utama Services') ? 'selected' : ''; ?>>PT Intertek Utama Services</option>
                                <option value="PT Macmahon Indonesia" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Macmahon Indonesia') ? 'selected' : ''; ?>>PT Macmahon Indonesia</option>
                                <option value="PT Manado Karya Angrah" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Manado Karya Angrah') ? 'selected' : ''; ?>>PT Manado Karya Angrah</option>
                                <option value="PT Samudera Mulia Abadi" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Samudera Mulia Abadi') ? 'selected' : ''; ?>>PT Samudera Mulia Abadi</option>
                            </optgroup>
                    </select>
                </div>

                <div class="form-row-modal">
                    <div class="form-group-modal">
                        <label>Upload CV <span class="text-danger">*</span></label>
                        <div class="file-upload-modal">
                            <i class="fas fa-file-upload"></i>
                            <input type="file" name="cv_file" class="file-input-modal" accept=".pdf,.doc,.docx" required>
                            <span class="file-text">Click or drag your CV file</span>
                            <span class="file-name"></span>
                        </div>
                    </div>
                    <div class="form-group-modal">
                        <label>Upload Statement Letter <span class="text-danger">*</span></label>
                        <div class="file-upload-modal">
                            <i class="fas fa-file-contract"></i>
                            <input type="file" name="statement_file" class="file-input-modal" accept=".pdf,.doc,.docx" required>
                            <span class="file-text">Click or drag statement file</span>
                            <span class="file-name"></span>
                        </div>
                    </div>
                </div>
                
                <hr style="margin: 25px 0;">
                <h4 class="section-title-modal">Certification/Competency</h4>
                <div id="certificationContainer">
                    <div class="certification-item">
                        <div class="cert-item-header">
                            <h5><i class="fas fa-file-certificate"></i> Certification #1</h5>
                            <div class="cert-header-actions">
                                </div>
                        </div>

                        <div class="form-row-modal">
                            <div class="form-group-modal">
                                <label>Certification Name <span class="text-danger">*</span></label>
                                <select name="certification_ids[]" class="form-control-modal cert-name-select" required onchange="updateIssuer(this)">
                                    <option value="">-- Select Certification --</option>
                                    <?php
                                    if ($certifications && $certifications->num_rows > 0) {
                                        $certifications->data_seek(0);
                                        while ($cert = $certifications->fetch_assoc()):
                                        ?>
                                        <option value="<?php echo $cert['id']; ?>" data-issuer="<?php echo htmlspecialchars($cert['cert_issuer'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($cert['cert_name']); ?>
                                        </option>
                                        <?php
                                        endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group-modal">
                                <label>Certificate Type <span class="text-danger">*</span></label>
                                <select name="cert_types[]" class="form-control-modal cert-type-select" required onchange="toggleOtherType(this)">
                                    <option value="">-- Select Type --</option>
                                    <option value="Attendance/Peserta">Attendance/Peserta</option>
                                    <option value="Kompeten">Competent</option>
                                    <option value="Training">Training</option>
                                </select>
                            </div>
                            <div class="form-group-modal other-type-input" style="display: none;">
                                <label>Other Type <span class="text-danger">*</span></label>
                                <input type="text" name="cert_types_other[]" class="form-control-modal" placeholder="Enter certificate type" data-lang-placeholder="enter-certificate-type">
                            </div>
                        </div>

                        <div class="form-row-modal">
                            <div class="form-group-modal">
                                <label>Certificate No. <span class="text-danger">*</span></label>
                                <input type="text" name="cert_numbers[]" class="form-control-modal" required placeholder="Certificate number" data-lang-placeholder="certificate-number-placeholder">
                            </div>
                            <div class="form-group-modal">
                                <label>Issuer <span class="text-danger">*</span></label>
                                <input type="text" name="cert_issuers[]" class="form-control-modal cert-issuer" required placeholder="Issuer/certification body name" data-lang-placeholder="issuer-certification-body-name">
                            </div>
                        </div>
                        
                        <div class="form-row-modal">
                            <div class="form-group-modal">
                                <label>Issue Date <span class="text-danger">*</span></label>
                                <input type="date" name="issue_dates[]" class="form-control-modal issue-date" required onchange="calculateExpiryDate(this)">
                            </div>
                            <div class="form-group-modal">
                                <label>Validity Period <span class="text-danger">*</span></label>
                                <div class="validity-input-group">
                                    <input type="number" name="validity_years[]" class="form-control-modal validity-years" min="0" step="0.5" placeholder="Years" data-lang-placeholder="years" onchange="calculateExpiryDate(this)">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="no_expiry[]" class="no-expiry-check" onchange="toggleExpiryField(this)">
                                        <span>No Expiry</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row-modal">
                            <div class="form-group-modal">
                                <label>Expiry Date <span class="text-danger">*</span></label>
                                <input type="date" name="expiry_dates[]" class="form-control-modal expiry-date" readonly>
                            </div>
                            <div class="form-group-modal">
                            </div>
                        </div>
                        
                        <div class="form-group-modal other-expiry-reason" style="display: none;">
                            <label>Notes <span class="text-danger">*</span></label>
                            <textarea name="expiry_reasons[]" class="form-control-modal" placeholder="Explain the reason..." data-lang-placeholder="explain-the-reason" rows="2"></textarea>
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Upload Certificate File <span class="text-danger">*</span></label>
                            <div class="file-upload-modal">
                                <i class="fas fa-file-pdf"></i>
                                <input type="file" name="certifications[]" class="file-input-modal" accept=".pdf" required>
                                <span class="file-text">Click or drag certificate file (PDF, Max 5MB)</span>
                                <span class="file-name"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-outline-primary-modal" onclick="addCertification()">
                    <i class="fas fa-plus-circle"></i> Add Another Certification
                </button>
            </div>
            <div class="modal-footer-modal">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save & Submit for Verification</button>
            </div>
        </form>
    </div>
</div>

<script>
const competenciesData = <?php echo json_encode($competencies_by_type); ?>;
const competenciesTableExists = <?php echo json_encode($competencies_table_exists); ?>;
const certificationsData = <?php echo json_encode($certifications_data); ?>;
const positionsData = <?php echo json_encode($positions_by_type); ?>;
const REQUIRES_COMPETENCY = ['pengawas_teknis', 'tenaga_teknis'];

// Store POST values for restoring after error
const postSubCompetency = '<?php echo isset($_POST['sub_competency']) ? addslashes($_POST['sub_competency']) : ''; ?>';
const hasPostError = <?php echo (!empty($error) && isset($_POST['action']) && $_POST['action'] == 'add') ? 'true' : 'false'; ?>;

function updateCompanyType() {
    // No action needed - removed department population logic
}

function updateScopeOptions() {
    // No action needed
}

function toggleCompetencyField() {
    const competencyType = document.getElementById('addCompetencyType').value;
    const supervisionAreaGroup = document.getElementById('supervision_area_group');
    const competencyGroup = document.getElementById('competency_group');
    const subCompetencyGroup = document.getElementById('sub_competency_group');
    const ruangLingkupGroup = document.getElementById('ruang_lingkup_group');
    const competencyInput = document.getElementById('addCompetencyName');
    const supervisionAreaInput = document.getElementById('addSupervisionArea');
    const subCompetencyInput = document.getElementById('addSubCompetency');
    const ruangLingkupInput = document.getElementById('scopeSelectEmp');

    // Reset required attributes
    competencyInput.removeAttribute('required');
    supervisionAreaInput.removeAttribute('required');
    subCompetencyInput.removeAttribute('required');
    ruangLingkupInput.removeAttribute('required');

    if (competencyType === 'pengawas_operasional') {
        ruangLingkupGroup.style.display = 'block';
        ruangLingkupInput.setAttribute('required', 'required');
        // Tampilkan kedua field untuk pengawas operasional
        competencyGroup.style.display = 'block';
        supervisionAreaGroup.style.display = 'block';
        subCompetencyGroup.style.display = 'none';

        // Kedua field wajib diisi
        competencyInput.setAttribute('required', 'required');
        supervisionAreaInput.setAttribute('required', 'required');

        // Filter kompetensi
        filterCompetencies('pengawas_operasional');
    } else if (competencyType === 'pengawas_teknis') {
        // Pengawas Teknis: show ruang_lingkup and competency, hide supervision_area
        ruangLingkupGroup.style.display = 'block';
        ruangLingkupInput.setAttribute('required', 'required');
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'block';
        subCompetencyGroup.style.display = 'none';
        competencyInput.setAttribute('required', 'required');
        supervisionAreaInput.removeAttribute('required');
        filterCompetencies(competencyType);
    } else if (competencyType === 'tenaga_teknis') {
        // Tenaga Teknis: show ruang_lingkup, competency, and sub_competency, hide supervision_area
        ruangLingkupGroup.style.display = 'block';
        ruangLingkupInput.setAttribute('required', 'required');
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'block';
        subCompetencyGroup.style.display = 'block';
        competencyInput.setAttribute('required', 'required');
        subCompetencyInput.setAttribute('required', 'required');
        supervisionAreaInput.removeAttribute('required');
        filterCompetencies(competencyType);
    } else {
        // Sembunyikan semua jika tidak ada tipe dipilih
        ruangLingkupGroup.style.display = 'none';
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'none';
        subCompetencyGroup.style.display = 'none';
    }
}

function filterCompetencies(competencyType) {
    const competencySelect = document.getElementById('addCompetencyName');
    const options = competencySelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
        } else if (option.getAttribute('data-type') === competencyType) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
}

async function loadSubCompetencies() {
    const competencySelect = document.getElementById('addCompetencyName');
    const subCompetencySelect = document.getElementById('addSubCompetency');
    const selectedOption = competencySelect.options[competencySelect.selectedIndex];

    subCompetencySelect.innerHTML = '<option value="">' + (window.getLanguageText('')) + '</option>';

    if (!selectedOption.value) return;

    const competencyId = selectedOption.getAttribute('data-id');
    if (!competencyId) {
        console.warn('Competency ID not found');
        return;
    }

    try {
        const response = await fetch('../../api/get_sub_competencies.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ competency_id: parseInt(competencyId) })
        });

        const result = await response.json();

        if (result.success && result.data.length > 0) {
            result.data.forEach(subComp => {
                const option = document.createElement('option');
                option.value = subComp.name;
                option.textContent = subComp.name;
                option.title = subComp.description || '';
                // Check if this option should be selected from POST
                if (postSubCompetency && subComp.name === postSubCompetency) {
                    option.selected = true;
                }
                subCompetencySelect.appendChild(option);
            });
            console.log('Sub-competencies loaded:', result.data);
        }
    } catch (error) {
        console.error('Error loading sub-competencies:', error);
    }
}

function updateIssuer(selectElement) {
    // Fungsi ini tidak lagi auto-fill issuer dan certificate type
    // User harus input manual untuk issuer dan certificate type
    const certId = selectElement.value;
    const certItem = selectElement.closest('.certification-item');
    const issuerInput = certItem.querySelector('.cert-issuer');
    const certTypeSelect = certItem.querySelector('.cert-type-select');
    const otherTypeInput = certItem.querySelector('.other-type-input');
    
    // Reset fields - user harus input manual
    issuerInput.value = '';
    issuerInput.readOnly = false;
    issuerInput.style.backgroundColor = '#ffffff';
    issuerInput.style.cursor = 'auto';
    
    if (certTypeSelect) {
        certTypeSelect.value = '';
        certTypeSelect.disabled = false;
        certTypeSelect.style.backgroundColor = '#ffffff';
        certTypeSelect.style.cursor = 'auto';
    }
    
    if (otherTypeInput) {
        otherTypeInput.style.display = 'none';
    }
}

function toggleOtherType(selectElement) {
    const certItem = selectElement.closest('.certification-item');
    const otherTypeInput = certItem.querySelector('.other-type-input');
    
    if (selectElement.value === 'Lainnya') {
        otherTypeInput.style.display = 'block';
    } else {
        otherTypeInput.style.display = 'none';
    }
}

function toggleExpiryField(checkboxElement) {
    const certItem = checkboxElement.closest('.certification-item');
    const expiryDateInput = certItem.querySelector('input[name="expiry_dates[]"]');
    const validityYearsInput = certItem.querySelector('input[name="validity_years[]"]');
    const otherExpiryReason = certItem.querySelector('.other-expiry-reason');
    
    if (checkboxElement.checked) {
        expiryDateInput.value = '';
        expiryDateInput.readOnly = true;
        validityYearsInput.value = '';
        validityYearsInput.disabled = true;
        otherExpiryReason.style.display = 'block';
    } else {
        expiryDateInput.readOnly = true;  // Still readonly, calculated from other fields
        validityYearsInput.disabled = false;
        otherExpiryReason.style.display = 'none';
    }
}

function calculateExpiryDate(inputElement) {
    const certItem = inputElement.closest('.certification-item');
    const issueDateInput = certItem.querySelector('input[name="issue_dates[]"]');
    const validityYearsInput = certItem.querySelector('input[name="validity_years[]"]');
    const expiryDateInput = certItem.querySelector('input[name="expiry_dates[]"]');
    const noExpiryCheck = certItem.querySelector('input[name="no_expiry[]"]');
    
    if (noExpiryCheck.checked) {
        return;
    }
    
    const issueDate = issueDateInput.value;
    const validityYears = parseFloat(validityYearsInput.value) || 0;
    
    if (issueDate && validityYears > 0) {
        const date = new Date(issueDate);
        date.setFullYear(date.getFullYear() + Math.floor(validityYears));
        date.setMonth(date.getMonth() + Math.round((validityYears % 1) * 12));
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        
        expiryDateInput.value = `${year}-${month}-${day}`;
    }
}

function addCertification() {
    const container = document.getElementById('certificationContainer');
    const certItems = container.querySelectorAll('.certification-item');
    const newIndex = certItems.length + 1;

    const newItem = document.createElement('div');
    newItem.className = 'certification-item';
    newItem.innerHTML = `
        <div class="cert-item-header">
            <h5><i class="fas fa-file-certificate"></i> Certification #${newIndex}</h5>
            <div class="cert-header-actions">
                <span class="badge badge-info">NEW</span>
                <button type="button" class="btn-remove-cert" onclick="removeCertification(this)" title="Remove this certification" data-lang-title="remove-this-certification">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="form-row-modal">
            <div class="form-group-modal">
                <label>Certification Name <span class="text-danger">*</span></label>
                <select name="certification_ids[]" class="form-control-modal cert-name-select" required onchange="updateIssuer(this)">
                    <option value="">-- Select Certification --</option>
                    ${getCertificationOptions()}
                </select>
            </div>
            <div class="form-group-modal">
                <label>Certificate Type <span class="text-danger">*</span></label>
                <select name="cert_types[]" class="form-control-modal cert-type-select" required onchange="toggleOtherType(this)">
                    <option value="">-- Select Type --</option>
                    <option value="Attendance/Peserta">Attendance/Peserta</option>
                    <option value="Kompeten">Competent</option>
                    <option value="Training">Training</option>
                </select>
            </div>
            <div class="form-group-modal other-type-input" style="display: none;">
                <label>Other Type <span class="text-danger">*</span></label>
                <input type="text" name="cert_types_other[]" class="form-control-modal" placeholder="Enter certificate type" data-lang-placeholder="enter-certificate-type">
            </div>
        </div>

        <div class="form-row-modal">
            <div class="form-group-modal">
                <label>Certificate No. <span class="text-danger">*</span></label>
                <input type="text" name="cert_numbers[]" class="form-control-modal" required placeholder="Certificate number" data-lang-placeholder="certificate-number-placeholder">
            </div>
            <div class="form-group-modal">
                <label>Issuer <span class="text-danger">*</span></label>
                <input type="text" name="cert_issuers[]" class="form-control-modal cert-issuer" required placeholder="Issuer/certification body name" data-lang-placeholder="issuer-certification-body-name">
            </div>
        </div>

        <div class="form-row-modal">
            <div class="form-group-modal">
                <label>Issue Date <span class="text-danger">*</span></label>
                <input type="date" name="issue_dates[]" class="form-control-modal issue-date" required onchange="calculateExpiryDate(this)">
            </div>
            <div class="form-group-modal">
                <label>Validity Period <span class="text-danger">*</span></label>
                <div class="validity-input-group">
                    <input type="number" name="validity_years[]" class="form-control-modal validity-years" min="0" step="0.5" placeholder="Years" data-lang-placeholder="years" onchange="calculateExpiryDate(this)">
                    <label class="checkbox-label">
                        <input type="checkbox" name="no_expiry[]" class="no-expiry-check" onchange="toggleExpiryField(this)">
                        <span>No Expiry</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-row-modal">
            <div class="form-group-modal">
                <label>Expiry Date <span class="text-danger">*</span></label>
                <input type="date" name="expiry_dates[]" class="form-control-modal expiry-date" readonly>
            </div>
            <div class="form-group-modal">
            </div>
        </div>

        <div class="form-group-modal other-expiry-reason" style="display: none;">
            <label>Notes <span class="text-danger">*</span></label>
            <textarea name="expiry_reasons[]" class="form-control-modal" placeholder="Explain the reason..." data-lang-placeholder="explain-the-reason" rows="2"></textarea>
        </div>

        <div class="form-group-modal">
            <label>Upload Certificate File <span class="text-danger">*</span></label>
            <div class="file-upload-modal">
                <i class="fas fa-file-pdf"></i>
                <input type="file" name="certifications[]" class="file-input-modal" accept=".pdf" required>
                <span class="file-text">Click or drag certificate file (PDF, Max 5MB)</span>
                <span class="file-name"></span>
            </div>
        </div>
    `;

    container.appendChild(newItem);

    // Setup file upload for the new item
    const fileUploadArea = newItem.querySelector('.file-upload-modal');
    setupFileUpload(fileUploadArea);

    // Update certification numbers
    updateCertificationNumbers();
}

function removeCertification(button) {
    const certItem = button.closest('.certification-item');
    const container = document.getElementById('certificationContainer');
    const certItems = container.querySelectorAll('.certification-item');

    // Don't remove if it's the only one
    if (certItems.length <= 1) {
        alert(window.getLanguageText(''));
        return;
    }

    // Confirm before removing
    if (confirm(window.getLanguageText(''))) {
        certItem.remove();
        updateCertificationNumbers();
    }
}

function updateCertificationNumbers() {
    const container = document.getElementById('certificationContainer');
    const certItems = container.querySelectorAll('.certification-item');

    certItems.forEach((item, index) => {
        const header = item.querySelector('.cert-item-header h5');
        header.innerHTML = `<i class="fas fa-file-certificate"></i> Certification #${index + 1}`;

        // Show/hide remove button based on index
        const actionsDiv = item.querySelector('.cert-header-actions');
        if (index === 0) {
            // First item - hide remove button
            actionsDiv.innerHTML = '';
        } else if (!actionsDiv.querySelector('.btn-remove-cert')) {
            // Not first item and no remove button - add it
            const badge = actionsDiv.querySelector('.badge');
            const badgeHTML = badge ? `<span class="${badge.className}">${badge.textContent}</span>` : '';
            actionsDiv.innerHTML = `
                ${badgeHTML}
                <button type="button" class="btn-remove-cert" onclick="removeCertification(this)" title="Remove this certification" data-lang-title="remove-this-certification">
                    <i class="fas fa-times"></i>
                </button>
            `;
        }
    });
}

function getCertificationOptions() {
    let options = '';
    for (const id in certificationsData) {
        const cert = certificationsData[id];
        const certName = cert.cert_name || cert;
        const certIssuer = cert.cert_issuer || '';
        options += `<option value="${id}" data-issuer="${certIssuer}">${certName}</option>`;
    }
    return options;
}

function setupFileUpload(area) {
    area.addEventListener('dragover', (e) => {
        e.preventDefault();
        area.classList.add('dragover');
    });
    area.addEventListener('dragleave', () => {
        area.classList.remove('dragover');
    });
    area.addEventListener('drop', (e) => {
        e.preventDefault();
        area.classList.remove('dragover');
        const input = area.querySelector('.file-input-modal');
        input.files = e.dataTransfer.files;
        updateFileName(area, input.files[0]);
    });
    
    const input = area.querySelector('.file-input-modal');
    input.addEventListener('change', () => {
        updateFileName(area, input.files[0]);
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Setup initial certification item
    const initialCertItem = document.querySelector('.certification-item');
    if (initialCertItem) {
        const certTypeSelect = initialCertItem.querySelector('.cert-type-select');
        if (certTypeSelect) {
            certTypeSelect.onchange = function() { toggleOtherType(this); };
        }
        
        const certNameSelect = initialCertItem.querySelector('.cert-name-select');
        if (certNameSelect) {
            certNameSelect.onchange = function() { updateIssuer(this); };
            if (certNameSelect.value) {
                updateIssuer(certNameSelect);
            }
        }
        
        const issueDate = initialCertItem.querySelector('.issue-date');
        if (issueDate) {
            issueDate.onchange = function() { calculateExpiryDate(this); };
        }
        
        const validityYears = initialCertItem.querySelector('.validity-years');
        if (validityYears) {
            validityYears.onchange = function() { calculateExpiryDate(this); };
        }
        
        const noExpiryCheck = initialCertItem.querySelector('.no-expiry-check');
        if (noExpiryCheck) {
            noExpiryCheck.onchange = function() { toggleExpiryField(this); };
        }
    }
    
    // Setup file upload areas
    document.querySelectorAll('.file-upload-modal').forEach(area => {
        setupFileUpload(area);
    });

    // Trigger toggleCompetencyField if competency_type has a value on page load
    const competencyTypeSelect = document.getElementById('addCompetencyType');
    if (competencyTypeSelect && competencyTypeSelect.value) {
        toggleCompetencyField();
        // Load sub-competencies if competency_name is already selected (after POST error)
        const competencyNameSelect = document.getElementById('addCompetencyName');
        if (competencyNameSelect && competencyNameSelect.value) {
            loadSubCompetencies();
        }
    }

    // Open modal automatically if there was a POST error
    if (hasPostError) {
        openModal('addModal');
    }
});

function updateFileName(area, file) {
    if (file) {
        area.querySelector('.file-name').textContent = file.name;
        area.querySelector('.file-name').style.display = 'block';
    }
}
</script>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
