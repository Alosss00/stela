<?php
if (!isset($db)) {
    $db = new Database();
}

$where_clause = "WHERE (a.status = 'approved' OR a.status = 'verified')";
$params = [];

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'user' && !empty($_SESSION['company_name'])) {
        $where_clause .= " AND e.contractor_company = ?";
        $params[] = $_SESSION['company_name'];
    } elseif (($_SESSION['role'] === 'department_user' || $_SESSION['role'] === 'dept') && !empty($_SESSION['department'])) {
        $where_clause .= " AND e.department = ?";
        $params[] = $_SESSION['department'];
    }
}

$sql_comp = "
    SELECT 
        a.appointment_number as register_internal,
        NULL as area_code_db,
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
    $where_clause
    ORDER BY 
        CAST(SUBSTRING_INDEX(a.appointment_number, '/', -1) AS UNSIGNED) ASC, 
        CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(a.appointment_number, '/', -2), '/', 1) AS UNSIGNED) ASC, 
        CAST(SUBSTRING_INDEX(a.appointment_number, '/', 1) AS UNSIGNED) ASC, 
        a.created_at DESC
";

$res_comp = $db->query($sql_comp, $params);

$allCompData = [];
if ($res_comp) {
    while ($row = $res_comp->fetch_assoc()) {
        $appNum = strtoupper($row['register_internal']);
        
        // 1. Tentukan Scope of Work (MSM / TTN) berdasarkan database atau nomor registrasi
        $area_code = 'MSM';
        if (!empty($row['area_code_db'])) {
            $area_code = strtoupper($row['area_code_db']);
            // If the scope is MSM/TTN, map it to TTN to match historical report categorization
            if ($area_code === 'MSM/TTN') {
                $area_code = 'TTN';
            }
        } elseif (strpos($appNum, '/TTN/') !== false) {
            $area_code = 'TTN';
        } elseif (strpos($appNum, '/MSM/') !== false) {
            $area_code = 'MSM';
        } else {
            // Fallback jika tidak ada di nomor
            $area = strtolower($row['supervision_area'] ?? '');
            if (strpos($area, 'tondano') !== false || strpos($area, 'ttn') !== false) {
                $area_code = 'TTN';
            }
        }
        
        // 2. Tentukan Competency Type (TT / PT / PO) berdasarkan nomor registrasi
        $type_code = '';
        if (strpos($appNum, '/TT/') !== false) {
            $type_code = 'TT';
        } elseif (strpos($appNum, '/PT/') !== false) {
            $type_code = 'PT';
        } elseif (strpos($appNum, '/PO/') !== false) {
            $type_code = 'PO';
        } else {
            // Fallback jika tidak ada di nomor
            $ctype = $row['competency_type'];
            if ($ctype === 'tenaga_teknis') {
                $type_code = 'TT';
            } elseif ($ctype === 'pengawas_teknis') {
                $type_code = 'PT';
            } elseif ($ctype === 'pengawas_operasional') {
                $type_code = 'PO';
            }
        }
        
        $row['area_code'] = $area_code;
        $row['type_code'] = $type_code;
        $allCompData[] = $row;
    }
}

$req_companies = [];
foreach ($allCompData as $row_c) {
    $company = $row_c['perusahaan'] ?: 'Unknown';
    if (!in_array($company, $req_companies)) {
        $req_companies[] = $company;
    }
}
sort($req_companies);
?>

