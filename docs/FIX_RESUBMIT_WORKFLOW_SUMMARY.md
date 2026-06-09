# Fix Resubmit Workflow - Implementation Summary

## ✅ MASALAH YANG DIPERBAIKI

### Masalah 1: User Tidak Bisa Resubmit Appointment ke KTT
**Deskripsi**:
- Ketika admin mengembalikan appointment ke user untuk resubmit (send_to_user)
- User resubmit employee data dan admin verify
- TAPI tidak ada cara untuk user mengirim appointment kembali ke KTT
- Appointment "terjebak" dalam status pending tanpa bisa diproses

**User Request**:
- "ketika dikembalikan ke user untuk resubmit, tidak perlu masuk ke bagian request lagi pada reviewer karena sudah pernah di verif"
- "langsung ke halaman assign letter"
- "tombol kirim ke KTT ganti menjadi tombol resubmit to KTT"

### Masalah 2: Appointment Tidak Muncul di Halaman KTT Setelah Resubmit
**Deskripsi**:
- Ketika admin mengirimkan hasil resubmit user ke KTT
- Appointment tidak tampil di halaman KTT
- Penyebabnya: ktt_ttn_status atau ktt_msm_status masih 'rejected', tidak direset ke 'pending'

**User Report**:
- "kemudian perbaiki tampilan karena ketika admin(reviewer) mengirimkan hasil resubmit user, tidak ada yang tampil pada halaman KTT"

---

## 🔧 SOLUSI YANG DIIMPLEMENTASIKAN

### Fix 1: Tambahkan Tombol "Resubmit to KTT" di user_appointments.php

**File**: `user_appointments.php`

**Perubahan**:

1. **Update Query (Lines 68-84)** - Tambah employee data:
```php
$appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.position,
           e.verification_status, e.resubmit_count,  // ← BARU
           p.position_name, p.position_type,
           ...
");
```

2. **Handler Resubmit Action (Lines 23-78)**:
```php
// Handle resubmit to KTT action
if (isset($_GET['action']) && $_GET['action'] == 'resubmit_to_ktt' && isset($_GET['id'])) {
    $appointment_id = intval($_GET['id']);

    // Verify this appointment is eligible for resubmit
    $verify_result = $db->query("
        SELECT a.id, e.verification_status, e.resubmit_count
        FROM appointments a
        JOIN employees e ON a.employee_id = e.id
        WHERE a.id = $appointment_id
        AND a.status = 'pending'
        AND a.admin_approval_action = 'send_to_user'
        AND e.verification_status = 'verified'
        AND e.resubmit_count > 0
        AND e.contractor_company = '{company_name}'
    ");

    if ($verify_result && $verify_result->num_rows > 0) {
        // Get which KTT needs to review
        $appt_details = $db->query("
            SELECT requires_ktt_msm_review, requires_ktt_ttn_review
            FROM appointments WHERE id = $appointment_id
        ")->fetch_assoc();

        // CRITICAL: Reset KTT status back to 'pending'
        $ktt_status_reset = "";
        if ($appt_details['requires_ktt_msm_review'] == 1) {
            $ktt_status_reset = ", ktt_msm_status = 'pending', ktt1_approved_by = NULL, ktt1_approved_date = NULL";
        }
        if ($appt_details['requires_ktt_ttn_review'] == 1) {
            $ktt_status_reset .= ", ktt_ttn_status = 'pending', ktt2_approved_by = NULL, ktt2_approved_date = NULL";
        }

        // Reset admin flags and KTT status
        $update_sql = "UPDATE appointments SET
                      admin_approval_action = NULL,
                      admin_approval_notes = NULL,
                      admin_approved_by = NULL,
                      admin_approved_date = NULL
                      $ktt_status_reset
                      WHERE id = $appointment_id";

        if ($db->query($update_sql)) {
            header("Location: user_appointments.php?success=resubmit");
            exit();
        }
    }
}
```

3. **Tampilan Tombol (Lines 251-271)**:
```php
<?php
// Show "Resubmit to KTT" button if:
// 1. Appointment status = 'pending'
// 2. Admin sent back to user (admin_approval_action = 'send_to_user')
// 3. Employee is verified (verification_status = 'verified')
// 4. Employee has resubmitted data (resubmit_count > 0)
$can_resubmit = (
    $row['status'] == 'pending' &&
    $row['admin_approval_action'] == 'send_to_user' &&
    $row['verification_status'] == 'verified' &&
    $row['resubmit_count'] > 0
);

if ($can_resubmit): ?>
<a href="user_appointments.php?action=resubmit_to_ktt&id=<?php echo $row['id']; ?>"
   class="btn-resubmit-appt"
   onclick="return confirm('Resubmit this appointment letter to KTT for review?')"
   title="Resubmit to KTT">
    <i class="fas fa-paper-plane"></i> Resubmit to KTT
</a>
<?php endif; ?>
```

4. **CSS Styling (Lines 579-600)**:
```css
.btn-resubmit-appt {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: linear-gradient(135deg, #f59e0b 0%, #fb923c 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
}

.btn-resubmit-appt:hover {
    background: linear-gradient(135deg, #d97706 0%, #f97316 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(245, 158, 11, 0.4);
}
```

5. **Alert Success/Error (Lines 105-125)**:
```php
<!-- Success Message -->
<?php if (isset($success_message)): ?>
<div class="alert alert-success" style="...">
    <i class="fas fa-check-circle"></i>
    <div>
        <strong>Success!</strong>
        <p><?php echo htmlspecialchars($success_message); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Error Message -->
<?php if (isset($error_message)): ?>
<div class="alert alert-error" style="...">
    <i class="fas fa-exclamation-circle"></i>
    <div>
        <strong>Error!</strong>
        <p><?php echo htmlspecialchars($error_message); ?></p>
    </div>
</div>
<?php endif; ?>
```

