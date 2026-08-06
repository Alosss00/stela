<?php
$page_title = 'User Add Employee';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php
// Included via bootstrap/app.php

// Only USER role can access this page
checkPageAccess(['user']);


// Pastikan ini ditaruh di baris paling awal sebelum ada output HTML/spasi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate token CSRF jika belum ada di session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = new Database();
$company_name = $_SESSION['company_name'] ?? '';
$current_department = $_SESSION['department'] ?? '';
$message = '';
$error = '';

// Get certifications and positions for dropdown
$certifications = $db->query("SELECT * FROM certifications ORDER BY cert_name");
$certifications_data = []; // Array untuk menyimpan data sertifikasi
if ($certifications && $certifications->num_rows > 0) {
    while ($cert = $certifications->fetch_assoc()) {
        $certifications_data[$cert['id']] = [
            'cert_name' => $cert['cert_name'],
            'cert_type' => $cert['cert_type'] ?? '',
            'cert_issuer' => $cert['cert_issuer'] ?? '',
            'issuing_authority' => $cert['issuing_authority'] ?? '',
            'issuing_authority_name' => $cert['issuing_authority_name'] ?? ''
        ];
    }
    // Reset pointer untuk loop berikutnya
    $certifications->data_seek(0);
}
$positions = $db->query("SELECT * FROM positions ORDER BY position_type, position_name");

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

