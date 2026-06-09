<?php
$page_title = 'Contractor Workforce Data';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/notifications.php';

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
                $upload_dir = '../../assets/uploads/cv/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $cv_file = 'cv_' . $employee_code . '_' . time() . '.' . $file_ext;
                
                if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_dir . $cv_file)) {
                    $cv_file = 'uploads/cv/' . $cv_file;
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
                    $statement_upload_dir = '../../assets/uploads/statements/';
                    if (!file_exists($statement_upload_dir)) {
                        mkdir($statement_upload_dir, 0777, true);
                    }
                    $statement_file = 'statement_' . $employee_code . '_' . time() . '.' . $statement_ext;
                    if (!move_uploaded_file($_FILES['statement_file']['tmp_name'], $statement_upload_dir . $statement_file)) {
                        $error = stela_t('failed-upload-statement-file');
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
                
                // Handle multiple certification uploads
                if (isset($_FILES['certifications']) && !empty($_FILES['certifications']['name'][0])) {
                    $upload_dir = '../../assets/uploads/certifications/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
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
                                $cert_path = 'uploads/certifications/' . $cert_file;
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

// Get filter from URL
$filter = isset($_GET['filter']) ? $db->escapeString($_GET['filter']) : '';

// Build WHERE clause for filter
$where_clause = "e.is_active = 1";
if (!empty($filter)) {
    $where_clause .= " AND e.verification_status = '$filter'";
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
               ELSE e.verification_status
           END as combined_status
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    LEFT JOIN appointments a ON e.id = a.employee_id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id
    WHERE $where_clause
    GROUP BY e.id
    ORDER BY e.verification_status, e.created_at DESC
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

require_once '../../includes/header.php';

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
                    'rejected' => 'Rejected'
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
    </div>
    
    <!-- Employees Table -->
    <div class="card-emp">
        <div class="card-header-emp">
            <h3><i class="fas fa-list"></i> <span data-lang="complete-workforce-list">Complete Workforce List</span></h3>
        </div>

        <div class="card-body-emp">
            <?php if ($employees->num_rows > 0): ?>
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
                        <tbody>
                            <?php 
                            $employees->data_seek(0);
                            while ($row = $employees->fetch_assoc()): 
                                $company_name = htmlspecialchars($row['contractor_company']);
                            ?>
                            <tr class="emp-row" data-company="<?php echo $company_name; ?>" data-status="<?php echo htmlspecialchars($row['verification_status']); ?>">
                                <td class="col-code">
                                    <span class="code-badge"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                                </td>
                                <td class="col-name">
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                </td>
                                <td class="col-position">
                                    <span class="position-tag-emp"><?php echo htmlspecialchars($row['position']); ?></span>
                                </td>
                                <td class="col-company">
                                    <span class="company-tag-emp"><?php echo $company_name; ?></span>
                                </td>
                                <td class="col-competency-type">
                                    <span class="competency-type-badge competency-<?php echo $row['competency_type']; ?>">
                                        <?php echo htmlspecialchars($row['competency_type_display'] ?? ''); ?>
                                    </span>
                                </td>
                                <td class="col-competency">
                                    <?php if (!empty($row['competency_name'])): ?>
                                        <span class="competency-tag"><?php echo htmlspecialchars($row['competency_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-status">
                                    <?php
                                    $final_status = $row['combined_status'] ?? $row['verification_status'];
                                    $final_class = $row['status_class'];
                                    if ($final_status == 'rejected') $final_class = 'danger';
                                    ?>
                                    <span class="badge-status badge-<?php echo $final_class; ?>">
                                        <?php echo strtoupper($final_status); ?>
                                    </span>
                                    <?php if ($final_status == 'rejected' && !empty($row['ktt_rejection_notes'])): ?>
                                    <br><small class="text-muted">Ditolak oleh KTT</small>
                                    <?php endif; ?>
                                </td>
                                <td class="col-verified-by">
                                    <?php 
                                    if (($row['verification_status'] == 'verified' || $row['verification_status'] == 'rejected') && $row['verified_by_name']) {
                                        echo '<strong>' . htmlspecialchars($row['verified_by_name']) . '</strong>';
                                        if ($row['verified_date']) {
                                            echo '<br><small class="text-muted">' . date('d/m/Y', strtotime($row['verified_date'])) . '</small>';
                                        }
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td class="col-action">
                                    <div class="action-buttons-emp">
                                        <a href="verify_employee.php?id=<?php echo $row['id']; ?>" class="btn-action-emp open-btn" title="Open" data-lang-title="open">
                                            <i class="fas fa-folder-open"></i>
                                        </a>
                                        <?php if ($final_status == 'rejected' && isset($row['created_by']) && $row['created_by'] == $_SESSION['user_id']): ?>
                                        <a href="resubmit_employee.php?id=<?php echo $row['id']; ?>" class="btn-action-emp resubmit-btn" title="Resubmit" data-lang-title="resubmit">
                                            <i class="fas fa-upload"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-emp">
                    <i class="fas fa-inbox"></i>
                    <p data-lang="no-workforce-data">No workforce data yet</p>
                </div>
            <?php endif; ?>
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

                <!-- Department disembunyikan, nilai default diatur otomatis -->
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

                            <!-- Internal Companies -->
                            <optgroup label="INTERNAL COMPANIES">
                                <option value="PT Meares Soputan Mining" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Meares Soputan Mining') ? 'selected' : ''; ?>>PT Meares Soputan Mining</option>
                                <option value="PT Tambang Tondano Nusajaya" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Tambang Tondano Nusajaya') ? 'selected' : ''; ?>>PT Tambang Tondano Nusajaya</option>
                            </optgroup>

                            <!-- Contractor Companies/External -->
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
                                <option value="PT Samudera Mulai Abadi" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Samudera Mulai Abadi') ? 'selected' : ''; ?>>PT Samudera Mulai Abadi</option>
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
                                <!-- Remove button will appear for additional certifications -->
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

<style>
.employees-admin-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-emp-admin {
    background: #F57C00;
    color: white;
    padding: 35px 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(245, 124, 0, 0.3);
}

.header-left h2 {
    margin: 0 0 8px 0;
    font-size: 26px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-left p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.btn-lg-emp {
    padding: 12px 25px;
    font-size: 15px;
    white-space: nowrap;
    background: #37474F;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-lg-emp:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
}

/* Alert Custom */
.alert-custom-emp {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success.alert-custom-emp {
    background: #E8F5E9;
    border-left-color: #2E7D32;
}

.alert-success.alert-custom-emp i {
    color: #2E7D32;
    font-size: 20px;
}

.alert-error.alert-custom-emp {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.alert-error.alert-custom-emp i {
    color: #ef4444;
    font-size: 20px;
}

.alert-info.alert-custom-emp {
    background: #ECEFF1;
    border-left-color: #37474F;
}

.alert-info.alert-custom-emp i {
    color: #37474F;
    font-size: 20px;
}

.alert-custom-emp strong {
    display: block;
    margin-bottom: 5px;
}

/* Stats Grid */
.stats-grid-emp {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-box-emp {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 15px;
    border-left: 4px solid #ccc;
    transition: all 0.3s ease;
}

.stat-box-emp:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
}

.stat-total { border-left-color: #37474F; }
.stat-pending { border-left-color: #f59e0b; }
.stat-verified { border-left-color: #2E7D32; }
.stat-rejected { border-left-color: #ef4444; }

.stat-icon-emp {
    font-size: 28px;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: white;
}


/* Selaraskan warna ikon statistik dengan dashboard dan appointments */
.stat-total .stat-icon-emp {
    background: #37474F;
    color: #fff;
}
.stat-pending .stat-icon-emp {
    background: linear-gradient(135deg, #FFD600, #FFB300); /* Kuning terang */
    color: #F57C00;
}
.stat-verified .stat-icon-emp {
    background: linear-gradient(135deg, #F57C00, #FF9800); /* Orange utama */
    color: #fff;
}
.stat-rejected .stat-icon-emp {
    background: linear-gradient(135deg, #EF5350, #D32F2F); /* Merah */
    color: #fff;
}
.stat-needs-review .stat-icon-emp {
    background: linear-gradient(135deg, #2196F3, #1976D2); /* Biru */
    color: #fff;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #333;
}

.stat-text {
    color: #666;
    font-size: 12px;
}

/* Card */
.card-emp {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.card-header-emp {
    padding: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header-emp h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header-emp i {
    color: #37474F;
}

.card-body-emp {
    padding: 0;
}

/* Table */
.table-emp {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table-emp thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 15px;
}

.emp-row {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.emp-row:hover {
    background-color: #f8f9ff;
}

.table-emp td {
    padding: 15px;
    vertical-align: middle;
    font-size: 13px;
}

.col-code { width: 10%; }
.col-name { width: 15%; }
.col-position { width: 12%; }
.col-company { width: 14%; }
.col-competency-type { width: 13%; }
.col-competency { width: 15%; }
.col-status { width: 9%; }
.col-verified-by { width: 10%; }
.col-action { width: 7%; }

.code-badge {
    background: #ECEFF1;
    color: #37474F;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.position-tag-emp {
    background: #f3f4f6;
    color: #666;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
}

.company-tag-emp {
    background: #fef3c7;
    color: #b45309;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    display: inline-block;
}

.cert-count-emp {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.table-emp thead th.col-position::after,
.table-emp thead th.col-company::after,
.table-emp thead th.col-competency-type::after,
.table-emp thead th.col-competency::after {
    content: none !important;
}
.cert-badge {
    font-weight: 600;
    color: #333;
    font-size: 12px;
}

.cert-progress {
    width: 80px;
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}

.cert-fill {
    height: 100%;
    background: linear-gradient(90deg, #37474F, #37474F);
}

.badge-status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.badge-success {
    background: #E8F5E9;
    color: #2E7D32;
}

.badge-warning {
    background: #fef3c7;
    color: #f59e0b;
}

.badge-danger {
    background: #fee2e2;
    color: #ef4444;
}

.action-buttons-emp {
    display: flex;
    gap: 6px;
}

.btn-action-emp {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    text-decoration: none;
    color: white;
}

.verify-btn {
    background: #2E7D32;
}

.verify-btn:hover {
    background: #1B5E20;
    transform: translateY(-1px);
}

.open-btn {
    background: #37474F;
}

.open-btn:hover {
    background: #263238;
    transform: translateY(-1px);
}

.resubmit-btn {
    background: #f59e0b;
}

.resubmit-btn:hover {
    background: #d97706;
    transform: translateY(-1px);
}

/* Rejected Alert Banner */
.alert-resubmit-emp {
    background: #fef3c7 !important;
    border-left-color: #f59e0b !important;
}

.alert-resubmit-emp i {
    color: #f59e0b !important;
    font-size: 24px !important;
}

.cv-btn {
    background: #37474F;
}

.cv-btn:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

/* Empty State */
.empty-state-emp {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-emp i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state-emp p {
    margin: 0;
    font-size: 16px;
}

/* Modal */
.modal-large-emp {
    max-width: 900px;
}

.modal-header-emp {
    background: #37474F;
    color: white;
    padding: 20px;
    border-radius: 8px 8px 0 0;
}

.modal-header-emp h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header-emp .close {
    color: white;
    opacity: 0.8;
}

.modal-header-emp .close:hover {
    opacity: 1;
}

.modal-body {
    padding: 25px;
    max-height: 70vh;
    overflow-y: auto;
}

.section-title-modal {
    color: #37474F;
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
    font-size: 15px;
    font-weight: 600;
}

.form-row-modal {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group-modal {
    margin-bottom: 0;
}

.form-group-modal.full-width {
    grid-column: 1 / -1;
    margin-bottom: 15px;
}

.form-group-modal label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.form-control-modal {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    transition: border-color 0.3s ease;
    font-family: inherit;
}

.form-control-modal:focus {
    outline: none;
    border-color: #37474F;
}

/* File Upload */
.file-upload-modal {
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.file-upload-modal:hover {
    border-color: #37474F;
    background: #f8f9ff;
}

.file-upload-modal.dragover {
    border-color: #37474F;
    background: #f0f4ff;
}

.file-upload-modal i {
    font-size: 32px;
    color: #37474F;
    margin-bottom: 10px;
    display: block;
}

.file-input-modal {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-text {
    display: block;
    color: #666;
    font-size: 13px;
}

.file-name {
    display: none;
    color: #2E7D32;
    font-weight: 600;
    font-size: 12px;
    margin-top: 10px;
}

.modal-footer-modal {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 15px 25px;
    border-top: 1px solid #e9ecef;
}

.btn-outline-primary-modal {
    border: 2px solid #37474F;
    color: #37474F;
    background: white;
    padding: 10px 16px;
    font-weight: 600;
    margin-bottom: 15px;
}

.btn-outline-primary-modal:hover {
    background: #e8f7fa;
}

/* Filter Section */
.filter-section-emp {
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.filter-group-emp {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 280px;
}

.filter-group-emp label {
    font-weight: 600;
    color: #333;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    font-size: 13px;
}

.filter-select-emp {
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: border-color 0.3s ease;
    min-width: 200px;
    flex: 1;
}

.filter-select-emp:hover,
.filter-select-emp:focus {
    border-color: #37474F;
    outline: none;
}

.table-info-emp {
    padding: 12px 20px;
    background: #ECEFF1;
    color: #37474F;
    border-top: 1px solid #b3e5fc;
    font-size: 12px;
    font-weight: 500;
}

/* Statistics Section */
.stats-section-title {
    margin-top: 30px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #37474F;
}

.stats-section-title h4 {
    margin: 0;
    color: #333;
    font-size: 16px;
    font-weight: 600;
}

.competency-type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.competency-pengawas_operasional {
    background: #ECEFF1;
    color: #37474F;
}

.competency-pengawas_teknis {
    background: #fef3c7;
    color: #b45309;
}

.competency-tenaga_teknis {
    background: #E8F5E9;
    color: #1B5E20;
}

.competency-tag {
    background: #f3f4f6;
    color: #374151;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.badge-status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

/* Certification Item Styles */
.certification-item {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
}

.cert-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.cert-item-header h5 {
    margin: 0;
    color: #333;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}

.cert-item-header i {
    color: #37474F;
}

.cert-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-remove-cert {
    background: #fee2e2;
    color: #dc2626;
    border: 2px solid #fecaca;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-remove-cert:hover {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
    transform: scale(1.05);
}

.btn-remove-cert i {
    color: inherit;
    font-size: 14px;
}

.badge-info {
    background: #dbeafe;
    color: #1d4ed8;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

/* Validity Input Group */
.validity-input-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.validity-input-group input[type="number"] {
    flex: 1;
}

/* Checkbox Label */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    margin: 0;
    cursor: pointer;
    font-weight: normal;
}

.checkbox-label input {
    cursor: pointer;
}

.checkbox-label span {
    font-size: 13px;
    color: #666;
}

/* Other Type Input */
.other-type-input {
    display: none;
}

/* Other Expiry Reason */
.other-expiry-reason {
    display: none;
}

.other-expiry-reason textarea {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    resize: vertical;
}

.other-expiry-reason textarea:focus {
    outline: none;
    border-color: #37474F;
}

/* Form Hint */
.form-hint {
    display: block;
    margin-top: 5px;
    color: #999;
    font-size: 11px;
}

.text-muted {
    color: #999 !important;
}

/* Readonly styling */
input[readonly] {
    background-color: #F9FAFB !important;
    cursor: not-allowed !important;
}

/* Responsive Styles */
@media (max-width: 1200px) {
    .stats-grid-emp {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .col-competency, .col-verified-by {
        display: none;
    }
    
    .table-emp thead th.col-competency,
    .table-emp thead th.col-verified-by {
        display: none;
    }
}

@media (max-width: 992px) {
    .page-header-emp-admin {
        flex-direction: column;
        gap: 20px;
        text-align: center;
        padding: 25px 20px;
    }
    
    .header-left h2 {
        font-size: 22px;
        justify-content: center;
    }
    
    .btn-lg-emp {
        width: 100%;
    }
    
    .modal-large-emp {
        max-width: 95%;
        margin: 2% auto;
    }
    
    .form-row-modal {
        grid-template-columns: 1fr;
    }
    
    .col-competency-type {
        display: none;
    }
    
    .table-emp thead th.col-competency-type {
        display: none;
    }
}

@media (max-width: 768px) {
    .employees-admin-container {
        padding: 15px 0;
    }
    
    .page-header-emp-admin {
        padding: 20px 15px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    
    .header-left h2 {
        font-size: 18px;
    }
    
    .header-left p {
        font-size: 13px;
    }
    
    .stats-grid-emp {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    
    .stat-box-emp {
        padding: 15px;
        gap: 10px;
    }
    
    .stat-icon-emp {
        width: 38px;
        height: 38px;
        font-size: 18px;
        flex-shrink: 0;
    }
    
    .stat-number {
        font-size: 18px;
    }
    
    .stat-text {
        font-size: 11px;
    }
    
    .card-header-emp {
        padding: 15px;
    }
    
    .card-header-emp h3 {
        font-size: 16px;
    }
    
    .filter-section-emp {
        padding: 15px;
        flex-direction: column;
        gap: 15px;
    }
    
    .filter-group-emp {
        flex-direction: column;
        align-items: stretch;
        max-width: 100%;
        min-width: auto;
    }
    
    .filter-select-emp {
        width: 100%;
        min-width: auto;
    }
    
    .table-emp thead th,
    .table-emp td {
        padding: 10px 8px;
        font-size: 11px;
    }
    
    .col-position, .col-company {
        display: none;
    }
    
    .table-emp thead th.col-position,
    .table-emp thead th.col-company {
        display: none;
    }
    
    .code-badge {
        padding: 3px 6px;
        font-size: 10px;
    }
    
    .badge-status {
        padding: 4px 8px;
        font-size: 10px;
    }
    
    .btn-action-emp {
        width: 28px;
        height: 28px;
        font-size: 11px;
    }
    
    .action-buttons-emp {
        gap: 4px;
    }
    
    .btn-action-emp {
        width: 40px;
        height: 40px;
        font-size: 14px;
    }
    
    .modal-body {
        max-height: calc(100vh - 130px);
        padding: 12px;
    }
    
    .modal-header-emp {
        padding: 15px;
    }
    
    .modal-header-emp h3 {
        font-size: 16px;
    }
    
    .section-title-modal {
        font-size: 14px;
    }
    
    .form-control-modal {
        padding: 8px 10px;
        font-size: 13px;
    }
    
    .file-upload-modal {
        padding: 15px;
    }
    
    .file-upload-modal i {
        font-size: 24px;
    }
    
    .file-text {
        font-size: 12px;
    }
    
    .modal-footer-modal {
        padding: 15px;
        flex-direction: column;
    }
    
    .modal-footer-modal .btn {
        width: 100%;
    }
    
    .certification-item {
        padding: 15px;
        margin-bottom: 10px;
    }
    
    .validity-input-group {
        flex-direction: column;
        align-items: stretch;
    }
    
    .checkbox-label {
        margin-top: 8px;
    }
    
    .table-info-emp {
        font-size: 11px;
        padding: 10px 15px;
    }
    
    .stats-section-title h4 {
        font-size: 14px;
    }
}

@media (max-width: 576px) {
    .employees-admin-container {
        padding: 10px 0;
    }
    
    .page-header-emp-admin {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .header-left h2 {
        font-size: 16px;
        flex-wrap: wrap;
    }
    
    .stats-grid-emp {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .stat-box-emp {
        padding: 12px;
        flex-direction: row;
    }
    
    .stat-icon-emp {
        width: 38px;
        height: 38px;
        font-size: 18px;
        flex-shrink: 0;
    }
    
    .stat-number {
        font-size: 18px;
    }
    
    .card-emp {
        margin: 0 -10px;
        border-radius: 0;
    }
    
    .card-header-emp {
        padding: 12px 15px;
    }
    
    .card-header-emp h3 {
        font-size: 14px;
    }
    
    .table-emp thead th,
    .table-emp td {
        padding: 8px 6px;
        font-size: 10px;
    }
    
    .col-status {
        display: none;
    }
    
    .table-emp thead th.col-status {
        display: none;
    }
    
    .emp-row td.col-name strong {
        font-size: 12px;
    }
    
    .btn-action-emp {
        width: 28px;
        height: 28px;
        font-size: 11px;
    }
    
    .modal-content {
        margin: 0;
        border-radius: 0;
        height: 100%;
        max-height: 100%;
    }
    
    .modal-large-emp {
        max-width: 100%;
        margin: 0;
    }
    
    .modal-body {
        max-height: calc(100vh - 130px);
        padding: 12px;
    }
    
    .form-group-modal label {
        font-size: 12px;
    }
    
    .form-control-modal {
        padding: 8px;
        font-size: 12px;
    }
    
    .certification-item {
        padding: 12px;
        margin-bottom: 10px;
    }
    
    .cert-item-header h5 {
        font-size: 13px;
    }
    
    .btn-outline-primary-modal {
        width: 100%;
        padding: 12px;
    }
    
    .empty-state-emp {
        padding: 40px 15px;
    }
    
    .empty-state-emp i {
        font-size: 36px;
    }
    
    .empty-state-emp p {
        font-size: 14px;
    }
    
    .alert-custom-emp {
        padding: 15px;
        flex-direction: column;
        text-align: center;
    }
    
    .alert-custom-emp i {
        font-size: 24px;
    }
}

/* Table Responsive - Convert to cards on very small screens */
@media (max-width: 480px) {
    .table-responsive {
        overflow-x: visible;
    }
    
    .table-emp {
        display: block;
    }
    
    .table-emp thead {
        display: none;
    }
    
    .table-emp tbody {
        display: block;
    }
    
    .emp-row {
        display: block;
        margin-bottom: 15px;
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .emp-row td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .emp-row td:last-child {
        border-bottom: none;
        justify-content: center;
        padding-top: 12px;
    }
    
    .emp-row td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #666;
        font-size: 11px;
        text-transform: uppercase;
    }
    
    .emp-row .col-code,
    .emp-row .col-name,
    .emp-row .col-action {
        display: flex;
    }
    
    .emp-row .col-position,
    .emp-row .col-company,
    .emp-row .col-competency-type,
    .emp-row .col-competency,
    .emp-row .col-status,
    .emp-row .col-verified-by {
        display: none;
    }
    
    .action-buttons-emp {
        justify-content: center;
        width: 100%;
    }
    
    .btn-action-emp {
        width: 40px;
        height: 40px;
        font-size: 14px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>




