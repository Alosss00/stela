<?php
if (!isset($db)) {
    $db = new Database();
}

$sql_comp = "
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

$res_comp = $db->query($sql_comp);

$allCompData = [];
if ($res_comp) {
    while ($row = $res_comp->fetch_assoc()) {
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
        
        $row['area_code'] = $area_code;
        $row['type_code'] = $type_code;
        $allCompData[] = $row;
    }
}
?>

<div class="card-report" id="section-competency-details" style="margin-top: 30px;">
    <div class="card-header-report">
        <div class="card-hd-left">
            <h3><i class="fas fa-list-alt"></i> Detail Kompetensi</h3>
            <span class="badge-header"><?php echo count($allCompData); ?></span>
        </div>
        <button onclick="toggleSection('competencyDetailsSection')" class="btn-toggle-section" id="btnCompetencyDetails">
            <span class="btn-toggle-text">View All</span> <i class="fas fa-chevron-down"></i>
        </button>
    </div>

    <div id="competencyDetailsSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Filters -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-map-marker-alt"></i> Scope of Work:</label>
                <select id="filterScopeComp" class="filter-select-report">
                    <option value="">-- Semua Area --</option>
                    <option value="MSM">MSM</option>
                    <option value="TTN">TTN</option>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-award"></i> Competency Type:</label>
                <select id="filterTypeComp" class="filter-select-report">
                    <option value="">-- Semua Tipe --</option>
                    <option value="TT">Tenaga Teknis</option>
                    <option value="PT">Pengawas Teknis</option>
                    <option value="PO">Pengawas Operasional</option>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('competencyDetailsTable', 'Detail_Kompetensi_Report')">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report datatable" id="competencyDetailsTable" style="width: 100%; white-space: nowrap;">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th class="text-center">NO</th>
                            <th>REGISTER INTERNAL</th>
                            <th>KOMPETENSI</th>
                            <th>JENIS SERTIFIKAT</th>
                            <th>NO SERTIFIKAT</th>
                            <th>NO ID</th>
                            <th>NAMA PEMEGANG</th>
                            <th>JABATAN</th>
                            <th>PERUSAHAAN</th>
                            <th>TANGGAL TERBIT</th>
                            <th>MASA BERLAKU</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach($allCompData as $row): 
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
                                        $masa_berlaku_str = date('d-M-Y', strtotime($row['masa_berlaku']));
                                    }
                                } catch (Exception $e) {
                                    $masa_berlaku_str = $row['masa_berlaku'];
                                }
                            } elseif (!empty($row['masa_berlaku']) && $row['masa_berlaku'] != '0000-00-00') {
                                    $masa_berlaku_str = date('d-M-Y', strtotime($row['masa_berlaku']));
                            }
                        ?>
                        <tr data-scope="<?php echo htmlspecialchars($row['area_code']); ?>" data-competency="<?php echo htmlspecialchars($row['type_code']); ?>">
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="font-monospace"><strong><?php echo htmlspecialchars($row['register_internal']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['kompetensi'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($jenis_sert); ?></td>
                            <td><?php echo htmlspecialchars($row['no_sertifikat'] ?: '-'); ?></td>
                            <td><span class="emp-code-detail"><?php echo htmlspecialchars($row['no_id'] ?: '-'); ?></span></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['nama_pemegang'] ?: '-'); ?></td>
                            <td><span class="position-badge-report"><?php echo htmlspecialchars($row['jabatan'] ?: '-'); ?></span></td>
                            <td><?php echo htmlspecialchars($row['perusahaan'] ?: '-'); ?></td>
                            <td><?php echo !empty($row['tanggal_terbit']) && $row['tanggal_terbit'] != '0000-00-00' ? date('d-M-Y', strtotime($row['tanggal_terbit'])) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($masa_berlaku_str); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if jQuery and DataTables are loaded
    if (typeof $ !== 'undefined' && $.fn.dataTable) {
        
        // Define our custom search function
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex, rowData, counter) {
                // Only apply to our specific table
                if (settings.nTable.id !== 'competencyDetailsTable') {
                    return true;
                }

                var scopeFilter = $('#filterScopeComp').val();
                var typeFilter = $('#filterTypeComp').val();
                
                var node = settings.aoData[dataIndex].nTr;
                var rowScope = node.getAttribute('data-scope') || '';
                var rowType = node.getAttribute('data-competency') || '';

                if (scopeFilter && rowScope !== scopeFilter) {
                    return false;
                }
                
                if (typeFilter && rowType !== typeFilter) {
                    return false;
                }
                
                return true;
            }
        );

        // Bind events to redraw table
        $('#filterScopeComp, #filterTypeComp').on('change', function() {
            var table = $('#competencyDetailsTable').DataTable();
            table.draw();
        });
    }
});
</script>
