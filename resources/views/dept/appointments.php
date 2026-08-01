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