// Get supervision areas from database
$supervision_areas = $db->query("SELECT * FROM supervision_areas ORDER BY area_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // PERBAIKAN KEAMANAN: Validasi Token CSRF
    // ==========================================
    if (
        !isset($_SESSION['csrf_token']) ||
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $error = 'Akses ditolak: Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba kirim ulang formulir.';
    }

    if (!$error) {
        $employee_code = $db->escapeString(trim($_POST['employee_code']));
    $full_name = $db->escapeString(trim($_POST['full_name']));
    $position = $db->escapeString(trim($_POST['position']));
    $department = $db->escapeString(trim($_POST['department']));
    $competency_type = $db->escapeString(trim($_POST['competency_type']));
    $competency_name = !empty($_POST['competency_name']) ? $db->escapeString(trim($_POST['competency_name'])) : '';
    $supervision_area = !empty($_POST['supervision_area']) ? $db->escapeString(trim($_POST['supervision_area'])) : '';
    $ruang_lingkup = !empty($_POST['ruang_lingkup']) ? $db->escapeString(trim($_POST['ruang_lingkup'])) : '';
    $sub_competency = !empty($_POST['sub_competency']) ? $db->escapeString(trim($_POST['sub_competency'])) : '';
    $contractor_company = $db->escapeString(trim($_POST['contractor_company']));
    $is_draft = isset($_POST['is_draft']) ? (int)$_POST['is_draft'] : 0;
    
    // Validate required fields
    if (!$is_draft) {
        if (empty($employee_code) || empty($full_name) || empty($position) || empty($department) || empty($competency_type) || empty($contractor_company)) {
            $error = 'All fields are required!';
        } elseif (in_array($competency_type, ['pengawas_teknis', 'pengawas_operasional']) && empty($ruang_lingkup)) {
            $error = 'Scope of Work is required for Technical Supervisor and Operational Supervisor!';
        } elseif ($competency_type == 'pengawas_operasional' && empty($supervision_area)) {
            $error = 'Supervision Area is required for Operational Supervisor!';
        } elseif (in_array($competency_type, ['pengawas_teknis', 'tenaga_teknis']) && empty($competency_name)) {
            $error = 'Competency is required for Technical Supervisor and Technical Personnel types!';
        } elseif (!isset($_FILES['cv_file']) || $_FILES['cv_file']['error'] != 0) {
            $error = 'CV upload is required!';
        } elseif (!isset($_FILES['statement_file']) || $_FILES['statement_file']['error'] != 0) {
            $error = 'Statement Letter upload is required!';
        }
    } else {
        // Validation for draft is very relaxed, just ensure employee_code exists if at all possible, though not strictly required
    }
    
    if (empty($employee_code)) {
        // Even for draft we need ID badge as it is a unique identifier (and part of filenames)
        $error = 'ID BADGE is required even for drafts!';
    }
    
    if (!$error) {
        // Check if employee code already exists
        $check = $db->query("SELECT id FROM employees WHERE employee_code = '$employee_code'");
        if ($check && $check->num_rows > 0) {
            $error = 'ID BADGE is already in use!';
        } else {
            // Handle CV upload (optional if draft)
            $cv_file = '';
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
                $file_size = $_FILES['cv_file']['size'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $file_extension = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
                
                // Validate by extension (more reliable)
                $allowed_cv_extensions = ['pdf'];
                
                if (!in_array($file_extension, $allowed_cv_extensions)) {
                    $error = 'File type not allowed! Only PDF.';
                } elseif ($file_size > $max_size) {
                    $error = 'File size too large! Maximum 5MB.';
                } else {
                    $new_filename = 'cv_' . $employee_code . '_' . time() . '.' . $file_extension;
                    $upload_dir   = upload_physical_dir('cv');
                    $upload_path  = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_path)) {
                        $cv_file = 'cv/' . $new_filename;
                    } else {
                        $error = 'Failed to upload CV file.';
                    }
                }
            } 
            
            // Handle Statement upload (optional if draft)
            $statement_file = '';
            if (!$error && isset($_FILES['statement_file']) && $_FILES['statement_file']['error'] == 0) {
                $stmt_file_size = $_FILES['statement_file']['size'];
                $stmt_max_size = 5 * 1024 * 1024; // 5MB
                $stmt_file_extension = strtolower(pathinfo($_FILES['statement_file']['name'], PATHINFO_EXTENSION));
                
                if ($stmt_file_extension !== 'pdf') {
                    $error = 'Statement Letter must be in PDF format!';
                } elseif ($stmt_file_size > $stmt_max_size) {
                    $error = 'Statement Letter file size too large! Maximum 5MB.';
                } else {
                    $stmt_new_filename = 'statement_' . $employee_code . '_' . time() . '.pdf';
                    $stmt_upload_dir   = upload_physical_dir('statements');
                    $stmt_upload_path  = $stmt_upload_dir . $stmt_new_filename;

                    if (move_uploaded_file($_FILES['statement_file']['tmp_name'], $stmt_upload_path)) {
                        $statement_file = 'statements/' . $stmt_new_filename;
                    } else {
                        $error = 'Failed to upload Statement Letter file.';
                    }
                }
            }
            
            // Only proceed with insert if no errors
            if (!$error) {
                // Cek struktur tabel employees terlebih dahulu
                $columns_result = $db->query("SHOW COLUMNS FROM employees");
                $available_columns = [];
                while ($col = $columns_result->fetch_assoc()) {
                    $available_columns[] = $col['Field'];
                }
                
                $status_val = $is_draft ? 'draft' : 'pending';
                // Create dynamic INSERT query based on available columns
                $insert_fields = ['employee_code', 'full_name', 'position', 'department', 'competency_type', 'contractor_company', 'ruang_lingkup', 'cv_file', 'verification_status', 'is_active'];
                $insert_values = ["'$employee_code'", "'$full_name'", "'$position'", "'$department'", "'$competency_type'", "'$contractor_company'", "'$ruang_lingkup'", "'$cv_file'", "'$status_val'", "1"];
                
                // Tambahkan field optional jika ada di tabel
                if (in_array('competency_name', $available_columns) && !empty($competency_name)) {
                    $insert_fields[] = 'competency_name';
                    $insert_values[] = "'$competency_name'";
                }
                
                if (in_array('supervision_area', $available_columns) && !empty($supervision_area)) {
                    $insert_fields[] = 'supervision_area';
                    $insert_values[] = "'$supervision_area'";
                }

                if (in_array('sub_competency', $available_columns) && !empty($sub_competency)) {
                    $insert_fields[] = 'sub_competency';
                    $insert_values[] = "'$sub_competency'";
                }

                if (in_array('statement_file', $available_columns) && !empty($statement_file)) {
                    $insert_fields[] = 'statement_file';
                    $insert_values[] = "'$statement_file'";
                }

                if (in_array('created_by', $available_columns)) {
                    $insert_fields[] = 'created_by';
                    $current_user_id = intval($_SESSION['user_id'] ?? 0);
                    $insert_values[] = "'$current_user_id'";
                }
                
                $sql = "INSERT INTO employees (" . implode(', ', $insert_fields) . ") 
                        VALUES (" . implode(', ', $insert_values) . ")";
                
                if ($db->query($sql)) {
                    $employee_id = $db->lastInsertId();
                    
                    // Handle multiple certification uploads
                    if (isset($_FILES['certifications']) && !empty($_FILES['certifications']['name'][0])) {
                        $upload_dir = upload_physical_dir('certifications');

                        $cert_ids = $_POST['certification_ids'] ?? [];
                        $cert_numbers = $_POST['cert_numbers'] ?? [];
                        $cert_types = $_POST['cert_types'] ?? [];
                        $cert_types_other = $_POST['cert_types_other'] ?? [];
                        $cert_issuers = $_POST['cert_issuers'] ?? [];
                        $issue_dates = $_POST['issue_dates'] ?? [];
                        $expiry_dates = $_POST['expiry_dates'] ?? [];
                        $no_expiry = $_POST['no_expiry'] ?? [];
                        $expiry_reasons = $_POST['expiry_reasons'] ?? [];
                        
                        foreach ($_FILES['certifications']['tmp_name'] as $key => $tmp_name) {
                            if (isset($_FILES['certifications']['error'][$key]) && $_FILES['certifications']['error'][$key] == 0) {
                                $file_ext = strtolower(pathinfo($_FILES['certifications']['name'][$key], PATHINFO_EXTENSION));

                                // Validate certification file type - only PDF allowed
                                if ($file_ext !== 'pdf') {
                                    $error = 'Certificate file must be in PDF format!';
                                    break;
                                }

                                $cert_file = $employee_code . '_cert_' . $key . '_' . time() . '.' . $file_ext;
                                
                                if (move_uploaded_file($tmp_name, $upload_dir . $cert_file)) {
                                    $cert_path = 'certifications/' . $cert_file;
                                    $cert_id = intval($cert_ids[$key] ?? 0);
                                    $cert_number = $db->escapeString($cert_numbers[$key] ?? '');
                                    
                                    // Get cert_type: if "Lainnya", use manual input, otherwise use dropdown value
                                    $cert_type = '';
                                    if (isset($cert_types[$key]) && !empty($cert_types[$key])) {
                                        if ($cert_types[$key] === 'Lainnya') {
                                            $cert_type = $db->escapeString($cert_types_other[$key] ?? '');
                                        } else {
                                            $cert_type = $db->escapeString($cert_types[$key]);
                                        }
                                    }
                                    
                                    $cert_issuer = $db->escapeString($cert_issuers[$key] ?? '');
                                    $issue_date = $db->escapeString($issue_dates[$key] ?? '');
                                    $expiry_date = $db->escapeString($expiry_dates[$key] ?? '');
                                    $reason = $db->escapeString($expiry_reasons[$key] ?? '');
                                    
                                    // Check if expired
                                    $today = date('Y-m-d');
                                    $status = ($expiry_date && $expiry_date < $today) ? 'expired' : 'pending';
                                    
                                    // Check if cert_type column exists in employee_certifications table
                                    $columns_check = $db->query("SHOW COLUMNS FROM employee_certifications LIKE 'cert_type'");
                                    
                                    if ($columns_check && $columns_check->num_rows > 0) {
                                        // Column cert_type EXISTS - include in INSERT
                                        $sql_cert = "INSERT INTO employee_certifications 
                                                    (employee_id, certification_id, cert_type, cert_number, cert_issuer, issue_date, expiry_date, 
                                                     document_file, status, verification_status, expiry_reason) 
                                                    VALUES ($employee_id, $cert_id, '$cert_type', '$cert_number', '$cert_issuer', '$issue_date', '$expiry_date', 
                                                            '$cert_path', '$status', 'pending', '$reason')";
                                    } else {
                                        // Column cert_type DOES NOT EXIST - skip cert_type (backward compatible)
                                        $sql_cert = "INSERT INTO employee_certifications 
                                                    (employee_id, certification_id, cert_number, cert_issuer, issue_date, expiry_date, 
                                                     document_file, status, verification_status, expiry_reason) 
                                                    VALUES ($employee_id, $cert_id, '$cert_number', '$cert_issuer', '$issue_date', '$expiry_date', 
                                                            '$cert_path', '$status', 'pending', '$reason')";
                                    }
                                
                                    if (!$db->query($sql_cert)) {

    die(
        "<h3>INSERT GAGAL</h3><pre>" .
        $db->getConnection()->error .
        "</pre>"
    );

}
                                }
                            }
                        }
                    }
                    
                    // Send notification to admin - with better error handling and timeout protection
                    try {
                        if (class_exists('NotificationService')) {
                            // Set a shorter timeout for the notification process
                            set_time_limit(60); // Allow 60 seconds for this page (30 more seconds)
                            
                            $notificationService = new NotificationService();
                            $notificationService->notifyNewEmployeeAdded($employee_id, $company_name);
                        } else {
                            error_log("NotificationService class not found");
                        }
                    } catch (Exception $e) {
                        // Tangani Exception (runtime errors) - don't fail the whole process
                        error_log("Notification Exception: " . $e->getMessage());
                    } catch (Error $e) {
                        // Tangani Error (class not found, dll) - don't fail the whole process
                        error_log("Notification Error: " . $e->getMessage());
                    }

                                        // Instantly index new employee to Bonsai.io / Elasticsearch
                    try {
                        $esServicePath = dirname(__DIR__, 2) . '/app/Services/ElasticsearchService.php';
                        if (file_exists($esServicePath)) {
                            require_once $esServicePath;
                            if (class_exists('ElasticsearchService')) {
                                $esService = ElasticsearchService::getInstance();
                                if ($esService->isAvailable()) {
                                    $esService->indexEmployee([
                                        'id' => (int)$employee_id,
                                        'employee_code' => $employee_code,
                                        'full_name' => $full_name,
                                        'position' => $position,
                                        'department' => $department,
                                        'contractor_company' => $contractor_company,
                                        'competency_type' => $competency_type,
                                        'competency_name' => $competency_name,
                                        'ruang_lingkup' => $ruang_lingkup,
                                        'sub_competency' => $sub_competency,
                                        'supervision_area' => $supervision_area,
                                        'approval_status' => 'pending',
                                        'is_active' => 1,
                                        'created_at' => date('Y-m-d H:i:s')
                                    ]);
                                }
                            }
                        }
                    } catch (\Throwable $esEx) {
                        error_log("Elasticsearch auto-index exception: " . $esEx->getMessage());
                    }

                    $message = 'Employee successfully added! Waiting for Admin verification.';

                    // Ini mencegah pengiriman ganda jika user me-refresh halaman sukses (Double Submit)
                    unset($_SESSION['csrf_token']);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    // Redirect after 2 seconds
                    header("refresh:2;url=employees.php");
                } else {
                    $error = 'Failed to add employee!';
                    error_log("Error inserting employee: " . $db->getConnection()->error);
                }
            }
        }
    }
}
}

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="add-employee-container">
    <!-- Page Header -->
    <div class="page-header-add">
        <div class="header-left">
            <h2><i class="fas fa-user-plus"></i> <span data-lang="add-new-request-employee">Add New Request Employee</span></h2>
            <?php if (!empty($current_department)): ?>
            <p><span data-lang="department">Department</span>: <strong><?php echo htmlspecialchars($current_department); ?></strong></p>
            <?php else: ?>
            <p><span data-lang="company">Company</span>: <strong><?php echo htmlspecialchars($company_name); ?></strong></p>
            <?php endif; ?>
        </div>
        <a href="employees.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> <span data-lang="back">Back</span>
        </a>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-add">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-add">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="" enctype="multipart/form-data" class="form-container" id="addEmployeeForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="is_draft" id="is_draft" value="0">
        <!-- Section 1: Identity & Competency Data -->
        <div class="form-section">
            <div class="section-header">
                <h3><i class="fas fa-id-card"></i> <span data-lang="identity-competency-data">Identity & Competency Data</span></h3>
                <span class="section-number">1</span>
            </div>
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="employee_code" data-lang="id-badge-required">ID BADGE <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="employee_code" name="employee_code"
                           value="<?php echo isset($_POST['employee_code']) ? htmlspecialchars($_POST['employee_code']) : ''; ?>"
                           required placeholder="Example: BADGE001" data-lang-placeholder="badge-example-placeholder">
                    <small class="form-hint" data-lang="unique-id-badge-hint">Unique ID for employee badge identification</small>
                </div>
                
                <div class="form-group col-lg-6">
                    <label for="full_name" data-lang="full-name">Full Name<span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                           required placeholder="Full name of the employee" data-lang-placeholder="full-name-of-employee-placeholder">
                </div>
            </div>
            
            <!-- Department disembunyikan, nilai default diatur otomatis -->
            <input type="hidden" id="department" name="department" value="General">
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="position" data-lang="position">Position <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="position" name="position" 
                           value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>"
                           required placeholder="Example: Rigger, HSE Superintendent" data-lang-placeholder="position-example-placeholder">
                </div>
                
                <div class="form-group col-lg-6" id="ruang_lingkup_group" style="display: none;">
                    <label for="ruang_lingkup" data-lang="scope-of-work">Scope of Work <span class="text-danger">*</span></label>
                    <select class="form-control" id="ruang_lingkup" name="ruang_lingkup">
                        <option value="" data-lang="select-scope-of-work">-- Select Scope of Work --</option>
                        <option value="PT Meares Soputan Mining (MSM)" data-lang="scope-of-work-msm" <?php echo (isset($_POST['ruang_lingkup']) && $_POST['ruang_lingkup'] == 'PT Meares Soputan Mining (MSM)') ? 'selected' : ''; ?>>PT Meares Soputan Mining</option>
                        <option value="PT Tambang Tondano Nusajaya (TTN)" data-lang="scope-of-work-ttn" <?php echo (isset($_POST['ruang_lingkup']) && $_POST['ruang_lingkup'] == 'PT Tambang Tondano Nusajaya (TTN)') ? 'selected' : ''; ?>>PT Tambang Tondano Nusajaya</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="competency_type" data-lang="competency-type">Competency Type <span class="text-danger">*</span></label>
                    <select class="form-control" id="competency_type" name="competency_type" onchange="toggleCompetencyField()" required>
                        <option value="" data-lang="select-competency-type">-- Select Competency Type --</option>
                        <option value="pengawas_operasional" data-lang="competency-type-operational-supervisor" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'pengawas_operasional') ? 'selected' : ''; ?>>Operational Supervisor</option>
                        <option value="pengawas_teknis" data-lang="competency-type-technical-supervisor" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'pengawas_teknis') ? 'selected' : ''; ?>>Technical Supervisor</option>
                        <option value="tenaga_teknis" data-lang="competency-type-technical-personnel" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'tenaga_teknis') ? 'selected' : ''; ?>>Technical Personnel</option>
                    </select>
                </div>

                <div class="form-group col-lg-6" id="supervision_area_group" style="display: none;">
                    <label for="supervision_area" data-lang="supervision-area">Supervision Area <span class="text-danger">*</span></label>
                    <select class="form-control" id="supervision_area" name="supervision_area">
                        <option value="" data-lang="select-supervision-area">-- Select Supervision Area --</option>
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

                <div class="form-group col-lg-6" id="competency_group" style="display: none;">
                    <label for="competency_name" data-lang="competency">Competency <span class="text-danger">*</span></label>
                    <select class="form-control" id="competency_name" name="competency_name" onchange="loadSubCompetencies()">
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
                        // Populate competencies for pengawas_teknis (menggunakan data tenaga_teknis sesuai permintaan)
                        if (!empty($competencies_by_type['tenaga_teknis'])) {
                            foreach ($competencies_by_type['tenaga_teknis'] as $comp):
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

                <div class="form-group col-lg-6" id="sub_competency_group" style="display: none;">
                    <label for="sub_competency" data-lang="sub-competency">Sub Competency</label>
                    <select class="form-control" id="sub_competency" name="sub_competency">
                        <option value="" data-lang="select-sub-competency">-- Select Sub Competency --</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="contractor_company">
                        <?php if (!empty($current_department)): ?>
                            <span data-lang="department">Department</span>
                        <?php else: ?>
                            <span data-lang="company">Company</span>
                        <?php endif; ?>
                    </label>
                    <input type="text" class="form-control" id="contractor_company" name="contractor_company"
                           value="<?php echo htmlspecialchars(!empty($current_department) ? $current_department : $company_name); ?>"
                           required readonly>
                    <small class="form-hint" data-lang="auto-filled-from-account">Automatically filled from your account</small>
                </div>
            </div>
            
            <!-- File Upload Section -->
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="cv_file" data-lang="upload-cv">Upload CV<span class="text-danger">*</span></label>
                    <div class="file-upload-area">
                        <i class="fas fa-file-upload"></i>
                        <input type="file" name="cv_file" id="cv_file" class="file-input" accept=".pdf" required>
                        <span class="file-text"><span data-lang="upload-cv-file">Upload file CV</span><br><span data-lang="pdf-max-5mb">(PDF, Max 5MB)</span></span>
                        <span class="file-name"></span>
                    </div>
                </div>
                
                <div class="form-group col-lg-6">
                    <label for="statement_file" data-lang="upload-statement-letter">Upload Statement Letter<span class="text-danger">*</span></label>
                    <div class="file-upload-area">
                        <i class="fas fa-file-signature"></i>
                        <input type="file" name="statement_file" id="statement_file" class="file-input" accept=".pdf" required>
                        <span class="file-text"><span data-lang="upload-tt-mgt-frs-008d">Upload TT-MGT-FRS-008D</span><br><span data-lang="pdf-max-5mb">(PDF, Max 5MB)</span></span>
                        <span class="file-name"></span>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-warning-custom" style="margin-bottom: 0;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong data-lang="important-statement-letter">Important - Statement Letter:</strong>
                    <p style="margin-bottom: 8px;" data-lang="wet-signature-pdf-instruction">The statement letter must be signed with wet signature (original) and scanned in PDF format</p>
                    <a href="https://drive.google.com/drive/folders/1z_LkU7C0bgz5VnVKyZBmmbP8mUuZGr06?usp=sharing" class="btn btn-info btn-sm" target="_blank" style="margin-top: 5px;">
                        <i class="fas fa-download"></i> <span data-lang="download-statement-letter-template">Download Statement Letter Template</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Section 2: Sertifikasi -->
        <div class="form-section">
            <div class="section-header">
                <h3><i class="fas fa-certificate"></i> <span data-lang="certification-competency">Certification/Competency</span></h3>
                <span class="section-number">2</span>
            </div>
            
            <div id="certificationContainer" class="certifications-list">
                <div class="certification-item">
                    <div class="cert-item-header">
                        <h5><i class="fas fa-file-certificate"></i> <span data-lang="certification-number-1">Certification #1</span></h5>
                        <div class="cert-header-actions">
                            <!-- Remove button will appear for additional certifications -->
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-4">
                            <label data-lang="certification-name">Certification Name <span class="text-danger">*</span></label>
                            <select name="certification_ids[]" class="form-control cert-name-select" required onchange="updateIssuer(this)">
                                <option value="" data-lang="select-certification">-- Select Certification --</option>
                                <?php
                                if ($certifications && $certifications->num_rows > 0) {
                                    $certifications->data_seek(0);
                                    while ($cert = $certifications->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $cert['id']; ?>" 
                                        data-type="<?php echo htmlspecialchars($cert['cert_type'] ?? ''); ?>" 
                                        data-issuer="<?php echo htmlspecialchars($cert['cert_issuer'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($cert['cert_name']); ?>
                                    </option>
                                    <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group col-lg-4">
                            <label data-lang="certificate-type">Certificate Type <span class="text-danger">*</span></label>
                            <select name="cert_types[]" class="form-control cert-type-select" required onchange="toggleOtherType(this)">
                                <option value="" data-lang="select-type">-- Select Type --</option>
                                <option value="Attendance/Participant" data-lang="attendance-participant">Attendance/Participant</option>
                                <option value="Competent" data-lang="competent">Competent</option>
                                <option value="Training" data-lang="training">Training</option>
                            </select>
                        </div>
                        <div class="form-group col-lg-4 other-type-input" style="display: none;">
                            <label data-lang="other-type">Other Type <span class="text-danger">*</span></label>
                            <input type="text" name="cert_types_other[]" class="form-control" placeholder="Enter certificate type" data-lang-placeholder="enter-certificate-type">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-6">
                            <label data-lang="certificate-number">Certificate Number <span class="text-danger">*</span></label>
                            <input type="text" name="cert_numbers[]" class="form-control" required placeholder="Certificate number" data-lang-placeholder="certificate-number-placeholder">
                        </div>
                        <div class="form-group col-lg-6">
                            <label data-lang="issuer">Issuer <span class="text-danger">*</span></label>
                            <input type="text" name="cert_issuers[]" class="form-control" required placeholder="Name of issuer/certification body" data-lang-placeholder="issuer-certification-body-name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-6">
                            <label data-lang="issue-date">Issue Date <span class="text-danger">*</span></label>
                            <input type="date" name="issue_dates[]" class="form-control issue-date" required onchange="calculateExpiryDate(this)">
                        </div>
                        <div class="form-group col-lg-6">
                            <label data-lang="validity-period">Validity Period <span class="text-danger">*</span></label>
                            <div class="validity-input-group">
                                <input type="number" name="validity_years[]" class="form-control validity-years" min="0" step="0.5" placeholder="Years" data-lang-placeholder="years" onchange="calculateExpiryDate(this)">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="no_expiry[]" class="no-expiry-check" onchange="toggleExpiryField(this)">
                                    <span data-lang="no-expiry">No Expiry</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-6">
                            <label data-lang="expiry-date">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_dates[]" class="form-control expiry-date" readonly>
                        </div>
                        <div class="form-group col-lg-6">
                        </div>
                    </div>
                    
                    <div class="form-group other-expiry-reason" style="display: none;">
                        <label data-lang="notes">Notes <span class="text-danger">*</span></label>
                        <textarea name="expiry_reasons[]" class="form-control" placeholder="Explain the reason..." data-lang-placeholder="explain-the-reason" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label data-lang="upload-certificate-file">Upload Certificate File <span class="text-danger">*</span></label>
                        <div class="file-upload-area">
                            <i class="fas fa-file-pdf"></i>
                            <input type="file" name="certifications[]" class="file-input" accept=".pdf" required>
                            <span class="file-text" data-lang="upload-certificate-file-pdf">Upload certificate file (PDF, Max 5MB)</span>
                            <span class="file-name"></span>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-outline-primary" onclick="addCertification()">
                <i class="fas fa-plus-circle"></i> <span data-lang="add-another-certification">Add Another Certification</span>
            </button>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-info-custom">
            <i class="fas fa-lightbulb"></i>
            <div>
                <strong data-lang="important-notes">Important Notes</strong>
                <p data-lang="after-employee-data-added-note">After the employee data is added, the status will be "Pending" and awaiting verification from Admin before an Appointment Letter can be created.</p>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> <span data-lang="save-submit-verification">Save & Submit for Verification</span>
            </button>
            <a href="employees.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-times"></i> <span data-lang="cancel">Cancel</span>
            </a>
        </div>
    </form>
</div>

<script>
// Data sertifikasi dari server
const certificationsData = <?php echo json_encode($certifications_data); ?>;
const competenciesWithId = <?php echo json_encode($competencies_with_id); ?>;

// Store POST values for restoring after error
const postSubCompetency = '<?php echo isset($_POST['sub_competency']) ? addslashes($_POST['sub_competency']) : ''; ?>';

// Debug: Tampilkan data di console
console.log('certificationsData:', certificationsData);
console.log('competenciesWithId:', competenciesWithId);

// Load sub-competencies based on selected competency
async function loadSubCompetencies() {
    const competencySelect = document.getElementById('competency_name');
    const subCompetencySelect = document.getElementById('sub_competency');
    const selectedOption = competencySelect.options[competencySelect.selectedIndex];
    
    // Clear previous options
    const selectSubCompetencyText = window.getLanguageText('select-sub-competency', '-- Select Sub Competency --');
    subCompetencySelect.innerHTML = '<option value="">' + selectSubCompetencyText + '</option>';
    
    if (!selectedOption.value) {
        toggleSubCompetency();
        return;
    }
    
    const competencyId = selectedOption.getAttribute('data-id');
    
    if (!competencyId) {
        console.warn('Competency ID not found');
        toggleSubCompetency();
        return;
    }
    
    try {
        const response = await fetch('../../api/get_sub_competencies.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                competency_id: parseInt(competencyId)
            })
        });
        
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            result.data.forEach(subComp => {
                const option = document.createElement('option');
                // Use subComp.name as value, with fallback to id to prevent undefined
                const subCompName = subComp.name || subComp.id || '';
                const subCompDisplay = subComp.name || `Sub-Competency #${subComp.id}` || '';
                
                if (subCompName === '' || subCompName === undefined) {
                    console.warn('Invalid sub-competency:', subComp);
                    return; // Skip this option if no valid name/id
                }
                
                option.value = subCompName;
                option.textContent = subCompDisplay;
                option.title = subComp.description || '';
                option.setAttribute('data-id', subComp.id);
                
                // Check if this option should be selected from POST
                if (postSubCompetency && subCompName === postSubCompetency) {
                    option.selected = true;
                }
                subCompetencySelect.appendChild(option);
            });

            console.log('Sub-competencies loaded:', result.data);
            // Force value restoration after options are added
            if (postSubCompetency && postSubCompetency !== '') {
                subCompetencySelect.value = postSubCompetency;
                console.log('Sub-competency restored to:', postSubCompetency);
            }
        } else {
            console.log('No sub-competencies found for this competency');
        }
    } catch (error) {
        console.error('Error loading sub-competencies:', error);
    }
    
    // Show or hide sub_competency field based on competency type and selection
    toggleSubCompetency();
}

