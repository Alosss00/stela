<?php
$page_title = 'Detail Employee';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/header.php';

// Only USER role can access this page
checkPageAccess(['user']);

// Initialize database and variables early (before POST handler)
$db = new Database();
$company_name = $_SESSION['company_name'] ?? '';
$message = '';
$error = '';

// Handle form submission for adding certificate
// Pastikan session sudah dimulai di bagian paling atas file PHP Anda
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_certificate') {
    
    // --- 1. VALIDASI TOKEN ANTI-CSRF ---
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Akses ditolak: Token keamanan tidak valid atau telah kedaluwarsa.';
    } else {
        
        // --- 2. LOGIKA UTAMA (Hanya berjalan jika lolos validasi CSRF) ---
        $employee_id = intval($_POST['employee_id']);
        
        // Verify employee belongs to this company
        $emp_check_result = $db->query("SELECT id FROM employees WHERE id = $employee_id AND contractor_company = '" . $db->escapeString($company_name) . "'");
        if (!$emp_check_result) {
            $error = 'Database error during verification.';
        } else {
            $emp_check = $emp_check_result->fetch_assoc();
            if (!$emp_check) {
                $error = 'Employee not found or not part of your company.';
            } else {
                $certification_id = intval($_POST['certification_id']);
                $cert_number = $db->escapeString($_POST['cert_number']);
                $issue_date = $db->escapeString($_POST['issue_date']);
                $expiry_date = !empty($_POST['expiry_date']) ? $db->escapeString($_POST['expiry_date']) : null;
                $expiry_reason = $db->escapeString($_POST['expiry_reason'] ?? '');
                $notes = $db->escapeString($_POST['notes'] ?? '');
                
                // Handle file upload
                $document_file = '';
                if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
                    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
                    $file_ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
                    
                    if (in_array($file_ext, $allowed_types) && $_FILES['document_file']['size'] <= 5242880) { // 5MB
                        $upload_dir = '../../assets/uploads/certifications/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_name = 'cert_' . $employee_id . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $file_path)) {
                            $document_file = $file_name;
                        } else {
                            $error = 'Failed to upload certificate file.';
                        }
                    } else {
                        $error = 'File format not supported or file size too large (max 5MB).';
                    }
                }
                
                if (!$error) {
                    // Determine status based on expiry date
                    $status = 'pending';
                    if ($expiry_date) {
                        $today = date('Y-m-d');
                        $status = ($expiry_date < $today) ? 'expired' : 'active';
                    }
                    
                    $sql = "INSERT INTO employee_certifications 
                            (employee_id, certification_id, cert_number, issue_date, expiry_date, document_file, status, verification_status, notes) 
                            VALUES ($employee_id, $certification_id, '$cert_number', '$issue_date', " . 
                            ($expiry_date ? "'$expiry_date'" : "NULL") . ", '$document_file', '$status', 'pending', '$notes')";
                    
                    if ($db->query($sql)) {
                        $message = 'Certificate successfully added! Waiting for Admin verification.';
                    } else {
                        $error = 'Failed to add certificate.';
                    }
                }
            }
        }
    } // End of CSRF else
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get employee details - ensure it belongs to this company
$employee_result = $db->query("
    SELECT e.*,
           u.full_name as verified_by_name,
           u.username as verified_by_username
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    WHERE e.id = $id AND e.contractor_company = '" . $db->escapeString($company_name) . "'
");

if (!$employee_result) {
    die('Database error: ' . $db->escapeString('Query failed'));
}

$employee = $employee_result->fetch_assoc();
if (!$employee) {
    header('Location: employees.php');
    exit();
}

// Get employee certifications
$certifications_result = $db->query("
    SELECT ec.*, c.cert_name, c.cert_type, c.issuing_authority
    FROM employee_certifications ec
    JOIN certifications c ON ec.certification_id = c.id
    WHERE ec.employee_id = $id
    ORDER BY ec.created_at DESC
");

$certifications = $certifications_result;

// Define type labels
$competency_type_labels = [
    'pengawas_operasional' => 'Pengawas Operasional',
    'pengawas_teknis' => 'Pengawas Teknis',
    'tenaga_teknis' => 'Tenaga Teknis'
];
?>

<style>
.employee-detail-container {
    max-width: 1400px;
    margin: 0 auto;
}

.employee-header-card {
    background: linear-gradient(135deg, #37474F 0%, #616161 100%);
    color: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.employee-header-content {
    display: flex;
    align-items: center;
    gap: 30px;
}

.employee-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: #37474F;
    font-weight: bold;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.employee-header-info h2 {
    margin: 0 0 10px 0;
    font-size: 28px;
}

.employee-header-info p {
    margin: 5px 0;
    opacity: 0.9;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.info-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-left: 4px solid #37474F;
}

.info-card h4 {
    margin: 0 0 15px 0;
    color: #37474F;
    font-size: 16px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.info-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #666;
    min-width: 150px;
}

.info-value {
    color: #333;
    flex: 1;
}

.cert-section {
    background: white;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.cert-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.cert-header h3 {
    margin: 0;
    color: #333;
    font-size: 20px;
}

.btn-add-cert {
    background: linear-gradient(135deg, #37474F 0%, #616161 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: transform 0.2s;
}

.btn-add-cert:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
}

.cert-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.cert-table thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    padding: 15px;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
}

.cert-table tbody td {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.cert-table tbody tr:hover {
    background-color: #f8f9fa;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.verified {
    background: #d4edda;
    color: #155724;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.expired {
    background: #e2e3e5;
    color: #383d41;
}

.verification-history {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.modal-content {
    max-width: 700px;
    border-radius: 10px;
}

.modal-header {
    background: linear-gradient(135deg, #37474F 0%, #616161 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 20px 25px;
}

.modal-header h3 {
    margin: 0;
    color: white;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group label {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    display: block;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #37474F;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.text-danger {
    color: #dc3545;
}

.no-data-row {
    text-align: center;
    padding: 40px !important;
    color: #999;
}

@media (max-width: 768px) {
    .employee-header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="employee-detail-container">
    <?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <!-- Employee Header -->
    <div class="employee-header-card">
        <div class="employee-header-content">
            <div class="employee-avatar">
                <?php echo strtoupper(substr($employee['full_name'], 0, 1)); ?>
            </div>
            <div class="employee-header-info">
                <h2><?php echo htmlspecialchars($employee['full_name']); ?></h2>
                <p><i class="fas fa-id-badge"></i> <span data-lang="id-short">ID</span>: <?php echo htmlspecialchars($employee['employee_code']); ?></p>
                <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($employee['position'] ?? '-'); ?></p>
                <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['contractor_company']); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Employee Information Grid -->
    <div class="info-grid">
        <!-- Basic Info -->
        <div class="info-card">
            <h4><i class="fas fa-user"></i> <span data-lang="basic-information">Basic Information</span></h4>
            <div class="info-row">
                <span class="info-label" data-lang="id-badge">ID Badge:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['employee_code']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="full-name">Full Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['full_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="position">Position:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['position'] ?? '-'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="company">Company:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['contractor_company']); ?></span>
            </div>
        </div>
        
        <!-- Competency Info -->
        <div class="info-card">
            <h4><i class="fas fa-certificate"></i> <span data-lang="competency-information">Competency Information</span></h4>
            <div class="info-row">
                <span class="info-label" data-lang="scope">Scope:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['ruang_lingkup'] ?? '-'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="competency-type">Competency Type:</span>
                <span class="info-value">
                    <?php
                    $type_key = $employee['competency_type'] ?? '';
                    $type_label = $competency_type_labels[$type_key] ?? $type_key;
                    echo htmlspecialchars($type_label);
                    ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="competency">Competency:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['competency_name'] ?? '-'); ?></span>
            </div>
            <?php if (!empty($employee['sub_competency']) || (($employee['competency_type'] ?? '') === 'tenaga_teknis')): ?>
            <div class="info-row">
                <span class="info-label" data-lang="sub-competency">Sub Competency:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['sub_competency'] ?? '-'); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Status Info -->
        <div class="info-card">
            <h4><i class="fas fa-check-circle"></i> <span data-lang="status-verification">Status & Verifikasi</span></h4>
            <div class="info-row">
                <span class="info-label" data-lang="status">Status:</span>
                <span class="info-value">
                    <?php
                    $status_badges = [
                        'verified' => 'verified',
                        'pending' => 'pending',
                        'rejected' => 'rejected'
                    ];
                    $status_labels = [
                        'verified' => 'Verified',
                        'pending' => 'Pending',
                        'rejected' => 'Rejected'
                    ];
                    $status_lang_keys = [
                        'verified' => 'verified',
                        'pending' => 'pending',
                        'rejected' => 'rejected'
                    ];
                    $badge_class = $status_badges[$employee['verification_status']] ?? 'pending';
                    $label = $status_labels[$employee['verification_status']] ?? strtoupper($employee['verification_status']);
                    ?>
                    <span class="status-badge <?php echo $badge_class; ?>" <?php echo isset($status_lang_keys[$employee['verification_status']]) ? 'data-lang="' . htmlspecialchars($status_lang_keys[$employee['verification_status']]) . '"' : ''; ?>>
                        <?php echo $label; ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="cv-file">CV File:</span>
                <span class="info-value">
                    <?php if ($employee['cv_file']): ?>
                        <a href="../../assets/<?php echo htmlspecialchars($employee['cv_file']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                            <i class="fas fa-file-pdf"></i> <span data-lang="view-cv">View CV</span>
                        </a>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (($employee['verification_status'] == 'verified' || $employee['verification_status'] == 'rejected') && $employee['verified_by_name']): ?>
            <div class="info-row">
                <span class="info-label" data-lang="verified-by">Verified By:</span>
                <span class="info-value">
                    <?php echo htmlspecialchars($employee['verified_by_name']); ?><br>
                    <small class="text-muted">
                        <?php echo htmlspecialchars($employee['verified_by_username']); ?>
                        <?php if ($employee['verified_date']): ?>
                            <br><?php echo date('d/m/Y H:i', strtotime($employee['verified_date'])); ?>
                        <?php endif; ?>
                    </small>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($employee['verification_notes']): ?>
    <div class="alert alert-info">
        <strong><i class="fas fa-info-circle"></i> <span data-lang="verification-notes">Verification Notes:</span></strong><br>
        <?php echo nl2br(htmlspecialchars($employee['verification_notes'])); ?>
    </div>
    <?php endif; ?>
    
    <!-- Certifications Section -->
    <div class="cert-section">
        <div class="cert-header">
            <h3><i class="fas fa-award"></i> <span data-lang="certification-list">Certification List</span></h3>
        </div>
        
        <div class="table-responsive">
            <table class="cert-table">
                <thead>
                    <tr>
                        <th data-lang="certificate-name">Certificate Name</th>
                        <th data-lang="certificate-type">Type</th>
                        <th data-lang="issuer">Issuer</th>
                        <th data-lang="certificate-no">Certificate No.</th>
                        <th data-lang="issue-date">Issue Date</th>
                        <th data-lang="expiry-date">Expiry Date</th>
                        <th data-lang="status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($certifications && $certifications->num_rows > 0): ?>
                        <?php 
                        // Reset result pointer to beginning
                        $certifications->data_seek(0);
                        while ($cert = $certifications->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cert['cert_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($cert['cert_type'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($cert['issuing_authority'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($cert['cert_number'] ?? '-'); ?></td>
                            <td><?php echo $cert['issue_date'] ? date('d/m/Y', strtotime($cert['issue_date'])) : '-'; ?></td>
                            <td>
                                <?php 
                                if ($cert['expiry_date']) {
                                    $expiry = new DateTime($cert['expiry_date']);
                                    $now = new DateTime();
                                    $diff = $now->diff($expiry);
                                    
                                    echo date('d/m/Y', strtotime($cert['expiry_date']));
                                    
                                    if ($expiry < $now) {
                                        echo '<br><small class="text-danger" data-lang="expired">Expired</small>';
                                    } elseif ($diff->days <= 30) {
                                        echo '<br><small class="text-warning" data-lang="expiring-soon">Expiring soon</small>';
                                    }
                                } else {
                                    echo '<span class="text-muted" data-lang="no-expiration-date">No expiration date</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $cert_status_class = [
                                    'verified' => 'verified',
                                    'pending' => 'pending',
                                    'rejected' => 'rejected',
                                    'expired' => 'expired'
                                ];
                                $cert_status_lang_keys = [
                                    'verified' => 'verified',
                                    'pending' => 'pending',
                                    'rejected' => 'rejected',
                                    'expired' => 'expired'
                                ];
                                $cert_badge = $cert_status_class[$cert['verification_status']] ?? 'pending';
                                ?>
                                <span class="status-badge <?php echo $cert_badge; ?>" <?php echo isset($cert_status_lang_keys[$cert['verification_status']]) ? 'data-lang="' . htmlspecialchars($cert_status_lang_keys[$cert['verification_status']]) . '"' : ''; ?>>
                                    <?php echo strtoupper($cert['verification_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data-row">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; display: block; margin-bottom: 10px;"></i>
                                <span data-lang="no-certificate-data">No certificate data</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="action-buttons">
        <a href="employees.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <span data-lang="back">Back</span>
        </a>
    </div>
</div>

<!-- Add Certificate Modal -->
<div id="addCertModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-certificate"></i> <span data-lang="add-employee-certificate">Add Employee Certificate</span></h3>
            <span class="close" onclick="closeModal('addCertModal')">&times;</span>
        </div>
    <form method="POST" action="" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
    
    <input type="hidden" name="action" value="add_certificate">
    <input type="hidden" name="employee_id" value="<?php echo $id; ?>">
    
    <div class="modal-body">
        <div class="form-group">
            <label data-lang="certification-type">Certification Type <span class="text-danger">*</span></label>
            <select name="certification_id" class="form-control" required>
                <option value="" data-lang="select-certification">-- Select Certification --</option>
                <?php
                $all_certs = $db->query("SELECT id, cert_name FROM certifications WHERE is_active = 1 ORDER BY cert_name");
                if ($all_certs && $all_certs->num_rows > 0):
                    while ($cert = $all_certs->fetch_assoc()):
                ?>
                <option value="<?php echo $cert['id']; ?>">
                    <?php echo htmlspecialchars($cert['cert_name']); ?>
                </option>
                <?php 
                    endwhile;
                endif;
                ?>
            </select>
        </div>
        <div class="form-group">
            <label data-lang="certificate-no">Certificate No. <span class="text-danger">*</span></label>
            <input type="text" name="cert_number" class="form-control" required placeholder="Enter certificate number" data-lang-placeholder="enter-certificate-number">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label data-lang="issue-date">Issue Date <span class="text-danger">*</span></label>
                <input type="date" name="issue_date" class="form-control issue-date" required onchange="calculateExpiryDate(this)">
            </div>
            <div class="form-group">
                <label data-lang="validity-period-years">Validity Period (Years) <span class="text-danger">*</span></label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="number" name="validity_years" class="form-control validity-years" min="0" step="0.5" placeholder="Example: 1, 2, 3" data-lang-placeholder="validity-years-example" onchange="calculateExpiryDate(this)">
                    <label style="white-space: nowrap; margin: 0;">
                        <input type="checkbox" name="no_expiry" class="no-expiry-check" onchange="toggleExpiryField(this)"> <span data-lang="no-expiry">No Expiry</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label data-lang="expiry-date">Expiry Date <span class="text-danger">*</span></label>
            <input type="date" name="expiry_date" class="form-control expiry-date" readonly style="background-color: #f0f0f0;">
        </div>
        <div class="form-group">
            <label data-lang="upload-certificate-document">Upload Certificate Document (PDF/JPG/PNG, Max 5MB)</label>
            <input type="file" name="document_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            <small class="text-muted" data-lang="supported-formats">Supported formats: PDF, JPG, PNG (Maximum 5MB)</small>
        </div>
        <div class="form-group">
            <label data-lang="notes">Notes</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes if needed" data-lang-placeholder="additional-notes-if-needed"></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addCertModal')">
            <i class="fas fa-times"></i> <span data-lang="cancel">Cancel</span>
        </button>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> <span data-lang="save">Save</span>
        </button>
    </div>
</form>
    </div>
</div>

<script>
function openAddCertModal() {
    openModal('addCertModal');
}

function calculateExpiryDate(element) {
    const modal = element.closest('.modal-content');
    const issueDate = modal.querySelector('.issue-date').value;
    const validityYears = parseFloat(modal.querySelector('.validity-years').value) || 0;
    const noExpiry = modal.querySelector('.no-expiry-check').checked;
    const expiryDateField = modal.querySelector('.expiry-date');
    
    if (noExpiry) {
        expiryDateField.value = '';
        return;
    }
    
    if (issueDate && validityYears > 0) {
        const date = new Date(issueDate);
        date.setFullYear(date.getFullYear() + Math.floor(validityYears));
        
        // Handle decimal years (months)
        const months = (validityYears % 1) * 12;
        date.setMonth(date.getMonth() + Math.round(months));
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        
        expiryDateField.value = `${year}-${month}-${day}`;
    } else {
        expiryDateField.value = '';
    }
}

function toggleExpiryField(checkboxElement) {
    const modal = checkboxElement.closest('.modal-content');
    const expiryDateField = modal.querySelector('.expiry-date');
    const validityYearsField = modal.querySelector('.validity-years');
    
    if (checkboxElement.checked) {
        expiryDateField.value = '';
        expiryDateField.setAttribute('readonly', true);
        expiryDateField.style.backgroundColor = '#f0f0f0';
        validityYearsField.setAttribute('readonly', true);
        validityYearsField.style.backgroundColor = '#f0f0f0';
        validityYearsField.value = '';
    } else {
        expiryDateField.removeAttribute('readonly');
        expiryDateField.style.backgroundColor = '#ffffff';
        validityYearsField.removeAttribute('readonly');
        validityYearsField.style.backgroundColor = '#ffffff';
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>



