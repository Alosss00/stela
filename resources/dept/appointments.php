<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Appointment Letters';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php
// Included via bootstrap/app.php

// Only department access permitted
requirePermission('appointment.view');
if (!hasPermission('dept.access') && !(hasPermission('user.access') && hasDepartment()) && !isSuperadmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

require_once dirname(__DIR__) . '/layouts/header.php';

$db = new Database();
$department = $_SESSION['department'] ?? '';

// Filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query with filter
$where_clause = "e.department = '" . $db->escapeString($department) . "'";
if ($status_filter != 'all') {
    $where_clause .= " AND a.status = '" . $db->escapeString($status_filter) . "'";
}

// Get appointments for employees in current department
$appointments = $db->query("
    SELECT a.*, 
           e.employee_code, e.full_name, e.position, e.department, e.contractor_company,
           e.competency_type, e.competency_name,
           p.position_name,
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
    ORDER BY a.created_at DESC, a.id DESC
");

// Get statistics

$all_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.deleted_at IS NULL AND e.deleted_at IS NULL AND e.department = '" . $db->escapeString($department) . "'")->fetch_assoc()['count'];
$pending_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.deleted_at IS NULL AND e.deleted_at IS NULL AND e.department = '" . $db->escapeString($department) . "' AND a.status = 'pending'")->fetch_assoc()['count'];
$approved_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.deleted_at IS NULL AND e.deleted_at IS NULL AND e.department = '" . $db->escapeString($department) . "' AND a.status = 'approved'")->fetch_assoc()['count'];
$rejected_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.deleted_at IS NULL AND e.deleted_at IS NULL AND e.department = '" . $db->escapeString($department) . "' AND a.status = 'rejected'")->fetch_assoc()['count'];
?>

<div class="appointments-container">
    <!-- Page Header -->
    <div class="page-header-appt">
        <div class="header-left">
            <h2><i class="fas fa-file-alt"></i> <span data-lang="appointment-letters">Appointment Letters</span></h2>
            <p><?php echo htmlspecialchars($department); ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> <span data-lang="back">Kembali</span>
        </a>
    </div>
    
    <!-- Statistics Cards -->
    <style>
        .custom-stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #f0f0f0;
            border-left: 4px solid;
        }
        .custom-stat-card .stat-info { display: flex; flex-direction: column; }
        .custom-stat-card .stat-title { font-size: 14px; color: #6c757d; font-weight: 600; margin-bottom: 8px; }
        .custom-stat-card .stat-number { font-size: 32px; font-weight: bold; margin-bottom: 5px; line-height: 1; }
        .custom-stat-card .stat-desc { font-size: 12px; color: #adb5bd; font-weight: 500; }
        .custom-stat-card .stat-icon { width: 55px; height: 55px; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 28px; }
        
        .stat-variant-blue { border-left-color: #1A73E8; } .stat-variant-blue .stat-number { color: #1A73E8; } .stat-variant-blue .stat-icon { background: #E8F0FE; color: #1A73E8; }
        .stat-variant-green { border-left-color: #1E8E3E; } .stat-variant-green .stat-number { color: #1E8E3E; } .stat-variant-green .stat-icon { background: #E6F4EA; color: #1E8E3E; }
        .stat-variant-orange { border-left-color: #F57C00; } .stat-variant-orange .stat-number { color: #F57C00; } .stat-variant-orange .stat-icon { background: #FFF3E0; color: #F57C00; }
        .stat-variant-red { border-left-color: #D93025; } .stat-variant-red .stat-number { color: #D93025; } .stat-variant-red .stat-icon { background: #FCE8E6; color: #D93025; }
    </style>
    
    <div class="stats-row-appt" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="custom-stat-card stat-variant-blue">
            <div class="stat-info">
                <div class="stat-title" data-lang="all-employees">All Employees</div>
                <div class="stat-number"><?php echo $all_count; ?></div>
                <div class="stat-desc" data-lang="total-registered">Total registered workforce</div>
            </div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        
        <div class="custom-stat-card stat-variant-green">
            <div class="stat-info">
                <div class="stat-title" data-lang="accept">Accept</div>
                <div class="stat-number"><?php echo $approved_count; ?></div>
                <div class="stat-desc" data-lang="verified-active">Verified & active</div>
            </div>
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        </div>
        
        <div class="custom-stat-card stat-variant-orange">
            <div class="stat-info">
                <div class="stat-title" data-lang="pending">Pending</div>
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-desc" data-lang="awaiting-review">Awaiting review</div>
            </div>
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
        </div>
        
        <div class="custom-stat-card stat-variant-red">
            <div class="stat-info">
                <div class="stat-title" data-lang="reject">Reject</div>
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-desc" data-lang="needs-correction">Needs correction</div>
            </div>
            <div class="stat-icon"><i class="fas fa-user-times"></i></div>
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
    
    <!-- Bonsai.io Pagination Search Section -->
    

    <div class="card-appt es-search-card" style="margin-bottom: 16px; padding: 14px 18px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e9ecef; position: relative; z-index: 1050; overflow: visible;">
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
            <select id="filterDeptApptStatus" style="height:40px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:130px;">
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="draft">Draft</option>
            </select>
            <select id="deptApptPageLimit" style="height:40px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:90px;">
                <option value="10">10 / hal</option>
                <option value="25">25 / hal</option>
                <option value="50">50 / hal</option>
            </select>
        </div>
        <div id="deptApptInfoContainer" style="margin-top:8px; font-size:13px; color:#6c757d;"></div>
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
                    <tbody id="deptApptTbody">
                        <?php if ($appointments && $appointments->num_rows > 0): ?>
                            <?php 
                            if ($appointments->num_rows > 0) $appointments->data_seek(0);
                            while ($apt = $appointments->fetch_assoc()): 
                            ?>
                            <tr class="appt-row" data-id="<?php echo $apt['id']; ?>">
                                <td class="col-number"><strong><?php echo htmlspecialchars($apt['appointment_number'] ?? '-'); ?></strong></td>
                                <td class="col-code"><span class="code-badge"><?php echo htmlspecialchars($apt['employee_code'] ?? '-'); ?></span></td>
                                <td class="col-name"><strong><?php echo htmlspecialchars($apt['full_name'] ?? '-'); ?></strong></td>
                                <td class="col-dept"><?php echo htmlspecialchars($apt['position'] ?? '-'); ?></td>
                                <td class="col-position"><span class="position-badge"><?php echo htmlspecialchars($apt['position_name'] ?? '-'); ?></span></td>
                                <td class="col-status"><span class="badge-status badge-<?php echo $apt['status_class']; ?>"><?php echo strtoupper($apt['status']); ?></span></td>
                                <td class="col-action">
                                    <div class="action-buttons-appt">
                                        <?php if ($apt['status'] === 'approved'): ?>
                                        <a href="../../exports/print_appointment.php?id=<?php echo $apt['id']; ?>" class="btn-print-appt" target="_blank" title="Print"><i class="fas fa-print"></i></a>
                                        <?php endif; ?>
                                        <a href="appointments_detail.php?id=<?php echo $apt['id']; ?>" class="btn-detail-appt"><i class="fas fa-eye"></i> View</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;padding:28px;color:#a0aec0;">Tidak ada data yang ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; flex-wrap:wrap; gap:8px;">
                <div id="deptApptInfoContainerTable" style="font-size:13px; color:#6c757d;"></div>
                <div id="deptApptPaginationContainer"></div>
            </div>

            <script src="../../assets/js/bonsai_pagination.js?v=<?php echo time(); ?>"></script>
            <script>
            (function() {
                const deptName = <?php echo json_encode($_SESSION['department'] ?? ''); ?>;
                const statusClasses = {
                    'approved': 'success',
                    'pending': 'warning',
                    'rejected': 'danger',
                    'draft': 'secondary'
                };

                window.deptApptPagination = new BonsaiPagination({
                    apiUrl: '../../api/search_elasticsearch.php',
                    target: 'appointments',
                    tableSelector: '#appointmentsTable',
                    tbodySelector: '#deptApptTbody',
                    searchInputSelector: '#esSearchInput',
                    clearBtnSelector: '#esClearBtn',
                    paginationContainerSelector: '#deptApptPaginationContainer',
                    infoContainerSelector: '#deptApptInfoContainer',
                    limitSelector: '#deptApptPageLimit',
                    filters: {
                        department: deptName
                    },
                    filterSelectors: {
                        status: '#filterDeptApptStatus'
                    },
                    defaultLimit: 10,
                    renderRow: function(item, index, rowNum) {
                        const status = item.status || 'pending';
                        const sClass = statusClasses[status] || 'secondary';
                        const printBtn = status === 'approved'
                            ? `<a href="../../exports/print_appointment.php?id=${item.id}" class="btn-print-appt" target="_blank" title="Print"><i class="fas fa-print"></i></a>`
                            : '';
                        return `<tr class="appt-row" data-id="${item.id}">
                            <td class="col-number"><strong>${item.appointment_number || '-'}</strong></td>
                            <td class="col-code"><span class="code-badge">${item.employee_code || '-'}</span></td>
                            <td class="col-name"><strong>${item.employee_name || item.full_name || '-'}</strong></td>
                            <td class="col-dept">${item.position || '-'}</td>
                            <td class="col-position"><span class="position-badge">${item.competency_name || '-'}</span></td>
                            <td class="col-status"><span class="badge-status badge-${sClass}">${status.toUpperCase()}</span></td>
                            <td class="col-action">
                                <div class="action-buttons-appt">
                                    ${printBtn}
                                    <a href="appointments_detail.php?id=${item.id}" class="btn-detail-appt"><i class="fas fa-eye"></i> View</a>
                                </div>
                            </td>
                        </tr>`;
                    }
                });

                // Sync info
                const origInfo = window.deptApptPagination.renderInfo.bind(window.deptApptPagination);
                window.deptApptPagination.renderInfo = function() {
                    origInfo();
                    const src = document.querySelector('#deptApptInfoContainer');
                    const dest = document.querySelector('#deptApptInfoContainerTable');
                    if (src && dest) dest.innerHTML = src.innerHTML;
                };
            })();
            </script>
        </div>
    </div>
</div>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
