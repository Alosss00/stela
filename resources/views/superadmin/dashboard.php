<?php
$page_title = 'Superadmin Dashboard';
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';

// Check if user is logged in and has superadmin permission
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . AUTH_BASE_URL . '/index.php');
    exit();
}

if ($_SESSION['role'] != 'superadmin') {
    // If not superadmin, redirect to their dashboard
    header('Location: ' . AUTH_BASE_URL . '/pages/admin/dashboard.php');
    exit();
}

$db = new Database();

// Get statistics
$stats = [
    'users' => 0,
    'companies' => 0,
    'departments' => 0,
    'employees' => 0,
    'total_requests' => 0,
    'waiting_approval' => 0,
    'accepted' => 0,
    'rejected' => 0
];

// Helper to safely execute query and fetch count
function fetchCount($db, $query) {
    $result = $db->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['count'] ?? 0;
    }
    return 0;
}

$stats['users'] = fetchCount($db, "SELECT COUNT(*) as count FROM users");
$stats['employees'] = fetchCount($db, "SELECT COUNT(*) as count FROM employees");
$stats['total_requests'] = fetchCount($db, "SELECT COUNT(*) as count FROM appointments");
$stats['waiting_approval'] = fetchCount($db, "SELECT COUNT(*) as count FROM appointments WHERE status IN ('pending', 'pending_admin_review')");
$stats['accepted'] = fetchCount($db, "SELECT COUNT(*) as count FROM appointments WHERE status = 'approved'");
$stats['rejected'] = fetchCount($db, "SELECT COUNT(*) as count FROM appointments WHERE status LIKE 'rejected%'");

// We might not have companies or departments tables depending on migration state,
// wrap these in try/catch or fail gracefully
$stats['companies'] = fetchCount($db, "SELECT COUNT(DISTINCT company_name) as count FROM users WHERE company_name IS NOT NULL AND company_name != ''");
$stats['departments'] = fetchCount($db, "SELECT COUNT(DISTINCT department) as count FROM users WHERE department IS NOT NULL AND department != ''");

// Get recent activities (Last 5 users registered for now)
$recent_activities = [];
$res_act = $db->query("SELECT username, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
if ($res_act) {
    while ($r = $res_act->fetch_assoc()) {
        $recent_activities[] = $r;
    }
}

// System Health Mock Data (can be dynamic later)
$es_status = 'Connected (Online)';
$db_status = 'Connected (Online)';
$sys_health = '100%';

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white shadow-sm border-0">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-1">Global System Control</h4>
                        <p class="mb-0 opacity-75">You have full access to manage all aspects of the STELA system.</p>
                    </div>
                    <i class="fas fa-shield-alt fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Users</h6>
                            <h3 class="mb-0 fw-bold"><?php echo number_format($stats['users']); ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-users text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Companies</h6>
                            <h3 class="mb-0 fw-bold"><?php echo number_format($stats['companies']); ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-building text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 border-start border-info border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Departments</h6>
                            <h3 class="mb-0 fw-bold"><?php echo number_format($stats['departments']); ?></h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-network-wired text-info fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Employees</h6>
                            <h3 class="mb-0 fw-bold"><?php echo number_format($stats['employees']); ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="fas fa-user-tie text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Request Status -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i> Request Status Overview</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-4">
                        <div class="col-4">
                            <h2 class="text-primary fw-bold"><?php echo number_format($stats['total_requests']); ?></h2>
                            <p class="text-muted mb-0">Total Requests</p>
                        </div>
                        <div class="col-4">
                            <h2 class="text-warning fw-bold"><?php echo number_format($stats['waiting_approval']); ?></h2>
                            <p class="text-muted mb-0">Waiting Approval</p>
                        </div>
                        <div class="col-4">
                            <h2 class="text-success fw-bold"><?php echo number_format($stats['accepted']); ?></h2>
                            <p class="text-muted mb-0">Accepted</p>
                        </div>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <?php 
                            $total = $stats['total_requests'] ?: 1; // prevent div by zero
                            $pct_acc = ($stats['accepted'] / $total) * 100;
                            $pct_wait = ($stats['waiting_approval'] / $total) * 100;
                            $pct_rej = ($stats['rejected'] / $total) * 100;
                        ?>
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct_acc; ?>%" aria-valuenow="<?php echo $pct_acc; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $pct_wait; ?>%" aria-valuenow="<?php echo $pct_wait; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $pct_rej; ?>%" aria-valuenow="<?php echo $pct_rej; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-server me-2 text-primary"></i> System Health</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-database me-2 text-muted"></i> MySQL Database</span>
                            <span class="badge bg-success rounded-pill"><?php echo $db_status; ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-search me-2 text-muted"></i> Elasticsearch</span>
                            <span class="badge bg-success rounded-pill"><?php echo $es_status; ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center border-bottom-0">
                            <span><i class="fas fa-heartbeat me-2 text-muted"></i> Overall Health</span>
                            <span class="text-success fw-bold"><?php echo $sys_health; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Recent Activities -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-bolt me-2 text-warning"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="users.php" class="btn btn-outline-primary text-start"><i class="fas fa-user-plus me-2"></i> Add New User</a>
                        <a href="roles_permissions.php" class="btn btn-outline-secondary text-start"><i class="fas fa-user-shield me-2"></i> Manage Roles & Permissions</a>
                        <a href="settings.php" class="btn btn-outline-info text-start"><i class="fas fa-cogs me-2"></i> Configure System Settings</a>
                        <a href="backup_restore.php" class="btn btn-outline-danger text-start"><i class="fas fa-hdd me-2"></i> Create Database Backup</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-history me-2 text-info"></i> Recent Registered Users</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Joined At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $act): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($act['username']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($act['role']); ?></span></td>
                                    <td><small class="text-muted"><?php echo date('d M Y H:i', strtotime($act['created_at'])); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_activities)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">No recent activities found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once dirname(__DIR__) . '/layouts/superadmin_footer.php'; ?>
