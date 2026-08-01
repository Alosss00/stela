<?php
$page_title = 'Appointment Letters';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php
// Included via bootstrap/app.php

// Only department_user role or user with department can access this page
if (!hasDepartment() && $_SESSION['role'] != 'department_user') {
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
    ORDER BY a.created_at DESC
");

// Perbaiki status certification jika sudah di-approve admin
if ($appointments && $appointments->num_rows > 0) {
    while ($apt = $appointments->fetch_assoc()) {
        $employee_id = $apt['employee_id'];
        $status = $apt['status'];
        if ($status == 'approved') {
            $db->query("UPDATE employee_certifications SET verification_status = 'verified' WHERE employee_id = " . intval($employee_id) . " AND verification_status = 'pending'");
        }
    }
    // Refresh appointments
    $appointments = $db->query("SELECT a.*, e.employee_code, e.full_name, e.position, e.department, e.contractor_company, e.competency_type, e.competency_name, p.position_name, CASE WHEN a.status = 'approved' THEN 'success' WHEN a.status = 'pending' THEN 'warning' WHEN a.status = 'rejected' THEN 'danger' WHEN a.status = 'draft' THEN 'secondary' ELSE 'secondary' END as status_class FROM appointments a JOIN employees e ON a.employee_id = e.id LEFT JOIN positions p ON a.position_id = p.id WHERE $where_clause ORDER BY a.created_at DESC");
}

// Get statistics
$all_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE e.department = '" . $db->escapeString($department) . "'")->fetch_assoc()['count'];
$pending_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE e.department = '" . $db->escapeString($department) . "' AND a.status = 'pending'")->fetch_assoc()['count'];
$approved_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE e.department = '" . $db->escapeString($department) . "' AND a.status = 'approved'")->fetch_assoc()['count'];
$rejected_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE e.department = '" . $db->escapeString($department) . "' AND a.status = 'rejected'")->fetch_assoc()['count'];
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
    <div class="stats-row-appt">
        <div class="stat-box-appt stat-all">
            <div class="stat-icon-appt"><i class="fas fa-file"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $all_count; ?></div>
                <div class="stat-text" data-lang="all-assign-letter">Semua Surat Penunjukan</div>
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
                <div class="stat-text" data-lang="accepted">Disetujui</div>
            </div>
        </div>
        
        <div class="stat-box-appt stat-rejected">
            <div class="stat-icon-appt"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-text" data-lang="rejected">Tidak disetujui</div>
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
                        <tr><td colspan="7" style="text-align:center;padding:28px;color:#a0aec0;"><i class="fas fa-circle-notch fa-spin"></i> Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; flex-wrap:wrap; gap:8px;">
                <div id="deptApptInfoContainerTable" style="font-size:13px; color:#6c757d;"></div>
                <div id="deptApptPaginationContainer"></div>
            </div>

            <script src="../../assets/js/bonsai_pagination.js"></script>
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
                    filterSelectors: {
                        status: '#filterDeptApptStatus'
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

                // Pre-set department filter
                window.deptApptPagination.filters['department'] = deptName;

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
/* Selaraskan warna ikon dengan palet utama dashboard */
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
    gap: 6px;
    align-items: center;
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

.text-muted {
    color: #999;
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




