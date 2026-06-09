# Implementasi Sistem Resubmit - Dokumentasi Lengkap

## Alur Sistem Resubmit

### 1. New Request (User/Dept)
- User/Dept membuat new request employee
- Status: `pending` (menunggu admin review)
- **TIDAK** ada resubmit count di tahap ini

### 2. Admin Review Pertama
**File**: `admin_employees.php` atau `employees.php`

**Jika Admin REJECT:**
- Status employee: `rejected`
- User/Dept harus resubmit via file: `user_resubmit_employee.php` atau `dept_resubmit_employee.php`
- **PENTING**: Resubmit di tahap ini **TIDAK** mengubah `resubmit_count` di appointments
- **PENTING**: Resubmit di tahap ini **TIDAK** membuat status "Resubmitted" di halaman KTT
- Alasan: Appointment letter belum dibuat, masih di tahap employee verification

**Jika Admin ACCEPT:**
- Status employee: `verified`
- Admin bisa create appointment letter
- Status appointment: `pending` (menunggu KTT review)

### 3. KTT Review Pertama
**File**: `approval.php`

**Jika KTT REJECT:**
- Status appointment: `rejected_by_ktt`
- Field `last_rejected_by_ktt`: 'msm' atau 'ttn'
- Field `rejected_by_ktt_user_id`: ID user KTT yang reject
- Masuk ke halaman Admin Review Rejection

**Jika Kedua KTT APPROVE:**
- Status appointment: `approved`
- Admin bisa print surat
- Selesai

### 4. Admin Review KTT Rejection
**File**: `admin_review_rejection.php` (sudah diupdate)

**Admin punya 2 pilihan:**

#### A. Send to KTT (Data sudah benar)
- Status appointment: `pending`
- Reset status KTT yang reject ke `pending`
- Field `requires_ktt_msm_review` atau `requires_ktt_ttn_review`: 1 (sesuai yang reject)
- KTT yang sudah approve **TIDAK** perlu review lagi
- **TIDAK** increment resubmit_count

#### B. Send to User (Data salah/kurang)
- Status appointment: `rejected`
- Status employee: `rejected`
- User/Dept harus resubmit
- **Increment resubmit_count di appointments**
- Field `resubmit_reason`: Kombinasi notes dari KTT + Admin

### 5. User/Dept Resubmit (Karena KTT Reject)
**File**: `user_resubmit_employee.php` atau `dept_resubmit_employee.php`

**Action:**
- Update data employee
- Update data certifications (jika perlu)
- Update appointment dengan data baru
- Status appointment: `pending` (untuk admin review lagi)
- **Field `resubmit_count` sudah di-increment saat admin send to user**
- Masuk ke Admin Review lagi

### 6. Admin Review Resubmitted Data
**File**: `admin_employees.php` atau `employees.php`

**Jika Admin ACCEPT:**
- Status employee: `verified`
- Status appointment: `pending` (untuk KTT review)
- **HANYA KTT yang reject sebelumnya** yang perlu review
- Field `requires_ktt_msm_review` atau `requires_ktt_ttn_review`: tetap 1

### 7. KTT Review Resubmitted Data
**File**: `approval.php`

**Tampilan untuk KTT:**
- Card appointment dengan label **"Resubmitted"**
- KTT yang sudah approve **TIDAK** melihat card ini
- Hanya KTT yang reject yang bisa review

**Jika KTT APPROVE:**
- Status appointment: `approved` (jika kedua KTT sudah approve)
- Admin bisa print surat
- Selesai

**Jika KTT REJECT lagi:**
- Kembali ke step 4 (Admin Review KTT Rejection)
- Resubmit count akan bertambah jika admin send to user lagi

---

## Database Schema Changes

### Tabel `appointments`

**Kolom yang HARUS ada:**

```sql
-- Jika kolom belum ada, jalankan migration ini:
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS resubmit_count INT DEFAULT 0 COMMENT 'Jumlah kali employee resubmit karena KTT reject';

ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS requires_ktt_msm_review TINYINT(1) DEFAULT 0 COMMENT 'Apakah perlu direview KTT MSM';

ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS requires_ktt_ttn_review TINYINT(1) DEFAULT 0 COMMENT 'Apakah perlu direview KTT TTN';

ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS last_rejected_by_ktt VARCHAR(10) NULL COMMENT 'msm atau ttn, KTT yang terakhir reject';

ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS rejected_by_ktt_user_id INT NULL COMMENT 'User ID KTT yang reject';

ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS admin_approval_action VARCHAR(20) NULL COMMENT 'send_to_user atau send_to_ktt';

ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS admin_approval_notes TEXT NULL COMMENT 'Notes dari admin saat review KTT rejection';

ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS admin_approved_by INT NULL COMMENT 'User ID admin yang review';

ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS admin_approved_date DATETIME NULL COMMENT 'Tanggal admin review';

ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS resubmit_reason TEXT NULL COMMENT 'Alasan resubmit dari KTT + Admin';
```

