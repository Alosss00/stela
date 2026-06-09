# 📱 SISTEM NOTIFIKASI WHATSAPP & EMAIL - IMPLEMENTASI LENGKAP

## ✅ Fitur yang Telah Diimplementasikan

### 1. **Notifikasi Tenaga Kerja Baru**
Ketika user company atau department menambahkan tenaga kerja baru, admin akan menerima notifikasi melalui:
- ✉️ **Email**: Detail karyawan baru yang perlu diverifikasi
- 📱 **WhatsApp**: Pesan instan dengan link ke halaman verifikasi

**Trigger**: 
- File: `user_add_employee.php` (user company)
- File: `dept_add_employee.php` (department user)  
- File: `employees.php` (admin menambahkan untuk company)

### 2. **Notifikasi Surat Ditolak KTT**
Ketika surat penunjukan ditolak oleh KTT dan perlu direview admin:
- ✉️ **Email**: Detail surat yang ditolak + alasan penolakan
- 📱 **WhatsApp**: Alert untuk segera me-review penolakan

**Trigger**:
- File: `approval.php` (ketika status berubah menjadi `rejected_by_ktt`)

---

## 📂 File-File yang Dibuat/Dimodifikasi

### File Baru
1. ✅ **`includes/notifications.php`** - Core notification system
   - Class: `NotificationService`
   - Methods: `notifyNewEmployeeAdded()`, `notifyAppointmentRejectedForReview()`
   - Support WhatsApp API dan Email

2. ✅ **`migration_add_contact_info.php`** - Database migration
   - Menambahkan kolom `email` dan `phone` ke tabel `users`

3. ✅ **`test_notifications.php`** - Testing script
   - Cek konfigurasi admin contacts
   - Preview format pesan notifikasi
   - Verify notification system

4. ✅ **`setup_admin_contacts.php`** - Quick setup guide
   - Generate SQL queries untuk update admin contacts

5. ✅ **`NOTIFICATION_SETUP_GUIDE.md`** - Dokumentasi lengkap
   - Panduan konfigurasi WhatsApp API
   - Panduan konfigurasi Email
   - Troubleshooting guide

### File yang Dimodifikasi
1. ✅ **`user_add_employee.php`** 
   - Added: `require_once 'includes/notifications.php'`
   - Added: Notification call setelah insert employee berhasil

2. ✅ **`dept_add_employee.php`**
   - Added: `require_once 'includes/notifications.php'`
   - Added: Notification call setelah insert employee berhasil

3. ✅ **`employees.php`**
   - Added: `require_once 'includes/notifications.php'`
   - Added: Notification call setelah insert employee berhasil (jika bukan admin)

4. ✅ **`approval.php`**
   - Added: `require_once 'includes/notifications.php'`
   - Added: Notification call ketika appointment status = `rejected_by_ktt`

---

## 🔧 Konfigurasi yang Diperlukan

### 1. Update Database
```bash
php migration_add_contact_info.php
```
✅ Sudah dijalankan - kolom `phone` ditambahkan

### 2. Update Admin Contacts
Jalankan SQL berikut atau edit sesuai data admin Anda:

```sql
-- Contoh untuk admin1
UPDATE users SET 
  email = 'admin@perusahaan.com',
  phone = '6281234567890'
WHERE username = 'admin1';

-- Ulangi untuk admin lainnya
```

Atau gunakan:
```bash
php setup_admin_contacts.php
```

### 3. Konfigurasi WhatsApp API

Edit file **`includes/notifications.php`** baris 11-12:

```php
private $whatsapp_api_url = 'https://api.fonnte.com/send'; // Ganti dengan API Anda
private $whatsapp_api_key = 'YOUR_WHATSAPP_API_KEY'; // Ganti dengan API key Anda
```

