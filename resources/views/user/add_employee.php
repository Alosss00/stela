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

    // Validate required fields
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
    } else {
        // Check if employee code already exists
        $check = $db->query("SELECT id FROM employees WHERE employee_code = '$employee_code'");
        if ($check && $check->num_rows > 0) {
            $error = 'ID BADGE is already in use!';
        } else {
            // Handle CV upload
            $cv_file = '';
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
                $upload_dir = '../../assets/uploads/cv/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $new_filename = 'cv_' . $employee_code . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_path)) {
                    $cv_file = 'uploads/cv/' . $new_filename;
                } else {
                    $error = 'Failed to upload CV file.';
                }
            } 
            
            // Handle Statement upload (required)
            $statement_file = '';
            if (!$error) {
                $stmt_file_size = $_FILES['statement_file']['size'];
                $stmt_max_size = 5 * 1024 * 1024; // 5MB
                $stmt_file_extension = strtolower(pathinfo($_FILES['statement_file']['name'], PATHINFO_EXTENSION));

                if ($stmt_file_extension !== 'pdf') {
                    $error = 'Statement Letter must be in PDF format!';
                } elseif ($stmt_file_size > $stmt_max_size) {
                    $error = 'Statement Letter file size too large! Maximum 5MB.';
                } else {
                    $stmt_upload_dir = dirname(__DIR__, 2) . '/assets/uploads/statements/';
                    if (!file_exists($stmt_upload_dir)) {
                        mkdir($stmt_upload_dir, 0755, true);
                    }
                    
                    $stmt_new_filename = 'statement_' . $employee_code . '_' . time() . '.pdf';
                    $stmt_upload_path = $stmt_upload_dir . $stmt_new_filename;
                    
                    if (move_uploaded_file($_FILES['statement_file']['tmp_name'], $stmt_upload_path)) {
                        $statement_file = 'uploads/statements/' . $stmt_new_filename;
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
                
                // Buat query INSERT dinamis berdasarkan kolom yang tersedia
                $insert_fields = ['employee_code', 'full_name', 'position', 'department', 'competency_type', 'contractor_company', 'ruang_lingkup', 'cv_file', 'verification_status', 'is_active'];
                $insert_values = ["'$employee_code'", "'$full_name'", "'$position'", "'$department'", "'$competency_type'", "'$contractor_company'", "'$ruang_lingkup'", "'$cv_file'", "'pending'", "1"];
                
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
                        $upload_dir = '../../assets/uploads/certifications/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
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
                                    $cert_path = 'uploads/certifications/' . $cert_file;
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

<div class="stela-card-page-wrapper">
    <div class="stela-reg-card">
        <!-- 1. Top Bar: Pill Toggle & Close Button -->
        <div class="stela-card-topbar">
            <div class="stela-pill-toggle">
                <span class="stela-pill-item">Single Request</span>
                <span class="stela-pill-item active">Employee Registration</span>
            </div>
            <a href="employees.php" class="stela-card-close" title="Back to Employees">
                <i class="fas fa-times"></i>
            </a>
        </div>

        <!-- 2. Header: Title & DaisyUI Stepper Bar -->
        <div class="stela-card-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="stela-card-title m-0"><span data-lang="add-new-request-employee">Registration Form – Add Employee</span></h2>
                <span class="daisy-badge daisy-badge-primary">DaisyUI Stepper</span>
            </div>
            
            <!-- DaisyUI Horizontal Steps Component -->
            <div class="daisy-steps-wrapper">
                <ul class="daisy-steps">
                    <li class="daisy-step active">
                        <div class="daisy-step-icon"><i class="fas fa-id-card"></i></div>
                        <span class="daisy-step-text" data-lang="identity-competency-data">Identitas</span>
                    </li>
                    <li class="daisy-step active">
                        <div class="daisy-step-icon"><i class="fas fa-layer-group"></i></div>
                        <span class="daisy-step-text" data-lang="competency">Kompetensi</span>
                    </li>
                    <li class="daisy-step active">
                        <div class="daisy-step-icon"><i class="fas fa-file-upload"></i></div>
                        <span class="daisy-step-text" data-lang="upload-docs-title">Berkas</span>
                    </li>
                    <li class="daisy-step">
                        <div class="daisy-step-icon"><i class="fas fa-certificate"></i></div>
                        <span class="daisy-step-text" data-lang="certification-competency">Sertifikasi</span>
                    </li>
                </ul>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="stela-card-alert success" style="margin: 0 36px 18px 36px; padding: 14px 18px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 14px; color: #166534;">
            <i class="fas fa-check-circle"></i> <strong>Success!</strong> <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="stela-card-alert error" style="margin: 0 36px 18px 36px; padding: 14px 18px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 14px; color: #991b1b;">
            <i class="fas fa-exclamation-circle"></i> <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- 3. Form Body -->
        <form method="POST" action="" enctype="multipart/form-data" id="addEmployeeForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">

            <div class="stela-card-body">
                
                <!-- Step 1: Identitas & Informasi Kerja -->
                <div class="stela-subcard">
                    <div class="stela-subcard-header">
                        <div class="stela-step-number">1</div>
                        <div class="stela-subcard-title-group">
                            <h4 class="stela-subcard-title" data-lang="identity-company-info">Informasi Identitas & Perusahaan</h4>
                            <p class="stela-subcard-subtitle" data-lang="identity-company-sub">Lengkapi ID Badge, nama lengkap, serta perusahaan/kontraktor karyawan.</p>
                        </div>
                    </div>
                    
                    <div class="stela-subcard-body">
                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label for="employee_code" class="stela-form-label" data-lang="id-badge-required">ID BADGE <span class="text-danger">*</span></label>
                                <div class="stela-input-icon-wrapper">
                                    <i class="fas fa-id-badge stela-input-icon"></i>
                                    <input type="text" class="form-control stela-pill-input" id="employee_code" name="employee_code"
                                           value="<?php echo isset($_POST['employee_code']) ? htmlspecialchars($_POST['employee_code']) : ''; ?>"
                                           required placeholder="Example: BADGE001" data-lang-placeholder="badge-example-placeholder">
                                </div>
                                <small class="stela-form-hint" data-lang="unique-id-badge-hint">Nomor ID unik untuk identifikasi badge karyawan</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="stela-form-label" data-lang="full-name">NAMA LENGKAP <span class="text-danger">*</span></label>
                                <div class="stela-input-icon-wrapper">
                                    <i class="fas fa-user stela-input-icon"></i>
                                    <input type="text" class="form-control stela-pill-input" id="full_name" name="full_name" 
                                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                                           required placeholder="Nama lengkap karyawan" data-lang-placeholder="full-name-of-employee-placeholder">
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="department" name="department" value="General">

                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label for="contractor_company" class="stela-form-label">
                                    <?php if (!empty($current_department)): ?>
                                        <span data-lang="department">PERUSAHAAN / DEPARTEMEN</span>
                                    <?php else: ?>
                                        <span data-lang="company">PERUSAHAAN / KONTRAKTOR</span>
                                    <?php endif; ?>
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="stela-input-icon-wrapper">
                                    <i class="fas fa-building stela-input-icon"></i>
                                    <select class="form-control stela-pill-input" id="contractor_company" name="contractor_company" required>
                                        <option value="" data-lang="select-company">-- Pilih Perusahaan --</option>
                                        <option value="PT Meares Soputan Mining" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Meares Soputan Mining') ? 'selected' : ''; ?>>PT Meares Soputan Mining</option>
                                        <option value="PT Tambang Tondano Nusajaya" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Tambang Tondano Nusajaya') ? 'selected' : ''; ?>>PT Tambang Tondano Nusajaya</option>
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
                                        <option value="PT Geopersada Mulia Abadi" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Geopersada Mulai Abadi') ? 'selected' : ''; ?>>PT Geopersada Mulai Abadi</option>
                                        <option value="PT Hidup Baru Sukses Mandiri" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Hidup Baru Sukses Mandiri') ? 'selected' : ''; ?>>PT Hidup Baru Sukses Mandiri</option>
                                        <option value="PT Intertek Utama Services" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Intertek Utama Services') ? 'selected' : ''; ?>>PT Intertek Utama Services</option>
                                        <option value="PT Macmahon Indonesia" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Macmahon Indonesia') ? 'selected' : ''; ?>>PT Macmahon Indonesia</option>
                                        <option value="PT Manado Karya Angrah" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Manado Karya Angrah') ? 'selected' : ''; ?>>PT Manado Karya Angrah</option>
                                        <option value="PT Samudera Mulai Abadi" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Samudera Mulai Abadi') ? 'selected' : ''; ?>>PT Samudera Mulai Abadi</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="position" class="stela-form-label" data-lang="position">JABATAN <span class="text-danger">*</span></label>
                                <div class="stela-input-icon-wrapper">
                                    <i class="fas fa-briefcase stela-input-icon"></i>
                                    <input type="text" class="form-control stela-pill-input" id="position" name="position" 
                                           value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>"
                                           required placeholder="Example: Rigger, HSE Superintendent" data-lang-placeholder="position-example-placeholder">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Klasifikasi Kompetensi & Lingkup Kerja -->
                <div class="stela-subcard">
                    <div class="stela-subcard-header">
                        <div class="stela-step-number">2</div>
                        <div class="stela-subcard-title-group">
                            <h4 class="stela-subcard-title" data-lang="competency-scope-title">Klasifikasi & Area Kompetensi</h4>
                            <p class="stela-subcard-subtitle" data-lang="competency-scope-sub">Pilih jenis kompetensi untuk menentukan area pengawasan dan sub-kompetensi.</p>
                        </div>
                    </div>
                    
                    <div class="stela-subcard-body">
                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label for="competency_type" class="stela-form-label" data-lang="competency-type">JENIS KOMPETENSI <span class="text-danger">*</span></label>
                                <div class="stela-input-icon-wrapper">
                                    <i class="fas fa-layer-group stela-input-icon"></i>
                                    <select class="form-control stela-pill-input" id="competency_type" name="competency_type" onchange="toggleCompetencyField()" required>
                                        <option value="" data-lang="select-competency-type">-- Pilih Jenis Kompetensi --</option>
                                        <option value="pengawas_operasional" data-lang="competency-type-operational-supervisor" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'pengawas_operasional') ? 'selected' : ''; ?>>Pengawas Operasional</option>
                                        <option value="pengawas_teknis" data-lang="competency-type-technical-supervisor" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'pengawas_teknis') ? 'selected' : ''; ?>>Pengawas Teknis</option>
                                        <option value="tenaga_teknis" data-lang="competency-type-technical-personnel" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'tenaga_teknis') ? 'selected' : ''; ?>>Tenaga Teknis</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3" id="supervision_area_group" style="display: none;">
                                <label for="supervision_area" class="stela-form-label" data-lang="supervision-area">AREA PENGAWASAN <span class="text-danger">*</span></label>
                                <div class="stela-input-icon-wrapper">
                                    <i class="fas fa-map-marker-alt stela-input-icon"></i>
                                    <select class="form-control stela-pill-input" id="supervision_area" name="supervision_area">
                                        <option value="" data-lang="select-supervision-area">-- Pilih Area Pengawasan --</option>
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
                            </div>

                            <div class="col-md-6 mb-3" id="competency_group" style="display: none;">
                                <label for="competency_name" class="stela-form-label" data-lang="competency">KOMPETENSI <span class="text-danger">*</span></label>
                                <div class="stela-input-icon-wrapper">
                                    <i class="fas fa-award stela-input-icon"></i>
                                    <select class="form-control stela-pill-input" id="competency_name" name="competency_name" onchange="loadSubCompetencies()">
                                        <option value="" data-lang="select-competency">-- Pilih Kompetensi --</option>
                                        <?php
                                        if (!empty($competencies_by_type['pengawas_operasional'])) {
                                            foreach ($competencies_by_type['pengawas_operasional'] as $comp):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($comp['competency_name']); ?>" data-id="<?php echo $comp['id']; ?>" data-type="pengawas_operasional" <?php echo (isset($_POST['competency_name']) && $_POST['competency_name'] == $comp['competency_name']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($comp['competency_name']); ?>
                                            </option>
                                        <?php
                                            endforeach;
                                        }
                                        if (!empty($competencies_by_type['pengawas_teknis'])) {
                                            foreach ($competencies_by_type['pengawas_teknis'] as $comp):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($comp['competency_name']); ?>" data-id="<?php echo $comp['id']; ?>" data-type="pengawas_teknis" <?php echo (isset($_POST['competency_name']) && $_POST['competency_name'] == $comp['competency_name']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($comp['competency_name']); ?>
                                            </option>
                                        <?php
                                            endforeach;
                                        }
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
                            </div>

                            <div class="col-md-6 mb-3" id="sub_competency_group" style="display: none;">
                                <label for="sub_competency" class="stela-form-label" data-lang="sub-competency">SUB KOMPETENSI</label>
                                <div class="stela-input-icon-wrapper">
                                    <i class="fas fa-list-ul stela-input-icon"></i>
                                    <select class="form-control stela-pill-input" id="sub_competency" name="sub_competency">
                                        <option value="" data-lang="select-sub-competency">-- Pilih Sub Kompetensi --</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3" id="ruang_lingkup_group" style="display: none;">
                                <label for="ruang_lingkup" class="stela-form-label" data-lang="scope-of-work">RUANG LINGKUP <span class="text-danger">*</span></label>
                                <div class="stela-input-icon-wrapper">
                                    <i class="fas fa-globe stela-input-icon"></i>
                                    <select class="form-control stela-pill-input" id="ruang_lingkup" name="ruang_lingkup">
                                        <option value="" data-lang="select-scope-of-work">-- Pilih Ruang Lingkup --</option>
                                        <option value="PT Meares Soputan Mining (MSM)" data-lang="scope-of-work-msm" <?php echo (isset($_POST['ruang_lingkup']) && $_POST['ruang_lingkup'] == 'PT Meares Soputan Mining (MSM)') ? 'selected' : ''; ?>>PT MSM</option>
                                        <option value="PT Tambang Tondano Nusajaya (TTN)" data-lang="scope-of-work-ttn" <?php echo (isset($_POST['ruang_lingkup']) && $_POST['ruang_lingkup'] == 'PT Tambang Tondano Nusajaya (TTN)') ? 'selected' : ''; ?>>PT TTN</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Unggah Berkas Persyaratan -->
                <div class="stela-subcard">
                    <div class="stela-subcard-header">
                        <div class="stela-step-number">3</div>
                        <div class="stela-subcard-title-group">
                            <h4 class="stela-subcard-title" data-lang="upload-docs-title">Unggah Berkas Persyaratan</h4>
                            <p class="stela-subcard-subtitle" data-lang="upload-docs-sub">Upload file CV dan Surat Pernyataan resmi dalam format PDF.</p>
                        </div>
                    </div>
                    
                    <div class="stela-subcard-body">
                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label for="cv_file" class="stela-form-label" data-lang="upload-cv">UPLOAD CV <span class="text-danger">*</span></label>
                                <div class="stela-file-box">
                                    <i class="fas fa-file-upload"></i>
                                    <input type="file" name="cv_file" id="cv_file" class="file-input" accept=".pdf" required>
                                    <span class="file-text"><span data-lang="upload-cv-file">Upload file CV</span> <span data-lang="pdf-max-5mb">(PDF, Max 5MB)</span></span>
                                    <span class="file-name"></span>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="statement_file" class="stela-form-label" data-lang="upload-statement-letter">UPLOAD SURAT PERNYATAAN <span class="text-danger">*</span></label>
                                <div class="stela-file-box">
                                    <i class="fas fa-file-signature"></i>
                                    <input type="file" name="statement_file" id="statement_file" class="file-input" accept=".pdf" required>
                                    <span class="file-text"><span data-lang="upload-tt-mgt-frs-008d">Upload TT-MGT-FRS-008D</span> <span data-lang="pdf-max-5mb">(PDF, Max 5MB)</span></span>
                                    <span class="file-name"></span>
                                </div>
                            </div>
                        </div>

                        <div class="stela-warn-box">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong data-lang="important-statement-letter">Penting - Surat Pernyataan:</strong>
                                <p data-lang="wet-signature-pdf-instruction">Surat pernyataan harus ditandatangani dengan tanda tangan basah (asli) dan di-scan dalam format PDF.</p>
                                <a href="https://drive.google.com/drive/folders/176NPnFCvAnzp2Mb9vrA2RC5OMA45Hga1?usp=sharing" target="_blank" class="stela-btn-download-template mt-2">
                                    <i class="fas fa-download"></i> <span data-lang="download-statement-letter-template">Unduh Template Surat Pernyataan</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Sertifikasi & Kompetensi Pendukung -->
                <div class="stela-subcard">
                    <div class="stela-subcard-header">
                        <div class="stela-step-number">4</div>
                        <div class="stela-subcard-title-group">
                            <h4 class="stela-subcard-title" data-lang="certification-competency">Sertifikasi & Kompetensi Pendukung</h4>
                            <p class="stela-subcard-subtitle" data-lang="cert-sub-title">Lengkapi data sertifikat sertifikasi yang dimiliki oleh karyawan.</p>
                        </div>
                    </div>
                    
                    <div class="stela-subcard-body">
                        <div id="certificationContainer" class="certifications-list">
                            <div class="certification-item stela-cert-card">
                                <div class="cert-item-header d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="m-0 font-weight-bold" style="color: #1e3a8a;"><i class="fas fa-file-certificate"></i> <span data-lang="certification-number-1">Sertifikasi #1</span></h5>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-4 mb-3">
                                        <label class="stela-form-label" data-lang="certification-name">NAMA SERTIFIKASI <span class="text-danger">*</span></label>
                                        <div class="stela-input-icon-wrapper">
                                            <i class="fas fa-certificate stela-input-icon"></i>
                                            <select name="certification_ids[]" class="form-control stela-pill-input cert-name-select" required onchange="updateIssuer(this)">
                                                <option value="" data-lang="select-certification">-- Pilih Sertifikasi --</option>
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
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="stela-form-label" data-lang="certificate-type">JENIS SERTIFIKAT <span class="text-danger">*</span></label>
                                        <div class="stela-input-icon-wrapper">
                                            <i class="fas fa-tag stela-input-icon"></i>
                                            <select name="cert_types[]" class="form-control stela-pill-input cert-type-select" required onchange="toggleOtherType(this)">
                                                <option value="" data-lang="select-type">-- Pilih Jenis --</option>
                                                <option value="Attendance/Participant" data-lang="attendance-participant">Attendance/Participant</option>
                                                <option value="Competent" data-lang="competent">Competent</option>
                                                <option value="Training" data-lang="training">Training</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3 other-type-input" style="display: none;">
                                        <label class="stela-form-label" data-lang="other-type">JENIS LAINNYA <span class="text-danger">*</span></label>
                                        <input type="text" name="cert_types_other[]" class="form-control stela-pill-input" placeholder="Masukkan jenis sertifikat" data-lang-placeholder="enter-certificate-type">
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="stela-form-label" data-lang="certificate-number">NOMOR SERTIFIKAT <span class="text-danger">*</span></label>
                                        <div class="stela-input-icon-wrapper">
                                            <i class="fas fa-hashtag stela-input-icon"></i>
                                            <input type="text" name="cert_numbers[]" class="form-control stela-pill-input" required placeholder="Nomor sertifikat" data-lang-placeholder="certificate-number-placeholder">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="stela-form-label" data-lang="issuing-authority">LEMBAGA PENERBIT <span class="text-danger">*</span></label>
                                        <div class="stela-input-icon-wrapper">
                                            <i class="fas fa-university stela-input-icon"></i>
                                            <input type="text" name="cert_issuers[]" class="form-control stela-pill-input cert-issuer-input" required placeholder="Nama penerbit" data-lang-placeholder="issuing-authority-name">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="stela-form-label" data-lang="valid-until">BERLAKU SAMPAI <span class="text-danger">*</span></label>
                                        <div class="validity-input-group d-flex gap-2 align-items-center">
                                            <input type="date" name="valid_untils[]" class="form-control stela-pill-input valid-until-date" onchange="handleDateChange(this)">
                                            <div class="form-check form-check-inline m-0">
                                                <input class="form-check-input lifetime-checkbox" type="checkbox" name="is_lifetimes[]" value="1" onchange="handleLifetimeToggle(this)">
                                                <label class="form-check-label small" data-lang="lifetime">Lifetime</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="stela-form-label" data-lang="upload-certificate">UPLOAD SERTIFIKAT <span class="text-danger">*</span></label>
                                        <div class="stela-file-box">
                                            <i class="fas fa-file-pdf"></i>
                                            <input type="file" name="cert_files[]" class="file-input cert-file-input" accept=".pdf" required>
                                            <span class="file-text"><span data-lang="upload-certificate-file-pdf">Upload PDF</span> <span data-lang="pdf-max-5mb">(Max 5MB)</span></span>
                                            <span class="file-name"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn stela-btn-add-cert mt-2" onclick="addCertification()">
                            <i class="fas fa-plus-circle"></i> <span data-lang="add-another-certification">Tambah Sertifikasi Lainnya</span>
                        </button>
                    </div>
                </div>

            </div> <!-- end .stela-card-body -->

            <!-- 4. Footer Banner: Info & Deep Navy Submit Button -->
            <div class="stela-card-footer">
                <div class="footer-info">
                    <div class="footer-title">STELA Verification Portal</div>
                    <div class="footer-desc" data-lang="after-employee-data-added-note">Submitted data will be reviewed by Department Admin before an Appointment Letter can be created.</div>
                </div>
                <div class="footer-buttons">
                    <a href="employees.php" class="btn stela-btn-cancel" data-lang="cancel">Cancel</a>
                    <button type="submit" class="btn stela-btn-submit">
                        <span data-lang="save-submit-verification">Submit Request</span> <i class="fas fa-paper-plane ml-1"></i>
                    </button>
                </div>
            </div>

        </form>
    </div>
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
        filterCompetencies(competencyType);
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
        } else if (competencyType === 'pengawas_teknis') {
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

// Dynamic File Upload Feedback Listener for DaisyUI File Boxes
function initFileUploadFeedback() {
    document.querySelectorAll('.stela-file-box').forEach(box => {
        const input = box.querySelector('input[type="file"]');
        const fileNameSpan = box.querySelector('.file-name');
        const icon = box.querySelector('i');
        
        if (!input || input.dataset.listenerAttached) return;
        input.dataset.listenerAttached = 'true';
        
        input.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                const file = this.files[0];
                box.classList.add('has-file');
                if (icon) {
                    icon.className = 'fas fa-check-circle text-success';
                }
                if (fileNameSpan) {
                    fileNameSpan.style.display = 'inline-flex';
                    fileNameSpan.innerHTML = `<i class="fas fa-file-pdf"></i> ${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)`;
                }
            } else {
                box.classList.remove('has-file');
                if (icon) {
                    icon.className = 'fas fa-file-pdf';
                }
                if (fileNameSpan) {
                    fileNameSpan.style.display = 'none';
                    fileNameSpan.innerHTML = '';
                }
            }
        });
    });
}
document.addEventListener('DOMContentLoaded', initFileUploadFeedback);
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
