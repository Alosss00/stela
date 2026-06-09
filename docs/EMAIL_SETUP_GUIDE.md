# Setup Email Notification dengan Gmail SMTP

## Masalah
Email notifikasi tidak masuk karena PHP mail() di Laragon menggunakan Mailpit (testing tool) yang tidak mengirim email ke internet.

## Solusi
Menggunakan **PHPMailer** dengan **Gmail SMTP** untuk mengirim email yang sebenarnya.

---

## Langkah Setup

### 1. Install PHPMailer ✅
```bash
composer require phpmailer/phpmailer
```

### 2. Setup App Password Gmail

#### Kenapa perlu App Password?
- Gmail tidak mengizinkan login dengan password biasa untuk aplikasi
- App Password adalah password khusus 16 karakter untuk aplikasi

#### Cara Generate App Password:

1. **Aktifkan 2-Step Verification** (jika belum)
   - Buka: https://myaccount.google.com/security
   - Cari "2-Step Verification"
   - Klik "Get Started" dan ikuti instruksi

2. **Generate App Password**
   - Buka: https://myaccount.google.com/apppasswords
   - Atau dari Security > 2-Step Verification > App passwords
   
3. **Pilih App & Device**
   - Select app: **Mail**
   - Select device: **Other (Custom name)** → tulis "Mining System"
   - Klik **Generate**

4. **Copy Password**
   - Akan muncul 16 karakter password (contoh: `abcd efgh ijkl mnop`)
   - Copy password tersebut

### 3. Update Konfigurasi di `includes/notifications.php`

Edit file `includes/notifications.php` baris **18**:

```php
private $smtp_password = 'abcdefghijklmnop'; // 16 karakter tanpa spasi
```

**Contoh:**
```php
// SEBELUM (kosong)
private $smtp_password = '';

// SESUDAH (isi dengan App Password Anda)
private $smtp_password = 'zpqkmxwvabcdefgh'; // ganti dengan password Anda
```

### 4. Test Email

Jalankan test script:
```bash
php test_send_email.php
```

**Output yang diharapkan:**
```
=== TEST EMAIL DENGAN PHPMAILER ===

📧 Mengirim test email ke: agriawanwiranto05@gmail.com (Administrator 1)

⏳ Mengirim email...

✅ Email berhasil dikirim!
   Silakan cek inbox email Anda.
```

### 5. Cek Email Inbox

- Buka Gmail: agriawanwiranto05@gmail.com
- Cek Inbox (atau Spam/Promotions folder)
- Anda akan menerima email dengan subject: "Tenaga Kerja Baru Perlu Verifikasi - PT DNX Indonesia"

---

## Kapan Email Dikirim?

Email akan otomatis terkirim ke semua admin dalam 2 situasi:

### Situasi 1: Company Menambah Employee Baru
**Trigger:** User company login dan tambah employee di:
- `user_add_employee.php`
- `dept_add_employee.php`
- `employees.php` (jika non-admin)

**Email Subject:** "Tenaga Kerja Baru Perlu Verifikasi - [Nama Company]"

**Email Content:**
```
🔔 NOTIFIKASI TENAGA KERJA BARU

Perusahaan PT DNX Indonesia telah menambahkan tenaga kerja baru yang memerlukan verifikasi:

📋 Detail Karyawan:
• ID Batch: 0910
• Nama: Windy
• Jabatan: Operator
• Perusahaan: PT DNX Indonesia

⚠️ Silakan login ke sistem untuk melakukan verifikasi.
```

### Situasi 2: Appointment Ditolak KTT
**Trigger:** KTT menolak surat penunjukan di `approval.php`

**Email Subject:** "Surat Penunjukan Ditolak - Perlu Review Admin"

**Email Content:**
```
⚠️ NOTIFIKASI SURAT DITOLAK

Surat penunjukan telah ditolak oleh KTT dan memerlukan review admin:

📋 Detail Surat:
• No. Surat: 001/TT/MSM/01/2026
• Karyawan: Agriawan Iswahyudi (PTDNX-121111)
• Jabatan: HSE Officer
• Perusahaan: PT DNX Indonesia
```