**Provider yang Recommended:**
- **Fonnte** (https://fonnte.com) - Mudah, cocok untuk Indonesia
- **Wablas** (https://wablas.com) - Alternatif lain
- **WhatsApp Business API** - Untuk enterprise

### 4. Konfigurasi Email

Edit file **`includes/notifications.php`** baris 15-16:

```php
private $email_from = 'noreply@perusahaan.com'; // Email pengirim Anda
private $email_from_name = 'Sistem Mining PT Meares'; // Nama pengirim
```

---

## 🧪 Testing

### Test Lengkap
```bash
php test_notifications.php
```

Output yang diharapkan:
- ✅ Admin contacts ditemukan (dengan email/phone)
- ✅ NotificationService berhasil di-load
- ✅ Sample message format ditampilkan
- ⚠️ Warning jika konfigurasi belum diisi

### Test Manual

**Test 1: Notifikasi Tenaga Kerja Baru**
1. Logout dari admin
2. Login sebagai **user company** (contoh: username `dnx`)
3. Buka menu "Tambah Tenaga Kerja"
4. Isi form lengkap dan submit
5. Cek WhatsApp dan Email admin - harus menerima notifikasi

**Test 2: Notifikasi Surat Ditolak**
1. Login sebagai **KTT** (contoh: username `ktt_msm`)
2. Buka menu "Approval"
3. Pilih surat yang pending
4. Klik "Reject" dan isi alasan
5. Jika sudah 2 KTT menolak → admin menerima notifikasi

---

## 📊 Monitoring

### Cek Log Notifikasi
```sql
SELECT * FROM notification_logs 
ORDER BY sent_at DESC 
LIMIT 20;
```

### Struktur Table
- `notification_type`: `new_employee` atau `appointment_rejected`
- `reference_id`: ID employee atau appointment
- `company_name`: Nama perusahaan terkait
- `message`: Full text pesan yang dikirim
- `sent_at`: Timestamp pengiriman

---

## 📱 Format Pesan

### Pesan Tenaga Kerja Baru
```
🔔 *NOTIFIKASI TENAGA KERJA BARU*

Perusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:

📋 *Detail Karyawan:*
• ID Batch: PTDNX-001
• Nama: John Doe
• Jabatan: HSE Officer
• Perusahaan: PT DNX Indonesia

⚠️ Silakan login ke sistem untuk melakukan verifikasi.
📍 https://your-domain.com/verify_employee.php
```

### Pesan Surat Ditolak
```
⚠️ *NOTIFIKASI SURAT DITOLAK*

Surat penunjukan telah ditolak oleh KTT dan memerlukan review admin:

📋 *Detail Surat:*
• No. Surat: 001/TT/MSM/01/2026
• Karyawan: John Doe (PTDNX-001)
• Jabatan: HSE Officer
• Perusahaan: PT DNX Indonesia
• Ditolak oleh: Tejo Prihantoro

💬 *Alasan Penolakan:*
Sertifikat tidak sesuai standar K3. Mohon upload ulang.

⚠️ Silakan login untuk me-review penolakan ini.
📍 https://your-domain.com/admin_review_rejection.php
```

---

## ⚙️ Customization

### Mengubah Format Pesan

Edit fungsi di **`includes/notifications.php`**:

**Untuk pesan tenaga kerja baru:**
```php
private function buildNewEmployeeMessage($employee, $company_name) {
    $message = "🔔 *CUSTOM NOTIFICATION*\n\n";
    // ... customize sesuai kebutuhan
    return $message;
}
```

**Untuk pesan surat ditolak:**
```php
private function buildRejectionReviewMessage($appointment) {
    $message = "⚠️ *CUSTOM REJECTION*\n\n";
    // ... customize sesuai kebutuhan
    return $message;
}
```

### Menambahkan Notifikasi Baru

Contoh: Notifikasi saat surat disetujui

1. Tambah method di `NotificationService`:
```php
public function notifyAppointmentApproved($appointment_id) {
    $admins = $this->getAdminContacts();
    // Build message
    $message = "✅ Surat penunjukan disetujui...";
    // Send to admins
    foreach ($admins as $admin) {
        if (!empty($admin['phone'])) {
            $this->sendWhatsApp($admin['phone'], $message);
        }
    }
}
```

2. Panggil di `approval.php` saat status = 'approved':
```php
$notificationService->notifyAppointmentApproved($id);
```

---

## 🔒 Keamanan & Best Practices

### 1. Jangan Commit API Key ke Git
Tambahkan ke `.gitignore`:
```
includes/notifications_config.php
.env
```

### 2. Gunakan Environment Variables
```php
$whatsapp_api_key = getenv('WHATSAPP_API_KEY');
$email_from = getenv('EMAIL_FROM');
```

### 3. Rate Limiting
Untuk mencegah spam, tambahkan delay atau limit:
```php
private $last_sent_time = [];

private function canSend($recipient) {
    if (isset($this->last_sent_time[$recipient])) {
        $time_diff = time() - $this->last_sent_time[$recipient];
        if ($time_diff < 60) { // Minimum 60 detik antar pesan
            return false;
        }
    }
    return true;
}
```

---

## ⚠️ Troubleshooting

### WhatsApp tidak terkirim
- ✅ Cek API key valid dan aktif
- ✅ Cek saldo/kredit di provider
- ✅ Cek format nomor: `6281234567890` (tanpa spasi/simbol)
- ✅ Review `error_log` PHP untuk detail error

### Email tidak terkirim
- ✅ Cek konfigurasi PHP `mail()` function
- ✅ Cek spam folder
- ✅ Gunakan SMTP untuk reliability lebih baik
- ✅ Test dengan script sederhana terlebih dahulu

### Admin tidak menerima notifikasi
- ✅ Cek data admin: `SELECT * FROM users WHERE role = 'admin'`
- ✅ Pastikan email dan phone tidak NULL
- ✅ Cek table notification_logs untuk konfirmasi

---

## 💰 Estimasi Biaya

### WhatsApp (Fonnte)
- Paket 1000 pesan: ~Rp 100.000
- Berlaku 1 tahun
- Cocok untuk sistem dengan traffic sedang

### Email
- PHP mail(): Gratis (tapi sering masuk spam)
- Gmail SMTP: Gratis (limit 500 email/day)
- SendGrid: Free tier 100 email/day

---

## 📞 Support

Untuk bantuan lebih lanjut:
1. Baca: **NOTIFICATION_SETUP_GUIDE.md**
2. Jalankan: `php test_notifications.php`
3. Check logs: Table `notification_logs` di database
4. Review error logs di server

---

## ✅ Checklist Setup

- [ ] Jalankan migration: `php migration_add_contact_info.php`
- [ ] Update admin contacts (email & phone)
- [ ] Daftar & dapatkan WhatsApp API key (Fonnte/Wablas)
- [ ] Update config di `includes/notifications.php`
- [ ] Test dengan: `php test_notifications.php`
- [ ] Test manual: Tambah employee sebagai user company
- [ ] Verify notifikasi diterima admin
- [ ] Monitor `notification_logs` table

---

**Implementasi Selesai**: February 1, 2026  
**Status**: ✅ **READY TO USE** (setelah konfigurasi API key)  
**Version**: 1.0
