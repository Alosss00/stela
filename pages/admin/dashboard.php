<?php
$page_title = 'Dashboard';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Redirect KTT to approval page
if (isKTT()) {
    header('Location: ../ktt/approval.php');
    exit();
}

// Redirect USER role to their specific dashboard
if (isUser()) {
    // If user has department, redirect to department dashboard
    if (hasDepartment()) {
        header('Location: ../dept/dashboard.php');
        exit();
    }
    header('Location: ../user/dashboard.php');
    exit();
}

// Redirect Department User role to their specific dashboard
if (isDepartmentUser()) {
    header('Location: ../dept/dashboard.php');
    exit();
}

require_once '../../includes/header.php';

$db = new Database();

// Get statistics
$total_appointments = $db->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
$pending_approvals = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'")->fetch_assoc()['count'];
$rejected_appointments = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'rejected'")->fetch_assoc()['count'];
$approved_appointments = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'approved'")->fetch_assoc()['count'];

// Get employee verification statistics
$pending_verification = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'pending' AND is_active = 1")->fetch_assoc()['count'];
// Count only verified/rejected by current logged-in admin
$current_user_id = $_SESSION['user_id'];
$verified_employees = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'verified' AND is_active = 1 AND verified_by = '$current_user_id'")->fetch_assoc()['count'];
$rejected_employees = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'rejected' AND is_active = 1 AND verified_by = '$current_user_id'")->fetch_assoc()['count'];

// Get certificate expiration statistics (certificates expiring in 2 months or less)
$expiring_certs_count = $db->query("
    SELECT COUNT(DISTINCT e.id) as count
    FROM employee_certifications ec
    JOIN employees e ON ec.employee_id = e.id
    WHERE ec.expiry_date IS NOT NULL
    AND ec.verification_status = 'verified'
    AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH)
    AND ec.expiry_date >= CURDATE()
    AND e.is_active = 1
")->fetch_assoc()['count'];

// Get appointments rejected by KTT that need admin review
$rejected_by_ktt_count = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'rejected_by_ktt'")->fetch_assoc()['count'];

// Get recent appointments with approval history
$recent_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.contractor_company, c.competency_name,
           u.full_name as approved_by_name,
           CASE 
               WHEN a.status = 'approved' THEN 'success'
               WHEN a.status = 'pending' THEN 'warning'
               WHEN a.status = 'rejected' THEN 'danger'
               ELSE 'secondary'
           END as status_class
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN competencies c ON a.position_id = c.id
    LEFT JOIN users u ON a.approved_by = u.id
    ORDER BY a.created_at DESC
    LIMIT 10
");

// Get additional statistics
$total_employees = $db->query("SELECT COUNT(*) as count FROM employees")->fetch_assoc()['count'];

// Email delivery log section
$email_logs_table_exists = false;
$email_delivery_logs = [];
$email_logs_total = 0;
$email_logs_valid = 0;
$email_logs_sent = 0;