---

## File Changes Required

### 1. File: `admin_review_rejection.php` ✅ SUDAH DIUPDATE

**Perubahan:**
- ✅ Menampilkan resubmit count dengan badge yang beranimasi
- ✅ Menampilkan status KTT MSM dan KTT TTN
- ✅ Query sudah include `COALESCE(a.resubmit_count, 0)`

**Action saat Send to User:**
- ⚠️ **BELUM** increment resubmit_count
- Perlu ditambahkan:

```php
// Di dalam blok send_to_user:
$update_sql = "UPDATE appointments SET
    status = 'rejected',
    admin_approved_by = $current_admin_id,
    admin_approved_date = NOW(),
    admin_approval_action = 'send_to_user',
    admin_approval_notes = '$admin_notes',
    requires_ktt_msm_review = $requires_ktt_msm,
    requires_ktt_ttn_review = $requires_ktt_ttn,
    resubmit_reason = '{$db->escapeString($combined_notes)}',
    resubmit_count = resubmit_count + 1  -- TAMBAHKAN INI
    WHERE id = $id AND status = 'rejected_by_ktt'";
```

### 2. File: `approval.php` (KTT)

**Perubahan yang PERLU dilakukan:**

#### A. Query Filter untuk KTT MSM
```php
// Filter appointments untuk KTT MSM
// Hanya tampilkan jika:
// 1. status = 'pending' DAN
// 2. (ktt_msm_status = 'pending' ATAU requires_ktt_msm_review = 1)

$ktt_msm_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           COALESCE(a.resubmit_count, 0) as resubmit_count,
           CASE 
               WHEN a.resubmit_count > 0 THEN 'resubmitted'
               ELSE 'new'
           END as submission_type
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status = 'pending'
    AND (a.ktt_msm_status = 'pending' OR a.requires_ktt_msm_review = 1)
    AND a.ktt1_approved_by IS NULL
    ORDER BY a.created_at DESC
");
```

#### B. Query Filter untuk KTT TTN
```php
// Filter appointments untuk KTT TTN
$ktt_ttn_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           COALESCE(a.resubmit_count, 0) as resubmit_count,
           CASE 
               WHEN a.resubmit_count > 0 THEN 'resubmitted'
               ELSE 'new'
           END as submission_type
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status = 'pending'
    AND (a.ktt_ttn_status = 'pending' OR a.requires_ktt_ttn_review = 1)
    AND a.ktt2_approved_by IS NULL
    ORDER BY a.created_at DESC
");
```

#### C. Tampilan Card dengan Label Resubmitted
```php
<?php while ($row = $ktt_msm_appointments->fetch_assoc()): ?>
<div class="approval-card">
    <div class="card-header">
        <h4>
            <?php echo htmlspecialchars($row['employee_name']); ?>
            
            <?php if ($row['submission_type'] == 'resubmitted'): ?>
                <span class="resubmit-label">
                    <i class="fas fa-redo"></i> Resubmitted (#<?php echo $row['resubmit_count']; ?>)
                </span>
            <?php else: ?>
                <span class="new-label">
                    <i class="fas fa-star"></i> New Request
                </span>
            <?php endif; ?>
        </h4>
    </div>
    <!-- rest of card content -->
</div>
<?php endwhile; ?>
```

#### D. Styling untuk Label Resubmitted
```css
.resubmit-label {
    display: inline-block;
    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: bold;
    margin-left: 10px;
    animation: pulse-resubmit 2s ease-in-out infinite;
}

.new-label {
    display: inline-block;
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: bold;
    margin-left: 10px;
}

@keyframes pulse-resubmit {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 2px 4px rgba(238, 90, 111, 0.3);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(238, 90, 111, 0.5);
    }
}
```