function toggleCompetencyField() {
    const competencyType = document.getElementById('competency_type').value;
    const supervisionAreaGroup = document.getElementById('supervision_area_group');
    const competencyGroup = document.getElementById('competency_group');
    const ruangLingkupGroup = document.getElementById('ruang_lingkup_group');
    const subCompetencyGroup = document.getElementById('sub_competency_group');
    const competencyInput = document.getElementById('competency_name');
    const supervisionInput = document.getElementById('supervision_area');
    const ruangLingkupInput = document.getElementById('ruang_lingkup');
    const subCompetencyInput = document.getElementById('sub_competency');

    // Reset required attributes
    competencyInput.removeAttribute('required');
    supervisionInput.removeAttribute('required');
    ruangLingkupInput.removeAttribute('required');
    subCompetencyInput.removeAttribute('required');

    // Hide sub_competency by default
    subCompetencyGroup.style.display = 'none';
    subCompetencyInput.value = '';

    if (competencyType === 'pengawas_operasional') {
        ruangLingkupGroup.style.display = 'block';
        ruangLingkupInput.setAttribute('required', 'required');
        // Tampilkan kedua field untuk pengawas operasional
        supervisionAreaGroup.style.display = 'block';
        competencyGroup.style.display = 'block';

        // Kedua field wajib diisi
        competencyInput.setAttribute('required', 'required');
        supervisionInput.setAttribute('required', 'required');

        // Filter kompetensi
        filterCompetencies('pengawas_operasional');
    } else if (competencyType === 'pengawas_teknis') {
        // Pengawas Teknis: show ruang_lingkup and competency, hide supervision_area
        ruangLingkupGroup.style.display = 'block';
        ruangLingkupInput.setAttribute('required', 'required');
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'block';
        competencyInput.setAttribute('required', 'required');
        supervisionInput.removeAttribute('required');
        filterCompetencies('pengawas_teknis');
    } else if (competencyType === 'tenaga_teknis') {
        // Tenaga Teknis: show ruang_lingkup and competency, hide supervision_area
        ruangLingkupGroup.style.display = 'block';
        ruangLingkupInput.setAttribute('required', 'required');
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'block';
        competencyInput.setAttribute('required', 'required');
        supervisionInput.removeAttribute('required');
        filterCompetencies(competencyType);

        // Check if competency is already selected, show sub_competency
        toggleSubCompetency();
    } else {
        // Sembunyikan semua jika tidak ada tipe dipilih
        ruangLingkupGroup.style.display = 'none';
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'none';
    }
}

