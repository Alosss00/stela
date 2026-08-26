<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Certificate Monitoring';
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/MonitoringHelper.php';

checkPageAccess(['superadmin']);

$db = new Database();
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
    'cert_status' => $_GET['cert_status'] ?? ''
];

// Fetch Data
$certificatesData = $monitoring->getCertificates($page, $limit, $search, $filters);
$certificates = $certificatesData['data'];
$totalPages = $certificatesData['pages'];
$totalRecords = $certificatesData['total'];

// Stats
$stats = $monitoring->getCertificateStats($filters);

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
    .stat-card.valid { border-left-color: #10b981; }
    .stat-card.expiring { border-left-color: #f59e0b; }
    .stat-card.expired { border-left-color: #ef4444; }
    
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
    .status-valid { background: #dcfce7; color: #166534; }
    .status-expiring { background: #fef3c7; color: #b45309; }
    .status-expired { background: #fee2e2; color: #991b1b; }

    .action-btn { background: transparent; border: none; color: #64748b; padding: 6px 10px; border-radius: 6px; transition: 0.2s; font-size: 0.9rem;}
    .action-btn:hover { background: #e2e8f0; color: #0f172a; }
</style>

<div class="container-fluid monitor-dashboard">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">Certificate Monitoring</h2>
            <p class="text-muted mb-0">Track compliance and expiry of all employee certificates</p>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card total position-relative">
                <div class="stat-title">Total Certificates</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <i class="fas fa-certificate stat-icon text-primary"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card valid position-relative">
                <div class="stat-title">Valid</div>
                <div class="stat-value text-success"><?php echo number_format($stats['valid']); ?></div>
                <i class="fas fa-check-circle stat-icon text-success"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card expiring position-relative">
                <div class="stat-title">Expiring Soon (≤60 Days)</div>
                <div class="stat-value text-warning"><?php echo number_format($stats['expiring_soon']); ?></div>
                <i class="fas fa-exclamation-triangle stat-icon text-warning"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card expired position-relative">
                <div class="stat-title">Expired</div>
                <div class="stat-value text-danger"><?php echo number_format($stats['expired']); ?></div>
                <i class="fas fa-times-circle stat-icon text-danger"></i>
            </div>
        </div>
    </div>

    <div class="monitor-card">
        <div class="monitor-card-header">
            <!-- Filter Form -->
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, cert number..." value="<?php echo htmlspecialchars($search); ?>">
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
                    <select name="cert_status" class="form-select">
                        <option value="">All Status</option>
                        <option value="Valid" <?php echo $filters['cert_status'] === 'Valid' ? 'selected' : ''; ?>>Valid</option>
                        <option value="Expiring Soon" <?php echo $filters['cert_status'] === 'Expiring Soon' ? 'selected' : ''; ?>>Expiring Soon</option>
                        <option value="Expired" <?php echo $filters['cert_status'] === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-secondary w-100" title="Apply Filters"><i class="fas fa-filter"></i></button>
                </div>
            </form>
        </div>

        <div class="card-body p-4">
            <div class="mb-3 text-muted small">
                Showing <?php echo count($certificates); ?> of <?php echo number_format($totalRecords); ?> certificates
            </div>
            
            <div class="table-responsive">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Certificate Type</th>
                            <th>Certificate Number</th>
                            <th>Employee</th>
                            <th>Company & Dept</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($certificates)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-certificate fa-3x mb-3 d-block opacity-25"></i>No certificates found.</td></tr>
                        <?php else: ?>
                            <?php foreach($certificates as $cert): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($cert['master_cert_name'] ?: 'Custom/Other'); ?></div>
                                        <?php if (isset($cert['submission_count']) && (int)$cert['submission_count'] > 1): ?>
                                            <span class="badge bg-info text-white" style="font-size: 0.75em; margin-top: 4px;"><i class="fas fa-sync-alt"></i> Resubmitted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="font-monospace small"><?php echo htmlspecialchars($cert['cert_number']); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($cert['employee_name'] ?: 'Unknown'); ?></div>
                                        <div class="small text-muted">ID: <?php echo htmlspecialchars($cert['employee_code'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark"><?php echo htmlspecialchars($cert['company'] ?: 'Internal'); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($cert['department'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold <?php echo ($cert['monitoring_status'] === 'Expired') ? 'text-danger' : ''; ?>">
                                            <?php 
                                            if (empty($cert['expiry_date']) || $cert['expiry_date'] == '0000-00-00') {
                                                echo 'Lifetime / None';
                                            } else {
                                                echo date('d M Y', strtotime($cert['expiry_date'])); 
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $s = $cert['monitoring_status'];
                                        if($s === 'Valid') {
                                            echo '<span class="status-badge status-valid"><i class="fas fa-check-circle"></i> Valid</span>';
                                        } elseif($s === 'Expiring Soon') {
                                            echo '<span class="status-badge status-expiring"><i class="fas fa-exclamation-triangle"></i> Expiring Soon</span>';
                                        } else {
                                            echo '<span class="status-badge status-expired"><i class="fas fa-times-circle"></i> Expired</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($cert['monitoring_status'] === 'Expired' || $cert['monitoring_status'] === 'Expiring Soon'): ?>
                                            <a href="../admin/resubmit_certificate.php?id=<?php echo (int)$cert['id']; ?>" class="action-btn text-decoration-none" title="Resubmit Certificate">
                                                <i class="fas fa-upload text-warning"></i> Resubmit
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
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