$check_email_logs_table = $db->query("SHOW TABLES LIKE 'notification_email_logs'");
if ($check_email_logs_table && $check_email_logs_table->num_rows > 0) {
    $email_logs_table_exists = true;
    $email_logs_summary = $db->query("
        SELECT 
            COUNT(*) as total_count,
            SUM(CASE WHEN email_is_valid = 1 THEN 1 ELSE 0 END) as valid_count,
            SUM(CASE WHEN email_sent = 1 THEN 1 ELSE 0 END) as sent_count
        FROM notification_email_logs
    ")->fetch_assoc();
    $email_logs_total = (int)($email_logs_summary['total_count'] ?? 0);
    $email_logs_valid = (int)($email_logs_summary['valid_count'] ?? 0);
    $email_logs_sent = (int)($email_logs_summary['sent_count'] ?? 0);

    $email_logs_result = $db->query("
        SELECT recipient_email, recipient_name, subject, email_is_valid, email_sent, error_message, created_at
        FROM notification_email_logs
        ORDER BY created_at DESC
        LIMIT 10
    ");
    if ($email_logs_result && $email_logs_result->num_rows > 0) {
        while ($row = $email_logs_result->fetch_assoc()) {
            $email_delivery_logs[] = $row;
        }
    }
}
?>

<div class="dashboard-modern">
    <!-- Welcome Section -->
    <div class="welcome-card">
        <div class="welcome-content">
            <div class="welcome-text">
                <h1><span data-lang="welcome-user">Welcome</span>, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
                <p data-lang="manage-appointments">Manage and monitor all appointment letters easily</p>
            </div>
            <div class="welcome-date">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>
    </div>

    <!-- Stats Section - Employee Verification -->
    <div class="section-wrapper">
        <div class="section-title">
            <h2 data-lang="employee-verification">Employee Verification</h2>
        </div>
        <div class="stats-grid-main">
            <a href="employees.php?filter=pending" class="stat-box stat-warning">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $pending_verification; ?></div>
                    <div class="stat-label" data-lang="needs-review-admin">Needs Review Admin</div>
                </div>
            </a>

            <a href="employees.php?filter=verified" class="stat-box stat-success">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $verified_employees; ?></div>
                    <div class="stat-label" data-lang="accept">Accept</div>
                </div>
            </a>

            <a href="employees.php?filter=rejected" class="stat-box stat-danger">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $rejected_employees; ?></div>
                    <div class="stat-label" data-lang="reject">Reject</div>
                </div>
            </a>

            <?php if ($rejected_by_ktt_count > 0): ?>
            <a href="appointments.php?status=rejected_by_ktt" class="stat-box stat-needs-review">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $rejected_by_ktt_count; ?></div>
                    <div class="stat-label" data-lang="needs-review-ktt">Needs Review (Reject KTT)</div>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Email Delivery Logs -->
    <div class="section-wrapper">
        <div class="recent-card">
            <div class="recent-header">
                <h3><i class="fas fa-envelope"></i> Email Delivery Logs</h3>
                <div class="email-log-summary">
                    <span class="email-log-chip">Total: <?php echo $email_logs_total; ?></span>
                    <span class="email-log-chip success">Valid: <?php echo $email_logs_valid; ?></span>
                    <span class="email-log-chip info">Sent: <?php echo $email_logs_sent; ?></span>
                </div>
            </div>

            <?php if ($email_logs_table_exists): ?>
                <?php if (!empty($email_delivery_logs)): ?>
                <div class="email-log-table-wrap">
                    <table class="email-log-table">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Email</th>
                                <th>Valid</th>
                                <th>Sent</th>
                                <th>Subject</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($email_delivery_logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['recipient_name'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($log['recipient_email']); ?></td>
                                <td>
                                    <span class="email-status-badge <?php echo $log['email_is_valid'] ? 'status-yes' : 'status-no'; ?>">
                                        <?php echo $log['email_is_valid'] ? 'Valid' : 'Invalid'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="email-status-badge <?php echo $log['email_sent'] ? 'status-yes' : 'status-no'; ?>">
                                        <?php echo $log['email_sent'] ? 'Sent' : 'Failed'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['subject'] ?: '-'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-envelope-open-text"></i>
                    <p>No email delivery logs yet</p>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-envelope-open-text"></i>
                    <p>Email delivery log table will appear after the first email is sent</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Certificate Expiration Alert -->
    <?php if ($expiring_certs_count > 0): ?>
    <div class="section-wrapper">
        <div class="certificate-expiration-card">
            <div class="cert-urgent-badge">
                <i class="fas fa-exclamation-triangle"></i> <span data-lang="urgent">URGENT</span>
            </div>

            <div class="cert-header">
                <div class="cert-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3 data-lang="certificate-expiration">Certificate Expiration</h3>
            </div>

            <div class="cert-body">
                <div class="cert-number-large"><?php echo $expiring_certs_count; ?></div>
                <p class="cert-description" data-lang="employees-expiring-certs">Employees with certificates expiring within = 2 months</p>

                <a href="reports.php#certificate-expiration" class="cert-btn">
                    <span data-lang="view-certificate-details">View Certificate Details</span> <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Appointments -->
    <div class="recent-card">
        <div class="recent-header">
            <h3><i class="fas fa-history"></i> <span data-lang="recent-appointments">Recent Appointment Letters History</span></h3>
            <button onclick="toggleAppointmentsList()" class="view-all-btn" id="viewAllBtn">
                <span id="btnText" data-lang="view-all">View All</span> <i class="fas fa-chevron-down" id="btnIcon"></i>
            </button>
        </div>

        <?php if ($recent_appointments && $recent_appointments->num_rows > 0): ?>
        <div class="appointments-list" id="appointmentsList" style="display: none; opacity: 0; max-height: 0;">
            <?php while ($row = $recent_appointments->fetch_assoc()): 
                // Get approval history
                $approval_history = $db->query("
                    SELECT ka.ktt_user_id, ka.action, ka.approval_date, u.full_name, u.company_name
                    FROM ktt_approvals ka
                    JOIN users u ON ka.ktt_user_id = u.id
                    WHERE ka.appointment_id = " . $row['id'] . "
                    ORDER BY ka.approval_date ASC
                ");
                
                // Get employee verification
                $emp_verify = $db->query("
                    SELECT verified_by, verified_date, verification_status
                    FROM employees
                    WHERE id = " . $row['employee_id'] . "
                ")->fetch_assoc();
            ?>
            <div class="appointment-item">
                <div class="appointment-main">
                    <div class="appointment-left">
                        <div class="appointment-number"><?php echo htmlspecialchars($row['appointment_number']); ?></div>
                        <div class="appointment-details">
                            <div class="detail-row">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($row['employee_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-building"></i>
                                <span><?php echo htmlspecialchars($row['contractor_company'] ?? '-'); ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-briefcase"></i>
                                <span><?php echo htmlspecialchars($row['competency_name'] ?? '-'); ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('d/m/Y', strtotime($row['appointment_date'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="appointment-status">
                        <span class="status-badge status-<?php echo $row['status_class']; ?>">
                            <?php 
                            $status_labels = [
                                'approved' => 'ACCEPT',
                                'pending' => 'PENDING',
                                'rejected' => 'REJECT',
                                'rejected_by_ktt' => 'REJECT BY KTT',
                                'draft' => 'DRAFT'
                            ];
                            echo $status_labels[$row['status']] ?? strtoupper($row['status']);
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="appointment-timeline">
                    <div class="timeline-label">Approval History:</div>
                    <div class="timeline-items">
                        <?php 
                        // Admin verification
                        if ($emp_verify && $emp_verify['verified_by']) {
                            $admin_user = $db->query("SELECT full_name FROM users WHERE id = " . $emp_verify['verified_by'])->fetch_assoc();
                        ?>
                        <div class="timeline-step step-admin">
                            <div class="step-badge">Admin</div>
                            <div class="step-name"><?php echo htmlspecialchars($admin_user['full_name']); ?></div>
                            <div class="step-time"><?php echo date('d/m/y', strtotime($emp_verify['verified_date'])); ?></div>
                        </div>
                        <?php 
                        }
                        
                        // KTT approvals
                        if ($approval_history && $approval_history->num_rows > 0) {
                            $approval_history->data_seek(0);
                            while ($approval = $approval_history->fetch_assoc()): 
                                // Determine KTT label based on company_name
                                $ktt_label = 'KTT';
                                if (!empty($approval['company_name'])) {
                                    if (stripos($approval['company_name'], 'MSM') !== false) {
                                        $ktt_label = 'KTT MSM';
                                    } elseif (stripos($approval['company_name'], 'TTN') !== false) {
                                        $ktt_label = 'KTT TTN';
                                    }
                                }
                        ?>
                        <div class="timeline-step step-ktt">
                            <div class="step-badge"><?php echo $ktt_label; ?></div>
                            <div class="step-name"><?php echo htmlspecialchars($approval['full_name']); ?></div>
                            <div class="step-time"><?php echo date('d/m/y', strtotime($approval['approval_date'])); ?></div>
                        </div>
                        <?php 
                            endwhile;
                        } else {
                            if (!$emp_verify || !$emp_verify['verified_by']) {
                        ?>
                        <div class="timeline-empty">No approvals yet</div>
                        <?php 
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No appointment letter data yet</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.dashboard-modern {
    padding: 20px;
    background: #F5F5F5;
}

/* Welcome Card */
.welcome-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.welcome-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #1f2937;
}

.welcome-text h1 {
    font-size: 22px;
    font-weight: 700;
    margin: 0 0 5px 0;
}

.welcome-text p {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
}

.welcome-date {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f3f4f6;
    color: #374151;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
}

/* Section Wrapper */
.section-wrapper {
    margin-bottom: 20px;
}

.section-title {
    margin-bottom: 14px;
}

.section-title h2 {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
}

.stats-grid-main {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.stat-box {
    background: white;
    border-radius: 10px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    cursor: pointer;
}

.stat-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 14px rgba(55, 71, 79, 0.15);
}

.stat-urgent {
    border: 2px solid #f59e0b;
}

.stat-urgent:hover {
    box-shadow: 0 6px 14px rgba(245, 158, 11, 0.25);
}

.stat-icon-wrapper {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: white;
    margin-bottom: 10px;
}


/* Selaraskan warna ikon dengan palet utama dashboard */
.stat-total .stat-icon-wrapper { background: #F57C00; }
.stat-success .stat-icon-wrapper {
    background: linear-gradient(135deg, #F57C00, #FF9800); /* Orange utama */
    color: #fff;
}
.stat-warning .stat-icon-wrapper {
    background: linear-gradient(135deg, #FFD600, #FFB300); /* Kuning terang */
    color: #F57C00;
    animation: pulse-warning 2s ease-in-out infinite;
}
.stat-danger .stat-icon-wrapper {
    background: linear-gradient(135deg, #EF5350, #D32F2F); /* Merah */
    color: #fff;
}
.stat-needs-review .stat-icon-wrapper {
    background: linear-gradient(135deg, #2196F3, #1976D2); /* Biru */
    color: #fff;
    animation: pulse-needs-review 2s ease-in-out infinite;
}
.stat-info .stat-icon-wrapper {
    background: linear-gradient(135deg, #37474F, #F57C00); /* Abu tua ke oranye */
    color: #fff;
}

@keyframes pulse-needs-review {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(139, 92, 246, 0.7);
    }
    50% {
        box-shadow: 0 0 0 12px rgba(139, 92, 246, 0);
    }
}

@keyframes pulse-warning {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
    }
    50% {
        box-shadow: 0 0 0 12px rgba(245, 158, 11, 0);
    }
}

.stat-urgent .stat-icon-wrapper {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    animation: pulse-urgent-icon 2s ease-in-out infinite;
}

@keyframes pulse-urgent-icon {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
    }
    50% {
        box-shadow: 0 0 0 12px rgba(245, 158, 11, 0);
    }
}

.stat-number {
    font-size: 26px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 11px;
    color: #616161;
    font-weight: 500;
}

/* Certificate Expiration Card */
.certificate-expiration-card {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 3px solid #f59e0b;
    border-radius: 12px;
    padding: 20px 24px;
    position: relative;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
    animation: pulse-cert-card 3s ease-in-out infinite;
    max-width: 10000px;
}

@keyframes pulse-cert-card {
    0%, 100% {
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2), 0 0 0 0 rgba(245, 158, 11, 0.4);
    }
    50% {
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3), 0 0 0 15px rgba(245, 158, 11, 0);
    }
}

.cert-urgent-badge {
    position: absolute;
    top: -2px;
    left: 20px;
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    padding: 5px 12px;
    border-radius: 0 0 8px 8px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 4px 8px rgba(220, 38, 38, 0.4);
}

.cert-urgent-badge i {
    font-size: 9px;
}

.cert-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}

.cert-icon {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.cert-header h3 {
    font-size: 17px;
    font-weight: 700;
    color: #92400e;
    margin: 0;
}

.cert-body {
    text-align: center;
}

.cert-number-large {
    font-size: 52px;
    font-weight: 700;
    color: #dc2626;
    line-height: 1;
    margin-bottom: 8px;
    text-shadow: 2px 2px 4px rgba(220, 38, 38, 0.2);
}

.cert-description {
    font-size: 14px;
    color: #92400e;
    margin: 0 0 18px 0;
    font-weight: 500;
}

.cert-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.cert-btn:hover {
    background: linear-gradient(135deg, #b91c1c, #991b1b);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.4);
}

.cert-btn i {
    font-size: 11px;
    transition: transform 0.3s ease;
}

.cert-btn:hover i {
    transform: translateX(4px);
}

.two-column-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

.info-card {
    background: white;
    border-radius: 10px;
    padding: 14px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    min-height: 220px;
}

.info-header {
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 2px solid #f3f4f6;
}

.info-header h3 {
    font-size: 12px;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.info-stats-vertical {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
}

.info-item-large {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.info-item-large:hover {
    transform: translateX(3px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.info-item-large i {
    font-size: 18px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    flex-shrink: 0;
}

.info-warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
}

.info-warning i {
    background: rgba(146, 64, 14, 0.15);
    color: #92400e;
}

.info-success {
    background: linear-gradient(135deg, #E8F5E9, #a7f3d0);
    color: #1B5E20;
}

.info-success i {
    background: rgba(6, 95, 70, 0.15);
    color: #1B5E20;
}

.info-danger {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
}

.info-danger i {
    background: rgba(153, 27, 27, 0.15);
    color: #991b1b;
}

.info-urgent {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border: 2px solid #f59e0b;
    animation: pulse-urgent 2s ease-in-out infinite;
}

.info-urgent i {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

@keyframes pulse-urgent {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4);
    }
    50% {
        box-shadow: 0 0 0 8px rgba(245, 158, 11, 0);
    }
}

.info-number {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 2px;
    line-height: 1;
}

.info-text {
    font-size: 9px;
    font-weight: 600;
    opacity: 0.9;
}

.info-alert {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 2px solid #f59e0b;
    position: relative;
}

.alert-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 8px;
    font-weight: 700;
    z-index: 2;
}

.alert-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(245, 158, 11, 0.3);
}

.alert-header i {
    font-size: 20px;
    color: #f59e0b;
}

.alert-header h3 {
    font-size: 14px;
    font-weight: 700;
    color: #92400e;
    margin: 0;
}

.alert-body {
    text-align: center;
    padding: 12px 0;
}

.alert-number-large {
    font-size: 48px;
    font-weight: 700;
    color: #92400e;
    line-height: 1;
    margin-bottom: 10px;
}

.alert-desc {
    font-size: 12px;
    color: #92400e;
    margin-bottom: 14px;
    line-height: 1.4;
}

.info-success-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    background: linear-gradient(135deg, #E8F5E9, #a7f3d0);
    border: 2px solid #2E7D32;
    min-height: 220px;
}

.success-icon {
    width: 60px;
    height: 60px;
    background: rgba(16, 185, 129, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}

.success-icon i {
    font-size: 30px;
    color: #1B5E20;
}

.info-success-state h3 {
    font-size: 16px;
    font-weight: 700;
    color: #1B5E20;
    margin: 0 0 8px 0;
}

.info-success-state p {
    font-size: 12px;
    color: #1B5E20;
    margin: 0;
    opacity: 0.9;
}

.recent-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.email-log-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.email-log-chip {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 999px;
    background: #eef2f7;
    color: #374151;
    font-size: 12px;
    font-weight: 600;
}

.email-log-chip.success {
    background: #dcfce7;
    color: #166534;
}

.email-log-chip.info {
    background: #dbeafe;
    color: #1d4ed8;
}

.email-log-table-wrap {
    overflow-x: auto;
}

.email-log-table {
    width: 100%;
    border-collapse: collapse;
}

.email-log-table th,
.email-log-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #eef2f7;
    text-align: left;
    font-size: 13px;
    vertical-align: top;
}

.email-log-table th {
    background: #f9fafb;
    color: #374151;
    font-weight: 700;
}

.email-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.status-yes {
    background: #dcfce7;
    color: #166534;
}

.status-no {
    background: #fee2e2;
    color: #b91c1c;
}

.recent-header {
    padding: 16px 18px;
    border-bottom: 2px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.recent-header h3 {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.view-all-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #37474F;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(55, 71, 79, 0.2);
}

.view-all-btn:hover {
    background: linear-gradient(135deg, #37474F, #007bb3);
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(55, 71, 79, 0.3);
}

.view-all-btn i {
    font-size: 12px;
    transition: transform 0.3s ease;
}

.appointments-list {
    padding: 0;
    transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
    overflow: hidden;
}

.appointment-item {
    padding: 14px 18px;
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.2s ease;
}

.appointment-item:hover {
    background-color: #f9fafb;
}

.appointment-item:last-child {
    border-bottom: none;
}

.appointment-main {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.appointment-number {
    font-size: 13px;
    font-weight: 700;
    color: #37474F;
    margin-bottom: 8px;
}

.appointment-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    color: #4b5563;
}

.detail-row i {
    width: 13px;
    color: #9ca3af;
    font-size: 10px;
}

.appointment-timeline {
    background: #f9fafb;
    padding: 8px 12px;
    border-radius: 6px;
}

.timeline-label {
    font-size: 10px;
    font-weight: 600;
    color: #616161;
    margin-bottom: 6px;
}

.timeline-items {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.timeline-step {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
}

.step-admin {
    background: linear-gradient(135deg, #ECEFF1, #bfdbfe);
    border-left: 3px solid #37474F;
}

.step-ktt {
    background: linear-gradient(135deg, #E8F5E9, #a7f3d0);
    border-left: 3px solid #2E7D32;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #9ca3af;
}

.empty-state i {
    font-size: 36px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 12px;
}

@media (max-width: 768px) {
    .dashboard-modern {
        padding: 14px;
    }
    
    .welcome-card {
        padding: 18px;
        margin-bottom: 16px;
    }
    
    .section-wrapper,
    .two-column-layout {
        margin-bottom: 16px;
    }
    
    .stats-grid-main {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .stat-box {
        padding: 12px;
    }
    
    .stat-icon-wrapper {
        width: 44px;
        height: 44px;
        font-size: 18px;
        margin-bottom: 8px;
    }
    
    .stat-number {
        font-size: 20px;
    }
}

@media (max-width: 480px) {
    .dashboard-modern {
        padding: 10px;
    }
    
    .stats-grid-main {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .cert-number-large {
        font-size: 44px;
    }
    
    .cert-description {
        font-size: 12px;
    }
    
    .cert-btn {
        font-size: 12px;
        padding: 8px 16px;
    }
    
    .two-column-layout {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}
</style>

<script>
function toggleAppointmentsList() {
    const list = document.getElementById('appointmentsList');
    const icon = document.getElementById('btnIcon');
    const btnText = document.getElementById('btnText');

    if (list.style.display === 'none' || list.style.display === '') {
        list.style.display = 'block';
        // Trigger reflow
        list.offsetHeight;
        list.style.opacity = '1';
        list.style.maxHeight = '5000px';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
        btnText.setAttribute('data-lang', 'hide');
        if (window.changeLanguage && window.getCurrentLanguage) {
            window.changeLanguage(window.getCurrentLanguage());
        }
    } else {
        list.style.opacity = '0';
        list.style.maxHeight = '0';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        btnText.setAttribute('data-lang', 'view-all');
        if (window.changeLanguage && window.getCurrentLanguage) {
            window.changeLanguage(window.getCurrentLanguage());
        }
        // Wait for transition before hiding
        setTimeout(() => {
            list.style.display = 'none';
        }, 300);
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>



