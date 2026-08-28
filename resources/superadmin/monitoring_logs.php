<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'System Logs';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';

requirePermission('view_logs');
require_once dirname(__DIR__) . '/layouts/superadmin_header.php';

$db = new Database();
$res = $db->query("SELECT * FROM notification_logs ORDER BY sent_at DESC LIMIT 100");
$logs = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
}
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-1 fw-bold" style="color: #1e293b;"><i class="fas fa-history text-primary me-2"></i> System Logs</h2>
            <p class="text-muted mb-0">View recent system notifications and audit logs</p>
        </div>
        <div class="col-md-6 text-end">
            <div class="export-actions">
                <?php 
                $exportQuery = $_GET;
                $exportQuery['type'] = 'logs';
                $baseQuery = http_build_query($exportQuery);
                ?>
                <a href="export_monitoring.php?<?php echo $baseQuery; ?>&format=pdf" target="_blank" class="btn btn-sm btn-outline-danger me-1">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <a href="export_monitoring.php?<?php echo $baseQuery; ?>&format=excel" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th class="px-4 py-3 border-0">Timestamp</th>
                            <th class="py-3 border-0">Type</th>
                            <th class="py-3 border-0">Company</th>
                            <th class="py-3 border-0 text-end pe-4">Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">No logs found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($logs as $log): ?>
                                <tr>
                                    <td class="px-4 fw-semibold text-dark"><?php echo $log['sent_at'] ? date('d M Y H:i:s', strtotime($log['sent_at'])) : '-'; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['notification_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($log['company_name'] ?? '-'); ?></td>
                                    <td class="text-end pe-4 text-muted"><?php echo htmlspecialchars($log['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/layouts/superadmin_footer.php'; ?>
