<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Dashboard';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

// Only department access permitted
requirePermission('dashboard.view');
if (!hasPermission('dept.access') && !(hasPermission('user.access') && hasDepartment()) && !isSuperadmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

require_once dirname(__DIR__) . '/layouts/header.php';

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
$total_employees = $db->query("SELECT COUNT(*) as count FROM employees WHERE deleted_at IS NULL AND $department_condition_single AND is_active = 1")->fetch_assoc()['count'];
$verified_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE deleted_at IS NULL AND $department_condition_single AND verification_status = 'verified' AND is_active = 1")->fetch_assoc()['count'];
$pending_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE deleted_at IS NULL AND $department_condition_single AND verification_status = 'pending' AND is_active = 1")->fetch_assoc()['count'];
$rejected_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE deleted_at IS NULL AND $department_condition_single AND verification_status = 'rejected' AND is_active = 1")->fetch_assoc()['count'];

// Get appointment statistics
$total_appointments = $db->query("
    SELECT COUNT(*) as count 
    FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.deleted_at IS NULL AND e.deleted_at IS NULL AND $department_condition_joined
")->fetch_assoc()['count'];

$approved_appointments = $db->query("
    SELECT COUNT(*) as count 
    FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.deleted_at IS NULL AND e.deleted_at IS NULL AND $department_condition_joined AND a.status = 'approved'
")->fetch_assoc()['count'];

$rejected_appointments = $db->query("
    SELECT COUNT(*) as count 
    FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.deleted_at IS NULL AND e.deleted_at IS NULL AND $department_condition_joined AND a.status = 'rejected'
")->fetch_assoc()['count'];

$pending_appointments = $db->query("
    SELECT COUNT(*) as count 
    FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.deleted_at IS NULL AND e.deleted_at IS NULL AND $department_condition_joined AND a.status = 'pending'
")->fetch_assoc()['count'];

// Get certificate expiration statistics (certificates expiring within <= 2 months OR already expired) for this department
// Uses latest record per certification per employee to avoid double-counting
$expiring_certs_count = $db->query("
    SELECT COUNT(ec.id) as count
    FROM employee_certifications ec
    JOIN employees e ON ec.employee_id = e.id
    WHERE e.is_active = 1
    AND ($department_condition_joined)
    AND ec.id = (
        SELECT ec2.id
        FROM employee_certifications ec2
        WHERE ec2.employee_id = ec.employee_id
        AND ec2.certification_id = ec.certification_id
        ORDER BY FIELD(ec2.status, 'pending', 'verified', 'active', 'expired'), ec2.id DESC
        LIMIT 1
    )
    AND EXISTS (
        SELECT 1 FROM employee_certifications ec_hist
        WHERE ec_hist.employee_id = ec.employee_id
        AND ec_hist.certification_id = ec.certification_id
        AND ec_hist.status = 'expired'
    )
    AND NOT EXISTS (
        SELECT 1 FROM employee_certifications ec_active
        WHERE ec_active.employee_id = ec.employee_id
        AND ec_active.certification_id = ec.certification_id
        AND ec_active.status = 'active'
        AND ec_active.expiry_date > CURDATE()
    )
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
                <a href="certificate_status.php" style="color: #dc3545; font-size: 13px; font-weight: 600; text-decoration: none;">
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
                                <th data-lang="registration-no" style="min-width: 180px; white-space: nowrap;">Registration No.</th>
                                <th data-lang="employee" style="min-width: 150px;">Employee</th>
                                <th data-lang="position">Position</th>
                                <th data-lang="effective-date" style="white-space: nowrap;">Effective Date</th>
                                <th data-lang="status">Status</th>
                                <th data-lang="approval" style="min-width: 160px;">Approval</th>
                                <th data-lang="action" style="white-space: nowrap; width: 1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($recent_appointments && $recent_appointments->num_rows > 0): 
                                // Pre-fetch data for N+1 optimization
                                $appointments_data = [];
                                $employee_ids = [];
                                while ($row = $recent_appointments->fetch_assoc()) {
                                    $appointments_data[] = $row;
                                    $employee_ids[] = $row['employee_id'];
                                }

                                // Fetch Employee Verification
                                $admin_verify_map = [];
                                if (!empty($employee_ids)) {
                                    $e_ids_str = implode(',', array_unique($employee_ids));
                                    $admin_verify_result = $db->query("
                                        SELECT id, verified_by 
                                        FROM employees 
                                        WHERE deleted_at IS NULL AND id IN ($e_ids_str) AND verified_by IS NOT NULL
                                    ");
                                    if ($admin_verify_result) {
                                        while ($av = $admin_verify_result->fetch_assoc()) {
                                            $admin_verify_map[$av['id']] = $av;
                                        }
                                    }
                                }

                                foreach ($appointments_data as $row): 
                            ?>
                                <tr>
                                    <td style="word-break: break-word; min-width: 160px;"><strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong></td>
                                    <td>
                                        <div class="employee-info" style="display: flex; flex-direction: column; gap: 4px;">
                                            <span class="emp-code" style="font-size: 11px; color: #6c757d; font-weight: 600;"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                                            <span class="emp-name" style="font-weight: 500;"><?php echo htmlspecialchars($row['employee_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['position_name'] ?? '-'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['effective_date'])); ?></td>
                                    <td style="white-space: nowrap;">
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
                                        <div class="approval-steps" style="font-size: 11px; display: flex; gap: 4px; flex-wrap: wrap; align-items: center;">
                                            <?php
                                            $emp_id = $row['employee_id'];
                                            $admin_verify = $admin_verify_map[$emp_id] ?? null;
                                            
                                            // Admin
                                            echo '<span class="step ' . ($admin_verify ? 'done' : 'pending') . '" style="padding: 2px 6px; border-radius: 4px; display: inline-block; text-align: center; margin-right: 2px;">Admin</span>';
                                            echo '<span class="step ' . ($row['ktt1_approved_by'] ? 'done' : 'pending') . '" style="padding: 2px 6px; border-radius: 4px; display: inline-block; text-align: center; margin-right: 2px;">KTT1</span>';
                                            echo '<span class="step ' . ($row['ktt2_approved_by'] ? 'done' : 'pending') . '" style="padding: 2px 6px; border-radius: 4px; display: inline-block; text-align: center;">KTT2</span>';
                                            ?>
                                        </div>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <a href="employee_detail.php?id=<?php echo $row['employee_id']; ?>" class="btn btn-sm btn-info" style="white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
                                            <i class="fas fa-eye"></i> <span data-lang="view">View</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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
</div>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
