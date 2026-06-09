# STELA - Expertise Appointment Letter System

## Struktur Folder

```
STELA/
├── index.php                    # Login page (entry point)
├── logout.php                   # Logout handler
├── init.php                     # Bootstrap/initialization (optional)
├── composer.json                # Dependencies
│
├── pages/                       # Halaman aplikasi per role
│   ├── admin/                   # Admin pages
│   │   ├── dashboard.php
│   │   ├── employees.php
│   │   ├── positions.php
│   │   ├── certifications.php
│   │   ├── supervision_areas.php
│   │   ├── appointments.php
│   │   ├── reports.php
│   │   └── change_password.php
│   │
│   ├── user/                    # Company User pages
│   │   ├── dashboard.php
│   │   ├── employees.php
│   │   ├── employee_detail.php
│   │   ├── appointments.php
│   │   ├── appointment_detail.php
│   │   └── reports.php
│   │
│   ├── dept/                    # Department User pages
│   │   ├── dashboard.php
│   │   ├── employees.php
│   │   ├── employee_detail.php
│   │   ├── appointments.php
│   │   └── reports.php
│   │
│   └── ktt/                     # KTT pages
│       └── approval.php
│
├── api/                         # API endpoints
│   ├── get_employee_certs.php
│   ├── get_sub_competencies.php
│   ├── get_appointment_details.php
│   └── get_approval_detail.php
│
├── includes/                    # Core PHP includes
│   ├── config.php               # Configuration (DB, site settings)
│   ├── db.php                   # Database class
│   ├── auth.php                 # Authentication & authorization
│   ├── header.php               # HTML header + navigation
│   ├── footer.php               # HTML footer + scripts
│   └── notifications.php        # Email notifications
│
├── assets/                      # Static files
│   ├── css/
│   │   ├── style.css
│   │   └── language-switcher.css
│   ├── js/
│   │   ├── script.js
│   │   └── language-switcher.js
│   ├── uploads/                 # User uploads
│   ├── templates/               # Document templates
│   └── Logo/                    # Logo images
│
├── migrations/                  # Database migrations
│   └── *.sql, *.php
│
├── utils/                       # Utility/debug scripts
│   ├── *.php                    # PHP utilities
│   └── python/                  # Python scripts
│
├── docs/                        # Documentation
│   ├── *.md
│   └── demos/                   # Demo files
│
├── database/                    # Database dumps
│   └── mining_appointment.sql
│
├── logs/                        # Application logs
│
├── routes/                      # Route definitions
│
└── vendor/                      # Composer dependencies
```

## Routing & Navigation

### Login Flow
1. User mengakses `index.php` (login page)
2. Setelah login, redirect ke dashboard sesuai role:
   - Admin → `pages/admin/dashboard.php`
   - User (Company) → `pages/user/dashboard.php`
   - User (Department) → `pages/dept/dashboard.php`
   - KTT → `pages/ktt/approval.php`

### URL Pattern
Semua halaman aplikasi berada di folder `pages/[role]/`:
- Admin: `/pages/admin/xxx.php`
- User: `/pages/user/xxx.php`
- Dept: `/pages/dept/xxx.php`
- KTT: `/pages/ktt/xxx.php`
- API: `/api/xxx.php`

### Include Paths
File di `pages/[role]/` menggunakan relative path ke includes:
```php
require_once '../../includes/auth.php';
require_once '../../includes/header.php';
```

### Asset Paths
Header.php menggunakan BASE_URL dinamis:
```php
<link href="<?php echo BASE_URL; ?>/assets/css/style.css">
<script src="<?php echo BASE_URL; ?>/assets/js/script.js">
```

## Menjalankan Aplikasi

1. Pastikan XAMPP/WAMP/Laragon sudah berjalan
2. Letakkan folder ini di `htdocs` atau `www`
3. Import database dari `database/mining_appointment.sql`
4. Akses `http://localhost/[folder-name]/`

## Konfigurasi Database

Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mining_appointment');
```

## Role Users

| Role | Akses |
|------|-------|
| admin | Full access - manage all data |
| user | Company user - manage own employees |
| department_user | Department user - manage department employees |
| ktt | KTT - approval only |
