# Update Workflow Approval System

## Ringkasan Perubahan

Sistem approval telah diperbarui dengan menambahkan **Admin Review** setelah KTT menolak surat penunjukan tenaga kerja.

### Workflow Lama:
```
User submit → KTT Review → [Approve: Done] OR [Reject: Langsung ke User]
```

### Workflow Baru:
```
User submit → KTT Review → 
    [Approve: Done] 
    OR 
    [Reject: → Admin Review → 
        [Admin: Send to User untuk perbaikan]
        OR
        [Admin: Send back to KTT untuk review ulang]
    ]
```

## Perubahan Database

### 1. Tabel `appointments` - Field Baru:
- `status`: Ditambahkan enum value baru:
  - `rejected_by_ktt`: Status baru ketika KTT menolak surat (belum final rejected)
  - `pending_admin_review`: (Optional) Status untuk tracking admin review
  
- `admin_approved_by` (INT): ID admin yang melakukan review
- `admin_approved_date` (DATETIME): Tanggal admin review
- `admin_approval_action` (ENUM): 'send_to_user' atau 'send_to_ktt'
- `admin_approval_notes` (TEXT): Catatan admin tentang keputusannya
- `rejected_by_ktt_user_id` (INT): ID KTT yang melakukan rejection

### 2. Tabel Baru: `ktt_rejections` (Optional)
Tabel ini untuk tracking historical rejection dari KTT (jika diperlukan untuk audit trail lebih detail).

## File-file yang Dimodifikasi

### 1. **migration_admin_approval.sql** (NEW)
File SQL untuk menambahkan field dan status baru ke database.

### 2. **run_migration.php** (NEW)
Script PHP untuk menjalankan migration secara aman dengan error handling.

### 3. **admin_review_rejection.php** (NEW)
Halaman baru untuk admin melakukan review terhadap surat yang ditolak KTT.

**Features:**
- Menampilkan semua surat dengan status `rejected_by_ktt`
- Admin dapat melihat alasan penolakan dari KTT
- Admin dapat memilih:
  - **Send to User**: Kembalikan ke user untuk perbaikan data
  - **Send to KTT**: Kirim kembali ke KTT untuk review ulang (clear previous KTT approvals)
- Riwayat review admin

### 4. **approval.php** (MODIFIED)
Modifikasi logika rejection KTT:

**Perubahan:**
```php
// BEFORE: Status langsung jadi 'rejected' dan employee status di-update
if ($rejection_count > 0) {
    status = 'rejected'
    // Update employee verification_status = 'rejected'
}

// AFTER: Status jadi 'rejected_by_ktt' dan tunggu admin review
if ($rejection_count > 0) {
    status = 'rejected_by_ktt'
    // Tidak update employee status (tunggu admin decision)
}
```

### 5. **user_resubmit_employee.php** (MODIFIED)
Update untuk menampilkan rejection notes dari admin:

**Perubahan:**
- Menambahkan field `admin_rejection_notes`, `admin_action`, `admin_reviewer_name` dalam query
- Menampilkan rejection dari Admin (jika admin mengembalikan ke user)
- Menampilkan rejection dari Admin Verifikasi awal
- Menampilkan rejection dari KTT

### 6. **includes/header.php** (MODIFIED)
Menambahkan menu link untuk Admin Review Rejection:

```php
<?php if ($_SESSION['role'] == 'admin'): ?>
<li>
    <a href="admin_review_rejection.php">
        <i class="fas fa-tasks"></i> Review Penolakan KTT
    </a>
</li>
<?php endif; ?>
```

## Cara Testing Workflow

### Prerequisites:
1. Database sudah di-migrate dengan menjalankan `run_migration.php`
2. Ada minimal 3 users dengan role:
   - 1 User (Contractor)
   - 1 Admin
   - 2 KTT

### Testing Steps:

#### Scenario 1: KTT Reject → Admin Send to User

1. **Login sebagai User (Contractor)**
   - Submit data tenaga kerja baru
   - Submit surat penunjukan

2. **Login sebagai Admin**
   - Verifikasi data tenaga kerja
   - Approve surat untuk KTT review

3. **Login sebagai KTT 1**
   - Buka halaman "Persetujuan KTT"
   - Pilih surat penunjukan
   - Klik **Reject** dengan alasan: "Sertifikat kurang lengkap"

4. **Login sebagai KTT 2**
   - Review surat yang sama
   - Klik **Reject** dengan alasan: "Data tidak valid"
   - Status surat sekarang: `rejected_by_ktt`

