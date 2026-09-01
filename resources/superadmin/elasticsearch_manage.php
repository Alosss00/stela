<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Elasticsearch Management';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';

checkPageAccess(['superadmin'], 'manage_elasticsearch');

$es = ElasticsearchService::getInstance();
$db = new Database();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        die("CSRF Token Invalid. Silakan muat ulang halaman.");
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'setup_indices') {
            $res = $es->setupIndices();
            if ($res) {
                $message = "Indices setup successfully.";
            } else {
                $error = "Failed to setup indices or service unavailable.";
            }
        } elseif ($_POST['action'] === 'sync_employees') {
            $res = $es->bulkIndexEmployees($db->getConnection());
            if ($res && $res['success']) {
                $message = "Successfully synced " . $res['count'] . " employees to Elasticsearch.";
            } else {
                $error = "Failed to sync employees: " . ($res['message'] ?? 'Unknown error');
            }
        } elseif ($_POST['action'] === 'sync_appointments') {
            $res = $es->bulkIndexAppointments($db->getConnection());
            if ($res && $res['success']) {
                $message = "Successfully synced " . $res['count'] . " appointments to Elasticsearch.";
            } else {
                $error = "Failed to sync appointments: " . ($res['message'] ?? 'Unknown error');
            }
        }
    }
}

$ping = $es->ping();
$isEnabled = $es->isAvailable();

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">Elasticsearch Management</h2>
            <p class="text-muted mb-0">Manage search engine cluster, indices, and data synchronization.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Cluster Status -->
        <div class="col-md-5 mb-4">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-server me-2 text-primary"></i> Cluster Status</h5>
                </div>
                <div class="card-body">
                    <?php if ($isEnabled && $ping['status'] === 'online'): ?>
                        <div class="d-flex align-items-center mb-4">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                <i class="fas fa-check text-success fs-4"></i>
                            </div>
                            <div>
                                <h4 class="mb-0 fw-bold text-success">Online</h4>
                                <span class="text-muted small">Connected to Cluster</span>
                            </div>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span class="text-muted">Cluster Name</span>
                                <span class="fw-bold"><?= htmlspecialchars($ping['cluster_name'] ?? 'N/A') ?></span>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span class="text-muted">Health</span>
                                <span>
                                    <?php
                                        $hStatus = $ping['health'] ?? 'unknown';
                                        $badgeColor = 'secondary';
                                        if ($hStatus === 'green') $badgeColor = 'success';
                                        if ($hStatus === 'yellow') $badgeColor = 'warning';
                                        if ($hStatus === 'red') $badgeColor = 'danger';
                                    ?>
                                    <span class="badge bg-<?= htmlspecialchars($badgeColor ?? "", ENT_QUOTES, "UTF-8") ?> text-uppercase"><?= htmlspecialchars($hStatus) ?></span>
                                </span>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span class="text-muted">Active Nodes</span>
                                <span class="fw-bold"><?= htmlspecialchars($ping['number_of_nodes'] ?? '0') ?></span>
                            </li>
                        </ul>
                    <?php else: ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                                <i class="fas fa-times text-danger fs-4"></i>
                            </div>
                            <div>
                                <h4 class="mb-0 fw-bold text-danger">Offline</h4>
                                <span class="text-muted small">Cannot reach Elasticsearch</span>
                            </div>
                        </div>
                        <p class="text-danger small mb-0"><?= htmlspecialchars($ping['message'] ?? 'Service is disabled or misconfigured in config/app.php') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Data Synchronization -->
        <div class="col-md-7 mb-4">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-sync-alt me-2 text-primary"></i> Data Synchronization</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Sync your MySQL database records to Elasticsearch to enable lightning-fast full-text search across the application.</p>
                    
                    <div class="d-grid gap-3">
                        <form method="POST" onsubmit="return confirm('Setup and update index mappings? This is safe to run anytime.');">
    <?= csrf_field() ?>
                            <input type="hidden" name="action" value="setup_indices">
                            <button type="submit" class="btn btn-outline-primary w-100 text-start d-flex align-items-center justify-content-between p-3" <?= !$isEnabled ? 'disabled' : '' ?>>
                                <div>
                                    <h6 class="mb-1 fw-bold"><i class="fas fa-tools me-2"></i> Setup Indices</h6>
                                    <span class="small text-muted fw-normal">Create missing indices and update mappings/analyzers.</span>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </form>

                        <form method="POST" onsubmit="return confirm('This will bulk index ALL employee records to Elasticsearch. Proceed?');">
    <?= csrf_field() ?>
                            <input type="hidden" name="action" value="sync_employees">
                            <button type="submit" class="btn btn-outline-success w-100 text-start d-flex align-items-center justify-content-between p-3" <?= !$isEnabled ? 'disabled' : '' ?>>
                                <div>
                                    <h6 class="mb-1 fw-bold"><i class="fas fa-users me-2"></i> Sync Employees</h6>
                                    <span class="small text-muted fw-normal">Push all employee records from MySQL to Elasticsearch.</span>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </form>

                        <form method="POST" onsubmit="return confirm('This will bulk index ALL appointment records to Elasticsearch. Proceed?');">
    <?= csrf_field() ?>
                            <input type="hidden" name="action" value="sync_appointments">
                            <button type="submit" class="btn btn-outline-info w-100 text-start d-flex align-items-center justify-content-between p-3" <?= !$isEnabled ? 'disabled' : '' ?>>
                                <div>
                                    <h6 class="mb-1 fw-bold"><i class="fas fa-file-signature me-2"></i> Sync Appointments</h6>
                                    <span class="small text-muted fw-normal">Push all appointment records from MySQL to Elasticsearch.</span>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/superadmin_footer.php'; ?>
