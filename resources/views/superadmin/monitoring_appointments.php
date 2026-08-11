<?php
$page_title = 'Appointment Monitoring';
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/MonitoringHelper.php';

checkPageAccess(['superadmin']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'submit') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
        exit;
    }
    
    $id = intval($_POST['id']);
    
    $appt = $db->query("SELECT requires_ktt_msm_review, requires_ktt_ttn_review, ktt_msm_status, ktt_ttn_status, status FROM appointments WHERE id = $id")->fetch_assoc();
    $is_resubmit = ($appt['requires_ktt_msm_review'] == 1 || $appt['requires_ktt_ttn_review'] == 1);

    if ($is_resubmit) {
        $update_parts = ["status = 'pending'"];
        if ($appt['requires_ktt_msm_review'] == 1) {
            $update_parts[] = "ktt_msm_status = 'pending'";
            $update_parts[] = "ktt1_approved_by = NULL";
            $update_parts[] = "ktt1_approved_date = NULL";
        }
        if ($appt['requires_ktt_ttn_review'] == 1) {
            $update_parts[] = "ktt_ttn_status = 'pending'";
            $update_parts[] = "ktt2_approved_by = NULL";
            $update_parts[] = "ktt2_approved_date = NULL";
        }
        $sql = "UPDATE appointments SET " . implode(', ', $update_parts) . " WHERE id = $id";
    } else {
        $sql = "UPDATE appointments SET status = 'pending', requires_ktt_msm_review = 1, requires_ktt_ttn_review = 1, ktt_msm_status = 'pending', ktt_ttn_status = 'pending' WHERE id = $id";
    }

    if ($db->query($sql)) {
        if ($is_resubmit) {
            if ($appt['requires_ktt_msm_review'] == 1) $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $id AND ktt_user_id = 7");
            if ($appt['requires_ktt_ttn_review'] == 1) $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $id AND ktt_user_id = 8");
        } else {
            $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $id");
        }

        try {
            $notifService = new NotificationService();
            $notifService->notifyKttForApproval($id, !$is_resubmit || $appt['requires_ktt_msm_review'] == 1, !$is_resubmit || $appt['requires_ktt_ttn_review'] == 1);
        } catch (Exception $e) {}

        $_SESSION['success_message'] = 'Appointment successfully submitted to KTT for approval.';
    } else {
        $_SESSION['error_message'] = 'Failed to submit appointment.';
    }
    header("Location: " . BASE_URL . "/pages/superadmin/monitoring_appointments.php");
    exit();
}
$monitoring = new MonitoringHelper($db);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 20;

