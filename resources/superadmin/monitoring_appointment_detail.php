<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Detail Appointment';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__) . '/layouts/superadmin_header.php';

$db = new Database();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get appointment and employee details
$query = "
    SELECT a.*, 
           e.full_name, e.employee_code, e.position, e.contractor_company, 
           e.competency_type, e.competency_name, e.ruang_lingkup, e.cv_file,
           p.position_name, p.position_type,
           u_admin.full_name as created_by_name
    FROM appointments a
    LEFT JOIN employees e ON a.employee_id = e.id
    LEFT JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u_admin ON a.created_by = u_admin.id
    WHERE a.id = $id
";
$result = $db->query($query);

if (!$result || $result->num_rows === 0) {
    ?>
    <div class="container-fluid py-5">
        <div class="alert alert-danger shadow-sm">
            <h4><i class="fas fa-exclamation-triangle"></i> Data Tidak Ditemukan</h4>
            <p>Data appointment dengan ID tersebut tidak ditemukan atau telah dihapus.</p>
            <a href="monitoring_appointments.php" class="btn btn-secondary mt-2">
                <i class="fas fa-arrow-left"></i> Kembali ke Monitoring Appointments
            </a>
        </div>
    </div>
    <?php
    require_once dirname(__DIR__) . '/layouts/footer.php';
    exit;
}

$appt = $result->fetch_assoc();

// Get certifications for this employee
$certs_query = "
    SELECT ec.*, c.cert_name 
    FROM employee_certifications ec
    LEFT JOIN certifications c ON ec.certification_id = c.id
    WHERE ec.employee_id = " . intval($appt['employee_id']) . "
";
$certs = $db->query($certs_query);

// Get KTT Approvals
$approvals_query = "
    SELECT ka.*, u.full_name as ktt_name, u.username
    FROM ktt_approvals ka
    LEFT JOIN users u ON ka.ktt_user_id = u.id
    WHERE ka.appointment_id = $id
    ORDER BY ka.approval_date ASC
";
$approvals = $db->query($approvals_query);

// Format Helpers
$statusBadge = '';
switch($appt['status']) {
    case 'approved': $statusBadge = '<span class="badge bg-success"><i class="fas fa-check-double"></i> Approved</span>'; break;
    case 'pending': $statusBadge = '<span class="badge bg-warning"><i class="fas fa-spinner fa-spin"></i> Processing</span>'; break;
    case 'rejected':
    case 'rejected_by_ktt': $statusBadge = '<span class="badge bg-danger"><i class="fas fa-ban"></i> Rejected</span>'; break;
    default: $statusBadge = '<span class="badge bg-secondary"><i class="fas fa-pencil-alt"></i> Draft</span>';
}

$competencyTypeLabels = [
    'pengawas_operasional' => 'Pengawas Operasional',
    'pengawas_teknis' => 'Pengawas Teknis',
    'tenaga_teknis' => 'Tenaga Teknis'
];
$compTypeDisplay = $competencyTypeLabels[$appt['competency_type']] ?? $appt['competency_type'];
?>

