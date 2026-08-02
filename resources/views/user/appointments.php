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

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$user_filter = "(a.created_by = '$current_user_id' OR (a.created_by IS NULL AND e.contractor_company = '" . $db->escapeString($company_name) . "'))";

// Build query with filter
$where_clause = $user_filter;
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
$all_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE $user_filter")->fetch_assoc()['count'];
$pending_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE $user_filter AND a.status = 'pending'")->fetch_assoc()['count'];
$approved_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE $user_filter AND a.status = 'approved'")->fetch_assoc()['count'];
$rejected_count = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE $user_filter AND a.status = 'rejected'")->fetch_assoc()['count'];
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

            <script src="../../assets/js/bonsai_pagination.js?v=<?php echo time(); ?>"></script>
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
