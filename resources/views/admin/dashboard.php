<?php
$page_title = 'Dashboard';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

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

require_once dirname(__DIR__) . '/layouts/header.php';

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
    <div class="welcome-card" style="background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 16px !important; padding: 24px 28px !important; box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;">
        <div class="welcome-content" style="display: flex !important; justify-content: space-between !important; align-items: center !important; flex-wrap: wrap !important; gap: 16px !important;">
            <div class="welcome-text">
                <h1 style="color: #0f172a !important; font-size: 24px !important; font-weight: 800 !important; margin: 0 0 6px 0 !important; letter-spacing: -0.4px !important;"><span data-lang="welcome-user" style="color: #0f172a !important;">Welcome</span>, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
                <p data-lang="manage-appointments" style="color: #334155 !important; font-size: 14px !important; font-weight: 500 !important; margin: 0 !important;">Manage and monitor all appointment letters easily</p>
            </div>
            <div class="welcome-date" style="display: inline-flex !important; align-items: center !important; gap: 8px !important; background: #f8fafc !important; border: 1px solid #cbd5e1 !important; padding: 8px 16px !important; border-radius: 10px !important; color: #0f172a !important; font-size: 13.5px !important; font-weight: 600 !important;">
                <i class="fas fa-calendar-alt" style="color: #4f46e5 !important;"></i>
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

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
