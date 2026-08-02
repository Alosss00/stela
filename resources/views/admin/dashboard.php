<?php
$page_title = 'Dashboard';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';

// Redirect KTT to approval page
if (isKTT()) {
    header('Location: ../ktt/approval.php');
    exit();
}

// Redirect USER role to their specific dashboard
if (isUser()) {
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
$current_user_id = $_SESSION['user_id'];
$verified_employees = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'verified' AND is_active = 1 AND verified_by = '$current_user_id'")->fetch_assoc()['count'];
$rejected_employees = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'rejected' AND is_active = 1 AND verified_by = '$current_user_id'")->fetch_assoc()['count'];

// Get certificate expiration statistics
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

// Get recent appointments
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

<div class="stela-dash-pro">
    <!-- Page Header Bar -->
    <div class="stela-dash-header">
        <div class="stela-dash-title-group">
            <h1>Dashboard</h1>
            <p><span data-lang="welcome-user">Welcome</span>, <?php echo htmlspecialchars($_SESSION['full_name']); ?> • <span data-lang="manage-appointments">Overview of your verification & appointment operations</span></p>
        </div>
        <div class="stela-dash-header-actions">
            <div class="stela-dash-date-pill">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>
    </div>

    <!-- 4 Stat Cards Row Grid -->
    <div class="stela-stat-grid">
        <a href="employees.php?filter=pending" class="stela-stat-card">
            <div class="stela-stat-top">
                <div class="stela-stat-icon-box warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stela-stat-meta">
                    <div class="stela-stat-label" data-lang="needs-review-admin">Needs Review Admin</div>
                    <div class="stela-stat-value"><?php echo $pending_verification; ?></div>
                </div>
            </div>
            <div class="stela-stat-bottom">
                <span class="stela-stat-badge warn"><i class="fas fa-hourglass-half"></i> Awaiting Action</span>
                <svg class="stela-sparkline-svg" viewBox="0 0 60 22" fill="none"><path d="M2 18L15 12L28 15L42 5L58 12" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <a href="employees.php?filter=verified" class="stela-stat-card">
            <div class="stela-stat-top">
                <div class="stela-stat-icon-box success">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stela-stat-meta">
                    <div class="stela-stat-label" data-lang="accept">Accept</div>
                    <div class="stela-stat-value"><?php echo $verified_employees; ?></div>
                </div>
            </div>
            <div class="stela-stat-bottom">
                <span class="stela-stat-badge up"><i class="fas fa-arrow-up"></i> Verified & Active</span>
                <svg class="stela-sparkline-svg" viewBox="0 0 60 22" fill="none"><path d="M2 16L16 18L30 8L44 12L58 4" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <a href="employees.php?filter=rejected" class="stela-stat-card">
            <div class="stela-stat-top">
                <div class="stela-stat-icon-box danger">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stela-stat-meta">
                    <div class="stela-stat-label" data-lang="reject">Reject</div>
                    <div class="stela-stat-value"><?php echo $rejected_employees; ?></div>
                </div>
            </div>
            <div class="stela-stat-bottom">
                <span class="stela-stat-badge down"><i class="fas fa-arrow-down"></i> Needs Correction</span>
                <svg class="stela-sparkline-svg" viewBox="0 0 60 22" fill="none"><path d="M2 6L16 12L30 10L44 18L58 14" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <a href="<?php echo ($rejected_by_ktt_count > 0) ? 'appointments.php?status=rejected_by_ktt' : 'appointments.php'; ?>" class="stela-stat-card">
            <div class="stela-stat-top">
                <div class="stela-stat-icon-box primary">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div class="stela-stat-meta">
                    <div class="stela-stat-label"><?php echo ($rejected_by_ktt_count > 0) ? 'Needs Review (KTT)' : 'Total Appointments'; ?></div>
                    <div class="stela-stat-value"><?php echo ($rejected_by_ktt_count > 0) ? $rejected_by_ktt_count : $total_appointments; ?></div>
                </div>
            </div>
            <div class="stela-stat-bottom">
                <span class="stela-stat-badge neutral"><i class="fas fa-layer-group"></i> Total Registered</span>
                <svg class="stela-sparkline-svg" viewBox="0 0 60 22" fill="none"><path d="M2 14L16 6L30 14L44 8L58 12" stroke="#4f46e5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>
    </div>

    <!-- Email Delivery Logs Card -->
    <div class="stela-dash-card">
        <div class="stela-dash-card-header">
            <div class="stela-dash-card-title">
                <i class="fas fa-envelope"></i>
                <span>Email Delivery Logs</span>
            </div>
            <div class="email-log-summary">
                <span class="email-log-chip">Total: <?php echo $email_logs_total; ?></span>
                <span class="email-log-chip success">Valid: <?php echo $email_logs_valid; ?></span>
                <span class="email-log-chip info">Sent: <?php echo $email_logs_sent; ?></span>
            </div>
        </div>

        <?php if ($email_logs_table_exists && !empty($email_delivery_logs)): ?>
        <div class="table-responsive">
            <table class="stela-dash-table">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Email</th>
                        <th>Status Valid</th>
                        <th>Status Sent</th>
                        <th>Subject</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($email_delivery_logs as $log): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($log['recipient_name'] ?: '-'); ?></strong></td>
                        <td><?php echo htmlspecialchars($log['recipient_email']); ?></td>
                        <td>
                            <span class="stela-status-pill <?php echo $log['email_is_valid'] ? 'success' : 'danger'; ?>">
                                <?php echo $log['email_is_valid'] ? 'Valid' : 'Invalid'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="stela-status-pill <?php echo $log['email_sent'] ? 'success' : 'danger'; ?>">
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
        <div class="empty-state" style="padding: 32px; text-align: center; color: #94a3b8;">
            <i class="fas fa-envelope-open-text" style="font-size: 32px; margin-bottom: 8px;"></i>
            <p>No email delivery log recorded yet.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Certificate Expiration Alert -->
    <?php if ($expiring_certs_count > 0): ?>
    <div class="stela-dash-card" style="border-left: 4px solid #ef4444;">
        <div class="stela-dash-card-header" style="background: #fef2f2;">
            <div class="stela-dash-card-title" style="color: #991b1b;">
                <i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i>
                <span data-lang="certificate-expiration">Certificate Expiration Alert</span>
            </div>
            <span class="stela-status-pill danger" data-lang="urgent">URGENT</span>
        </div>
        <div style="padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="font-size: 32px; font-weight: 800; color: #991b1b; line-height: 1;"><?php echo $expiring_certs_count; ?></div>
                <p style="margin: 4px 0 0 0; color: #7f1d1d; font-size: 13.5px;" data-lang="employees-expiring-certs">Employees with certificates expiring within 2 months</p>
            </div>
            <a href="reports.php#certificate-expiration" class="btn btn-danger btn-sm" style="border-radius: 10px; padding: 8px 18px; font-weight: 600;">
                <span data-lang="view-certificate-details">View Details</span> <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Appointments History Card -->
    <div class="stela-dash-card">
        <div class="stela-dash-card-header">
            <div class="stela-dash-card-title">
                <i class="fas fa-history"></i>
                <span data-lang="recent-appointments">Recent Appointment Letters History</span>
            </div>
            <button onclick="toggleAppointmentsList()" class="btn btn-outline-secondary btn-sm" id="viewAllBtn" style="border-radius: 8px; font-weight: 600;">
                <span id="btnText" data-lang="view-all">View All</span> <i class="fas fa-chevron-down" id="btnIcon"></i>
            </button>
        </div>

        <?php if ($recent_appointments && $recent_appointments->num_rows > 0): ?>
        <div class="appointments-list" id="appointmentsList" style="display: none; opacity: 0; max-height: 0; padding: 16px;">
            <?php while ($row = $recent_appointments->fetch_assoc()): 
                $approval_history = $db->query("
                    SELECT ka.ktt_user_id, ka.action, ka.approval_date, u.full_name, u.company_name
                    FROM ktt_approvals ka
                    JOIN users u ON ka.ktt_user_id = u.id
                    WHERE ka.appointment_id = " . $row['id'] . "
                    ORDER BY ka.approval_date ASC
                ");
                
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
                        
                        if ($approval_history && $approval_history->num_rows > 0) {
                            $approval_history->data_seek(0);
                            while ($approval = $approval_history->fetch_assoc()): 
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
        <div class="empty-state" style="padding: 32px; text-align: center; color: #94a3b8;">
            <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 8px;"></i>
            <p>No appointment letter data recorded yet.</p>
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
        setTimeout(() => {
            list.style.display = 'none';
        }, 300);
    }
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
