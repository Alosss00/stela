# STELA – Technical System & Presentation Guide

## 1. Executive Summary
**STELA (Smart Toka Electronic Letter Assign)** adalah sistem manajemen aplikasi web (Web-Based Appointment Letter Management System) yang dikembangkan khusus untuk mendigitalisasi, melacak, dan mengamankan seluruh proses pendaftaran sertifikat kompetensi karyawan hingga penerbitan Surat Penunjukan (SK). Proyek ini dirancang dengan pendekatan arsitektur *Native PHP Custom MVC* yang memprioritaskan keamanan (Security), kecepatan eksekusi (Performance), serta isolasi data antar perusahaan (Data Isolation).

## 2. System Overview
- **Tujuan STELA:** Memastikan setiap tenaga teknis dan pengawas operasional tambang memiliki sertifikat kompetensi yang valid serta Surat Penunjukan resmi yang disetujui langsung oleh Kepala Teknik Tambang (KTT).
- **Masalah yang diselesaikan:** Mencegah human-error sertifikat kedaluwarsa, sentralisasi dokumen (paperless), dan mempercepat birokrasi verifikasi dokumen secara real-time.
- **Alur Proses:** `User Request` ➔ `Admin Verification` ➔ `KTT Approval` ➔ `Accepted/Rejected` ➔ `Appointment Letter Issued` ➔ `Certificate Monitoring`.

## 3. Technology Stack
- **Backend:** Vanilla/Native PHP (versi 8.x ke atas disarankan)
- **Database:** MySQL / MariaDB
- **Search Engine:** Elasticsearch (Dengan sistem fallback otomatis ke MySQL via `ElasticsearchService.php`)
- **Frontend UI:** HTML5, CSS3, JavaScript (Vanilla & jQuery)
- **Library Tambahan:** `mpdf` (Cetak PDF SK), `phpmailer` (Notifikasi Email)

## 4. Architecture & 5. Folder Structure
Aplikasi tidak menggunakan framework pihak ketiga (seperti Laravel), melainkan menggunakan struktur MVC yang disesuaikan (*Custom Architecture*):
- `app/` (Berisi Logika Inti)
  - `Helpers/` (Logika modular: `auth_helper.php`, `upload_helper.php`, `ReportsHelper.php`)
  - `Models/` (Akses DB: `Database.php`)
  - `Security/` (Proteksi: `Firewall.php`)
  - `Services/` (Layanan luar: `NotificationService.php`, `ElasticsearchService.php`)
- `bootstrap/app.php` (Titik inisialisasi awal, mengatur header HTTP dan exception handler)
- `config/` (Pengaturan database & sistem environment)
- `resources/` (Berisi Controller dan View yang dibagi berdasarkan Role (admin, ktt, user, superadmin, dept))

## 6. Role & Permission (RBAC)
Diatur melalui tabel `roles`, `permissions`, `role_permissions` dan divalidasi via `hasPermission()` di `auth_helper.php`.

1. **Super Admin (`resources/superadmin/`)**: Memiliki akses penuh. Dapat memanipulasi *Master Data* (Departments, Companies, Competencies), Users, dan Audit Log.
2. **Admin (`resources/admin/`)**: Verifikator dokumen. Berwenang melihat semua karyawan, menekan tombol *Verify/Reject* pada sertifikat, dan mengajukan (Drafting) *Appointment Letter* ke KTT.
3. **KTT (`resources/ktt/`)**: Pihak otoritas (Approver). Mengakses dashboard persetujuan, melihat lampiran sertifikat, dan mengklik *Approve* atau *Reject* beserta Catatan (Notes).
4. **Department / User (`resources/user/` atau `resources/dept/`)**: Kontraktor/User penginput data. Hanya berhak melihat data perusahaannya sendiri. Dapat menambah karyawan, upload sertifikat (PDF), dan merespons jika KTT me-reject pengajuan (Fitur *Resubmit*).
5. **Reviewer (Jika ada)**: Bertugas mengecek ulang dokumen tanpa otoritas *Approve* (Biasanya melekat pada Admin).