<div class="card-report" id="section-competency-details">
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
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Company:</label>
                <select id="filterCompanyComp" class="filter-select-report">
                    <option value="">-- All Company --</option>
                    <?php foreach ($req_companies as $comp): ?>
                    <option value="<?php echo htmlspecialchars($comp); ?>">
                        <?php echo htmlspecialchars($comp); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportCompetencyToExcel()">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report datatable" id="competencyDetailsTable" style="width: 100%; min-width: 1000px; table-layout: auto !important;">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th class="text-center">No</th>
                            <th style="min-width: 250px !important; white-space: nowrap !important;">Nomor Registrasi</th>
                            <th>Kompetensi</th>
                            <th>ID Badge</th>
                            <th>Nama Karyawan</th>
                            <th>Jabatan</th>
                            <th>Perusahaan</th>
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
                        <tr data-company="<?php echo htmlspecialchars($row['perusahaan'] ?: 'Unknown'); ?>" data-scope="<?php echo htmlspecialchars($row['area_code']); ?>" data-competency="<?php echo htmlspecialchars($row['type_code']); ?>">
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="font-monospace" style="white-space: nowrap !important; max-width: none !important;"><strong><?php echo htmlspecialchars($row['register_internal']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['kompetensi'] ?: '-'); ?></td>
                            <td><span class="emp-code-detail"><?php echo htmlspecialchars($row['no_id'] ?: '-'); ?></span></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['nama_pemegang'] ?: '-'); ?></td>
                            <td><span class="position-badge-report"><?php echo htmlspecialchars($row['jabatan'] ?: '-'); ?></span></td>
                            <td><?php echo htmlspecialchars($row['perusahaan'] ?: '-'); ?></td>
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
                var companyFilter = $('#filterCompanyComp').val();
                
                var node = settings.aoData[dataIndex].nTr;
                var rowScope = node.getAttribute('data-scope') || '';
                var rowType = node.getAttribute('data-competency') || '';
                var rowCompany = node.getAttribute('data-company') || '';

                if (scopeFilter && rowScope !== scopeFilter) {
                    return false;
                }
                
                if (typeFilter && rowType !== typeFilter) {
                    return false;
                }
                
                if (companyFilter && rowCompany !== companyFilter) {
                    return false;
                }
                
                return true;
            }
        );

        // Bind events to redraw table
        $('#filterScopeComp, #filterTypeComp, #filterCompanyComp').on('change', function() {
            var table = $('#competencyDetailsTable').DataTable();
            table.draw();
        });
    }
});

function exportCompetencyToExcel() {
    var table = document.getElementById('competencyDetailsTable');
    if (!table) return;

    var xl = `<html xmlns:o="urn:schemas-microsoft-com:office:office"
        xmlns:x="urn:schemas-microsoft-com:office:excel"
        xmlns="http://www.w3.org/TR/REC-html40">
    <head><meta charset="UTF-8"></head><body><table>`;
    
    xl += `<tr><td colspan="7"><b>Laporan Detail Kompetensi</b></td></tr>`;
    xl += `<tr><td colspan="7">Export Date: ${new Date().toLocaleDateString('id-ID')} ${new Date().toLocaleTimeString('id-ID')}</td></tr>`;
    
    var filterLabel = [];
    if ($('#filterScopeComp').val()) filterLabel.push('Scope: ' + $('#filterScopeComp option:selected').text());
    if ($('#filterTypeComp').val()) filterLabel.push('Type: ' + $('#filterTypeComp option:selected').text());
    if ($('#filterCompanyComp').val()) filterLabel.push('Company: ' + $('#filterCompanyComp option:selected').text());
    if (filterLabel.length > 0) xl += `<tr><td colspan="7">Filter: ${filterLabel.join(' | ')}</td></tr>`;
    
    xl += `<tr><td colspan="7"></td></tr>`;
    
    // Headers
    xl += '<tr>';
    xl += '<th>No</th><th>Nomor Registrasi</th><th>Kompetensi</th><th>ID Badge</th><th>Nama Karyawan</th><th>Jabatan</th><th>Perusahaan</th>';
    xl += '</tr>';

    // Data rows
    var dt = $('#competencyDetailsTable').DataTable();
    var rows = dt.rows({ search: 'applied' }).nodes(); // only filtered rows
    
    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        xl += `<tr>`;
        xl += `<td>${i+1}</td>`;
        for (var j = 1; j < cells.length; j++) {
            xl += `<td>${cells[j].textContent.trim()}</td>`;
        }
        xl += `</tr>`;
    }

    xl += '</table></body></html>';

    var blob = new Blob([xl], { type: 'application/vnd.ms-excel;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'Detail_Kompetensi_Report_' + new Date().toISOString().slice(0, 10) + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
