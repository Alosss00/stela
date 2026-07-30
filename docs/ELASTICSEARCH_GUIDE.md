# Panduan Integrasi Elasticsearch - STELA 2.0

Dokumen ini menjelaskan arsitektur, konfigurasi, dan penggunaan **Elasticsearch** pada sistem STELA (Expertise Appointment Letter System).

---

## 1. Arsitektur & Perancangan

Sistem STELA 2.0 menggunakan Elasticsearch untuk menyediakan pencarian teks lengkap (*Full-Text Search*), pencarian toleran salah ketik (*Fuzzy Search*), serta filter data berperforma tinggi pada entitas Karyawan (*Employees*) dan Penunjukan (*Appointments*).

### Graceful Fallback Strategy
Jika server Elasticsearch dalam keadaan luring (*offline*), belum dikonfigurasi, atau mengalami kendala jaringan, `ElasticsearchService` secara otomatis mengalihkan kueri pencarian kembali ke database MySQL (*Graceful MySQL Fallback*). Sistem akan terus beroperasi tanpa menyebabkan eror pada antarmuka pengguna.

---

## 2. Struktur Indeks & Mapping

Sistem mengelola dua indeks utama dengan awalan `stela_`:

### A. Indeks Karyawan (`stela_employees`)
- **`employee_code`**: Keyword / Exact Match & Text.
- **`full_name`**: Text dengan kustom analyzer `stela_analyzer` (standard tokenizer, lowercase, asciifolding) & Keyword subfield.
- **`position`**: Text & Keyword.
- **`department`**: Text & Keyword.
- **`contractor_company`**: Text & Keyword.
- **`competency_type`**: Keyword (`pengawas_operasional`, `pengawas_teknis`, `tenaga_teknis`).
- **`ruang_lingkup`**: Text.
- **`sub_competency`**: Text.
- **`supervision_area`**: Text.
- **`approval_status`**: Keyword.
- **`is_active`**: Integer.
- **`created_at`**: Date.

### B. Indeks Penunjukan (`stela_appointments`)
- **`appointment_number`**: Keyword & Text.
- **`employee_id`**: Integer.
- **`employee_name`**: Text.
- **`contractor_company`**: Text.
- **`competency_type`**: Keyword.
- **`status`**: Keyword (`draft`, `pending`, `approved`, `rejected`).
- **`created_at`**: Date.

---

## 3. Konfigurasi (`includes/config.php`)

Konfigurasi Elasticsearch diatur pada file `includes/config.php`:

```php
// Konfigurasi Elasticsearch
define('ELASTICSEARCH_HOST', getenv('ELASTICSEARCH_HOST') ?: 'http://localhost:9200');
define('ELASTICSEARCH_ENABLED', true);
define('ELASTICSEARCH_INDEX_PREFIX', getenv('ELASTICSEARCH_INDEX_PREFIX') ?: 'stela_');
```

---

## 4. Cara Melakukan Re-index Data

Untuk pertama kali atau saat melakukan sinkronisasi ulang data dari MySQL ke Elasticsearch, jalankan skrip re-index:

### Melalui Command Line Interface (CLI):
```bash
php utils/reindex_elasticsearch.php
```

### Melalui Browser (Memerlukan Sesi Admin):
Akses URL:
```
http://<host>/stela-2/utils/reindex_elasticsearch.php
```

---

## 5. Penggunaan API Pencarian (`api/search_elasticsearch.php`)

Endpoint API REST JSON tersedia untuk pencarian cepat dari frontend:

### Contoh Permintaan (GET):
```http
GET /stela-2/api/search_elasticsearch.php?q=budi&target=employees&company=PT+Toka&limit=20
```

### Parameter:
- `q` / `query`: Kata kunci pencarian (nama, kode karyawan, posisi, perusahaan, dll).
- `target`: `employees` (default) atau `appointments`.
- `company`: Filter nama perusahaan kontraktor.
- `competency_type`: Filter jenis kompetensi.
- `status`: Filter status persetujuan.
- `page`: Nomor halaman (default `1`).
- `limit`: Jumlah data per halaman (default `20`).

### Contoh Respon JSON:
```json
{
  "status": "success",
  "source": "elasticsearch",
  "query": "budi",
  "page": 1,
  "limit": 20,
  "total": 1,
  "items": [
    {
      "id": 42,
      "employee_code": "EMP-0042",
      "full_name": "Budi Santoso",
      "position": "Pengawas Operasional Pertama",
      "department": "Mining Operations",
      "contractor_company": "PT Toka Tene",
      "competency_type": "pengawas_operasional",
      "approval_status": "verified",
      "created_at": "2026-07-30 08:00:00"
    }
  ]
}
```

---

## 6. Penggunaan dalam Kode PHP

```php
require_once __DIR__ . '/includes/ElasticsearchService.php';

$es = ElasticsearchService::getInstance();

// 1. Cek ketersediaan service
if ($es->isAvailable()) {
    // 2. Pencarian Karyawan dengan Fuzzy Matching
    $results = $es->searchEmployees('budi', [
        'contractor_company' => 'PT Toka Tene'
    ]);

    foreach ($results['items'] as $item) {
        echo $item['full_name'];
    }
} else {
    // Fallback ke MySQL
}
```
