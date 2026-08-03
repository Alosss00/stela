<?php
/**
 * Master Reports View Hub - STELA-2
 * Shared modular template for Admin, User, and Department roles.
 */

$userRole = strtolower(trim((string)($_SESSION['role'] ?? 'user')));
$userCompany = trim((string)($_SESSION['contractor_company'] ?? ''));
$userDepartment = trim((string)($_SESSION['department'] ?? ''));
$page_title = 'STELA Reports Hub';

require_once dirname(__DIR__, 2) . '/app/Services/ReportService.php';
$db = new Database();

// Fetch company list for Admin dropdown filter
$companyList = [];
if ($userRole === 'admin') {
    $resComp = $db->query("SELECT DISTINCT contractor_company FROM employees WHERE contractor_company IS NOT NULL AND contractor_company != '' ORDER BY contractor_company ASC");
    if ($resComp) {
        while ($row = $resComp->fetch_assoc()) {
            $companyList[] = $row['contractor_company'];
        }
    }
}

// Fetch department list
$deptList = [];
$resDept = $db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
if ($resDept) {
    while ($row = $resDept->fetch_assoc()) {
        $deptList[] = $row['department'];
    }
}

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<style>
/* Custom Styles for STELA Reports Hub */
.reports-hub-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    padding: 24px;
    border-radius: 16px;
    margin-bottom: 24px;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15);
}
.reports-hub-header h2 {
    font-weight: 700;
    margin-bottom: 6px;
    color: #f8fafc;
}

.report-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    overflow: hidden;
    height: 100%;
}
.report-metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.08);
    border-color: #3b82f6;
}
.report-metric-card.active {
    border: 2px solid #2563eb;
    background: #f0f9ff;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.15);
}
.report-metric-card .metric-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 12px;
}
.report-metric-card .metric-count {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
    margin-bottom: 6px;
}
.report-metric-card .metric-title {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.report-toolbar-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.stela-custom-table th {
    background: #1e293b !important;
    color: #f8fafc !important;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 16px;
    border-bottom: none;
}
.stela-custom-table td {
    padding: 14px 16px;
    font-size: 14px;
    color: #334155;
}
</style>

<div class="container-fluid px-4 py-3">
    <!-- Header Banner -->
    <div class="reports-hub-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h2><i class="fas fa-chart-pie me-2 text-warning"></i>STELA Reports Center</h2>
            <p class="mb-0 text-slate-300">Pusat Laporan & Pemantauan Aktivitas Sistem STELA - Realtime Bonsai.io Search</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary px-3 py-2 fs-6"><i class="fas fa-user-shield me-1"></i> Role: <?php echo strtoupper($userRole); ?></span>
            <?php if (!empty($userCompany)): ?>
                <span class="badge bg-secondary px-3 py-2 fs-6"><i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($userCompany); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 6 Metric Cards Selection Hub -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="report-metric-card active" data-report="accepted_requests">
                <div class="metric-icon bg-success text-white"><i class="fas fa-user-check"></i></div>
                <div class="metric-count" id="count_accepted_requests">0</div>
                <div class="metric-title">Accepted Request</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="report-metric-card" data-report="rejected_requests">
                <div class="metric-icon bg-danger text-white"><i class="fas fa-user-times"></i></div>
                <div class="metric-count" id="count_rejected_requests">0</div>
                <div class="metric-title">Rejected Request</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="report-metric-card" data-report="waiting_requests">
                <div class="metric-icon bg-warning text-dark"><i class="fas fa-hourglass-half"></i></div>
                <div class="metric-count" id="count_waiting_requests">0</div>
                <div class="metric-title">Waiting Request</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="report-metric-card" data-report="accepted_assign_letters">
                <div class="metric-icon bg-info text-dark"><i class="fas fa-file-signature"></i></div>
                <div class="metric-count" id="count_accepted_assign_letters">0</div>
                <div class="metric-title">Accepted Assign Letter</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="report-metric-card" data-report="rejected_assign_letters">
                <div class="metric-icon bg-dark text-white"><i class="fas fa-file-excel"></i></div>
                <div class="metric-count" id="count_rejected_assign_letters">0</div>
                <div class="metric-title">Rejected Assign Letter</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="report-metric-card" data-report="expired_certificates">
                <div class="metric-icon bg-danger text-white"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="metric-count" id="count_expired_certificates">0</div>
                <div class="metric-title">Expired Certificates</div>
            </div>
        </div>
    </div>

    <!-- Active Report Toolbar & Filters -->
    <div class="report-toolbar-card">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div>
                <h4 id="activeReportTitle" class="fw-bold mb-1 text-slate-800">Detail Accepted Request (Verified by Admin)</h4>
                <span class="badge bg-light text-dark border" id="reportTotalBadge">0 Records</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button id="btnRefreshReport" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sync-alt me-1"></i> Refresh</button>
                <button id="btnExportExcel" class="btn btn-success btn-sm"><i class="fas fa-file-excel me-1"></i> Export Excel</button>
                <button id="btnExportPdf" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf me-1"></i> Export PDF</button>
            </div>
        </div>

        <div class="row g-2 align-items-center">
            <!-- Bonsai.io Realtime Search -->
            <div class="col-lg-4 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-primary"></i></span>
                    <input type="text" id="reportSearchInput" class="form-control" placeholder="Search via Bonsai.io (Name, ID, Company, Cert)...">
                </div>
            </div>

            <!-- Scope Filter (PT MSM / PT TTN) -->
            <div class="col-lg-2 col-md-3">
                <select id="reportScopeFilter" class="form-select">
                    <option value="">-- All Scopes --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>

            <!-- Company Filter (For Admin Role) -->
            <?php if ($userRole === 'admin'): ?>
            <div class="col-lg-3 col-md-3">
                <select id="reportCompanyFilter" class="form-select">
                    <option value="">-- All Companies --</option>
                    <?php foreach ($companyList as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Department Filter -->
            <div class="col-lg-2 col-md-3">
                <select id="reportDepartmentFilter" class="form-select">
                    <option value="">-- All Departments --</option>
                    <?php foreach ($deptList as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Items Per Page -->
            <div class="col-lg-1 col-md-3 ms-auto">
                <select id="reportPerPageSelect" class="form-select">
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Table Render Area -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-body p-0 position-relative">
            <div id="reportLoadingSpinner" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted small">Fetching data from Bonsai.io & MySQL...</p>
            </div>
            <div id="reportTableContainer">
                <!-- Dynamically rendered table via JS -->
            </div>
        </div>
        <div class="card-footer bg-white py-3 border-top-0 d-flex align-items-center justify-content-between" id="reportPaginationContainer">
            <!-- Pagination Controls -->
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/reports-manager.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.stelaReportManager = new StelaReportManager({
        apiEndpoint: '<?php echo BASE_URL; ?>/resources/views/api/reports_data.php',
        userRole: '<?php echo $userRole; ?>'
    });
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