5. **Login sebagai Admin**
   - Buka menu **"Review Penolakan KTT"**
   - Lihat surat yang ditolak oleh KTT
   - Baca alasan rejection dari KTT
   - Pilih action: **"Kembalikan ke User untuk Perbaikan"**
   - Masukkan catatan: "Mohon lengkapi sertifikat yang kurang dan perbaiki data sesuai saran KTT"
   - Submit

6. **Login sebagai User (Contractor)**
   - Lihat status employee menjadi **"Rejected"**
   - Buka halaman **"Unggah Perbaikan"**
   - Lihat alasan penolakan dari:
     - Admin (Review KTT Rejection)
     - KTT 1
     - KTT 2
   - Upload perbaikan data
   - Submit ulang

#### Scenario 2: KTT Reject → Admin Send back to KTT

1. **Ikuti langkah 1-4 dari Scenario 1**

2. **Login sebagai Admin**
   - Buka menu **"Review Penolakan KTT"**
   - Lihat surat yang ditolak
   - Pilih action: **"Kirim ke KTT untuk Review Ulang"**
   - Masukkan catatan: "Data sudah valid, mohon KTT review ulang. Sertifikat sudah dilengkapi oleh user sebelumnya."
   - Submit

3. **System Action:**
   - Status surat berubah kembali menjadi `pending`
   - Previous KTT approvals dihapus
   - Surat muncul lagi di queue KTT untuk review ulang

4. **Login sebagai KTT 1 atau KTT 2**
   - Lihat surat muncul lagi di "Menunggu Persetujuan Anda"
   - Review ulang dan approve/reject

## Monitoring dan Logging

### Query untuk Monitoring:

```sql
-- Lihat semua surat yang menunggu admin review
SELECT a.*, e.full_name, e.employee_code
FROM appointments a
JOIN employees e ON a.employee_id = e.id
WHERE a.status = 'rejected_by_ktt'
ORDER BY a.updated_at DESC;

-- Lihat history admin review
SELECT a.appointment_number, e.full_name, 
       a.admin_approval_action, a.admin_approval_notes,
       admin.full_name as admin_name, a.admin_approved_date
FROM appointments a
JOIN employees e ON a.employee_id = e.id
JOIN users admin ON a.admin_approved_by = admin.id
WHERE a.admin_approved_by IS NOT NULL
ORDER BY a.admin_approved_date DESC;

-- Lihat KTT rejections
SELECT a.appointment_number, e.full_name,
       ka.action, ka.approval_notes, 
       ktt.full_name as ktt_name, ka.approval_date
FROM appointments a
JOIN employees e ON a.employee_id = e.id
JOIN ktt_approvals ka ON a.id = ka.appointment_id
JOIN users ktt ON ka.ktt_user_id = ktt.id
WHERE ka.action = 'reject'
ORDER BY ka.approval_date DESC;
```

## Troubleshooting

### Issue 1: Menu "Review Penolakan KTT" tidak muncul
**Solution:** Pastikan sudah login sebagai Admin.

### Issue 2: Status tidak berubah setelah admin action
**Solution:** 
- Check query di `admin_review_rejection.php`
- Pastikan WHERE clause menggunakan `status = 'rejected_by_ktt'`

### Issue 3: KTT tidak bisa review ulang setelah admin send back to KTT
**Solution:**
- Pastikan query `DELETE FROM ktt_approvals WHERE appointment_id = $id` berhasil dijalankan
- Check apakah status sudah berubah kembali ke `pending`

### Issue 4: User tidak melihat rejection dari admin
**Solution:**
- Check query di `user_resubmit_employee.php`
- Pastikan field `admin_rejection_notes` dan `admin_action` sudah di-select dalam query

## Catatan Penting

1. **Backward Compatibility**: 
   - Surat yang sudah diproses sebelum update ini tetap dengan status lama (`rejected`)
   - Tidak ada perubahan untuk surat yang sudah approved/rejected sebelum migration

2. **Data Consistency**:
   - Pastikan jalankan migration SEBELUM deploy file PHP yang baru
   - Migration bersifat idempotent (aman dijalankan berkali-kali)

3. **Role Permissions**:
   - Hanya Admin yang bisa akses `admin_review_rejection.php`
   - KTT tetap bisa reject seperti biasa
   - User bisa resubmit seperti biasa

## Kontributor

- Migration & Database: √ Completed
- Admin Review Page: √ Completed  
- KTT Workflow Update: √ Completed
- User Resubmit Update: √ Completed
- Testing: In Progress

## Changelog

### Version 2.0 (January 2026)
- ✅ Added admin review layer for KTT rejections
- ✅ New status: `rejected_by_ktt`
- ✅ Admin can send back to user or KTT
- ✅ Enhanced rejection tracking and history
- ✅ Updated user resubmit page to show all rejection sources

### Version 1.0 (Original)
- Basic KTT dual approval system
- Direct rejection to user
