-- ================================================
-- MIGRATION: Resubmit System Enhancement
-- Description: Menambahkan kolom-kolom untuk tracking resubmit system
-- Date: 2026-02-12
-- ================================================

-- Cek apakah kolom sudah ada sebelum menambahkan
-- Jalankan query ini di database Anda

-- 1. Tambah kolom resubmit_count
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS resubmit_count INT DEFAULT 0 
COMMENT 'Jumlah kali employee resubmit karena KTT reject';

-- 2. Tambah kolom requires_ktt_msm_review
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS requires_ktt_msm_review TINYINT(1) DEFAULT 0 
COMMENT 'Flag: Apakah perlu direview KTT MSM (1=yes, 0=no)';

-- 3. Tambah kolom requires_ktt_ttn_review
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS requires_ktt_ttn_review TINYINT(1) DEFAULT 0 
COMMENT 'Flag: Apakah perlu direview KTT TTN (1=yes, 0=no)';

-- 4. Tambah kolom last_rejected_by_ktt
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS last_rejected_by_ktt VARCHAR(10) NULL 
COMMENT 'Nilai: msm atau ttn - KTT yang terakhir reject';

-- 5. Tambah kolom rejected_by_ktt_user_id
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS rejected_by_ktt_user_id INT NULL 
COMMENT 'User ID dari KTT yang melakukan reject';

-- 6. Tambah kolom admin_approval_action
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS admin_approval_action VARCHAR(20) NULL 
COMMENT 'Nilai: send_to_user atau send_to_ktt - Action admin saat review rejection';

-- 7. Tambah kolom admin_approval_notes
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS admin_approval_notes TEXT NULL 
COMMENT 'Notes dari admin saat review KTT rejection';

-- 8. Tambah kolom admin_approved_by
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS admin_approved_by INT NULL 
COMMENT 'User ID admin yang melakukan review rejection dari KTT';

-- 9. Tambah kolom admin_approved_date
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS admin_approved_date DATETIME NULL 
COMMENT 'Tanggal/waktu admin melakukan review rejection';

-- 10. Tambah kolom resubmit_reason
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS resubmit_reason TEXT NULL 
COMMENT 'Kombinasi notes dari KTT + Admin untuk user saat resubmit';

-- ================================================
-- INDEX untuk performance
-- ================================================

-- Index untuk filter KTT MSM
CREATE INDEX IF NOT EXISTS idx_ktt_msm_review 
ON appointments(status, ktt_msm_status, requires_ktt_msm_review);

-- Index untuk filter KTT TTN
CREATE INDEX IF NOT EXISTS idx_ktt_ttn_review 
ON appointments(status, ktt_ttn_status, requires_ktt_ttn_review);

-- Index untuk admin review rejection
CREATE INDEX IF NOT EXISTS idx_admin_review 
ON appointments(status, last_rejected_by_ktt);

-- Index untuk resubmit tracking
CREATE INDEX IF NOT EXISTS idx_resubmit_count 
ON appointments(resubmit_count);

-- ================================================
-- Update data lama (jika perlu)
-- ================================================

-- Set default 0 untuk resubmit_count pada data lama
UPDATE appointments 
SET resubmit_count = 0 
WHERE resubmit_count IS NULL;

-- Set default 0 untuk requires flags pada data lama
UPDATE appointments 
SET requires_ktt_msm_review = 0,
    requires_ktt_ttn_review = 0
WHERE requires_ktt_msm_review IS NULL 
   OR requires_ktt_ttn_review IS NULL;

-- ================================================
-- Verification Queries
-- ================================================

-- Cek apakah semua kolom sudah ada
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'appointments'
  AND COLUMN_NAME IN (
    'resubmit_count',
    'requires_ktt_msm_review',
    'requires_ktt_ttn_review',
    'last_rejected_by_ktt',
    'rejected_by_ktt_user_id',
    'admin_approval_action',
    'admin_approval_notes',
    'admin_approved_by',
    'admin_approved_date',
    'resubmit_reason'
)
ORDER BY ORDINAL_POSITION;

-- Cek data appointments yang sedang dalam proses resubmit
SELECT 
    a.id,
    a.appointment_number,
    e.employee_name,
    a.status,
    a.resubmit_count,
    a.last_rejected_by_ktt,
    a.requires_ktt_msm_review,
    a.requires_ktt_ttn_review,
    a.ktt_msm_status,
    a.ktt_ttn_status
FROM appointments a
JOIN employees e ON a.employee_id = e.id
WHERE a.status IN ('rejected_by_ktt', 'rejected')
   OR a.resubmit_count > 0
ORDER BY a.resubmit_count DESC, a.created_at DESC;

-- ================================================
-- ROLLBACK (jika perlu)
-- ================================================
-- HATI-HATI: Ini akan menghapus kolom dan data!
-- Hanya jalankan jika ingin rollback migration

/*
ALTER TABLE appointments DROP COLUMN IF EXISTS resubmit_count;
ALTER TABLE appointments DROP COLUMN IF EXISTS requires_ktt_msm_review;
ALTER TABLE appointments DROP COLUMN IF EXISTS requires_ktt_ttn_review;
ALTER TABLE appointments DROP COLUMN IF EXISTS last_rejected_by_ktt;
ALTER TABLE appointments DROP COLUMN IF EXISTS rejected_by_ktt_user_id;
ALTER TABLE appointments DROP COLUMN IF EXISTS admin_approval_action;
ALTER TABLE appointments DROP COLUMN IF EXISTS admin_approval_notes;
ALTER TABLE appointments DROP COLUMN IF EXISTS admin_approved_by;
ALTER TABLE appointments DROP COLUMN IF EXISTS admin_approved_date;
ALTER TABLE appointments DROP COLUMN IF EXISTS resubmit_reason;

DROP INDEX IF EXISTS idx_ktt_msm_review ON appointments;
DROP INDEX IF EXISTS idx_ktt_ttn_review ON appointments;
DROP INDEX IF EXISTS idx_admin_review ON appointments;
DROP INDEX IF EXISTS idx_resubmit_count ON appointments;
*/

-- ================================================
-- END OF MIGRATION
-- ================================================
