# 🔧 TROUBLESHOOTING: Notifikasi Email

## Masalah yang Ditemukan dan Diperbaiki

### 1. ❌ Email Username Typo
**Error:** `smtp_username` salah di notifications.php  
**Was:** `agriawanwiranto09@gmail.com` (dengan 09)  
**Fixed:** `agriawanwiranto5@gmail.com` (tanpa 0)

### 2. ❌ SQL Error pada Rejection Notification
**Error:** `Unknown column 'a.rejection_reason' in 'field list'`  
**Cause:** Kolom `rejection_reason` tidak ada di tabel `appointments`  
**Fixed:** Gunakan `a.approval_notes as rejection_reason` sebagai gantinya

### 3. ✅ Added Detailed Logging
Semua fungsi notifikasi sekarang punya logging lengkap untuk debug.

---

## Status Fix

| Issue | Status | Fix |
|-------|--------|-----|
| Email username typo | ✅ FIXED | Line 20 in notifications.php |
| SQL column error | ✅ FIXED | Line 100 in notifications.php |
| Logging for debug | ✅ ADDED | All notification functions |

---

## Testing Instructions

### Test 1: New Employee Notification ✅ WORKING

**Steps:**
1. Login sebagai user company (bukan admin)
2. Klik "Tambah Tenaga Kerja"
3. Isi form dan submit

**Check Error Log:**
```bash
php view_error_log.php
```

**Expected Log Output:**
```
[NOTIFICATION] notifyNewEmployeeAdded called for employee_id=XX, company=PT XXX
[NOTIFICATION] Found 4 admin(s)
[NOTIFICATION] Employee found: [Name] ([Code])
[NOTIFICATION] Email sent to agriawanwiranto5@gmail.com
[NOTIFICATION] Total emails sent: 1 / 4
```

**Check Gmail:**
- Subject: "Tenaga Kerja Baru Perlu Verifikasi - [Company]"
- Should arrive within 1-2 minutes

---

### Test 2: Resubmit Employee ⚠️ NEEDS TESTING

**Steps:**
1. Admin reject employee dengan notes
2. Login sebagai user company
3. Buka employee yang ditolak
4. Klik "Perbaiki Data"
5. Upload perbaikan dan submit

**Check Error Log:**
```bash
php view_error_log.php
```

**Expected Log Output:**
```
[NOTIFICATION] notifyNewEmployeeAdded called for employee_id=XX, company=PT XXX
[NOTIFICATION] Found 4 admin(s)
[NOTIFICATION] Employee found: [Name] ([Code])
[NOTIFICATION] Email sent to agriawanwiranto5@gmail.com
[NOTIFICATION] Total emails sent: 1 / 4
```

**Check Gmail:**
- Subject: "Tenaga Kerja Baru Perlu Verifikasi - [Company]"

---

### Test 3: KTT Reject Appointment ⚠️ NEEDS TESTING

**Steps:**
1. Login sebagai KTT
2. Buka appointment yang pending
3. Pilih "Tolak" dengan alasan
4. Submit rejection

**Check Error Log:**
```bash
php view_error_log.php
```

**Expected Log Output:**
```
[NOTIFICATION] notifyAppointmentRejectedForReview called for appointment_id=XX
[NOTIFICATION] Found 4 admin(s)
[NOTIFICATION] Appointment found: [Number] for [Employee]
[NOTIFICATION] Email sent to agriawanwiranto5@gmail.com
[NOTIFICATION] Total emails sent: 1 / 4
```

**Check Gmail:**
- Subject: "Surat Penunjukan Ditolak - Perlu Review Admin"

---

## Debugging Tools

### 1. View Error Log
```bash
php view_error_log.php
```
Shows last notification logs from PHP error log.

### 2. Debug Notification System
```bash
php debug_notification.php
```
Tests notification system completely (files, config, send email).

### 3. Test Rejection Notification
```bash
php test_rejection_notification.php
```
Tests appointment rejection notification specifically.

### 4. Monitor Real-time
```powershell
Get-Content 'C:/laragon/tmp/php_errors.log' -Wait -Tail 50 | Select-String -Pattern 'NOTIFICATION'
```
Watch notifications in real-time as they happen.

---

## Common Issues & Solutions

### Issue: "No notification logs found"

**Cause:** Notification not being called from web  
**Check:**
1. Verify file integration: `php verify_notifications.php`
2. Check if user has proper role (user/dept, not admin)
3. Check if company_name is set in session

