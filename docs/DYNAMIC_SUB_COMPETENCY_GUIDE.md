# Dynamic Sub-Competency Implementation Guide

## Overview
Implementasi fitur **dynamic sub-competency dropdown** yang populate secara otomatis berdasarkan competency yang dipilih.

Contoh:
- Saat memilih "Petugas Industrial Hygiene" → sub-competency akan menampilkan:
  - Ahli Higiene Industri Muda
  - Ahli Higiene Industri Madya
  - Ahli Higiene Industri Utama

---

## Files Created/Modified

### 1. **migration_add_competency_sub_competencies.sql** (BARU)
Database migration yang membuat tabel relasi:
- `id` - PK
- `competency_id` - FK ke tabel competencies
- `sub_competency_name` - Nama sub-competency
- `sub_competency_level` - Level/order (1, 2, 3, ...)
- `description` - Deskripsi optional
- `is_active` - Flag aktif/non-aktif

### 2. **api_get_sub_competencies.php** (BARU)
API endpoint yang mengembalikan sub-competencies dalam format JSON:
```
POST /api_get_sub_competencies.php
Request: { "competency_id": 5 }
Response: {
  "success": true,
  "data": [
    { "id": 1, "name": "Ahli Higiene Industri Muda", "level": 1, "description": "Young..." },
    { "id": 2, "name": "Ahli Higiene Industri Madya", "level": 2, "description": "Middle..." }
  ]
}
```

### 3. **dept_add_employee.php** (MODIFIED)
Perubahan:
- ✅ Menambahkan `data-id` attribute ke competency options
- ✅ Mengganti hardcoded sub_competency options dengan placeholder kosong
- ✅ Menambahkan `onchange="loadSubCompetencies()"` event handler
- ✅ Menambahkan fungsi `loadSubCompetencies()` yang async fetch dari API
- ✅ Pass `$competencies_with_id` ke JavaScript untuk kemudahan debugging

### 4. **user_add_employee.php** (SIAP untuk modifikasi serupa)
File ini masih menggunakan approach hardcoded. Dapat diupdate dengan cara sama jika diperlukan.

---

## Setup Instructions

### Step 1: Jalankan Database Migration
```sql
-- Execute file: migration_add_competency_sub_competencies.sql
mysql -u username -p database_name < migration_add_competency_sub_competencies.sql
```

### Step 2: Insert Sample Data

Contoh untuk "Petugas Industrial Hygiene":

```sql
-- Terlebih dahulu, pastikan competency sudah ada
INSERT INTO `competencies` (`competency_name`, `position_type`) 
VALUES ('Petugas Industrial Hygiene', 'tenaga_teknis');

-- Kemudian insert sub-competencies
INSERT INTO `competency_sub_competencies` 
(`competency_id`, `sub_competency_name`, `sub_competency_level`, `description`, `is_active`) 
VALUES 
(
  (SELECT id FROM competencies WHERE competency_name = 'Petugas Industrial Hygiene'),
  'Ahli Higiene Industri Muda',
  1,
  'Young Industrial Hygiene Expert',
  1
),
(
  (SELECT id FROM competencies WHERE competency_name = 'Petugas Industrial Hygiene'),
  'Ahli Higiene Industri Madya',
  2,
  'Middle Industrial Hygiene Expert',
  1
),
(
  (SELECT id FROM competencies WHERE competency_name = 'Petugas Industrial Hygiene'),
  'Ahli Higiene Industri Utama',
  3,
  'Main Industrial Hygiene Expert',
  1
);
```

### Step 3: Test pada Halaman `/dept_add_employee.php`

1. Pilih **Competency Type**: "Tenaga Teknis"
2. Pilih **Competency**: "Petugas Industrial Hygiene"
3. **Sub Competency** dropdown akan otomatis populate dengan 3 pilihan
4. Pilih salah satu dan submit form

---

## How It Works

### JavaScript Flow:

```
┌─────────────────────────────────────┐
│ User selects competency from dropdown
└─────────┬───────────────────────────┘
          │
          ▼
  ┌──────────────────────────────┐
  │ onchange="loadSubCompetencies()"
  └─────────┬────────────────────┘
            │
            ▼
   ┌────────────────────────────────┐
   │ Extract competency_id data attr│
   └─────────┬──────────────────────┘
             │
             ▼
      ┌─────────────────────────┐
      │ Fetch API endpoint with │
      │ competency_id           │
      └─────────┬───────────────┘
                │
                ▼
      ┌──────────────────────────────┐
      │ Receive JSON array of         │
      │ sub-competencies             │
      └─────────┬────────────────────┘
                │
                ▼
      ┌──────────────────────────────┐
      │ Dynamically create <option>   │
      │ elements in sub_competency    │
      │ dropdown                      │
      └──────────────────────────────┘
```

---

## Database Structure

```sql
-- Tabel competency_sub_competencies
┌─────────────────────────────────────────┐
│ id (PK)                                 │
│ competency_id (FK) → competencies.id   │
│ sub_competency_name (VARCHAR 255)      │
│ sub_competency_level (INT)             │
│ description (TEXT)                     │
│ is_active (TINYINT, default 1)         │
│ created_at (TIMESTAMP)                 │
│ updated_at (TIMESTAMP)                 │
└─────────────────────────────────────────┘
```

---

## Features

✅ **Dynamic Loading** - Sub-competencies load dari database saat competency dipilih
✅ **Async/Await** - Non-blocking, user tetap bisa interact dengan form
✅ **Error Handling** - Console logging untuk debugging
✅ **Clean HTML** - No hardcoded options
✅ **Scalable** - Mudah extend untuk competency lainnya
✅ **Performance** - API endpoint menggunakan index untuk query cepat

---

## Extensibility

Untuk menambah sub-competencies untuk competency lain:

```sql
INSERT INTO `competency_sub_competencies` 
(`competency_id`, `sub_competency_name`, `sub_competency_level`, `description`) 
VALUES 
(
  (SELECT id FROM competencies WHERE competency_name = 'NAMA_COMPETENCY_LAIN'),
  'SUB_COMPETENCY_NAME',
  1,
  'DESKRIPSI'
);
```

Format direkomendasikan untuk level:
- Level 1 = Junior/Muda
- Level 2 = Middle/Madya
- Level 3 = Senior/Utama

---

## Browser Compatibility

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ IE 11+ (with polyfills for async/await)

---

## Troubleshooting

### Sub-competency tidak tampil?

1. **Check API Endpoint:**
   ```javascript
   // Open browser console (F12) dan lihat network tab
   // Pastikan api_get_sub_competencies.php accessible
   ```

2. **Check Database:**
   ```sql
   SELECT * FROM competency_sub_competencies WHERE is_active = 1;
   ```

3. **Check Console Log:**
   ```javascript
   // Buka F12 → Console tab
   // Cari error messages dari loadSubCompetencies()
   ```

### Competency ID tidak terdeteksi?

- Pastikan competency sudah di-select terlebih dahulu
- Check bahwa `data-id` attribute ada di competency option:
  ```php
  <option value="..." data-id="<?php echo $comp['id']; ?>">...</option>
  ```

---

## Next Steps

1. ✅ Run migration SQL file
2. ✅ Insert sample data untuk competencies
3. ✅ Test di `/dept_add_employee.php`
4. ⏳ Update `/user_add_employee.php` (opsional, sama langkahnya)
5. ⏳ Update `/admin_add_employee.php` (opsional)
6. ⏳ Create UI management page untuk manage sub-competencies

---

## Support

Jika ada pertanyaan atau error, check:
- Browser console (F12)
- PHP error log
- MySQL error log
- Check database constraints dan foreign keys
