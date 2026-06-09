# PANDUAN KONFIGURASI NOTIFIKASI WHATSAPP & EMAIL

## 📋 Overview
Sistem ini mendukung notifikasi otomatis melalui WhatsApp dan Email kepada admin ketika:
1. **User company menambahkan tenaga kerja baru** (perlu verifikasi admin)
2. **Surat penunjukan ditolak oleh KTT** (perlu review admin)

---

## 🔧 Konfigurasi WhatsApp API

### Pilihan Provider WhatsApp API
Anda dapat menggunakan salah satu provider berikut:

#### 1. **Fonnte** (Recommended untuk Indonesia)
- Website: https://fonnte.com
- Cara Setup:
  1. Daftar di fonnte.com
  2. Beli paket sesuai kebutuhan
  3. Dapatkan API Key dari dashboard
  4. Hubungkan nomor WhatsApp Anda

#### 2. **Wablas**
- Website: https://wablas.com
- Setup serupa dengan Fonnte

#### 3. **WhatsApp Business API** (Official)
- Lebih kompleks, memerlukan verifikasi bisnis
- Cocok untuk skala enterprise

### Update Konfigurasi WhatsApp

Edit file: `includes/notifications.php`

```php
// Baris 11-12
private $whatsapp_api_url = 'https://api.fonnte.com/send'; // Ganti dengan API URL Anda
private $whatsapp_api_key = 'YOUR_WHATSAPP_API_KEY'; // Ganti dengan API key Anda
```

**Contoh untuk Fonnte:**
```php
private $whatsapp_api_url = 'https://api.fonnte.com/send';
private $whatsapp_api_key = 'abcd1234567890xyz'; // API key dari dashboard Fonnte
```

**Contoh untuk Wablas:**
```php
private $whatsapp_api_url = 'https://solo.wablas.com/api/send-message';
private $whatsapp_api_key = 'your_wablas_token';
```

---

## 📧 Konfigurasi Email

### Update Konfigurasi Email

Edit file: `includes/notifications.php`

```php
// Baris 15-16
private $email_from = 'noreply@mining-system.com'; // Email pengirim
private $email_from_name = 'Mining Appointment System'; // Nama pengirim
```

**Contoh:**
```php
private $email_from = 'sistem@perusahaananda.com';
private $email_from_name = 'Sistem Surat Penunjukan - PT Meares Soputan';
```

### Konfigurasi SMTP (Opsional - untuk Email yang lebih reliable)

Jika ingin menggunakan SMTP (Gmail, SendGrid, dll), Anda perlu menginstal PHPMailer:

```bash
composer require phpmailer/phpmailer
```

Kemudian update fungsi `sendEmail()` di `includes/notifications.php`.

---

## 👥 Update Kontak Admin

Jalankan query SQL berikut untuk menambahkan email dan nomor telepon admin:

```sql
-- Update admin pertama
UPDATE users 
SET email = 'admin@example.com', 
    phone = '6281234567890' 
WHERE username = 'admin';

-- Update admin kedua (jika ada)
UPDATE users 
SET email = 'admin2@example.com', 
    phone = '6281234567891' 
WHERE username = 'admin2';
```

**Format Nomor Telepon:**
- Gunakan format internasional: `62` + nomor tanpa 0 di depan
- Contoh: `081234567890` → `6281234567890`

---

## 🧪 Testing Notifikasi

### Test Tambah Tenaga Kerja

1. Login sebagai **user company** (bukan admin)
2. Buka halaman "Tambah Tenaga Kerja"
3. Isi semua data dan submit
4. Cek WhatsApp dan Email admin - seharusnya menerima notifikasi

### Test Penolakan Surat oleh KTT

1. Login sebagai **KTT**
2. Buka halaman "Approval"
3. Tolak salah satu surat penunjukan
4. Jika sudah 2 KTT menolak, admin akan menerima notifikasi review

### Debug Mode

Untuk melihat log notifikasi, cek file `error_log` atau tambahkan:

