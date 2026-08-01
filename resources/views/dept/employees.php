<?php
$page_title = 'Employees';
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

// Get statistics for current department
$total_employees = $db->query("SELECT COUNT(*) as count FROM employees WHERE department = '" . $db->escapeString($department) . "' AND is_active = 1")->fetch_assoc()['count'];
$verified_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE department = '" . $db->escapeString($department) . "' AND verification_status = 'verified' AND is_active = 1")->fetch_assoc()['count'];
$pending_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE department = '" . $db->escapeString($department) . "' AND verification_status = 'pending' AND is_active = 1")->fetch_assoc()['count'];
$rejected_count_stat = $db->query("SELECT COUNT(*) as count FROM employees WHERE department = '" . $db->escapeString($department) . "' AND verification_status = 'rejected' AND is_active = 1")->fetch_assoc()['count'];

// Get all employees for current department with appointment status
$employees = $db->query("
    SELECT e.*, 
           COUNT(ec.id) as cert_count,
           SUM(CASE WHEN ec.verification_status = 'verified' THEN 1 ELSE 0 END) as verified_cert_count,
           GROUP_CONCAT(ec.cert_number SEPARATOR ', ') as cert_numbers,
           u.full_name as verified_by_name,
           e.resubmit_count,
           e.resubmit_date,
           MAX(a.status) as appointment_status,
           MAX(a.approval_notes) as ktt_rejection_notes,
           MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) as has_ktt_rejection,
           CASE 
               WHEN MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) = 1 AND e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL THEN 'pending'
               WHEN MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) = 1 THEN 'rejected'
               WHEN MAX(a.status) = 'rejected' THEN 'rejected'
               WHEN e.verification_status = 'rejected' THEN 'rejected'
               ELSE e.verification_status
           END as combined_status
    FROM employees e
    LEFT JOIN employee_certifications ec ON e.id = ec.employee_id
    LEFT JOIN users u ON e.verified_by = u.id
    LEFT JOIN appointments a ON e.id = a.employee_id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id
    WHERE e.is_active = 1 AND e.department = '" . $db->escapeString($department) . "'
    GROUP BY e.id
    ORDER BY combined_status, e.created_at DESC
");

// Update employee status jika appointment sudah approved
if ($employees && $employees->num_rows > 0) {
    $employees->data_seek(0);
    while ($row = $employees->fetch_assoc()) {
        if ($row['appointment_status'] == 'approved' && $row['verification_status'] == 'pending') {
            $db->query("UPDATE employees SET verification_status = 'verified' WHERE id = " . intval($row['id']));
        }
    }
    // Refresh employees
    $employees = $db->query("SELECT e.*, COUNT(ec.id) as cert_count, SUM(CASE WHEN ec.verification_status = 'verified' THEN 1 ELSE 0 END) as verified_cert_count, GROUP_CONCAT(ec.cert_number SEPARATOR ', ') as cert_numbers, u.full_name as verified_by_name, e.resubmit_count, e.resubmit_date, MAX(a.status) as appointment_status, MAX(a.approval_notes) as ktt_rejection_notes, MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) as has_ktt_rejection, CASE WHEN MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) = 1 AND e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL THEN 'pending' WHEN MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) = 1 THEN 'rejected' WHEN MAX(a.status) = 'rejected' THEN 'rejected' WHEN e.verification_status = 'rejected' THEN 'rejected' ELSE e.verification_status END as combined_status FROM employees e LEFT JOIN employee_certifications ec ON e.id = ec.employee_id LEFT JOIN users u ON e.verified_by = u.id LEFT JOIN appointments a ON e.id = a.employee_id LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id WHERE e.is_active = 1 AND e.department = '" . $db->escapeString($department) . "' GROUP BY e.id ORDER BY combined_status, e.created_at DESC");
}

