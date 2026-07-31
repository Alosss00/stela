-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 09, 2026 at 05:15 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mining_appointment`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int NOT NULL,
  `appointment_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `employee_id` int NOT NULL,
  `position_id` int NOT NULL,
  `appointment_date` date NOT NULL,
  `effective_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected','expired','verified','rejected_by_ktt','pending_admin_review') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `auto_generated` tinyint(1) DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `letter_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `admin_approved_by` int DEFAULT NULL,
  `admin_approved_date` datetime DEFAULT NULL,
  `admin_approval_action` enum('send_to_user','send_to_ktt') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `admin_approval_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `rejected_by_ktt_user_id` int DEFAULT NULL,
  `ktt1_approved_by` int DEFAULT NULL,
  `ktt1_approved_date` datetime DEFAULT NULL,
  `ktt2_approved_by` int DEFAULT NULL,
  `ktt2_approved_date` datetime DEFAULT NULL,
  `ktt_msm_status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending' COMMENT 'KTT MSM approval status',
  `ktt_ttn_status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending' COMMENT 'KTT TTN approval status',
  `requires_ktt_msm_review` tinyint(1) DEFAULT '1' COMMENT 'Flag: should KTT MSM review this appointment?',
  `requires_ktt_ttn_review` tinyint(1) DEFAULT '1' COMMENT 'Flag: should KTT TTN review this appointment?',
  `resubmit_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'Combined admin + KTT rejection notes for user',
  `last_rejected_by_ktt` enum('msm','ttn') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Which KTT type rejected last (msm or ttn)',
  `approved_date` datetime DEFAULT NULL,
  `approval_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `final_approval_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resubmit_count` int DEFAULT '0',
  `last_rejection_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'Stores rejection reason from last KTT rejection',
  `last_rejection_by_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Stores name of KTT who rejected',
  `last_rejection_date` datetime DEFAULT NULL COMMENT 'Stores date of last rejection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `appointment_number`, `employee_id`, `position_id`, `appointment_date`, `effective_date`, `expiry_date`, `status`, `auto_generated`, `notes`, `letter_content`, `created_by`, `approved_by`, `admin_approved_by`, `admin_approved_date`, `admin_approval_action`, `admin_approval_notes`, `rejected_by_ktt_user_id`, `ktt1_approved_by`, `ktt1_approved_date`, `ktt2_approved_by`, `ktt2_approved_date`, `ktt_msm_status`, `ktt_ttn_status`, `requires_ktt_msm_review`, `requires_ktt_ttn_review`, `resubmit_reason`, `last_rejected_by_ktt`, `approved_date`, `approval_notes`, `final_approval_date`, `created_at`, `updated_at`, `resubmit_count`, `last_rejection_notes`, `last_rejection_by_name`, `last_rejection_date`) VALUES
(1, 'SP/2026/0001', 1, 2, '2026-01-07', '2026-01-07', NULL, 'approved', 1, 'Auto-generated setelah verifikasi data', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-07 10:17:09', 8, '2026-01-07 10:18:22', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-07 10:18:22', 'setuju', '2026-01-07 10:18:22', '2026-01-07 00:15:52', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(2, 'SP/2026/3320', 3, 4, '2026-01-07', '2026-01-07', '0000-00-00', 'approved', 0, '', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-07 10:17:48', 8, '2026-01-07 10:18:36', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-07 10:18:36', 'setuju\r\n', '2026-01-07 10:18:36', '2026-01-07 01:47:12', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(3, 'SP/2026/5603', 4, 6, '2026-01-07', '2026-01-07', '0000-00-00', 'approved', 0, '', NULL, 4, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-07 11:26:28', 8, '2026-01-07 11:27:18', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-07 11:27:18', 'berhasil', '2026-01-07 11:27:18', '2026-01-07 03:12:54', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(4, 'SP/2026/5604', 18, 8, '2026-01-09', '2026-01-09', NULL, 'approved', 0, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 5, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-09 13:36:34', NULL, NULL, '2026-01-09 05:31:47', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(5, 'SP/2026/5605', 19, 8, '2026-01-09', '2026-01-09', NULL, 'approved', 0, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-09 13:55:27', 8, '2026-01-09 13:55:49', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-09 13:55:49', '', '2026-01-09 13:55:49', '2026-01-09 05:47:41', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(6, 'SP/2026/5606', 21, 3, '2026-01-12', '2026-01-12', NULL, 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-12 08:09:55', 8, '2026-01-12 08:10:46', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-12 08:10:46', '', '2026-01-12 08:10:46', '2026-01-12 00:07:53', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(7, 'SP/2026/5607', 22, 3, '2026-01-13', '2026-01-13', '2027-06-23', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 5, 7, NULL, NULL, NULL, NULL, NULL, 8, '2026-01-13 08:39:42', 7, '2026-01-13 08:40:02', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-13 08:40:02', '', '2026-01-13 08:40:02', '2026-01-13 00:38:21', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(8, 'SP/2026/5608', 24, 3, '2026-01-13', '2026-01-13', '2028-11-22', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 6, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-13 10:33:03', 8, '2026-01-13 10:49:17', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-13 10:49:17', '', '2026-01-13 10:49:17', '2026-01-13 02:07:40', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(9, 'SP/2026/5609', 23, 3, '2026-01-13', '2026-01-13', '2029-11-18', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 6, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-13 10:33:09', 8, '2026-01-13 10:49:23', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-13 10:49:23', '', '2026-01-13 10:49:23', '2026-01-13 02:08:12', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(10, 'SP/2026/5610', 25, 3, '2026-01-13', '2026-01-13', NULL, 'rejected', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 4, 7, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', 1, 1, NULL, NULL, '2026-01-13 14:17:05', 'sertifikat expired\r\n', NULL, '2026-01-13 06:15:44', '2026-01-13 06:17:05', 0, NULL, NULL, NULL),
(11, 'SP/2026/5611', 26, 3, '2026-01-13', '2026-01-13', '2029-11-18', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 4, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-13 14:17:44', 8, '2026-01-13 14:34:07', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-13 14:34:07', '', '2026-01-13 14:34:07', '2026-01-13 06:16:06', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(12, 'SP/2026/5612', 27, 3, '2026-01-19', '2026-01-19', '2027-01-01', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-19 22:14:56', 8, '2026-01-19 22:15:07', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-19 22:15:07', '', '2026-01-19 22:15:07', '2026-01-19 14:14:10', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(13, 'SP/2026/5613', 28, 3, '2026-01-21', '2026-01-21', '2027-01-01', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', 'Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan. \nMaka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Pengawas Operasional Pertambangan dengan tugas, tanggung jawab dan wewenang sebagai berikut:\n1)	Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;\n2)	Melaksanakan inspeksi, pemeriksaan, dan pengujian;\n3)	Bertanggung jawab atas keselamatan, kesehatan, dan kesejahtaeraan dari semua orang yang ditugaskan kepadanya;\n4)	Membuat dan menandatangai laporan pemeriksaan, inspeksi, dan pengujian;\n5)	Menerapkan Sistem Manajemen Keselamatan Pertambangan;\n6)	Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;\n7)	Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;\n8)	Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;\n9)	Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindak lanjuti;\n', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-21 14:15:40', 8, '2026-01-21 14:15:57', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-21 14:15:57', '', '2026-01-21 14:15:57', '2026-01-20 23:21:19', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(15, '001/PT/MSM/01/2026', 30, 3, '2026-01-21', '2026-01-21', '2028-11-01', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', 'Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan. \r\nMaka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Pengawas Operasional Pertambangan dengan tugas, tanggung jawab dan wewenang sebagai berikut:\r\n1)	Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;\r\n2)	Melaksanakan inspeksi, pemeriksaan, dan pengujian;\r\n3)	Bertanggung jawab atas keselamatan, kesehatan, dan kesejahtaeraan dari semua orang yang ditugaskan kepadanya;\r\n4)	Membuat dan menandatangai laporan pemeriksaan, inspeksi, dan pengujian;\r\n5)	Menerapkan Sistem Manajemen Keselamatan Pertambangan;\r\n6)	Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;\r\n7)	Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;\r\n8)	Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;\r\n9)	Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindak lanjuti;\r\n', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-21 14:15:44', 8, '2026-01-21 14:16:05', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-21 14:16:05', '', '2026-01-21 14:16:05', '2026-01-21 02:26:30', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(16, '001/PO/MSM/01/2026', 31, 3, '2026-01-21', '2026-01-21', '2029-11-18', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', 'Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan. \r\nMaka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Pengawas Operasional Pertambangan dengan tugas, tanggung jawab dan wewenang sebagai berikut:\r\n1)	Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;\r\n2)	Melaksanakan inspeksi, pemeriksaan, dan pengujian;\r\n3)	Bertanggung jawab atas keselamatan, kesehatan, dan kesejahtaeraan dari semua orang yang ditugaskan kepadanya;\r\n4)	Membuat dan menandatangai laporan pemeriksaan, inspeksi, dan pengujian;\r\n5)	Menerapkan Sistem Manajemen Keselamatan Pertambangan;\r\n6)	Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;\r\n7)	Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;\r\n8)	Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;\r\n9)	Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindak lanjuti;', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-21 14:15:47', 8, '2026-01-21 14:16:09', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-21 14:16:09', '', '2026-01-21 14:16:09', '2026-01-21 03:28:39', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(17, '001/PO/TTN/01/2026', 6, 3, '2026-01-22', '2026-01-22', '2029-01-22', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.</p><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Pengawas Operasional dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahteraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangani laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindaklanjuti;<br>Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.</li></ol><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Pengawas Operasional dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahteraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangani laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindaklanjuti;Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.</li></ol><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Pengawas Operasional dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahteraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangani laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindaklanjuti;</li></ol>', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-01-22 06:41:57', 8, '2026-01-22 06:42:13', 'approved', 'approved', 0, 0, NULL, NULL, '2026-01-22 06:42:13', '', '2026-01-22 06:42:13', '2026-01-21 22:39:32', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(20, '001/TT/MSM/01/2026', 35, 3, '2026-01-27', '2026-01-27', '2028-01-23', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', 'Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Teknis.\r\nMaka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai;\r\nPengawas Teknis dengan tugas, tanggung jawab dan wewenang sebagai berikut:\r\n1)	Bertanggung jawab kepada KTT/PTL untuk keselamatan pemasangan dan pekerjaan serta \r\npemeliharan yang benar semua Sarana, Prasarana, Instalasi, dan Peralatan pertambangan (SPIP) \r\nyang menjadi tugasnya;\r\n2)	Merencanakan dan menekankan dilaksanakannya jadwal pemeliharaan yang telah direncanakan \r\nserta semua perbaikan Sarana, Prasarana, Instalasi, dan Peralatan pertambangan (SPIP) yang \r\ndipergunakan.\r\n3)	Mengawasi dan memeriksa semua Sarana, Prasarana, Instalasi, dan Peralatan pertambangan \r\n(SPIP) dalam ruang lingkup yang menjadi tanggung jawabnya;\r\n4)	Menjamin bahwa selalu dilaksanakan penyelidikan, pemeriksaan, dan pengujian Sarana, \r\nPrasarana, Instalasi, dan Peralatan pertambangan (SPIP);\r\n5)	Melaksanakan penyelidikan, pemeriksaan, dan pengujian Sarana, Prasarana, Instalasi, dan \r\nPeralatan pertambangan (SPIP) sebelum digunakan, setelah dipasang kembali, dan/atau \r\ndiperbaiki; dan\r\n6)	Membuat dan menandatangani laporan dari penyelidikan, pemeriksaan, dan pengujian Sarana, \r\nPrasarana, Instalasi, dan Peralatan pertambangan (SPIP);', 3, 8, 3, '2026-02-02 13:11:51', 'send_to_ktt', 'Tidak ada catatan', 7, 7, '2026-02-02 14:39:03', 8, '2026-02-02 14:39:30', 'approved', 'approved', 0, 0, NULL, NULL, '2026-02-02 14:39:30', '', '2026-02-02 14:39:30', '2026-01-26 21:36:41', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(22, '001/TT/MSM/02/2026', 38, 3, '2026-02-02', '2026-02-02', '2027-11-29', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-02 14:39:07', 8, '2026-02-02 14:39:37', 'approved', 'approved', 0, 0, NULL, NULL, '2026-02-02 14:39:37', '', '2026-02-02 14:39:37', '2026-02-02 06:32:00', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(23, '002/TT/MSM/02/2026', 39, 3, '2026-02-02', '2026-02-02', '2029-11-18', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan peraturan tentang Keselamatan dan Kesehatan Kerja Pengoperasian Alat Berat, terkait penunjukan Operator Alat Berat.<strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Tenaga Teknis - Operator Alat Angkut / Rigger dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><figure class=\"table\"><table><tbody><tr><td>No</td><td>Alat/Unit</td><td>Merk/Type/Seri</td></tr><tr><td>1</td><td>LV</td><td>&nbsp;</td></tr><tr><td>2</td><td>Bus</td><td>&nbsp;</td></tr><tr><td>3</td><td>ADT</td><td>A40F, A45G, A40JA60H</td></tr><tr><td>4</td><td>Compactor</td><td>BMG BW211D-40, BW212D-40, BW212D-5 SL, CAT CS533E</td></tr><tr><td>5</td><td>Dozer</td><td>KOM D85ESS-2A, CAT D8, D9R</td></tr><tr><td>6</td><td>Dump Truck</td><td>Liugong DW90A, DW105A, Volvo FMX440</td></tr><tr><td>7</td><td>Excavator</td><td>Liugong L906F,Volvo EC210BL,EC210D,EC480DL</td></tr><tr><td>8</td><td>Fuel Truck</td><td>REN KERAX 380</td></tr><tr><td>9</td><td>Service Truck</td><td>Volvo FMX400</td></tr></tbody></table></figure><ol><li>Mengoperasikan alat berat sesuai dengan spesifikasi teknis dan standar operasional prosedur yang berlaku;</li><li>Melakukan pemeriksaan kondisi alat berat sebelum dan sesudah pengoperasian (Pre-operation dan Post-operation check);</li><li>Melaksanakan pekerjaan dengan memperhatikan aspek keselamatan kerja, produktivitas dan efisiensi;</li><li>Melaporkan setiap kerusakan atau kelainan yang terjadi pada alat berat yang dioperasikan;</li><li>Membuat laporan operasional harian (Daily Report) sesuai dengan ketentuan yang berlaku;</li><li>Mematuhi rambu-rambu dan aturan lalu lintas di area tambang;</li></ol>', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-02 14:39:10', 8, '2026-02-02 14:39:33', 'approved', 'approved', 0, 0, NULL, NULL, '2026-02-02 14:39:33', '', '2026-02-02 14:39:33', '2026-02-02 06:32:11', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(24, '001/PO/MSM/02/2026', 40, 3, '2026-02-02', '2026-02-02', '2027-11-29', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.</p><figure class=\"table\"><table><tbody><tr><td>awdawdawdwadadad</td><td>awdwadawdaw</td><td>adawda</td><td>awdadawd</td></tr><tr><td>awdaw</td><td>adad</td><td>dawdaw</td><td>dawd</td></tr><tr><td>adwa</td><td>awdawd</td><td>dawwd</td><td>adwad</td></tr></tbody></table></figure><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Pengawas Operasional - Pengawas Operasional Madya dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahteraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangani laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindaklanjuti;</li></ol>', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-02 14:39:13', 8, '2026-02-02 14:39:40', 'approved', 'approved', 0, 0, NULL, NULL, '2026-02-02 14:39:40', '', '2026-02-02 14:39:40', '2026-02-02 06:37:18', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(25, '001/PT/MSM/02/2026', 44, 3, '2026-02-04', '2026-02-04', '2029-11-18', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan peraturan perundang-undangan yang berlaku tentang Keselamatan dan Kesehatan Kerja Pertambangan, terkait penunjukan Pengawas Teknis.</p><p>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai <strong>Tenaga Medis</strong>, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Melakukan pengawasan terhadap pelaksanaan kegiatan teknis di bidang teknis;</li><li>Memberikan arahan teknis kepada tenaga pelaksana dalam melaksanakan pekerjaan sesuai standar operasional;</li><li>Melakukan pemeriksaan dan evaluasi kondisi peralatan secara berkala;</li><li>Memastikan penerapan standar keselamatan dan kesehatan kerja dalam setiap kegiatan teknis;</li><li>Membuat laporan pelaksanaan kegiatan teknis dan rekomendasi perbaikan;</li></ol>', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-04 13:13:34', 8, '2026-02-04 13:13:51', 'approved', 'approved', 0, 0, NULL, NULL, '2026-02-04 13:13:51', '', '2026-02-04 13:13:51', '2026-02-04 01:51:41', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(26, '002/PT/MSM/02/2026', 43, 3, '2026-02-04', '2026-02-04', '2029-11-18', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan peraturan perundang-undangan yang berlaku tentang Keselamatan dan Kesehatan Kerja Pertambangan, terkait penunjukan Pengawas Teknis.</p><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Pengawas Teknis - Ahli Listrik dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melakukan pengawasan terhadap pelaksanaan kegiatan teknis di bidang teknis;</li><li>Memberikan arahan teknis kepada tenaga pelaksana dalam melaksanakan pekerjaan sesuai standar operasional;</li><li>Melakukan pemeriksaan dan evaluasi kondisi peralatan secara berkala;</li><li>Memastikan penerapan standar keselamatan dan kesehatan kerja dalam setiap kegiatan teknis;</li><li>Membuat laporan pelaksanaan kegiatan teknis dan rekomendasi perbaikan;</li></ol>', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-04 13:13:37', 8, '2026-02-04 13:13:56', 'approved', 'approved', 0, 0, NULL, NULL, '2026-02-04 13:13:56', '', '2026-02-04 13:13:56', '2026-02-04 01:58:51', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(27, '002/PO/MSM/02/2026', 42, 3, '2026-02-04', '2026-02-04', '2029-11-18', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.</p><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Pengawas Operasional - Pengawas Operasional Pertama dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahteraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangani laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindaklanjuti;</li></ol>', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-04 13:13:41', 8, '2026-02-04 13:13:59', 'approved', 'approved', 0, 0, NULL, NULL, '2026-02-04 13:13:59', '', '2026-02-04 13:13:59', '2026-02-04 03:14:39', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(28, '003/PO/MSM/02/2026', 41, 3, '2026-02-04', '2026-02-04', '2029-11-18', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p style=\"margin-left:0in;text-align:justify;\">Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.&nbsp;</p><p style=\"margin-left:0in;text-align:justify;\">Maka&nbsp;dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; <strong><u>Pengawas Operasional Pertambangan</u></strong> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahtaeraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangai laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindak lanjuti;</li></ol>', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-07 10:40:24', 8, '2026-02-07 10:43:00', 'approved', 'approved', 0, 0, NULL, NULL, '2026-02-07 10:43:00', '', '2026-02-07 10:43:00', '2026-02-04 05:17:23', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(29, '003/TT/MSM/02/2026', 45, 3, '2026-02-05', '2026-02-05', '2029-05-24', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</p><p>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; <strong>Tenaga Teknis - Higiene Industri</strong> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;</li></ol>', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-07 10:40:27', 8, '2026-02-07 10:43:03', 'approved', 'approved', 0, 0, NULL, NULL, '2026-02-07 10:43:03', '', '2026-02-07 10:43:03', '2026-02-05 02:15:37', '2026-02-11 03:43:46', 0, NULL, NULL, NULL),
(37, '004/TT/MSM/02/2026', 53, 3, '2026-02-11', '2026-02-11', '2028-02-11', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 7, 3, '2026-02-11 13:08:08', 'send_to_ktt', 'No notes', NULL, 7, '2026-02-11 13:08:30', 8, '2026-02-11 13:07:11', 'approved', 'approved', 1, 0, NULL, NULL, '2026-02-11 13:08:30', '', '2026-02-11 13:08:30', '2026-02-11 05:06:44', '2026-02-11 05:08:30', 0, NULL, NULL, NULL),
(38, '005/TT/MSM/02/2026', 54, 3, '2026-02-11', '2026-02-11', '2028-02-11', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 7, 3, '2026-02-11 13:29:17', 'send_to_ktt', 'No notes', NULL, 7, '2026-02-11 13:29:29', 8, '2026-02-11 13:28:52', 'approved', 'approved', 1, 0, NULL, NULL, '2026-02-11 13:29:29', '', '2026-02-11 13:29:29', '2026-02-11 05:13:47', '2026-02-11 05:29:29', 0, NULL, NULL, NULL),
(39, '006/TT/MSM/02/2026', 55, 3, '2026-02-11', '2026-02-11', '2028-02-11', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, 3, '2026-02-11 13:38:43', 'send_to_ktt', 'No notes', NULL, 7, '2026-02-11 13:32:13', 8, '2026-02-11 13:48:16', 'approved', 'approved', 0, 1, NULL, NULL, '2026-02-11 13:48:16', '', '2026-02-11 13:48:16', '2026-02-11 05:31:43', '2026-02-11 05:48:16', 0, NULL, NULL, NULL),
(40, '001/TT/TTN/02/2026', 56, 3, '2026-02-11', '2026-02-11', '2028-02-11', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, NULL, NULL, NULL, NULL, 8, NULL, NULL, 8, '2026-02-11 14:05:54', 'approved', 'approved', 0, 1, NULL, 'ttn', '2026-02-11 14:05:54', '', '2026-02-11 14:05:54', '2026-02-11 05:50:09', '2026-02-11 06:05:54', 0, NULL, NULL, NULL),
(41, '004/PO/MSM/02/2026', 57, 3, '2026-02-11', '2026-02-11', '2028-02-11', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, 3, '2026-02-12 10:50:20', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-02-11 19:13:44', 8, '2026-02-12 11:39:20', 'approved', 'approved', 0, 1, NULL, NULL, '2026-02-12 11:39:20', '', '2026-02-12 11:39:20', '2026-02-11 11:13:27', '2026-02-12 03:39:20', 0, NULL, NULL, NULL),
(42, '005/PO/MSM/02/2026', 60, 3, '2026-02-12', '2026-02-12', '2027-09-10', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p style=\"margin-left:0in;text-align:justify;\">Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.&nbsp;</p><p style=\"margin-left:0in;text-align:justify;\">Maka&nbsp;dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai <strong><u>Pengawas Operasional Pertambangan</u></strong> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahtaeraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangai laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindak lanjuti;</li></ol>', 5, 7, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-12 10:49:46', 8, '2026-02-12 10:48:03', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-12 10:49:46', '', '2026-02-12 10:49:46', '2026-02-12 01:50:23', '2026-02-12 02:49:46', 0, NULL, NULL, NULL),
(43, '003/PT/MSM/02/2026', 59, 3, '2026-02-12', '2026-02-12', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan peraturan perundang-undangan yang berlaku tentang Keselamatan dan Kesehatan Kerja Pertambangan, terkait penunjukan Pengawas Teknis.</p><p>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai <strong><u>Pengawas Teknis</u></strong> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Melakukan pengawasan terhadap pelaksanaan kegiatan teknis di bidang teknis;</li><li>Memberikan arahan teknis kepada tenaga pelaksana dalam melaksanakan pekerjaan sesuai standar operasional;</li><li>Melakukan pemeriksaan dan evaluasi kondisi peralatan secara berkala;</li><li>Memastikan penerapan standar keselamatan dan kesehatan kerja dalam setiap kegiatan teknis;</li><li>Membuat laporan pelaksanaan kegiatan teknis dan rekomendasi perbaikan;</li></ol>', 4, 7, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-12 10:49:54', 8, '2026-02-12 10:47:17', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-12 10:49:54', '', '2026-02-12 10:49:54', '2026-02-12 01:52:47', '2026-02-12 02:49:54', 0, NULL, NULL, NULL),
(44, '007/TT/MSM/02/2026', 58, 3, '2026-02-12', '2026-02-12', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</p><p>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai <strong><u>Tenaga Teknis</u></strong> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;</li></ol>', 6, 7, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-12 10:48:25', 8, '2026-02-12 10:47:08', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-12 10:48:25', '', '2026-02-12 10:48:25', '2026-02-12 01:59:05', '2026-02-12 02:48:25', 0, NULL, NULL, NULL),
(46, '006/PO/MSM/02/2026', 61, 3, '2026-02-12', '2026-02-12', '2028-06-14', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p style=\"margin-left:0in;text-align:justify;\">Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.&nbsp;</p><p style=\"margin-left:0in;text-align:justify;\">Maka&nbsp;dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai <strong><u>Pengawas Operasional Pertambangan</u></strong> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahtaeraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangai laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindak lanjuti;</li></ol>', 3, 8, 4, '2026-02-19 09:17:10', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-02-12 13:08:01', 8, '2026-02-20 08:45:42', 'approved', 'approved', 0, 1, NULL, NULL, '2026-02-20 08:45:42', '', '2026-02-20 08:45:42', '2026-02-12 03:04:21', '2026-02-20 00:45:42', 0, 'tidak sesuai', 'Agung Praptono', '2026-02-12 14:08:58'),
(47, '007/PO/MSM/02/2026', 64, 3, '2026-02-12', '2026-02-12', '2028-06-14', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p style=\"margin-left:0in;text-align:justify;\">Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.&nbsp;</p><p style=\"margin-left:0in;text-align:justify;\">Maka&nbsp;dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai <strong><u>Pengawas Operasional Pertambangan</u></strong> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahtaeraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangai laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindak lanjuti;</li></ol>', 5, 7, 4, '2026-02-12 12:24:38', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-02-12 13:02:23', 8, '2026-02-12 11:39:24', 'approved', 'approved', 1, 0, NULL, NULL, '2026-02-12 13:02:23', '', '2026-02-12 13:02:23', '2026-02-12 03:34:24', '2026-02-12 05:02:23', 0, NULL, NULL, NULL),
(48, '008/PO/MSM/02/2026', 66, 3, '2026-02-12', '2026-02-12', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p style=\"margin-left:0in;text-align:justify;\">Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.&nbsp;</p><p style=\"margin-left:0in;text-align:justify;\">Maka&nbsp;dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai <strong><u>Pengawas Operasional Pertambangan</u></strong> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahtaeraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangai laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindak lanjuti;</li></ol>', 4, 7, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-12 13:37:29', 8, '2026-02-12 13:36:49', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-12 13:37:29', '', '2026-02-12 13:37:29', '2026-02-12 05:28:54', '2026-02-12 05:37:29', 0, NULL, NULL, NULL),
(50, '009/TT/MSM/02/2026', 67, 3, '2026-02-12', '2026-02-12', '2027-11-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, 3, '2026-02-18 15:17:47', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-02-12 14:08:23', 8, '2026-02-18 15:18:15', 'approved', 'approved', 0, 1, NULL, NULL, '2026-02-18 15:18:15', '', '2026-02-18 15:18:15', '2026-02-12 06:07:00', '2026-02-18 07:18:15', 0, 'test resubmit only', 'Agung Praptono', '2026-02-16 03:32:05'),
(51, '001/PT/TTN/02/2026', 62, 3, '2026-02-12', '2026-02-12', '2028-06-14', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-12 14:08:29', 8, '2026-02-12 15:24:11', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-12 15:24:11', '', '2026-02-12 15:24:11', '2026-02-12 06:07:40', '2026-02-12 07:24:11', 0, NULL, NULL, NULL),
(55, '004/PT/MSM/02/2026', 72, 3, '2026-02-18', '2026-02-18', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 7, 6, '2026-02-18 21:15:01', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-02-18 21:15:37', 8, '2026-02-18 19:34:03', 'approved', 'approved', 1, 0, NULL, NULL, '2026-02-18 21:15:37', '', '2026-02-18 21:15:37', '2026-02-18 11:33:14', '2026-02-18 13:15:37', 0, 'harus diubah', 'Tejo Prihantoro', '2026-02-18 19:33:48'),
(56, '005/PT/MSM/02/2026', 73, 3, '2026-02-18', '2026-02-18', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 6, 7, 6, '2026-02-19 09:12:51', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-02-21 15:32:00', 8, '2026-02-19 08:01:11', 'approved', 'approved', 1, 0, NULL, NULL, '2026-02-21 15:32:00', '', '2026-02-21 15:32:00', '2026-02-18 13:20:49', '2026-02-21 07:32:00', 0, 'ubah', 'Tejo Prihantoro', '2026-02-19 09:09:08'),
(57, '006/PT/MSM/02/2026', 76, 3, '2026-02-19', '2026-02-19', '2028-06-14', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 5, 8, 3, '2026-02-25 13:19:08', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-02-19 14:02:43', 8, '2026-02-26 08:28:01', 'approved', 'approved', 0, 1, NULL, NULL, '2026-02-26 08:28:01', '', '2026-02-26 08:28:01', '2026-02-19 01:32:26', '2026-02-26 00:28:01', 0, 'perbiaki', 'Agung Praptono', '2026-02-19 14:02:56'),
(58, '010/TT/MSM/02/2026', 79, 3, '2026-02-19', '2026-02-19', '2027-05-11', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</p><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Tenaga Teknis - Rigger dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;</li></ol>', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-24 08:02:22', 8, '2026-02-25 09:08:22', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-25 09:08:22', '', '2026-02-25 09:08:22', '2026-02-19 06:14:56', '2026-02-25 01:08:22', 0, NULL, NULL, NULL),
(59, '007/PT/MSM/02/2026', 82, 3, '2026-02-20', '2026-02-20', '2028-06-14', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 4, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-24 08:02:25', 8, '2026-02-27 09:33:22', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-27 09:33:22', '', '2026-02-27 09:33:22', '2026-02-20 00:43:32', '2026-02-27 01:33:22', 0, NULL, NULL, NULL),
(60, '009/PO/MSM/02/2026', 84, 3, '2026-02-21', '2026-02-21', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 5, 7, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-24 08:02:29', 8, '2026-02-21 15:34:50', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-24 08:02:29', '', '2026-02-24 08:02:29', '2026-02-21 03:51:07', '2026-02-24 00:02:29', 0, NULL, NULL, NULL),
(61, '011/TT/MSM/02/2026', 83, 3, '2026-02-21', '2026-02-21', '2028-05-22', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 5, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-24 08:03:30', 8, '2026-03-02 09:12:02', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-02 09:12:02', '', '2026-03-02 09:12:02', '2026-02-21 03:51:55', '2026-03-02 01:12:02', 0, NULL, NULL, NULL),
(62, '012/TT/MSM/02/2026', 86, 3, '2026-02-21', '2026-02-21', '2027-05-11', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 5, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-21 14:44:53', 8, '2026-02-21 14:45:05', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-21 14:45:05', '', '2026-02-21 14:45:05', '2026-02-21 06:44:03', '2026-02-21 06:45:05', 0, NULL, NULL, NULL),
(63, '013/TT/MSM/02/2026', 77, 3, '2026-02-24', '2026-02-24', '2026-12-10', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-27 08:24:31', 8, '2026-03-02 09:19:59', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-02 09:19:59', '', '2026-03-02 09:19:59', '2026-02-24 00:04:29', '2026-03-02 01:19:59', 0, NULL, NULL, NULL),
(64, '014/TT/MSM/02/2026', 85, 3, '2026-02-25', '2026-02-25', '2027-05-11', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-25 10:45:58', 8, '2026-02-25 10:46:10', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-25 10:46:10', '', '2026-02-25 10:46:10', '2026-02-25 02:43:33', '2026-02-25 02:46:10', 0, NULL, NULL, NULL),
(65, '008/PT/MSM/02/2026', 87, 3, '2026-02-25', '2026-02-25', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 4, 7, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-27 08:24:35', 8, '2026-02-25 12:01:18', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-27 08:24:35', '', '2026-02-27 08:24:35', '2026-02-25 04:00:18', '2026-02-27 00:24:35', 0, NULL, NULL, NULL),
(66, '015/TT/MSM/02/2026', 88, 3, '2026-02-25', '2026-02-25', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 5, 7, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-25 14:37:26', 8, '2026-02-25 14:37:17', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-25 14:37:26', '', '2026-02-25 14:37:26', '2026-02-25 06:32:35', '2026-02-25 06:37:26', 0, NULL, NULL, NULL),
(67, '002/TT/TTN/02/2026', 89, 3, '2026-02-25', '2026-02-25', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 6, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-02-25 14:45:12', 8, '2026-02-25 14:45:22', 'approved', 'approved', 1, 1, NULL, NULL, '2026-02-25 14:45:22', '', '2026-02-25 14:45:22', '2026-02-25 06:44:46', '2026-02-25 06:45:22', 0, NULL, NULL, NULL),
(68, '016/TT/MSM/02/2026', 91, 3, '2026-02-27', '2026-02-27', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-03-02 09:11:37', 8, '2026-03-02 10:27:36', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-02 10:27:36', '', '2026-03-02 10:27:36', '2026-02-27 00:18:50', '2026-03-02 02:27:36', 0, NULL, NULL, NULL),
(69, '017/TT/MSM/02/2026', 90, 3, '2026-02-27', '2026-02-27', '2028-06-15', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 6, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-03-02 09:11:40', 8, '2026-03-02 10:28:06', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-02 10:28:06', '', '2026-03-02 10:28:06', '2026-02-27 00:19:57', '2026-03-02 02:28:06', 0, NULL, NULL, NULL),
(70, '018/TT/MSM/02/2026', 78, 3, '2026-02-27', '2026-02-27', '2026-12-10', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 6, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-03-02 09:11:43', 8, '2026-03-02 10:28:30', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-02 10:28:30', '', '2026-03-02 10:28:30', '2026-02-27 00:20:13', '2026-03-02 02:28:30', 0, NULL, NULL, NULL),
(71, '003/TT/TTN/02/2026', 75, 3, '2026-02-27', '2026-02-27', '2026-12-10', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 5, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-03-02 09:11:46', 8, '2026-03-02 10:29:54', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-02 10:29:54', '', '2026-03-02 10:29:54', '2026-02-27 00:20:50', '2026-03-02 02:29:54', 0, NULL, NULL, NULL);
INSERT INTO `appointments` (`id`, `appointment_number`, `employee_id`, `position_id`, `appointment_date`, `effective_date`, `expiry_date`, `status`, `auto_generated`, `notes`, `letter_content`, `created_by`, `approved_by`, `admin_approved_by`, `admin_approved_date`, `admin_approval_action`, `admin_approval_notes`, `rejected_by_ktt_user_id`, `ktt1_approved_by`, `ktt1_approved_date`, `ktt2_approved_by`, `ktt2_approved_date`, `ktt_msm_status`, `ktt_ttn_status`, `requires_ktt_msm_review`, `requires_ktt_ttn_review`, `resubmit_reason`, `last_rejected_by_ktt`, `approved_date`, `approval_notes`, `final_approval_date`, `created_at`, `updated_at`, `resubmit_count`, `last_rejection_notes`, `last_rejection_by_name`, `last_rejection_date`) VALUES
(72, '010/PO/MSM/02/2026', 74, 3, '2026-02-27', '2026-02-27', '2028-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p style=\"margin-left:0in;text-align:justify;\">Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan.&nbsp;</p><p style=\"margin-left:0in;text-align:justify;\">Maka&nbsp;dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai <strong><u>Pengawas Operasional Pertambangan</u></strong> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian;</li><li>Bertanggung jawab atas keselamatan, kesehatan, dan kesejahtaeraan dari semua orang yang ditugaskan kepadanya;</li><li>Membuat dan menandatangai laporan pemeriksaan, inspeksi, dan pengujian;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindak lanjuti;</li></ol>', 4, 8, 5, '2026-03-02 09:23:35', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-02-27 08:40:29', 8, '2026-03-02 10:26:59', 'approved', 'approved', 0, 1, NULL, NULL, '2026-03-02 10:26:59', '', '2026-03-02 10:26:59', '2026-02-27 00:21:29', '2026-03-02 02:26:59', 0, 'ubah no sertifikat', 'Agung Praptono', '2026-03-02 09:22:45'),
(73, '001/TT/MSM/03/2026', 93, 3, '2026-03-04', '2026-03-04', '2028-06-15', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 5, 7, NULL, NULL, NULL, NULL, NULL, 7, '2026-03-04 14:21:46', 8, '2026-03-04 14:21:37', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-04 14:21:46', '', '2026-03-04 14:21:46', '2026-03-04 05:45:28', '2026-03-04 06:21:46', 0, NULL, NULL, NULL),
(74, '002/TT/MSM/03/2026', 96, 3, '2026-03-04', '2026-03-04', '2028-06-15', 'rejected', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, NULL, 4, '2026-03-05 09:39:26', 'send_to_user', 'Reviewed by admin', 7, 7, '2026-03-04 14:23:15', 8, '2026-03-04 14:23:03', 'rejected', 'approved', 1, 1, NULL, 'msm', NULL, 'ubahkan no serti', NULL, '2026-03-04 06:22:45', '2026-03-05 01:39:26', 0, 'ubahkan no serti', 'Tejo Prihantoro', '2026-03-04 14:23:15'),
(75, '003/TT/MSM/03/2026', 101, 3, '2026-03-05', '2026-03-05', '2026-05-22', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 7, 3, '2026-03-05 12:52:25', NULL, NULL, 8, 7, '2026-03-05 13:35:18', 8, '2026-03-05 13:27:38', 'approved', 'approved', 1, 1, NULL, 'ttn', '2026-03-05 13:35:18', '', '2026-03-05 13:35:18', '2026-03-05 04:50:35', '2026-03-05 05:35:18', 0, 'ubah no serti', 'Agung Praptono', '2026-03-05 12:51:11'),
(76, '001/PO/MSM/03/2026', 71, 3, '2026-03-05', '2026-03-05', '2027-05-20', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 4, 8, 6, '2026-03-05 13:50:42', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-03-05 13:49:33', 8, '2026-03-05 13:51:21', 'approved', 'approved', 0, 1, NULL, NULL, '2026-03-05 13:51:21', '', '2026-03-05 13:51:21', '2026-03-05 05:38:48', '2026-03-05 05:51:21', 0, 'ubahkan no sertifikat', 'Agung Praptono', '2026-03-05 13:49:48'),
(77, '001/PT/MSM/03/2026', 102, 3, '2026-03-12', '2026-03-12', '2026-06-22', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 7, NULL, NULL, NULL, NULL, NULL, 7, '2026-03-16 10:19:20', 8, '2026-03-12 15:17:06', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-16 10:19:20', '', '2026-03-16 10:19:20', '2026-03-12 06:45:34', '2026-03-16 02:19:20', 0, NULL, NULL, NULL),
(78, '002/PT/MSM/03/2026', 100, 3, '2026-03-12', '2026-03-12', '2027-05-22', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 6, 7, NULL, NULL, NULL, NULL, NULL, 7, '2026-03-16 10:21:17', 8, '2026-03-12 15:17:10', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-16 10:21:17', '', '2026-03-16 10:21:17', '2026-03-12 06:47:54', '2026-03-16 02:21:17', 0, NULL, NULL, NULL),
(79, '001/TT/TTN/03/2026', 103, 3, '2026-03-16', '2026-03-16', '2026-05-22', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-03-16 10:22:05', 8, '2026-03-16 10:22:15', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-16 10:22:15', '', '2026-03-16 10:22:15', '2026-03-16 02:18:19', '2026-03-16 02:22:15', 0, NULL, NULL, NULL),
(80, '002/PO/MSM/03/2026', 105, 3, '2026-03-17', '2026-03-17', '2027-06-23', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan KEPMEN ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan kaidah Teknik Pertambangan yang Baik dan KEPDIRJEN ESDM 185.K/37.04/DJB/2019 Lampiran IV, Elemen III, terkait penunjukan Pengawas Operasional Pertambangan. Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; <strong>Pengawas Operasional Pertambangan</strong> dengan tugas, tanggung jawab dan wewenang sebagai berikut:</p><ol><li>Bertanggung jawab kepada KTT untuk keselamatan dan kesehatan semua pekerja tambang yang beraktifitas di lingkup kerjanya;&nbsp;</li><li>Melaksanakan inspeksi, pemeriksaan, dan pengujian; Bertanggung jawab atas keselamatan, kesehatan, dan kesejahtaeraan dari semua orang yang ditugaskan kepadanya;&nbsp;</li><li>Membuat dan menandatangai laporan pemeriksaan, inspeksi, dan pengujian;&nbsp;</li><li>Menerapkan Sistem Manajemen Keselamatan Pertambangan;&nbsp;</li><li>Mengidentifikasi semua bahaya, menilai dan mengendalikan risikonya secara tepat dan konsisten di lingkup kerjanya;&nbsp;</li><li>Memastikan semua aktifitas berisiko telah terdapat prosedur kerja yang memadai, tersosialisasi dan diterapkan dengan baik oleh pekerja;&nbsp;</li><li>Memastikan semua pekerja telah memiliki kompetensi yang memadai dan diberikan akses untuk bertanya jika kurang paham akan suatu tugas;&nbsp;</li><li>Dapat menghentikan suatu pekerjaan jika dianggap berpotensi insiden dan harus segera ditindak lanjuti;</li></ol>', 6, 7, 3, '2026-03-17 08:50:59', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-03-17 09:58:00', 8, '2026-03-17 08:49:52', 'approved', 'approved', 1, 0, NULL, NULL, '2026-03-17 09:58:00', '', '2026-03-17 09:58:00', '2026-03-17 00:46:20', '2026-03-17 01:58:00', 0, 'ubah tgl sertifikat', 'Tejo Prihantoro', '2026-03-17 08:50:10'),
(81, '003/PT/MSM/03/2026', 99, 3, '2026-03-17', '2026-03-17', '2026-05-22', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 3, 7, 4, '2026-03-17 09:09:56', NULL, NULL, NULL, 7, '2026-03-17 09:57:22', 8, '2026-03-17 09:57:06', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-17 09:57:22', '', '2026-03-17 09:57:22', '2026-03-17 01:08:25', '2026-03-17 01:57:22', 0, 'ubah tgl sertifikat', 'Agung Praptono', '2026-03-17 09:09:04'),
(82, '004/PT/MSM/03/2026', 107, 3, '2026-03-17', '2026-03-17', '2026-05-22', 'pending', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan peraturan perundang-undangan yang berlaku tentang Keselamatan dan Kesehatan Kerja Pertambangan, terkait penunjukan Pengawas Teknis.</p><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Pengawas Teknis - Juru Las dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melakukan pengawasan terhadap pelaksanaan kegiatan teknis di bidang teknis;</li><li>Memberikan arahan teknis kepada tenaga pelaksana dalam melaksanakan pekerjaan sesuai standar operasional;</li><li>Melakukan pemeriksaan dan evaluasi kondisi peralatan secara berkala;</li><li>Memastikan penerapan standar keselamatan dan kesehatan kerja dalam setiap kegiatan teknis;</li><li>Membuat laporan pelaksanaan kegiatan teknis dan rekomendasi perbaikan;</li></ol>', 4, NULL, 3, '2026-03-17 15:54:00', NULL, NULL, NULL, 7, '2026-06-08 15:10:43', NULL, NULL, 'approved', 'pending', 1, 1, NULL, NULL, NULL, NULL, NULL, '2026-03-17 07:41:09', '2026-06-08 07:10:43', 0, 'cek sertifikat kompetensi', 'Agung Praptono', '2026-03-17 15:49:15'),
(83, '004/TT/MSM/03/2026', 108, 3, '2026-03-27', '2026-03-27', '2027-12-23', 'approved', 1, 'Auto-generated setelah verifikasi data tenaga kerja', '<p>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</p><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Tenaga Teknis - Petugas Industrial Hygiene dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;<br>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</li></ol><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Tenaga Teknis - Petugas Industrial Hygiene dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;<br>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</li></ol><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Tenaga Teknis - Petugas Industrial Hygiene dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;<br>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</li></ol><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Tenaga Teknis - Petugas Industrial Hygiene dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;<br>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</li></ol><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Tenaga Teknis - Petugas Industrial Hygiene dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;<br>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</li></ol><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Tenaga Teknis - Petugas Industrial Hygiene dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;<br>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</li></ol><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Tenaga Teknis - Petugas Industrial Hygiene dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;<br>Berdasarkan Peraturan Menteri ESDM dan standar kompetensi nasional, terkait penunjukan Tenaga Teknis Berkompeten.</li></ol><p><strong>Maka dengan mempertimbangkan kompetensi yang dimiliki maka bersama ini anda ditunjuk sebagai; Tenaga Teknis - Petugas Industrial Hygiene dengan tugas, tanggung jawab dan wewenang sebagai berikut:</strong></p><ol><li>Melaksanakan tugas dan tanggung jawab sesuai dengan kompetensi yang dimiliki;</li><li>Menerapkan prosedur keselamatan dan kesehatan kerja dalam pelaksanaan tugas;</li><li>Berkoordinasi dengan atasan dan rekan kerja terkait pelaksanaan tugas;</li><li>Membuat laporan pelaksanaan tugas secara berkala;</li><li>Mematuhi seluruh peraturan dan tata tertib perusahaan;</li></ol>', 3, 8, NULL, NULL, NULL, NULL, NULL, 7, '2026-03-27 10:00:11', 8, '2026-03-27 10:00:19', 'approved', 'approved', 1, 1, NULL, NULL, '2026-03-27 10:00:19', '', '2026-03-27 10:00:19', '2026-03-27 01:58:57', '2026-03-27 02:00:19', 0, NULL, NULL, NULL),
(84, '001/PT/MSM/06/2026', 106, 3, '2026-06-08', '2026-06-08', '2027-06-23', 'pending', 1, 'Auto-generated setelah verifikasi data tenaga kerja', NULL, 4, NULL, 4, '2026-06-08 15:14:32', 'send_to_ktt', 'Reviewed by admin', NULL, 7, '2026-06-09 08:08:01', NULL, NULL, 'approved', 'pending', 1, 1, NULL, NULL, NULL, 'tidak sesuai', NULL, '2026-06-08 07:11:36', '2026-06-09 00:08:01', 0, 'tidak sesuai', 'Agung Praptono', '2026-06-08 15:12:48');

-- --------------------------------------------------------

--
-- Table structure for table `certifications`
--

CREATE TABLE `certifications` (
  `id` int NOT NULL,
  `cert_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cert_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issuing_authority` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `validity_period` int DEFAULT NULL COMMENT 'Masa berlaku dalam bulan',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certifications`