```php
// Di includes/notifications.php, setelah send
error_log("WhatsApp sent to: " . $phone);
error_log("Email sent to: " . $to_email);
```

---

## 📊 Monitoring Notifikasi

### Cek Log Notifikasi di Database

```sql
SELECT * FROM notification_logs 
ORDER BY sent_at DESC 
LIMIT 10;
```

### Struktur Tabel notification_logs

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| notification_type | VARCHAR(50) | `new_employee` atau `appointment_rejected` |
| reference_id | INT | ID employee atau appointment |
| company_name | VARCHAR(255) | Nama perusahaan |
| message | TEXT | Isi pesan yang dikirim |
| sent_at | TIMESTAMP | Waktu pengiriman |

---

## ⚠️ Troubleshooting

### Notifikasi WhatsApp tidak terkirim

1. **Cek API Key**: Pastikan API key valid dan masih aktif
2. **Cek Saldo**: Provider seperti Fonnte memerlukan saldo/kredit
3. **Cek Format Nomor**: Harus format 62xxx tanpa spasi/karakter lain
4. **Cek Log Error**: Lihat `error_log` PHP untuk pesan error detail

```php
// Tambahkan ini untuk debugging
error_log("WhatsApp API Response: " . print_r($response, true));
```

### Notifikasi Email tidak terkirim

1. **Cek konfigurasi mail server**: Pastikan PHP `mail()` function aktif
2. **Cek spam folder**: Email mungkin masuk ke spam
3. **Gunakan SMTP**: Lebih reliable daripada PHP mail()
4. **Test dengan script sederhana**:

```php
<?php
$to = "test@example.com";
$subject = "Test Email";
$message = "This is a test";
$headers = "From: noreply@yourdomain.com";

if(mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully";
} else {
    echo "Email failed";
}
?>
```

### Admin tidak menerima notifikasi

1. **Cek data kontak admin**:
```sql
SELECT username, email, phone FROM users WHERE role = 'admin';
```

2. **Pastikan kolom tidak NULL**
3. **Update jika perlu**:
```sql
UPDATE users SET email = 'correct@email.com' WHERE username = 'admin';
```

---

## 🔒 Keamanan

### Best Practices

1. **Jangan commit API Key ke Git**:
   - Tambahkan ke `.gitignore`:
   ```
   includes/notifications_config.php
   ```

2. **Gunakan Environment Variables**:
   ```php
   $whatsapp_api_key = getenv('WHATSAPP_API_KEY');
   ```

3. **Limit Rate**: Batasi jumlah notifikasi per waktu untuk avoid spam

---

## 💰 Estimasi Biaya

### Fonnte (Indonesia)
- Paket 1000 pesan: ~Rp 100.000
- Berlaku 1 tahun
- Cocok untuk sistem dengan moderate traffic

### Email
- PHP mail(): Gratis (tapi sering masuk spam)
- SMTP Gmail: Gratis (limit 500/day)
- SendGrid: Free tier 100 email/day

---

## 📝 Customization

### Mengubah Format Pesan

Edit fungsi `buildNewEmployeeMessage()` atau `buildRejectionReviewMessage()` di `includes/notifications.php`:

```php
private function buildNewEmployeeMessage($employee, $company_name) {
    $message = "🔔 *CUSTOM NOTIFICATION*\n\n";
    $message .= "Perusahaan: {$company_name}\n";
    // ... customize as needed
    return $message;
}
```

### Menambahkan Notifikasi Baru

Contoh: Notifikasi saat surat disetujui

```php
public function notifyAppointmentApproved($appointment_id) {
    $admins = $this->getAdminContacts();
    // ... build message
    // ... send to admins
}
```

Kemudian panggil di file approval ketika status = 'approved'.

---

## 📞 Support & Contact

Jika ada pertanyaan atau masalah:
1. Cek dokumentasi provider WhatsApp API Anda
2. Review error logs di server
3. Test dengan data dummy terlebih dahulu
4. Pastikan semua konfigurasi sudah benar

---

**Last Updated**: February 1, 2026  
**Version**: 1.0
