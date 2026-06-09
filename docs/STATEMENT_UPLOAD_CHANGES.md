# PERUBAHAN: UPLOAD SURAT PERNYATAAN

## Tanggal: 2 Februari 2026

## Ringkasan Perubahan

Upload tanda tangan telah diganti menjadi **Upload Surat Pernyataan** dengan persyaratan:
- Format: **PDF**
- Wajib diisi (**REQUIRED**)
- Harus ditandatangani dengan **tanda tangan basah (asli)** oleh yang bersangkutan

## File yang Diubah

### 1. Frontend (Form Upload)
- `user_add_employee.php` - Form tambah employee untuk role USER
- `dept_add_employee.php` - Form tambah employee untuk role DEPARTMENT
- `admin_add_employee.php` - Form tambah employee untuk role ADMIN

**Perubahan:**
- Menambahkan field upload `statement_file` (required)
- Menambahkan alert warning tentang tanda tangan basah
- Menambahkan tombol download template surat pernyataan
- Accept hanya file PDF (`.pdf`)
- Max size: 5MB

### 2. Backend (Upload Handler)
**File yang diubah:**
- `user_add_employee.php` (line 65-136)
- `dept_add_employee.php` (line 65-136)
- `admin_add_employee.php` (line 50-100)

**Perubahan Logic:**
```php
// Validasi wajib
elseif (!isset($_FILES['statement_file']) || $_FILES['statement_file']['error'] != 0) {
    $error = 'Upload Surat Pernyataan wajib diisi!';
}

// Handle upload
$statement_file = '';
if (!$error) {
    // Validasi PDF only
    if ($stmt_file_extension !== 'pdf') {
        $error = 'Surat Pernyataan harus berformat PDF!';
    }
    
    // Upload ke folder statements/
    $stmt_upload_dir = 'assets/uploads/statements/';
    $stmt_new_filename = 'statement_' . $employee_code . '_' . time() . '.pdf';
    
    // Save to database
    if (in_array('statement_file', $available_columns) && !empty($statement_file)) {
        $insert_fields[] = 'statement_file';
        $insert_values[] = "'$statement_file'";
    }
}
```

### 3. Database Migration
**File:**
- `migration_add_statement_file.sql`
- `run_migration_statement.php`

**SQL:**
```sql
ALTER TABLE `employees` 
ADD COLUMN `statement_file` VARCHAR(255) NULL DEFAULT NULL 
COMMENT 'Path to employee statement letter (PDF with wet signature)' 
AFTER `cv_file`;
```

**Status:** ✅ Migration berhasil dijalankan

### 4. Template Surat Pernyataan
**File:**
- `assets/templates/template_surat_pernyataan.html`

**Cara Penggunaan:**
1. Klik tombol "Download Template Surat Pernyataan" di form
2. Template akan terbuka di tab baru
3. Edit teks berwarna biru sesuai data karyawan
4. Klik tombol "Print / Save as PDF" di pojok kanan atas
5. Pilih "Save as PDF" atau "Microsoft Print to PDF"
6. Cetak PDF tersebut
7. Tanda tangani dengan **tanda tangan basah** di atas materai
8. Scan hasil yang sudah ditandatangani
9. Upload ke sistem

**Isi Surat Pernyataan:**
- Identitas karyawan (Nama, Jabatan, Perusahaan, ID Batch)
- 5 poin pernyataan:
  1. Data yang diberikan benar
  2. Sertifikat asli dan masih berlaku
  3. Memiliki kompetensi sesuai jabatan
  4. Bersedia mematuhi peraturan
  5. Bersedia menerima sanksi jika ada pemalsuan

### 5. Folder Upload
**Dibuat:**
- `assets/uploads/statements/`

**Naming Convention:**
```
statement_[EMPLOYEE_CODE]_[TIMESTAMP].pdf
Contoh: statement_BATCH001_1738547892.pdf
```

## CSS yang Ditambahkan

```css
.alert-warning-custom {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 15px 20px;
    border-radius: 8px;
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.alert-warning-custom i {
    color: #f59e0b;
    font-size: 20px;
}

.alert-warning-custom strong {
    display: block;
    color: #92400e;
    margin-bottom: 5px;
}

.alert-warning-custom p {
    margin: 0;
    color: #92400e;
    font-size: 13px;
}
```

## Testing Checklist

### Sebelum Testing
- [x] Database migration berhasil
- [x] Kolom `statement_file` ada di tabel `employees`
- [x] Folder `assets/uploads/statements/` sudah dibuat
- [x] Template HTML tersedia di `assets/templates/`

### Testing Form
- [ ] Buka form tambah employee (user/dept/admin)
- [ ] Cek field "Upload Surat Pernyataan" muncul
- [ ] Cek alert warning muncul (background kuning)
- [ ] Cek tombol "Download Template" berfungsi
- [ ] Template terbuka di tab baru
- [ ] Template bisa di-print/save as PDF

### Testing Upload
- [ ] Coba submit tanpa upload surat pernyataan → Error: "Upload Surat Pernyataan wajib diisi!"
- [ ] Coba upload file non-PDF (JPG/PNG) → Error: "Surat Pernyataan harus berformat PDF!"
- [ ] Coba upload file > 5MB → Error: "Ukuran file terlalu besar!"
- [ ] Upload file PDF valid → Berhasil tersimpan

### Testing Database
- [ ] Cek record baru di tabel `employees`
- [ ] Kolom `statement_file` berisi path file: `uploads/statements/statement_XXX_XXX.pdf`
- [ ] File fisik ada di `assets/uploads/statements/`

## Catatan Penting

### Untuk Developer
1. Field `statement_file` sekarang **REQUIRED** (wajib)
2. Hanya accept file PDF
3. Max size: 5MB
4. File naming: `statement_[EMPLOYEE_CODE]_[TIMESTAMP].pdf`

### Untuk User
1. **WAJIB** upload surat pernyataan
2. Surat pernyataan harus ditandatangani dengan **tanda tangan basah (asli)**
3. Download template yang disediakan
4. Isi sesuai data karyawan
5. Print → Tanda tangan → Scan → Upload

### Backward Compatibility
- Kolom `signature_file` (lama) masih ada di database
- Data lama tidak akan terganggu
- Employee baru harus menggunakan `statement_file`

## Rollback (jika diperlukan)

Jika ingin kembali ke sistem lama:

```sql
-- Remove statement_file column
ALTER TABLE employees DROP COLUMN statement_file;
```

Kemudian revert perubahan di file PHP (gunakan Git):
```bash
git checkout HEAD -- user_add_employee.php dept_add_employee.php admin_add_employee.php
```

## File Tambahan yang Dibuat

1. `migration_add_statement_file.sql` - SQL untuk add column
2. `run_migration_statement.php` - Script runner migration
3. `assets/templates/template_surat_pernyataan.html` - Template surat
4. `create_statement_template.php` - Script generator (tidak terpakai)
5. `STATEMENT_UPLOAD_CHANGES.md` - Dokumentasi ini

## Selesai ✅

Semua perubahan telah diimplementasikan dan siap untuk testing!
