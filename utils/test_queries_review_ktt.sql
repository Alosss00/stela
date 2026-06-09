-- ============================================
-- QUERY HELPER: Testing Review KTT Function
-- ============================================

-- 1. CEK DATA REJECTED_BY_KTT
-- Lihat surat yang menunggu review admin
SELECT 
    a.id,
    a.appointment_number,
    a.status,
    e.full_name as employee_name,
    e.employee_code,
    a.ktt_rejection_notes,
    a.created_at
FROM appointments a
JOIN employees e ON a.employee_id = e.id
WHERE a.status = 'rejected_by_ktt'
ORDER BY a.created_at DESC;

-- 2. BUAT DATA TEST (Jika tidak ada data)
-- Ubah appointment yang ada jadi rejected_by_ktt
UPDATE appointments 
SET 
    status = 'rejected_by_ktt',
    ktt_rejection_notes = 'TEST: Data sertifikat tidak lengkap atau masa berlaku habis'
WHERE id = 1  -- Ganti dengan ID yang ada
LIMIT 1;

-- 3. CEK HASIL SETELAH REVIEW
-- Lihat perubahan status setelah admin review
SELECT 
    a.id,
    a.appointment_number,
    a.status,
    a.admin_approval_action,
    a.admin_approval_notes,
    a.admin_approved_by,
    u.full_name as admin_name,
    a.admin_approved_date
FROM appointments a
LEFT JOIN users u ON a.admin_approved_by = u.id
WHERE a.id = 1  -- Ganti dengan ID yang direview
ORDER BY a.admin_approved_date DESC;

-- 4. RESET DATA TEST (Jika perlu test ulang)
-- Kembalikan status ke rejected_by_ktt
UPDATE appointments 
SET 
    status = 'rejected_by_ktt',
    admin_approval_action = NULL,
    admin_approval_notes = NULL,
    admin_approved_by = NULL,
    admin_approved_date = NULL
WHERE id = 1  -- Ganti dengan ID yang ada
LIMIT 1;

-- 5. LIHAT SEMUA STATUS APPOINTMENTS
-- Overview semua surat
SELECT 
    status,
    COUNT(*) as jumlah
FROM appointments
GROUP BY status
ORDER BY jumlah DESC;

-- 6. CEK KTT APPROVALS
-- Lihat history approval KTT
SELECT 
    ka.id,
    ka.appointment_id,
    a.appointment_number,
    ka.action,
    ka.approval_notes,
    u.full_name as ktt_name,
    ka.approval_date
FROM ktt_approvals ka
JOIN appointments a ON ka.appointment_id = a.id
JOIN users u ON ka.ktt_user_id = u.id
WHERE ka.action = 'reject'
ORDER BY ka.approval_date DESC
LIMIT 10;

-- 7. SIMULATE FULL WORKFLOW (Test Complete)
-- Step 1: Buat appointment baru dengan status pending
-- Step 2: KTT reject (manual atau via approval page)
-- Step 3: Auto-fix akan ubah jadi rejected_by_ktt
-- Step 4: Admin review via tombol di kolom AKSI
-- Step 5: Status berubah jadi pending (accept) atau rejected (reject)

-- Untuk simulate reject by KTT:
INSERT INTO ktt_approvals (appointment_id, ktt_user_id, action, approval_notes, approval_date)
VALUES (1, 2, 'reject', 'Test rejection dari KTT', NOW());
-- Catatan: Ganti appointment_id dan ktt_user_id sesuai data Anda

-- 8. CHECK AUTO-FIX QUERY
-- Lihat appointments yang bisa di auto-fix
SELECT 
    a.id,
    a.appointment_number,
    a.status,
    COUNT(ka.id) as rejection_count
FROM appointments a
LEFT JOIN ktt_approvals ka ON a.appointment_id = ka.appointment_id AND ka.action = 'reject'
WHERE a.status = 'pending'
GROUP BY a.id
HAVING rejection_count > 0;

-- ============================================
-- EXPECTED BEHAVIOR:
-- ============================================
-- 1. Surat dengan status 'rejected_by_ktt' muncul di tabel
-- 2. Kolom Status menampilkan: "⚠ PERLU REVIEW"
-- 3. Kolom AKSI menampilkan:
--    - Info box merah dengan alasan penolakan
--    - Tombol Accept (hijau) dan Reject (merah)
-- 4. Klik tombol → Modal muncul
-- 5. Submit dengan catatan → Status berubah
--    - Accept → status = 'pending'
--    - Reject → status = 'rejected'
-- ============================================
