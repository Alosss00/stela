<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

if (!isset($_GET['id'])) {
    die('ID tidak valid');
}

$id = intval($_GET['id']);
$db = new Database();

$appointment = $db->query("
    SELECT a.*, 
           e.full_name as employee_name, 
           e.employee_code, 
           e.id_number, 
           e.position, 
           e.contractor_company, 
           e.signature_file,
           e.competency_type,
           e.competency_name,
           e.ruang_lingkup,
           e.supervision_area,
           u.full_name as approved_by_name,
           GROUP_CONCAT(DISTINCT ec.cert_number ORDER BY ec.id SEPARATOR ', ') as cert_numbers
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN users u ON a.approved_by = u.id
    LEFT JOIN employee_certifications ec ON e.id = ec.employee_id AND ec.verification_status = 'verified'
    WHERE a.id = $id
    GROUP BY a.id
")->fetch_assoc();

if (!$appointment) {
    die('Data tidak ditemukan');
}

// Check if both KTTs have approved (for digital signatures)
$ktt_approvals_check = $db->query("
    SELECT COUNT(*) as total_approvals,
           SUM(CASE WHEN action = 'approve' THEN 1 ELSE 0 END) as approved_count
    FROM ktt_approvals 
    WHERE appointment_id = $id
")->fetch_assoc();

$both_ktt_approved = ($appointment['status'] == 'approved' && 
                      $ktt_approvals_check['approved_count'] >= 2);

// Function to determine document code based on competency type
function getDocumentCode($competency_type) {
    if ($competency_type == 'pengawas_operasional') {
        return 'TT-MGT-FRS-008A';
    } elseif ($competency_type == 'pengawas_teknis') {
        return 'TT-MGT-FRS-008B';
    } elseif ($competency_type == 'tenaga_teknis') {
        return 'TT-MGT-FRS-008C';
    }
    
    return 'TT-MGT-FRS-008C';
}

// Function to get header title based on competency type
function getHeaderTitle($competency_type) {
    if ($competency_type == 'pengawas_operasional') {
        return 'Pengawas Operasional Pertambangan';
    } elseif ($competency_type == 'pengawas_teknis') {
        return 'Pengawas Teknis';
    } elseif ($competency_type == 'tenaga_teknis') {
        return 'Tenaga Teknis Berkompeten';
    }
    
    return 'Tenaga Teknis Berkompeten';
}

// Function to get letter content based on competency type and name
function getLetterContent($competency_type, $competency_name, $appointment) {
    $comp_lower = strtolower($competency_name ?? '');
    
    // Pengawas Operasional Pertambangan
    if ($competency_type == 'pengawas_operasional') {
        return [
            'introduction' => '<p>Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.</p>',
            'responsibilities' => [
                'Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;',
                'Melaksanakan inspeksi, pemeriksaan, dan pengujian;',
                'Bertanggung jawab atas keselamatan, kesehatan, dan kesejahteraan dari semua orang yang ditugaskan kepadanya;',
                'Membuat dan menandatangani laporan pemeriksaan, inspeksi, dan pengujian;',
                'Menerapkan Sistem Manajemen Keselamatan Pertambangan;',
                'Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;',
                'Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;',
                'Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;',
                'Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindaklanjuti;'
            ]
        ];
    }
    
    // Pengawas Teknis
    if ($competency_type == 'pengawas_teknis') {
        return [
            'introduction' => '<p>Berdasarkan peraturan perundang-undangan yang berlaku tentang Keselamatan dan Kesehatan Kerja Pertambangan, terkait penunjukan Pengawas Teknis.</p>',
            'responsibilities' => [
                'Melakukan pengawasan terhadap pelaksanaan kegiatan teknis di bidang ' . (strpos($comp_lower, 'mekanik') !== false ? 'permesinan dan peralatan mekanik' : (strpos($comp_lower, 'elektrik') !== false ? 'kelistrikan dan peralatan elektrik' : 'teknis')) . ';',
                'Memberikan arahan teknis kepada tenaga pelaksana dalam melaksanakan pekerjaan sesuai standar operasional;',
                'Melakukan pemeriksaan dan evaluasi kondisi peralatan secara berkala;',
                'Memastikan penerapan standar keselamatan dan kesehatan kerja dalam setiap kegiatan teknis;',
                'Membuat laporan pelaksanaan kegiatan teknis dan rekomendasi perbaikan;'
            ]
        ];
    }
    
    // Tenaga Teknis
    if ($competency_type == 'tenaga_teknis') {
        // Check for specific technical positions
        if (strpos($comp_lower, 'juru las') !== false || strpos($comp_lower, 'welder') !== false) {
            return [
                'introduction' => '<p>Berdasarkan peraturan tentang Keselamatan dan Kesehatan Kerja Bidang Pengelasan, terkait penunjukan Juru Las/Welder.</p>',
                'responsibilities' => [
                    'Melaksanakan pekerjaan pengelasan sesuai dengan spesifikasi teknis dan standar keselamatan yang berlaku;',
                    'Melakukan pemeriksaan visual terhadap hasil pengelasan sebelum diserahkan;',
                    'Melakukan perawatan dan pemeliharaan peralatan las yang digunakan;',
                    'Menerapkan prosedur K3 (Keselamatan dan Kesehatan Kerja) dalam setiap pekerjaan pengelasan;',
                    'Membuat laporan hasil pekerjaan pengelasan yang telah dilaksanakan;',
                    'Memastikan area kerja aman dari bahaya kebakaran dan ledakan;'
                ]
            ];
        } elseif (strpos($comp_lower, 'operator') !== false) {
            return [
                'introduction' => '<p>Berdasarkan peraturan tentang Keselamatan dan Kesehatan Kerja Pengoperasian Alat Berat, terkait penunjukan Operator Alat Berat.</p>',
                'responsibilities' => [
                    'Mengoperasikan alat berat sesuai dengan spesifikasi teknis dan standar operasional prosedur yang berlaku;',
                    'Melakukan pemeriksaan kondisi alat berat sebelum dan sesudah pengoperasian (Pre-operation dan Post-operation check);',
                    'Melaksanakan pekerjaan dengan memperhatikan aspek keselamatan kerja, produktivitas dan efisiensi;',
                    'Melaporkan setiap kerusakan atau kelainan yang terjadi pada alat berat yang dioperasikan;',
                    'Membuat laporan operasional harian (Daily Report) sesuai dengan ketentuan yang berlaku;',
                    'Mematuhi rambu-rambu dan aturan lalu lintas di area tambang;'
                ]
            ];
        }
        
        // Default Tenaga Teknis
        return [
            'introduction' => '<p>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</p>',
            'responsibilities' => [
                'Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;',
                'Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;',
                'Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;',
                'Membuat laporan pelaksanaan tugas secara berkala;',
                'Mematuhi seluruh peraturan dan tata tertib perusahaan;'
            ]
        ];
    }
    
    // Default content
    return [
        'introduction' => '<p>Berdasarkan kebutuhan operasional perusahaan dan mempertimbangkan kompetensi yang dimiliki.</p>',
        'responsibilities' => [
            'Melaksanakan tugas dan tanggung jawab sesuai dengan jabatan yang diberikan;',
            'Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;',
            'Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;',
            'Membuat laporan pelaksanaan tugas secara berkala;',
            'Mematuhi seluruh peraturan dan tata tertib perusahaan;'
        ]
    ];
}

// Check if letter_content already filled by admin
if (!empty($appointment['letter_content'])) {
    // Use custom letter content from admin
    $custom_content = $appointment['letter_content'];
} else {
    // Generate automatic content based on competency type
    $letter_content = getLetterContent($appointment['competency_type'], $appointment['competency_name'], $appointment);
    $custom_content = null;
}

// Get document code and header title based on competency type
$doc_code = getDocumentCode($appointment['competency_type']);
$header_title = getHeaderTitle($appointment['competency_type']);

// Map competency type display
$type_labels = [
    'pengawas_operasional' => 'Pengawas Operasional',
    'pengawas_teknis' => 'Pengawas Teknis',
    'tenaga_teknis' => 'Tenaga Teknis'
];
$competency_type_display = $type_labels[$appointment['competency_type']] ?? $appointment['competency_type'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Penunjukan - <?php echo $appointment['appointment_number']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none; }
            
            @page {
                margin: 15mm 10mm 20mm 10mm;
                size: A4;
            }
            
            body {
                counter-reset: page;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                height: 2.5cm;
                padding: 5px 0;
                background: white;
                z-index: 1000;
                border-bottom: 4px solid #808080;
            }
            
            .footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: auto;
                padding: 5px 10px;
                background: white;
                z-index: 1000;
                border-top: 4px solid #808080;
            }
            
            .content {
                position: relative;
                z-index: 1;
                margin-top: 3cm;
                margin-bottom: 2.5cm;
                padding-top: 0px;
                page-break-inside: auto;
            }
            
            .data-table {
                position: relative;
                z-index: 10;
            }
            
            .signature-table {
                page-break-inside: avoid;
            }
        }
        
        @media screen {
            .header {
                margin-bottom: 0;
                padding-bottom: 15px;
                border-bottom: 4px solid #808080;
                background: #fff;
            }
            
            .content {
                margin-top: 0;
                margin-bottom: 0;
                padding: 20px 5px;
                background: #fff;
            }
            
            .footer {
                margin-top: 0;
                margin-left: -5px;
                margin-right: -5px;
                padding-top: 15px;
                padding-left: 5px;
                padding-right: 5px;
                border-top: 4px solid #808080;
                page-break-inside: avoid;
                background: #fff;
            }
            
            .header-spacer {
                display: none;
            }
            
            .footer-spacer {
                display: none;
            }
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 5px;
            font-size: 11pt;
            line-height: 1.3;
        }
        
        .container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 5px;
        }
        
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .header-table td {
            vertical-align: middle;
        }
        
        .header-logo-cell {
            width: 150px;
            text-align: center;
        }
        
        .header-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        
        .header-content {
            text-align: center;
            padding: 0 15px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 14pt;
            font-weight: bold;
            color: #000;
        }
        
        .header h2 {
            margin: 2px 0;
            font-size: 12pt;
            font-weight: normal;
        }
        
        .header p {
            margin: 1px 0;
            font-size: 11pt;
            font-weight: bold;
        }
        
        .content {
            margin-top: 30px;
            margin-bottom: 0;
            clear: both;
        }
        
        .content table,
        .content .data-table {
            page-break-inside: auto;
        }
        
        .content .signature-table {
            page-break-inside: avoid;
            margin-top: 15px;
        }
        
        .content tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        
        /* Spacer untuk memastikan konten tidak overlap dengan header/footer saat print */
        .header-spacer {
            height: 0;
        }
        
        .footer-spacer {
            height: 0;
        }
        
        @media print {
            .header-spacer {
                height: 2.2cm;
            }
            
            .footer-spacer {
                height: 2cm;
            }
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10pt;
        }
        
        .data-table td {
            padding: 4px 8px;
            border: 0;
            border-bottom: 1px solid #000;
            vertical-align: top;
        }
        
        .data-table tr:first-child td {
            border-top: 1px solid #000;
        }
        
        .data-table td.no-border {
            border: none;
        }
        
        .data-table td:first-child {
            width: 150px;
            font-weight: normal;
        }
        
        .data-table td:nth-child(2) {
            width: 20px;
            text-align: center;
        }
        
        .content-text {
            padding: 5px;
            text-align: justify;
            line-height: 1.5;
            font-size: 10pt;
            margin-bottom: 5px;
        }
        
        .content-text p {
            margin: 5px 0;
        }
        
        .content-text ul {
            list-style: none;
            padding-left: 0;
            margin: 5px 0;
        }
        
        .content-text li {
            margin: 4px 0;
            padding-left: 20px;
            text-indent: -20px;
        }
        
        .content-text li:before {
            content: "• ";
            font-weight: bold;
        }
        
        .details {
            margin: 15px 0 15px 50px;
        }
        
        .details table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .details td {
            padding: 5px 10px;
            vertical-align: top;
        }
        
        .details td:first-child {
            width: 200px;
        }
        
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10pt;
        }
        
        .signature-table td {
            padding: 6px;
            border: 1px solid #000;
            text-align: center;
            vertical-align: middle;
        }
        
        .signature-table td:first-child {
            border-left: none;
        }
        
        .signature-table td:last-child {
            border-right: none;
        }
        
        .signature-table td div {
            height: 40px;
        }
        
        .signature-table td .signature-img {
            max-width: 120px;
            max-height: 40px;
            object-fit: contain;
        }
        
        .signature {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        
        .signature-box {
            float: right;
            text-align: center;
            width: 250px;
        }
        
        .signature-box p {
            margin: 5px 0;
        }
        
        .signature-space {
            height: 80px;
        }
        
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6pt;
        }
        
        .footer-table td {
            padding: 1px 2px;
            border: 0.3px solid #000;
            vertical-align: top;
        }
        
        .footer-table td:first-child {
            border-left: none;
        }
        
        .footer-table td:last-child {
            border-right: none;
        }
        
        .footer-table .label {
            font-weight: bold;
            width: 110px;
        }
        
        .btn-print {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            margin-bottom: 20px;
            margin-right: 10px;
        }
        
        .btn-print:hover {
            background-color: #0056b3;
        }
        
        .btn-close {
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .btn-close:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Cetak Surat</button>
        <button onclick="window.close()" class="btn-close">Tutup</button>
    </div>
    
    <div class="container">
        <!-- HEADER SECTION -->
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="header-logo-cell">
                        <img src="assets/Logo/LOGO_MSM_TTN.png" alt="Logo" class="header-logo">
                    </td>
                    <td class="header-content">
                        <h1>TOKA TINDUNG PROJECT</h1>
                        <h2><strong>Surat Penunjukan <?php echo htmlspecialchars($header_title); ?></h2></strong>
                        <p><?php echo htmlspecialchars($doc_code); ?></p>
                    </td>
                    <td class="header-logo-cell">
                        <img src="assets/Logo/LOGO_ARCHI.png" alt="Logo" class="header-logo">
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Spacer untuk header -->
        <div class="header-spacer"></div>
        
        <!-- CONTENT SECTION - Terpisah dari Header dan Footer -->
        <div class="content">
            <table class="data-table">
                <tr>
                    <td>Nama Lengkap</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($appointment['employee_name']); ?></td>
                </tr>
                <tr>
                    <td>Badge ID</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($appointment['employee_code']); ?></td>
                </tr>
                <tr>
                    <td>Jabatan</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($appointment['position']); ?></td>
                </tr>
                <tr>
                    <td>Perusahaan</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($appointment['contractor_company'] ?? 'PT Mahakam Sumber Mandiri'); ?></td>
                </tr>
                <?php if (!empty($appointment['competency_name'])): ?>
                <tr>
                    <td>Kompetensi</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($appointment['competency_name']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($appointment['competency_type'] == 'pengawas_operasional' || $appointment['competency_type'] == 'pengawas_teknis'): ?>
                    <?php if (!empty($appointment['ruang_lingkup'])): ?>
                    <tr>
                        <td>Lingkup Tugas</td>
                        <td>:</td>
                        <td>
                            <?php 
                            // Format khusus: Di "contractor_name" untuk area kerja "ruang_lingkup"
                            $contractor_name = $appointment['contractor_company'] ?? 'PT Mahakam Sumber Mandiri';
                            $ruang_lingkup = $appointment['ruang_lingkup'];
                            echo 'Di ' . htmlspecialchars($contractor_name) . ' untuk area kerja ' . htmlspecialchars($ruang_lingkup) . '';
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($appointment['competency_type'] == 'pengawas_operasional'): ?>
                    <?php if (!empty($appointment['supervision_area'])): ?>
                    <tr>
                        <td>Area Pengawasan</td>
                        <td>:</td>
                        <td><?php echo htmlspecialchars($appointment['supervision_area']); ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endif; ?>
                <tr>
                    <td style="width: 150px;">No Registrasi</td>
                    <td style="width: 20px;">:</td>
                    <td>
                        <?php 
                        // Use appointment_number from database (already generated in appointments.php)
                        echo htmlspecialchars($appointment['appointment_number']);
                        ?>
                        
                        <span style="margin-left: 100px;">Sertifikat No:
                        <?php 
                        if (!empty($appointment['cert_numbers'])) {
                            echo htmlspecialchars($appointment['cert_numbers']);
                        } else {
                            echo '-';
                        }
                        ?></span>
                    </td>
                </tr>
    
                <tr>
                    <td colspan="4" class="no-border">
                        <div class="content-text">
                            <?php if ($custom_content): ?>
                                <!-- Custom letter content from admin -->
                                <?php echo nl2br(htmlspecialchars($custom_content)); ?>
                            <?php else: ?>
                                <!-- Auto-generated content based on position -->
                                <?php echo $letter_content['introduction']; ?>
                                
                                <p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; <?php echo htmlspecialchars($competency_type_display); ?><?php if (!empty($appointment['competency_name'])): ?> - <?php echo htmlspecialchars($appointment['competency_name']); ?><?php endif; ?> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p>
                                
                                <ul>
                                    <?php foreach ($letter_content['responsibilities'] as $responsibility): ?>
                                    <li><?php echo htmlspecialchars($responsibility); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
            
            <!-- Signature Table -->
            <table class="signature-table">
                <tr>
                    <td style="width: 50%; vertical-align: middle;">
                        <strong>Ditunjuk oleh KTT MSM</strong>
                    </td>
                    <td style="width: 50%; vertical-align: middle;">
                        <strong>Ditunjuk oleh KTT TTN</strong>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div>
                            <?php 
                            // Show KTT MSM signature only if both KTTs have approved
                            if ($both_ktt_approved): 
                                $ktt_msm_sig = 'assets/uploads/signatures/signature_KTT_MSM.png';
                                if (file_exists($ktt_msm_sig)): 
                            ?>
                                <img src="<?php echo htmlspecialchars($ktt_msm_sig); ?>" alt="Tanda Tangan KTT MSM" class="signature-img">
                            <?php 
                                endif;
                            endif; 
                            ?>
                        </div>
                        <strong>TEJO PRIHANTORO</strong>
                    </td>
                    <td>
                        <div>
                            <?php 
                            // Show KTT TTN signature only if both KTTs have approved
                            if ($both_ktt_approved): 
                                $ktt_ttn_sig = 'assets/uploads/signatures/signature_KTT_TTN.png';
                                if (file_exists($ktt_ttn_sig)): 
                            ?>
                                <img src="<?php echo htmlspecialchars($ktt_ttn_sig); ?>" alt="Tanda Tangan KTT TTN" class="signature-img">
                            <?php 
                                endif;
                            endif; 
                            ?>
                        </div>
                        <strong>AGUNG PRAPTONO</strong>
                    </td>
                </tr>
                <tr>
                    <td>Tgl: <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></td>
                    <td>Tgl: <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></td>
                </tr>
            </table>
        </div>
        <!-- END CONTENT SECTION -->
        
        <!-- Spacer untuk footer -->
        <div class="footer-spacer"></div>
        
        <!-- FOOTER SECTION - Terpisah dari Content -->
        <div class="footer">
            <?php
            // Determine document name based on competency type
            $doc_name = 'Surat Penunjukan ' . $header_title;
            
            $appointment_date = new DateTime($appointment['appointment_date']);
            $tanggal_terbit = $appointment_date->format('d F Y');
            
            // Calculate 3 years from appointment date for review date
            $review_date = clone $appointment_date;
            $review_date->modify('+3 years');
            $tanggal_tinjau = $review_date->format('d F Y');
            
            // Convert month names to Indonesian
            $months_id = [
                'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
                'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
                'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
                'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
            ];
            
            foreach ($months_id as $en => $id) {
                $tanggal_terbit = str_replace($en, $id, $tanggal_terbit);
                $tanggal_tinjau = str_replace($en, $id, $tanggal_tinjau);
            }
            ?>
            <br>
            <table class="footer-table">
                <tr>
                    <td class="label">Nama Dokumen</td>
                    <td colspan="3"><?php echo htmlspecialchars($doc_name); ?></td>
                </tr>
                <tr>
                    <td class="label">Ditetapkan Oleh</td>
                    <td>Kepala Teknik Tambang</td>
                    <td class="label">Tanggal Terbit</td>
                    <td><?php echo htmlspecialchars($tanggal_terbit); ?></td>
                </tr>
                <tr>
                    <td class="label">No Dokumen</td>
                    <td><?php echo htmlspecialchars($doc_code); ?></td>
                    <td class="label">Tanggal Tinjau Ulang</td>
                    <td><?php echo htmlspecialchars($tanggal_tinjau); ?></td>
                </tr>
                <tr>
                    <td class="label">No Revisi</td>
                    <td>00</td>
                    <td colspan="2"><span style="color: #d32f2f;">Dokumen terkendali dan valid hanya ada di sharepoint Archi Indonesia</span></td>
                </tr>
            </table>
        </div>
        <!-- END FOOTER SECTION -->
    </div>
</body>
</html>

