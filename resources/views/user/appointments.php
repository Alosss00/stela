<?php
$page_title = 'Assign Letter';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php
// Included via bootstrap/app.php

// Only USER role can access this page
checkPageAccess(['user']);

require_once dirname(__DIR__) . '/layouts/header.php';

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
    

    
    <!-- Bonsai.io Pagination Search Section -->
    <style>
    .es-autocomplete-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        max-height: 320px;
        overflow-y: auto;
        display: none;
    }
    .es-suggestion-item {
        padding: 10px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s ease;
    }
    .es-suggestion-item:last-child {
        border-bottom: none;
    }
    .es-suggestion-item:hover, .es-suggestion-item.active {
        background-color: #f1f5f9;
    }
    .es-sug-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 14px;
    }
    .es-sug-sub {
        font-size: 12px;
        color: #64748b;
        margin-top: 2px;
    }
    .es-sug-badge {
        font-size: 11px;
        font-weight: 600;
        background: #e2e8f0;
        color: #334155;
        padding: 3px 8px;
        border-radius: 6px;
    }
    .dataTables_filter, #appointmentsTable_filter {
        display: none !important;
    }
    </style>

    <div class="card-appt" style="margin-bottom: 16px; padding: 14px 18px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e9ecef;">
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 14px; z-index: 2;"></i>
                <input type="text" id="esSearchInput" autocomplete="off"
                       placeholder="Cari No. Registrasi, ID Badge, Nama Karyawan..."
                       style="width:100%; padding-left: 38px; padding-right: 36px; height: 40px; border-radius: 8px; border: 1px solid #ced4da; font-size: 14px;">
                <button type="button" id="esClearBtn" title="Bersihkan"
                        style="display:none; position: absolute; right: 9px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #a0aec0; cursor: pointer; font-size: 16px; padding: 0; z-index: 2;">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
            <select id="filterUserApptStatus" style="height:40px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:130px;">
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="draft">Draft</option>
            </select>
            <select id="userApptPageLimit" style="height:40px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:90px;">
                <option value="10">10 / hal</option>
                <option value="25">25 / hal</option>
                <option value="50">50 / hal</option>
            </select>
        </div>
        <div id="userApptInfoContainer" style="margin-top:8px; font-size:13px; color:#6c757d;"></div>
    </div>

    <!-- Appointments Table Card -->
    <div class="card-appt">
        <div class="card-header-appt">
            <h3><i class="fas fa-list"></i> <span data-lang="assign-letter-list">Assign Letter List</span></h3>
        </div>
        <div class="card-body-appt">
            <div class="table-responsive">
                <table class="table-appt" id="appointmentsTable">
                    <thead>
                        <tr>
                            <th class="col-number" data-lang="registration-no">No. Registration</th>
                            <th class="col-code" data-lang="id-badge">ID Badge</th>
                            <th class="col-name" data-lang="name">Name</th>
                            <th class="col-dept" data-lang="position">Position</th>
                            <th class="col-position" data-lang="competency">Competency</th>
                            <th class="col-status" data-lang="status">Status</th>
                            <th class="col-action" data-lang="action">Action</th>
                        </tr>
                    </thead>
                    <tbody id="userApptTbody">
                        <tr><td colspan="7" style="text-align:center;padding:28px;color:#a0aec0;"><i class="fas fa-circle-notch fa-spin"></i> Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; flex-wrap:wrap; gap:8px;">
                <div id="userApptInfoContainerTable" style="font-size:13px; color:#6c757d;"></div>
                <div id="userApptPaginationContainer"></div>
            </div>

            <script src="../../assets/js/bonsai_pagination.js"></script>
            <script>
            (function() {
                const companyName = <?php echo json_encode($_SESSION['company_name'] ?? ''); ?>;
                const statusClasses = {
                    'approved': 'success',
                    'pending': 'warning',
                    'rejected': 'danger',
                    'draft': 'secondary'
                };

                window.userApptPagination = new BonsaiPagination({
                    apiUrl: '../../api/search_elasticsearch.php',
                    target: 'appointments',
                    tableSelector: '#appointmentsTable',
                    tbodySelector: '#userApptTbody',
                    searchInputSelector: '#esSearchInput',
                    clearBtnSelector: '#esClearBtn',
                    paginationContainerSelector: '#userApptPaginationContainer',
                    infoContainerSelector: '#userApptInfoContainer',
                    limitSelector: '#userApptPageLimit',
                    filterSelectors: {
                        status: '#filterUserApptStatus'
                    },
                    defaultLimit: 10,
                    renderRow: function(item, index, rowNum) {
                        const status = item.status || 'pending';
                        const sClass = statusClasses[status] || 'secondary';
                        const printBtn = status === 'approved'
                            ? `<a href="../../print_appointment.php?id=${item.id}" class="btn-print-appt" target="_blank" title="Print"><i class="fas fa-print"></i></a>`
                            : '';
                        return `<tr class="appt-row" data-id="${item.id}">
                            <td class="col-number"><strong>${item.appointment_number || '-'}</strong></td>
                            <td class="col-code"><span class="code-badge">${item.employee_code || '-'}</span></td>
                            <td class="col-name"><strong>${item.employee_name || '-'}</strong></td>
                            <td class="col-dept">${item.position || '-'}</td>
                            <td class="col-position"><span class="position-badge">${item.competency_name || '-'}</span></td>
                            <td class="col-status"><span class="badge-status badge-${sClass}">${status.toUpperCase()}</span></td>
                            <td class="col-action">
                                <div class="action-buttons-appt">
                                    ${printBtn}
                                    <a href="appointment_detail.php?id=${item.id}" class="btn-detail-appt"><i class="fas fa-eye"></i> <span data-lang="view">View</span></a>
                                </div>
                            </td>
                        </tr>`;
                    }
                });

                // Pre-set company filter
                window.userApptPagination.filters['company'] = companyName;

                // Sync info
                const origInfo = window.userApptPagination.renderInfo.bind(window.userApptPagination);
                window.userApptPagination.renderInfo = function() {
                    origInfo();
                    const src = document.querySelector('#userApptInfoContainer');
                    const dest = document.querySelector('#userApptInfoContainerTable');
                    if (src && dest) dest.innerHTML = src.innerHTML;
                };
            })();
            </script>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('esSearchInput');
    const dropdown = document.getElementById('esSuggestionsDropdown');
    const apptRows = document.querySelectorAll('#appointmentsTable tbody tr.appt-row');
    const clearBtn = document.getElementById('esClearBtn');
    let debounceTimer = null;

    if (!searchInput) return;

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterTableLive('');
            clearBtn.style.display = 'none';
            if (dropdown) {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
            }
            searchInput.focus();
        });
    }

    function filterTableLive(query, matchingIds = null) {
        const cleanQ = query.toLowerCase().trim();
        apptRows.forEach(row => {
            const rowId = row.dataset.id;
            const textContent = row.textContent.toLowerCase();
            let isMatch = false;

            if (cleanQ === '') {
                isMatch = true;
            } else if (matchingIds !== null && matchingIds.size > 0) {
                isMatch = matchingIds.has(rowId) || textContent.includes(cleanQ);
            } else {
                isMatch = textContent.includes(cleanQ);
            }

            row.style.display = isMatch ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(debounceTimer);

        if (clearBtn) {
            clearBtn.style.display = query.length > 0 ? 'block' : 'none';
        }

        filterTableLive(query);

        if (query.length < 1) {
            if (dropdown) {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
            }
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch('../../api/search_elasticsearch.php?target=appointments&q=' + encodeURIComponent(query) + '&limit=100')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.items) {
                        const matchingIds = new Set(data.items.map(item => String(item.id)));
                        filterTableLive(query, matchingIds);

                        if (dropdown && data.items.length > 0) {
                            renderSuggestions(data.items.slice(0, 8));
                        } else if (dropdown) {
                            dropdown.style.display = 'none';
                        }
                    }
                })
                .catch(err => console.error('Elasticsearch appointments search error:', err));
        }, 150);
    });

    function renderSuggestions(items) {
        if (!dropdown) return;
        dropdown.innerHTML = '';
        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'es-suggestion-item';
            div.innerHTML = `
                <div>
                    <div class="es-sug-name">${escapeHtml(item.appointment_number || item.employee_name)}</div>
                    <div class="es-sug-sub">${escapeHtml(item.employee_name || '')} &bull; ${escapeHtml(item.contractor_company || '')}</div>
                </div>
                <span class="es-sug-badge">${escapeHtml(item.status || '')}</span>
            `;
            div.addEventListener('click', function() {
                searchInput.value = item.appointment_number || item.employee_name;
                filterTableLive(searchInput.value);
                dropdown.style.display = 'none';
            });
            dropdown.appendChild(div);
        });
        dropdown.style.display = 'block';
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    document.addEventListener('click', function(e) {
        if (dropdown && !dropdown.contains(e.target) && e.target !== searchInput) {
            dropdown.style.display = 'none';
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>