#### E. Action saat KTT Reject
```php
// Saat KTT reject, set flags untuk admin review
if ($action == 'reject') {
    $rejected_ktt = ($_SESSION['ktt_role'] == 'ktt_msm') ? 'msm' : 'ttn';
    
    $db->query("UPDATE appointments SET
        status = 'rejected_by_ktt',
        ktt_{$rejected_ktt}_status = 'rejected',
        last_rejected_by_ktt = '$rejected_ktt',
        rejected_by_ktt_user_id = {$_SESSION['user_id']}
        WHERE id = $appointment_id");
    
    // Insert ke ktt_approvals
    $db->query("INSERT INTO ktt_approvals (...) VALUES (...)");
}
```

#### F. Action saat KTT Approve (Resubmitted Data)
```php
// Saat KTT approve data resubmit, reset flags
if ($action == 'approve') {
    $ktt_type = ($_SESSION['ktt_role'] == 'ktt_msm') ? 'msm' : 'ttn';
    
    // Reset flag review
    if ($ktt_type == 'msm') {
        $db->query("UPDATE appointments SET
            requires_ktt_msm_review = 0,
            ktt_msm_status = 'approved',
            ktt1_approved_by = {$_SESSION['user_id']},
            ktt1_approved_date = NOW()
            WHERE id = $appointment_id");
    } else {
        $db->query("UPDATE appointments SET
            requires_ktt_ttn_review = 0,
            ktt_ttn_status = 'approved',
            ktt2_approved_by = {$_SESSION['user_id']},
            ktt2_approved_date = NOW()
            WHERE id = $appointment_id");
    }
    
    // Cek apakah kedua KTT sudah approve
    $check = $db->query("SELECT ktt_msm_status, ktt_ttn_status 
                        FROM appointments WHERE id = $appointment_id")->fetch_assoc();
    
    if ($check['ktt_msm_status'] == 'approved' && $check['ktt_ttn_status'] == 'approved') {
        // Kedua KTT sudah approve, set status final
        $db->query("UPDATE appointments SET
            status = 'approved',
            last_rejected_by_ktt = NULL,
            rejected_by_ktt_user_id = NULL,
            requires_ktt_msm_review = 0,
            requires_ktt_ttn_review = 0
            WHERE id = $appointment_id");
    }
}
```

### 3. File: `user_resubmit_employee.php` & `dept_resubmit_employee.php`

**Perubahan yang PERLU dilakukan:**

#### Cek apakah appointment rejected by KTT
```php
// Di awal file, setelah get employee data
$appointment = $db->query("
    SELECT a.*, a.resubmit_count, a.resubmit_reason,
           a.ktt_msm_status, a.ktt_ttn_status,
           a.requires_ktt_msm_review, a.requires_ktt_ttn_review
    FROM appointments a
    WHERE a.employee_id = $employee_id
    AND a.status IN ('rejected', 'rejected_by_ktt')
    ORDER BY a.id DESC
    LIMIT 1
")->fetch_assoc();

$is_ktt_rejection = ($appointment && $appointment['status'] == 'rejected_by_ktt');
```

#### Tampilkan info resubmit jika dari KTT rejection
```php
<?php if ($is_ktt_rejection): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>Resubmission Required (Rejection from KTT)</strong>
        <p>This is resubmission #<?php echo $appointment['resubmit_count']; ?></p>
        <p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($appointment['resubmit_reason'])); ?></p>
    </div>
</div>
<?php endif; ?>
```

#### Saat submit resubmit (PENTING)
```php
// Setelah update employee data
if ($db->query($update_employee_sql)) {
    // Update appointment
    // JANGAN increment resubmit_count di sini, sudah di-increment di admin_review_rejection.php
    
    $update_appointment_sql = "UPDATE appointments SET
        status = 'pending',  -- Kembali ke admin review
        position_id = $position_id,
        valid_from = '$valid_from',
        valid_until = '$valid_until',
        work_location = '$work_location'
        -- TIDAK increment resubmit_count di sini
        WHERE id = {$appointment['id']}";
    
    $db->query($update_appointment_sql);
    
    // Update certifications jika ada perubahan
    // ...
    
    $message = 'Employee data successfully resubmitted. Waiting for admin review.';
}
```

### 4. File: `admin_employees.php` atau `employees.php`

**Perubahan yang PERLU dilakukan:**

