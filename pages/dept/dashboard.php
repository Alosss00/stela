<?php
$page_title = 'Dashboard';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Only department_user role, user with department, or superadmin can access this page
if (!hasDepartment() && $_SESSION['role'] != 'department_user' && $_SESSION['role'] != 'superadmin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

require_once '../../includes/header.php';

$db = new Database();
$is_superadmin = isSuperadmin();
$department = $_SESSION['department'] ?? '';
if ($is_superadmin) {
    $department = 'All Departments';
}

// Department condition for single table queries (employees)
$department_condition_single = $is_superadmin
    ? '1=1'
    : "department = '" . $db->escapeString($_SESSION['department'] ?? '') . "'";

// Department condition for queries with employee table aliased as 'e'
$department_condition_joined = $is_superadmin
    ? '1=1'
    : "e.department = '" . $db->escapeString($_SESSION['department'] ?? '') . "'";

// Get statistics for current department
$total_employees = $db->query("SELECT COUNT(*) as count FROM employees WHERE $department_condition_single AND is_active = 1")->fetch_assoc()['count'];
$verified_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE $department_condition_single AND verification_status = 'verified' AND is_active = 1")->fetch_assoc()['count'];
$pending_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE $department_condition_single AND verification_status = 'pending' AND is_active = 1")->fetch_assoc()['count'];
$rejected_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE $department_condition_single AND verification_status = 'rejected' AND is_active = 1")->fetch_assoc()['count'];

// Get appointment statistics
$total_appointments = $db->query("
    SELECT COUNT(*) as count 
    FROM appointments a 
    JOIN employees e ON a.employee_id = e.id 
    WHERE $department_condition_joined
")->fetch_assoc()['count'];

$approved_appointments = $db->query("
    SELECT COUNT(*) as count 
    FROM appointments a 
    JOIN employees e ON a.employee_id = e.id 
    WHERE $department_condition_joined AND a.status = 'approved'
")->fetch_assoc()['count'];

$rejected_appointments = $db->query("
    SELECT COUNT(*) as count 
    FROM appointments a 
    JOIN employees e ON a.employee_id = e.id 
    WHERE $department_condition_joined AND a.status = 'rejected'
")->fetch_assoc()['count'];

$pending_appointments = $db->query("
    SELECT COUNT(*) as count 
    FROM appointments a 
    JOIN employees e ON a.employee_id = e.id 
    WHERE $department_condition_joined AND a.status = 'pending'
")->fetch_assoc()['count'];

// Get certificate expiration statistics (certificates expiring in 2 months or less) for this department
$expiring_certs_count = $db->query("
    SELECT COUNT(DISTINCT e.id) as count
    FROM employee_certifications ec
    JOIN employees e ON ec.employee_id = e.id
    WHERE ec.expiry_date IS NOT NULL
    AND ec.verification_status = 'verified'
    AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH)
    AND ec.expiry_date >= CURDATE()
    AND e.is_active = 1
    AND ($department_condition_joined)
")->fetch_assoc()['count'];

// Get recent appointments for this department with approval information
$recent_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, p.position_name,
           u1.full_name as created_by_name,
           u3.full_name as ktt1_approved_name,
           u4.full_name as ktt2_approved_name,
           CASE 
               WHEN a.status = 'approved' THEN 'success'
               WHEN a.status = 'pending' THEN 'warning'
               WHEN a.status = 'rejected' THEN 'danger'
               ELSE 'secondary'
           END as status_class
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u1 ON a.created_by = u1.id
    LEFT JOIN users u3 ON a.ktt1_approved_by = u3.id
    LEFT JOIN users u4 ON a.ktt2_approved_by = u4.id
    WHERE $department_condition_joined
    ORDER BY a.created_at DESC
    LIMIT 15
");
?>

<div class="dashboard">
    <div class="page-header">
        <div class="header-content">
            <h2><span data-lang="dashboard">Dashboard</span> - <?php echo htmlspecialchars($department); ?></h2>
            <p data-lang="welcome-competency-appointment-letter-system">Welcome to the competency appointment letter system</p>
        </div>
    </div>
    
    <!-- Certificate Expiration Alert -->
    <?php if ($expiring_certs_count > 0): ?>
    <div class="alert alert-danger" style="margin: 20px 0; padding: 20px; background: #fee; border-left: 4px solid #dc3545; border-radius: 8px; box-shadow: 0 2px 8px rgba(220, 53, 69, 0.15);">
        <div style="display: flex; align-items: flex-start; gap: 15px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 28px; color: #dc3545; flex-shrink: 0; margin-top: 3px;"></i>
            <div style="flex: 1;">
                <strong style="color: #721c24; display: block; margin-bottom: 10px; font-size: 16px;">
                    <i class="fas fa-certificate"></i> <span data-lang="certificates-expiring-soon">Certificates Expiring Soon!</span>
                </strong>
                <p style="margin: 0 0 15px 0; color: #721c24; font-size: 14px;">
                    <span data-lang="there-are">There are</span> <strong><?php echo $expiring_certs_count; ?></strong> <span data-lang="expiring-certificate-message-suffix">employees with certificates expiring within = 2 months. Please check and update their certificates.</span>
                </p>
                <a href="reports.php#certificate-expiration" style="color: #dc3545; font-size: 13px; font-weight: 600; text-decoration: none;">
                    <i class="fas fa-list"></i> <span data-lang="view-certificate-details">View Certificate Details</span>
                </a>
            </div>
        </div>
    </div>
    <section class="stats-section" style="margin-top: 30px;">
        <div class="expiring-cert-card">
            <div class="expiring-cert-badge">
                <i class="fas fa-exclamation-triangle"></i> URGENT
            </div>
            <div class="expiring-cert-content">
                <div class="expiring-cert-header">
                    <i class="fas fa-certificate"></i>
                    <h3 data-lang="certificates-about-to-expire">Certificates About to Expire</h3>
                </div>
                <div class="expiring-cert-body">
                    <div class="expiring-cert-number"><?php echo $expiring_certs_count; ?></div>
                    <div class="expiring-cert-desc" data-lang="expiring-certificate-desc">Employees with certificates expiring within = 2 months</div>
                    <a href="reports.php#certificate-expiration" class="expiring-cert-action">
                        <span data-lang="view-certificate-details">View Certificate Details</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Stats Grid Section -->
    <section class="stats-section">
        <h4 class="section-subtitle" data-lang="employee-statistics">Employee Statistics</h4>
        <div class="stats-grid stats-grid-4">
            <div class="stat-card stat-card-primary">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_employees; ?></h3>
                    <p data-lang="all-requests">All Request</p>
                </div>
            </div>
            
            <div class="stat-card stat-card-success">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $verified_count; ?></h3>
                    <p data-lang="accepted">Accepted</p>
                </div>
            </div>
            
            <div class="stat-card stat-card-warning">
                <div class="stat-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pending_count; ?></h3>
                    <p data-lang="waiting-reviewer">Waiting Reviewer</p>
                </div>
            </div>
            
            <a href="employees.php?status=rejected" class="stat-card stat-card-danger stat-card-clickable" style="text-decoration: none; color: inherit;">
                <div class="stat-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $rejected_count; ?></h3>
                    <p data-lang="rejected">Rejected</p>
                </div>
            </a>
        </div>
    </section>
    
    <!-- Appointments Stats Section -->
    <section class="stats-section">
        <h4 class="section-subtitle" data-lang="appointment-letter-statistics">Appointment Letter Statistics</h4>
        <div class="stats-grid">
            <div class="stat-card stat-card-info">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_appointments; ?></h3>
                    <p data-lang="all-letters">All Letters</p>
                </div>
            </div>
            
            <div class="stat-card stat-card-success">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $approved_appointments; ?></h3>
                    <p data-lang="accepted-ktt">Accepted KTT</p>
                </div>
            </div>
            
            <div class="stat-card stat-card-danger">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $rejected_appointments; ?></h3>
                    <p data-lang="rejected-ktt">Rejected KTT</p>
                </div>
            </div>
            
            <div class="stat-card stat-card-warning">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pending_appointments; ?></h3>
                    <p data-lang="waiting-ktt">Waiting KTT</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Recent Appointments -->
    <section class="recent-section">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> <span data-lang="history-assign-letter">History Assign Letter</span></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th data-lang="registration-no">Registration No.</th>
                                <th data-lang="employee">Employee</th>
                                <th data-lang="position">Position</th>
                                <th data-lang="effective-date">Effective Date</th>
                                <th data-lang="status">Status</th>
                                <th data-lang="approval">Approval</th>
                                <th data-lang="action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_appointments && $recent_appointments->num_rows > 0): ?>
                                <?php while ($row = $recent_appointments->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong></td>
                                    <td>
                                        <div class="employee-info">
                                            <span class="emp-code"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                                            <span class="emp-name"><?php echo htmlspecialchars($row['employee_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['position_name'] ?? '-'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['effective_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['status_class']; ?>">
                                            <?php 
                                            $status_label = [
                                                'draft' => ['key' => 'draft', 'text' => 'DRAFT'],
                                                'pending' => ['key' => 'waiting-reviewer', 'text' => 'WAITING REVIEWER'],
                                                'approved' => ['key' => 'accepted', 'text' => 'ACCEPTED'],
                                                'rejected' => ['key' => 'rejected', 'text' => 'REJECTED']
                                            ];
                                            $label = $status_label[$row['status']] ?? ['key' => strtolower($row['status']), 'text' => strtoupper($row['status'])];
                                            echo '<span data-lang="' . htmlspecialchars($label['key']) . '">' . htmlspecialchars($label['text']) . '</span>';
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="approval-steps" style="font-size: 11px;">
                                            <?php
                                            $emp_id = $row['employee_id'];
                                            $admin_verify = $db->query("SELECT verified_by FROM employees WHERE id = $emp_id AND verified_by IS NOT NULL")->fetch_assoc();
                                            
                                            // Admin
                                            echo '<span class="step ' . ($admin_verify ? 'done' : 'pending') . '">Admin</span>';
                                            echo '<span class="step ' . ($row['ktt1_approved_by'] ? 'done' : 'pending') . '">KTT1</span>';
                                            echo '<span class="step ' . ($row['ktt2_approved_by'] ? 'done' : 'pending') . '">KTT2</span>';
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="employee_detail.php?id=<?php echo $row['employee_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> <span data-lang="view">View</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-inbox" style="font-size: 24px; color: #ccc;"></i>
                                        <p class="mt-2 text-muted" data-lang="no-appointment-letter-data">No appointment letter data</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Action Buttons -->
    <section class="action-section">
        <div class="btn-group-custom">
            <a href="employees.php" class="btn btn-primary btn-lg">
                <i class="fas fa-users"></i>
                <span data-lang="view-all-employees">View All Employees</span>
            </a>
            <a href="appointments.php" class="btn btn-info btn-lg">
                <i class="fas fa-file-alt"></i>
                <span data-lang="view-all-letters">View All Letters</span>
            </a>
        </div>
    </section>
</div>

<style>
.dashboard {
    padding: 20px;
    background: #F5F5F5;
}

.page-header {
    background: #F57C00;
    color: white;
    padding: 40px 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(245, 124, 0, 0.3);
}

.header-content h2 {
    margin-bottom: 8px;
    font-size: 28px;
    font-weight: 600;
}

.header-content p {
    opacity: 0.9;
    font-size: 14px;
}

/* Stats Section */
.stats-section {
    margin-bottom: 40px;
}

.section-subtitle {
    color: #333;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
    padding-left: 5px;
    border-left: 3px solid #37474F;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.stats-grid-4 {
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border-top: 4px solid #ccc;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
}

.stat-card-primary { border-top-color: #37474F; }
.stat-card-success { border-top-color: #2E7D32; }
.stat-card-warning { border-top-color: #f59e0b; }
.stat-card-info { border-top-color: #37474F; }
.stat-card-danger { border-top-color: #ef4444; }

.stat-icon {
    font-size: 40px;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    color: white;
}


/* Selaraskan warna ikon dengan palet utama dashboard */
.stat-card-primary .stat-icon { background: #FFA240; }
.stat-card-success .stat-icon { background: linear-gradient(135deg, #F57C00, #FF9800); }
.stat-card-warning .stat-icon { background: linear-gradient(135deg, #FFD600, #FFB300); color: #F57C00; }
.stat-card-info .stat-icon { background: linear-gradient(135deg, #37474F, #F57C00); }
.stat-card-danger .stat-icon { background: linear-gradient(135deg, #EF5350, #D32F2F); }

.stat-content h3 {
    font-size: 28px;
    font-weight: 700;
    color: #333;
    margin: 0;
}

.stat-content p {
    color: #666;
    font-size: 13px;
    margin: 5px 0 0 0;
}

/* Recent Section */
.recent-section {
    margin-bottom: 30px;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header h3 {
    color: #333;
    margin: 0;
    font-size: 18px;
}

.card-header i {
    color: #37474F;
}

.table-hover tbody tr:hover {
    background-color: #f8f9ff;
}

.employee-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.emp-code {
    font-size: 12px;
    color: #999;
    font-weight: 500;
}

.emp-name {
    font-weight: 600;
    color: #333;
}

.approval-steps {
    display: flex;
    gap: 6px;
}

.step {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
}

.step.done {
    background: #E8F5E9;
    color: #1B5E20;
}

.step.pending {
    background: #fef3c7;
    color: #b45309;
}

/* Action Section */
.action-section {
    margin-top: 30px;
}

.btn-group-custom {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
}

.btn-lg {
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    font-size: 16px;
    border-radius: 8px;
    transition: all 0.3s ease;
    font-weight: 600;
}

.btn-lg:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.btn-lg i {
    font-size: 24px;
}

.stat-card-clickable {
    cursor: pointer;
    position: relative;
}

.stat-card-clickable:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 25px rgba(239, 68, 68, 0.3);
}

.stat-card-clickable:active {
    transform: translateY(-3px);
}

/* Badge styles */
.badge-success { background: #2E7D32; color: white; }
.badge-warning { background: #f59e0b; color: white; }
.badge-danger { background: #ef4444; color: white; }
.badge-secondary { background: #6c757d; color: white; }

.badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stats-grid-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .page-header {
        padding: 30px 25px;
    }
    
    .header-content h2 {
        font-size: 24px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        font-size: 32px;
    }
    
    .stat-content h3 {
        font-size: 24px;
    }
    
    .btn-group-custom {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .dashboard {
        padding: 14px;
    }
    
    .page-header {
        padding: 25px 20px;
        margin-bottom: 20px;
    }
    
    .header-content h2 {
        font-size: 20px;
    }
    
    .header-content p {
        font-size: 13px;
    }
    
    .section-subtitle {
        font-size: 14px;
    }
    
    .stats-section {
        margin-bottom: 25px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .stats-grid-4 {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 15px;
        gap: 15px;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 24px;
    }
    
    .stat-content h3 {
        font-size: 22px;
    }
    
    .stat-content p {
        font-size: 12px;
    }
    
    .card-header h3 {
        font-size: 16px;
    }
    
    .table th, .table td {
        padding: 10px 8px;
        font-size: 12px;
    }
    
    .table-responsive {
        margin: 0 -15px;
        padding: 0 15px;
    }
    
    .employee-info {
        min-width: 100px;
    }
    
    .emp-code {
        font-size: 10px;
    }
    
    .emp-name {
        font-size: 12px;
    }
    
    .approval-steps {
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .step {
        padding: 3px 6px;
        font-size: 9px;
    }
    
    .badge {
        padding: 4px 8px;
        font-size: 10px;
    }
    
    .btn-group-custom {
        grid-template-columns: 1fr;
    }
    
    .btn-lg {
        padding: 15px;
        font-size: 14px;
        flex-direction: row;
        justify-content: center;
    }
    
    .btn-lg i {
        font-size: 20px;
    }
    
    .action-section {
        margin-top: 20px;
    }
}

/* Certificate Expiration Card Styles */
.expiring-cert-card {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 2px solid #f59e0b;
    border-radius: 12px;
    padding: 0;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
}

.expiring-cert-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
}

.expiring-cert-badge {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    padding: 8px 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 0 0 12px 0;
    position: absolute;
    top: 0;
    left: 0;
}

.expiring-cert-content {
    padding: 50px 30px 30px 30px;
}

.expiring-cert-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.expiring-cert-header i {
    font-size: 32px;
    color: #f59e0b;
}

.expiring-cert-header h3 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #92400e;
}

.expiring-cert-body {
    text-align: center;
}

.expiring-cert-number {
    font-size: 56px;
    font-weight: 800;
    color: #dc2626;
    line-height: 1;
    margin-bottom: 12px;
}

.expiring-cert-desc {
    font-size: 15px;
    color: #92400e;
    margin-bottom: 20px;
    font-weight: 500;
}

.expiring-cert-action {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
}

.expiring-cert-action:hover {
    background: linear-gradient(135deg, #b91c1c, #991b1b);
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
}

@media (max-width: 576px) {
    .dashboard {
        padding: 12px;
    }
    
    .page-header {
        padding: 20px 15px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    
    .header-content h2 {
        font-size: 18px;
    }
    
    .stat-card {
        flex-direction: row;
        text-align: left;
        padding: 12px 15px;
    }
    
    .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .stat-content h3 {
        font-size: 20px;
    }
    
    .card {
        margin: 0 -10px;
        border-radius: 0;
    }
    
    .card-header {
        padding: 15px;
    }
    
    .card-body {
        padding: 10px;
    }
    
    .table th, .table td {
        padding: 8px 6px;
        font-size: 11px;
        white-space: nowrap;
    }
    
    .btn-sm {
        padding: 5px 8px;
        font-size: 10px;
    }
    
    .btn-lg {
        padding: 12px;
        gap: 8px;
    }
    
    .click-hint {
        font-size: 10px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>



