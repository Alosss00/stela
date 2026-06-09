<?php
/**
 * Script untuk membuat template Surat Pernyataan dalam format PDF
 * Jalankan script ini sekali untuk menghasilkan template PDF
 */

require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Konfigurasi Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

// HTML untuk Surat Pernyataan
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Template Surat Pernyataan</title>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.6;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .title {
            font-weight: bold;
            font-size: 14pt;
            text-decoration: underline;
            margin-bottom: 5px;
        }
        .content {
            text-align: justify;
            margin-bottom: 20px;
        }
        .statement-item {
            margin-bottom: 15px;
            padding-left: 30px;
            text-indent: -30px;
        }
        .signature-section {
            margin-top: 50px;
            margin-left: 60%;
        }
        .signature-box {
            margin-top: 80px;
            border-bottom: 1px solid #000;
            width: 200px;
        }
        .note {
            margin-top: 40px;
            font-style: italic;
            font-size: 10pt;
            color: #666;
        }
        .placeholder {
            color: #0066cc;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">SURAT PERNYATAAN</div>
        <div>KESESUAIAN KOMPETENSI DAN SERTIFIKASI</div>
    </div>

    <div class="content">
        <p>Yang bertanda tangan di bawah ini:</p>
        
        <table style="margin-left: 30px; margin-bottom: 20px;">
            <tr>
                <td style="width: 150px;">Nama</td>
                <td style="width: 20px;">:</td>
                <td><span class="placeholder">[Nama Lengkap]</span></td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>:</td>
                <td><span class="placeholder">[Jabatan]</span></td>
            </tr>
            <tr>
                <td>Perusahaan</td>
                <td>:</td>
                <td><span class="placeholder">[Nama Perusahaan]</span></td>
            </tr>
            <tr>
                <td>ID BADGE</td>
                <td>:</td>
                <td><span class="placeholder">[ID BADGE]</span></td>
            </tr>
        </table>

        <p>Dengan ini menyatakan bahwa:</p>

        <div class="statement-item">
            1. Semua data yang saya berikan dalam formulir pengajuan tenaga kerja adalah benar dan dapat dipertanggungjawabkan.
        </div>

        <div class="statement-item">
            2. Sertifikat kompetensi yang saya lampirkan adalah asli dan masih berlaku sesuai dengan masa berlaku yang tertera.
        </div>

        <div class="statement-item">
            3. Saya memiliki kompetensi yang sesuai dengan jabatan yang diajukan dan bersedia melaksanakan tugas dengan sebaik-baiknya.
        </div>

        <div class="statement-item">
            4. Saya bersedia mematuhi seluruh peraturan dan ketentuan yang berlaku di lingkungan kerja.
        </div>

        <div class="statement-item">
            5. Apabila dikemudian hari terbukti ada data yang tidak benar atau pemalsuan dokumen, saya bersedia menerima sanksi sesuai dengan ketentuan yang berlaku.
        </div>

        <p style="margin-top: 20px;">
            Demikian surat pernyataan ini saya buat dengan sebenar-benarnya untuk dapat dipergunakan sebagaimana mestinya.
        </p>
    </div>

    <div class="signature-section">
        <p><span class="placeholder">[Kota]</span>, <span class="placeholder">[Tanggal]</span></p>
        <p>Yang Membuat Pernyataan,</p>
        <div class="signature-box"></div>
        <p><strong><span class="placeholder">[Nama Lengkap]</span></strong></p>
    </div>

    <div class="note">
        <strong>CATATAN PENTING:</strong><br>
        1. Ganti semua teks berwarna biru dengan data yang sesuai.<br>
        2. Cetak surat ini, tanda tangani dengan <strong>tanda tangan basah (asli)</strong> di atas materai 10.000.<br>
        3. Scan hasil yang sudah ditandatangani dalam format PDF.<br>
        4. Upload file PDF hasil scan ke sistem.
    </div>
</body>
</html>
';

// Load HTML ke Dompdf
$dompdf->loadHtml($html);

// Set ukuran kertas A4
$dompdf->setPaper('A4', 'portrait');

// Render PDF
$dompdf->render();

// Simpan ke file
$output_path = 'assets/templates/template_surat_pernyataan.pdf';
file_put_contents($output_path, $dompdf->output());

echo "✅ Template Surat Pernyataan berhasil dibuat!\n";
echo "📁 Lokasi file: $output_path\n";
echo "\n";
echo "Silakan buka file tersebut untuk melihat template.\n";
?>