// Count rejected employees (including KTT rejections, but exclude resubmitted ones)
$rejected_count = $db->query("
    SELECT COUNT(DISTINCT e.id) as count 
    FROM employees e
    LEFT JOIN appointments a ON e.id = a.employee_id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id AND ka.action = 'reject'
    WHERE e.department = '" . $db->escapeString($department) . "' 
    AND (
        (e.verification_status = 'rejected') 
        OR 
        (ka.id IS NOT NULL AND NOT (e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL))
        OR
        (a.status = 'rejected' AND NOT (e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL))
    )
")->fetch_assoc()['count'];
?>

<div class="employees-container">
    <!-- Page Header -->
    <div class="page-header-custom">
        <div class="header-content">
            <h2><i class="fas fa-users"></i> <span data-lang="employee-list">Employee List</span></h2>
            <p><?php echo htmlspecialchars($department); ?></p>
        </div>
        <a href="add_employee.php" class="btn btn-primary btn-lg-custom">
            <i class="fas fa-plus-circle"></i> New Request
        </a>
    </div>
    
    <!-- Rejected Data Alert -->
    <?php if ($rejected_count > 0): ?>
    <div class="alert alert-warning alert-resubmit">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong data-lang="rejected-data">Rejected Data!</strong>
            <p>
                <span data-lang="there-are">There are</span> <strong><?php echo $rejected_count; ?></strong> <span data-lang="rejected-employee-data-suffix">rejected employee data that need to be corrected. Please click the "Upload Correction" button to resubmit the corrected data.</span>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-cards-row">
        <div class="stat-box stat-box-total">
            <div class="stat-number"><?php echo $total_employees; ?></div>
            <div class="stat-label" data-lang="all-employees">Seluruh Karyawan</div>
        </div>
        <div class="stat-box stat-box-verified">
            <div class="stat-number"><?php echo $verified_count; ?></div>
            <div class="stat-label" data-lang="accepted">Disetujui</div>
        </div>
        <div class="stat-box stat-box-pending">
            <div class="stat-number"><?php echo $pending_count; ?></div>
            <div class="stat-label" data-lang="pending">Menunggu</div>
        </div>
        <div class="stat-box stat-box-rejected">
            <div class="stat-number"><?php echo $rejected_count_stat; ?></div>
            <div class="stat-label" data-lang="rejected">Tidak disetujui</div>
        </div>
    </div>
    
    <!-- Bonsai.io Pagination Search Section -->
    

    <div class="card" style="margin-bottom: 16px; padding: 14px 18px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e9ecef;">
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 14px; z-index: 2;"></i>
                <input type="text" id="esSearchInput" autocomplete="off"
                       placeholder="Cari Nama, ID Badge, Posisi..."
                       style="width:100%; padding-left: 38px; padding-right: 36px; height: 40px; border-radius: 8px; border: 1px solid #ced4da; font-size: 14px;">
                <button type="button" id="esClearBtn" title="Bersihkan"
                        style="display:none; position: absolute; right: 9px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #a0aec0; cursor: pointer; font-size: 16px; padding: 0; z-index: 2;">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
            <select id="filterDeptEmpStatus" style="height:40px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:130px;">
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="rejected">Rejected</option>
            </select>
            <select id="filterDeptCompetencyType" style="height:40px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:170px;">
                <option value="">Semua Kompetensi</option>
                <option value="pengawas_operasional">Pengawas Operasional</option>
                <option value="pengawas_teknis">Pengawas Teknis</option>
                <option value="tenaga_teknis">Tenaga Teknis</option>
            </select>
            <select id="deptEmpPageLimit" style="height:40px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:90px;">
                <option value="10">10 / hal</option>
                <option value="25">25 / hal</option>
                <option value="50">50 / hal</option>
            </select>
        </div>
        <div id="deptEmpInfoContainer" style="margin-top:8px; font-size:13px; color:#6c757d;"></div>
    </div>

    <!-- Employees Table Card -->
    <div class="card">
        <div class="card-header-custom">
            <h3><i class="fas fa-list"></i> <span data-lang="complete-employee-list">Complete Employee List</span></h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-employees" id="employeesTable">
                    <thead>
                        <tr>
                            <th data-lang="id-badge">ID BADGE</th>
                            <th data-lang="name">Name</th>
                            <th data-lang="position">Position</th>
                            <th data-lang="competency-type">Competency Type</th>
                            <th data-lang="competency">Competency</th>
                            <th data-lang="status">Status</th>
                            <th data-lang="action">Action</th>
                        </tr>
                    </thead>
                    <tbody id="deptEmpTbody">
                        <tr><td colspan="7" style="text-align:center;padding:28px;color:#a0aec0;"><i class="fas fa-circle-notch fa-spin"></i> Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; flex-wrap:wrap; gap:8px;">
                <div id="deptEmpInfoContainerTable" style="font-size:13px; color:#6c757d;"></div>
                <div id="deptEmpPaginationContainer"></div>
            </div>

            <script src="../../assets/js/bonsai_pagination.js?v=<?php echo time(); ?>"></script>
            <script>
            (function() {
                const deptName = <?php echo json_encode($_SESSION['department'] ?? ''); ?>;
                const competencyLabels = {
                    'pengawas_operasional': 'Pengawas Operasional',
                    'pengawas_teknis': 'Pengawas Teknis',
                    'tenaga_teknis': 'Tenaga Teknis'
                };
                const statusBadges = {
                    'verified': '<span class="badge badge-success" data-lang="accept">Disetujui</span>',
                    'pending': '<span class="badge badge-warning" data-lang="pending">Menunggu</span>',
                    'rejected': '<span class="badge badge-danger" data-lang="reject">Tidak disetujui</span>'
                };

                window.deptEmpPagination = new BonsaiPagination({
                    apiUrl: '../../api/search_elasticsearch.php',
                    target: 'employees',
                    tableSelector: '#employeesTable',
                    tbodySelector: '#deptEmpTbody',
                    searchInputSelector: '#esSearchInput',
                    clearBtnSelector: '#esClearBtn',
                    paginationContainerSelector: '#deptEmpPaginationContainer',
                    infoContainerSelector: '#deptEmpInfoContainer',
                    limitSelector: '#deptEmpPageLimit',
                    filterSelectors: {
                        status: '#filterDeptEmpStatus',
                        competency_type: '#filterDeptCompetencyType'
                    },
                    defaultLimit: 10,
                    renderRow: function(item, index, rowNum) {
                        const status = item.approval_status || 'pending';
                        const compType = item.competency_type || '';
                        const compLabel = competencyLabels[compType] || compType;
                        const badge = statusBadges[status] || `<span class="badge">${status}</span>`;
                        const resubmitBtn = status === 'rejected'
                            ? `<a href="resubmit_employee.php?id=${item.id}" class="btn btn-sm btn-warning" title="Resubmit"><i class="fas fa-upload"></i> <span data-lang="resubmit">Resubmit</span></a>`
                            : '';
                        return `<tr class="emp-row" data-id="${item.id}">
                            <td><strong>${item.employee_code || '-'}</strong></td>
                            <td>${item.full_name || '-'}</td>
                            <td>${item.position || '-'}</td>
                            <td>${compLabel}</td>
                            <td>${item.competency_name || item.sub_competency || '-'}</td>
                            <td>${badge}</td>
                            <td>
                                <div class="action-buttons">
                                    <a href="employee_detail.php?id=${item.id}" class="btn btn-sm btn-info" title="View Details"><i class="fas fa-eye"></i> <span data-lang="view">View</span></a>
                                    ${resubmitBtn}
                                </div>
                            </td>
                        </tr>`;
                    }
                });

                // Pre-set department filter
                window.deptEmpPagination.filters['department'] = deptName;

                // Sync info
                const origInfo = window.deptEmpPagination.renderInfo.bind(window.deptEmpPagination);
                window.deptEmpPagination.renderInfo = function() {
                    origInfo();
                    const src = document.querySelector('#deptEmpInfoContainer');
                    const dest = document.querySelector('#deptEmpInfoContainerTable');
                    if (src && dest) dest.innerHTML = src.innerHTML;
                };
            })();
            </script>
        </div>
    </div>
    
    <!-- Back Button -->
    <div class="action-footer">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <span data-lang="back-to-dashboard">Back to Dashboard</span>
        </a>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('esSearchInput');
    const dropdown = document.getElementById('esSuggestionsDropdown');
    const empRows = document.querySelectorAll('#employeesTable tbody tr.emp-row');
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
        empRows.forEach(row => {
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
            fetch('../../api/search_elasticsearch.php?target=employees&q=' + encodeURIComponent(query) + '&limit=100')
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const ct = res.headers.get('content-type') || '';
                    if (!ct.includes('application/json')) throw new Error('Non-JSON response');
                    return res.json();
                })
                .then(data => {
                    if (data && data.status === 'success' && data.items) {
                        const matchingIds = new Set(data.items.map(item => String(item.id)));
                        filterTableLive(query, matchingIds);

                        if (dropdown && data.items.length > 0) {
                            renderSuggestions(data.items.slice(0, 8));
                        } else if (dropdown) {
                            dropdown.style.display = 'none';
                        }
                    }
                })
                .catch(err => console.warn('Elasticsearch live search notice:', err.message));
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
                    <div class="es-sug-name">${escapeHtml(item.full_name || item.employee_code)}</div>
                    <div class="es-sug-sub">${escapeHtml(item.position || '')} &bull; ${escapeHtml(item.contractor_company || '')}</div>
                </div>
                <span class="es-sug-badge">${escapeHtml(item.employee_code || '')}</span>
            `;
            div.addEventListener('click', function() {
                searchInput.value = item.full_name || item.employee_code;
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
