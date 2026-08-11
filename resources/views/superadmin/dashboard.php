<?php
$page_title = 'Super Admin Control Center';
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/DashboardHelper.php';

// RBAC Check
requirePermission('dashboard.view');
requirePermission('admin.access'); 

if (!isSuperadmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$db = new Database();
$helper = new DashboardHelper($db);

$sysStatus = $helper->getSystemStatus();
$summaryStats = $helper->getSummaryStats();
$appointmentStats = $helper->getAppointmentStats();
$certificateStats = $helper->getCertificateStats();
$monthlyRequests = $helper->getMonthlyRequests();

$recentRequests = $helper->getRecentRequests(7);
$recentAppointments = $helper->getRecentAppointments(7);
$certAlerts = $helper->getCertificateExpirationAlerts(10);
$recentActivity = $helper->getRecentActivity(8);

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>

<style>
    /* Modern Dashboard Styling */
    .sa-dashboard { font-family: 'Inter', sans-serif; padding: 20px 0; }
    .sa-card { background: #fff; border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); margin-bottom: 24px; transition: transform 0.2s; }
    .sa-card:hover { transform: translateY(-3px); }
    .sa-card-header { background: transparent; border-bottom: 1px solid #f0f2f5; padding: 18px 24px; font-weight: 600; color: #2c3e50; display: flex; justify-content: space-between; align-items: center; }
    .sa-card-body { padding: 24px; }
    
    .stat-box { padding: 20px; border-radius: 12px; color: #fff; display: flex; align-items: center; justify-content: space-between; }
    .stat-box.blue { background: linear-gradient(135deg, #3a7bd5 0%, #3a6073 100%); }
    .stat-box.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .stat-box.orange { background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%); }
    .stat-box.red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
    
    .stat-info h3 { margin: 0; font-size: 2.2rem; font-weight: 700; }
    .stat-info p { margin: 0; font-size: 0.95rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-icon { font-size: 3rem; opacity: 0.4; }

    .chart-container { position: relative; height: 300px; width: 100%; }
    .small-chart-container { position: relative; height: 220px; width: 100%; }

    .table-modern { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    .table-modern th { border: none; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; padding: 10px 15px; }
    .table-modern td { background: #f8fafc; padding: 12px 15px; border: none; }
    .table-modern td:first-child { border-radius: 8px 0 0 8px; }
    .table-modern td:last-child { border-radius: 0 8px 8px 0; }
    
    .badge-modern { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.3px; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-warning { background: #fef08a; color: #854d0e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-info { background: #e0f2fe; color: #075985; }

    .qa-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px; background: #f8fafc; border-radius: 12px; color: #334155; text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; border: 1px solid transparent; }
    .qa-btn:hover { background: #fff; color: #3b82f6; border-color: #bfdbfe; transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .qa-btn i { font-size: 1.8rem; margin-bottom: 8px; color: #64748b; }
    .qa-btn:hover i { color: #3b82f6; }

    .activity-feed { list-style: none; padding: 0; margin: 0; }
    .activity-item { padding: 15px 0; border-bottom: 1px solid #f1f5f9; display: flex; gap: 15px; }
    .activity-item:last-child { border-bottom: none; }
    .activity-icon { width: 40px; height: 40px; border-radius: 50%; background: #e0f2fe; color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
    .activity-content { flex-grow: 1; }
    .activity-title { margin: 0 0 4px 0; font-size: 0.95rem; color: #334155; }
    .activity-time { font-size: 0.8rem; color: #94a3b8; margin: 0; }

    .sys-status-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed #e2e8f0; }
    .sys-status-item:last-child { border-bottom: none; }
    .status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; }
    .dot-on { background: #10b981; box-shadow: 0 0 8px rgba(16,185,129,0.5); }
    .dot-off { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,0.5); }
</style>

<div class="container-fluid sa-dashboard">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">STELA System Overview</h2>
            <p class="text-muted mb-0">Control Center & Global Monitoring</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="badge bg-white text-dark border p-2 rounded-pill shadow-sm">
                <i class="fas fa-clock text-primary me-2"></i> <?php echo $sysStatus['server_time']; ?>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
            <div class="stat-box blue">
                <div class="stat-info">
                    <h3><?php echo number_format($summaryStats['total_users']); ?></h3>
                    <p>Total Users</p>
                </div>
                <i class="fas fa-users stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
            <div class="stat-box green">
                <div class="stat-info">
                    <h3><?php echo number_format($summaryStats['total_employees']); ?></h3>
                    <p>Active Employees</p>
                </div>
                <i class="fas fa-id-badge stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
            <div class="stat-box orange">
                <div class="stat-info">
                    <h3><?php echo number_format($summaryStats['total_appointments']); ?></h3>
                    <p>Appointments</p>
                </div>
                <i class="fas fa-file-contract stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
            <div class="stat-box red">
                <div class="stat-info">
                    <h3><?php echo number_format($summaryStats['expired_certificates']); ?></h3>
                    <p>Expired Certs</p>
                </div>
                <i class="fas fa-exclamation-triangle stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="sa-card">
                <div class="sa-card-header">
                    <span><i class="fas fa-bolt text-warning me-2"></i> Quick Actions</span>
                </div>
                <div class="sa-card-body p-3">
                    <div class="row g-3">
                        <?php if(hasPermission('user.view')): ?>
                        <div class="col-md-2 col-sm-4 col-6">
                            <a href="../superadmin/users.php" class="qa-btn">
                                <i class="fas fa-users-cog"></i>
                                Manage Users
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(hasPermission('role.view')): ?>
                        <div class="col-md-2 col-sm-4 col-6">
                            <a href="../superadmin/roles.php" class="qa-btn">
                                <i class="fas fa-user-shield"></i>
                                Manage Roles
                            </a>
                        </div>
                        <?php endif; ?>

                        <?php if(hasPermission('employee.view')): ?>
                        <div class="col-md-2 col-sm-4 col-6">
                            <a href="../admin/employees.php" class="qa-btn">
                                <i class="fas fa-id-card"></i>
                                Employees
                            </a>
                        </div>
                        <?php endif; ?>

                        <?php if(hasPermission('appointment.view')): ?>
                        <div class="col-md-2 col-sm-4 col-6">
                            <a href="../admin/appointments.php" class="qa-btn">
                                <i class="fas fa-file-signature"></i>
                                Appointments
                            </a>
                        </div>
                        <?php endif; ?>

                        <?php if(hasPermission('certificate.view')): ?>
                        <div class="col-md-2 col-sm-4 col-6">
                            <a href="../admin/certificate_status.php" class="qa-btn">
                                <i class="fas fa-certificate"></i>
                                Certificates
                            </a>
                        </div>
                        <?php endif; ?>

                        <?php if(hasPermission('settings.view')): ?>
                        <div class="col-md-2 col-sm-4 col-6">
                            <a href="../admin/supervision_areas.php" class="qa-btn">
                                <i class="fas fa-cogs"></i>
                                System Settings
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Charts Area -->
        <div class="col-lg-8">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="sa-card h-100">
                        <div class="sa-card-header">Request Status</div>
                        <div class="sa-card-body">
                            <div class="small-chart-container">
                                <canvas id="requestChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="sa-card h-100">
                        <div class="sa-card-header">Appointment Status</div>
                        <div class="sa-card-body">
                            <div class="small-chart-container">
                                <canvas id="appointmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="sa-card h-100">
                        <div class="sa-card-header">Certificate Health</div>
                        <div class="sa-card-body">
                            <div class="small-chart-container">
                                <canvas id="certificateChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="sa-card h-100">
                        <div class="sa-card-header">Monthly Requests (6 Months)</div>
                        <div class="sa-card-body">
                            <div class="small-chart-container">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Appointments -->
            <div class="sa-card mb-4">
                <div class="sa-card-header">
                    <span><i class="fas fa-history me-2 text-primary"></i> Recent Appointments</span>
                    <a href="../admin/appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="sa-card-body p-0">
                    <div class="table-responsive p-3">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Appt. Number</th>
                                    <th>Employee</th>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recentAppointments)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No recent appointments.</td></tr>
                                <?php else: ?>
                                    <?php foreach($recentAppointments as $ra): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($ra['appointment_number']); ?></td>
                                        <td><?php echo htmlspecialchars($ra['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($ra['contractor_company']); ?></td>
                                        <td>
                                            <?php 
                                            $badge = 'badge-secondary';
                                            if ($ra['status'] == 'approved') $badge = 'badge-success';
                                            elseif ($ra['status'] == 'pending') $badge = 'badge-warning';
                                            elseif (strpos($ra['status'], 'reject') !== false) $badge = 'badge-danger';
                                            ?>
                                            <span class="badge-modern <?php echo $badge; ?>"><?php echo strtoupper(str_replace('_', ' ', $ra['status'])); ?></span>
                                        </td>
                                        <td class="text-muted text-nowrap"><?php echo date('d M Y', strtotime($ra['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Certificate Alerts -->
            <div class="sa-card mb-4">
                <div class="sa-card-header border-danger">
                    <span><i class="fas fa-exclamation-circle text-danger me-2"></i> Certificate Expiration Alerts</span>
                    <a href="../admin/certificate_status.php" class="btn btn-sm btn-outline-danger">Manage</a>
                </div>
                <div class="sa-card-body p-0">
                    <div class="table-responsive p-3">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Certificate</th>
                                    <th>Expiry Date</th>
                                    <th>Days Left</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($certAlerts)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No alerts at this time.</td></tr>
                                <?php else: ?>
                                    <?php foreach($certAlerts as $ca): ?>
                                    <tr>
                                        <td class="fw-bold">
                                            <?php echo htmlspecialchars($ca['full_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($ca['contractor_company']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($ca['certificate_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo date('d M Y', strtotime($ca['expiry_date'])); ?></td>
                                        <td class="fw-bold <?php echo ($ca['remaining_days'] < 0) ? 'text-danger' : 'text-warning'; ?>">
                                            <?php echo $ca['remaining_days']; ?> days
                                        </td>
                                        <td>
                                            <?php if($ca['remaining_days'] < 0): ?>
                                                <span class="badge-modern badge-danger">EXPIRED</span>
                                            <?php else: ?>
                                                <span class="badge-modern badge-warning">EXPIRING</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- System Status -->
            <div class="sa-card mb-4">
                <div class="sa-card-header">
                    <span><i class="fas fa-server text-success me-2"></i> System Status</span>
                </div>
                <div class="sa-card-body">
                    <div class="sys-status-item">
                        <span class="text-muted fw-bold">Database</span>
                        <span>
                            <span class="status-dot <?php echo $sysStatus['db_connected'] ? 'dot-on' : 'dot-off'; ?>"></span>
                            <?php echo $sysStatus['db_connected'] ? 'Connected' : 'Disconnected'; ?>
                        </span>
                    </div>
                    <div class="sys-status-item">
                        <span class="text-muted fw-bold">Elasticsearch</span>
                        <span>
                            <span class="status-dot <?php echo $sysStatus['es_connected'] ? 'dot-on' : 'dot-off'; ?>"></span>
                            <?php echo $sysStatus['es_connected'] ? 'Connected' : 'Disconnected'; ?>
                        </span>
                    </div>
                    <div class="sys-status-item">
                        <span class="text-muted fw-bold">PHP Version</span>
                        <span class="text-dark fw-bold"><?php echo htmlspecialchars($sysStatus['php_version']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Recent Requests -->
            <div class="sa-card mb-4">
                <div class="sa-card-header">
                    <span><i class="fas fa-user-clock text-info me-2"></i> Recent Requests</span>
                </div>
                <div class="sa-card-body p-0">
                    <ul class="activity-feed p-4">
                        <?php if(empty($recentRequests)): ?>
                            <li class="text-center text-muted">No recent requests.</li>
                        <?php else: ?>
                            <?php foreach($recentRequests as $rr): ?>
                            <li class="activity-item">
                                <div class="activity-icon bg-light">
                                    <i class="fas fa-user-plus text-primary"></i>
                                </div>
                                <div class="activity-content">
                                    <h4 class="activity-title">
                                        <strong><?php echo htmlspecialchars($rr['full_name']); ?></strong> 
                                        requested verification.
                                    </h4>
                                    <p class="activity-time">
                                        <?php echo htmlspecialchars($rr['contractor_company']); ?> &bull; 
                                        <?php echo date('d M Y, H:i', strtotime($rr['created_at'])); ?>
                                    </p>
                                    <?php 
                                        $vbadge = 'badge-warning';
                                        if($rr['verification_status'] == 'verified') $vbadge = 'badge-success';
                                        if($rr['verification_status'] == 'rejected') $vbadge = 'badge-danger';
                                    ?>
                                    <span class="badge-modern <?php echo $vbadge; ?> mt-1 d-inline-block">
                                        <?php echo strtoupper($rr['verification_status']); ?>
                                    </span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Recent Activity Log -->
            <div class="sa-card mb-4">
                <div class="sa-card-header">
                    <span><i class="fas fa-stream text-secondary me-2"></i> System Activity</span>
                </div>
                <div class="sa-card-body p-0">
                    <ul class="activity-feed p-4">
                        <?php if(empty($recentActivity)): ?>
                            <li class="text-center text-muted">No activity logged yet.</li>
                        <?php else: ?>
                            <?php foreach($recentActivity as $act): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-check-double text-success"></i>
                                </div>
                                <div class="activity-content">
                                    <h4 class="activity-title">
                                        <strong><?php echo htmlspecialchars($act['user_name'] ?: 'System'); ?></strong> 
                                        <?php echo htmlspecialchars($act['action']); ?>
                                        <strong><?php echo htmlspecialchars($act['employee_name'] ?: ''); ?></strong>
                                    </h4>
                                    <p class="activity-time"><?php echo date('d M Y, H:i', strtotime($act['created_at'])); ?></p>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Scripts for Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Common Chart Options
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { family: 'Inter' } } }
        }
    };

    // 1. Request Status Chart
    new Chart(document.getElementById('requestChart'), {
        type: 'doughnut',
        data: {
            labels: ['Waiting', 'Accepted', 'Rejected'],
            datasets: [{
                data: [
                    <?php echo $summaryStats['waiting_requests']; ?>, 
                    <?php echo $summaryStats['accepted_requests']; ?>, 
                    <?php echo $summaryStats['rejected_requests']; ?>
                ],
                backgroundColor: ['#fef08a', '#dcfce7', '#fee2e2'],
                borderColor: ['#eab308', '#22c55e', '#ef4444'],
                borderWidth: 1
            }]
        },
        options: { ...commonOptions, cutout: '65%' }
    });

    // 2. Appointment Status Chart
    new Chart(document.getElementById('appointmentChart'), {
        type: 'doughnut',
        data: {
            labels: ['Waiting', 'Approved', 'Rejected'],
            datasets: [{
                data: [
                    <?php echo $appointmentStats['waiting']; ?>, 
                    <?php echo $appointmentStats['approved']; ?>, 
                    <?php echo $appointmentStats['rejected']; ?>
                ],
                backgroundColor: ['#fef08a', '#dcfce7', '#fee2e2'],
                borderColor: ['#eab308', '#22c55e', '#ef4444'],
                borderWidth: 1
            }]
        },
        options: { ...commonOptions, cutout: '65%' }
    });

    // 3. Certificate Health Chart
    new Chart(document.getElementById('certificateChart'), {
        type: 'pie',
        data: {
            labels: ['Valid', 'Expiring Soon', 'Expired'],
            datasets: [{
                data: [
                    <?php echo $certificateStats['valid']; ?>, 
                    <?php echo $certificateStats['expiring_soon']; ?>, 
                    <?php echo $certificateStats['expired']; ?>
                ],
                backgroundColor: ['#dcfce7', '#fef08a', '#fee2e2'],
                borderColor: ['#22c55e', '#eab308', '#ef4444'],
                borderWidth: 1
            }]
        },
        options: commonOptions
    });

    // 4. Monthly Requests Chart (Line)
    <?php 
        $months = array_keys($monthlyRequests);
        $counts = array_values($monthlyRequests);
    ?>
    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Requests',
                data: <?php echo json_encode($counts); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [2, 4] } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
