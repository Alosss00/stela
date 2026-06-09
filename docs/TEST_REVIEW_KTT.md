# Testing: Fungsi Review KTT di Kolom AKSI

## ✅ Perubahan yang Telah Dilakukan:

### 1. **Status yang Ditangani**
- Status `rejected_by_ktt` (bukan `rejected`)
- Status `rejected` adalah untuk surat yang sudah final ditolak admin

### 2. **Tampilan di Kolom Status**
- Surat dengan status `rejected_by_ktt` akan menampilkan badge: **⚠ PERLU REVIEW**

### 3. **Tampilan di Kolom AKSI**
Untuk status `rejected_by_ktt`, akan muncul:
- **Info box merah**: Menampilkan alasan penolakan (50 karakter pertama)
- **Tombol Accept (✓)**: Hijau - Kirim ke KTT
- **Tombol Reject (✗)**: Merah - Kembalikan ke User

### 4. **Modal Review Admin**
Saat klik tombol Accept/Reject:
- Modal popup dengan form catatan admin
- Menampilkan info karyawan, nomor surat, dan tindakan
- Required field untuk catatan admin

---

## 🧪 Cara Testing:

### Step 1: Cek Data
Jalankan query untuk melihat data `rejected_by_ktt`:
```sql
SELECT id, appointment_number, status, employee_id 
FROM appointments 
WHERE status = 'rejected_by_ktt';
```

### Step 2: Jika Tidak Ada Data
Buat data test dengan query:
```sql
-- Cari appointment yang bisa diubah jadi rejected_by_ktt
UPDATE appointments 
SET status = 'rejected_by_ktt',
    ktt_rejection_notes = 'Test penolakan: Data tidak lengkap'
WHERE id = [ID_APPOINTMENT]
LIMIT 1;
```

### Step 3: Refresh Halaman
1. Buka: `http://localhost/appointments.php`
2. **Hard Refresh**: `Ctrl + Shift + R` atau `Ctrl + F5`
3. Cari surat dengan badge **PERLU REVIEW**

### Step 4: Test Tombol
1. Klik tombol **✓ (Accept)**
   - Modal harus muncul
   - Judul: "Accept - Kirim ke KTT"
   - Warna hijau
   
2. Klik tombol **✗ (Reject)**
   - Modal harus muncul
   - Judul: "Reject - Kembalikan ke User"
   - Warna merah

### Step 5: Test Submit
1. Isi catatan admin
2. Submit form
3. Verify status berubah:
   - Accept → status jadi `pending`
   - Reject → status jadi `rejected`

---

## 🐛 Troubleshooting:

### Tombol Tidak Muncul?
**Penyebab**: Tidak ada data dengan status `rejected_by_ktt`
**Solusi**: 
1. Cek database dengan query di Step 1
2. Buat data test dengan query di Step 2

### CSS Tidak Muncul?
**Penyebab**: Browser cache
**Solusi**: 
- Hard refresh: `Ctrl + Shift + R`
- Clear cache browser
- Buka dalam incognito mode

### Modal Tidak Muncul?
**Penyebab**: JavaScript error
**Solusi**: 
1. Buka Developer Tools (F12)
2. Cek Console untuk error
3. Pastikan fungsi `showAdminReviewModal()` ada

### Error Submit?
**Penyebab**: POST handler belum lengkap
**Solusi**: 
- Cek ada action `admin_review` di PHP handler (sudah ada di line 67-118)

---

## 📍 Lokasi File yang Diubah:

- `appointments.php` Line 195: Status class mapping
- `appointments.php` Line 424-431: Kolom Status display
- `appointments.php` Line 518-547: Kolom AKSI untuk rejected_by_ktt
- `appointments.php` Line 806-839: Modal Review Admin
- `appointments.php` Line 1607-1687: CSS untuk review buttons
- `appointments.php` Line 1928-1960: JavaScript function

---

## ✨ Expected Result:

Ketika ada surat dengan status `rejected_by_ktt`:
1. ✅ Badge status menampilkan: **⚠ PERLU REVIEW**
2. ✅ Kolom AKSI menampilkan info penolakan + 2 tombol
3. ✅ Klik tombol → modal muncul
4. ✅ Submit → status berubah sesuai aksi
5. ✅ Workflow lebih cepat tanpa pindah tab

---

**Created**: 2026-01-31
**File**: appointments.php
**Version**: v2.0 - Simplified Admin Workflow
