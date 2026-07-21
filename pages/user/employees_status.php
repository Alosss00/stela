<?php
$page_title = 'Employee Status';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/notifications.php';

$db = new Database();
$message = '';
$error = '';


// Get filter from URL
$filter = isset($_GET['filter']) ? $db->escapeString($_GET['filter']) : '';

// Build WHERE clause for filter
$where_clause = "e.is_active = 1";
if (!empty($filter)) {
    $where_clause .= " AND e.employee_status='$filter'";
}

// Get all employees with verification status and KTT rejection awareness
$employees = $db->query("
SELECT
    e.id,
    e.employee_code,
    e.full_name,
    e.contractor_company,
    e.position,
    e.competency_type,
    e.competency_name,
    e.employee_status,
    e.resign_date,

    a.appointment_number,
    a.appointment_date

FROM employees e

INNER JOIN appointments a
    ON a.employee_id = e.id

WHERE
    $where_clause
    AND a.status='approved'
    AND a.is_current=1

ORDER BY e.full_name ASC
");

require_once '../../includes/header.php';

// Get unique companies for filter (moved here before statistics calculation)
$companies = $db->query("
    SELECT DISTINCT contractor_company
    FROM employees
    WHERE is_active = 1
    ORDER BY contractor_company
");

// Get statistics
$total_employees =
$db->query("
SELECT COUNT(*) total
FROM employees
WHERE is_active=1
")->fetch_assoc()['total'];

$active_count =
$db->query("
SELECT COUNT(*) total
FROM employees
WHERE employee_status='active'
")->fetch_assoc()['total'];

$resigned_count =
$db->query("
SELECT COUNT(*) total
FROM employees
WHERE employee_status='resigned'
")->fetch_assoc()['total'];

$inactive_count =
$db->query("
SELECT COUNT(*) total
FROM employees
WHERE is_active=0
")->fetch_assoc()['total'];

// Get statistics per company
$companies_stats = [];
if ($companies && $companies->num_rows > 0) {
    $companies->data_seek(0);
    while($comp=$companies->fetch_assoc()){
        $company_name=$comp['contractor_company'];
        $stats=$db->query("
        SELECT
        COUNT(*) total,
        SUM(employee_status='active') active,
        SUM(employee_status='resign') resign
        FROM employees
        WHERE contractor_company='".$db->escapeString($company_name)."'
        AND is_active=1
        ")->fetch_assoc();
        $companies_stats[$company_name]=$stats;
    }
}

// Reset companies pointer for filter dropdown
$companies = $db->query("
    SELECT DISTINCT contractor_company
    FROM employees
    WHERE is_active = 1
    ORDER BY contractor_company
");
?>

<div class="employees-admin-container">
    <!-- Page Header -->
    <<div class="page-header-emp-admin">
    <div class="header-left">
        <h2><i class="fas fa-user-clock"></i>Employee Status</h2>
        <p>Manage Active and Resigned Employees</p>
    </div>
</div>

    <?php if (!empty($filter)): ?>
    <div class="alert alert-info alert-custom-emp">
        <i class="fas fa-filter"></i>
        <div>
            <strong data-lang="active-filter">Active Filter:</strong>
            <p><span data-lang="displaying-employees-status">Displaying employees with status:</span> <strong>
                <?php
                $filter_labels = [
                    'active' => 'Active',
                    'resign' => 'Resigned'
                ];
                echo $filter_labels[$filter] ?? $filter;
                ?>
            </strong></p>
        </div>
        <a href="employees_status.php" class="btn btn-sm btn-secondary" style="margin-left: auto;">
            <i class="fas fa-times"></i> <span data-lang="remove-filter">Remove Filter</span>
        </a>
    </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-emp">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-emp">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards - Overall -->
    <div class="stats-section-title">
        <h4><span data-lang="overall-statistics">Overall Statistics</span></h4>
    </div>
    <div class="stats-grid-emp">
        <div class="stat-box-emp stat-active">
            <div class="stat-icon-emp">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?= $active_count ?></div>
                <div class="stat-text">Active</div>
            </div>
        </div>

        <div class="stat-box-emp stat-resigned">
            <div class="stat-icon-emp">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?= $resigned_count ?></div>
                <div class="stat-text">Resigned</div>
            </div>
            </div>
                </div>

            </div>
    
    <!-- Employees Table -->
    <div class="card-emp">
        <div class="card-header-emp">
            <h3><i class="fas fa-list"></i> <span data-lang="complete-workforce-list">Complete Workforce List</span></h3>
        </div>

        <div class="card-body-emp">
            <?php if ($employees->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table-emp" id="employeesTable">
                        <thead>
                            <tr>
                                <th class="col-code" data-lang="id-badge">ID BADGE</th>
                                <th class="col-name" data-lang="name">Name</th>
                                <th class="col-position no-required-marker" data-lang="position">Position</th>
                                <th class="col-company no-required-marker" data-lang="company">Company</th>
                                <th class="col-competency-type no-required-marker" data-lang="competency-type">Competency Type</th>
                                <th class="col-competency no-required-marker" data-lang="competency">Competency</th>
                                <th class="col-status" data-lang="status">Appointment No</th>
                                <th class="col-verified-by" data-lang="verified-by">Employee Status</th>
                                <th class="col-action" data-lang="action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $employees->data_seek(0);
                            while ($row = $employees->fetch_assoc()): 
                                $company_name = htmlspecialchars($row['contractor_company']);
                            ?>
                            <tr class="emp-row" data-company="<?php echo $company_name; ?>" data-status="<?php echo htmlspecialchars($row['verification_status']); ?>">
                                <td class="col-code">
                                    <span class="code-badge"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                                </td>
                                <td class="col-name">
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                </td>
                                <td class="col-position">
                                    <span class="position-tag-emp"><?php echo htmlspecialchars($row['position']); ?></span>
                                </td>
                                <td class="col-company">
                                    <span class="company-tag-emp"><?php echo $company_name; ?></span>
                                </td>
                                <td class="col-competency-type">
                                    <span class="competency-type-badge competency-<?php echo $row['competency_type']; ?>">
                                        <?php echo htmlspecialchars($row['competency_type_display'] ?? ''); ?>
                                    </span>
                                </td>
                                <td class="col-competency">
                                    <?php if (!empty($row['competency_name'])): ?>
                                        <span class="competency-tag"><?php echo htmlspecialchars($row['competency_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if($row['employee_status']=="active"){
                                        echo '<span class="badge-status badge-success">ACTIVE</span>';
                                    }else{
                                        echo '<span class="badge-status badge-danger">RESIGNED</span>';
                                         }
                                    ?>
                                </td>
                                <td class="col-verified-by">
                                    <?php 
                                    if (($row['verification_status'] == 'verified' || $row['verification_status'] == 'rejected') && $row['verified_by_name']) {
                                        echo '<strong>' . htmlspecialchars($row['verified_by_name']) . '</strong>';
                                        if ($row['verified_date']) {
                                            echo '<br><small class="text-muted">' . date('d/m/Y', strtotime($row['verified_date'])) . '</small>';
                                        }
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons-emp">
                                    <button class="btn-action-emp edit-status-btn" data-id="<?= $row['id']?>" data-status="<?= $row['employee_status']?>" title="Change Status">
                                    <i class="fas fa-user-edit"></i>
                                    </button>
                                    </div>
                                </td>
                              </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-emp">
                    <i class="fas fa-inbox"></i>
                    <p data-lang="no-workforce-data">No workforce data yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<style>
.employees-admin-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-emp-admin {
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

.btn-lg-emp {
    padding: 12px 25px;
    font-size: 15px;
    white-space: nowrap;
    background: #37474F;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-lg-emp:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
}

/* Alert Custom */
.alert-custom-emp {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success.alert-custom-emp {
    background: #E8F5E9;
    border-left-color: #2E7D32;
}

.alert-success.alert-custom-emp i {
    color: #2E7D32;
    font-size: 20px;
}

.alert-error.alert-custom-emp {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.alert-error.alert-custom-emp i {
    color: #ef4444;
    font-size: 20px;
}

.alert-info.alert-custom-emp {
    background: #ECEFF1;
    border-left-color: #37474F;
}

.alert-info.alert-custom-emp i {
    color: #37474F;
    font-size: 20px;
}

.alert-custom-emp strong {
    display: block;
    margin-bottom: 5px;
}

/* Stats Grid */
.stats-grid-emp {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-box-emp {
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

.stat-box-emp:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
}

.stat-total { border-left-color: #37474F; }
.stat-pending { border-left-color: #f59e0b; }
.stat-verified { border-left-color: #2E7D32; }
.stat-rejected { border-left-color: #ef4444; }

.stat-icon-emp {
    font-size: 28px;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: white;
}


/* Selaraskan warna ikon statistik dengan dashboard dan appointments */
.stat-total .stat-icon-emp {
    background: #37474F;
    color: #fff;
}
.stat-pending .stat-icon-emp {
    background: linear-gradient(135deg, #FFD600, #FFB300); /* Kuning terang */
    color: #F57C00;
}
.stat-verified .stat-icon-emp {
    background: linear-gradient(135deg, #F57C00, #FF9800); /* Orange utama */
    color: #fff;
}
.stat-rejected .stat-icon-emp {
    background: linear-gradient(135deg, #EF5350, #D32F2F); /* Merah */
    color: #fff;
}
.stat-needs-review .stat-icon-emp {
    background: linear-gradient(135deg, #2196F3, #1976D2); /* Biru */
    color: #fff;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #333;
}

.stat-text {
    color: #666;
    font-size: 12px;
}

/* Card */
.card-emp {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.card-header-emp {
    padding: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header-emp h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header-emp i {
    color: #37474F;
}

.card-body-emp {
    padding: 0;
}

/* Table */
.table-emp {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table-emp thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 15px;
}

.emp-row {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.emp-row:hover {
    background-color: #f8f9ff;
}

.table-emp td {
    padding: 15px;
    vertical-align: middle;
    font-size: 13px;
}

.col-code { width: 10%; }
.col-name { width: 15%; }
.col-position { width: 12%; }
.col-company { width: 14%; }
.col-competency-type { width: 13%; }
.col-competency { width: 15%; }
.col-status { width: 9%; }
.col-verified-by { width: 10%; }
.col-action { width: 7%; }

.code-badge {
    background: #ECEFF1;
    color: #37474F;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.position-tag-emp {
    background: #f3f4f6;
    color: #666;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
}

.company-tag-emp {
    background: #fef3c7;
    color: #b45309;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    display: inline-block;
}

.cert-count-emp {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.table-emp thead th.col-position::after,
.table-emp thead th.col-company::after,
.table-emp thead th.col-competency-type::after,
.table-emp thead th.col-competency::after {
    content: none !important;
}
.cert-badge {
    font-weight: 600;
    color: #333;
    font-size: 12px;
}

.cert-progress {
    width: 80px;
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}

.cert-fill {
    height: 100%;
    background: linear-gradient(90deg, #37474F, #37474F);
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

.action-buttons-emp {
    display: flex;
    gap: 6px;
}

.btn-action-emp {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    text-decoration: none;
    color: white;
}

.verify-btn {
    background: #2E7D32;
}

.verify-btn:hover {
    background: #1B5E20;
    transform: translateY(-1px);
}

.open-btn {
    background: #37474F;
}

.open-btn:hover {
    background: #263238;
    transform: translateY(-1px);
}

.resubmit-btn {
    background: #f59e0b;
}

.resubmit-btn:hover {
    background: #d97706;
    transform: translateY(-1px);
}

/* Rejected Alert Banner */
.alert-resubmit-emp {
    background: #fef3c7 !important;
    border-left-color: #f59e0b !important;
}

.alert-resubmit-emp i {
    color: #f59e0b !important;
    font-size: 24px !important;
}

.cv-btn {
    background: #37474F;
}

.cv-btn:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

/* Empty State */
.empty-state-emp {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-emp i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state-emp p {
    margin: 0;
    font-size: 16px;
}


.section-title-modal {
    color: #37474F;
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
    font-size: 15px;
    font-weight: 600;
}

.file-input-modal {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-text {
    display: block;
    color: #666;
    font-size: 13px;
}

.file-name {
    display: none;
    color: #2E7D32;
    font-weight: 600;
    font-size: 12px;
    margin-top: 10px;
}
/* Filter Section */
.filter-section-emp {
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.filter-group-emp {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 280px;
}

.filter-group-emp label {
    font-weight: 600;
    color: #333;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    font-size: 13px;
}

.filter-select-emp {
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: border-color 0.3s ease;
    min-width: 200px;
    flex: 1;
}

.filter-select-emp:hover,
.filter-select-emp:focus {
    border-color: #37474F;
    outline: none;
}

.table-info-emp {
    padding: 12px 20px;
    background: #ECEFF1;
    color: #37474F;
    border-top: 1px solid #b3e5fc;
    font-size: 12px;
    font-weight: 500;
}

/* Statistics Section */
.stats-section-title {
    margin-top: 30px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #37474F;
}

.stats-section-title h4 {
    margin: 0;
    color: #333;
    font-size: 16px;
    font-weight: 600;
}

.competency-type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.competency-pengawas_operasional {
    background: #ECEFF1;
    color: #37474F;
}

.competency-pengawas_teknis {
    background: #fef3c7;
    color: #b45309;
}

.competency-tenaga_teknis {
    background: #E8F5E9;
    color: #1B5E20;
}

.competency-tag {
    background: #f3f4f6;
    color: #374151;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.badge-status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

/* Certification Item Styles */
.cert-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.cert-item-header h5 {
    margin: 0;
    color: #333;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}

.cert-item-header i {
    color: #37474F;
}

.cert-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-remove-cert {
    background: #fee2e2;
    color: #dc2626;
    border: 2px solid #fecaca;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-remove-cert:hover {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
    transform: scale(1.05);
}

.btn-remove-cert i {
    color: inherit;
    font-size: 14px;
}

.badge-info {
    background: #dbeafe;
    color: #1d4ed8;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

/* Validity Input Group */
.validity-input-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.validity-input-group input[type="number"] {
    flex: 1;
}

/* Checkbox Label */

/* Other Type Input */
.other-type-input {
    display: none;
}

/* Other Expiry Reason */
.other-expiry-reason {
    display: none;
}

.other-expiry-reason textarea {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    resize: vertical;
}

.other-expiry-reason textarea:focus {
    outline: none;
    border-color: #37474F;
}

/* Form Hint */

.text-muted {
    color: #999 !important;
}

/* Readonly styling */
input[readonly] {
    background-color: #F9FAFB !important;
    cursor: not-allowed !important;
}

/* Responsive Styles */
@media (max-width: 1200px) {
    .stats-grid-emp {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .col-competency, .col-verified-by {
        display: none;
    }
    
    .table-emp thead th.col-competency,
    .table-emp thead th.col-verified-by {
        display: none;
    }
}

@media (max-width: 992px) {
    .page-header-emp-admin {
        flex-direction: column;
        gap: 20px;
        text-align: center;
        padding: 25px 20px;
    }
    
    .header-left h2 {
        font-size: 22px;
        justify-content: center;
    }
    
    .btn-lg-emp {
        width: 100%;
    }

    .col-competency-type {
        display: none;
    }
    
    .table-emp thead th.col-competency-type {
        display: none;
    }
}

@media (max-width: 768px) {
    .employees-admin-container {
        padding: 15px 0;
    }
    
    .page-header-emp-admin {
        padding: 20px 15px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    
    .header-left h2 {
        font-size: 18px;
    }
    
    .header-left p {
        font-size: 13px;
    }
    
    .stats-grid-emp {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    
    .stat-box-emp {
        padding: 15px;
        gap: 10px;
    }
    
    .stat-icon-emp {
        width: 38px;
        height: 38px;
        font-size: 18px;
        flex-shrink: 0;
    }
    
    .stat-number {
        font-size: 18px;
    }
    
    .stat-text {
        font-size: 11px;
    }
    
    .card-header-emp {
        padding: 15px;
    }
    
    .card-header-emp h3 {
        font-size: 16px;
    }
    
    .filter-section-emp {
        padding: 15px;
        flex-direction: column;
        gap: 15px;
    }
    
    .filter-group-emp {
        flex-direction: column;
        align-items: stretch;
        max-width: 100%;
        min-width: auto;
    }
    
    .filter-select-emp {
        width: 100%;
        min-width: auto;
    }
    
    .table-emp thead th,
    .table-emp td {
        padding: 10px 8px;
        font-size: 11px;
    }
    
    .col-position, .col-company {
        display: none;
    }
    
    .table-emp thead th.col-position,
    .table-emp thead th.col-company {
        display: none;
    }
    
    .code-badge {
        padding: 3px 6px;
        font-size: 10px;
    }
    
    .badge-status {
        padding: 4px 8px;
        font-size: 10px;
    }
    
    .btn-action-emp {
        width: 28px;
        height: 28px;
        font-size: 11px;
    }
    
    .action-buttons-emp {
        gap: 4px;
    }
    
    .btn-action-emp {
        width: 40px;
        height: 40px;
        font-size: 14px;
    }
    
    .section-title-modal {
        font-size: 14px;
    }
    
    .file-text {
        font-size: 12px;
    }
    
    .validity-input-group {
        flex-direction: column;
        align-items: stretch;
    }

    
    .table-info-emp {
        font-size: 11px;
        padding: 10px 15px;
    }
    
    .stats-section-title h4 {
        font-size: 14px;
    }
}

@media (max-width: 576px) {
    .employees-admin-container {
        padding: 10px 0;
    }
    
    .page-header-emp-admin {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .header-left h2 {
        font-size: 16px;
        flex-wrap: wrap;
    }
    
    .stats-grid-emp {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .stat-box-emp {
        padding: 12px;
        flex-direction: row;
    }
    
    .stat-icon-emp {
        width: 38px;
        height: 38px;
        font-size: 18px;
        flex-shrink: 0;
    }
    
    .stat-number {
        font-size: 18px;
    }
    
    .card-emp {
        margin: 0 -10px;
        border-radius: 0;
    }
    
    .card-header-emp {
        padding: 12px 15px;
    }
    
    .card-header-emp h3 {
        font-size: 14px;
    }
    
    .table-emp thead th,
    .table-emp td {
        padding: 8px 6px;
        font-size: 10px;
    }
    
    .col-status {
        display: none;
    }
    
    .table-emp thead th.col-status {
        display: none;
    }
    
    .emp-row td.col-name strong {
        font-size: 12px;
    }
    
    .btn-action-emp {
        width: 28px;
        height: 28px;
        font-size: 11px;
    }
    
    .cert-item-header h5 {
        font-size: 13px;
    }

    
    .empty-state-emp {
        padding: 40px 15px;
    }
    
    .empty-state-emp i {
        font-size: 36px;
    }
    
    .empty-state-emp p {
        font-size: 14px;
    }
    
    .alert-custom-emp {
        padding: 15px;
        flex-direction: column;
        text-align: center;
    }
    
    .alert-custom-emp i {
        font-size: 24px;
    }
}

/* Table Responsive - Convert to cards on very small screens */
@media (max-width: 480px) {
    .table-responsive {
        overflow-x: visible;
    }
    
    .table-emp {
        display: block;
    }
    
    .table-emp thead {
        display: none;
    }
    
    .table-emp tbody {
        display: block;
    }
    
    .emp-row {
        display: block;
        margin-bottom: 15px;
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .emp-row td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .emp-row td:last-child {
        border-bottom: none;
        justify-content: center;
        padding-top: 12px;
    }
    
    .emp-row td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #666;
        font-size: 11px;
        text-transform: uppercase;
    }
    
    .emp-row .col-code,
    .emp-row .col-name,
    .emp-row .col-action {
        display: flex;
    }
    
    .emp-row .col-position,
    .emp-row .col-company,
    .emp-row .col-competency-type,
    .emp-row .col-competency,
    .emp-row .col-status,
    .emp-row .col-verified-by {
        display: none;
    }
    
    .action-buttons-emp {
        justify-content: center;
        width: 100%;
    }
    
    .btn-action-emp {
        width: 40px;
        height: 40px;
        font-size: 14px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>