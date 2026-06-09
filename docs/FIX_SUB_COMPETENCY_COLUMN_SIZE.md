# Sub-Competency Column Size Fix

## 🔴 Masalah yang Ditemukan

Error: `Data too long for column 'sub_competency' at row 1`

### Penyebab
Kolom `sub_competency` di tabel `employees` didefinisikan dengan kapasitas **VARCHAR(10)**, yang terlalu kecil untuk menyimpan nama sub-competency lengkap.

Definisi lama (di `migration_add_sub_competency.sql`):
```sql
ALTER TABLE employees ADD COLUMN sub_competency VARCHAR(10) NULL AFTER competency_name;
```

Namun, nama sub-competency bisa mencapai 100+ karakter, contohnya:
- "Ahli Hygiene Industri Muda"
- "Ahli Madya Industrial Hygiene"
- dst.

## ✅ Solusi

### Option 1: Jalankan PHP Migration Script (Rekomendasi)

1. Akses file: `fix_sub_competency_column.php` melalui browser:
   ```
   http://localhost/revisi 17-3-26/fix_sub_competency_column.php
   ```

2. Script akan secara otomatis:
   - Memeriksa ukuran kolom saat ini
   - Mengubah kapasitas dari VARCHAR(10) menjadi VARCHAR(255)
   - Menampilkan status hasil perubahan

### Option 2: Jalankan SQL Query Langsung

Jalankan SQL berikut di phpMyAdmin atau MySQL client:

```sql
ALTER TABLE employees MODIFY COLUMN sub_competency VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;
```

Atau gunakan file: `migration_fix_sub_competency_column.sql`

### Option 3: Jalankan di Terminal/Command Line

```bash
mysql -u root -p mining_appointment < migration_fix_sub_competency_column.sql
```

## 📋 Langkah-Langkah Perbaikan

1. **Jalankan salah satu dari 3 opsi solusi di atas** untuk memperbaiki struktur tabel

2. **Verifikasi perubahan**:
   ```sql
   DESCRIBE employees;
   ```
   Pastikan kolom `sub_competency` sekarang menunjukkan `varchar(255)` atau lebih besar

3. **Coba submit form lagi** di user_add_employee.php
   - Pilih Position Type: **Tenaga Teknis**
   - Pilih Competency: Salah satu dari daftar
   - Pilih Sub Competency: Akan muncul otomatis
   - Submit form - sekarang seharusnya berfungsi ✅

## 📁 File yang Diperbarui

- ✅ `fix_sub_competency_column.php` - Migration script PHP
- ✅ `migration_fix_sub_competency_column.sql` - Raw SQL migration
- ✅ `user_add_employee.php` - Sudah mendukung sub-competency dinamis

## 🔍 Detail Teknis

**Sebelum:**
```
Kolom: sub_competency
Tipe: VARCHAR(10)
Kapasitas: 10 karakter
Status: ❌ Tidak cukup untuk nama sub-competency
```

**Sesudah:**
```
Kolom: sub_competency
Tipe: VARCHAR(255)
Kapasitas: 255 karakter
Status: ✅ Sempurna untuk nama sub-competency lengkap
```

## 🚨 Catatan Penting

- Perubahan struktur tabel tidak akan menghapus data yang sudah ada
- Data yang sudah tersimpan (jika ada) tidak akan terpengaruh
- Pastikan Anda memiliki backup database sebelum menjalankan migration
- Hanya jalankan satu opsi solusi, jangan menjalankan ketiganya bersamaan

## ✨ Hasil yang Diharapkan

Setelah migration:
1. User bisa memilih Tenaga Teknis sebagai competency type
2. Form akan menampilkan dropdown Competency
3. Saat memilih Competency, dropdown Sub Competency otomatis muncul
4. User bisa memilih sub-competency dan submit form tanpa error ✅