#### Saat admin verify employee yang sudah punya appointment
```php
// Saat admin accept employee verification
if ($action == 'verify') {
    // Check if employee has appointment
    $appointment = $db->query("
        SELECT id, status, requires_ktt_msm_review, requires_ktt_ttn_review
        FROM appointments 
        WHERE employee_id = $employee_id
        ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();
    
    if ($appointment && $appointment['status'] == 'pending') {
        // Employee sudah punya appointment, ini adalah resubmit review
        
        // Check apakah ini dari KTT rejection
        if ($appointment['requires_ktt_msm_review'] || $appointment['requires_ktt_ttn_review']) {
            // Ini resubmit dari KTT rejection
            // Status tetap 'pending', akan masuk ke KTT yang reject saja
            
            // TIDAK ubah status appointment
            // TIDAK reset requires_ktt flags
            
            $message = 'Employee verified. Appointment sent to KTT for re-review.';
        } else {
            // Ini new appointment, kirim ke kedua KTT
            $message = 'Employee verified. Appointment sent to KTT for review.';
        }
    } else {
        // Employee baru atau belum punya appointment
        $message = 'Employee verified. You can now create appointment letter.';
    }
    
    // Update employee status
    $db->query("UPDATE employees SET
        verification_status = 'verified',
        verified_by = {$_SESSION['user_id']},
        verified_date = NOW()
        WHERE id = $employee_id");
}
```

---

## Summary Resubmit Flow

```
NEW REQUEST
    ↓
[ADMIN REVIEW] ← (resubmit jika reject, TIDAK count di appointment)
    ↓ accept
[CREATE APPOINTMENT]
    ↓
[KTT REVIEW]
    ↓ reject
[ADMIN REVIEW REJECTION]
    ↓
    ├─→ Send to KTT → [KTT REVIEW] (repeat)
    │                      ↓ reject again
    │                  [ADMIN REVIEW REJECTION] (loop)
    │
    └─→ Send to User (INCREMENT resubmit_count)
            ↓
        [USER RESUBMIT]
            ↓
        [ADMIN REVIEW]
            ↓ accept
        [KTT REVIEW] (hanya yang reject sebelumnya)
            ↓
            ├─→ approve → SELESAI
            └─→ reject → [ADMIN REVIEW REJECTION] (repeat flow)
```

---

## Testing Checklist

### ✅ Admin Review Rejection Page
- [ ] Resubmit count tampil dengan badge
- [ ] Badge beranimasi pulse
- [ ] Status KTT MSM dan TTN tampil
- [ ] Notes required saat reject
- [ ] Notes optional saat accept

### ⚠️ Database Migration
- [ ] Jalankan ALTER TABLE commands
- [ ] Verifikasi semua kolom sudah ada
- [ ] Set default values untuk data lama

### ⚠️ KTT Approval Page
- [ ] Filter query hanya tampilkan appointment yang perlu direview
- [ ] Label "Resubmitted" tampil dengan count
- [ ] KTT yang sudah approve tidak lihat appointment resubmit
- [ ] Saat approve, reset flags dengan benar

### ⚠️ User/Dept Resubmit Page
- [ ] Tampilkan info resubmit count
- [ ] Tampilkan reason dari KTT + Admin
- [ ] TIDAK increment resubmit_count saat submit
- [ ] Status appointment kembali ke 'pending'

### ⚠️ Admin Employee Review
- [ ] Deteksi apakah employee ada appointment
- [ ] Handle case resubmit dari KTT rejection
- [ ] TIDAK reset requires_ktt flags prematur

---

## Notes Penting

1. **Resubmit Count hanya increment di `admin_review_rejection.php`** saat admin send to user
2. **Status "rejected_by_ktt" adalah temporary status** untuk admin review
3. **Requires_ktt flags digunakan untuk filter KTT** mana yang perlu review
4. **KTT yang sudah approve tidak perlu review lagi** saat resubmit
5. **Label "Resubmitted" di KTT page** untuk membedakan new vs resubmit
6. **Resubmit dari admin reject (sebelum KTT)** tidak count sebagai resubmit

---

## File Updates Priority

1. ✅ **HIGH**: `admin_review_rejection.php` - Sudah diupdate
2. ⚠️ **HIGH**: Database migration - Perlu dijalankan
3. ⚠️ **CRITICAL**: `admin_review_rejection.php` - Tambah increment resubmit_count
4. ⚠️ **CRITICAL**: `approval.php` - Filter query dan tampilan resubmitted
5. ⚠️ **MEDIUM**: `user_resubmit_employee.php` & `dept_resubmit_employee.php` - Info display
6. ⚠️ **MEDIUM**: `admin_employees.php` - Handle resubmit verification

---

*Dokumentasi dibuat: <?php echo date('d F Y H:i'); ?>*
