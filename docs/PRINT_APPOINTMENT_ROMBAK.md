# Dokumentasi Perombakan Print Appointment dengan CKEditor

## 🔄 Perubahan Besar

Sistem print appointment telah **dirombak total dari 0** dengan mengintegrasikan **CKEditor 5 Document Editor** langsung ke dalam halaman print_appointment.php.

## ✨ Fitur Baru

### 1. **Mode Toggle: View & Edit**
- **View Mode** (Default): Menampilkan surat siap cetak dengan format profesional
- **Edit Mode**: Editor CKEditor dengan toolbar lengkap untuk mengedit konten
- Toggle dengan satu tombol antara kedua mode

### 2. **Integrated Editor**
- CKEditor 5 Document Editor terintegrasi langsung
- Real-time preview saat beralih ke View Mode
- Toolbar lengkap dengan semua fitur formatting

### 3. **Auto-save & Database Integration**
- Konten yang diedit disimpan ke database
- Auto-load konten yang sudah diedit sebelumnya
- Fallback ke template otomatis jika belum diedit

### 4. **Print-Ready**
- Mode cetak yang optimal untuk A4
- Fixed header dan footer saat print
- Semua styling template tetap terjaga

## 📁 File Changes

### File yang Dirombak:
- **print_appointment.php** - Dirombak total dengan CKEditor integrated
  - Mode toggle view/edit
  - Editor CKEditor 5 Document Editor
  - Responsive design
  - Print-optimized layout

### File yang Dihapus:
- **edit_print_appointment.php** - Tidak diperlukan lagi (fungsi digabung ke print_appointment.php)

### File yang Dibackup:
- **print_appointment_backup.php** - Backup file lama sebelum perombakan

### File yang Dimodifikasi:
- **appointments.php** - Hapus tombol edit terpisah (edit sudah ada di dalam print page)
- **approval.php** - Hapus tombol edit terpisah di modal

## 🎯 Cara Penggunaan

### Untuk Admin & KTT:

1. **Buka Surat Penunjukan**:
   - Klik tombol "Cetak" pada appointment
   - Halaman akan terbuka dengan view mode (surat siap cetak)

2. **Edit Konten** (opsional):
   - Klik tombol **"Edit Surat"** di toolbar atas
   - Editor CKEditor akan muncul
   - Edit konten sesuai kebutuhan
   - Klik **"Simpan"** untuk menyimpan perubahan

3. **Preview Hasil Edit**:
   - Klik **"Lihat Preview"** (tombol yang sama dengan Edit)
   - Sistem akan menampilkan hasil edit dalam format surat

4. **Cetak**:
   - Klik tombol **"Cetak"**
   - Atau gunakan Ctrl+P / Cmd+P
   - Surat akan dicetak dengan format profesional

### Untuk User Biasa:
- Hanya bisa melihat dan mencetak
- Tidak ada tombol Edit (role-based access)

## 🛠️ Technical Details

### Template System (Tetap Tersedia)
Semua template auto-generate berdasarkan competency_type tetap berfungsi:

1. **Pengawas Operasional** (TT-MGT-FRS-008A)
   - Template khusus dengan 9 responsibilities
   - Reference ke KEPMEN ESDM

2. **Pengawas Teknis** (TT-MGT-FRS-008B)
   - Template dengan 5 responsibilities
   - Customization untuk mekanik/elektrik

3. **Tenaga Teknis** (TT-MGT-FRS-008C)
   - Sub-template untuk:
     - Juru Las/Welder
     - Operator Alat Berat
     - Default Tenaga Teknis

### CKEditor Configuration
```javascript
toolbar: {
    items: [
        'heading', '|',
        'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', '|',
        'bold', 'italic', 'underline', 'strikethrough', '|',
        'alignment', '|',
        'numberedList', 'bulletedList', '|',
        'indent', 'outdent', '|',
        'link', 'blockQuote', 'insertTable', '|',
        'undo', 'redo'
    ]
}
```

### Database Schema
Konten disimpan di kolom `appointments.letter_content`:
- **NULL/Empty**: Sistem generate otomatis dari template
- **Filled**: Gunakan konten custom dari editor

### Security
- ✅ Role-based access control (admin & ktt only)
- ✅ Session validation
- ✅ SQL injection protection (prepared statements)
- ✅ XSS protection (htmlspecialchars)

## 📊 Perbandingan Before vs After

### Before (File Terpisah):
```
appointments.php → tombol edit → edit_print_appointment.php (editor)
                → tombol cetak → print_appointment.php (print only)
```

### After (Integrated):
```
appointments.php → tombol cetak → print_appointment.php (view + edit + print)
                                   └─ Toggle: View ⟺ Edit
```

## 🎨 UI/UX Improvements

### Toolbar
- Sticky toolbar tetap di atas saat scroll
- Tombol dengan icon FontAwesome
- Color-coded buttons:
  - Orange: Edit
  - Green: Save
  - Purple: Print
  - Gray: Close

### Responsive
- Desktop: Layout optimal untuk editing
- Mobile: Stack buttons, responsive container
- Print: A4 optimized dengan fixed header/footer

### Print Layout
- Fixed header dengan logo MSM & Archi
- Data table dengan border profesional
- Content area dengan typography Times New Roman
- Signature table dengan digital signatures
- Footer dengan metadata dokumen

## ✅ Checklist Fitur

- ✅ **Template auto-generate berfungsi** untuk semua jenis kompetensi
- ✅ **CKEditor terintegrasi** dengan toolbar lengkap
- ✅ **Mode toggle** view/edit dengan satu tombol
- ✅ **Real-time preview** saat edit
- ✅ **Database persistence** untuk konten yang diedit
- ✅ **Print optimization** dengan fixed header/footer
- ✅ **Role-based access** untuk editing
- ✅ **Responsive design** untuk semua device
- ✅ **Digital signatures** untuk KTT approval
- ✅ **Alert notifications** untuk feedback user

## 🚀 Performance

### Optimizations:
- CKEditor loaded via CDN (40.2.0)
- Lazy initialization hanya untuk admin/ktt
- CSS optimized untuk print media
- Minimal JavaScript untuk non-editor users

### Loading Time:
- View Mode: Instant (no editor load)
- Edit Mode: ~1-2s (CKEditor initialization)
- Print: Instant (browser native)

## 📝 Migration Notes

### Untuk User yang Sudah Ada:
- Surat yang sudah ada tetap bisa dibuka
- Konten yang sudah diedit sebelumnya tetap tersimpan
- Tidak perlu migration script

### Compatibility:
- ✅ Chrome/Edge (recommended)
- ✅ Firefox
- ✅ Safari
- ⚠️ IE11 (not tested, not recommended)

## 🔮 Future Enhancements

Potential improvements:
- [ ] Version history untuk tracking perubahan
- [ ] Template library untuk konten reusable
- [ ] Collaborative editing (multiple users)
- [ ] PDF export dengan content yang diedit
- [ ] Image upload support
- [ ] Spell checker Indonesia
- [ ] Comments & annotations

## 📞 Support

Jika ada masalah:
1. Clear browser cache
2. Check session (login ulang)
3. Verify role permissions
4. Check browser console untuk errors
5. Restore dari backup jika diperlukan

---

**Backup File**: `print_appointment_backup.php`  
**Version**: 2.0 (CKEditor Integrated)  
**Date**: February 3, 2026  
**Author**: System Modernization