---

## 🔄 WORKFLOW BARU (SETELAH FIX)

### Skenario: User Resubmit After KTT Rejection

```
1. KTT TTN Rejects Appointment
   ↓
2. Admin Reviews → Choose "Send to User"
   - Status: appointment = 'pending'
   - admin_approval_action = 'send_to_user'
   - Status: employee = 'rejected'
   ↓
3. User Resubmit Employee Data
   - User goes to user_resubmit_employee.php
   - Fix data and resubmit
   - Status: employee = 'pending', resubmit_count++
   ↓
4. Admin Verify Employee AGAIN
   - Admin goes to reviewer.php
   - Verify employee data
   - Status: employee = 'verified'
   ↓
5. **BYPASS REQUEST** - User Goes Directly to Assign Letter Page
   - User goes to user_appointments.php (NOT request again)
   - User sees appointment with "Resubmit to KTT" button
   ↓
6. User Click "Resubmit to KTT" Button
   - admin_approval_action → NULL
   - ktt_ttn_status → 'pending' (reset from 'rejected')
   - ktt2_approved_by → NULL
   - ktt2_approved_date → NULL
   ↓
7. KTT TTN Sees Appointment in Their List
   - appointment.status = 'pending' ✓
   - requires_ktt_ttn_review = 1 ✓
   - ktt_ttn_status = 'pending' ✓
   - Appointment now VISIBLE to KTT TTN
   ↓
8. KTT TTN Reviews and Approves
   - ktt_ttn_status → 'approved'
   - Since ktt_msm_status already 'approved'
   - Final status → 'approved'
```

---

## ✅ VERIFICATION TESTING

### Test Case: Appointment ID 40

**Before Resubmit**:
```
Appointment: 001/TT/TTN/02/2026
  Status: pending
  Admin Action: send_to_user
  KTT MSM: approved | KTT TTN: rejected
  Requires MSM: 0 | Requires TTN: 1
  Employee Verification: verified
  Resubmit Count: 1
```

**After User Clicks "Resubmit to KTT"**:
```
Appointment: 001/TT/TTN/02/2026
  Status: pending
  Admin Action: NULL ← Reset!
  KTT MSM: approved | KTT TTN: pending ← Reset!
  Requires MSM: 0 | Requires TTN: 1 ← Preserved!
```

**Visibility Check**:
```
✓ KTT TTN WILL SEE THIS APPOINTMENT
  - Query matches: status='pending' + requires_ktt_ttn_review=1 + ktt_ttn_status='pending'

✓ KTT MSM WILL NOT SEE THIS APPOINTMENT (Correct - already approved)
  - ktt_msm_status='approved' (preserved from before)
```

---

## 📋 KEY FEATURES

1. **Conditional Button Display**:
   - Button only shows when ALL conditions met:
     * status = 'pending'
     * admin_approval_action = 'send_to_user'
     * employee.verification_status = 'verified'
     * employee.resubmit_count > 0

2. **Smart KTT Status Reset**:
   - Only resets the KTT that needs to review (based on requires flags)
   - Preserves the KTT that already approved
   - Example: If requires_ktt_ttn_review=1, only reset ktt_ttn_status to 'pending'

3. **Preserved Admin Routing Decisions**:
   - Admin's requires_ktt_msm_review flag preserved
   - Admin's requires_ktt_ttn_review flag preserved
   - This ensures only rejecting KTT sees the resubmitted appointment

4. **User-Friendly Button Text**:
   - Changed from "Submit to KTT" to **"Resubmit to KTT"**
   - Clear indication this is a resubmission, not initial submission

5. **Security Check**:
   - Verify appointment belongs to user's company
   - Verify all eligibility conditions before processing
   - Prevent unauthorized resubmit attempts

---

## 🎯 BENEFITS

1. **Clear Workflow**: User knows exactly what to do after employee verified
2. **No Confusion**: Don't need to go back to request verification
3. **Efficient**: Direct path from employee verification to KTT review
4. **Selective Visibility**: Only rejecting KTT reviews resubmitted data
5. **Preserved Decisions**: Approved KTT's decision not lost

---

## 📝 NOTES FOR USERS

### For Company Users:
- After your employee is re-verified, go to **Assign Letter page**
- Find your appointment (status: PENDING)
- Click **"Resubmit to KTT"** button
- Appointment will be sent to KTT for review
- You'll see success message confirming resubmit

### For KTT:
- After user resubmits, appointment appears in your pending list again
- Review the corrected data
- Approve or reject as needed
- If you previously approved, you won't see it (only rejecting KTT reviews)

### For Admin:
- After sending appointment back to user
- Wait for user to resubmit employee data
- Verify employee data in reviewer page
- User can then directly resubmit appointment to KTT
- No need for second verification request

---

## 🚀 FILES MODIFIED

1. **user_appointments.php** (Main changes)
   - Added resubmit action handler
   - Added employee data to query
   - Added "Resubmit to KTT" button
   - Added success/error alerts
   - Added CSS styling for button

---

## ✅ READY FOR PRODUCTION

All fixes complete and tested! User can now:
1. ✅ Resubmit employee data after rejection
2. ✅ Bypass second verification request
3. ✅ Directly resubmit appointment to KTT
4. ✅ Appointment visible to correct KTT after resubmit

**Next Steps**:
- Test in production environment
- Monitor user feedback
- Verify all resubmit scenarios
