<?php
$page_title = 'Assign Letter';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Only USER role can access this page
checkPageAccess(['user']);

require_once '../../includes/header.php';

$db = new Database();
$company_name = $_SESSION['company_name'] ?? '';

// Pastikan session sudah aktif di bagian paling atas file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query with filter
$where_clause = "e.contractor_company = '" . $db->escapeString($company_name) . "'";
if ($status_filter != 'all') {
    $where_clause .= " AND a.status = '" . $db->escapeString($status_filter) . "'";
}

// Handle resubmit to KTT action
if (isset($_GET['action']) && $_GET['action'] == 'resubmit_to_ktt' && isset($_GET['id'])) {
    
    // --- 1. VALIDASI TOKEN ANTI-CSRF ---
    if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals(
    $_SESSION['csrf_token'],
    $_GET['csrf_token']
)) {
        
        $error_message = "Akses ditolak: Token keamanan tidak valid atau telah kedaluwarsa.";
        
    } else {
        
        // --- 2. LOGIKA UTAMA (Hanya berjalan jika token valid) ---
        $appointment_id = intval($_GET['id']);

        // Verify this appointment belongs to user's company and is resubmittable
        $verify_result = $db->query("
            SELECT a.id, e.verification_status, e.resubmit_count
            FROM appointments a
            JOIN employees e ON a.employee_id = e.id
            WHERE a.id = $appointment_id
            AND a.status = 'pending'
            AND a.admin_approval_action = 'send_to_user'
            AND e.verification_status = 'verified'
            AND e.resubmit_count > 0
            AND e.contractor_company = '" . $db->escapeString($company_name) . "'
        ");

        if ($verify_result && $verify_result->num_rows > 0) {
            // Get which KTT needs to review (from requires flags)
            $appt_details = $db->query("
                SELECT requires_ktt_msm_review, requires_ktt_ttn_review
                FROM appointments
                WHERE id = $appointment_id
            ")->fetch_assoc();

            // Prepare KTT status reset based on which KTT needs to review
            $ktt_status_reset = "";
            if ($appt_details['requires_ktt_msm_review'] == 1) {
                $ktt_status_reset = ", ktt_msm_status = 'pending', ktt1_approved_by = NULL, ktt1_approved_date = NULL";
            }
            if ($appt_details['requires_ktt_ttn_review'] == 1) {
                $ktt_status_reset .= ", ktt_ttn_status = 'pending', ktt2_approved_by = NULL, ktt2_approved_date = NULL";
            }

            // Reset admin_approval_action to NULL so appointment becomes visible to KTT
            $update_sql = "UPDATE appointments SET
                          admin_approval_action = NULL,
                          admin_approval_notes = NULL,
                          admin_approved_by = NULL,
                          admin_approved_date = NULL
                          $ktt_status_reset
                          WHERE id = $appointment_id";

            if ($db->query($update_sql)) {
                $success_message = "Appointment letter has been resubmitted to KTT for review.";
                header("Location: appointments.php?success=resubmit");
                exit();
            } else {
                $error_message = "Failed to resubmit appointment letter!";
            }
        } else {
            $error_message = "Invalid appointment or not eligible for resubmit!";
        }
    }
}

// Display success message
if (isset($_GET['success']) && $_GET['success'] == 'resubmit') {
    $success_message = "Appointment letter has been successfully resubmitted to KTT for review.";
}

// Get all appointments for this company
$appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.position,
           e.verification_status, e.resubmit_count,
           p.position_name, p.position_type,
           CASE
               WHEN a.status = 'approved' THEN 'success'
               WHEN a.status = 'pending' THEN 'warning'
               WHEN a.status = 'rejected' THEN 'danger'
               WHEN a.status = 'draft' THEN 'secondary'
               ELSE 'secondary'
           END as status_class
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN positions p ON a.position_id = p.id
    WHERE $where_clause
    ORDER BY a.created_at DESC
");

// Get statistics
$all_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE e.contractor_company = '" . $db->escapeString($company_name) . "'")->fetch_assoc()['count'];
$pending_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE e.contractor_company = '" . $db->escapeString($company_name) . "' AND a.status = 'pending'")->fetch_assoc()['count'];
$approved_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE e.contractor_company = '" . $db->escapeString($company_name) . "' AND a.status = 'approved'")->fetch_assoc()['count'];
$rejected_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE e.contractor_company = '" . $db->escapeString($company_name) . "' AND a.status = 'rejected'")->fetch_assoc()['count'];
?>

<div class="appointments-container">
    <!-- Page Header -->
    <div class="page-header-appt">
        <div class="header-left">
            <h2><i class="fas fa-file-alt"></i> <span data-lang="assign-letter">Assign Letter</span></h2>
            <p><?php echo htmlspecialchars($company_name); ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> <span data-lang="back">Back</span>
        </a>
    </div>

    <!-- Success Message -->
    <?php if (isset($success_message)): ?>
    <div class="alert alert-success" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; background: #E8F5E9; color: #1B5E20; border: 1px solid #2E7D32; border-radius: 8px; margin: 20px 0;">
        <i class="fas fa-check-circle" style="font-size: 20px;"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
    <div class="alert alert-error" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; border-radius: 8px; margin: 20px 0;">
        <i class="fas fa-exclamation-circle" style="font-size: 20px;"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-row-appt">
        <div class="stat-box-appt stat-all">
            <div class="stat-icon-appt"><i class="fas fa-file"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $all_count; ?></div>
                <div class="stat-text" data-lang="all-assign-letter">All Assign Letter</div>
            </div>
        </div>
        
        <div class="stat-box-appt stat-pending">
            <div class="stat-icon-appt"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-text" data-lang="pending">Menunggu</div>
            </div>
        </div>
        
        <div class="stat-box-appt stat-approved">
            <div class="stat-icon-appt"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $approved_count; ?></div>
                <div class="stat-text" data-lang="accept">Accept</div>
            </div>
        </div>
        
        <div class="stat-box-appt stat-rejected">
            <div class="stat-icon-appt"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-text" data-lang="reject">Reject</div>
            </div>
        </div>
    </div>
    
    <!-- Filter Card -->
    <div class="filter-card-appt">
        <form method="GET" action="" class="filter-form-appt">
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> <span data-lang="filter-status-label">Filter Status:</span></label>
                <select name="status" class="form-control-appt" onchange="this.form.submit()">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status (<?php echo $all_count; ?>)</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending (<?php echo $pending_count; ?>)</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Accept (<?php echo $approved_count; ?>)</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Reject (<?php echo $rejected_count; ?>)</option>
                </select>
            </div>
        </form>
    </div>
    
    <!-- Appointments Table Card -->
    <div class="card-appt">
        <div class="card-header-appt">
            <h3><i class="fas fa-list"></i> <span data-lang="assign-letter-list">Assign Letter List</span></h3>
        </div>
        <div class="card-body-appt">
            <?php if ($appointments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table-appt">
                        <thead>
                            <tr>
                                <th class="col-number" data-lang="registration-no">No. Registrastion</th>
                                <th class="col-code" data-lang="id-badge">ID Badge</th>
                                <th class="col-name" data-lang="name">Name</th>
                                <th class="col-dept" data-lang="position">Position</th>
                                <th class="col-position" data-lang="competency">Competency</th>
                                <th class="col-date" data-lang="effective-date">Effective Date</th>
                                <th class="col-expiry" data-lang="expiry-date">Expiry Date</th>
                                <th class="col-status" data-lang="status">Status</th>
                                <th class="col-action" data-lang="action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $appointments->fetch_assoc()): ?>
                            <tr class="appt-row">
                                <td class="col-number">
                                    <strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong>
                                </td>
                                <td class="col-code">
                                    <span class="code-badge"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                                </td>
                                <td class="col-name">
                                    <strong><?php echo htmlspecialchars($row['employee_name']); ?></strong>
                                </td>
                                <td class="col-dept">
                                    <?php echo htmlspecialchars($row['position'] ?? '-'); ?>
                                </td>
                                <td class="col-position">
                                    <span class="position-badge"><?php echo htmlspecialchars($row['position_name']); ?></span>
                                </td>
                                <td class="col-date">
                                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($row['effective_date'])); ?>
                                </td>
                                <td class="col-expiry">
                                    <?php 
                                    if ($row['expiry_date']) {
                                        echo '<i class="fas fa-calendar-times"></i> ' . date('d/m/Y', strtotime($row['expiry_date']));
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td class="col-status">
                                    <?php 
                                    $status_label = [
                                        'draft' => 'Draft',
                                        'pending' => 'Pending',
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected',
                                        'expired' => 'Expired'
                                    ];
                                    $status_lang_keys = [
                                        'draft' => 'draft',
                                        'pending' => 'pending',
                                        'approved' => 'approved',
                                        'rejected' => 'rejected',
                                        'expired' => 'expired'
                                    ];
                                    ?>
                                    <span class="badge-status badge-<?php echo $row['status_class']; ?>" <?php echo isset($status_lang_keys[$row['status']]) ? 'data-lang="' . htmlspecialchars($status_lang_keys[$row['status']]) . '"' : ''; ?>>
                                        <?php echo $status_label[$row['status']] ?? strtoupper($row['status']); ?>
                                    </span>
                                </td>
                                <td class="col-action">
                                    <div class="action-buttons-appt">
                                        <?php if ($row['status'] == 'approved'): ?>
                                        <a href="../../print_appointment.php?id=<?php echo $row['id']; ?>" class="btn-print-appt" target="_blank" title="Cetak" data-lang-title="print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php
                                        // Show "Resubmit to KTT" button if:
                                        // 1. Appointment status = 'pending'
                                        // 2. Admin sent back to user (admin_approval_action = 'send_to_user')
                                        // 3. Employee is verified (verification_status = 'verified')
                                        // 4. Employee has resubmitted data (resubmit_count > 0)
                                        $can_resubmit = (
                                            $row['status'] == 'pending' &&
                                            $row['admin_approval_action'] == 'send_to_user' &&
                                            $row['verification_status'] == 'verified' &&
                                            $row['resubmit_count'] > 0
                                        );

                                        if ($can_resubmit): ?>
                                        <a href="appointments.php?action=resubmit_to_ktt&id=<?php echo $row['id']; ?>"
                                           class="btn-resubmit-appt"
                                           onclick="return confirm(window.getLanguageText(''))"
                                           title="Resubmit to KTT" data-lang-title="resubmit-to-ktt">
                                            <i class="fas fa-paper-plane"></i> <span data-lang="resubmit-to-ktt">Resubmit to KTT</span>
                                        </a>
                                        <?php endif; ?>

                                        <a href="appointment_detail.php?id=<?php echo $row['id']; ?>" class="btn-detail-appt">
                                            <i class="fas fa-eye"></i> <span data-lang="view">View</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-appt">
                    <i class="fas fa-inbox"></i>
                    <p data-lang="no-assign-letters">No assign letters</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.appointments-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-appt {
    background: #F57C00;
    color: white;
    padding: 35px 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(245, 124, 0, 0.3);
}

.header-left h2 {
    margin: 0 0 8px 0;
    font-size: 26px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-left p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

/* Stats Row */
.stats-row-appt {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-box-appt {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 15px;
    border-left: 4px solid #ccc;
    transition: all 0.3s ease;
}

.stat-box-appt:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
}

.stat-all { border-left-color: #37474F; }
.stat-pending { border-left-color: #f59e0b; }
.stat-approved { border-left-color: #2E7D32; }
.stat-rejected { border-left-color: #ef4444; }

.stat-icon-appt {
    font-size: 28px;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: white;
}

.stat-all .stat-icon-appt { background: #37474F; }
.stat-pending .stat-icon-appt { background: #f59e0b; }
.stat-approved .stat-icon-appt { background: #2E7D32; }
.stat-rejected .stat-icon-appt { background: #ef4444; }
/* Selaraskan warna ikon dengan palet utama website */
.stat-all .stat-icon-appt { background: #FFA240; }
.stat-pending .stat-icon-appt { background: linear-gradient(135deg, #FFD600, #FFB300); color: #F57C00; }
.stat-approved .stat-icon-appt { background: linear-gradient(135deg, #F57C00, #FF9800); }
.stat-rejected .stat-icon-appt { background: linear-gradient(135deg, #EF5350, #D32F2F); }

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #333;
}

.stat-text {
    color: #666;
    font-size: 12px;
}

/* Filter Card */
.filter-card-appt {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.filter-form-appt {
    display: flex;
    align-items: flex-end;
    gap: 15px;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    max-width: 300px;
}

.filter-group label {
    font-weight: 600;
    color: #333;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
}

.form-control-appt {
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-control-appt:hover,
.form-control-appt:focus {
    border-color: #37474F;
    outline: none;
}

/* Card */
.card-appt {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.card-header-appt {
    padding: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header-appt h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header-appt i {
    color: #37474F;
}

.card-body-appt {
    padding: 0;
}

/* Table */
.table-appt {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table-appt thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 15px;
    text-align: left;
}

.appt-row {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.appt-row:hover {
    background-color: #f8f9ff;
}

.table-appt td {
    padding: 15px;
    vertical-align: middle;
    font-size: 13px;
}

.col-number { width: 12%; }
.col-code { width: 10%; }
.col-name { width: 15%; }
.col-dept { width: 12%; }
.col-position { width: 15%; }
.col-date { width: 12%; }
.col-expiry { width: 12%; }
.col-status { width: 10%; }
.col-action { width: 12%; }

.code-badge {
    background: #ECEFF1;
    color: #37474F;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.position-badge {
    background: #f3f4f6;
    color: #666;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    display: inline-block;
}

.badge-status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.badge-success {
    background: #E8F5E9;
    color: #2E7D32;
}

.badge-warning {
    background: #fef3c7;
    color: #f59e0b;
}

.badge-danger {
    background: #fee2e2;
    color: #ef4444;
}

.badge-secondary {
    background: #f3f4f6;
    color: #666;
}

.action-buttons-appt {
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-print-appt {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: #E8F5E9;
    color: #2E7D32;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-print-appt:hover {
    background: #2E7D32;
    color: white;
    transform: translateY(-1px);
}

.btn-resubmit-appt {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: linear-gradient(135deg, #f59e0b 0%, #fb923c 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
}

.btn-resubmit-appt:hover {
    background: linear-gradient(135deg, #d97706 0%, #f97316 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(245, 158, 11, 0.4);
}

.btn-detail-appt {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #ECEFF1;
    color: #37474F;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-detail-appt:hover {
    background: #37474F;
    color: white;
    transform: translateY(-1px);
}

/* Empty State */
.empty-state-appt {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-appt i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state-appt p {
    margin: 0;
    font-size: 16px;
}

/* Responsive */
@media (max-width: 1024px) {
    .page-header-appt {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .col-dept { display: none; }
    .col-position { display: none; }
}

@media (max-width: 768px) {
    .page-header-appt {
        padding: 25px 15px;
    }
    
    .header-left h2 {
        font-size: 20px;
    }
    
    .stats-row-appt {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-form-appt {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        max-width: none;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .col-code { display: none; }
    .col-date { display: none; }
    .col-expiry { display: none; }
    
    .table-responsive {
        font-size: 12px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>




