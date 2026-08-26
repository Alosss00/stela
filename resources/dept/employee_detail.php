<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Detail Karyawan';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php
require_once dirname(__DIR__) . '/layouts/header.php';

// Check access - only department users
if (!hasDepartment()) {
    $_SESSION['error'] = 'Unauthorized access';
    header('Location: employees.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$department = $_SESSION['department'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    $_SESSION['error'] = 'Invalid employee ID';
    header('Location: employees.php');
    exit();
}

// Get employee details with verified by information
$employee_query = "
    SELECT e.*,
           u.full_name as verified_by_name,
           u.username as verified_by_username
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    WHERE e.id = {$id} AND e.department = '" . $conn->real_escape_string($department) . "'
";

$employee_result = $conn->query($employee_query);
if (!$employee_result) {
    die('Database error: ' . $conn->error);
}

$employee = $employee_result->fetch_assoc();
if (!$employee) {
    ?>
    <div class="employee-detail-container" style="display: flex; justify-content: center; padding-top: 50px; min-height: 60vh;">
        <div style="max-width: 600px; width: 100%;">
            <div class="alert alert-error" style="padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h3 style="margin-top: 0; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: inherit;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i> Peringatan Akses
                </h3>
                <p style="margin-bottom: 25px; line-height: 1.6; font-size: 16px;">
                    Data karyawan tidak ditemukan atau Anda <strong>tidak memiliki hak akses</strong> untuk melihat data karyawan dari departemen lain.
                </p>
                <a href="employees.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar Karyawan
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once dirname(__DIR__) . '/layouts/footer.php';
    exit();
}

// Get employee certifications
$certs_query = "
    SELECT ec.*, c.cert_name, c.cert_type, c.issuing_authority
    FROM employee_certifications ec
    LEFT JOIN certifications c ON ec.certification_id = c.id
    WHERE ec.employee_id = {$id}
    ORDER BY ec.created_at DESC
";
$certifications = @$conn->query($certs_query);

// Get workflow history
require_once dirname(__DIR__, 2) . '/app/Services/AuditService.php';
$auditService = new AuditService();
$workflow_history = $auditService->getHistoryByEmployee($id);

// Define status class mapping
$status_class = [
    'verified' => 'verified',
    'approved' => 'verified',
    'pending' => 'pending',
    'rejected' => 'rejected',
    'expired' => 'expired'
];

// Define type labels
$competency_type_labels = [
    'pengawas_operasional' => 'Pengawas Operasional',
    'pengawas_teknis' => 'Pengawas Teknis',
    'tenaga_teknis' => 'Tenaga Teknis',
    'ahli' => 'Ahli'
];
?>

<div class="employee-detail-container">
    <!-- Employee Header -->
    <div class="employee-header-card">
        <div class="employee-header-content">
            <div class="employee-avatar">
                <?php echo strtoupper(substr($employee['full_name'], 0, 1)); ?>
            </div>
            <div class="employee-header-info" style="color: #495057;">
                <h2><?php echo htmlspecialchars($employee['full_name']); ?></h2>
                <p><i class="fas fa-id-badge"></i> <span data-lang="id-short">ID</span>: <?php echo htmlspecialchars($employee['employee_code']); ?></p>
                <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($employee['position'] ?? '-'); ?></p>
                <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['contractor_company'] ?? '-'); ?></p>
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
                <span class="info-value"><?php echo htmlspecialchars($employee['contractor_company'] ?? '-'); ?></span>
            </div>
        </div>

        <!-- Competency Info -->
        <div class="info-card">
            <h4><i class="fas fa-certificate"></i> <span data-lang="competency-information">Competency Information</span></h4>
            <div class="info-row">
                <span class="info-label" data-lang="scope-of-work">Scope:</span>
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
            <?php if ($employee['competency_type'] == 'pengawas_operasional' && !empty($employee['supervision_area'])): ?>
            <div class="info-row">
                <span class="info-label" data-lang="supervision-area">Supervision Area:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['supervision_area'] ?? '-'); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Status Info -->
        <div class="info-card">
            <h4><i class="fas fa-check-circle"></i> <span data-lang="status-verification">Status & Verification</span></h4>
            <div class="info-row">
                <span class="info-label" data-lang="status">Status:</span>
                <span class="info-value">
                    <?php
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
                    $badge_class = $status_class[$employee['verification_status']] ?? 'pending';
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
                    <?php if (!empty($employee['cv_file'])): ?>
                        <a href="<?php echo upload_url(htmlspecialchars($employee['cv_file'])); ?>" target="_blank" class="btn btn-sm btn-secondary">
                            <i class="fas fa-file-pdf"></i> <span data-lang="view-cv">View CV</span>
                        </a>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="department">Department:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['department'] ?? '-'); ?></span>
            </div>
            <?php if (($employee['verification_status'] == 'verified' || $employee['verification_status'] == 'rejected') && $employee['verified_by_name']): ?>
            <div class="info-row">
                <span class="info-label" data-lang="verified-by">Verified By:</span>
                <span class="info-value">
                    <?php echo htmlspecialchars($employee['verified_by_name']); ?><br>
                    <small class="text-muted">
                        <?php echo htmlspecialchars($employee['verified_by_username'] ?? ''); ?>
                        <?php if (!empty($employee['verified_date'])): ?>
                            <br><?php echo date('d/m/Y H:i', strtotime($employee['verified_date'])); ?>
                        <?php endif; ?>
                    </small>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($employee['verification_notes'])): ?>
    <div class="alert alert-info" style="margin-bottom: 20px;">
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
            <table class="cert-table" style="width: 100% !important; table-layout: fixed !important; width: 100% !important; table-layout: fixed !important;">
                <thead>
                    <tr>
                        <th data-lang="certificate-name">Certificate Name</th>
                        <th data-lang="certificate-no">Certificate No.</th>
                        <th data-lang="issuer">Issuer</th>
                        <th data-lang="issue-date">Issue Date</th>
                        <th data-lang="expiry-date">Expiry Date</th>
                        <th data-lang="status">Status</th>
                        <th data-lang="document">Document</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($certifications && $certifications->num_rows > 0): ?>
                        <?php while ($cert = $certifications->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cert['cert_name'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($cert['cert_number']); ?></td>
                            <td><?php echo htmlspecialchars($cert['cert_issuer'] ?? '-'); ?></td>
                            <td><?php echo $cert['issue_date'] ? date('d/m/Y', strtotime($cert['issue_date'])) : '-'; ?></td>
                            <td>
                                <?php 
                                if ($cert['expiry_date']) {
                                    $expiry = new DateTime($cert['expiry_date']);
                                    $now = new DateTime();
                                    $diff = $now->diff($expiry);
                                    
                                    echo date('d/m/Y', strtotime($cert['expiry_date']));
                                    
                                    if ($expiry < $now) {
                                        echo '<br><small class="text-danger">Expired</small>';
                                    } elseif ($diff->days <= 30) {
                                        echo '<br><small class="text-warning">Expiring soon</small>';
                                    }
                                } else {
                                    echo '<span class="text-muted">No expiration date</span>';
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
                            <td>
                                <?php if (!empty($cert['document_file'])): ?>
                                <a href="<?php echo upload_url(htmlspecialchars($cert['document_file'])); ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-data-row">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; display: block; margin-bottom: 10px;"></i>
                                No certificate data
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Workflow History Timeline -->
    <div class="info-card" style="margin-top: 25px; margin-bottom: 25px; grid-column: 1 / -1; width: 100%;">
        <h4><i class="fas fa-history"></i> <span data-lang="workflow-history">Workflow History</span></h4>
        <?php include dirname(__DIR__) . '/components/workflow_timeline.php'; ?>
    </div>

    <div class="action-buttons">
        <a href="employees.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <span data-lang="back">Back</span>
        </a>
    </div>
</div>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
