<?php
// resources/views/superadmin/reports_export.php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/ReportsHelper.php';

requirePermission('reports.view');
if (!isSuperadmin()) { die('Unauthorized'); }

$db = new Database();
$helper = new ReportsHelper($db);

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'excel';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filters = [
    'company' => $_GET['company'] ?? '',
    'department' => $_GET['department'] ?? '',
    'position' => $_GET['position'] ?? '',
    'competency' => $_GET['competency'] ?? '',
    'date_start' => $_GET['date_start'] ?? '',
    'date_end' => $_GET['date_end'] ?? '',
    'cert_status' => $_GET['cert_status'] ?? 'all_expired'
];

$sort = $_GET['sort'] ?? '';
$order = $_GET['order'] ?? '';

$data = [];
$title = "Report";
switch ($type) {
    case 'accepted':
        $sort = $sort ?: 'verified_date';
        $order = $order ?: 'DESC';
        $res = $helper->getAcceptedRequests(1, 10, $search, $filters, $sort, $order, true);
        $data = $res['data'];
        $title = "Accepted Requests Report";
        break;
    case 'rejected':
        $sort = $sort ?: 'verified_date';
        $order = $order ?: 'DESC';
        $res = $helper->getRejectedRequests(1, 10, $search, $filters, $sort, $order, true);
        $data = $res['data'];
        $title = "Rejected Requests Report";
        break;
    case 'waiting':
        $sort = $sort ?: 'created_at';
        $order = $order ?: 'ASC';
        $res = $helper->getWaitingRequests(1, 10, $search, $filters, $sort, $order, true);
        $data = $res['data'];
        $title = "Waiting Requests Report";
        break;
    case 'approved_assign':
        $sort = $sort ?: 'approved_date';
        $order = $order ?: 'DESC';
        $res = $helper->getApprovedAppointments(1, 10, $search, $filters, $sort, $order, true);
        $data = $res['data'];
        $title = "Approved Assign Letters Report";
        break;
    case 'rejected_assign':
        $sort = $sort ?: 'last_rejection_date';
        $order = $order ?: 'DESC';
        $res = $helper->getRejectedAppointments(1, 10, $search, $filters, $sort, $order, true);
        $data = $res['data'];
        $title = "Rejected Assign Letters Report";
        break;
    case 'cert_expired':
        $sort = $sort ?: 'expiry_date';
        $order = $order ?: 'ASC';
        $res = $helper->getExpiredCertificates(1, 10, $search, $filters, $sort, $order, true);
        $data = $res['data'];
        $title = "Expired Certificates Report";
        break;
    default:
        die("Invalid report type.");
}

