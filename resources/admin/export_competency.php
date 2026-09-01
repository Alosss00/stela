<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';

requirePermission('admin.access');
requirePermission('reports.view');

$tab = $_GET['tab'] ?? 'ALL';
$db = new Database();

$sql = "
    SELECT 
        a.appointment_number as register_internal,
        e.competency_name as kompetensi,
        c.cert_name as jenis_sertifikat,
        c.issuing_authority as issuer,
        ec.cert_number as no_sertifikat,
        e.employee_code as no_id,
        e.full_name as nama_pemegang,
        e.position as jabatan,
        e.contractor_company as perusahaan,
        ec.issue_date as tanggal_terbit,
        ec.expiry_date as masa_berlaku,
        e.competency_type,
        e.supervision_area,
        e.ruang_lingkup
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN employee_certifications ec ON a.employee_certification_id = ec.id
    LEFT JOIN certifications c ON ec.certification_id = c.id
    WHERE a.status = 'approved' OR a.status = 'verified'
    ORDER BY CAST(SUBSTRING_INDEX(a.appointment_number, '/', 1) AS UNSIGNED) ASC, a.created_at DESC
";

$res = $db->query($sql);
$data = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $area = strtolower($row['supervision_area'] ?? '');
        $area_code = 'MSM';
        if (strpos($area, 'tondano') !== false || strpos($area, 'ttn') !== false) {
            $area_code = 'TTN';
        }
        
        $ctype = $row['competency_type'];
        $type_code = '';
        if ($ctype === 'tenaga_teknis') {
            $type_code = 'TT';
        } elseif ($ctype === 'pengawas_teknis') {
            $type_code = 'PT';
        } elseif ($ctype === 'pengawas_operasional') {
            $type_code = 'PO';
        } else {
            $appNum = strtoupper($row['register_internal']);
            if (strpos($appNum, '/TT/') !== false) $type_code = 'TT';
            elseif (strpos($appNum, '/PT/') !== false) $type_code = 'PT';
            elseif (strpos($appNum, '/PO/') !== false) $type_code = 'PO';
        }
        
        if ($type_code && ($tab === 'ALL' || "{$area_code}-{$type_code}" === $tab)) {
            // Add area and type to the row for "ALL" export
            $row['area_code'] = $area_code;
            $row['type_code'] = $type_code;
            $data[] = $row;
        }
    }
}

$tabLabels = [
    'MSM-TT' => 'Daftar Tenaga Teknis (MSM)',
    'MSM-PT' => 'Daftar Pengawas Teknis (MSM)',
    'MSM-PO' => 'Daftar Pengawas Operasional (MSM)',
    'TTN-TT' => 'Daftar Tenaga Teknis (TTN)',
    'TTN-PT' => 'Daftar Pengawas Teknis (TTN)',
    'TTN-PO' => 'Daftar Pengawas Operasional (TTN)'
];

$title = $tab === 'ALL' ? 'Daftar Seluruh Kompetensi' : ($tabLabels[$tab] ?? 'Daftar Kompetensi');
$filename = str_replace(' ', '_', $title) . "_" . date('Ymd');

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title ?? "", ENT_QUOTES, "UTF-8") ?></title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        h2 { text-align: center; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #dddddd; text-align: center; padding: 6px; }
        th { background-color: #f2f2f2; font-weight: bold; }
    </style>
</head>
<body>
    <h2><?= htmlspecialchars($title ?? "", ENT_QUOTES, "UTF-8") ?></h2>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Register Internal</th>
                <th>Kompetensi</th>
                <th>Jenis Sertifikat</th>
                <th>No Sertifikat</th>
                <th>NO ID</th>
                <th>Nama Pemegang</th>
                <th>Jabatan</th>
                <th>Perusahaan</th>
                <?php if ($tab === 'ALL'): ?>
                <th>Area Pengawasan</th>
                <th>Tipe Kompetensi</th>
                <?php endif; ?>
                <th>Tanggal Terbit</th>
                <th>Masa Berlaku</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="11" style="text-align:center;">No records found.</td></tr>
            <?php else: ?>
                <?php 
                $i=1; 
                foreach($data as $row): 
                    $jenis_sert = trim(($row['jenis_sertifikat'] ?? '') . ' ' . ($row['issuer'] ?? ''));
                    if (empty($jenis_sert)) $jenis_sert = '-';
                    
                    $masa_berlaku_str = '-';
                    if (!empty($row['tanggal_terbit']) && $row['tanggal_terbit'] != '0000-00-00' && !empty($row['masa_berlaku']) && $row['masa_berlaku'] != '0000-00-00') {
                        try {
                            $d1 = new DateTime($row['tanggal_terbit']);
                            $d2 = new DateTime($row['masa_berlaku']);
                            $diff = $d1->diff($d2);
                            if ($diff->y > 0) {
                                $masa_berlaku_str = $diff->y . ' tahun';
                            } else {
                                $masa_berlaku_str = $row['masa_berlaku'];
                            }
                        } catch (Exception $e) {
                            $masa_berlaku_str = $row['masa_berlaku'];
                        }
                    } elseif (!empty($row['masa_berlaku']) && $row['masa_berlaku'] != '0000-00-00') {
                         $masa_berlaku_str = $row['masa_berlaku'];
                    }
                ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['register_internal']) ?></td>
                        <td><?= htmlspecialchars($row['kompetensi'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($jenis_sert) ?></td>
                        <td><?= htmlspecialchars($row['no_sertifikat'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['no_id'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['nama_pemegang'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['jabatan'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['perusahaan'] ?: '-') ?></td>
                        <?php if ($tab === 'ALL'): ?>
                        <td><?= htmlspecialchars($row['area_code']) ?></td>
                        <td><?= htmlspecialchars($row['type_code'] === 'TT' ? 'Tenaga Teknis' : ($row['type_code'] === 'PT' ? 'Pengawas Teknis' : 'Pengawas Operasional')) ?></td>
                        <?php endif; ?>
                        <td><?= !empty($row['tanggal_terbit']) && $row['tanggal_terbit'] != '0000-00-00' ? date('d-M-Y', strtotime($row['tanggal_terbit'])) : '-' ?></td>
                        <td><?= htmlspecialchars($masa_berlaku_str) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename.xls\"");
header("Pragma: no-cache");
header("Expires: 0");
echo $html;
exit;