// Filters
$search = $_GET['search'] ?? '';
$filters = [
    'company' => $_GET['company'] ?? '',
    'department' => $_GET['department'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Fetch Data
$appointmentsData = $monitoring->getAppointments($page, $limit, $search, $filters);
$appointments = $appointmentsData['data'];
$totalPages = $appointmentsData['pages'];
$totalRecords = $appointmentsData['total'];

// Stats
$stats = $monitoring->getAppointmentStats($filters);

// Dropdown Options
$companies = $monitoring->getCompanies();
$departments = $monitoring->getDepartments();

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>

<style>
    .monitor-dashboard { font-family: 'Inter', sans-serif; padding: 20px 0; }
    
    .stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        border-left: 4px solid transparent;
        transition: transform 0.2s;
        height: 100%;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-card.total { border-left-color: #3b82f6; }
    .stat-card.waiting { border-left-color: #f59e0b; }
    .stat-card.approved { border-left-color: #10b981; }
    .stat-card.rejected { border-left-color: #ef4444; }
    
    .stat-title { color: #64748b; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-value { color: #0f172a; font-size: 1.8rem; font-weight: 700; margin-top: 10px; }
    .stat-icon { font-size: 2rem; opacity: 0.2; position: absolute; right: 20px; top: 25px; }

    .monitor-card { background: #fff; border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
    .monitor-card-header { background: transparent; border-bottom: 1px solid #f0f2f5; padding: 18px 24px; font-weight: 600; }
    
    .table-modern { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    .table-modern th { border: none; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 10px 15px; }
    .table-modern td { background: #f8fafc; padding: 12px 15px; border: none; vertical-align: middle; }
    .table-modern td:first-child { border-radius: 8px 0 0 8px; }
    .table-modern td:last-child { border-radius: 0 8px 8px 0; }
    
    .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; }
    .status-badge i { margin-right: 5px; font-size: 0.7rem; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-pending { background: #fef3c7; color: #b45309; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .status-draft { background: #f1f5f9; color: #475569; }

    .action-btn { background: transparent; border: none; color: #64748b; padding: 6px 10px; border-radius: 6px; transition: 0.2s; font-size: 0.9rem;}
    .action-btn:hover { background: #e2e8f0; color: #0f172a; }
</style>

<div class="container-fluid monitor-dashboard">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">Appointment Monitoring</h2>
            <p class="text-muted mb-0">Track all employee working appointments (STELA) across the site</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card total position-relative">
                <div class="stat-title">Total Appointments</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <i class="fas fa-file-contract stat-icon text-primary"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card waiting position-relative">
                <div class="stat-title">In Progress</div>
                <div class="stat-value text-warning"><?php echo number_format($stats['waiting']); ?></div>
                <i class="fas fa-spinner stat-icon text-warning"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card approved position-relative">
                <div class="stat-title">Approved</div>
                <div class="stat-value text-success"><?php echo number_format($stats['approved']); ?></div>
                <i class="fas fa-check-double stat-icon text-success"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card rejected position-relative">
                <div class="stat-title">Rejected</div>
                <div class="stat-value text-danger"><?php echo number_format($stats['rejected']); ?></div>
                <i class="fas fa-ban stat-icon text-danger"></i>
            </div>
        </div>
    </div>

    <div class="monitor-card">
        <div class="monitor-card-header">
            <!-- Filter Form -->
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search Number, Employee Name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="company" class="form-select">
                        <option value="">All Companies</option>
                        <?php foreach($companies as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['contractor_company']); ?>" <?php echo $filters['company'] === $c['contractor_company'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['contractor_company']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach($departments as $d): ?>
                            <option value="<?php echo htmlspecialchars($d['department']); ?>" <?php echo $filters['department'] === $d['department'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending/In Progress</option>
                        <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-secondary w-100" title="Apply Filters"><i class="fas fa-filter"></i></button>
                </div>
            </form>
        </div>

        <div class="card-body p-4">
            <div class="mb-3 text-muted small">
                Showing <?php echo count($appointments); ?> of <?php echo number_format($totalRecords); ?> appointments
            </div>
            
            <div class="table-responsive">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Appointment No.</th>
                            <th>Employee</th>
                            <th>Company & Dept</th>
                            <th>Date Created</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($appointments)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-file-signature fa-3x mb-3 d-block opacity-25"></i>No appointments found.</td></tr>
                        <?php else: ?>
                            <?php foreach($appointments as $appt): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($appt['appointment_number']); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($appt['employee_name'] ?: 'Unknown'); ?></div>
                                        <div class="small text-muted">ID: <?php echo htmlspecialchars($appt['employee_code'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark"><?php echo htmlspecialchars($appt['company'] ?: 'Internal'); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($appt['department'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <div class="small"><?php echo date('d M Y, H:i', strtotime($appt['created_at'])); ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                        $s = $appt['status'];
                                        if($s === 'approved') {
                                            echo '<span class="status-badge status-approved"><i class="fas fa-check-double"></i> Approved</span>';
                                        } elseif($s === 'rejected' || $s === 'rejected_by_ktt') {
                                            echo '<span class="status-badge status-rejected"><i class="fas fa-ban"></i> Rejected</span>';
                                        } elseif($s === 'draft') {
                                            echo '<span class="status-badge status-draft"><i class="fas fa-pencil-alt"></i> Draft</span>';
                                        } else {
                                            echo '<span class="status-badge status-pending"><i class="fas fa-spinner fa-spin"></i> Processing</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($s === 'draft'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="submit">
                                            <input type="hidden" name="id" value="<?php echo $appt['id']; ?>">
                                            <button type="submit" class="action-btn text-primary border-0 bg-transparent" onclick="return confirm('Submit this appointment letter to KTT for approval?')" title="Submit to KTT">
                                                <i class="fas fa-paper-plane"></i> Submit
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <a href="<?php echo BASE_URL; ?>/pages/superadmin/monitoring_appointment_detail.php?id=<?php echo $appt['id']; ?>" class="action-btn" title="View Detail">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php 
                        $q = $_GET;
                        $q['page'] = max(1, $page - 1);
                        $prevUrl = '?' . http_build_query($q);
                        
                        $q['page'] = min($totalPages, $page + 1);
                        $nextUrl = '?' . http_build_query($q);
                        ?>
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $prevUrl; ?>">Previous</a>
                        </li>
                        
                        <?php for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php $q['page'] = $i; ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query($q); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $nextUrl; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