$filename = str_replace(' ', '_', $title) . "_" . date('Ymd_His');

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $title ?></title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        h2 { text-align: center; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #dddddd; text-align: left; padding: 6px; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2><?= $title ?></h2>
    <p><strong>Export Date:</strong> <?= date('d M Y H:i:s') ?></p>
    <?php if (!empty($search)) echo "<p><strong>Search:</strong> " . htmlspecialchars($search) . "</p>"; ?>
    <?php if (!empty($filters['company'])) echo "<p><strong>Company:</strong> " . htmlspecialchars($filters['company']) . "</p>"; ?>
    <?php if (!empty($filters['date_start'])) echo "<p><strong>Date Filter:</strong> " . $filters['date_start'] . " to " . $filters['date_end'] . "</p>"; ?>
    
    <table>
        <thead>
            <?php if ($type == 'accepted'): ?>
                <tr><th>No</th><th>Badge ID</th><th>Employee Name</th><th>Company</th><th>Dept</th><th>Position</th><th>Verified Date</th><th>Verified By</th></tr>
            <?php elseif ($type == 'rejected'): ?>
                <tr><th>No</th><th>Badge ID</th><th>Employee Name</th><th>Company</th><th>Dept</th><th>Position</th><th>Rejected Date</th><th>Rejected By</th><th>Notes</th></tr>
            <?php elseif ($type == 'waiting'): ?>
                <tr><th>No</th><th>Badge ID</th><th>Employee Name</th><th>Company</th><th>Dept</th><th>Position</th><th>Request Date</th></tr>
            <?php elseif ($type == 'approved_assign'): ?>
                <tr><th>No</th><th>Appointment No</th><th>Employee Name</th><th>Company</th><th>Dept</th><th>Position</th><th>Competency</th><th>Approved Date</th><th>KTT Name</th></tr>
            <?php elseif ($type == 'rejected_assign'): ?>
                <tr><th>No</th><th>Appointment No</th><th>Employee Name</th><th>Company</th><th>Dept</th><th>Position</th><th>Competency</th><th>Rejected Date</th><th>Notes</th></tr>
            <?php elseif ($type == 'cert_expired'): ?>
                <tr><th>No</th><th>Employee Name</th><th>Company</th><th>Certificate</th><th>Cert No</th><th>Expiry Date</th><th>Remaining Days</th></tr>
            <?php endif; ?>
        </thead>
        <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="10" style="text-align:center;">No records found.</td></tr>
            <?php else: ?>
                <?php $i=1; foreach($data as $row): ?>
                    <?php if ($type == 'accepted'): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['employee_code'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['contractor_company'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['department'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['position'] ?? '-') ?></td>
                            <td><?= $row['verified_date'] ? date('Y-m-d H:i', strtotime($row['verified_date'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['verify_actor'] ?? 'System') ?></td>
                        </tr>
                    <?php elseif ($type == 'rejected'): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['employee_code'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['contractor_company'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['department'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['position'] ?? '-') ?></td>
                            <td><?= $row['verified_date'] ? date('Y-m-d H:i', strtotime($row['verified_date'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['verify_actor'] ?? 'System') ?></td>
                            <td><?= htmlspecialchars($row['verification_notes'] ?? '-') ?></td>
                        </tr>
                    <?php elseif ($type == 'waiting'): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['employee_code'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['contractor_company'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['department'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['position'] ?? '-') ?></td>
                            <td><?= $row['created_at'] ? date('Y-m-d H:i', strtotime($row['created_at'])) : '-' ?></td>
                        </tr>
                    <?php elseif ($type == 'approved_assign'): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['appointment_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['employee_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['company'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['department'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['emp_position'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['competency_type'] ?? '-') ?></td>
                            <td><?= $row['approved_date'] ? date('Y-m-d H:i', strtotime($row['approved_date'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['ktt_name'] ?? '-') ?></td>
                        </tr>
                    <?php elseif ($type == 'rejected_assign'): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['appointment_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['employee_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['company'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['department'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['emp_position'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['competency_type'] ?? '-') ?></td>
                            <td><?= $row['last_rejection_date'] ? date('Y-m-d H:i', strtotime($row['last_rejection_date'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['rejection_notes'] ?? '-') ?></td>
                        </tr>
                    <?php elseif ($type == 'cert_expired'): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['employee_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['company'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['master_cert_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['cert_number'] ?? '-') ?></td>
                            <td><?= $row['expiry_date'] ? date('Y-m-d', strtotime($row['expiry_date'])) : '-' ?></td>
                            <td><?= $row['remaining_days'] ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

if ($format === 'pdf') {
    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
        if (class_exists('\Mpdf\Mpdf')) {
            try {
                $mpdf = new \Mpdf\Mpdf(['orientation' => 'L', 'format' => 'A4']);
                $mpdf->WriteHTML($html);
                $mpdf->Output($filename . '.pdf', \Mpdf\Output\Destination::INLINE);
                exit;
            } catch (Exception $e) {
                // If MPDF fails, fallback to browser print view
            }
        }
    }
    
    // Fallback: Show HTML and auto print
    echo $html;
    echo "<script>window.onload = function() { window.print(); }</script>";
    exit;

} else {
    // Excel Download
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename.xls\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo $html;
    exit;
}
