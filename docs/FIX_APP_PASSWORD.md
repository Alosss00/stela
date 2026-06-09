# ❌ MASALAH: App Password Ditolak Gmail

## Error yang Terjadi
```
535-5.7.8 Username and Password not accepted
SMTP Error: Could not authenticate
```

## Penyebab
App Password yang digunakan (`oyqhifxaegvrymmr`) **sudah tidak valid** atau **salah**.

---

## ✅ SOLUSI: Generate App Password Baru

### Langkah 1: Buka Gmail App Passwords
Klik link ini: https://myaccount.google.com/apppasswords

**ATAU**

1. Buka: https://myaccount.google.com/security
2. Cari "2-Step Verification" → Klik
3. Scroll ke bawah, cari "App passwords" → Klik

### Langkah 2: Generate Password Baru

1. **Select app:** Mail
2. **Select device:** Other (Custom name)
   - Tulis: `Mining System`
3. Klik **Generate**

### Langkah 3: Copy Password

Akan muncul password 16 karakter seperti:
```
abcd efgh ijkl mnop
```

**Copy password tersebut** (tanpa spasi)

### Langkah 4: Update includes/notifications.php

Buka: `includes/notifications.php` baris **21**

**SEBELUM:**
```php
private $smtp_password = 'oyqhifxaegvrymmr'; // Password LAMA/SALAH
```

**SESUDAH:**
```php
private $smtp_password = 'abcdefghijklmnop'; // Password BARU dari Gmail
```

⚠️ **Ganti dengan password yang baru Anda generate!**

### Langkah 5: Test

```bash
php test_send_email.php
```

Jika berhasil, akan muncul:
```
✅ Email berhasil dikirim!
```

Dan cek inbox Gmail Anda dalam 1-2 menit.

---

## Troubleshooting Tambahan

### Jika Masih Error "Could not authenticate"

**Cek 1: 2-Step Verification Aktif?**
- Buka: https://myaccount.google.com/security
- Pastikan "2-Step Verification" = **ON** (aktif)
- Jika OFF, aktifkan dulu

**Cek 2: App Password Benar?**
- 16 karakter lowercase
- Tidak ada spasi
- Copy paste langsung dari Gmail

**Cek 3: Email Benar?**
- Email di notifications.php: `agriawanwiranto5@gmail.com`
- Email di Gmail App Password: Harus sama

**Cek 4: Generate Ulang**
- Hapus App Password lama di Gmail
- Generate yang baru
- Update ke notifications.php

---

## Kenapa App Password Lama Tidak Berfungsi?

Kemungkinan:
1. **Password revoked** - User menghapus dari Gmail settings
2. **Password expired** - Gmail secara otomatis disable
3. **Password salah** - Typo saat copy/paste
4. **Account security change** - Gmail reset semua app passwords

---

## Setelah Update Password

Test dengan 3 cara:

**1. Test Script:**
```bash
php test_send_email.php
```

**2. Debug Mode:**
```bash
php debug_email.php
```
Cari output: `✅ Email SENT SUCCESSFULLY!`

**3. Cek Inbox:**
- Buka: https://mail.google.com
- Cek Inbox, Spam, Promotions
- Search: `from:agriawanwiranto5@gmail.com`

---

## Quick Fix (Copy-Paste Ready)

1. Generate App Password: https://myaccount.google.com/apppasswords
2. Copy 16-digit password
3. Edit `includes/notifications.php` line 21
4. Paste password baru (hapus spasi)
5. Save file
6. Run: `php test_send_email.php`
7. Cek Gmail inbox

✅ **Email seharusnya masuk dalam 1-2 menit!**