// Function to toggle Sub Competency field for Tenaga Teknis
// Only show sub competency for allowed competencies: Juru Las, Juru Ledak
function toggleSubCompetency() {
    const competencyType = document.getElementById('competency_type').value;
    const competencyInput = document.getElementById('competency_name');
    const subCompetencyGroup = document.getElementById('sub_competency_group');
    const subCompetencyInput = document.getElementById('sub_competency');
    
    // Allowed competencies with sub-competencies
    const ALLOWED_COMPETENCIES_WITH_SUB = ['Juru Las', 'Juru Ledak'];
    const selectedCompetency = competencyInput.value.trim();
    const isAllowedCompetency = ALLOWED_COMPETENCIES_WITH_SUB.includes(selectedCompetency);

    if (competencyType === 'tenaga_teknis' && selectedCompetency !== '' && isAllowedCompetency) {
        subCompetencyGroup.style.display = 'block';
        // subCompetency is optional for now; do not set required
    } else {
        subCompetencyGroup.style.display = 'none';
        subCompetencyInput.removeAttribute('required');
        subCompetencyInput.value = '';
    }
}

function filterCompetencies(competencyType) {
    const competencySelect = document.getElementById('competency_name');
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

function updateIssuer(selectElement) {

    // User mengisi issuer secara manual.
    // Jangan menghapus nilai issuer jika Certification Name berubah.

    const certItem = selectElement.closest('.certification-item');

    const certTypeSelect =
        certItem.querySelector('select[name="cert_types[]"]');

    const otherTypeInput =
        certItem.querySelector('.other-type-input');

    // Reset Certificate Type saja
    certTypeSelect.value = '';
    otherTypeInput.style.display = 'none';

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
    const otherExpiryReason = certItem.querySelector('.other-expiry-reason');
    
    if (checkboxElement.checked) {
        expiryDateInput.value = '';
        expiryDateInput.readOnly = true;
        otherExpiryReason.style.display = 'block';
    } else {
        expiryDateInput.readOnly = false;
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
        const input = area.querySelector('.file-input');
        input.files = e.dataTransfer.files;
        updateFileName(area, input.files[0]);
    });
    
    const input = area.querySelector('.file-input');
    input.addEventListener('change', () => {
        updateFileName(area, input.files[0]);
    });
}

