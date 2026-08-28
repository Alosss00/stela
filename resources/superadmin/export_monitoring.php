<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/MonitoringHelper.php';

// Only Superadmin
if (!isSuperadmin()) { die('Unauthorized'); }

$db = new Database();
$helper = new MonitoringHelper($db);

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'excel';

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filters = [];

$data = [];
$title = "Monitoring Report";

switch ($type) {
    case 'employees':
        $filters['company'] = $_GET['company'] ?? '';
        $filters['department'] = $_GET['department'] ?? '';
        $filters['status'] = $_GET['status'] ?? '';
        
        $res = $helper->getEmployees(1, 10000, $search, $filters, 'created_at', 'DESC');
        $data = $res['data'];
        $title = "Employee Monitoring Report";
        break;
        
    case 'appointments':
        $filters['company'] = $_GET['company'] ?? '';
        $filters['status'] = $_GET['status'] ?? '';
        
        $res = $helper->getAppointments(1, 10000, $search, $filters, 'created_at', 'DESC');
        $data = $res['data'];
        $title = "Appointment Monitoring Report";
        break;
        
    case 'certificates':
        $filters['cert_status'] = $_GET['cert_status'] ?? '';
        
        $res = $helper->getCertificates(1, 10000, $search, $filters, 'expiry_date', 'ASC');
        $data = $res['data'];
        $title = "Certificate Monitoring Report";
        break;
        
    case 'logs':
        $sql = "SELECT * FROM notification_logs ";
        if (!empty($search)) {
            $sql .= " WHERE company_name LIKE '%" . $db->escapeString($search) . "%' OR message LIKE '%" . $db->escapeString($search) . "%' ";
        }
        $sql .= " ORDER BY sent_at DESC LIMIT 10000";
        $res = $db->query($sql);
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
        }
        $title = "System Logs Report";
        break;
        
    default:
        die("Invalid monitoring type.");
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
    
    <table>
        <thead>
            <?php if ($type == 'employees'): ?>
                <tr>
                    <th>No</th>
                    <th>Employee Name</th>
                    <th>Badge ID</th>
                    <th>Company</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Date Requested</th>
                    <th>Status</th>
                </tr>
            <?php elseif ($type == 'appointments'): ?>
                <tr>
                    <th>No</th>
                    <th>Appointment No</th>
                    <th>Employee Name</th>
                    <th>Badge ID</th>
                    <th>Company</th>
                    <th>Department</th>
                    <th>Date Created</th>
                    <th>Status</th>
                </tr>
            <?php elseif ($type == 'certificates'): ?>
                <tr>
                    <th>No</th>
                    <th>Certificate Type</th>
                    <th>Certificate No</th>
                    <th>Employee Name</th>
                    <th>Badge ID</th>
                    <th>Company</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                </tr>
            <?php elseif ($type == 'logs'): ?>
                <tr>
                    <th>No</th>
                    <th>Timestamp</th>
                    <th>Notification Type</th>
                    <th>Company</th>
                    <th>Recipient Email</th>
                    <th>Recipient Name</th>
                    <th>Status</th>
                    <th>Error</th>
                </tr>
            <?php endif; ?>
        </thead>
        <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="10" style="text-align:center;">No records found.</td></tr>
            <?php else: ?>
                <?php $i=1; foreach($data as $row): ?>
                    <?php if ($type == 'employees'): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['full_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['employee_code'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['contractor_company'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['department'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['position'] ?? '-') ?></td>
                            <td><?= isset($row['created_at']) ? date('d M Y H:i', strtotime($row['created_at'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['verification_status'] ?? ($row['approval_status'] ?? 'pending')) ?></td>
                        </tr>
                    <?php elseif ($type == 'appointments'): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['appointment_number'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['employee_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['employee_code'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['company'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['department'] ?? '-') ?></td>
                            <td><?= isset($row['created_at']) ? date('d M Y H:i', strtotime($row['created_at'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['status'] ?? '-') ?></td>
                        </tr>
                    <?php elseif ($type == 'certificates'): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['master_cert_name'] ?: 'Custom/Other') ?></td>
                            <td><?= htmlspecialchars($row['cert_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['employee_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['employee_code'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['company'] ?? '-') ?></td>
                            <td><?= empty($row['expiry_date']) || $row['expiry_date'] == '0000-00-00' ? 'Lifetime / None' : date('d M Y', strtotime($row['expiry_date'])) ?></td>
                            <td><?= htmlspecialchars($row['monitoring_status'] ?? '-') ?></td>
                        </tr>
                    <?php elseif ($type == 'logs'): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= $row['sent_at'] ? date('d M Y H:i:s', strtotime($row['sent_at'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['notification_type'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['company_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['recipient_email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['recipient_name'] ?? '-') ?></td>
                            <td><?= ($row['is_sent'] ?? 0) == 1 ? 'Sent' : 'Failed' ?></td>
                            <td><?= htmlspecialchars($row['error_message'] ?? '-') ?></td>
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