## 7. Module-by-Module Analysis
- **Login & Authentication:** (`resources/auth/login.php`) Memvalidasi hash password, men-generate token CSRF, mencegah Bruteforce (Akun di-lock 15 menit), dan Session Regeneration.
- **Employee & Certificate:** (`resources/user/add_employee.php`) Menangani input formulir dan *File Upload* (diverifikasi via MIME Type `finfo_file` di `upload_helper.php`).
- **Appointments & KTT Approval:** (`resources/admin/employees.php` ➔ `resources/ktt/appointment_detail.php`) Logika persetujuan. Menyimpan `ktt_notes` dan status 'approved' atau 'rejected_by_ktt' di tabel `appointments`.
- **Reports & Monitoring:** (`app/Helpers/MonitoringHelper.php`) Logika dinamis untuk menghitung hari sisa masa aktif sertifikat. 
- **Elasticsearch/Bonsai:** (`app/Services/ElasticsearchService.php`) Digunakan untuk mempercepat kueri *search bar* berskala masif, melakukan sinkronisasi dengan data MySQL.

## 8. Database & ERD
**Tabel Utama:**
- `users` (id, username, password, role, failed_login_attempts)
- `employees` (id, full_name, company_name, department_id, verification_status)
- `employee_certifications` (id, employee_id, cert_number, expiry_date, file_path)
- `appointments` (id, employee_id, appointment_number, ktt_notes, status, approved_by)

**Relasi (ERD):**
- 1 `User` mengelola banyak `Employees` (berdasarkan `company_name`).
- 1 `Employee` memiliki banyak (1 to Many) `Employee_Certifications` (FK: `employee_id`).
- 1 `Employee` dapat memiliki banyak histori `Appointments` (FK: `employee_id`).

## 9. End-to-End Workflow
1. **Request:** User (Contractor) login, klik Add Employee. Input data & unggah PDF sertifikat. (Status: `pending`). Sistem mengirim email via `NotificationService`.
2. **Verification:** Admin login, memeriksa file sertifikat karyawan. Klik *Verify*. (Status: `verified`).
3. **Drafting:** Admin men-generate Draft *Appointment Letter* dan meneruskannya ke KTT. (Status: `pending_ktt_approval`).
4. **Approval:** KTT login, melihat draft di *Dashboard*. KTT mengklik *Approve*. (Status: `approved`).
5. **Issuance:** Sistem men-generate file PDF (Surat Penunjukan resmi) yang bisa di-*download*.
6. **Monitoring:** Halaman *Monitoring* pada dashboard secara *background* memantau `expiry_date`. Jika mendekati expired, warnanya berubah menjadi peringatan (Warning/Danger).

## 10. Important Code & File Location
- **Login/Lockout:** `resources/auth/login.php` (Baris ~125). Mengupdate `failed_login_attempts` saat gagal.
- **RBAC Check:** `app/Helpers/auth_helper.php`. Memeriksa array `$_SESSION['permissions']`.
- **Prepared Statements (SQLi Prevention):** `app/Models/Database.php` (Function `query()`). Semua parameter diikat (bind).
- **CSRF Check:** `app/Helpers/csrf_helper.php` dan `bootstrap/app.php`. Membandingkan token dengan `hash_equals()`.
- **File Upload Security:** `app/Helpers/upload_helper.php`. Mengecek tipe MIME menggunakan *finfo* bukan sekadar nama ekstensi file.

## 11. Security Analysis (Telah Diterapkan Penuh)
- **Password Hashing:** Sudah diterapkan (Menggunakan algoritma Bcrypt via `password_hash()`).
- **Session Security:** Sudah diterapkan. Menggunakan parameter `Secure`, `HttpOnly`, `SameSite=Lax`, dan mendeteksi enkripsi di belakang Proxy.
- **SQL Injection:** Sudah diterapkan 100%. Parameterized queries aktif. Celah *ORDER BY Injection* dinamis pada *Reports* telah ditambal dengan *Whitelist array validation*.
- **XSS Protection:** Sudah diterapkan. Seluruh rendering variabel ke HTML melalui fungsi `htmlspecialchars()`.
- **Brute Force & WAF:** Sudah diterapkan. *Rate Limiting* (10 req/min) via `app/Security/Firewall.php` + 15 menit akun di-lock via `login.php`.
- **Company Data Isolation:** Diterapkan. User hanya bisa query tabel `WHERE company_name = ?`.

## 12. Performance Analysis
- Elasticsearch mem-bypass limitasi teks MySQL (Sangat Baik).
- *Pagination* ditangani efisien pada level SQL (`LIMIT offset, limit`) di helper (Sangat Baik).
- Kelemahan: Ada potensi *N+1 Query Problem* pada tampilan loop (misal: mencari status sertifikat di dalam tabel HTML), yang disarankan ke depan disatukan menggunakan query *SQL JOIN* tunggal.

