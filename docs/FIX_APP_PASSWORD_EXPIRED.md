# 🔴 APP PASSWORD TIDAK VALID

## Masalah yang Teridentifikasi:

```
SMTP Error: Could not authenticate
```

**Root Cause:** App Password `msoxtvqbgyptkonl` sudah **tidak valid**

Kemungkinan penyebab:
1. ⏰ App Password expired
2. 🗑️ App Password di-revoke dari Gmail
3. 🔒 Gmail security policy berubah
4. ❌ Password salah/typo

---

## ✅ SOLUSI: Generate App Password Baru

### Langkah 1: Buka Gmail App Passwords

**KLIK LINK INI:**  
👉 https://myaccount.google.com/apppasswords

**ATAU manual:**
1. Buka: https://myaccount.google.com/security
2. Scroll ke "2-Step Verification"
3. Klik "App passwords"

---

### Langkah 2: Generate Password Baru

1. **Select app:** Mail
2. **Select device:** Other (custom name)
3. Ketik: `Mining System 2026`
4. Klik **GENERATE**

📋 **Copy password yang muncul** (16 karakter)

Contoh format:
```
abcd efgh ijkl mnop
```

**PENTING:** Copy semua 16 karakter (tanpa spasi saat paste)

---

### Langkah 3: Update File notifications.php

Buka file: `includes/notifications.php`

**Cari baris 21:**
```php
private $smtp_password = 'msoxtvqbgyptkonl'; // Password LAMA
```

**Ganti dengan password baru (TANPA SPASI):**
```php
private $smtp_password = 'abcdefghijklmnop'; // Password BARU dari step 2
```

⚠️ **16 karakter lowercase tanpa spasi!**

---

### Langkah 4: Test Email

Setelah update password, test dengan:

```bash
php test_smtp_auth.php
```

**Expected Output:**
```
✅ SUCCESS! Authentication working.
```

Lalu test notification lengkap:
```bash
php debug_notification.php
```

**Expected:**
```
✅ SUCCESS! Email sent.
```

---

## Quick Commands

**1. Test SMTP Auth:**
```bash
php test_smtp_auth.php
```

**2. Test Full Notification:**
```bash
php debug_notification.php
```

**3. View Error Log:**
```bash
php view_error_log.php
```

**4. Monitor Real-time:**
```powershell
Get-Content 'C:/laragon/tmp/php_errors.log' -Wait -Tail 50 | Select-String -Pattern 'NOTIFICATION'
```

---

## Checklist

- [ ] 1. Buka: https://myaccount.google.com/apppasswords
- [ ] 2. Generate password baru untuk "Mail" / "Mining System 2026"
- [ ] 3. Copy 16 karakter password
- [ ] 4. Update `includes/notifications.php` line 21
- [ ] 5. Paste password TANPA SPASI (16 chars)
- [ ] 6. Save file
- [ ] 7. Run: `php test_smtp_auth.php`
- [ ] 8. Konfirmasi: ✅ SUCCESS!
- [ ] 9. Run: `php debug_notification.php`
- [ ] 10. Check Gmail inbox

---

## Troubleshooting

### Error: "2-Step Verification not enabled"

1. Buka: https://myaccount.google.com/security
2. Aktifkan "2-Step Verification"
3. Follow wizard untuk setup (SMS/Phone)
4. Setelah aktif, bisa generate App Password

### Error: "App passwords option not available"

1. Pastikan 2-Step Verification sudah ON
2. Logout dari Google Account
3. Login ulang
4. Try again

### Error: Masih "Could not authenticate" setelah update

1. Verify password benar (16 chars, no spaces)
2. Try generate ulang App Password
3. Delete App Password lama di Gmail
4. Create new one dengan nama berbeda
5. Update di notifications.php

---

## Why App Password Expired?

Gmail App Passwords bisa expired karena:
- Security policy update
- Unused untuk waktu lama
- Manual revoke
- Account security review

**Solusi:** Generate ulang setiap kali expired.

---

## Setelah Fix

Test workflow lengkap:

**Test 1: Add Employee**
- Login as user company
- Add new employee
- Check: `php view_error_log.php`
- Look for: `[NOTIFICATION SUCCESS] Email sent`
- Check Gmail inbox

**Test 2: Resubmit**
- Reject employee as admin
- Resubmit as user
- Check log dan Gmail

**Test 3: KTT Reject**
- Reject appointment as KTT
- Check log dan Gmail

---

## Expected Behavior After Fix

**Before:**
```
[NOTIFICATION ERROR] SMTP Error: Could not authenticate
[NOTIFICATION ERROR] Failed to send email
[NOTIFICATION] Total emails sent: 0 / 4
```

**After (with new App Password):**
```
[NOTIFICATION SUCCESS] Email sent to agriawanwiranto5@gmail.com
[NOTIFICATION SUCCESS] Email sent to admin2@mining.com
[NOTIFICATION] Total emails sent: 4 / 4
```

---

## 🎯 ACTION REQUIRED

👉 **GENERATE APP PASSWORD BARU SEKARANG:**  
https://myaccount.google.com/apppasswords

Lalu update di: `includes/notifications.php` line 21

Test dengan: `php test_smtp_auth.php`