<style>
    .monitor-detail-dashboard { font-family: 'Inter', sans-serif; padding: 20px 0; }
    .detail-card { background: #fff; border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); margin-bottom: 24px; overflow: hidden; }
    .detail-card-header { background: #f8fafc; border-bottom: 1px solid #f0f2f5; padding: 18px 24px; font-weight: 600; font-size: 1.1rem; color: #1e293b; }
    .detail-card-body { padding: 24px; }
    .info-group { margin-bottom: 16px; }
    .info-label { display: block; font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px; letter-spacing: 0.5px; }
    .info-value { font-size: 1rem; color: #0f172a; font-weight: 500; }
</style>

<div class="container-fluid monitor-detail-dashboard">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">Appointment Detail</h2>
            <p class="text-muted mb-0">View comprehensive details for Appointment <?php echo htmlspecialchars($appt['appointment_number']); ?></p>
        </div>
        <div>
            <a href="monitoring_appointments.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Letter Information -->
        <div class="col-md-6">
            <div class="detail-card">
                <div class="detail-card-header">
                    <i class="fas fa-file-contract text-primary me-2"></i> Letter Information
                </div>
                <div class="detail-card-body">
                    <div class="row">
                        <div class="col-md-6 info-group">
                            <span class="info-label">Letter Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($appt['appointment_number'] ?: '-'); ?></span>
                        </div>
                        <div class="col-md-6 info-group">
                            <span class="info-label">Status</span>
                            <span class="info-value"><?php echo $statusBadge; ?></span>
                        </div>
                        <div class="col-md-6 info-group">
                            <span class="info-label">Date Created</span>
                            <span class="info-value"><?php echo date('d M Y, H:i', strtotime($appt['created_at'])); ?></span>
                        </div>
                        <div class="col-md-6 info-group">
                            <span class="info-label">Created By</span>
                            <span class="info-value"><?php echo htmlspecialchars($appt['created_by_name'] ?: 'System'); ?></span>
                        </div>
                        <div class="col-md-6 info-group">
                            <span class="info-label">Effective Date</span>
                            <span class="info-value"><?php echo $appt['effective_date'] ? date('d M Y', strtotime($appt['effective_date'])) : '-'; ?></span>
                        </div>
                        <div class="col-md-6 info-group">
                            <span class="info-label">Expiry Date</span>
                            <span class="info-value"><?php echo $appt['expiry_date'] ? date('d M Y', strtotime($appt['expiry_date'])) : '<span class="text-muted">No Expiry</span>'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Information -->
        <div class="col-md-6">
            <div class="detail-card">
                <div class="detail-card-header">
                    <i class="fas fa-user text-success me-2"></i> Employee Information
                </div>
                <div class="detail-card-body">
                    <div class="row">
                        <div class="col-md-6 info-group">
                            <span class="info-label">Name</span>
                            <span class="info-value fw-bold text-primary"><?php echo htmlspecialchars($appt['full_name']); ?></span>
                        </div>
                        <div class="col-md-6 info-group">
                            <span class="info-label">ID Badge</span>
                            <span class="info-value"><?php echo htmlspecialchars($appt['employee_code']); ?></span>
                        </div>
                        <div class="col-md-6 info-group">
                            <span class="info-label">Position</span>
                            <span class="info-value"><?php echo htmlspecialchars($appt['position'] ?: $appt['position_name']); ?></span>
                        </div>
                        <div class="col-md-6 info-group">
                            <span class="info-label">Company</span>
                            <span class="info-value"><?php echo htmlspecialchars($appt['contractor_company']); ?></span>
                        </div>
                        <div class="col-md-6 info-group">
                            <span class="info-label">Competency Type</span>
                            <span class="info-value"><?php echo htmlspecialchars($compTypeDisplay); ?></span>
                        </div>
                        <div class="col-md-6 info-group">
                            <span class="info-label">Work Scope</span>
                            <span class="info-value"><?php echo htmlspecialchars($appt['ruang_lingkup'] ?: '-'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approvals & Workflow History -->
    <div class="detail-card">
        <div class="detail-card-header">
            <i class="fas fa-tasks text-warning me-2"></i> Approval Status & History
        </div>
        <div class="detail-card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Step</th>
                            <th>Reviewer</th>
                            <th>Action</th>
                            <th>Date</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($approvals && $approvals->num_rows > 0): ?>
                            <?php while ($appr = $approvals->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">KTT Review</td>
                                <td>
                                    <?php echo htmlspecialchars($appr['ktt_name']); ?> 
                                    <small class="text-muted d-block">@<?php echo htmlspecialchars($appr['username']); ?></small>
                                </td>
                                <td>
                                    <?php if ($appr['action'] === 'approve'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Approved</span>
                                    <?php elseif ($appr['action'] === 'reject'): ?>
                                        <span class="badge bg-danger"><i class="fas fa-times"></i> Rejected</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning"><i class="fas fa-clock"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $appr['approval_date'] ? date('d M Y, H:i', strtotime($appr['approval_date'])) : '-'; ?></td>
                                <td><small><?php echo nl2br(htmlspecialchars($appr['approval_notes'] ?: '-')); ?></small></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="fas fa-clock mb-2 d-block"></i> No approval history yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Certifications -->
    <div class="detail-card">
        <div class="detail-card-header">
            <i class="fas fa-certificate text-info me-2"></i> Employee Certifications
        </div>
        <div class="detail-card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Certificate Name</th>
                            <th>Certificate No.</th>
                            <th>Issue Date</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($certs && $certs->num_rows > 0): ?>
                            <?php while ($cert = $certs->fetch_assoc()): 
                                $isExpired = $cert['expiry_date'] && strtotime($cert['expiry_date']) < time();
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo htmlspecialchars($cert['cert_name']); ?></td>
                                <td><?php echo htmlspecialchars($cert['cert_number']); ?></td>
                                <td><?php echo $cert['issue_date'] ? date('d M Y', strtotime($cert['issue_date'])) : '-'; ?></td>
                                <td>
                                    <?php if ($cert['expiry_date']): ?>
                                        <span class="<?php echo $isExpired ? 'text-danger fw-bold' : 'text-success'; ?>">
                                            <?php echo date('d M Y', strtotime($cert['expiry_date'])); ?>
                                            <?php if ($isExpired): ?> <span class="badge bg-danger ms-1">Expired</span> <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">No Expiry</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="fas fa-folder-open mb-2 d-block"></i> No certifications found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
