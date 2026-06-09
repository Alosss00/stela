# Dokumentasi Fitur Edit Surat Penunjukan

## Fitur Baru
Sistem sekarang memiliki kemampuan untuk mengedit konten surat penunjukan sebelum dicetak menggunakan CKEditor 5 Document Editor.

## File yang Dibuat/Dimodifikasi

### 1. File Baru: `edit_print_appointment.php`
File ini menyediakan interface untuk mengedit konten surat dengan fitur:
- **Text Editor**: CKEditor 5 Document Editor dengan toolbar lengkap
- **Live Preview**: Preview real-time saat mengedit
- **Simpan Perubahan**: Menyimpan konten yang diedit ke database
- **Preview & Cetak**: Membuka halaman cetak setelah menyimpan

#### Fitur CKEditor:
- Font family & size
- Text formatting (bold, italic, underline, strikethrough)
- Text alignment
- Lists (numbered & bulleted)
- Indent/outdent
- Links, tables, blockquotes
- Undo/redo

#### Keamanan:
- Hanya admin dan KTT yang dapat mengakses
- Session validation
- SQL injection protection dengan prepared statements

### 2. File Dimodifikasi: `appointments.php`
Menambahkan tombol "Edit Surat" untuk appointment dengan status 'approved':

```php
<!-- Edit Surat Button (for approved appointments) -->
<?php if ($row['status'] == 'approved'): ?>
<a href="edit_print_appointment.php?id=<?php echo $row['id']; ?>" 
   target="_blank" class="btn-action-appt edit-btn" 
   title="Edit Konten Surat">
    <i class="fas fa-edit"></i>
</a>
<?php endif; ?>
```

### 3. File Dimodifikasi: `approval.php`
Menambahkan:
- Tombol "Edit Surat" di modal review
- CSS styling untuk tombol edit
- JavaScript untuk menampilkan tombol hanya untuk approved appointments

## Cara Penggunaan

### Untuk Admin:

1. **Dari Halaman Appointments**:
   - Buka halaman appointments.php
   - Cari appointment dengan status "Disetujui" (approved)
   - Klik tombol **Edit** (icon pensil) di sebelah tombol cetak
   - Halaman editor akan terbuka di tab baru

2. **Dari Halaman KTT (approval.php)**:
   - Buka appointment yang sudah disetujui
   - Di modal detail, akan muncul tombol **Edit Surat**
   - Klik untuk membuka editor

3. **Di Halaman Editor**:
   - Panel kiri: Editor dengan toolbar lengkap
   - Panel kanan: Preview real-time
   - Edit konten sesuai kebutuhan
   - Klik **"Simpan Perubahan"** untuk menyimpan ke database
   - Klik **"Preview & Cetak"** untuk melihat hasil akhir dan mencetak
   - Klik **"Kembali"** untuk kembali ke halaman appointments

## Database
Konten yang diedit disimpan di kolom `letter_content` pada tabel `appointments`:
- Jika `letter_content` kosong/NULL: sistem akan generate otomatis berdasarkan competency_type
- Jika `letter_content` terisi: sistem akan menggunakan konten custom dari admin

## Teknologi yang Digunakan

### CKEditor 5 Document Editor
- **CDN**: https://cdn.ckeditor.com/ckeditor5/40.2.0/decoupled-document/ckeditor.js
- **Build**: Decoupled Document Editor
- **Versi**: 40.2.0

### Fitur UI/UX:
- Responsive design (2 kolom untuk desktop, 1 kolom untuk mobile)
- Real-time preview
- Loading overlay saat menyimpan
- Alert notifications untuk feedback
- Modern gradient styling

## Keuntungan

1. **Fleksibilitas**: Admin dapat menyesuaikan konten surat sesuai kebutuhan spesifik
2. **Preview Real-time**: Melihat hasil sebelum menyimpan
3. **User-friendly**: Interface intuitif dengan WYSIWYG editor
4. **Persistent**: Konten tersimpan di database, bisa diedit kembali kapan saja
5. **Backward Compatible**: Jika tidak diedit, sistem tetap generate otomatis

## Catatan Teknis

### Auto-generate vs Custom Content
- **Auto-generate**: Sistem menggunakan fungsi `getLetterContent()` berdasarkan:
  - competency_type (pengawas_operasional, pengawas_teknis, tenaga_teknis)
  - competency_name (untuk detail spesifik seperti welder, operator, dll)
  
- **Custom Content**: Disimpan di `appointments.letter_content`
  - Jika terisi, akan digunakan sebagai pengganti auto-generate
  - Tetap bisa dikembalikan ke auto-generate dengan menghapus konten

### Security Considerations
- Role-based access control
- Session validation
- Prepared statements untuk database queries
- HTML escaping untuk prevent XSS

## Future Improvements
- [ ] Version history untuk tracking perubahan
- [ ] Template library untuk konten standar
- [ ] Approval workflow untuk perubahan konten
- [ ] Rich media support (images, charts)
- [ ] Export to PDF with edited content
