<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Employee Monitoring';
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
    'status' => $_GET['status'] ?? ''
];

// Fetch Data
$employeesData = $monitoring->getEmployees($page, $limit, $search, $filters);
$employees = $employeesData['data'];
$totalPages = $employeesData['pages'];
$totalRecords = $employeesData['total'];

// Stats
$stats = $monitoring->getEmployeeStats($filters);

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
    .stat-card.accepted { border-left-color: #10b981; }
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
    .status-verified { background: #dcfce7; color: #166534; }
    .status-pending { background: #fef3c7; color: #b45309; }
    .status-rejected { background: #fee2e2; color: #991b1b; }

    .action-btn { background: transparent; border: none; color: #64748b; padding: 6px 10px; border-radius: 6px; transition: 0.2s; font-size: 0.9rem;}
    .action-btn:hover { background: #e2e8f0; color: #0f172a; }
</style>

<div class="container-fluid monitor-dashboard">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">Employee Monitoring Center</h2>
            <p class="text-muted mb-0">Global overview of all registered employees and contractors</p>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card total position-relative">
                <div class="stat-title">Total Employees</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <i class="fas fa-users stat-icon text-primary"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card waiting position-relative">
                <div class="stat-title">Pending Verification</div>
                <div class="stat-value text-warning"><?php echo number_format($stats['waiting']); ?></div>
                <i class="fas fa-clock stat-icon text-warning"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card accepted position-relative">
                <div class="stat-title">Verified</div>
                <div class="stat-value text-success"><?php echo number_format($stats['accepted']); ?></div>
                <i class="fas fa-check-circle stat-icon text-success"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card rejected position-relative">
                <div class="stat-title">Rejected</div>
                <div class="stat-value text-danger"><?php echo number_format($stats['rejected']); ?></div>
                <i class="fas fa-times-circle stat-icon text-danger"></i>
            </div>
        </div>
    </div>

    <div class="monitor-card">
        <div class="monitor-card-header">
            <!-- Filter Form -->
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, ID, NIK..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <option value="verified" <?php echo $filters['status'] === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-secondary w-100" title="Apply Filters"><i class="fas fa-filter"></i></button>
                </div>
            </form>
        </div>

        <div class="card-body p-4">
            <div class="mb-3 text-muted small d-flex justify-content-between align-items-center">
                <span>Showing <?php echo count($employees); ?> of <?php echo number_format($totalRecords); ?> records <?php echo isset($employeesData['source']) && $employeesData['source'] == 'elasticsearch' ? '<span class="badge bg-info text-dark ms-2"><i class="fas fa-bolt"></i> Fast Search</span>' : ''; ?></span>
                
                <div class="export-actions">
                    <?php 
                    $exportQuery = $_GET;
                    $exportQuery['type'] = 'employees';
                    $baseQuery = http_build_query($exportQuery);
                    ?>
                    <a href="export_monitoring.php?<?php echo $baseQuery; ?>&format=pdf" target="_blank" class="btn btn-sm btn-outline-danger me-1">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                    <a href="export_monitoring.php?<?php echo $baseQuery; ?>&format=excel" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Company & Dept</th>
                            <th>Position</th>
                            <th>Date Requested</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($employees)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>No employees found matching the criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach($employees as $emp): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($emp['full_name'] ?? ''); ?></div>
                                        <div class="small text-muted">ID: <?php echo htmlspecialchars($emp['employee_code'] ?? '-'); ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark"><?php echo htmlspecialchars($emp['contractor_company'] ?? 'Internal'); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($emp['department'] ?? '-'); ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold"><?php echo htmlspecialchars($emp['position'] ?? '-'); ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark"><?php echo isset($emp['created_at']) ? date('d M Y', strtotime($emp['created_at'])) : '-'; ?></div>
                                        <div class="small text-muted"><?php echo isset($emp['created_at']) ? date('H:i', strtotime($emp['created_at'])) : ''; ?></div>
                                    </td>
                                    <td>
                                        <?php $vStatus = $emp['verification_status'] ?? ($emp['approval_status'] ?? 'pending'); ?>
                                        <?php if($vStatus === 'verified'): ?>
                                            <span class="status-badge status-verified"><i class="fas fa-check"></i> Verified</span>
                                        <?php elseif($vStatus === 'rejected'): ?>
                                            <span class="status-badge status-rejected" title="<?php echo htmlspecialchars($emp['verification_notes'] ?? ''); ?>"><i class="fas fa-times"></i> Rejected</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if(($emp['verification_status'] ?? '') === 'pending'): ?>
                                        <a href="<?php echo BASE_URL; ?>/resources/admin/verify_employee.php?id=<?php echo $emp['id'] ?? 0; ?>" class="action-btn text-warning" title="Verify Data">
                                            <i class="fas fa-user-check"></i> Verify
                                        </a>
                                        <?php endif; ?>
                                        <a href="<?php echo BASE_URL; ?>/resources/admin/verify_employee.php?id=<?php echo $emp['id'] ?? 0; ?>" class="action-btn" title="View 360° Profile">
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