## 13. Strengths & 14. Weaknesses
**Kelebihan:** Sangat cepat, spesifik sesuai *workflow* pertambangan, lapisan keamanannya (Security) sekelas aplikasi enterprise.
**Kekurangan:** Arsitektur *Native PHP* membuat pemeliharaan jangka panjang lebih berat *(Technical Debt)* bagi tim IT baru dibandingkan menggunakan framework populer seperti Laravel.

## 15. Recommended Improvements
1. Refactoring tampilan `resources/` menggunakan sistem *Template Engine* agar kode PHP dan HTML tidak tercampur aduk.
2. Memigrasi ke Framework *Laravel* untuk skalabilitas fitur RESTful API Mobile App.
3. Mengganti session berbasis file menjadi *Redis* jika pengguna mencapai puluhan ribu secara serentak.

## 16. Presentation Script
**Slide 1: Overview**
*"Selamat pagi. STELA adalah sistem krusial perusahaan untuk memastikan tidak ada karyawan tambang yang bekerja tanpa sertifikat aktif. Alurnya jelas: Kontraktor mendaftar, Admin memverifikasi, KTT memberi persetujuan final."*

**Slide 2: Security & Architecture**
*"Kami menggunakan Native PHP yang super cepat. Saya pastikan dari sisi keamanan, STELA setara perbankan. Kami 100% memblokir SQL Injection, membungkus output HTML agar kebal serangan XSS, serta menanamkan Firewall otomatis yang mengunci akun penyusup (Bruteforce) selama 15 menit."*

## 17. Possible IT Questions & Answers (30 Pertanyaan)

