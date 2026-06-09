    <?php
$page_title = 'Detail Karyawan';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/header.php';

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
    header('Location: employees.php');
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
            <div class="employee-header-info">
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
                        <a href="../../assets/<?php echo htmlspecialchars($employee['cv_file']); ?>" target="_blank" class="btn btn-sm btn-secondary">
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
            <table class="cert-table">
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
                                <a href="../../assets/<?php echo htmlspecialchars($cert['document_file']); ?>" target="_blank" class="btn btn-sm btn-info">
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
                            <td colspan="7" class="no-data-row">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; display: block; margin-bottom: 10px;"></i>
                                No certificate data
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

<style>
.employee-detail-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.employee-header-card {
    background: linear-gradient(135deg, #37474F 0%, #616161 100%);
    color: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #37474F;
}

.info-card h4 {
    margin: 0 0 15px 0;
    color: #37474F;
    font-size: 16px;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    gap: 8px;
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
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    border-left: 4px solid #37474F;
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
    display: flex;
    align-items: center;
    gap: 10px;
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
    font-size: 13px;
}

.cert-table tbody td {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
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

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
    border-radius: 5px;
    text-decoration: none;
    cursor: pointer;
    border: none;
    font-size: 14px;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.btn-primary {
    background: #37474F;
    color: white;
}

.btn-primary:hover {
    background: #263238;
    transform: translateY(-2px);
}

.btn-info {
    background: #37474F;
    color: white;
}

.btn-info:hover {
    background: #263238;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.no-data-row {
    text-align: center;
    padding: 40px !important;
    color: #999;
}

.text-muted {
    color: #6c757d;
}

.text-danger {
    color: #dc3545;
    font-weight: 500;
}

.text-warning {
    color: #ffc107;
    font-weight: 500;
}

.table-responsive {
    overflow-x: auto;
}

.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.alert-info {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

@media (max-width: 768px) {
    .employee-header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .employee-detail-container {
        padding: 15px;
    }
    
    .info-label {
        min-width: 120px;
        font-size: 13px;
    }
    
    .info-value {
        font-size: 13px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>