---

## Troubleshooting

### Email Tidak Masuk

**Cek 1: App Password Sudah Diisi?**
```bash
php -r "require 'includes/notifications.php'; echo 'OK';"
```
Jika error "authentication failed", App Password salah atau kosong.

**Cek 2: Email di Spam?**
- Buka Gmail
- Cek folder **Spam** atau **Promotions**
- Mark as "Not Spam" jika ada

**Cek 3: 2-Step Verification Aktif?**
- Buka: https://myaccount.google.com/security
- Pastikan 2-Step Verification: ON

**Cek 4: App Password Valid?**
- App Password harus 16 karakter lowercase tanpa spasi
- Jika lupa, generate ulang di: https://myaccount.google.com/apppasswords

### Error "SMTP connect() failed"

**Solusi:**
1. Cek koneksi internet
2. Pastikan port 587 tidak diblock firewall
3. Gmail SMTP: smtp.gmail.com:587

### Error "Could not authenticate"

**Solusi:**
1. App Password salah → Generate ulang
2. 2-Step Verification belum aktif → Aktifkan dulu

---

## Konfigurasi SMTP (Detail)

File: `includes/notifications.php`

```php
// Email Configuration - Gmail SMTP
private $smtp_host = 'smtp.gmail.com';      // Gmail SMTP server
private $smtp_port = 587;                    // TLS port
private $smtp_username = 'agriawanwiranto5@gmail.com'; // Email pengirim
private $smtp_password = '';                 // ⚠️ ISI DENGAN APP PASSWORD
private $email_from = 'agriawanwiranto5@gmail.com';
private $email_from_name = 'Mining Appointment System';
```

---

## Update Email Admin Lain

Jika ingin admin2, admin3, admin4 juga terima email:

```sql
UPDATE users SET email = 'emailadmin2@gmail.com' WHERE username = 'admin2';
UPDATE users SET email = 'emailadmin3@gmail.com' WHERE username = 'admin3';
UPDATE users SET email = 'emailadmin4@gmail.com' WHERE username = 'admin4';
```

---

## Monitoring

Cek log pengiriman email:

```sql
SELECT * FROM notification_logs ORDER BY created_at DESC LIMIT 10;
```

Atau di file PHP error log:
```
✓ Email sent successfully to agriawanwiranto05@gmail.com: Tenaga Kerja Baru Perlu Verifikasi - PT DNX Indonesia
```

---

## Keuntungan PHPMailer vs mail()

| Feature | mail() (Mailpit) | PHPMailer (SMTP) |
|---------|------------------|------------------|
| Real email delivery | ❌ Hanya testing | ✅ Email sungguhan |
| HTML support | ✅ | ✅ |
| Attachments | ⚠️ Limited | ✅ Full support |
| Error handling | ❌ Basic | ✅ Detailed |
| Authentication | ❌ | ✅ SMTP Auth |
| SSL/TLS | ❌ | ✅ |

---

## File yang Dimodifikasi

1. ✅ `includes/notifications.php` - Updated to use PHPMailer
2. ✅ `composer.json` - Added PHPMailer dependency
3. ✅ `vendor/` - PHPMailer library installed
4. ✅ `test_send_email.php` - Testing script
5. ✅ `setup_email.php` - Configuration helper

---

## Checklist Setup

- [ ] Install PHPMailer: `composer require phpmailer/phpmailer`
- [ ] Aktifkan 2-Step Verification di Gmail
- [ ] Generate App Password
- [ ] Update `smtp_password` di `includes/notifications.php`
- [ ] Test dengan `php test_send_email.php`
- [ ] Cek inbox Gmail
- [ ] (Optional) Update email admin2, admin3, admin4

---

## Selesai! 🎉

Sistem notifikasi email sekarang akan mengirim email yang sebenarnya ke Gmail admin.