1. **Kenapa tidak memakai framework seperti Laravel?** (Karena mengejar performa maksimum & menghindari bloatware di awal pengerjaan. Namun arsitekturnya sudah dibuat menyerupai Laravel untuk mempermudah migrasi masa depan).
2. **Bagaimana kalau penyerang mengunggah file PHP berkedok PDF?** (Sistem kami aman. Fungsi `finfo_file` mengecek struktur bit/DNA file asli, bukan ekstensinya).
3. **Bagaimana cara mencegah akun disadap (Session Hijacking)?** (Cookie dikunci dengan *HttpOnly* dan *Secure flag*. Meskipun diakses lewat jaringan publik, sesi tidak bisa dicuri oleh JavaScript).
4. **Apakah KTT A bisa melihat data anak buah KTT B?** (Tidak. Kami menerapkan Data Isolation di tingkat SQL query `WHERE`).
5. **Apa fungsi Elasticsearch di sistem ini?** (Untuk query pencarian teks yang super cepat pada jutaan baris data, agar database MySQL tidak terbeban saat orang melakukan *Search*).
6. **Apakah ada resiko SQL Injection?** (Tidak. 100% menggunakan *Prepared Statements mysqli*. Bahkan fitur *ORDER BY* di tabel laporan sudah divalidasi ke *Whitelist* array).
7. **Di mana error log disimpan jika aplikasi bermasalah?** (`bootstrap/app.php` menangkap error dan diam-diam mencatatnya di `storage/logs/error.log` tanpa membocorkan path server ke layar pengguna).
8. **Bagaimana mekanisme CSRF diterapkan?** (Setiap form POST men-generate token baru yang disimpan di `$_SESSION` dan diverifikasi dengan `hash_equals()`).
9. **Berapa lama akun terkunci jika salah password?** (Setelah 5 kali percobaan gagal, akun terkunci selama 15 menit).
10. **Bisakah admin men-bypass persetujuan KTT?** (Tidak. Secara logika bisnis di `appointment_detail.php`, status tidak akan bergeser ke 'approved' tanpa id session dari role KTT yang login).
11. **Apakah ada API di aplikasi ini?** (Saat ini belum ada endpoint REST API resmi, masih berbasis aplikasi monolithic).
12. **Apakah aplikasi berjalan lambat jika data sertifikat mencapai puluhan ribu?** (Tidak, kami menggunakan pagination SQL `LIMIT` dan index di database).
13. **Apakah ada Audit Trail/Logging untuk aksi user?** (Aksi krusial seperti penerbitan surat tercatat stempel waktu dan ID *approver*-nya di tabel).
14. **Bagaimana backup databasenya?** (Karena menggunakan MySQL standar, *backup* diurus langsung oleh infrastruktur *cloud* atau *cronjob mysqldump*).
15. **Apakah sertifikat lama (Expired) akan terhapus otomatis?** (Tidak, data tidak dihapus. Hanya statusnya yang ditandai expired pada layar *Monitoring*).
16. **Bagaimana menangani masalah N+1 Query?** (Kami akan merestrukturisasi query pada loop menggunakan relasi `LEFT JOIN` yang efisien ke depannya).
17. **Bagaimana cara mengubah konfigurasi seperti limit file upload?** (Semuanya tersentralisasi di variabel `MAX_UPLOAD_SIZE` pada file `.env` atau `config/app.php`).
18. **Apakah aplikasi ini siap untuk kontainerisasi (Docker)?** (Sangat siap. Kami sudah memiliki `.env.example` yang memudahkan portabilitas aplikasi antar-server).
19. **Mengapa menggunakan MD5 atau SHA256 di proyek ini?** (Untuk password user, kami *HANYA* menggunakan algoritma Bcrypt `password_hash()`. SHA256 hanya dipakai memvalidasi *cookie* token sementara (Remember Me)).
20. **Jika server di belakang Load Balancer/Proxy (seperti AWS), apakah koneksi dijamin HTTPS?** (Ya, kami telah memodifikasi *cookie secure check* untuk membaca parameter `HTTP_X_FORWARDED_PROTO`).
21. **Apa yang terjadi jika file konfigurasi database gagal termuat?** (Aplikasi akan terhenti (Fatal Error) yang langsung ditangkap oleh Global Exception Handler kami dan merender halaman gangguan yang *user-friendly*).
22. **Bagaimana dengan file sampah (Trash)?** (Menggunakan sistem *Soft Deletes*. Kolom `deleted_at` diisi tanggal, sehingga datanya hilang dari UI tetapi tetap utuh di database).
23. **Apakah aplikasi ini mengirim notifikasi email otomatis?** (Ya, menggunakan modul `PHPMailer` melalui *NotificationService*).
24. **Bagaimana cara sistem memutus sesi pengguna jika dibajak?** (Administrator dapat me-reset pengguna atau langsung menghapus sesi terkait dari storage, atau pengguna mengganti password yang memicu regenerasi sesi).
25. **Bagaimana jika Elasticsearch down? Apakah aplikasi akan mati?** (Tidak, sistem menggunakan pola *try-catch* yang otomatis kembali menggunakan kueri `%LIKE%` MySQL sebagai *fallback*).
26. **Apakah ada perlindungan dari serangan XSS secara global?** (Kami mengaplikasikan header `Content-Security-Policy` yang mencegah browser menjalankan *inline-scripts* dari sumber ilegal).
27. **Bisakah saya meretas sistem menggunakan teknik Path Traversal?** (Mustahil. Skrip uploader kami mengunci prefix folder `/cv/` atau `/cert/` dan *hardcoded path*, mengabaikan masukan path kustom pengguna).
28. **Bagaimana cara deploy sistem ini ke Production?** (Cukup menyalin kode via Git, import skema MySQL, copy `.env.example` ke `.env`, dan pastikan folder `uploads/` serta `storage/logs/` diberi *permission write* (755)).
29. **Apakah password superadmin di hardcode dalam script?** (Sama sekali tidak ada kredensial yang di-hardcode. Semua *seeded* dari *database*).
30. **Bagaimana struktur *Role* dibuat fleksibel?** (Menggunakan sistem tabel dinamis; kami bisa menambahkan *role* atau perizinan baru kapan saja di DB tanpa merombak hardcode `if-else`).

## 18. Final Presentation Checklist
- [ ] Buka halaman `config/app.php` saat presentasi keamanan untuk menunjukkan standar cookie tinggi.
- [ ] Buka file `auth_helper.php` untuk menunjukan mekanisme validasi hak akses RBAC (Role-Based Access Control).
- [ ] Tunjukkan layar **Monitoring** dan jelaskan secara singkat ke audiens bagaimana itu menghemat waktu KTT memilah sertifikat mati.
- [ ] Bersiap menjawab secara teknis dengan percaya diri: *Codebase ini ringkas, 100% bebas framework bloatware, dan sangat tangguh dalam segi cyber-security.*