**Debug:**
Add to the PHP file (e.g., user_resubmit_employee.php):
```php
error_log("DEBUG: About to send notification for employee_id=$employee_id, company=$company_name");
```

### Issue: "Employee not found"

**Cause:** employee_id invalid  
**Check:**
```sql
SELECT id, full_name, contractor_company FROM employees WHERE id = [employee_id];
```

### Issue: "No admin contacts found"

**Cause:** No active admins in database  
**Check:**
```sql
SELECT username, email, is_active FROM users WHERE role = 'admin';
```

**Fix:**
```sql
UPDATE users SET is_active = 1 WHERE role = 'admin';
```

### Issue: "Failed to send email"

**Cause:** SMTP error  
**Check:**
1. Email username: `agriawanwiranto5@gmail.com` (bukan 09)
2. Password: 16 karakter tanpa spasi
3. App Password valid (not expired)

**Debug:**
```bash
php debug_email.php
```

---

## File Changes Summary

### Modified Files:

**1. includes/notifications.php**
- ✅ Fixed `smtp_username` typo (line 20)
- ✅ Fixed SQL query for rejection (line 100)
- ✅ Added detailed logging to all functions
- ✅ Fixed email sending to return proper result

**2. user_resubmit_employee.php**
- ✅ Added notification call after successful update

**3. dept_resubmit_employee.php**
- ✅ Added notification call after successful update

---

## Testing Checklist

Before testing on website:

- [ ] Run: `php verify_notifications.php` → All should be ✓
- [ ] Run: `php debug_notification.php` → Should send test email
- [ ] Run: `php test_rejection_notification.php` → Should send test email
- [ ] Check Gmail inbox for 2 test emails

Test on website:

- [ ] Test 1: Add new employee → Check error log → Check Gmail
- [ ] Test 2: Resubmit employee → Check error log → Check Gmail  
- [ ] Test 3: KTT reject appointment → Check error log → Check Gmail

---

## Expected Behavior

### Scenario 1: Add New Employee (Working)
1. User submits new employee
2. **Log:** `[NOTIFICATION] notifyNewEmployeeAdded called...`
3. **Log:** `[NOTIFICATION] Email sent to admin@...`
4. **Gmail:** Email arrives within 1-2 minutes

### Scenario 2: Resubmit Employee (Should Work Now)
1. User resubmits after rejection
2. **Log:** `[NOTIFICATION] notifyNewEmployeeAdded called...`
3. **Log:** `[NOTIFICATION] Email sent to admin@...`
4. **Gmail:** Email arrives within 1-2 minutes

### Scenario 3: KTT Reject (Should Work Now)
1. KTT rejects appointment
2. **Log:** `[NOTIFICATION] notifyAppointmentRejectedForReview called...`
3. **Log:** `[NOTIFICATION] Email sent to admin@...`
4. **Gmail:** Email arrives within 1-2 minutes

---

## If Still Not Working

1. **Check PHP Error Log:**
   ```bash
   php view_error_log.php
   ```
   Look for any [NOTIFICATION ERROR] messages

2. **Monitor in Real-time:**
   ```powershell
   Get-Content 'C:/laragon/tmp/php_errors.log' -Wait -Tail 50
   ```
   Then perform the action on website

3. **Verify Email Sent:**
   Check log for: `[NOTIFICATION] Total emails sent: X / Y`
   - If X = 0, email not sent (check SMTP)
   - If X > 0, email sent (check Gmail inbox/spam)

4. **Check Gmail:**
   - Inbox
   - Spam folder
   - Search: `from:agriawanwiranto5@gmail.com`
   - All Mail
   - Wait 2-5 minutes

5. **Verify Admin Emails:**
   ```sql
   SELECT username, email FROM users WHERE role = 'admin';
   ```
   Make sure email addresses are correct.

---

## Support Files

- `debug_notification.php` - Full system test
- `test_rejection_notification.php` - Test rejection specifically
- `view_error_log.php` - View notification logs
- `verify_notifications.php` - Verify file integration
- `NOTIFICATION_FIX_RESUBMIT.md` - Documentation

---

## Summary

✅ **FIXED:**
1. Email username typo
2. SQL column error
3. Added detailed logging

🧪 **NEEDS TESTING ON WEBSITE:**
1. Resubmit employee scenario
2. KTT reject appointment scenario

📧 **WORKING:**
1. Add new employee (confirmed)
2. Email configuration (confirmed)
3. PHPMailer SMTP (confirmed)

---

**Next Step:** Test scenarios 2 & 3 on website and check error log to see if notifications are triggered.
