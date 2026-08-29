<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Detail Kompetensi Reports';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';

requirePermission('admin.access');
requirePermission('reports.view');

// Handle header injection dynamically based on role
$is_superadmin = isSuperadmin();
if ($is_superadmin) {
    require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
} else {
    require_once dirname(__DIR__) . '/layouts/header.php';
}

$db = new Database();

// Query all approved appointments with their certificate and employee info
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

$reportData = [
    'MSM-TT' => [],
    'TTN-TT' => [],
    'MSM-PT' => [],
    'TTN-PT' => [],
    'MSM-PO' => [],
    'TTN-PO' => []
];

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
            // Fallback inference from appointment number
            $appNum = strtoupper($row['register_internal']);
            if (strpos($appNum, '/TT/') !== false) $type_code = 'TT';
            elseif (strpos($appNum, '/PT/') !== false) $type_code = 'PT';
            elseif (strpos($appNum, '/PO/') !== false) $type_code = 'PO';
        }
        
        if ($type_code) {
            $key = "{$area_code}-{$type_code}";
            if (isset($reportData[$key])) {
                $reportData[$key][] = $row;
            }
        }
    }
}

$tabLabels = [
    'MSM-TT' => 'Tenaga Teknis (MSM)',
    'MSM-PT' => 'Pengawas Teknis (MSM)',
    'MSM-PO' => 'Pengawas Operasional (MSM)',
    'TTN-TT' => 'Tenaga Teknis (TTN)',
    'TTN-PT' => 'Pengawas Teknis (TTN)',
    'TTN-PO' => 'Pengawas Operasional (TTN)'
];
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">
                <i class="fas fa-file-excel text-success me-2"></i> Detail Kompetensi
            </h2>
            <p class="text-muted mb-0">Laporan terperinci per area pengawasan dan tipe kompetensi (Format Spreadsheet)</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="d-flex gap-2 justify-content-end">
                <div class="dropdown">
                    <button class="btn btn-outline-success dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-file-excel"></i> Download per Tipe
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                        <?php foreach ($tabLabels as $key => $label): ?>
                        <li><a class="dropdown-item" href="export_competency.php?tab=<?php echo $key; ?>"><?php echo $label; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <a href="export_competency.php?tab=ALL" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Download Semua
                </a>
                <a href="<?php echo $is_superadmin ? '../superadmin/reports.php' : 'reports.php'; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
            <ul class="nav nav-tabs card-header-tabs" id="competencyTabs" role="tablist">
                <?php
                $first = true;
                foreach ($tabLabels as $key => $label):
                ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $first ? 'active' : ''; ?> fw-bold" 
                            id="tab-<?php echo $key; ?>" 
                            data-bs-toggle="tab" 
                            data-bs-target="#pane-<?php echo $key; ?>" 
                            type="button" role="tab">
                        <?php echo $label; ?>
                        <span class="badge bg-secondary rounded-pill ms-1"><?php echo count($reportData[$key]); ?></span>
                    </button>
                </li>
                <?php $first = false; endforeach; ?>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="competencyTabsContent">
                <?php 
                $first = true;
                foreach ($tabLabels as $key => $label): 
                ?>
                <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="pane-<?php echo $key; ?>" role="tabpanel">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 text-dark fw-bold"><?php echo $label; ?></h5>
                        <a href="export_competency.php?tab=<?php echo $key; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel"></i> Export to Excel
                        </a>
                    </div>
                    
                    <div class="table-responsive" style="max-height: 600px;">
                        <table class="table table-bordered table-hover table-striped align-middle" style="font-size: 0.85rem; white-space: nowrap;">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th class="text-center">No</th>
                                    <th>Register Internal</th>
                                    <th>Kompetensi</th>
                                    <th>Jenis Sertifikat</th>
                                    <th>No Sertifikat</th>
                                    <th>NO ID</th>
                                    <th>Nama Pemegang</th>
                                    <th>Jabatan</th>
                                    <th>Perusahaan</th>
                                    <th>Tanggal Terbit</th>
                                    <th>Masa Berlaku</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($reportData[$key])): ?>
                                <tr><td colspan="11" class="text-center py-4 text-muted">Belum ada data untuk kategori ini.</td></tr>
                                <?php else: ?>
                                    <?php 
                                    $no = 1;
                                    foreach($reportData[$key] as $row): 
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
                                        <td class="text-center"><?php echo $no++; ?></td>
                                        <td class="font-monospace"><?php echo htmlspecialchars($row['register_internal']); ?></td>
                                        <td><?php echo htmlspecialchars($row['kompetensi'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($jenis_sert); ?></td>
                                        <td><?php echo htmlspecialchars($row['no_sertifikat'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['no_id'] ?: '-'); ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['nama_pemegang'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['jabatan'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['perusahaan'] ?: '-'); ?></td>
                                        <td><?php echo !empty($row['tanggal_terbit']) && $row['tanggal_terbit'] != '0000-00-00' ? date('d-M-Y', strtotime($row['tanggal_terbit'])) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($masa_berlaku_str); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php $first = false; endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php 
if ($is_superadmin) {
    require_once dirname(__DIR__) . '/layouts/superadmin_footer.php';
} else {
    require_once dirname(__DIR__) . '/layouts/footer.php';
}
?>