// File upload preview
document.querySelectorAll('.file-upload-area').forEach(area => {
    setupFileUpload(area);
});

function updateFileName(area, file) {
    if (file) {
        area.querySelector('.file-name').textContent = file.name;
        area.querySelector('.file-name').style.display = 'block';
    }
}

// Initialize event listeners for initial certification item on page load
document.addEventListener('DOMContentLoaded', function() {
    // Re-initialize event listeners for all certification items
    document.querySelectorAll('.certification-item').forEach(item => {
        const certTypeSelect = item.querySelector('.cert-type-select');
        if (certTypeSelect) certTypeSelect.onchange = function() { toggleOtherType(this); };
        
        const certNameSelect = item.querySelector('.cert-name-select');
        if (certNameSelect) {
            certNameSelect.onchange = function() { updateIssuer(this); };
            if (certNameSelect.value) updateIssuer(certNameSelect);
        }
        
        const issueDate = item.querySelector('input[name="issue_dates[]"]');
        if (issueDate) issueDate.onchange = function() { calculateExpiryDate(this); };
        
        const validityYears = item.querySelector('input[name="validity_years[]"]');
        if (validityYears) validityYears.onchange = function() { calculateExpiryDate(this); };
        
        const noExpiryCheck = item.querySelector('input[name="no_expiry[]"]');
        if (noExpiryCheck) {
            noExpiryCheck.onchange = function() { toggleExpiryField(this); };
            if (noExpiryCheck.checked) toggleExpiryField(noExpiryCheck);
        }
    });
    
    // Trigger toggleCompetencyField if competency_type has a value on page load
    const compTypeEl = document.getElementById('competency_type');
    if (compTypeEl && compTypeEl.value) {
        toggleCompetencyField();
        const compNameEl = document.getElementById('competency_name');
        if (compNameEl && compNameEl.value) {
            loadSubCompetencies();
        }
    }

    // Client-side form validation before submit
    const formEl = document.getElementById('addEmployeeForm') || document.querySelector('form.form-container');
    if (formEl) {
        formEl.addEventListener('submit', function(e) {
            let isValid = true;
            let firstInvalid = null;

            // Clear previous invalid highlights
            formEl.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            formEl.querySelectorAll('.file-upload-area').forEach(el => el.style.borderColor = '');

            // Check required fields
            const requiredFields = formEl.querySelectorAll('input[required], select[required], textarea[required]');
            requiredFields.forEach(field => {
                const group = field.closest('.form-group');
                const isVisible = group ? (group.style.display !== 'none' && group.offsetHeight > 0) : true;
                
                if (isVisible) {
                    if (field.type === 'file') {
                        if (field.files.length === 0 && !field.dataset.hasExisting) {
                            isValid = false;
                            const area = field.closest('.file-upload-area');
                            if (area) area.style.borderColor = '#ef4444';
                            if (!firstInvalid) firstInvalid = field;
                        }
                    } else if (!field.value || field.value.trim() === '') {
                        isValid = false;
                        field.classList.add('is-invalid');
                        field.style.borderColor = '#ef4444';
                        if (!firstInvalid) firstInvalid = field;
                    }
                }
            });

            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                alert('Mohon lengkapi semua data dan berkas yang wajib diisi (*) terlebih dahulu!');
                return false;
            }
        });
    }
});

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

        <div class="form-row">
            <div class="form-group col-lg-4">
                <label data-lang="certification-name">Certification Name <span class="text-danger">*</span></label>
                <select name="certification_ids[]" class="form-control cert-name-select" required onchange="updateIssuer(this)">
                    <option value="" data-lang="select-certification">-- Select Certification --</option>
                    ${getCertificationOptions()}
                </select>
            </div>
            <div class="form-group col-lg-4">
                <label data-lang="certificate-type">Certificate Type <span class="text-danger">*</span></label>
                <select name="cert_types[]" class="form-control cert-type-select" required onchange="toggleOtherType(this)">
                    <option value="" data-lang="select-type">-- Select Type --</option>
                    <option value="Attendance/Participant" data-lang="attendance-participant">Attendance/Participant</option>
                    <option value="Competent" data-lang="competent">Competent</option>
                    <option value="Training" data-lang="training">Training</option>
                </select>
            </div>
            <div class="form-group col-lg-4 other-type-input" style="display: none;">
                <label data-lang="other-type">Other Type <span class="text-danger">*</span></label>
                <input type="text" name="cert_types_other[]" class="form-control" placeholder="Enter certificate type" data-lang-placeholder="enter-certificate-type">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-lg-6">
                <label data-lang="certificate-number">Certificate Number <span class="text-danger">*</span></label>
                <input type="text" name="cert_numbers[]" class="form-control" required placeholder="Certificate number" data-lang-placeholder="certificate-number-placeholder">
            </div>
            <div class="form-group col-lg-6">
                <label data-lang="issuer">Issuer <span class="text-danger">*</span></label>
                <input type="text" name="cert_issuers[]" class="form-control" required placeholder="Name of issuer/certification body" data-lang-placeholder="issuer-certification-body-name">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-lg-6">
                <label data-lang="issue-date">Issue Date <span class="text-danger">*</span></label>
                <input type="date" name="issue_dates[]" class="form-control issue-date" required onchange="calculateExpiryDate(this)">
            </div>
            <div class="form-group col-lg-6">
                <label data-lang="validity-period">Validity Period <span class="text-danger">*</span></label>
                <div class="validity-input-group">
                    <input type="number" name="validity_years[]" class="form-control validity-years" min="0" step="0.5" placeholder="Years" data-lang-placeholder="years" onchange="calculateExpiryDate(this)">
                    <label class="checkbox-label">
                        <input type="checkbox" name="no_expiry[]" class="no-expiry-check" onchange="toggleExpiryField(this)">
                        <span data-lang="no-expiry">No Expiry</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-lg-6">
                <label data-lang="expiry-date">Expiry Date <span class="text-danger">*</span></label>
                <input type="date" name="expiry_dates[]" class="form-control expiry-date" readonly>
            </div>
            <div class="form-group col-lg-6">
            </div>
        </div>

        <div class="form-group other-expiry-reason" style="display: none;">
            <label data-lang="notes">Notes <span class="text-danger">*</span></label>
            <textarea name="expiry_reasons[]" class="form-control" placeholder="Explain the reason..." data-lang-placeholder="explain-the-reason" rows="2"></textarea>
        </div>

        <div class="form-group">
            <label data-lang="upload-certificate-file">Upload Certificate File <span class="text-danger">*</span></label>
            <div class="file-upload-area">
                <i class="fas fa-file-pdf"></i>
                <input type="file" name="certifications[]" class="file-input" accept=".pdf" required>
                <span class="file-text" data-lang="upload-certificate-file-pdf">Upload certificate file (PDF, Max 5MB)</span>
                <span class="file-name"></span>
            </div>
        </div>
    `;

    container.appendChild(newItem);

    // Setup file upload for the new item
    const fileUploadArea = newItem.querySelector('.file-upload-area');
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
        const mustHaveOneCert = window.getLanguageText('must-have-one-cert', 'Must have at least one certification!');
        alert(mustHaveOneCert);
        return;
    }

    // Confirm before removing
    const confirmRemoveCert = window.getLanguageText('confirm-remove-cert', 'Are you sure you want to remove this certification?');
    if (confirm(confirmRemoveCert)) {
        certItem.remove();
        updateCertificationNumbers();
    }
}

function updateCertificationNumbers() {
    const container = document.getElementById('certificationContainer');
    const certItems = container.querySelectorAll('.certification-item');

    certItems.forEach((item, index) => {
        const header = item.querySelector('.cert-item-header h5');
        const certLabel = window.getLanguageText('certification', 'Certification');
        header.innerHTML = `<i class="fas fa-file-certificate"></i> ${certLabel} #${index + 1}`;

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