--

INSERT INTO `certifications` (`id`, `cert_name`, `cert_type`, `issuing_authority`, `validity_period`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Pengawas Operasional Pertama (POP)', 'Kompeten', 'ESDM', 36, '', 1, '2026-01-07 00:05:37', '2026-02-02 06:24:59'),
(2, 'Pengawas Operasional Madya (POM)', 'Kompeten', 'ESDM', 36, '', 1, '2026-01-07 00:05:37', '2026-02-02 06:24:20'),
(3, 'Pengawas Teknis Pertama', 'Kompeten', 'ESDM', 36, '', 0, '2026-01-07 00:05:37', '2026-01-28 10:16:28'),
(4, 'Pengawas Teknis Madya', 'Kompeten', 'ESDM', 36, '', 0, '2026-01-07 00:05:37', '2026-01-28 10:16:09'),
(5, 'Juru Las Bersertifikat', 'Attendance/Peserta', 'BNSP', 24, '', 0, '2026-01-07 00:05:37', '2026-01-28 10:16:16'),
(6, 'Rigger Bersertifikat', 'Kompeten', 'BNSP', 24, '', 0, '2026-01-07 00:05:37', '2026-01-28 10:16:13'),
(7, 'Operator Alat Berat', 'Attendance/Peserta', 'Disnaker', 36, '', 0, '2026-01-07 00:05:37', '2026-01-28 10:16:23'),
(8, 'Juru Ukur Tambang', 'Attendance/Peserta', 'SDM', 0, '', 0, '2026-01-08 23:51:58', '2026-01-28 10:16:19'),
(10, 'Pengawas Operasional Utama (POU)', 'Kompeten', 'BNSP', 60, '', 1, '2026-01-13 01:38:11', '2026-02-02 06:25:09'),
(12, 'Avignam Samagram Rescue Training', 'Training', 'Basic Rescue Training', NULL, '', 0, '2026-01-13 03:51:37', '2026-01-28 10:16:31'),
(13, '', '', '', NULL, NULL, 0, '2026-01-28 10:12:27', '2026-01-28 10:12:38'),
(14, 'Perencana Reklamasi', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:17:23', '2026-01-28 10:17:23'),
(15, 'Pelaksana Reklamasi', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:18:19', '2026-01-28 10:18:19'),
(16, 'Perencana Pascatambang', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:18:36', '2026-01-28 10:18:36'),
(17, 'Pelaksana Pascatambang', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:18:51', '2026-01-28 10:18:51'),
(18, 'Pelaksana Pemantauan Pascatambang', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:19:03', '2026-01-28 10:19:03'),
(19, 'Pemroduksi Pembibitan (Production Nursery)', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:19:13', '2026-01-28 10:19:13'),
(20, 'Pelaksana Pembibitan (Nursery)', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:19:27', '2026-01-28 10:19:27'),
(21, 'Pengawas Lingkungan Pertambangan/ Supervisor Environment', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:19:41', '2026-01-28 10:19:41'),
(22, 'Perancanaan Operasional Tambang Jangka Pendek', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:19:57', '2026-01-28 10:19:57'),
(23, 'Perancanaan Operasional Tambang Jangka Panjang', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:20:11', '2026-01-28 10:20:11'),
(24, 'Remote Pilot License (Drone)', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:20:20', '2026-01-28 10:20:20'),
(25, 'Juru Ukur Tambang', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:20:34', '2026-01-28 10:20:34'),
(26, 'Identifikasi Bahaya dan Pengendalian Risiko (IBPR) Pertambangan', 'Kompeten', '', NULL, NULL, 0, '2026-01-28 10:20:43', '2026-01-28 10:21:22'),
(27, 'Identifikasi Bahaya dan Pengendalian Risiko (IBPR) Pertambangan', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:21:05', '2026-01-28 10:21:05'),
(28, 'Penyusunan Job Safety Analysis (JSA) Pertambangan', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:33:13', '2026-01-28 10:33:13'),
(29, 'Pelaksanaan Inspeksi K3 Pertambangan', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:33:35', '2026-01-28 10:33:35'),
(30, 'Pelaksanaan Investigasi pada Kecelakaan Tambang', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:33:54', '2026-01-28 10:33:54'),
(31, 'Ahli K3 Umum', 'Kompeten', 'BNSP', NULL, NULL, 1, '2026-01-28 10:34:07', '2026-01-28 10:34:07'),
(32, 'Auditor Sistem Manajemen K3', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:34:21', '2026-01-28 10:34:21'),
(33, 'Ahli Higiene Industri Muda', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:34:34', '2026-01-28 10:34:34'),
(34, 'Ahli Higiene Industri Madya', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:35:17', '2026-01-28 10:35:17'),
(35, 'Ahli Higiene Industri Utama', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:35:31', '2026-01-28 10:35:31'),
(36, 'Pengawas K3 Migas', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:35:46', '2026-01-28 10:35:46'),
(37, 'Operator Keselamatan & Kesehatan Kerja', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:36:01', '2026-01-28 10:36:01'),
(38, 'Petugas Keselamatan & Kesehatan Kerja', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:36:28', '2026-01-28 10:36:28'),
(39, 'Teknisi K3 Listrik', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:36:54', '2026-01-28 10:36:54'),
(40, 'Ahli K3 Listrik ', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:37:09', '2026-01-28 10:37:09'),
(41, 'Training Of Trainer (TOT) Level 3', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:37:18', '2026-01-28 10:37:18'),
(42, 'Training Of Trainer (TOT) Level 4', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:37:26', '2026-01-28 10:37:26'),
(43, 'Training Of Trainer (TOT) Level 5', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:37:34', '2026-01-28 10:37:34'),
(44, 'Penanggungjawab Pengendalian Pencemaran Udara (PPPU)', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:37:50', '2026-01-28 10:37:50'),
(45, 'Penanggungjawab Operasional Pengendalian Pencemaran Udara (POPU)', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:38:17', '2026-01-28 10:38:17'),
(46, 'Penanggungjawab Ops Instalasi Pengendalian Pencemaran Udara (POIPU)', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:38:35', '2026-01-28 10:38:35'),
(47, 'Penanggung Jawab Operasional Pengolahan Air Limbah (POPAL) ', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:39:03', '2026-01-28 10:39:03'),
(48, 'Penanggungjawab Pengendalian Pencemaran Air (PPPA) ', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:39:21', '2026-01-28 10:39:21'),
(49, 'Pengembalian Contoh Uji Air (PCUA)', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:39:45', '2026-01-28 10:39:45'),
(50, 'Petugas Pengambilan Contoh Uji Udara Ambien dan Kebauan (PCUU) ', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:40:15', '2026-01-28 10:40:15'),
(51, 'Petugas Pengukuran Emisi Sumber Bergerak (PESB) ', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:40:28', '2026-01-28 10:40:28'),
(52, 'Operator Penyimpanan Limbah B3 (OPPLB3)', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:40:48', '2026-01-28 10:40:48'),
(53, 'Manajer Pengumpulan Limbah B3 (MPLB3)', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:41:12', '2026-01-28 10:41:12'),
(54, 'Operator Pengumpulan Limbah B3 (OPLB3)', 'Kompeten', '', NULL, NULL, 1, '2026-01-28 10:41:27', '2026-01-28 10:41:27'),
(55, 'Rigger', NULL, NULL, NULL, NULL, 1, '2026-02-12 00:56:10', '2026-02-12 00:56:10');

-- --------------------------------------------------------

--
-- Table structure for table `competencies`
--

CREATE TABLE `competencies` (
  `id` int NOT NULL,
  `competency_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `position_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `competencies`
--

INSERT INTO `competencies` (`id`, `competency_name`, `position_type`, `created_at`) VALUES
(1, 'Pengawas Teknis Elektrik', 'pengawas_teknis', '2026-01-21 01:35:31'),
(2, 'Juru Las', 'tenaga_teknis', '2026-01-21 01:37:38'),
(3, 'Operator Alat Angkat dan Angkut', 'tenaga_teknis', '2026-01-21 01:38:14'),
(4, 'Rigger', 'tenaga_teknis', '2026-01-21 01:38:52'),
(5, 'Pengawas Teknis Mekanik', 'pengawas_teknis', '2026-01-21 01:39:05'),
(6, 'Pengawas Operasional Pertama', 'pengawas_operasional', '2026-01-28 09:59:17'),
(7, 'Pengawas Operasional Madya', 'pengawas_operasional', '2026-01-28 09:59:26'),
(8, 'Pengawas Operasional Utama', 'pengawas_operasional', '2026-01-28 09:59:33'),
(45, 'Juru Derek', 'tenaga_teknis', '2026-03-26 00:27:10'),
(46, 'Juru Ledak', 'tenaga_teknis', '2026-03-26 00:27:20'),
(47, 'Petugas Bahan Peledak', 'tenaga_teknis', '2026-03-26 00:27:32'),
(48, 'Juru Ukur', 'tenaga_teknis', '2026-03-26 00:27:45'),
(49, 'Juru Bor', 'tenaga_teknis', '2026-03-26 00:27:54'),
(50, 'Ahli Listrik', 'pengawas_teknis', '2026-03-26 00:28:06'),
(51, 'Juru Langsir', 'tenaga_teknis', '2026-03-26 00:28:15'),
(52, 'Penambangan', 'tenaga_teknis', '2026-03-26 00:28:43'),
(53, 'Pengolahan', 'tenaga_teknis', '2026-03-26 00:28:50'),
(54, 'Maintenance', 'tenaga_teknis', '2026-03-26 00:28:57'),
(55, 'Petugas P3K', 'tenaga_teknis', '2026-03-26 00:29:06'),
(56, 'Tim Tanggap Darurat', 'tenaga_teknis', '2026-03-26 00:29:16'),
(57, 'Pemadam Kebakaran', 'tenaga_teknis', '2026-03-26 00:29:26'),
(58, 'Petugas Ventilasi', 'tenaga_teknis', '2026-03-26 00:29:35'),
(59, 'Petugas Industrial Hygiene', 'tenaga_teknis', '2026-03-26 00:29:49'),
(60, 'Dokter', 'tenaga_teknis', '2026-03-26 00:29:55'),
(61, 'Juru Rawat', 'tenaga_teknis', '2026-03-26 00:30:06'),
(62, 'Paramedis', 'tenaga_teknis', '2026-03-26 00:30:15'),
(63, 'Petugas Proteksi Radiasi', 'tenaga_teknis', '2026-03-26 00:30:33'),
(64, 'Perencanaan Tambang', 'tenaga_teknis', '2026-03-26 00:30:51'),
(65, 'Loading Master / Berthing Master', 'tenaga_teknis', '2026-03-26 00:31:01'),
(66, 'Petugas Bahan Kimia', 'tenaga_teknis', '2026-03-26 00:31:09'),
(67, 'Geologi', 'tenaga_teknis', '2026-03-26 00:31:16'),
(68, 'Eksplorasi', 'tenaga_teknis', '2026-03-26 00:31:23');

-- --------------------------------------------------------

--
-- Table structure for table `competency_sub_competencies`
--

CREATE TABLE `competency_sub_competencies` (
  `id` int NOT NULL,
  `competency_id` int NOT NULL,
  `sub_competency_name` varchar(255) NOT NULL,
  `sub_competency_level` varchar(100) DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `competency_sub_competencies`
--

INSERT INTO `competency_sub_competencies` (`id`, `competency_id`, `sub_competency_name`, `sub_competency_level`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(8, 59, 'Ahli Hygiene Industri Muda', NULL, NULL, 1, '2026-03-26 02:57:12', '2026-03-26 02:57:12'),
(9, 59, 'Ahli Hygiene Industri Madya', NULL, NULL, 1, '2026-03-26 02:57:12', '2026-03-26 02:57:12'),
(10, 59, 'Ahli Hygiene Industri Utama', NULL, NULL, 1, '2026-03-26 02:57:12', '2026-03-26 02:57:12'),
(15, 2, 'Kelas 1', NULL, NULL, 1, '2026-03-26 06:43:06', '2026-03-26 06:43:06'),
(16, 2, 'Kelas 2', NULL, NULL, 1, '2026-03-26 06:43:06', '2026-03-26 06:43:06'),
(17, 2, 'Kelas 3', NULL, NULL, 1, '2026-03-26 06:43:06', '2026-03-26 06:43:06');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int NOT NULL,
  `employee_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contractor_company` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `competency_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `competency_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sub_competency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supervision_area` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ruang_lingkup` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cv_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `statement_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Path to employee statement letter (PDF with wet signature)',
  `created_by` int DEFAULT NULL,
  `signature_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `verified_by` int DEFAULT NULL,
  `verified_date` datetime DEFAULT NULL,
  `verification_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resubmit_count` int DEFAULT '0' COMMENT 'Jumlah re-submit yang dilakukan',
  `resubmit_date` datetime DEFAULT NULL COMMENT 'Tanggal terakhir re-submit',
  `appointment_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Nomor surat penunjukan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_code`, `full_name`, `id_number`, `department`, `position`, `contractor_company`, `competency_type`, `competency_name`, `sub_competency`, `supervision_area`, `ruang_lingkup`, `cv_file`, `statement_file`, `created_by`, `signature_file`, `verification_status`, `verified_by`, `verified_date`, `verification_notes`, `is_active`, `created_at`, `updated_at`, `resubmit_count`, `resubmit_date`, `appointment_number`) VALUES
(1, '0910', 'windy', '28', NULL, NULL, 'PT DNX Indonesia', NULL, NULL, NULL, NULL, NULL, 'uploads/cv/0910_cv_1767744929.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-07 08:15:52', 'berhasil diverifikasi', 1, '2026-01-07 00:15:29', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/0001'),
(2, 'PTSMA-21937', 'windy', NULL, NULL, 'Maintenance', 'PT Samudera Mulai Abadi', NULL, NULL, NULL, NULL, NULL, 'uploads/cv/cv_PTSMA-21937_1767746346.docx', NULL, NULL, NULL, 'verified', 3, '2026-01-07 09:46:42', 'berhasil', 1, '2026-01-07 00:39:06', '2026-02-01 07:35:09', 0, NULL, NULL),
(3, 'PTDNX-1360', 'patricia', NULL, NULL, 'Maintenance', 'PT DNX Indonesia', NULL, NULL, NULL, NULL, NULL, 'uploads/cv/cv_PTDNX-1360_1767749042.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-07 09:46:02', 'berhasil', 1, '2026-01-07 01:24:02', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/3320'),
(4, 'PTDNX-819', 'Vino', NULL, NULL, 'Produksi', 'PT DNX Indonesia', NULL, NULL, NULL, NULL, NULL, 'uploads/cv/cv_PTDNX-819_1767755487.pdf', NULL, NULL, NULL, 'verified', 4, '2026-01-07 11:12:35', 'berhasil\r\n', 1, '2026-01-07 03:11:27', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5603'),
(5, 'PTDNX-2812', 'Valen', NULL, NULL, 'Produksi', 'PT DNX Indonesia', NULL, NULL, NULL, NULL, NULL, 'uploads/cv/cv_PTDNX-2812_1767766143.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-07 14:10:15', 'berhasil diverifikasi', 1, '2026-01-07 06:09:03', '2026-02-01 07:35:09', 0, NULL, NULL),
(6, 'PTDNX-183901', 'Pat', NULL, NULL, 'Produksi', 'PT DNX Indonesia', 'pengawas_operasional', '', NULL, 'PT Tambang Tondano Nusajaya (TTN)', 'PT Tambang Tondano Nusajaya (TTN)', 'uploads/cv/cv_PTDNX-183901_1767767079.pdf', NULL, NULL, '', 'verified', 3, '2026-01-22 06:39:32', '', 1, '2026-01-07 06:24:39', '2026-02-01 07:35:09', 1, '2026-01-22 06:38:30', '001/PO/TTN/01/2026'),
(7, 'PTDNX-2893H', 'Vito', NULL, NULL, 'Produksi', 'PT DNX Indonesia', NULL, NULL, NULL, NULL, NULL, 'uploads/cv/cv_PTDNX-2893H_1767768294.pdf', NULL, NULL, NULL, 'verified', 4, '2026-01-07 14:45:22', 'berhasil', 1, '2026-01-07 06:44:54', '2026-02-01 07:35:09', 0, NULL, NULL),
(8, 'PTGMA-975490', 'Vidya', NULL, NULL, 'Produksi', 'PT Geopersada Mulai Abadi', NULL, NULL, NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA-975490_1767856106.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-08 15:09:01', 'Verifikasi berhasil', 1, '2026-01-08 07:08:26', '2026-02-01 07:35:09', 0, NULL, NULL),
(9, 'DNX-18228', 'xiaojun', NULL, NULL, 'Produksi', 'PT DNX Indonesia', NULL, NULL, NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_DNX-18228_1767858906.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-08 15:57:56', 'berhasil', 1, '2026-01-08 07:55:06', '2026-02-01 07:35:09', 0, NULL, NULL),
(10, 'DNX-182281', 'lucas', NULL, NULL, 'Maintenance', 'PT DNX Indonesia', NULL, NULL, NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_DNX-182281_1767859013.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-08 15:57:32', 'satu sertifikat di tolak krna tidak relevan', 1, '2026-01-08 07:56:53', '2026-02-01 07:35:09', 0, NULL, NULL),
(11, 'PTGMA-1090300', 'Siska', NULL, NULL, 'IT', 'PT Geopersada Mulai Abadi', NULL, NULL, NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA-1090300_1767925542.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-09 12:41:16', 'tidak ada sertifikat', 1, '2026-01-09 02:25:42', '2026-02-01 07:35:09', 0, NULL, NULL),
(12, 'PTGMA-10903', 'A', NULL, NULL, 'IT', 'PT Geopersada Mulai Abadi', NULL, NULL, NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA-10903_1767926938.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-09 12:41:31', 'tidak ada sertifikat ', 1, '2026-01-09 02:48:58', '2026-02-01 07:35:09', 0, NULL, NULL),
(13, 'PTGMA-2101', 'B', NULL, NULL, 'IT', 'PT Geopersada Mulai Abadi', NULL, NULL, NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA-2101_1767927092.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-09 12:41:00', 'tidak ada sertifikat', 1, '2026-01-09 02:51:32', '2026-02-01 07:35:09', 0, NULL, NULL),
(14, 'PTGMA-210', 'B', NULL, NULL, 'IT', 'PT Geopersada Mulai Abadi', NULL, NULL, NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA-210_1767927352.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-09 11:47:05', '', 1, '2026-01-09 02:55:52', '2026-02-01 07:35:09', 0, NULL, NULL),
(15, 'PTG4S-1029', 'B', NULL, NULL, 'Produksi', 'G4S Security Services', NULL, NULL, NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTG4S-1029_1767935612.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-09 13:13:55', '', 1, '2026-01-09 05:13:32', '2026-02-01 07:35:09', 0, NULL, NULL),
(16, 'PTG4S-10291', 'B', NULL, NULL, 'Produksi', 'G4S Security Services', NULL, NULL, NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTG4S-10291_1767936233.pdf', NULL, NULL, NULL, 'verified', 4, '2026-01-09 13:24:19', '', 1, '2026-01-09 05:23:53', '2026-02-01 07:35:09', 0, NULL, NULL),
(17, '09101A', 'C', NULL, NULL, 'Produksi', 'PT Arlie Labora Utama', NULL, NULL, NULL, NULL, NULL, 'uploads/cv/09101A_cv_1767936413.pdf', NULL, NULL, NULL, 'verified', 5, '2026-01-09 13:27:39', '', 1, '2026-01-09 05:26:53', '2026-02-01 07:35:09', 0, NULL, NULL),
(18, '2203', 'N', NULL, NULL, 'Produksi', 'PT Tata Wisata', NULL, NULL, NULL, NULL, NULL, 'uploads/cv/2203_cv_1767936695.pdf', NULL, NULL, NULL, 'verified', 5, '2026-01-09 13:31:47', 'BERHASIL', 1, '2026-01-09 05:31:35', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5604'),
(19, 'DNX-1822', 'S', NULL, NULL, 'Produksi', 'PT Samudera Mulai Abadi', NULL, NULL, NULL, NULL, NULL, 'uploads/cv/DNX-1822_cv_1767937651.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-09 13:47:41', '', 1, '2026-01-09 05:47:31', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5605'),
(20, 'PTM-1726', 'Syarul', NULL, NULL, 'Produksi', 'PT Maxidrill Indonesia', NULL, NULL, NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTM-1726_1768175783.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-12 07:57:14', '', 1, '2026-01-11 23:56:23', '2026-02-01 07:35:09', 0, NULL, NULL),
(21, 'PTDNX-19200', 'C', NULL, NULL, 'Produksi', 'PT DNX Indonesia', NULL, NULL, NULL, NULL, 'PT Tambang Tondano Nusajaya (TTN)', 'uploads/cv/cv_PTDNX-19200_1768176434.pdf', NULL, NULL, NULL, 'verified', 3, '2026-01-12 08:07:53', '', 1, '2026-01-12 00:07:14', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5606'),
(22, 'PTS-120', 'B', NULL, NULL, 'Supervisior operation', 'PT Saribuana Manado', 'pengawas_operasional', '', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTS-120_1768264653.pdf', NULL, NULL, NULL, 'verified', 5, '2026-01-13 08:38:21', '', 1, '2026-01-13 00:37:33', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5607'),
(23, 'PTSMA23-1346', 'Isaac Kaunang', NULL, NULL, 'Mining Engineer', 'PT Samudera Mulai Abadi', 'pengawas_teknis', 'Pengawas Teknis', NULL, NULL, 'PT Tambang Tondano Nusajaya (TTN)', 'uploads/cv/cv_PTSMA23-1346_1768267900.pdf', NULL, NULL, NULL, 'verified', 6, '2026-01-13 10:08:12', '', 1, '2026-01-13 01:31:40', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5609'),
(24, 'PTSMA16-300', 'Bima Supriyanto', NULL, NULL, 'Mining Engineer Superintendent', 'PT Samudera Mulai Abadi', 'pengawas_teknis', 'Pengawas Operasional Pertama', NULL, NULL, 'PT Tambang Tondano Nusajaya (TTN)', 'uploads/cv/cv_PTSMA16-300_1768268405.pdf', NULL, NULL, NULL, 'verified', 6, '2026-01-13 10:07:40', '', 1, '2026-01-13 01:40:05', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5608'),
(25, 'PTSMA16-389', 'Winarno', NULL, NULL, 'Mine Survey Superintendent', 'PT Samudera Mulai Abadi', 'pengawas_teknis', 'Pengawas Teknis', NULL, NULL, 'PT TTN', 'uploads/cv/cv_PTSMA16-389_1768275428.pdf', NULL, NULL, NULL, 'verified', 4, '2026-01-13 14:15:44', 'expired', 1, '2026-01-13 03:37:08', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5610'),
(26, 'PTSMA17-616', 'Leo Nelwan', NULL, NULL, 'Maintenance Foreman', 'PT Samudera Mulai Abadi', 'pengawas_teknis', 'Pengawas Teknis', NULL, NULL, 'PT Tambang Tondano Nusajaya (TTN)', 'uploads/cv/cv_PTSMA17-616_1768275968.pdf', NULL, NULL, NULL, 'verified', 4, '2026-01-13 14:16:06', '', 1, '2026-01-13 03:46:08', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5611'),
(27, 'adwa', 'adw', NULL, NULL, 'Juru Las', 'PT DNX Indonesia', 'tenaga_teknis', 'Juru Las', NULL, '', 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_adwa_1768831988.pdf', NULL, NULL, 'uploads/signatures/signature.png', 'verified', 3, '2026-01-19 22:14:10', '', 1, '2026-01-19 14:13:08', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5612'),
(28, 'PTDNX-121212', 'Agriawan Iswahyudi', NULL, NULL, 'Rigger', 'PT DNX Indonesia', 'pengawas_operasional', NULL, NULL, 'PT MSM', 'PT MSM', 'uploads/cv/cv_PTDNX-121212_1768951028.pdf', NULL, NULL, 'uploads/signatures/signature_PTDNX-121212_1768951028.png', 'verified', 3, '2026-01-21 07:21:19', '', 1, '2026-01-20 23:17:08', '2026-02-01 07:35:09', 0, NULL, 'SP/2026/5613'),
(29, 'PTDNX-88', 'J', NULL, NULL, 'Supervisior operation', 'PT DNX Indonesia', 'tenaga_teknis', 'Juru Las', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTDNX-88_1768959615.pdf', NULL, NULL, 'uploads/signatures/signature_PTDNX-88_1768959615.png', 'verified', 3, '2026-01-21 09:40:47', '', 1, '2026-01-21 01:40:15', '2026-02-01 07:35:09', 0, NULL, NULL),
(30, 'PTDNX08-65', 'K', NULL, NULL, 'Supervisior operation', 'PT DNX Indonesia', 'pengawas_teknis', 'Pengawas Teknis Elektrik', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTDNX08-65_1768962349.pdf', NULL, NULL, 'uploads/signatures/signature_PTDNX08-65_1768962349.png', 'verified', 3, '2026-01-21 10:26:30', '', 1, '2026-01-21 02:25:49', '2026-02-01 07:35:09', 0, NULL, '001/PT/MSM/01/2026'),
(31, 'PTGMA19', 'L', NULL, NULL, 'Produksi', 'PT Geopersada Mulai Abadi', 'pengawas_operasional', NULL, NULL, 'PT MSM', 'PT MSM', 'uploads/cv/cv_PTGMA19_1768966092.pdf', NULL, NULL, 'uploads/signatures/signature_PTGMA19_1768966092.png', 'verified', 3, '2026-01-21 11:28:39', '', 1, '2026-01-21 03:28:12', '2026-02-01 07:35:09', 0, NULL, '001/PO/MSM/01/2026'),
(35, 'PTDNX-121111', 'Agriawan iswahyudi', NULL, 'General', 'Rigger', 'PT DNX Indonesia', 'tenaga_teknis', 'Operator Alat Berat', NULL, 'PT Meares Soputan Mining (MSM)', 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTDNX-121111_1769463219.pdf', NULL, NULL, 'uploads/signatures/signature_PTDNX-121111_1769463219.png', 'verified', 3, '2026-02-02 08:11:59', '', 1, '2026-01-26 21:33:39', '2026-02-02 00:11:59', 5, '2026-02-02 00:59:44', '001/TT/MSM/01/2026'),
(38, 'PTGMA-0062', 'Y', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Operator Alat Angkut / Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA-0062_1769994759.docx', 'uploads/statements/statement_PTGMA-0062_1769994759.pdf', NULL, NULL, 'verified', 3, '2026-02-02 14:32:00', '', 1, '2026-02-02 01:12:39', '2026-02-02 06:32:00', 0, NULL, '001/TT/MSM/02/2026'),
(39, 'PTGMA-006211', 'Y', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Operator Alat Angkut / Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA-006211_1769994884.docx', 'uploads/statements/statement_PTGMA-006211_1769994884.pdf', NULL, NULL, 'verified', 3, '2026-02-02 14:32:11', '', 1, '2026-02-02 01:14:44', '2026-02-02 06:32:11', 0, NULL, '002/TT/MSM/02/2026'),
(40, 'PTGMA20001', 'R', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'pengawas_operasional', 'Pengawas Operasional Madya', NULL, 'PT Meares Soputan Mining (MSM)', 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA20001_1770014170.pdf', 'uploads/statements/statement_PTGMA20001_1770014170.pdf', NULL, NULL, 'verified', 3, '2026-02-02 14:37:18', '', 1, '2026-02-02 06:36:10', '2026-02-02 06:37:18', 0, NULL, '001/PO/MSM/02/2026'),
(41, 'GMA9911', 'Taeyong', NULL, 'General', 'Superintendent', 'PT Geopersada Mulai Abadi', 'pengawas_operasional', 'Pengawas Operasional Pertama', NULL, 'PT Meares Soputan Mining (MSM)', 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_GMA9911_1770166884.pdf', 'uploads/statements/statement_GMA9911_1770166884.pdf', NULL, NULL, 'verified', 3, '2026-02-04 13:17:23', '', 1, '2026-02-04 01:01:24', '2026-02-04 05:17:23', 0, NULL, '003/PO/MSM/02/2026'),
(42, 'GMA9911to', 'Taeyong', NULL, 'General', 'Superintendent', 'PT Geopersada Mulai Abadi', 'pengawas_operasional', 'Pengawas Operasional Pertama', NULL, 'PT Meares Soputan Mining (MSM)', 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_GMA9911to_1770167120.pdf', 'uploads/statements/statement_GMA9911to_1770167120.pdf', NULL, NULL, 'verified', 3, '2026-02-04 11:14:39', '', 1, '2026-02-04 01:05:20', '2026-02-04 03:14:39', 0, NULL, '002/PO/MSM/02/2026'),
(43, 'www', 'l', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'pengawas_teknis', 'Ahli Listrik', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_www_1770167499.docx', 'uploads/statements/statement_www_1770167499.pdf', NULL, NULL, 'verified', 3, '2026-02-04 09:58:51', '', 1, '2026-02-04 01:11:39', '2026-02-04 01:58:51', 0, NULL, '002/PT/MSM/02/2026'),
(44, 'wwwsa', 'l', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'pengawas_teknis', 'Dokter', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_wwwsa_1770167746.pdf', 'uploads/statements/statement_wwwsa_1770167746.pdf', NULL, NULL, 'verified', 3, '2026-02-04 09:51:41', '', 1, '2026-02-04 01:15:46', '2026-02-04 01:51:41', 0, NULL, '001/PT/MSM/02/2026'),
(45, 'GMAAQQ', 'Windy', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Higiene Industri', NULL, NULL, 'PT MSM', 'uploads/cv/cv_GMAAQQ_1770257246.docx', 'uploads/statements/statement_GMAAQQ_1770257246.pdf', NULL, NULL, 'verified', 3, '2026-02-05 10:15:37', '', 1, '2026-02-05 02:07:26', '2026-02-05 02:15:37', 0, NULL, '003/TT/MSM/02/2026'),
(53, 'PTGMA-001', 'Agriawan iswahyudi', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Juru Ledak', NULL, NULL, 'PT MSM', 'uploads/cv/cv_PTGMA-001_1770786368.pdf', 'uploads/statements/statement_PTGMA-001_1770786368.pdf', NULL, NULL, 'verified', 3, '2026-02-11 13:06:44', '', 1, '2026-02-11 05:06:08', '2026-02-11 05:06:44', 0, NULL, '004/TT/MSM/02/2026'),
(54, 'PTGMA-002', 'Agri iswahyudi', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Juru Derek', NULL, 'Mining MTS', 'PT MSM', 'uploads/cv/cv_PTGMA-002_1770786768.pdf', 'uploads/statements/statement_PTGMA-002_1770786768.pdf', NULL, NULL, 'verified', 3, '2026-02-11 13:13:47', '', 1, '2026-02-11 05:12:48', '2026-02-11 05:13:47', 0, NULL, '005/TT/MSM/02/2026'),
(55, 'PTGMA-003', 'Agriawan', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Juru Angsir', NULL, NULL, 'PT MSM', 'uploads/cv/cv_PTGMA-003_1770787851.pdf', 'uploads/statements/statement_PTGMA-003_1770787851.pdf', NULL, NULL, 'verified', 3, '2026-02-11 13:31:43', '', 1, '2026-02-11 05:30:51', '2026-02-11 05:31:43', 0, NULL, '006/TT/MSM/02/2026'),
(56, 'PTGMA-010', 'Agriawan11', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Ahli Geoteknik', NULL, '', 'PT Tambang Tondano Nusajaya (TTN)', 'uploads/cv/cv_PTGMA-010_1770788976.pdf', 'uploads/statements/statement_PTGMA-010_1770788976.pdf', NULL, NULL, 'verified', 3, '2026-02-11 13:54:31', '', 1, '2026-02-11 05:49:36', '2026-02-11 05:54:31', 1, '2026-02-11 13:53:39', '001/TT/TTN/02/2026'),
(57, 'PTDNX', 'Agriawan12', NULL, 'General', 'Rigger', 'PT DNX Indonesia', 'pengawas_operasional', 'Pengawas Operasional Madya', NULL, 'Mining Geologis', 'PT MSM', 'uploads/cv/cv_PTDNX_1770807111.pdf', 'uploads/statements/statement_PTDNX_1770807111.pdf', NULL, NULL, 'verified', 3, '2026-02-11 19:13:27', '', 1, '2026-02-11 10:51:51', '2026-02-11 11:13:27', 0, NULL, '004/PO/MSM/02/2026'),
(58, 'ARCHI-26-004211', 'Windy', NULL, 'General', 'HSE Officer', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'K3', NULL, '', 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_ARCHI-26-004211_1770859590.docx', 'uploads/statements/statement_ARCHI-26-004211_1770859590.pdf', NULL, NULL, 'verified', 6, '2026-02-12 09:59:05', '', 1, '2026-02-12 01:26:30', '2026-02-12 01:59:05', 1, '2026-02-12 09:57:25', '007/TT/MSM/02/2026'),
(59, 'ARCHI-325-92811', 'Sarah', NULL, 'General', 'Maintenance Specialist', 'PT Geopersada Mulai Abadi', 'pengawas_teknis', 'Maintenance', NULL, NULL, 'PT MSM', 'uploads/cv/cv_ARCHI-325-92811_1770859775.docx', 'uploads/statements/statement_ARCHI-325-92811_1770859775.pdf', NULL, NULL, 'verified', 4, '2026-02-12 09:52:47', '', 1, '2026-02-12 01:29:35', '2026-02-12 01:52:47', 0, NULL, '003/PT/MSM/02/2026'),
(60, 'ARCHI-19-20182', 'Patricia', NULL, 'General', 'Supervisior Maintenance', 'PT Geopersada Mulai Abadi', 'pengawas_operasional', 'Pengawas Operasional Pertama', NULL, 'Fix Plant Maintenance', 'PT MSM', 'uploads/cv/cv_ARCHI-19-20182_1770859883.docx', 'uploads/statements/statement_ARCHI-19-20182_1770859883.pdf', NULL, NULL, 'verified', 5, '2026-02-12 09:50:23', '', 1, '2026-02-12 01:31:23', '2026-02-12 01:50:23', 0, NULL, '005/PO/MSM/02/2026'),
(61, 'ARCHI-21-044', 'Leo', NULL, 'HSE&Formalities', 'Maintenance Specialist', 'PT Meares Soputan Mining', 'pengawas_operasional', '', NULL, 'Fixed Plant Maintenance', 'PT MSM', 'uploads/cv/cv_ARCHI-21-044_1770865148.docx', 'uploads/statements/statement_ARCHI-21-044_1770865148.pdf', NULL, '', 'verified', 4, '2026-02-19 09:16:03', '', 1, '2026-02-12 02:59:08', '2026-02-19 01:17:10', 2, '2026-02-19 09:15:23', '006/PO/MSM/02/2026'),
(62, 'ARCHI-21-04454', 'Siska', NULL, 'HSE&Formalities', 'HSE Officer', 'PT Tambang Tondano Nusajaya', 'pengawas_teknis', 'K3', NULL, '', 'PT Tambang Tondano Nusajaya (TTN)', 'uploads/cv/cv_ARCHI-21-04454_1770865242.docx', 'uploads/statements/statement_ARCHI-21-04454_1770865242.pdf', NULL, '', 'verified', 3, '2026-02-12 14:07:40', '', 1, '2026-02-12 03:00:42', '2026-02-12 06:07:40', 1, '2026-02-12 11:06:20', '001/PT/TTN/02/2026'),
(64, 'ARCHI-21-04470', 'Windy', NULL, 'HSE&Formalities', 'Maintenance Specialist', 'PT Meares Soputan Mining', 'pengawas_operasional', '', NULL, 'Fixed Plant Maintenance', 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_ARCHI-21-04470_1770867194.docx', 'uploads/statements/statement_ARCHI-21-04470_1770867194.pdf', NULL, '', 'verified', 3, '2026-02-12 11:42:09', '', 1, '2026-02-12 03:33:14', '2026-02-12 03:42:09', 1, '2026-02-12 11:40:00', '007/PO/MSM/02/2026'),
(66, 'PTGMA-18318', 'A', NULL, 'General', 'Maintenance Specialist', 'PT Geopersada Mulai Abadi', 'pengawas_operasional', 'Pengawas Operasional Pertama', NULL, 'Fixed Plant Maintenance', 'PT MSM', 'uploads/cv/cv_PTGMA-18318_1770873956.docx', 'uploads/statements/statement_PTGMA-18318_1770873956.pdf', NULL, NULL, 'verified', 4, '2026-02-12 13:28:54', '', 1, '2026-02-12 05:25:56', '2026-02-12 05:28:54', 0, NULL, '008/PO/MSM/02/2026'),
(67, 'PTMAX-127898', 'R', NULL, 'General', 'Rigger', 'PT Maxidrill Indonesia', 'tenaga_teknis', 'Rigger', NULL, '', 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTMAX-127898_1770876371.docx', 'uploads/statements/statement_PTMAX-127898_1770876371.pdf', NULL, NULL, 'verified', NULL, '2026-02-16 03:26:33', 'Auto-verified for resubmit', 1, '2026-02-12 06:06:12', '2026-02-18 07:17:47', 15, '2026-02-16 03:26:33', '009/TT/MSM/02/2026'),
(71, '1355', 'Tes', NULL, 'General', 'Superintendent', 'PT Maxidrill Indonesia', 'pengawas_operasional', '', NULL, 'Fixed Plant Metallurgy', 'PT MSM', 'uploads/cv/cv_1355_1771414114.docx', 'uploads/statements/statement_1355_1771414114.pdf', NULL, NULL, 'verified', 4, '2026-03-05 13:38:48', '', 1, '2026-02-18 11:28:34', '2026-03-05 05:38:48', 1, '2026-03-04 14:27:01', '001/PO/MSM/03/2026'),
(72, 'PTDNX-10330', 'Lucas', NULL, 'General', 'Superintendent', 'PT DNX Indonesia', 'pengawas_teknis', 'Perancangan Tambang', NULL, NULL, 'PT MSM', 'uploads/cv/cv_PTDNX-10330_1771414326.pdf', 'uploads/statements/statement_PTDNX-10330_1771414326.pdf', NULL, NULL, 'verified', 3, '2026-02-18 19:33:14', '', 1, '2026-02-18 11:32:06', '2026-02-18 13:15:01', 1, NULL, '004/PT/MSM/02/2026'),
(73, 'PTGMA-253590', 'Tio', NULL, 'General', 'Superintendent', 'PT Geopersada Mulai Abadi', 'pengawas_teknis', 'Ahli Medis', NULL, NULL, 'PT MSM', 'uploads/cv/cv_PTGMA-253590_1771420788.docx', 'uploads/statements/statement_PTGMA-253590_1771420788.pdf', NULL, NULL, 'verified', 6, '2026-02-18 21:20:49', '', 1, '2026-02-18 13:19:48', '2026-02-19 01:12:51', 2, NULL, '005/PT/MSM/02/2026'),
(74, '10', 'Willy', NULL, 'General', 'Superintendent', 'PT Maxidrill Indonesia', 'pengawas_operasional', 'Ahli Eksplorasi', NULL, 'Eksplorasi', 'PT MSM', 'uploads/cv/cv_10_1771458818.pdf', 'uploads/statements/statement_10_1771458818.pdf', NULL, NULL, 'verified', 4, '2026-02-27 08:21:29', '', 1, '2026-02-18 23:53:38', '2026-02-27 00:21:29', 0, NULL, '010/PO/MSM/02/2026'),
(75, 'PTDNX-011619', 'Tes', NULL, 'General', 'Rigger', 'PT DNX Indonesia', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT TTN', 'uploads/cv/cv_PTDNX-011619_1771462521.pdf', 'uploads/statements/statement_PTDNX-011619_1771462521.pdf', NULL, NULL, 'verified', 5, '2026-02-27 08:20:50', '', 1, '2026-02-19 00:55:21', '2026-02-27 00:20:50', 0, NULL, '003/TT/TTN/02/2026'),
(76, 'ARCHI-21-8760', 'Tes', NULL, 'HSE&Formalities', 'HSE Officer', 'PT Meares Soputan Mining', 'pengawas_teknis', 'Petugas K3', NULL, '', 'PT MSM', 'uploads/cv/cv_ARCHI-21-8760_1771463258.pdf', 'uploads/statements/statement_ARCHI-21-8760_1771463258.pdf', NULL, '', 'verified', NULL, NULL, NULL, 1, '2026-02-19 01:07:38', '2026-02-26 02:16:36', 4, '2026-02-21 15:20:43', '006/PT/MSM/02/2026'),
(77, '12345', 'Tes GMA', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT MSM', 'uploads/cv/cv_12345_1771464664.docx', 'uploads/statements/statement_12345_1771464664.pdf', NULL, NULL, 'verified', 3, '2026-02-24 08:04:28', '', 1, '2026-02-19 01:31:04', '2026-02-24 00:04:29', 0, NULL, '013/TT/MSM/02/2026'),
(78, '7401A', 'Tes Aptekindo', NULL, 'General', 'Rigger', 'PT Aptekindo Mitra Solusitama', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT MSM', 'uploads/cv/cv_7401A_1771480188.docx', 'uploads/statements/statement_7401A_1771480188.pdf', NULL, NULL, 'verified', 6, '2026-02-27 08:20:13', '', 1, '2026-02-19 05:49:48', '2026-02-27 00:20:13', 0, NULL, '018/TT/MSM/02/2026'),
(79, '1526A', 'Tes', NULL, 'General', 'Rigger', 'PT Mandara Fasilitas Indonesia', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT MSM', 'uploads/cv/cv_1526A_1771480695.docx', 'uploads/statements/statement_1526A_1771480695.pdf', NULL, NULL, 'verified', 3, '2026-02-19 14:14:56', '', 1, '2026-02-19 05:58:15', '2026-02-19 06:14:56', 0, NULL, '010/TT/MSM/02/2026'),
(81, '13554245', 'T', NULL, 'General', 'Rigger', 'PT Meares Soputan Mining', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_13554245_1771482115.pdf', 'statement_13554245_1771482115.pdf', NULL, NULL, 'rejected', 3, '2026-02-24 08:04:14', 'tidak ada sertifikat', 1, '2026-02-19 06:21:55', '2026-03-10 04:09:08', 0, NULL, NULL),
(82, 'ARCHI-0931', 'Coba', NULL, 'Mining Tech Service', 'HSE Officer', 'PT Meares Soputan Mining', 'pengawas_teknis', 'K3', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_ARCHI-0931_1771546508.docx', 'uploads/statements/statement_ARCHI-0931_1771546508.pdf', NULL, NULL, 'verified', 4, '2026-02-20 08:43:32', '', 1, '2026-02-20 00:15:08', '2026-02-20 00:43:32', 0, NULL, '007/PT/MSM/02/2026'),
(83, '135542002', 'T', NULL, 'General', 'Rigger', 'PT Meares Soputan Mining', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_135542002_1771548583.pdf', 'statement_135542002_1771548583.pdf', NULL, NULL, 'verified', 5, '2026-02-21 11:51:55', '', 1, '2026-02-20 00:49:43', '2026-03-10 04:09:08', 0, NULL, '011/TT/MSM/02/2026'),
(84, 'PTMFI-20', 'Dani', NULL, 'General', 'Maintenance Specialist', 'PT Mandara Fasilitas Indonesia', 'pengawas_operasional', 'Maintenance', NULL, 'Fixed Plant Maintenance', 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTMFI-20_1771550458.docx', 'uploads/statements/statement_PTMFI-20_1771550458.pdf', NULL, NULL, 'verified', 5, '2026-02-21 11:51:07', '', 1, '2026-02-20 01:20:58', '2026-02-21 03:51:07', 0, NULL, '009/PO/MSM/02/2026'),
(85, 'PTGMA-8870', 'Tes notif email', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA-8870_1771644624.docx', 'uploads/statements/statement_PTGMA-8870_1771644624.pdf', NULL, NULL, 'verified', 3, '2026-02-25 10:43:33', '', 1, '2026-02-21 03:30:24', '2026-02-25 02:43:33', 0, NULL, '014/TT/MSM/02/2026'),
(86, 'PTGMA-65', 'Sriyanti', NULL, 'General', 'HSE Officer', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Petugas K3', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA-65_1771645824.pdf', 'uploads/statements/statement_PTGMA-65_1771645824.pdf', NULL, NULL, 'verified', 5, '2026-02-21 14:44:03', '', 1, '2026-02-21 03:50:24', '2026-02-21 06:44:03', 0, NULL, '012/TT/MSM/02/2026'),
(87, 'PTGMA', 'Coba GMA', NULL, 'General', 'Maintenance Specialist', 'PT Geopersada Mulai Abadi', 'pengawas_teknis', 'Maintenance', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA_1771991889.docx', 'uploads/statements/statement_PTGMA_1771991889.pdf', NULL, NULL, 'verified', 4, '2026-02-25 12:00:18', '', 1, '2026-02-25 03:58:09', '2026-02-25 04:00:18', 0, NULL, '008/PT/MSM/02/2026'),
(88, 'GMA', 'Coba Notif WA', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_GMA_1772001068.docx', 'uploads/statements/statement_GMA_1772001068.pdf', NULL, NULL, 'verified', 5, '2026-02-25 14:32:35', '', 1, '2026-02-25 06:31:08', '2026-02-25 06:32:35', 0, NULL, '015/TT/MSM/02/2026'),
(89, '1', 'tes', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT Tambang Tondano Nusajaya (TTN)', 'uploads/cv/cv_1_1772001815.pdf', 'uploads/statements/statement_1_1772001815.pdf', NULL, NULL, 'verified', 6, '2026-02-25 14:44:46', '', 1, '2026-02-25 06:43:35', '2026-02-25 06:44:46', 0, NULL, '002/TT/TTN/02/2026'),
(90, 'TES123', 'TES', NULL, 'General', 'Rigger', 'PT Meares Soputan Mining', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_TES123_1772075170.docx', 'statement_TES123_1772075170.pdf', NULL, NULL, 'verified', 6, '2026-02-27 08:19:57', '', 1, '2026-02-26 03:06:10', '2026-03-10 04:09:08', 0, NULL, '017/TT/MSM/02/2026'),
(91, '2', 'TESSS', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_2_1772076855.docx', 'uploads/statements/statement_2_1772076855.pdf', NULL, NULL, 'verified', 3, '2026-02-27 08:18:50', '', 1, '2026-02-26 03:34:15', '2026-02-27 00:18:50', 0, NULL, '016/TT/MSM/02/2026'),
(93, 'PTDNX-0111', 'D', NULL, 'General', 'Rigger', 'PT DNX Indonesia', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTDNX-0111_1772429955.docx', 'statement_PTDNX-0111_1772429955.pdf', NULL, NULL, 'verified', 5, '2026-03-04 13:45:27', '', 1, '2026-03-02 05:39:15', '2026-03-04 05:45:28', 0, NULL, '001/TT/MSM/03/2026'),
(96, 'ARCH-00-1012', 'D', NULL, 'General', 'Rigger', 'PT Meares Soputan Mining', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_ARCH-00-1012_1772430716.docx', 'statement_ARCH-00-1012_1772430716.pdf', NULL, NULL, 'rejected', NULL, NULL, 'Rejection from KTT: ubahkan no serti\n\nAdmin Notes: Reviewed by admin', 1, '2026-03-02 05:51:56', '2026-03-10 04:09:08', 0, NULL, '002/TT/MSM/03/2026'),
(97, 'ARCHH-0020', 'Han', NULL, 'General', 'HSE Officer', 'PT Meares Soputan Mining', 'tenaga_teknis', 'Ahli K3 Listrik ', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_ARCHH-0020_1772434195.docx', 'statement_ARCHH-0020_1772434195.pdf', NULL, NULL, 'rejected', 5, '2026-03-04 14:24:26', 'ubahkan masa berlaku dari sertifikat', 1, '2026-03-02 06:49:55', '2026-03-10 04:09:08', 0, NULL, NULL),
(98, 'PTGMA-6522', 'R', NULL, 'General', 'Rigger', 'PT Geopersada Mulai Abadi', 'pengawas_teknis', 'Rigger', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_PTGMA-6522_1772602871.docx', 'uploads/statements/statement_PTGMA-6522_1772602871.pdf', NULL, NULL, 'pending', NULL, NULL, NULL, 1, '2026-03-04 05:41:11', '2026-03-04 05:41:11', 0, NULL, NULL),
(99, 'ARCHI-17-2054', 'Vidya', NULL, 'HSE&Formalities', 'HSE Officer', 'PT Meares Soputan Mining', 'pengawas_teknis', 'Ahli K3 Listrik', NULL, '', 'PT MSM', 'uploads/cv/cv_ARCHI-17-2054_1772602968.pdf', 'uploads/statements/statement_ARCHI-17-2054_1772602968.pdf', NULL, '', 'verified', 3, '2026-03-17 09:13:38', '', 1, '2026-03-04 05:42:48', '2026-03-17 01:13:38', 1, '2026-03-17 09:12:14', '003/PT/MSM/03/2026'),
(100, 'ARCH-13-482', 'G', NULL, 'General', 'Rigger', 'PT Meares Soputan Mining', 'pengawas_teknis', 'Pengawas Teknis Mekanik', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_ARCH-13-482_1772603074.pdf', 'statement_ARCH-13-482_1772603074.pdf', NULL, NULL, 'verified', 6, '2026-03-12 14:47:54', '', 1, '2026-03-04 05:44:34', '2026-03-12 06:47:54', 0, NULL, '002/PT/MSM/03/2026'),
(101, 'archi-9081', 'k', NULL, 'General', 'Rigger', 'PT Meares Soputan Mining', 'tenaga_teknis', 'Rigger', NULL, '', 'PT MSM', 'uploads/cv/cv_archi-9081_1772686215.docx', 'statement_archi-9081_1772686215.pdf', 3, NULL, 'verified', 3, '2026-03-05 13:03:37', '', 1, '2026-03-05 04:50:15', '2026-03-10 04:09:08', 1, '2026-03-05 12:54:00', '003/TT/MSM/03/2026'),
(102, '14', 'Dinda', NULL, 'General', 'HSE Officer', 'PT Maxidrill Indonesia', 'pengawas_teknis', 'Ahli K3 Listrik', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_14_1773297823.docx', 'uploads/statements/statement_14_1773297823.pdf', NULL, NULL, 'verified', 3, '2026-03-12 14:45:34', '', 1, '2026-03-12 06:43:43', '2026-03-12 06:45:34', 0, NULL, '001/PT/MSM/03/2026'),
(103, '31', 'Queen', NULL, 'General', 'Rigger', 'PT Tambang Tondano Nusajaya (TTN)', 'tenaga_teknis', 'Rigger', NULL, NULL, 'PT Tambang Tondano Nusajaya (TTN)', 'uploads/cv/cv_31_1773622284.docx', 'statement_31_1773622284.pdf', 6, NULL, 'verified', 3, '2026-03-16 10:18:19', '', 1, '2026-03-16 00:51:24', '2026-03-16 02:18:19', 0, NULL, '001/TT/TTN/03/2026'),
(104, '244', 'Dodi', NULL, 'General', 'HSE Officer', 'PT Maxidrill Indonesia', 'tenaga_teknis', 'Ahli K3 Listrik', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_244_1773623373.docx', 'uploads/statements/statement_244_1773623373.pdf', NULL, NULL, 'pending', NULL, NULL, NULL, 1, '2026-03-16 01:09:33', '2026-03-16 01:09:33', 0, NULL, NULL),
(105, '9009', 'Tes', NULL, 'General', 'Maintenance Specialist', 'PT Geopersada Mulai Abadi', 'pengawas_operasional', 'Pengawas Operasional Pertama', NULL, 'Eksplorasi', 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_9009_1773708325.docx', 'uploads/statements/statement_9009_1773708325.pdf', NULL, NULL, 'verified', 6, '2026-03-17 08:46:20', '', 1, '2026-03-17 00:45:25', '2026-03-17 00:46:20', 0, NULL, '002/PO/MSM/03/2026'),
(106, '3638', 'Coba', NULL, 'General', 'Maintenance Specialist', 'PT Maxidrill Indonesia', 'pengawas_teknis', 'Maintenance', NULL, NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_3638_1773729441.docx', 'uploads/statements/statement_3638_1773729441.pdf', NULL, NULL, 'verified', 4, '2026-06-08 15:11:36', '', 1, '2026-03-17 06:37:21', '2026-06-08 07:11:36', 0, NULL, '001/PT/MSM/06/2026'),
(107, '3212115', 'Yoman', NULL, 'General', 'Juru Las', 'PT Intertek Utama Services', 'pengawas_teknis', 'Juru Las', '', '', 'PT MSM', 'uploads/cv/cv_3212115_1773732920.docx', 'uploads/statements/statement_3212115_1773732920.pdf', NULL, NULL, 'verified', 4, '2026-06-08 15:08:40', '', 1, '2026-03-17 07:35:20', '2026-06-08 07:08:40', 1, '2026-05-22 09:41:59', '004/PT/MSM/03/2026'),
(108, 'IDBADGE1', 'Tes', NULL, 'General', 'Superintendent', 'PT Maxidrill Indonesia', 'tenaga_teknis', 'Petugas Industrial Hygiene', 'Ahli Hygiene Industri Muda', NULL, 'PT Meares Soputan Mining (MSM)', 'uploads/cv/cv_IDBADGE1_1774509040.pdf', 'uploads/statements/statement_IDBADGE1_1774509040.pdf', NULL, NULL, 'verified', 3, '2026-03-27 09:58:57', '', 1, '2026-03-26 07:10:40', '2026-03-27 01:58:57', 0, NULL, '004/TT/MSM/03/2026');

-- --------------------------------------------------------

--
-- Table structure for table `employee_certifications`
--

CREATE TABLE `employee_certifications` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `certification_id` int NOT NULL,
  `cert_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cert_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cert_issuer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `expiry_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `document_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('active','expired','suspended','pending') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `verification_status` enum('pending','verified','rejected','expired') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `verified_by` int DEFAULT NULL,
  `verified_date` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_certifications`
--

INSERT INTO `employee_certifications` (`id`, `employee_id`, `certification_id`, `cert_type`, `cert_number`, `cert_issuer`, `issue_date`, `expiry_date`, `expiry_reason`, `document_file`, `status`, `verification_status`, `verified_by`, `verified_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Kompeten', 'qywi020', NULL, '2024-02-23', '2026-02-23', NULL, 'uploads/certifications/0910_cert_0_1767744929.pdf', 'pending', 'verified', 3, '2026-01-07 08:15:43', NULL, '2026-01-07 00:15:29', '2026-02-21 03:45:06'),
(2, 2, 2, 'Kompeten', '2i22b1939', NULL, '2023-03-13', '2026-03-13', NULL, 'uploads/certifications/PTSMA-21937_cert_0_1767746346.pdf', 'pending', 'verified', 3, '2026-01-07 09:46:35', NULL, '2026-01-07 00:39:06', '2026-02-21 03:45:06'),
(3, 3, 2, 'Kompeten', '2i22b1939', NULL, '2023-03-13', '2026-03-13', NULL, 'uploads/certifications/PTDNX-1360_cert_0_1767749042.pdf', 'pending', 'verified', 3, '2026-01-07 09:45:00', NULL, '2026-01-07 01:24:02', '2026-02-21 03:45:06'),
(4, 4, 6, 'Kompeten', '33333', NULL, '2024-02-25', '2026-02-25', NULL, 'uploads/certifications/PTDNX-819_cert_0_1767755487.pdf', 'pending', 'verified', 4, '2026-01-07 11:12:28', NULL, '2026-01-07 03:11:27', '2026-02-21 03:45:06'),
(5, 5, 3, 'Kompeten', '9014RO8', NULL, '2024-05-24', '2026-05-24', NULL, 'uploads/certifications/PTDNX-2812_cert_0_1767766143.pdf', 'pending', 'verified', 3, '2026-01-07 14:10:02', NULL, '2026-01-07 06:09:03', '2026-02-21 03:45:06'),
(6, 5, 4, 'Kompeten', '8U7YUU', NULL, '2025-10-05', '2026-10-05', NULL, 'uploads/certifications/PTDNX-2812_cert_1_1767766143.pdf', 'pending', 'verified', 3, '2026-01-07 14:10:06', NULL, '2026-01-07 06:09:03', '2026-02-21 03:45:06'),
(7, 6, 1, 'Kompeten', '1227RQJ998', '', '2026-01-22', '2029-01-22', '', 'uploads/certifications/PTDNX-183901_cert_0_1769035110.pdf', 'pending', 'verified', 3, '2026-01-22 06:39:25', NULL, '2026-01-07 06:24:39', '2026-02-21 03:45:06'),
(8, 7, 1, 'Kompeten', '2190JK02', NULL, '2024-03-23', '2026-03-23', NULL, 'uploads/certifications/PTDNX-2893H_cert_0_1767768294.pdf', 'pending', 'verified', 4, '2026-01-07 14:45:17', NULL, '2026-01-07 06:44:54', '2026-02-21 03:45:06'),
(9, 8, 1, 'Kompeten', '120/K/L/VII/2025', NULL, '2024-03-23', '2026-03-23', NULL, 'uploads/certifications/PTGMA-975490_cert_0_1767856106.pdf', 'pending', 'verified', 3, '2026-01-08 15:08:52', NULL, '2026-01-08 07:08:26', '2026-02-21 03:45:06'),
(10, 9, 1, 'Kompeten', '120/K/L/VII/2025', NULL, '2024-03-23', '2026-03-23', NULL, 'uploads/certifications/DNX-18228_cert_0_1767858906.pdf', 'pending', 'verified', 3, '2026-01-08 15:57:48', NULL, '2026-01-08 07:55:06', '2026-02-21 03:45:06'),
(11, 10, 5, 'Attendance/Peserta', '988D/K/2024', NULL, '2024-03-26', '2026-03-26', NULL, 'uploads/certifications/DNX-182281_cert_0_1767859013.pdf', 'pending', 'rejected', 3, '2026-01-08 15:57:11', NULL, '2026-01-08 07:56:53', '2026-02-21 03:45:06'),
(12, 10, 1, 'Kompeten', '5789/A/2025', NULL, '2025-04-27', '2026-04-27', NULL, 'uploads/certifications/DNX-182281_cert_1_1767859013.pdf', 'pending', 'verified', 3, '2026-01-08 15:57:16', NULL, '2026-01-08 07:56:53', '2026-02-21 03:45:06'),
(13, 14, 1, 'Kompeten', '4311.L/B129/2024', NULL, '2024-05-25', '2027-05-25', '', 'uploads/certifications/PTGMA-210_cert_0_1767927352.pdf', 'pending', 'verified', 3, '2026-01-09 11:47:01', NULL, '2026-01-09 02:55:52', '2026-02-21 03:45:06'),
(14, 15, 1, 'Kompeten', '1190/K/29/2023', NULL, '2023-05-14', '2026-05-14', '', 'uploads/certifications/PTG4S-1029_cert_0_1767935612.pdf', 'pending', 'verified', 3, '2026-01-09 13:13:51', NULL, '2026-01-09 05:13:32', '2026-02-21 03:45:06'),
(15, 16, 2, 'Kompeten', '1190/K/29/2023', NULL, '2023-05-14', '2026-05-14', '', 'uploads/certifications/PTG4S-10291_cert_0_1767936233.pdf', 'pending', 'verified', 4, '2026-01-09 13:24:10', NULL, '2026-01-09 05:23:53', '2026-02-21 03:45:06'),
(16, 16, 2, 'Kompeten', '3361/L/2023', NULL, '2023-08-19', '2026-08-19', '', 'uploads/certifications/PTG4S-10291_cert_1_1767936233.pdf', 'pending', 'verified', 4, '2026-01-09 13:24:16', NULL, '2026-01-09 05:23:53', '2026-02-21 03:45:06'),
(17, 17, 8, 'Attendance/Peserta', 'e2p111', NULL, '2023-09-10', '2026-09-10', NULL, 'uploads/certifications/09101A_cert_0_1767936413.pdf', 'pending', 'verified', 5, '2026-01-09 13:27:36', NULL, '2026-01-09 05:26:53', '2026-02-21 03:45:06'),
(18, 18, 1, 'Kompeten', 'e2p111', NULL, '2023-09-10', '2026-09-10', NULL, 'uploads/certifications/2203_cert_0_1767936695.pdf', 'pending', 'verified', 5, '2026-01-09 13:31:41', NULL, '2026-01-09 05:31:35', '2026-02-21 03:45:06'),
(19, 19, 1, 'Kompeten', 'e2p111', NULL, '2023-09-10', '2028-09-10', NULL, 'uploads/certifications/DNX-1822_cert_0_1767937651.pdf', 'pending', 'verified', 3, '2026-01-09 13:47:37', NULL, '2026-01-09 05:47:31', '2026-02-21 03:45:06'),
(20, 20, 1, 'Kompeten', 'No. Reg. PMB. 1238.08607 2024', NULL, '2024-11-18', '2029-11-18', '', 'uploads/certifications/PTM-1726_cert_0_1768175783.pdf', 'pending', 'verified', 3, '2026-01-12 07:57:11', NULL, '2026-01-11 23:56:23', '2026-02-21 03:45:06'),
(21, 21, 1, 'Kompeten', 'No. Reg. PMB. 1238.08607 2024', NULL, '2024-11-18', '2029-11-18', '', 'uploads/certifications/PTDNX-19200_cert_0_1768176434.pdf', 'pending', 'verified', 3, '2026-01-12 08:07:51', NULL, '2026-01-12 00:07:14', '2026-02-21 03:45:06'),
(22, 22, 1, 'Kompeten', '336', NULL, '2024-06-23', '2027-06-23', '', 'uploads/certifications/PTS-120_cert_0_1768264653.pdf', 'pending', 'verified', 5, '2026-01-13 08:38:18', NULL, '2026-01-13 00:37:33', '2026-02-21 03:45:06'),
(23, 23, 1, 'Kompeten', 'No. Reg. PMB. 1238.08606 2024', 'BNSP', '2024-11-18', '2029-11-18', '', 'uploads/certifications/PTSMA23-1346_cert_0_1768267900.pdf', 'pending', 'verified', 6, '2026-01-13 10:08:08', NULL, '2026-01-13 01:31:40', '2026-02-21 03:45:06'),
(24, 24, 10, 'Kompeten', 'No. Reg. PMB. 1238.00423 2023', 'BNSP', '2023-11-22', '2028-11-22', '', 'uploads/certifications/PTSMA16-300_cert_0_1768268405.pdf', 'pending', 'verified', 6, '2026-01-13 10:07:35', NULL, '2026-01-13 01:40:05', '2026-02-21 03:45:06'),
(25, 25, 8, 'Attendance/Peserta', '088/65.01.08/KP/BPD/2010', 'Minerba', '2010-02-10', '0000-00-00', 'tidak ada masa berlaku', 'uploads/certifications/PTSMA16-389_cert_0_1768275428.pdf', 'pending', 'rejected', 4, '2026-01-13 14:15:29', NULL, '2026-01-13 03:37:08', '2026-02-21 03:45:06'),
(26, 26, 1, 'Kompeten', 'Reg.PMB. 1238.08607 2024', 'BNSP', '2024-11-18', '2029-11-18', '', 'uploads/certifications/PTSMA17-616_cert_0_1768275969.pdf', 'pending', 'verified', 4, '2026-01-13 14:16:03', NULL, '2026-01-13 03:46:09', '2026-02-21 03:45:06'),
(27, 27, 5, 'Attendance/Peserta', '123123123', 'BNSP', '2026-01-01', '2027-01-01', '', 'uploads/certifications/adwa_cert_0_1768831988.pdf', 'pending', 'verified', 3, '2026-01-19 22:13:59', NULL, '2026-01-19 14:13:08', '2026-02-21 03:45:06'),
(28, 28, 6, 'Kompeten', '01241209371092837', 'BNSP', '2026-01-01', '2027-01-01', '', 'uploads/certifications/PTDNX-121212_cert_0_1768951028.pdf', 'pending', 'verified', 3, '2026-01-21 07:21:10', NULL, '2026-01-20 23:17:08', '2026-02-21 03:45:06'),
(29, 29, 5, 'Attendance/Peserta', '4311.L/B129/2024', 'BNSP', '2025-11-01', '2028-11-01', '', 'uploads/certifications/PTDNX-88_cert_0_1768959615.pdf', 'pending', 'verified', 3, '2026-01-21 09:40:45', NULL, '2026-01-21 01:40:15', '2026-02-21 03:45:06'),
(30, 30, 2, 'Kompeten', '4311.L/B129/2024', 'ESDM', '2025-11-01', '2028-11-01', '', 'uploads/certifications/PTDNX08-65_cert_0_1768962349.pdf', 'pending', 'verified', 3, '2026-01-21 10:26:27', NULL, '2026-01-21 02:25:49', '2026-02-21 03:45:06'),
(31, 31, 10, 'Kompeten', 'No. Reg. PMB. 1238.08607 2024', 'BNSP', '2024-11-18', '2029-11-18', '', 'uploads/certifications/PTGMA19_cert_0_1768966092.pdf', 'pending', 'verified', 3, '2026-01-21 11:28:37', NULL, '2026-01-21 03:28:12', '2026-02-21 03:45:06'),
(35, 35, 43, 'Kompeten', '01241209371092837', 'BNSP', '2026-01-23', '2028-01-23', '', 'uploads/certifications/PTDNX-121111_cert_0_1769463219.pdf', 'pending', 'verified', 3, '2026-02-02 08:11:57', NULL, '2026-01-26 21:33:39', '2026-02-21 03:45:06'),
(38, 38, 1, 'Kompeten', '4311.L/B129/2024', 'ESDM', '2024-11-29', '2027-11-29', '', 'uploads/certifications/PTGMA-0062_cert_0_1769994759.pdf', 'pending', 'verified', 3, '2026-02-02 14:31:55', NULL, '2026-02-02 01:12:39', '2026-02-21 03:45:06'),
(39, 39, 1, 'Kompeten', '4311.L/B129/2024', 'ESDM', '2024-11-18', '2029-11-18', '', 'uploads/certifications/PTGMA-006211_cert_0_1769994884.pdf', 'pending', 'verified', 3, '2026-02-02 14:32:09', NULL, '2026-02-02 01:14:44', '2026-02-21 03:45:06'),
(40, 40, 1, 'Kompeten', '4311.L/B129/2024', 'ESDM', '2024-11-29', '2027-11-29', '', 'uploads/certifications/PTGMA20001_cert_0_1770014170.pdf', 'pending', 'verified', 3, '2026-02-02 14:37:14', NULL, '2026-02-02 06:36:10', '2026-02-21 03:45:06'),
(41, 41, 1, 'Kompeten', 'No. Reg. PMB. 1238.09776 2024', 'ESDM', '2024-11-18', '2029-11-18', '', 'uploads/certifications/GMA9911_cert_0_1770166884.pdf', 'pending', 'verified', 3, '2026-02-04 13:17:20', NULL, '2026-02-04 01:01:24', '2026-02-21 03:45:06'),
(42, 42, 1, 'Kompeten', 'No. Reg. PMB. 1238.09776 2024', 'ESDM', '2024-11-18', '2029-11-18', '', 'uploads/certifications/GMA9911to_cert_0_1770167120.pdf', 'pending', 'verified', 3, '2026-02-04 11:14:35', NULL, '2026-02-04 01:05:20', '2026-02-21 03:45:06'),
(43, 43, 1, 'Kompeten', 'No. Reg. PMB. 1238.0976 2024', 'ESDM', '2024-11-18', '2029-11-18', '', 'uploads/certifications/www_cert_0_1770167499.pdf', 'pending', 'verified', 3, '2026-02-04 09:58:48', NULL, '2026-02-04 01:11:39', '2026-02-21 03:45:06'),
(44, 44, 1, 'Kompeten', 'No. Reg. PMB. 1238.0976 2024', 'ESDM', '2024-11-18', '2029-11-18', '', 'uploads/certifications/wwwsa_cert_0_1770167746.pdf', 'pending', 'verified', 3, '2026-02-04 09:56:44', NULL, '2026-02-04 01:15:46', '2026-02-21 03:45:06'),
(45, 45, 33, 'Kompeten', '4311.L/B129/2024', 'BNSP', '2024-05-24', '2029-05-24', '', 'uploads/certifications/GMAAQQ_cert_0_1770257246.pdf', 'pending', 'verified', 3, '2026-02-05 10:15:32', NULL, '2026-02-05 02:07:26', '2026-02-21 03:45:06'),
(52, 53, 41, 'Kompeten', '1239410298i34', 'BNSP', '2026-02-11', '2028-02-11', '', 'uploads/certifications/PTGMA-001_cert_0_1770786368.pdf', 'pending', 'verified', 3, '2026-02-11 13:06:42', NULL, '2026-02-11 05:06:08', '2026-02-21 03:45:06'),
(53, 54, 29, 'Kompeten', '102834780123', 'BNSP', '2026-02-11', '2028-02-11', '', 'uploads/certifications/PTGMA-002_cert_0_1770786768.pdf', 'pending', 'verified', 3, '2026-02-11 13:13:45', NULL, '2026-02-11 05:12:48', '2026-02-21 03:45:06'),
(54, 55, 28, 'Kompeten', '1083247108237410', 'BNSP', '2026-02-11', '2028-02-11', '', 'uploads/certifications/PTGMA-003_cert_0_1770787851.pdf', 'pending', 'verified', 3, '2026-02-11 13:31:41', NULL, '2026-02-11 05:30:51', '2026-02-21 03:45:06'),
(55, 56, 20, 'Kompeten', '123123123123', 'BNSP', '2026-02-11', '2028-02-11', '', 'uploads/certifications/PTGMA-010_cert_0_1770788976.pdf', 'pending', 'verified', 3, '2026-02-11 13:54:29', NULL, '2026-02-11 05:49:36', '2026-02-21 03:45:06'),
(56, 57, 34, 'Kompeten', '1231232131312344', 'BNSP', '2026-02-11', '2028-02-11', '', 'uploads/certifications/PTDNX_cert_0_1770807111.pdf', 'pending', 'verified', 3, '2026-02-11 19:13:27', NULL, '2026-02-11 10:51:51', '2026-02-21 03:45:06'),
(57, 58, 1, 'Kompeten', 'No. Reg. PMB. 1239.08607 2024', 'BNSP', '2024-05-20', '2028-05-20', '', 'uploads/certifications/ARCHI-26-004211_cert_0_1770859590.pdf', 'pending', 'verified', 6, '2026-02-12 09:59:05', NULL, '2026-02-12 01:26:30', '2026-02-21 03:45:06'),
(58, 59, 1, 'Kompeten', 'No. Reg. PMB. 1238.08607 2024', 'BNSP', '2024-05-20', '2028-05-20', '', 'uploads/certifications/ARCHI-325-92811_cert_0_1770859775.pdf', 'pending', 'verified', 4, '2026-02-12 09:52:47', NULL, '2026-02-12 01:29:35', '2026-02-21 03:45:06'),
(59, 60, 1, 'Kompeten', 'No. Reg. PMB. 1238.08307 2024', 'ESDM', '2022-09-10', '2027-09-10', '', 'uploads/certifications/ARCHI-19-20182_cert_0_1770859883.docx', 'pending', 'verified', 5, '2026-02-12 09:50:23', NULL, '2026-02-12 01:31:23', '2026-02-21 03:45:06'),
(60, 61, 1, 'Kompeten', '336', 'BNSP', '2023-06-14', '2028-06-14', '', 'uploads/certifications/ARCHI-21-044_cert_0_1770865148.pdf', 'pending', 'verified', 4, '2026-02-19 09:16:03', NULL, '2026-02-12 02:59:08', '2026-02-21 03:45:06'),
(61, 62, 31, 'Kompeten', '336', 'BNSP', '2023-06-14', '2028-06-14', '', 'uploads/certifications/ARCHI-21-04454_cert_0_1770865242.pdf', 'pending', 'verified', 3, '2026-02-12 14:07:40', NULL, '2026-02-12 03:00:42', '2026-02-21 03:45:06'),
(63, 64, 1, 'Kompeten', '336/PMB/11.2/2024', 'ESDM', '2023-06-14', '2028-06-14', '', 'uploads/certifications/ARCHI-21-04470_cert_0_1770867194.pdf', 'pending', 'verified', 3, '2026-02-12 11:42:09', NULL, '2026-02-12 03:33:14', '2026-02-21 03:45:06'),
(65, 66, 1, 'Kompeten', 'No. Reg. PMB. 1238.08607 2024', 'BNSP', '2024-05-20', '2028-05-20', '', 'uploads/certifications/PTGMA-18318_cert_0_1770873956.pdf', 'pending', 'verified', 4, '2026-02-12 13:28:54', NULL, '2026-02-12 05:25:56', '2026-02-21 03:45:06'),
(66, 67, 55, NULL, '4311.L/B129/2024', 'BNSP', '2024-11-20', '2027-11-20', '', 'uploads/certifications/PTMAX-127898_cert_0_1770876372.pdf', 'pending', 'verified', NULL, '2026-02-16 03:26:33', NULL, '2026-02-12 06:06:12', '2026-02-15 19:26:33'),
(70, 71, 1, 'Kompeten', 'No. Reg. PMB. 1238.08607 2024', 'ESDM', '2024-05-20', '2027-05-20', '', 'uploads/certifications/1355_cert_0_1771414114.pdf', 'pending', 'verified', 4, '2026-03-05 13:38:48', NULL, '2026-02-18 11:28:34', '2026-03-05 05:38:48'),
(71, 72, 1, 'Kompeten', 'No. Reg. PMB. 1238.08607 2024', 'Disnaker', '2024-05-20', '2028-05-20', '', 'uploads/certifications/PTDNX-10330_cert_0_1771414326.pdf', 'pending', 'verified', 3, '2026-02-18 19:33:14', NULL, '2026-02-18 11:32:06', '2026-02-21 03:45:06'),
(72, 73, 1, 'Kompeten', 'No. Reg. PMB. 1238.08607 2024', 'BNSP', '2024-05-20', '2028-05-20', '', 'uploads/certifications/PTGMA-253590_cert_0_1771420788.pdf', 'pending', 'verified', 6, '2026-02-18 21:20:49', NULL, '2026-02-18 13:19:48', '2026-02-21 03:45:06'),
(73, 74, 31, 'Kompeten', 'No. Reg. PMB. 1238.08607 2024', 'BNSP', '2024-05-20', '2028-05-20', '', 'uploads/certifications/10_cert_0_1771458818.pdf', 'pending', 'verified', 4, '2026-02-27 08:21:29', NULL, '2026-02-18 23:53:38', '2026-02-27 00:21:29'),
(74, 75, 55, NULL, 'No. Reg. PMB. 1238.08607 2024', 'BNSP', '2023-12-10', '2026-12-10', '', 'uploads/certifications/PTDNX-011619_cert_0_1771462521.pdf', 'pending', 'verified', 5, '2026-02-27 08:20:50', NULL, '2026-02-19 00:55:21', '2026-02-27 00:20:50'),
(75, 76, 31, 'Kompeten', '336/PMB/11.2/2024', 'ESDM', '2023-06-14', '2028-06-14', '', 'uploads/certifications/ARCHI-21-8760_cert_0_1771463258.pdf', 'pending', 'verified', NULL, NULL, NULL, '2026-02-19 01:07:38', '2026-02-26 02:10:32'),
(76, 77, 55, NULL, 'No. Reg. PMB. 1238.08607 2024', 'Disnaker', '2023-12-10', '2026-12-10', '', 'uploads/certifications/12345_cert_0_1771464664.pdf', 'pending', 'verified', 3, '2026-02-24 08:04:28', NULL, '2026-02-19 01:31:04', '2026-02-24 00:04:28'),
(77, 78, 55, NULL, 'No. Reg. PMB. 1238.08607 2024', 'Disnaker', '2023-12-10', '2026-12-10', '', 'uploads/certifications/7401A_cert_0_1771480189.pdf', 'pending', 'verified', 6, '2026-02-27 08:20:13', NULL, '2026-02-19 05:49:49', '2026-02-27 00:20:13'),
(78, 79, 55, NULL, '174790.K', 'Disnaker', '2024-05-11', '2027-05-11', '', 'uploads/certifications/1526A_cert_0_1771480695.pdf', 'pending', 'verified', 3, '2026-02-19 14:14:56', NULL, '2026-02-19 05:58:15', '2026-02-19 06:14:56'),
(79, 82, 31, 'Kompeten', '336/PMB/11.2/2024', 'BNSP', '2023-06-14', '2028-06-14', '', 'uploads/certifications/ARCHI-0931_cert_0_1771546508.pdf', 'pending', 'verified', 4, '2026-02-20 08:43:32', NULL, '2026-02-20 00:15:08', '2026-02-21 03:45:06'),
(80, 83, 55, NULL, '336/PMB/11.2/2024', 'BNSP', '2023-06-15', '2028-06-15', '', 'uploads/certifications/135542002_cert_0_1771548583.pdf', 'pending', 'verified', 5, '2026-02-21 11:51:55', NULL, '2026-02-20 00:49:43', '2026-02-21 03:51:55'),
(81, 83, 31, 'Kompeten', '336/PMB/11.2/2024', 'Disnaker', '2023-05-22', '2028-05-22', '', 'uploads/certifications/135542002_cert_1_1771548583.pdf', 'pending', 'verified', 5, '2026-02-21 11:51:55', NULL, '2026-02-20 00:49:43', '2026-02-21 03:51:55'),
(82, 84, 1, 'Kompeten', 'No. Reg. PMB. 1238.08607 2024', 'BNSP', '2024-05-20', '2028-05-20', '', 'uploads/certifications/PTMFI-20_cert_0_1771550458.pdf', 'pending', 'verified', 5, '2026-02-21 11:51:07', NULL, '2026-02-20 01:20:58', '2026-02-21 03:51:07'),
(83, 85, 55, NULL, '174790.K', 'ESDM', '2024-05-11', '2027-05-11', '', 'uploads/certifications/PTGMA-8870_cert_0_1771644624.pdf', 'pending', 'verified', 3, '2026-02-25 10:43:33', NULL, '2026-02-21 03:30:24', '2026-02-25 02:43:33'),
(84, 86, 31, 'Competent', '174790.K', 'ESDM', '2024-05-11', '2027-05-11', '', 'uploads/certifications/PTGMA-65_cert_0_1771645824.pdf', 'pending', 'verified', 5, '2026-02-21 14:44:03', NULL, '2026-02-21 03:50:24', '2026-02-21 06:44:03'),
(85, 87, 1, 'Competent', 'No. Reg. PMB. 1238.08607 2024', 'BNSP', '2024-05-20', '2028-05-20', '', 'uploads/certifications/PTGMA_cert_0_1771991889.pdf', 'pending', 'verified', 4, '2026-02-25 12:00:18', NULL, '2026-02-25 03:58:09', '2026-02-25 04:00:18'),
(86, 88, 55, 'Competent', 'No. Reg. PMB. 1238.08607 2024', 'BNSP', '2024-05-20', '2028-05-20', '', 'uploads/certifications/GMA_cert_0_1772001068.pdf', 'pending', 'verified', 5, '2026-02-25 14:32:35', NULL, '2026-02-25 06:31:08', '2026-02-25 06:32:35'),
(87, 89, 55, 'Competent', 'No. Reg. PMB. 1238.08607 2024', 'Disnaker', '2024-05-20', '2028-05-20', '', 'uploads/certifications/1_cert_0_1772001815.pdf', 'pending', 'verified', 6, '2026-02-25 14:44:46', NULL, '2026-02-25 06:43:35', '2026-02-25 06:44:46'),
(88, 90, 55, NULL, '336/PMB/11.2/2024', 'ESDM', '2023-06-15', '2028-06-15', '', 'uploads/certifications/TES123_cert_0_1772075170.pdf', 'pending', 'verified', 6, '2026-02-27 08:19:57', NULL, '2026-02-26 03:06:10', '2026-02-27 00:19:57'),
(89, 91, 55, 'Attendance/Participant', 'No. Reg. PMB. 1238.08607 2024', 'Minerba', '2024-05-20', '2028-05-20', '', 'uploads/certifications/2_cert_0_1772076855.pdf', 'pending', 'verified', 3, '2026-02-27 08:18:50', NULL, '2026-02-26 03:34:15', '2026-02-27 00:18:50'),
(90, 93, 55, NULL, '336/PMB/11.2/2024', 'Disnaker', '2023-06-15', '2028-06-15', '', 'uploads/certifications/PTDNX-0111_cert_0_1772429955.pdf', 'pending', 'verified', 5, '2026-03-04 13:45:27', NULL, '2026-03-02 05:39:15', '2026-03-04 05:45:27'),
(91, 96, 55, NULL, '336/PMB/11.2/2024', 'BNSP', '2023-06-15', '2028-06-15', '', 'uploads/certifications/ARCH-00-1012_cert_0_1772430716.pdf', 'pending', 'verified', 3, '2026-03-04 14:22:45', NULL, '2026-03-02 05:51:56', '2026-03-04 06:22:45'),
(92, 97, 31, NULL, '4311.L/B129/2024', 'Disnaker', '2023-05-22', '2026-05-22', '', 'uploads/certifications/ARCHH-0020_cert_0_1772434195.pdf', 'pending', 'pending', NULL, NULL, NULL, '2026-03-02 06:49:55', '2026-03-02 06:49:55'),
(93, 98, 55, 'Competent', 'No. Reg. PMB. 1238.08607 2024', 'Disnaker', '2024-04-22', '2027-04-22', '', 'uploads/certifications/PTGMA-6522_cert_0_1772602871.pdf', 'pending', 'pending', NULL, NULL, NULL, '2026-03-04 05:41:11', '2026-03-04 05:41:11'),
(94, 99, 31, NULL, '4311.L/B129/2024', 'ESDM', '2023-05-22', '2026-05-22', '', 'uploads/certifications/ARCHI-17-2054_cert_0_1772602968.pdf', 'pending', 'verified', 3, '2026-03-17 09:13:38', NULL, '2026-03-04 05:42:48', '2026-03-17 01:13:38'),
(95, 100, 31, NULL, 'No. 183602/K/L/2024', 'Disnaker', '2024-05-22', '2027-05-22', '', 'uploads/certifications/ARCH-13-482_cert_0_1772603074.pdf', 'pending', 'verified', 6, '2026-03-12 14:47:54', NULL, '2026-03-04 05:44:34', '2026-03-12 06:47:54'),
(96, 101, 55, NULL, '336/PMB/11.2/202436', 'Disnaker', '2023-05-22', '2026-05-22', '', 'uploads/certifications/archi-9081_cert_0_1772686215.pdf', 'pending', 'verified', 3, '2026-03-05 13:03:37', NULL, '2026-03-05 04:50:15', '2026-03-05 05:03:37'),
(97, 102, 40, 'Competent', '138733.K.AL2025', 'BNSP', '2023-06-22', '2026-06-22', '', 'uploads/certifications/14_cert_0_1773297823.docx', 'pending', 'verified', 3, '2026-03-12 14:45:34', NULL, '2026-03-12 06:43:43', '2026-03-12 06:45:34'),
(98, 103, 55, NULL, '19280/KL/09/2021', 'Disnaker', '2021-05-22', '2026-05-22', '', 'uploads/certifications/31_cert_0_1773622284.pdf', 'pending', 'verified', 3, '2026-03-16 10:18:18', NULL, '2026-03-16 00:51:24', '2026-03-16 02:18:18'),
(99, 104, 31, 'Competent', 'No. Reg. PMB. 1238.08607 2024', 'BNSP', '2022-05-22', '2027-05-22', '', 'uploads/certifications/244_cert_0_1773623373.pdf', 'pending', 'pending', NULL, NULL, NULL, '2026-03-16 01:09:33', '2026-03-16 01:09:33'),
(100, 105, 1, 'Competent', '198260.P.K/2024', 'Disnaker', '2024-06-23', '2027-06-23', '', 'uploads/certifications/9009_cert_0_1773708325.pdf', 'pending', 'verified', 6, '2026-03-17 08:46:20', NULL, '2026-03-17 00:45:25', '2026-03-17 00:46:20'),
(101, 106, 1, 'Competent', '198260.P.K/2024.09', 'Disnaker', '2024-06-23', '2027-06-23', '', 'uploads/certifications/3638_cert_0_1773729441.pdf', 'pending', 'verified', 4, '2026-06-08 15:11:36', NULL, '2026-03-17 06:37:21', '2026-06-08 07:11:36'),
(102, 107, 5, 'Competent', '142537.K/2024', 'Disnaker', '2023-05-22', '2026-05-22', '', 'uploads/certifications/3212115_cert_0_1773732920.pdf', 'pending', 'verified', 4, '2026-06-08 15:08:40', NULL, '2026-03-17 07:35:20', '2026-06-08 07:08:40'),
(103, 107, 1, 'Competent', '336/PMB/11.2/2024', 'BNsSP', '2022-05-21', '2027-05-21', '', 'uploads/certifications/3212115_cert_1_1773732920.pdf', 'pending', 'verified', 4, '2026-06-08 15:08:40', NULL, '2026-03-17 07:35:20', '2026-06-08 07:08:40'),
(104, 108, 33, 'Competent', '17268/K/09/2024', 'BNSP', '2024-12-23', '2027-12-23', '', 'uploads/certifications/IDBADGE1_cert_0_1774509040.pdf', 'pending', 'verified', 3, '2026-03-27 09:58:57', NULL, '2026-03-26 07:10:40', '2026-03-27 01:58:57');

-- --------------------------------------------------------

--
-- Table structure for table `ktt_approvals`
--

CREATE TABLE `ktt_approvals` (
  `id` int NOT NULL,
  `appointment_id` int NOT NULL,
  `ktt_user_id` int NOT NULL,
  `action` enum('approve','reject') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `approval_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `approval_date` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ktt_approvals`
--

INSERT INTO `ktt_approvals` (`id`, `appointment_id`, `ktt_user_id`, `action`, `approval_notes`, `approval_date`) VALUES
(1, 1, 7, 'approve', 'setuju untuk surat penunjukan', '2026-01-07 10:17:09'),
(2, 2, 7, 'approve', 'setuju untuk dikeluarkan surat penunjukan', '2026-01-07 10:17:48'),
(3, 1, 8, 'approve', 'setuju', '2026-01-07 10:18:22'),
(4, 2, 8, 'approve', 'setuju\r\n', '2026-01-07 10:18:36'),
(5, 3, 7, 'approve', 'se7', '2026-01-07 11:26:28'),
(6, 3, 8, 'approve', 'berhasil', '2026-01-07 11:27:18'),
(7, 5, 7, 'approve', '', '2026-01-09 13:55:27'),
(8, 5, 8, 'approve', '', '2026-01-09 13:55:49'),
(9, 6, 7, 'approve', '', '2026-01-12 08:09:55'),
(10, 6, 8, 'approve', '', '2026-01-12 08:10:46'),
(11, 7, 8, 'approve', '', '2026-01-13 08:39:42'),
(12, 7, 7, 'approve', '', '2026-01-13 08:40:02'),
(13, 8, 7, 'approve', '', '2026-01-13 10:33:03'),
(14, 9, 7, 'approve', '', '2026-01-13 10:33:09'),
(15, 8, 8, 'approve', '', '2026-01-13 10:49:17'),
(16, 9, 8, 'approve', '', '2026-01-13 10:49:23'),
(17, 10, 7, 'reject', 'sertifikat expired\r\n', '2026-01-13 14:17:05'),
(18, 11, 7, 'approve', '', '2026-01-13 14:17:44'),
(19, 11, 8, 'approve', '', '2026-01-13 14:34:07'),
(20, 12, 7, 'approve', '', '2026-01-19 22:14:56'),
(21, 12, 8, 'approve', '', '2026-01-19 22:15:07'),
(22, 13, 7, 'approve', '', '2026-01-21 14:15:40'),
(23, 15, 7, 'approve', '', '2026-01-21 14:15:44'),
(24, 16, 7, 'approve', '', '2026-01-21 14:15:47'),
(25, 13, 8, 'approve', '', '2026-01-21 14:15:57'),
(26, 15, 8, 'approve', '', '2026-01-21 14:16:05'),
(27, 16, 8, 'approve', '', '2026-01-21 14:16:09'),
(28, 17, 7, 'approve', '', '2026-01-22 06:41:57'),
(29, 17, 8, 'approve', '', '2026-01-22 06:42:13'),
(66, 20, 7, 'approve', '', '2026-02-02 14:39:03'),
(67, 22, 7, 'approve', '', '2026-02-02 14:39:07'),
(68, 23, 7, 'approve', '', '2026-02-02 14:39:10'),
(69, 24, 7, 'approve', '', '2026-02-02 14:39:13'),
(70, 20, 8, 'approve', '', '2026-02-02 14:39:30'),
(71, 23, 8, 'approve', '', '2026-02-02 14:39:33'),
(72, 22, 8, 'approve', '', '2026-02-02 14:39:37'),
(73, 24, 8, 'approve', '', '2026-02-02 14:39:40'),
(74, 25, 7, 'approve', '', '2026-02-04 13:13:34'),
(75, 26, 7, 'approve', '', '2026-02-04 13:13:37'),
(76, 27, 7, 'approve', '', '2026-02-04 13:13:41'),
(77, 25, 8, 'approve', '', '2026-02-04 13:13:51'),
(78, 26, 8, 'approve', '', '2026-02-04 13:13:56'),
(79, 27, 8, 'approve', '', '2026-02-04 13:13:59'),
(80, 28, 7, 'approve', '', '2026-02-07 10:40:24'),
(81, 29, 7, 'approve', '', '2026-02-07 10:40:27'),
(82, 28, 8, 'approve', '', '2026-02-07 10:43:00'),
(83, 29, 8, 'approve', '', '2026-02-07 10:43:03'),
(95, 37, 8, 'approve', '', '2026-02-11 13:07:11'),
(97, 37, 7, 'approve', '', '2026-02-11 13:08:30'),
(99, 38, 8, 'approve', '', '2026-02-11 13:28:52'),
(100, 38, 7, 'approve', '', '2026-02-11 13:29:29'),
(102, 39, 7, 'approve', '', '2026-02-11 13:32:13'),
(103, 39, 8, 'approve', '', '2026-02-11 13:48:16'),
(107, 40, 8, 'approve', '', '2026-02-11 14:05:54'),
(108, 41, 7, 'approve', '', '2026-02-11 19:13:44'),
(110, 44, 8, 'approve', '', '2026-02-12 10:47:08'),
(111, 43, 8, 'approve', '', '2026-02-12 10:47:17'),
(113, 42, 8, 'approve', '', '2026-02-12 10:48:03'),
(114, 44, 7, 'approve', '', '2026-02-12 10:48:25'),
(115, 42, 7, 'approve', '', '2026-02-12 10:49:46'),
(116, 43, 7, 'approve', '', '2026-02-12 10:49:54'),
(118, 41, 8, 'approve', '', '2026-02-12 11:39:20'),
(119, 47, 8, 'approve', '', '2026-02-12 11:39:24'),
(121, 47, 7, 'approve', '', '2026-02-12 13:02:23'),
(123, 46, 7, 'approve', '', '2026-02-12 13:08:01'),
(128, 48, 8, 'approve', '', '2026-02-12 13:36:49'),
(129, 48, 7, 'approve', '', '2026-02-12 13:37:29'),
(133, 51, 7, 'approve', '', '2026-02-12 14:08:29'),
(142, 51, 8, 'approve', '', '2026-02-12 15:24:11'),
(152, 50, 8, 'approve', '', '2026-02-18 15:18:15'),
(154, 55, 8, 'approve', '', '2026-02-18 19:34:03'),
(155, 55, 7, 'approve', '', '2026-02-18 21:15:37'),
(157, 56, 8, 'approve', '', '2026-02-19 08:01:11'),
(159, 57, 7, 'approve', '', '2026-02-19 14:02:43'),
(161, 46, 8, 'approve', '', '2026-02-20 08:45:42'),
(162, 62, 7, 'approve', '', '2026-02-21 14:44:53'),
(163, 62, 8, 'approve', '', '2026-02-21 14:45:05'),
(164, 56, 7, 'approve', '', '2026-02-21 15:32:00'),
(165, 60, 8, 'approve', '', '2026-02-21 15:34:50'),
(166, 58, 7, 'approve', '', '2026-02-24 08:02:22'),
(167, 59, 7, 'approve', '', '2026-02-24 08:02:25'),
(168, 60, 7, 'approve', '', '2026-02-24 08:02:29'),
(169, 61, 7, 'approve', '', '2026-02-24 08:03:30'),
(170, 58, 8, 'approve', '', '2026-02-25 09:08:22'),
(171, 64, 7, 'approve', '', '2026-02-25 10:45:58'),
(172, 64, 8, 'approve', '', '2026-02-25 10:46:10'),
(173, 65, 8, 'approve', '', '2026-02-25 12:01:18'),
(174, 66, 8, 'approve', '', '2026-02-25 14:37:17'),
(175, 66, 7, 'approve', '', '2026-02-25 14:37:26'),
(176, 67, 7, 'approve', '', '2026-02-25 14:45:12'),
(177, 67, 8, 'approve', '', '2026-02-25 14:45:22'),
(178, 57, 8, 'approve', '', '2026-02-26 08:28:01'),
(179, 63, 7, 'approve', '', '2026-02-27 08:24:31'),
(180, 65, 7, 'approve', '', '2026-02-27 08:24:35'),
(181, 72, 7, 'approve', '', '2026-02-27 08:40:29'),
(182, 59, 8, 'approve', '', '2026-02-27 09:33:22'),
(183, 68, 7, 'approve', '', '2026-03-02 09:11:37'),
(184, 69, 7, 'approve', '', '2026-03-02 09:11:40'),
(185, 70, 7, 'approve', '', '2026-03-02 09:11:43'),
(186, 71, 7, 'approve', '', '2026-03-02 09:11:46'),
(187, 61, 8, 'approve', '', '2026-03-02 09:12:02'),
(188, 63, 8, 'approve', '', '2026-03-02 09:19:59'),
(190, 72, 8, 'approve', '', '2026-03-02 10:26:59'),
(191, 68, 8, 'approve', '', '2026-03-02 10:27:36'),
(192, 69, 8, 'approve', '', '2026-03-02 10:28:06'),
(193, 70, 8, 'approve', '', '2026-03-02 10:28:30'),
(194, 71, 8, 'approve', '', '2026-03-02 10:29:54'),
(195, 73, 8, 'approve', '', '2026-03-04 14:21:37'),
(196, 73, 7, 'approve', '', '2026-03-04 14:21:46'),
(197, 74, 8, 'approve', '', '2026-03-04 14:23:03'),
(201, 75, 8, 'approve', '', '2026-03-05 13:27:38'),
(202, 75, 7, 'approve', '', '2026-03-05 13:35:18'),
(203, 76, 7, 'approve', '', '2026-03-05 13:49:33'),
(205, 76, 8, 'approve', '', '2026-03-05 13:51:21'),
(206, 77, 8, 'approve', '', '2026-03-12 15:17:06'),
(207, 78, 8, 'approve', '', '2026-03-12 15:17:10'),
(208, 77, 7, 'approve', '', '2026-03-16 10:19:20'),
(209, 78, 7, 'approve', '', '2026-03-16 10:21:17'),
(210, 79, 7, 'approve', '', '2026-03-16 10:22:05'),
(211, 79, 8, 'approve', '', '2026-03-16 10:22:15'),
(212, 80, 8, 'approve', '', '2026-03-17 08:49:52'),
(216, 81, 8, 'approve', '', '2026-03-17 09:57:06'),
(217, 81, 7, 'approve', '', '2026-03-17 09:57:22'),
(218, 80, 7, 'approve', '', '2026-03-17 09:58:00'),
(221, 83, 7, 'approve', '', '2026-03-27 10:00:11'),
(222, 83, 8, 'approve', '', '2026-03-27 10:00:19'),
(224, 82, 7, 'approve', '', '2026-06-08 15:10:43'),
(227, 84, 7, 'approve', '', '2026-06-09 08:08:01');

-- --------------------------------------------------------

--
-- Table structure for table `ktt_rejections`
--

CREATE TABLE `ktt_rejections` (
  `id` int NOT NULL,
  `appointment_id` int NOT NULL,
  `ktt_user_id` int NOT NULL,
  `rejection_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `rejection_date` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int NOT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `message` text,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notification_logs`
--

INSERT INTO `notification_logs` (`id`, `notification_type`, `reference_id`, `company_name`, `message`, `sent_at`) VALUES
(1, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 15:45:02'),
(2, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 15:52:45'),
(3, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 15:53:59'),
(4, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 15:54:52'),
(5, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 15:57:08'),
(6, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 15:59:26'),
(7, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:00:11'),
(8, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:02:10'),
(9, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:03:36'),
(10, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:04:55'),
(11, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:05:32'),
(12, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:06:12'),
(13, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:09:03'),
(14, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:09:52'),
(15, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:19:18'),
(16, 'new_employee', 36, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: PTDNX-1211232\n• Nama: Agri\n• Jabatan: Rigger\n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:27:05'),
(17, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:29:27'),
(18, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:31:18'),
(19, 'appointment_rejected', 21, 'PT DNX Indonesia', '⚠️ *NOTIFIKASI SURAT DITOLAK*\n\nSurat penunjukan telah ditolak oleh KTT dan memerlukan review admin:\n\n📋 *Detail Surat:*\n• No. Surat: 001/PO/MSM/02/2026\n• Karyawan: Agri (PTDNX-1211232)\n• Jabatan: HSE Officer\n• Perusahaan: PT DNX Indonesia\n• Ditolak oleh: Tejo Prihantoro\n\n💬 *Alasan Penolakan:*\ntes email\r\n\n\n⚠️ Silakan login untuk me-review penolakan ini.\n', '2026-02-01 16:31:44'),
(20, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:36:42'),
(21, 'new_employee', 36, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: PTDNX-1211232\n• Nama: Agri\n• Jabatan: Rigger\n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:41:56'),
(22, 'new_employee', 37, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: PTDNX-122222\n• Nama: Agri\n• Jabatan: Rigger\n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:55:14'),
(23, 'new_employee', 1, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: 0910\n• Nama: windy\n• Jabatan: \n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:58:08'),
(24, 'appointment_rejected', 21, 'PT DNX Indonesia', '⚠️ *NOTIFIKASI SURAT DITOLAK*\n\nSurat penunjukan telah ditolak oleh KTT dan memerlukan review admin:\n\n📋 *Detail Surat:*\n• No. Surat: 001/PO/MSM/02/2026\n• Karyawan: Agri (PTDNX-1211232)\n• Jabatan: HSE Officer\n• Perusahaan: PT DNX Indonesia\n• Ditolak oleh: Agung Praptono\n\n💬 *Alasan Penolakan:*\ntes email\r\n\n\n⚠️ Silakan login untuk me-review penolakan ini.\n', '2026-02-01 16:58:34'),
(25, 'new_employee', 35, 'PT DNX Indonesia', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT DNX Indonesia* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: PTDNX-121111\n• Nama: Agriawan iswahyudi\n• Jabatan: Rigger\n• Perusahaan: PT DNX Indonesia\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-01 16:59:59'),
(26, 'appointment_rejected', 20, 'PT DNX Indonesia', '⚠️ *NOTIFIKASI SURAT DITOLAK*\n\nSurat penunjukan telah ditolak oleh KTT dan memerlukan review admin:\n\n📋 *Detail Surat:*\n• No. Surat: 001/TT/MSM/01/2026\n• Karyawan: Agriawan iswahyudi (PTDNX-121111)\n• Jabatan: HSE Officer\n• Perusahaan: PT DNX Indonesia\n• Ditolak oleh: Tejo Prihantoro\n\n💬 *Alasan Penolakan:*\ntes email\n\n⚠️ Silakan login untuk me-review penolakan ini.\n', '2026-02-01 17:03:22'),
(27, 'new_employee', 40, 'PT Geopersada Mulai Abadi', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT Geopersada Mulai Abadi* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID Batch: PTGMA20001\n• Nama: R\n• Jabatan: Rigger\n• Perusahaan: PT Geopersada Mulai Abadi\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-02 06:36:35'),
(28, 'new_employee', 42, 'PT Geopersada Mulai Abadi', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT Geopersada Mulai Abadi* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID BADGE: GMA9911to\n• Nama: Taeyong\n• Jabatan: Superintendent\n• Perusahaan: PT Geopersada Mulai Abadi\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-04 01:05:20'),
(29, 'new_employee', 44, 'PT Geopersada Mulai Abadi', '🔔 *NOTIFIKASI TENAGA KERJA BARU*\n\nPerusahaan *PT Geopersada Mulai Abadi* telah menambahkan tenaga kerja baru yang memerlukan verifikasi:\n\n📋 *Detail Karyawan:*\n• ID BADGE: wwwsa\n• Nama: l\n• Jabatan: Rigger\n• Perusahaan: PT Geopersada Mulai Abadi\n\n⚠️ Silakan login ke sistem untuk melakukan verifikasi.\n', '2026-02-04 01:16:05'),
(30, 'new_employee', 47, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: GMA31-982\n• Name: A\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n', '2026-02-09 00:10:02'),
(31, 'new_employee', 48, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-87441\n• Name: Y\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-09 00:46:35'),
(32, 'new_employee', 49, 'PT MSM', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT MSM* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-19-044\n• Name: A\n• Position: Rigger\n• Company: PT MSM\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-09 01:44:30'),
(33, 'new_employee', 47, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: GMA31-982\n• Name: A\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-09 06:01:56'),
(34, 'new_employee', 48, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-87441\n• Name: Y\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-09 06:27:19'),
(35, 'new_employee', 50, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: GMA00007\n• Name: A\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-09 07:16:23'),
(36, 'appointment_rejected', 31, 'PT Geopersada Mulai Abadi', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 004/TT/MSM/02/2026\n• Employee: A (GMA31-982)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntest resubmit count\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-11 02:41:47'),
(37, 'new_employee', 47, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: GMA31-982\n• Name: A\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-11 03:00:17'),
(38, 'appointment_rejected', 33, 'PT Geopersada Mulai Abadi', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 005/TT/MSM/02/2026\n• Employee: A (GMA00007)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntes resubmit count\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-11 03:07:21'),
(39, 'new_employee', 51, 'PT DNX Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT DNX Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTDNX-001\n• Name: Agri\n• Position: Rigger\n• Company: PT DNX Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-11 03:56:26'),
(40, 'appointment_rejected', 35, 'PT DNX Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 006/TT/MSM/02/2026\n• Employee: Agri (PTDNX-001)\n• Position: HSE Officer\n• Company: PT DNX Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntest resubmit\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-11 03:58:39'),
(41, 'appointment_rejected', 34, 'PT Geopersada Mulai Abadi', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 005/PO/MSM/02/2026\n• Employee: W (PTGMA-9173)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntes\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-11 04:38:04'),
(42, 'new_employee', 50, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: GMA00007\n• Name: A\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-11 04:46:30'),
(43, 'new_employee', 52, 'PT DNX Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT DNX Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTDNX-002\n• Name: Agri\n• Position: Rigger\n• Company: PT DNX Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-11 05:00:06'),
(44, 'appointment_rejected', 36, 'PT DNX Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 006/PO/MSM/02/2026\n• Employee: Agri (PTDNX-002)\n• Position: HSE Officer\n• Company: PT DNX Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntes resubmit\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-11 05:02:18'),
(45, 'new_employee', 53, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-001\n• Name: Agriawan iswahyudi\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-11 05:06:22'),
(46, 'appointment_rejected', 37, 'PT Geopersada Mulai Abadi', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 004/TT/MSM/02/2026\n• Employee: Agriawan iswahyudi (PTGMA-001)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntes\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-11 05:07:40'),
(47, 'new_employee', 54, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-002\n• Name: Agri iswahyudi\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-11 05:13:03'),
(48, 'appointment_rejected', 38, 'PT Geopersada Mulai Abadi', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 005/TT/MSM/02/2026\n• Employee: Agri iswahyudi (PTGMA-002)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntes\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-11 05:14:17'),
(49, 'new_employee', 55, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-003\n• Name: Agriawan\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-11 05:31:08'),
(50, 'new_employee', 56, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-010\n• Name: Agriawan11\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-11 05:49:51'),
(51, 'appointment_rejected', 40, 'PT Geopersada Mulai Abadi', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 001/TT/TTN/02/2026\n• Employee: Agriawan11 (PTGMA-010)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nsadsz\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-11 05:51:19'),
(52, 'new_employee', 56, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-010\n• Name: Agriawan11\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-11 05:53:53'),
(53, 'new_employee', 57, 'PT DNX Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT DNX Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTDNX\n• Name: Agriawan12\n• Position: Rigger\n• Company: PT DNX Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-11 10:52:06'),
(54, 'appointment_rejected', 41, 'PT DNX Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 004/PO/MSM/02/2026\n• Employee: Agriawan12 (PTDNX)\n• Position: HSE Officer\n• Company: PT DNX Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nkureng\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-11 11:14:22'),
(55, 'new_employee', 58, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-26-004211\n• Name: Windy\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 01:26:57'),
(56, 'new_employee', 59, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-325-92811\n• Name: Sarah\n• Position: Maintenance Specialist\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 01:30:03'),
(57, 'new_employee', 60, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-19-20182\n• Name: Patricia\n• Position: Supervisior Maintenance\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 01:31:50'),
(58, 'new_employee', 58, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-26-004211\n• Name: Windy\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 01:57:47'),
(59, 'appointment_rejected', 41, 'PT DNX Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 004/PO/MSM/02/2026\n• Employee: Agriawan12 (PTDNX)\n• Position: HSE Officer\n• Company: PT DNX Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntidak sesuai\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 02:47:53'),
(60, 'new_employee', 61, 'PT Meares Soputan Mining', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Meares Soputan Mining* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-044\n• Name: Leo\n• Position: Maintenance Specialist\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 02:59:27'),
(61, 'new_employee', 62, 'PT Tambang Tondano Nusajaya', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Tambang Tondano Nusajaya* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-04454\n• Name: Siska\n• Position: HSE Officer\n• Company: PT Tambang Tondano Nusajaya\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 03:01:06'),
(62, 'new_employee', 63, 'PT Meares Soputan Mining', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Meares Soputan Mining* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-04476\n• Name: Windy\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 03:02:29'),
(63, 'new_employee', 62, '', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany ** has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-04454\n• Name: Siska\n• Position: HSE Officer\n• Company: PT Tambang Tondano Nusajaya\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 03:06:38'),
(64, 'new_employee', 64, 'PT Meares Soputan Mining', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Meares Soputan Mining* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-04470\n• Name: Windy\n• Position: Maintenance Specialist\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 03:33:42'),
(65, 'new_employee', 64, '', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany ** has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-04470\n• Name: Windy\n• Position: Maintenance Specialist\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 03:40:22'),
(66, 'appointment_rejected', 47, 'PT Meares Soputan Mining', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 007/PO/MSM/02/2026\n• Employee: Windy (ARCHI-21-04470)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntidak sesuai\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 04:23:43'),
(67, 'appointment_rejected', 45, 'PT Meares Soputan Mining', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 008/TT/MSM/02/2026\n• Employee: Windy (ARCHI-21-04476)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntidak sesuai\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 05:06:43'),
(68, 'appointment_rejected', 46, 'PT Meares Soputan Mining', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 006/PO/MSM/02/2026\n• Employee: Leo (ARCHI-21-044)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nperbaiki\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 05:08:52'),
(69, 'appointment_rejected', 46, 'PT Meares Soputan Mining', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 006/PO/MSM/02/2026\n• Employee: Leo (ARCHI-21-044)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntidak sesuai\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 05:12:36'),
(70, 'appointment_rejected', 45, 'PT Meares Soputan Mining', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 008/TT/MSM/02/2026\n• Employee: Windy (ARCHI-21-04476)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nperbaiki kembali\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 05:13:14'),
(71, 'new_employee', 65, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-19737\n• Name: Winday\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 05:21:10'),
(72, 'new_employee', 66, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-18318\n• Name: A\n• Position: Maintenance Specialist\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 05:26:16'),
(73, 'appointment_rejected', 49, 'PT Geopersada Mulai Abadi', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 004/PT/MSM/02/2026\n• Employee: Winday (PTGMA-19737)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nubah\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 05:36:31'),
(74, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 06:06:26'),
(75, 'appointment_rejected', 46, 'PT Meares Soputan Mining', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 006/PO/MSM/02/2026\n• Employee: Leo (ARCHI-21-044)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntidak sesuai\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 06:09:24'),
(76, 'appointment_rejected', 50, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 009/TT/MSM/02/2026\n• Employee: R (PTMAX-127898)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntidak sesuai\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 06:10:52'),
(77, 'new_employee', 68, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-101\n• Name: windah basuara\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 06:54:19'),
(78, 'appointment_rejected', 52, 'PT Geopersada Mulai Abadi', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 002/TT/TTN/02/2026\n• Employee: windah basuara (PTGMA-101)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntes resubmit\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 06:56:30'),
(79, 'new_employee', 68, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-101\n• Name: windah basuara\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 06:57:35'),
(80, 'new_employee', 69, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-222\n• Name: windah batubara\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:07:28'),
(81, 'new_employee', 69, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-222\n• Name: windah batubara\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:09:16'),
(82, 'new_employee', 70, 'PT DNX Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT DNX Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTDNX-222\n• Name: windah batubara\n• Position: Rigger\n• Company: PT DNX Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:12:25'),
(83, 'new_employee', 70, 'PT DNX Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT DNX Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTDNX-222\n• Name: windah batubara\n• Position: Rigger\n• Company: PT DNX Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:13:46'),
(84, 'appointment_rejected', 54, 'PT DNX Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 002/TT/TTN/02/2026\n• Employee: windah batubara (PTDNX-222)\n• Position: HSE Officer\n• Company: PT DNX Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nresubmit \n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 07:21:41'),
(85, 'new_employee', 70, 'PT DNX Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT DNX Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTDNX-222\n• Name: windah batubara\n• Position: Rigger\n• Company: PT DNX Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:22:40'),
(86, 'appointment_rejected', 50, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 009/TT/MSM/02/2026\n• Employee: R (PTMAX-127898)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nresubmit\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 07:24:39'),
(87, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:26:31'),
(88, 'appointment_rejected', 50, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 009/TT/MSM/02/2026\n• Employee: R (PTMAX-127898)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nresubmit\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 07:29:30'),
(89, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:30:34'),
(90, 'appointment_rejected', 50, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 009/TT/MSM/02/2026\n• Employee: R (PTMAX-127898)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nresubmit\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 07:34:41'),
(91, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:41:39'),
(92, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:47:03'),
(93, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:47:40'),
(94, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:51:53'),
(95, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 07:58:07'),
(96, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 08:06:46'),
(97, 'appointment_rejected', 50, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 009/TT/MSM/02/2026\n• Employee: R (PTMAX-127898)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nresubmit\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-12 08:08:24'),
(98, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-12 08:10:35'),
(99, 'appointment_rejected', 50, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 009/TT/MSM/02/2026\n• Employee: R (PTMAX-127898)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\npouibuoipbhoui\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-15 06:43:08'),
(100, 'appointment_rejected', 50, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 009/TT/MSM/02/2026\n• Employee: R (PTMAX-127898)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntes resubmit note\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-15 19:09:45'),
(101, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-15 19:10:59'),
(102, 'appointment_rejected', 50, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 009/TT/MSM/02/2026\n• Employee: R (PTMAX-127898)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntes resubmit note\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-15 19:19:09'),
(103, 'appointment_rejected', 50, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 009/TT/MSM/02/2026\n• Employee: R (PTMAX-127898)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntes resubmit reviewer - admin - ktt\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-15 19:25:47'),
(104, 'new_employee', 67, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMAX-127898\n• Name: R\n• Position: Rigger\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-15 19:26:43'),
(105, 'appointment_rejected', 50, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 009/TT/MSM/02/2026\n• Employee: R (PTMAX-127898)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\ntest resubmit only\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-15 19:32:15'),
(106, 'new_employee', 71, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 1355\n• Name: Tes\n• Position: Superintendent\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-18 11:28:45'),
(107, 'new_employee', 72, 'PT DNX Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT DNX Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTDNX-10330\n• Name: Lucas\n• Position: Superintendent\n• Company: PT DNX Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-18 11:32:17'),
(108, 'new_employee', 73, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-253590\n• Name: Tio\n• Position: Superintendent\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-18 13:20:00'),
(109, 'new_employee', 74, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 10\n• Name: Willy\n• Position: Superintendent\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-18 23:54:02'),
(110, 'new_employee', 75, 'PT DNX Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT DNX Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTDNX-011619\n• Name: Tes\n• Position: Rigger\n• Company: PT DNX Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-19 00:55:35'),
(111, 'new_employee', 76, 'PT Meares Soputan Mining', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Meares Soputan Mining* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-8760\n• Name: Tes\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-19 01:08:02'),
(112, 'appointment_rejected', 56, 'PT Geopersada Mulai Abadi', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 005/PT/MSM/02/2026\n• Employee: Tio (PTGMA-253590)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nubah\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-19 01:09:26'),
(113, 'new_employee', 61, 'HSE&Formalities', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *HSE&Formalities* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-044\n• Name: Leo\n• Position: Maintenance Specialist\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-19 01:15:37'),
(114, 'new_employee', 77, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 12345\n• Name: Tes GMA\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-19 01:31:14'),
(115, 'new_employee', 78, 'PT Aptekindo Mitra Solusitama', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Aptekindo Mitra Solusitama* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 7401A\n• Name: Tes Aptekindo\n• Position: Rigger\n• Company: PT Aptekindo Mitra Solusitama\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-19 05:50:10');
INSERT INTO `notification_logs` (`id`, `notification_type`, `reference_id`, `company_name`, `message`, `sent_at`) VALUES
(116, 'new_employee', 79, 'PT Mandara Fasilitas Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Mandara Fasilitas Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 1526A\n• Name: Tes\n• Position: Rigger\n• Company: PT Mandara Fasilitas Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-19 05:58:37'),
(117, 'appointment_rejected', 57, 'PT Meares Soputan Mining', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 006/PT/MSM/02/2026\n• Employee: Tes (ARCHI-21-8760)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• Rejected by: Agung Praptono\n\n💬 *Rejection Reason:*\nperbiaki\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-02-19 06:03:28'),
(118, 'new_employee', 76, 'HSE&Formalities', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *HSE&Formalities* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-8760\n• Name: Tes\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-19 06:25:52'),
(119, 'new_employee', 82, 'PT Meares Soputan Mining', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Meares Soputan Mining* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-0931\n• Name: Coba\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-20 00:15:24'),
(120, 'ktt_both_approved_admin', 46, 'PT Meares Soputan Mining', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 006/PO/MSM/02/2026\n• Employee: Leo (ARCHI-21-044)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-20 00:46:08'),
(121, 'new_employee', 84, 'PT Mandara Fasilitas Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Mandara Fasilitas Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTMFI-20\n• Name: Dani\n• Position: Maintenance Specialist\n• Company: PT Mandara Fasilitas Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-20 01:21:15'),
(122, 'new_employee', 85, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-8870\n• Name: Tes notif email\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-21 03:30:47'),
(123, 'new_employee', 86, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-65\n• Name: Sriyanti\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-21 03:50:43'),
(124, 'admin_accepted_employee', 84, 'PT Mandara Fasilitas Indonesia', '✅ *EMPLOYEE DATA ACCEPTED*\n\nThe following employee data has been *accepted* and verified by Admin:\n\n📋 *Employee Details:*\n• ID BADGE: PTMFI-20\n• Name: Dani\n• Company: PT Mandara Fasilitas Indonesia\n\nThe assign letter has been generated and is now pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-21 03:51:11'),
(125, 'admin_accepted_employee', 86, 'PT Geopersada Mulai Abadi', '✅ *EMPLOYEE DATA ACCEPTED*\n\nThe following employee data has been *accepted* and verified by Admin:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-65\n• Name: Sriyanti\n• Company: PT Geopersada Mulai Abadi\n\nThe assign letter has been generated and is now pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-21 06:44:14'),
(126, 'ktt_both_approved_admin', 62, 'PT Geopersada Mulai Abadi', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 012/TT/MSM/02/2026\n• Employee: Sriyanti (PTGMA-65)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-21 06:45:35'),
(127, 'ktt_approved_final_user_dept', 62, 'PT Geopersada Mulai Abadi', '🎉 *ASSIGN LETTER APPROVED*\n\nThe assign letter for the following employee has been *fully approved* by both KTTs:\n\n📋 *Letter Details:*\n• Letter No.: 012/TT/MSM/02/2026\n• Employee: Sriyanti (PTGMA-65)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n✅ The assign letter is now active and approved.\n📍 http://localhost/windy/appointments.php', '2026-02-21 06:45:39'),
(128, 'new_employee', 76, 'HSE&Formalities', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *HSE&Formalities* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-8760\n• Name: Tes\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-21 07:15:17'),
(129, 'new_employee', 76, 'HSE&Formalities', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *HSE&Formalities* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-21-8760\n• Name: Tes\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-21 07:21:05'),
(130, 'ktt_both_approved_admin', 56, 'PT Geopersada Mulai Abadi', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 005/PT/MSM/02/2026\n• Employee: Tio (PTGMA-253590)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-21 07:32:21'),
(131, 'ktt_approved_final_user_dept', 56, 'PT Geopersada Mulai Abadi', '🎉 *ASSIGN LETTER APPROVED*\n\nThe assign letter for the following employee has been *fully approved* by both KTTs:\n\n📋 *Letter Details:*\n• Letter No.: 005/PT/MSM/02/2026\n• Employee: Tio (PTGMA-253590)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n✅ The assign letter is now active and approved.\n📍 http://localhost/windy/appointments.php', '2026-02-21 07:32:26'),
(132, 'ktt_both_approved_admin', 60, 'PT Mandara Fasilitas Indonesia', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 009/PO/MSM/02/2026\n• Employee: Dani (PTMFI-20)\n• Position: HSE Officer\n• Company: PT Mandara Fasilitas Indonesia\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-24 00:02:49'),
(133, 'ktt_approved_final_user_dept', 60, 'PT Mandara Fasilitas Indonesia', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 009/PO/MSM/02/2026\n• Employee: Dani (PTMFI-20)\n• Position: HSE Officer\n• Company: PT Mandara Fasilitas Indonesia\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-02-24 00:03:08'),
(134, 'admin_accepted_employee', 77, 'PT Geopersada Mulai Abadi', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Omega Banea* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 12345\n• Name: Tes GMA\n• Company: PT Geopersada Mulai Abadi\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-24 00:04:34'),
(135, 'ktt_both_approved_admin', 58, 'PT Mandara Fasilitas Indonesia', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 010/TT/MSM/02/2026\n• Employee: Tes (1526A)\n• Position: HSE Officer\n• Company: PT Mandara Fasilitas Indonesia\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-25 01:08:48'),
(136, 'ktt_approved_final_user_dept', 58, 'PT Mandara Fasilitas Indonesia', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 010/TT/MSM/02/2026\n• Employee: Tes (1526A)\n• Position: HSE Officer\n• Company: PT Mandara Fasilitas Indonesia\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-02-25 01:08:56'),
(137, 'admin_accepted_employee', 85, 'PT Geopersada Mulai Abadi', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Omega Banea* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-8870\n• Name: Tes notif email\n• Company: PT Geopersada Mulai Abadi\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-25 02:43:38'),
(138, 'ktt_both_approved_admin', 64, 'PT Geopersada Mulai Abadi', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 014/TT/MSM/02/2026\n• Employee: Tes notif email (PTGMA-8870)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-25 02:46:31'),
(139, 'ktt_approved_final_user_dept', 64, 'PT Geopersada Mulai Abadi', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 014/TT/MSM/02/2026\n• Employee: Tes notif email (PTGMA-8870)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-02-25 02:46:36'),
(140, 'new_employee', 87, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA\n• Name: Coba GMA\n• Position: Maintenance Specialist\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-25 03:58:30'),
(141, 'admin_accepted_employee', 87, 'PT Geopersada Mulai Abadi', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Marselin Matitaputty* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA\n• Name: Coba GMA\n• Company: PT Geopersada Mulai Abadi\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-25 04:00:24'),
(142, 'new_employee', 88, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: GMA\n• Name: Coba Notif WA\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-25 06:31:32'),
(143, 'admin_accepted_employee', 88, 'PT Geopersada Mulai Abadi', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Pingkan Mandang* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: GMA\n• Name: Coba Notif WA\n• Company: PT Geopersada Mulai Abadi\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-25 06:32:40'),
(144, 'ktt_both_approved_admin', 66, 'PT Geopersada Mulai Abadi', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 015/TT/MSM/02/2026\n• Employee: Coba Notif WA (GMA)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-25 06:37:54'),
(145, 'ktt_approved_final_user_dept', 66, 'PT Geopersada Mulai Abadi', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 015/TT/MSM/02/2026\n• Employee: Coba Notif WA (GMA)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-02-25 06:38:05'),
(146, 'new_employee', 89, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 1\n• Name: tes\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-25 06:43:52'),
(147, 'admin_accepted_employee', 89, 'PT Geopersada Mulai Abadi', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Jenry Tolu* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 1\n• Name: tes\n• Company: PT Geopersada Mulai Abadi\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-25 06:44:50'),
(148, 'ktt_both_approved_admin', 67, 'PT Geopersada Mulai Abadi', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 002/TT/TTN/02/2026\n• Employee: tes (1)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-25 06:45:51'),
(149, 'ktt_approved_final_user_dept', 67, 'PT Geopersada Mulai Abadi', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 002/TT/TTN/02/2026\n• Employee: tes (1)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-02-25 06:45:56'),
(150, 'ktt_both_approved_admin', 57, 'PT Meares Soputan Mining', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 006/PT/MSM/02/2026\n• Employee: Tes (ARCHI-21-8760)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-26 00:28:25'),
(151, 'new_employee', 91, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 2\n• Name: TESSS\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-02-26 03:34:46'),
(152, 'admin_accepted_employee', 91, 'PT Geopersada Mulai Abadi', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Omega Banea* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 2\n• Name: TESSS\n• Company: PT Geopersada Mulai Abadi\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-27 00:19:02'),
(153, 'admin_accepted_employee', 78, 'PT Aptekindo Mitra Solusitama', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Jenry Tolu* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 7401A\n• Name: Tes Aptekindo\n• Company: PT Aptekindo Mitra Solusitama\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-27 00:20:25'),
(154, 'admin_accepted_employee', 75, 'PT DNX Indonesia', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Pingkan Mandang* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: PTDNX-011619\n• Name: Tes\n• Company: PT DNX Indonesia\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-27 00:21:02'),
(155, 'admin_accepted_employee', 74, 'PT Maxidrill Indonesia', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Marselin Matitaputty* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 10\n• Name: Willy\n• Company: PT Maxidrill Indonesia\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-02-27 00:21:41'),
(156, 'ktt_both_approved_admin', 65, 'PT Geopersada Mulai Abadi', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 008/PT/MSM/02/2026\n• Employee: Coba GMA (PTGMA)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-27 00:25:05'),
(157, 'ktt_approved_final_user_dept', 65, 'PT Geopersada Mulai Abadi', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 008/PT/MSM/02/2026\n• Employee: Coba GMA (PTGMA)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-02-27 00:25:22'),
(158, 'ktt_both_approved_admin', 59, 'PT Meares Soputan Mining', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 007/PT/MSM/02/2026\n• Employee: Coba (ARCHI-0931)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-02-27 01:33:43'),
(159, 'ktt_both_approved_admin', 61, 'PT Meares Soputan Mining (MSM)', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 011/TT/MSM/02/2026\n• Employee: T (135542002)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining (MSM)\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-02 01:12:27'),
(160, 'ktt_both_approved_admin', 63, 'PT Geopersada Mulai Abadi', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 013/TT/MSM/02/2026\n• Employee: Tes GMA (12345)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-02 01:20:31'),
(161, 'ktt_approved_final_user_dept', 63, 'PT Geopersada Mulai Abadi', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 013/TT/MSM/02/2026\n• Employee: Tes GMA (12345)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-03-02 01:20:38'),
(162, 'appointment_rejected', 72, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 010/PO/MSM/02/2026\n• Employee: Willy (10)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Agung Praptono\n\n💬 *Rejection Reason:*\nubah no sertifikat\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-03-02 01:23:07'),
(163, 'ktt_both_approved_admin', 72, 'PT Maxidrill Indonesia', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 010/PO/MSM/02/2026\n• Employee: Willy (10)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-02 02:27:17'),
(164, 'ktt_approved_final_user_dept', 72, 'PT Maxidrill Indonesia', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 010/PO/MSM/02/2026\n• Employee: Willy (10)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-03-02 02:27:21'),
(165, 'ktt_both_approved_admin', 68, 'PT Geopersada Mulai Abadi', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 016/TT/MSM/02/2026\n• Employee: TESSS (2)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-02 02:27:53'),
(166, 'ktt_approved_final_user_dept', 68, 'PT Geopersada Mulai Abadi', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 016/TT/MSM/02/2026\n• Employee: TESSS (2)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-03-02 02:27:59'),
(167, 'ktt_both_approved_admin', 69, 'PT Meares Soputan Mining (MSM)', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 017/TT/MSM/02/2026\n• Employee: TES (TES123)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining (MSM)\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-02 02:28:26'),
(168, 'ktt_both_approved_admin', 70, 'PT Aptekindo Mitra Solusitama', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 018/TT/MSM/02/2026\n• Employee: Tes Aptekindo (7401A)\n• Position: HSE Officer\n• Company: PT Aptekindo Mitra Solusitama\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-02 02:28:54'),
(169, 'ktt_approved_final_user_dept', 70, 'PT Aptekindo Mitra Solusitama', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 018/TT/MSM/02/2026\n• Employee: Tes Aptekindo (7401A)\n• Position: HSE Officer\n• Company: PT Aptekindo Mitra Solusitama\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-03-02 02:29:01'),
(170, 'ktt_both_approved_admin', 71, 'PT DNX Indonesia', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 003/TT/TTN/02/2026\n• Employee: Tes (PTDNX-011619)\n• Position: HSE Officer\n• Company: PT DNX Indonesia\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-02 02:30:20'),
(171, 'ktt_approved_final_user_dept', 71, 'PT DNX Indonesia', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 003/TT/TTN/02/2026\n• Employee: Tes (PTDNX-011619)\n• Position: HSE Officer\n• Company: PT DNX Indonesia\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-03-02 02:30:29'),
(172, 'new_employee', 98, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: PTGMA-6522\n• Name: R\n• Position: Rigger\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-03-04 05:41:12'),
(173, 'new_employee', 99, 'PT Meares Soputan Mining', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Meares Soputan Mining* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-17-2054\n• Name: Vidya\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-03-04 05:42:48'),
(174, 'admin_accepted_employee', 93, 'PT DNX Indonesia', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Pingkan Mandang* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: PTDNX-0111\n• Name: D\n• Company: PT DNX Indonesia\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-03-04 05:45:28'),
(175, 'ktt_both_approved_admin', 73, 'PT DNX Indonesia', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 001/TT/MSM/03/2026\n• Employee: D (PTDNX-0111)\n• Position: HSE Officer\n• Company: PT DNX Indonesia\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-04 06:22:10'),
(176, 'ktt_approved_final_user_dept', 73, 'PT DNX Indonesia', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 001/TT/MSM/03/2026\n• Employee: D (PTDNX-0111)\n• Position: HSE Officer\n• Company: PT DNX Indonesia\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-03-04 06:22:16'),
(177, 'appointment_rejected', 74, 'PT Meares Soputan Mining (MSM)', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 002/TT/MSM/03/2026\n• Employee: D (ARCH-00-1012)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining (MSM)\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nubahkan no serti\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-03-04 06:23:38'),
(178, 'new_employee', 71, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 1355\n• Name: Tes\n• Position: Superintendent\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-03-04 06:27:24'),
(179, 'appointment_rejected', 75, 'PT Meares Soputan Mining (MSM)', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 003/TT/MSM/03/2026\n• Employee: k (archi-9081)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining (MSM)\n• Rejected by: Agung Praptono\n\n💬 *Rejection Reason:*\nubah no serti\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-03-05 04:51:55'),
(180, 'ktt_both_approved_admin', 75, 'PT Meares Soputan Mining (MSM)', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 003/TT/MSM/03/2026\n• Employee: k (archi-9081)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining (MSM)\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-05 05:35:38'),
(181, 'admin_accepted_employee', 71, 'PT Maxidrill Indonesia', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Marselin Matitaputty* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 1355\n• Name: Tes\n• Company: PT Maxidrill Indonesia\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-03-05 05:39:02'),
(182, 'appointment_rejected', 76, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 001/PO/MSM/03/2026\n• Employee: Tes (1355)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Agung Praptono\n\n💬 *Rejection Reason:*\nubahkan no sertifikat\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-03-05 05:50:27'),
(183, 'ktt_both_approved_admin', 76, 'PT Maxidrill Indonesia', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 001/PO/MSM/03/2026\n• Employee: Tes (1355)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-05 05:51:40'),
(184, 'ktt_approved_final_user_dept', 76, 'PT Maxidrill Indonesia', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 001/PO/MSM/03/2026\n• Employee: Tes (1355)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-03-05 05:51:50'),
(185, 'new_employee', 102, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 14\n• Name: Dinda\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-03-12 06:44:07'),
(186, 'admin_accepted_employee', 102, 'PT Maxidrill Indonesia', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Omega Banea* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 14\n• Name: Dinda\n• Company: PT Maxidrill Indonesia\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-03-12 06:45:39'),
(187, 'new_employee', 104, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 244\n• Name: Dodi\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-03-16 01:10:03'),
(188, 'ktt_both_approved_admin', 77, 'PT Maxidrill Indonesia', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 001/PT/MSM/03/2026\n• Employee: Dinda (14)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-16 02:19:44'),
(189, 'ktt_approved_final_user_dept', 77, 'PT Maxidrill Indonesia', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 001/PT/MSM/03/2026\n• Employee: Dinda (14)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-03-16 02:19:57'),
(190, 'ktt_both_approved_admin', 78, 'PT Meares Soputan Mining', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 002/PT/MSM/03/2026\n• Employee: G (ARCH-13-482)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-16 02:21:36'),
(191, 'ktt_both_approved_admin', 79, 'PT Tambang Tondano Nusajaya (TTN)', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 001/TT/TTN/03/2026\n• Employee: Queen (31)\n• Position: HSE Officer\n• Company: PT Tambang Tondano Nusajaya (TTN)\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-16 02:22:35'),
(192, 'new_employee', 105, 'PT Geopersada Mulai Abadi', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Geopersada Mulai Abadi* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 9009\n• Name: Tes\n• Position: Maintenance Specialist\n• Company: PT Geopersada Mulai Abadi\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-03-17 00:45:55'),
(193, 'admin_accepted_employee', 105, 'PT Geopersada Mulai Abadi', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Jenry Tolu* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 9009\n• Name: Tes\n• Company: PT Geopersada Mulai Abadi\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-03-17 00:46:26'),
(194, 'appointment_rejected', 80, 'PT Geopersada Mulai Abadi', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 002/PO/MSM/03/2026\n• Employee: Tes (9009)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• Rejected by: Tejo Prihantoro\n\n💬 *Rejection Reason:*\nubah tgl sertifikat\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-03-17 00:50:37'),
(195, 'appointment_rejected', 81, 'PT Meares Soputan Mining', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 003/PT/MSM/03/2026\n• Employee: Vidya (ARCHI-17-2054)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• Rejected by: Agung Praptono\n\n💬 *Rejection Reason:*\nubah tgl sertifikat\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-03-17 01:09:33'),
(196, 'new_employee', 99, 'HSE&Formalities', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *HSE&Formalities* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: ARCHI-17-2054\n• Name: Vidya\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-03-17 01:12:39'),
(197, 'ktt_both_approved_admin', 81, 'PT Meares Soputan Mining', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 003/PT/MSM/03/2026\n• Employee: Vidya (ARCHI-17-2054)\n• Position: HSE Officer\n• Company: PT Meares Soputan Mining\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-17 01:57:43'),
(198, 'ktt_both_approved_admin', 80, 'PT Geopersada Mulai Abadi', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 002/PO/MSM/03/2026\n• Employee: Tes (9009)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-17 01:58:24'),
(199, 'ktt_approved_final_user_dept', 80, 'PT Geopersada Mulai Abadi', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 002/PO/MSM/03/2026\n• Employee: Tes (9009)\n• Position: HSE Officer\n• Company: PT Geopersada Mulai Abadi\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-03-17 01:58:28'),
(200, 'new_employee', 106, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 3638\n• Name: Coba\n• Position: Maintenance Specialist\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-03-17 06:37:44'),
(201, 'new_employee', 107, 'PT Intertek Utama Services', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Intertek Utama Services* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 3212115\n• Name: Yoman\n• Position: Juru Las\n• Company: PT Intertek Utama Services\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-03-17 07:35:44'),
(202, 'admin_accepted_employee', 107, 'PT Intertek Utama Services', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Marselin Matitaputty* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 3212115\n• Name: Yoman\n• Company: PT Intertek Utama Services\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-03-17 07:41:21'),
(203, 'appointment_rejected', 82, 'PT Intertek Utama Services', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 004/PT/MSM/03/2026\n• Employee: Yoman (3212115)\n• Position: HSE Officer\n• Company: PT Intertek Utama Services\n• Rejected by: Agung Praptono\n\n💬 *Rejection Reason:*\ncek sertifikat kompetensi\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-03-17 07:49:35'),
(204, 'new_employee', 108, 'PT Maxidrill Indonesia', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Maxidrill Indonesia* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: IDBADGE1\n• Name: Tes\n• Position: Superintendent\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-03-26 07:11:06'),
(205, 'admin_accepted_employee', 108, 'PT Maxidrill Indonesia', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Omega Banea* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: IDBADGE1\n• Name: Tes\n• Company: PT Maxidrill Indonesia\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-03-27 01:59:02'),
(206, 'ktt_approval_request', 83, 'PT Maxidrill Indonesia', '📋 *NEW APPOINTMENT FOR APPROVAL*\n\nAdmin has submitted an appointment letter that requires your approval:\n\n📋 *Letter Details:*\n• Letter No.: 004/TT/MSM/03/2026\n• Employee: Tes (IDBADGE1)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to review and approve/reject this appointment.\n📍 http://localhost/windy/approval.php', '2026-03-27 01:59:55'),
(207, 'ktt_both_approved_admin', 83, 'PT Maxidrill Indonesia', '✅ *KTT FINAL APPROVAL NOTIFICATION*\n\nBoth KTTs have approved the following assign letter:\n\n📋 *Letter Details:*\n• Letter No.: 004/TT/MSM/03/2026\n• Employee: Tes (IDBADGE1)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• KTT MSM: Tejo Prihantoro\n• KTT TTN: Agung Praptono\n\nℹ️ The assign letter is now fully approved by both KTTs.\n📍 http://localhost/windy/appointments.php', '2026-03-27 02:00:38'),
(208, 'ktt_approved_final_user_dept', 83, 'PT Maxidrill Indonesia', '🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*\n\nThe assign letter for the following employee has been *successfully approved* by KTT:\n\n📋 *Letter Details:*\n• Letter No.: 004/TT/MSM/03/2026\n• Employee: Tes (IDBADGE1)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n\n✅ The assign letter is now active and fully approved.\n📍 http://localhost/windy/appointments.php', '2026-03-27 02:00:42'),
(209, 'new_employee', 107, 'PT Intertek Utama Services', '🔔 *NEW EMPLOYEE NOTIFICATION*\n\nCompany *PT Intertek Utama Services* has added a new employee that requires verification:\n\n📋 *Employee Details:*\n• ID BADGE: 3212115\n• Name: Yoman\n• Position: Juru Las\n• Company: PT Intertek Utama Services\n\n⚠️ Please login to the system to perform verification.\n📍 http://localhost/windy/employees.php', '2026-05-22 01:42:37'),
(210, 'ktt_approval_request', 82, 'PT Intertek Utama Services', '📋 *NEW APPOINTMENT FOR APPROVAL*\n\nAdmin has submitted an appointment letter that requires your approval:\n\n📋 *Letter Details:*\n• Letter No.: 004/PT/MSM/03/2026\n• Employee: Yoman (3212115)\n• Position: HSE Officer\n• Company: PT Intertek Utama Services\n\n⚠️ Please login to review and approve/reject this appointment.\n📍 http://localhost/windy/approval.php', '2026-06-08 07:02:59'),
(211, 'admin_accepted_employee', 107, 'PT Intertek Utama Services', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Marselin Matitaputty* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 3212115\n• Name: Yoman\n• Company: PT Intertek Utama Services\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-06-08 07:08:48'),
(212, 'ktt_approval_request', 82, 'PT Intertek Utama Services', '📋 *NEW APPOINTMENT FOR APPROVAL*\n\nAdmin has submitted an appointment letter that requires your approval:\n\n📋 *Letter Details:*\n• Letter No.: 004/PT/MSM/03/2026\n• Employee: Yoman (3212115)\n• Position: HSE Officer\n• Company: PT Intertek Utama Services\n\n⚠️ Please login to review and approve/reject this appointment.\n📍 http://localhost/windy/approval.php', '2026-06-08 07:08:59'),
(213, 'admin_accepted_employee', 106, 'PT Maxidrill Indonesia', '✅ *EMPLOYEE VERIFICATION SUCCESSFUL*\n\nThe following employee data has been *successfully verified by Admin - Marselin Matitaputty* and is now awaiting KTT approval:\n\n📋 *Employee Details:*\n• ID BADGE: 3638\n• Name: Coba\n• Company: PT Maxidrill Indonesia\n\nThe assign letter has been created and is currently pending KTT approval.\n📍 http://localhost/windy/employees.php', '2026-06-08 07:11:40'),
(214, 'ktt_approval_request', 84, 'PT Maxidrill Indonesia', '📋 *NEW APPOINTMENT FOR APPROVAL*\n\nAdmin has submitted an appointment letter that requires your approval:\n\n📋 *Letter Details:*\n• Letter No.: 001/PT/MSM/06/2026\n• Employee: Coba (3638)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to review and approve/reject this appointment.\n📍 http://localhost/windy/approval.php', '2026-06-08 07:11:50'),
(215, 'appointment_rejected', 84, 'PT Maxidrill Indonesia', '⚠️ *LETTER REJECTION NOTIFICATION*\n\nAn appointment letter has been rejected by KTT and requires admin review:\n\n📋 *Letter Details:*\n• Letter No.: 001/PT/MSM/06/2026\n• Employee: Coba (3638)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n• Rejected by: Agung Praptono\n\n💬 *Rejection Reason:*\ntidak sesuai\n\n⚠️ Please login to review this rejection.\n📍 http://localhost/windy/admin_review_rejection.php', '2026-06-08 07:13:15'),
(216, 'ktt_approval_request', 84, 'PT Maxidrill Indonesia', '📋 *NEW APPOINTMENT FOR APPROVAL*\n\nAdmin has submitted an appointment letter that requires your approval:\n\n📋 *Letter Details:*\n• Letter No.: 001/PT/MSM/06/2026\n• Employee: Coba (3638)\n• Position: HSE Officer\n• Company: PT Maxidrill Indonesia\n\n⚠️ Please login to review and approve/reject this appointment.\n📍 http://localhost/windy/approval.php', '2026-06-08 07:14:46');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int NOT NULL,
  `position_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `position_type` enum('pengawas_operasional','pengawas_teknis','tenaga_teknis') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `competency_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `position_name`, `position_type`, `competency_id`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Pengawas Operasional Produksi', 'pengawas_operasional', NULL, 'Mengawasi operasional produksi tambang', 0, '2026-01-07 00:05:37', '2026-01-11 23:53:17'),
(2, 'Pengawas Operasional Eksplorasi', 'pengawas_operasional', NULL, 'Mengawasi operasional eksplorasi', 0, '2026-01-07 00:05:37', '2026-01-11 23:53:12'),
(3, 'HSE Officer', 'pengawas_teknis', 5, 'Mengawasi teknis mekanik', 1, '2026-01-07 00:05:37', '2026-01-21 01:39:05'),
(4, 'HSE Officer', 'pengawas_teknis', 1, 'Mengawasi teknis elektrik', 1, '2026-01-07 00:05:37', '2026-01-21 01:35:31'),
(5, 'Maintenance Spacialist', 'tenaga_teknis', 2, 'Tenaga teknis pengelasan', 1, '2026-01-07 00:05:37', '2026-01-21 01:37:38'),
(6, 'Rigger', 'tenaga_teknis', 4, 'Tenaga teknis rigging', 1, '2026-01-07 00:05:37', '2026-01-21 01:38:52'),
(7, 'Plant Operator', 'tenaga_teknis', 3, 'Operator alat berat tambang', 1, '2026-01-07 00:05:37', '2026-01-21 01:38:14'),
(8, 'Pengawas Operasional', 'pengawas_operasional', NULL, 'Mengawasi Operasional tambang', 1, '2026-01-07 02:46:47', '2026-01-08 03:18:54');

-- --------------------------------------------------------

--
-- Table structure for table `position_requirements`
--

CREATE TABLE `position_requirements` (
  `id` int NOT NULL,
  `position_id` int NOT NULL,
  `certification_id` int NOT NULL,
  `is_mandatory` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervision_areas`
--

CREATE TABLE `supervision_areas` (
  `id` int NOT NULL,
  `area_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `area_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervision_areas`
--

INSERT INTO `supervision_areas` (`id`, `area_name`, `area_code`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'Fixed Plant Maintenance', NULL, NULL, 1, '2026-02-08 06:14:15', '2026-02-12 02:55:48'),
(4, 'Fixed Plant Processing', NULL, NULL, 1, '2026-02-08 06:14:36', '2026-02-12 02:56:03'),
(5, 'Fixed Plant Metallurgy', NULL, NULL, 1, '2026-02-08 06:15:12', '2026-02-12 02:55:56'),
(6, 'Mining MTS', NULL, NULL, 1, '2026-02-08 06:15:23', '2026-02-08 06:15:23'),
(7, 'Mining Dewatering', NULL, NULL, 1, '2026-02-08 06:15:37', '2026-02-08 06:15:37'),
(8, 'Mining Geologis', NULL, NULL, 1, '2026-02-08 06:15:45', '2026-02-08 06:15:45'),
(9, 'Eksplorasi', NULL, NULL, 1, '2026-02-08 06:15:52', '2026-02-08 06:15:52');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `company_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('admin','ktt','user','department_user') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `company_name`, `is_active`, `email`, `phone`, `role`, `created_at`, `updated_at`, `department`) VALUES
(3, 'reviewer_omega', '$2y$10$pEMNjRzOQh2cHv.zik5XvecjgycL4Plj.StBec.nsTG8o/cz88gLi', 'Omega Banea', NULL, 1, 'windypatriciaa10@gmail.com', '6285173023567', 'admin', '2026-01-07 00:06:52', '2026-02-05 06:01:42', NULL),
(4, 'reviewer_marselin', '$2y$10$9sgHYztPKaMkQOeHjU0EouLqVfdtdb0KBOMwdS9Usd6eIVU6luVum', 'Marselin Matitaputty', NULL, 1, 'admin2@mining.com', NULL, 'admin', '2026-01-07 00:06:52', '2026-02-05 06:02:03', NULL),
(5, 'reviewer_pingkan', '$2y$10$9sgHYztPKaMkQOeHjU0EouLqVfdtdb0KBOMwdS9Usd6eIVU6luVum', 'Pingkan Mandang', NULL, 1, 'admin3@mining.com', NULL, 'admin', '2026-01-07 00:06:52', '2026-02-05 06:02:23', NULL),
(6, 'reviewer_jenry', '$2y$10$9sgHYztPKaMkQOeHjU0EouLqVfdtdb0KBOMwdS9Usd6eIVU6luVum', 'Jenry Tolu', NULL, 1, 'admin4@mining.com', NULL, 'admin', '2026-01-07 00:06:52', '2026-02-05 06:02:40', NULL),
(7, 'ktt_msm', '$2y$10$9sgHYztPKaMkQOeHjU0EouLqVfdtdb0KBOMwdS9Usd6eIVU6luVum', 'Tejo Prihantoro', 'PT MSM', 1, 'ktt1@mining.com', NULL, 'ktt', '2026-01-07 00:06:52', '2026-02-01 07:47:19', NULL),
(8, 'ktt_ttn', '$2y$10$9sgHYztPKaMkQOeHjU0EouLqVfdtdb0KBOMwdS9Usd6eIVU6luVum', 'Agung Praptono', 'PT TTN', 1, 'ktt2@mining.com', NULL, 'ktt', '2026-01-07 00:06:52', '2026-02-01 07:46:32', NULL),
(9, 'admin_g4s', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'G4S Security Services', 'G4S Security Services', 1, 'contact@g4s.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 02:28:23', NULL),
(10, 'admin_partsentra', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Part Sentra Indomandiri', 'PT Part Sentra Indomandiri', 1, 'contact@partsentra.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 02:28:35', NULL),
(11, 'admin_anekakimia', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Aneka Kimia Raya Corporindo', 'PT Aneka Kimia Raya Corporindo', 1, 'contact@anekakimia.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 02:28:51', NULL),
(12, 'admin_saribuana', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Saribuana Manado', 'PT Saribuana Manado', 1, 'contact@saribuana.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 02:29:02', NULL),
(13, 'admin_maxidrill', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Maxidrill Indonesia', 'PT Maxidrill Indonesia', 1, 'contact@maxidrill.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 02:29:13', NULL),
(14, 'admin_tatawisata', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Tata Wisata', 'PT Tata Wisata', 1, 'contact@tatawisata.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 02:29:48', NULL),
(15, 'admin_arlie', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Arlie Labora Utama', 'PT Arlie Labora Utama', 1, 'contact@arlielabora.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-21 03:02:50', NULL),
(16, 'admin_toumaesa', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Tou Maesa Sejahtera', 'PT Tou Maesa Sejahtera', 1, 'contact@toumaesa.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 02:30:44', NULL),
(17, 'admin_dnx', '$2y$10$hqjEOIk9Bd3c/cqZIlrhlui7ckvjklir29BWEt1MTCfFnehfNH4x.', 'PT DNX Indonesia', 'PT DNX Indonesia', 1, 'contact@dnx.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 02:30:58', NULL),
(18, 'admin_mfi', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Mandara Fasilitas Indonesia', 'PT Mandara Fasilitas Indonesia', 1, 'contact@mandarafasilitas.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 02:31:16', NULL),
(19, 'admin_aptekindo', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Aptekindo Mitra Solusitama', 'PT Aptekindo Mitra Solusitama', 1, 'contact@aptekindo.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 03:52:36', NULL),
(20, 'admin_gma', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Geopersada Mulai Abadi', 'PT Geopersada Mulai Abadi', 1, 'vidyapatriciaaa09@gmail.com', '6281245782798', 'user', '2026-01-07 00:06:52', '2026-02-25 02:45:25', NULL),
(21, 'admin_hidupbaru', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Hidup Baru Sukses Mandiri', 'PT Hidup Baru Sukses Mandiri', 1, 'contact@hidupbaru.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 03:53:06', NULL),
(22, 'admin_intertek', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Intertek Utama Services', 'PT Intertek Utama Services', 1, 'contact@intertek.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 03:53:22', NULL),
(23, 'admin_macmahon', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Macmahon Indonesia', 'PT Macmahon Indonesia', 1, 'contact@macmahon.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 03:53:43', NULL),
(24, 'admin_manadokarya', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Manado Karya Angrah', 'PT Manado Karya Angrah', 1, 'contact@manadokarya.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 03:53:59', NULL),
(25, 'admin_sma', '$2y$10$za9rDizAekmvtJydcb36S.oQG88BvPxjoqyaUX4zjc/fyJEaw9EEi', 'PT Samudera Mulai Abadi', 'PT Samudera Mulai Abadi', 1, 'contact@samudera.com', NULL, 'user', '2026-01-07 00:06:52', '2026-02-07 03:54:15', NULL),
(26, 'dept_hcbp', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'HCBP Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 03:54:39', 'HCBP'),
(27, 'dept_MiningOperation', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Mining Operation Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 03:55:27', 'Mining Operation'),
(28, 'dept_PrincipalMining', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Principal Mining Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 03:55:52', 'Principal Mining'),
(29, 'dept_MTS', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Mining Tech Service Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 03:56:36', 'Mining Tech Service'),
(30, 'dept_ProcessPlant', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Process Plant Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 03:58:29', 'Process Plant'),
(31, 'dept_maintenance', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Maintenance Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 03:58:54', 'Maintenance'),
(32, 'dept_mettalurgy', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Mettalurgy Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 04:00:46', 'Mettalurgy'),
(33, 'dept_project', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Project Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 04:01:12', 'Project'),
(34, 'dept_OHS', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'OHS Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 04:01:32', 'OHS'),
(35, 'dept_environmental', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Environmental Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 07:06:01', 'Environmental'),
(36, 'dept_OSF', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'HSE & Formalities Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 07:06:57', 'HSE&Formalities'),
(37, 'dept_exploration', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Exploration Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 07:07:14', 'Exploration'),
(38, 'dept_underground', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Underground Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 07:07:28', 'Underground'),
(39, 'dept_CSR', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'CSR Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 07:07:41', 'CSR'),
(40, 'dept_compliance', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Compliance Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 07:08:32', 'Compliance'),
(41, 'dept_commercial', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Commercial Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 07:08:46', 'Commercial'),
(42, 'dept_Finance&Accounting', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'Finance & Accounting Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 07:09:32', 'Finance&Accounting'),
(43, 'dept_IT', '$2y$10$MbD4ErxLj8pn0RxxzN/g3u/MHHY0yWDAcy06bpvQ2a/hgAibZvkkC', 'IT Department', NULL, 1, NULL, NULL, 'user', '2026-01-13 13:31:30', '2026-02-07 07:09:45', 'IT');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appointment_number` (`appointment_number`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_admin_approved` (`admin_approved_by`,`status`),
  ADD KEY `idx_ktt_msm_review` (`status`,`ktt_msm_status`,`requires_ktt_msm_review`),
  ADD KEY `idx_ktt_ttn_review` (`status`,`ktt_ttn_status`,`requires_ktt_ttn_review`),
  ADD KEY `idx_admin_review` (`status`,`last_rejected_by_ktt`),
  ADD KEY `idx_resubmit_count` (`resubmit_count`),
  ADD KEY `idx_last_rejection_date` (`last_rejection_date`);

--
-- Indexes for table `certifications`
--
ALTER TABLE `certifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `competencies`
--
ALTER TABLE `competencies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `competency_sub_competencies`
--
ALTER TABLE `competency_sub_competencies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_competency_id` (`competency_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_sub_competency` (`sub_competency`);

--
-- Indexes for table `employee_certifications`
--
ALTER TABLE `employee_certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `certification_id` (`certification_id`);

--
-- Indexes for table `ktt_approvals`
--
ALTER TABLE `ktt_approvals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ktt_approval` (`appointment_id`,`ktt_user_id`),
  ADD KEY `ktt_user_id` (`ktt_user_id`);

--
-- Indexes for table `ktt_rejections`
--
ALTER TABLE `ktt_rejections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `ktt_user_id` (`ktt_user_id`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`notification_type`),
  ADD KEY `idx_reference` (`reference_id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `competency_id` (`competency_id`);

--
-- Indexes for table `position_requirements`
--
ALTER TABLE `position_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_position_cert` (`position_id`,`certification_id`),
  ADD KEY `certification_id` (`certification_id`);

--
-- Indexes for table `supervision_areas`
--
ALTER TABLE `supervision_areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `area_name` (`area_name`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `certifications`
--
ALTER TABLE `certifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `competencies`
--
ALTER TABLE `competencies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `competency_sub_competencies`
--
ALTER TABLE `competency_sub_competencies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `employee_certifications`
--
ALTER TABLE `employee_certifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `ktt_approvals`
--
ALTER TABLE `ktt_approvals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=228;

--
-- AUTO_INCREMENT for table `ktt_rejections`
--
ALTER TABLE `ktt_rejections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=217;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `position_requirements`
--
ALTER TABLE `position_requirements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supervision_areas`
--
ALTER TABLE `supervision_areas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `competency_sub_competencies`
--
ALTER TABLE `competency_sub_competencies`
  ADD CONSTRAINT `fk_competency_id` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `employee_certifications`
--
ALTER TABLE `employee_certifications`
  ADD CONSTRAINT `employee_certifications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_certifications_ibfk_2` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ktt_approvals`
--
ALTER TABLE `ktt_approvals`
  ADD CONSTRAINT `ktt_approvals_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ktt_approvals_ibfk_2` FOREIGN KEY (`ktt_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ktt_rejections`
--
ALTER TABLE `ktt_rejections`
  ADD CONSTRAINT `ktt_rejections_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ktt_rejections_ibfk_2` FOREIGN KEY (`ktt_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`);

--
-- Constraints for table `position_requirements`
--
ALTER TABLE `position_requirements`
  ADD CONSTRAINT `position_requirements_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `position_requirements_ibfk_2` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