// ==========================================
// Idle Timeout Auto-Draft Implementation
// ==========================================
let idleTime = 0;
// 15 minutes timeout (15 * 60 = 900 seconds)
const IDLE_TIMEOUT_SECONDS = 900; 
let idleInterval = setInterval(timerIncrement, 1000); // 1 second

// Zero the idle timer on user action
const resetTimer = () => {
    idleTime = 0;
};
window.onload = resetTimer;
window.onmousemove = resetTimer;
window.onmousedown = resetTimer;
window.ontouchstart = resetTimer;
window.onclick = resetTimer;
window.onkeypress = resetTimer;

function timerIncrement() {
    idleTime++;
    if (idleTime > IDLE_TIMEOUT_SECONDS) {
        // Trigger auto draft
        saveDraftAndLogout();
        // Stop timer to prevent multiple triggers
        clearInterval(idleInterval);
    }
}

function saveDraftAndLogout() {
    // Show a loading overlay so the user knows what's happening
    const overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.backgroundColor = 'rgba(0,0,0,0.7)';
    overlay.style.zIndex = '9999';
    overlay.style.display = 'flex';
    overlay.style.flexDirection = 'column';
    overlay.style.justifyContent = 'center';
    overlay.style.alignItems = 'center';
    overlay.style.color = 'white';
    overlay.innerHTML = `
        <i class="fas fa-spinner fa-spin fa-3x" style="margin-bottom: 20px;"></i>
        <h3>Sesi Berakhir karena tidak ada aktivitas</h3>
        <p>Menyimpan data sebagai draft...</p>
    `;
    document.body.appendChild(overlay);

    const form = document.getElementById('addEmployeeForm');
    if(form) {
        document.getElementById('is_draft').value = '1';
        
        const formData = new FormData(form);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            // Redirect to logout regardless of success/fail to protect account
            window.location.href = '../../../logout.php?reason=timeout';
        })
        .catch(error => {
            console.error('Error saving draft:', error);
            window.location.href = '../../../logout.php?reason=timeout';
        });
    } else {
        window.location.href = '../../../logout.php?reason=timeout';
    }
}
</script>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
