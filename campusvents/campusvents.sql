-- ============================================================
--  CampusVents — Complete Database Schema & Seed Data
--  MySQL 8.0+ / MariaDB 10.6+
--
--  Import cara:
--    mysql -u root -p < campusvents.sql
--    atau via phpMyAdmin: Import > pilih file ini
--
--  Akun Demo:
--    Admin    : admin@campusvents.id     / Admin@2026
--    Panitia  : panitia@campusvents.id   / Panitia@2026
--    Mahasiswa: mahasiswa@campusvents.id / Mahasiswa@2026
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';
SET NAMES utf8mb4;

-- ── DATABASE ────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS `campusvents_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `campusvents_db`;

-- ── DROP EXISTING TABLES (urutan aman) ─────────────────────
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `user_interests`;
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ============================================================
--  TABLE: users
-- ============================================================
CREATE TABLE `users` (
  `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150)     NOT NULL,
  `email`      VARCHAR(200)     NOT NULL,
  `password`   VARCHAR(255)     NOT NULL,
  `nim`        VARCHAR(30)      NULL DEFAULT NULL,
  `prodi`      VARCHAR(100)     NULL DEFAULT NULL,
  `role`       ENUM('mahasiswa','panitia','admin') NOT NULL DEFAULT 'mahasiswa',
  `avatar`     VARCHAR(255)     NULL DEFAULT NULL,
  `bio`        TEXT             NULL DEFAULT NULL,
  `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: categories
-- ============================================================
CREATE TABLE `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `icon`        VARCHAR(100) NOT NULL DEFAULT '🏷️',
  `color`       VARCHAR(20)  NOT NULL DEFAULT '#1A1A2E',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: events
-- ============================================================
CREATE TABLE `events` (
  `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`                 VARCHAR(255)  NOT NULL,
  `description`           TEXT          NOT NULL,
  `category_id`           INT UNSIGNED  NOT NULL,
  `organizer_id`          INT UNSIGNED  NOT NULL,
  `date_start`            DATETIME      NOT NULL,
  `date_end`              DATETIME      NOT NULL,
  `location`              VARCHAR(255)  NOT NULL,
  `quota`                 INT UNSIGNED  NOT NULL DEFAULT 100,
  `registration_deadline` DATETIME      NOT NULL,
  `poster`                VARCHAR(255)  NULL DEFAULT NULL,
  `status`                ENUM('draft','pending','published','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `rejection_reason`      TEXT          NULL DEFAULT NULL,
  `view_count`            INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `events_category_id_index` (`category_id`),
  KEY `events_organizer_id_index` (`organizer_id`),
  KEY `events_status_index` (`status`),
  KEY `events_date_start_index` (`date_start`),
  CONSTRAINT `events_category_id_foreign`
    FOREIGN KEY (`category_id`)  REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `events_organizer_id_foreign`
    FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`)      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: registrations
-- ============================================================
CREATE TABLE `registrations` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED  NOT NULL,
  `event_id`          INT UNSIGNED  NOT NULL,
  `status`            ENUM('pending','confirmed','attended','cancelled') NOT NULL DEFAULT 'confirmed',
  `registration_code` VARCHAR(25)   NOT NULL,
  `registered_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `attended_at`       DATETIME      NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `registrations_code_unique` (`registration_code`),
  UNIQUE KEY `registrations_user_event_unique` (`user_id`, `event_id`),
  KEY `registrations_event_id_index` (`event_id`),
  KEY `registrations_status_index` (`status`),
  CONSTRAINT `registrations_user_id_foreign`
    FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `registrations_event_id_foreign`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: user_interests
-- ============================================================
CREATE TABLE `user_interests` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_interests_unique` (`user_id`, `category_id`),
  KEY `user_interests_category_id_index` (`category_id`),
  CONSTRAINT `user_interests_user_id_foreign`
    FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`)      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `user_interests_category_id_foreign`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `event_id`   INT UNSIGNED NULL DEFAULT NULL,
  `type`       ENUM('event_baru','pendaftaran','validasi','pengingat','sistem') NOT NULL DEFAULT 'sistem',
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT         NOT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_index` (`user_id`),
  KEY `notifications_event_id_index` (`event_id`),
  KEY `notifications_is_read_index` (`is_read`),
  CONSTRAINT `notifications_user_id_foreign`
    FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `notifications_event_id_foreign`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  SEED DATA
-- ============================================================

-- ── USERS ───────────────────────────────────────────────────
-- Passwords di-hash dengan PASSWORD_BCRYPT (cost=12)
-- Admin@2026 | Panitia@2026 | Mahasiswa@2026
INSERT INTO `users` (`id`, `name`, `email`, `password`, `nim`, `prodi`, `role`, `bio`, `is_active`, `created_at`) VALUES
(1, 'Admin CampusVents',   'admin@campusvents.id',     '$2y$12$mBra3RLEeU18OYtIqFywQ.9rn13JPZjrXe.AqpoWaXd7OsviB1Bp6', NULL, NULL, 'admin',     'Administrator platform CampusVents yang bertanggung jawab mengelola keseluruhan ekosistem.', 1, '2026-01-01 08:00:00'),
(2, 'Panitia CampusVents', 'panitia@campusvents.id',   '$2y$12$XFOTfL9ADVqHlkpy4O5MO.D.5zshVWP9b/6ZIbx7gfk3ZNcYXFu3C', NULL, NULL, 'panitia',   'Panitia utama penyelenggara berbagai event kampus berkualitas.', 1, '2026-01-05 09:00:00'),
(3, 'Budi Santoso',        'mahasiswa@campusvents.id', '$2y$12$AplMbc.EKVpb5S6NCRBE6.ttx6Liaef9tkKChoKjRlUrll15WbC66', '20210001', 'Teknik Informatika', 'mahasiswa', 'Mahasiswa aktif yang gemar mengikuti event teknologi dan kewirausahaan.', 1, '2026-01-10 10:00:00'),
(4, 'Siti Nurhaliza',      'siti@example.com',         '$2y$12$AplMbc.EKVpb5S6NCRBE6.ttx6Liaef9tkKChoKjRlUrll15WbC66', '20210042', 'Sistem Informasi',   'mahasiswa', 'Aktif di bidang seni dan budaya kampus.', 1, '2026-01-12 11:00:00'),
(5, 'Rizky Pratama',       'rizky@example.com',        '$2y$12$AplMbc.EKVpb5S6NCRBE6.ttx6Liaef9tkKChoKjRlUrll15WbC66', '20210088', 'Manajemen Bisnis',   'mahasiswa', 'Entrepreneur muda yang sedang membangun startup di bidang EdTech.', 1, '2026-01-15 14:00:00'),
(6, 'Dewi Kartika',        'dewi@example.com',         '$2y$12$AplMbc.EKVpb5S6NCRBE6.ttx6Liaef9tkKChoKjRlUrll15WbC66', '20210123', 'Desain Komunikasi Visual', 'mahasiswa', 'Seniman muda yang passionate di bidang desain dan seni visual.', 1, '2026-02-01 08:30:00'),
(7, 'Ahmad Fauzi',         'ahmad@example.com',        '$2y$12$AplMbc.EKVpb5S6NCRBE6.ttx6Liaef9tkKChoKjRlUrll15WbC66', '20210200', 'Ilmu Kesehatan Masyarakat', 'mahasiswa', 'Aktif dalam kegiatan kesehatan dan olahraga kampus.', 1, '2026-02-10 09:00:00'),
(8, 'Himpunan Teknik Info', 'hiti@campusvents.id',     '$2y$12$XFOTfL9ADVqHlkpy4O5MO.D.5zshVWP9b/6ZIbx7gfk3ZNcYXFu3C', NULL, NULL, 'panitia',   'Himpunan Mahasiswa Teknik Informatika, penyelenggara event teknologi terbesar di kampus.', 1, '2026-01-20 10:00:00');

-- ── CATEGORIES ──────────────────────────────────────────────
INSERT INTO `categories` (`id`, `name`, `description`, `icon`, `color`) VALUES
(1, 'Teknologi',             'Event seputar teknologi, coding, AI, dan inovasi digital',   '💻', '#0069D9'),
(2, 'Seni & Budaya',         'Pertunjukan seni, festival budaya, pameran, dan kreasi',     '🎨', '#B845CB'),
(3, 'Olahraga',              'Turnamen, lomba, dan kegiatan olahraga kampus',              '⚽', '#E86A00'),
(4, 'Kewirausahaan',         'Seminar bisnis, pitching startup, dan entrepreneurship',     '💼', '#0A7C59'),
(5, 'Penelitian & Akademik', 'Konferensi, seminar ilmiah, dan kompetisi akademik',        '🔬', '#1A1A2E'),
(6, 'Sosial & Lingkungan',   'Kegiatan sosial, bakti masyarakat, dan pelestarian alam',   '🌱', '#C94B00'),
(7, 'Kesehatan',             'Seminar kesehatan, donor darah, dan wellness kampus',        '❤️', '#B80D2C'),
(8, 'Musik',                 'Konser, festival musik, dan pertunjukan seni suara',         '🎵', '#5B45CB');

-- ── EVENTS ──────────────────────────────────────────────────
INSERT INTO `events` (`id`, `title`, `description`, `category_id`, `organizer_id`, `date_start`, `date_end`, `location`, `quota`, `registration_deadline`, `status`, `rejection_reason`, `view_count`, `created_at`) VALUES

(1,
 'Hackathon Nasional 2026 — Code For Future',
 'Hackathon selama 48 jam berturut-turut yang menantang mahasiswa untuk membangun solusi teknologi inovatif. Peserta akan dibagi dalam tim beranggotakan 3–5 orang dan berkompetisi untuk memenangkan total hadiah senilai 50 juta rupiah.\n\nTopik tahun ini: Sustainable Tech & Smart City. Tersedia mentoring dari engineer senior industri terkemuka.\n\nJadwal:\n• Hari 1 (08:00): Pembukaan, briefing, dan mulai coding\n• Hari 2 (08:00): Sesi mentoring & office hours\n• Hari 3 (10:00): Demo Day & penilaian juri\n• Hari 3 (15:00): Pengumuman pemenang\n\nPersyaratan:\n- Mahasiswa aktif D3/D4/S1 semua jurusan\n- Tim 3–5 orang (boleh lintas kampus)\n- Membawa laptop dan perlengkapan sendiri\n- Registrasi tim sebelum deadline',
 1, 2,
 '2026-05-24 08:00:00', '2026-05-26 17:00:00',
 'Gedung Teknik A, Lantai 3 — Lab Komputer Terpadu',
 150, '2026-05-20 23:59:59',
 'published', NULL, 247, '2026-04-01 09:00:00'),

(2,
 'Business Pitch Competition — StartUp Campus 2026',
 'Ajang bergengsi bagi mahasiswa wirausaha muda untuk mempresentasikan ide bisnis inovatif di hadapan investor dan mentor berpengalaman. Tahun ini membuka kesempatan bagi 20 tim terbaik mendapatkan pendanaan awal hingga 100 juta rupiah dari venture capital partner kami.\n\nWorkshop persiapan pitching tersedia GRATIS untuk semua peserta terdaftar, dibimbing langsung oleh founding team dari unicorn Indonesia.\n\nKriteria penilaian:\n1. Inovasi & keunikan solusi (25%)\n2. Market opportunity & scalability (25%)\n3. Business model & monetisasi (20%)\n4. Kemampuan presentasi tim (20%)\n5. Traction & prototype (10%)\n\nHadiah:\n🥇 Juara 1: Rp 50.000.000 + investasi dari VC partner\n🥈 Juara 2: Rp 25.000.000\n🥉 Juara 3: Rp 15.000.000\nFavorit: Mentoring eksklusif 6 bulan',
 4, 2,
 '2026-05-31 09:00:00', '2026-05-31 18:00:00',
 'Auditorium Utama Kampus — Gedung Rektorat Lt. 2',
 200, '2026-05-28 23:59:59',
 'published', NULL, 183, '2026-04-05 10:00:00'),

(3,
 'Pameran Seni Kontemporer Mahasiswa 2026',
 'Pameran seni terbuka yang menampilkan karya mahasiswa dari berbagai program studi selama 5 hari berturut-turut. Meliputi lukisan, patung, instalasi seni, dan karya digital interaktif.\n\nTerbuka untuk umum. Tersedia workshop singkat oleh seniman-seniman tamu dari Jakarta, Bandung, dan Yogyakarta.\n\nWorkshop yang tersedia:\n• Watercolor & Illustration (Sabtu, 13:00–16:00)\n• Digital Art with Procreate (Minggu, 10:00–13:00)\n• Photography & Editing (Senin, 14:00–17:00)\n\nTiket masuk pameran: GRATIS\nWorkshop: Rp 75.000/sesi (termasuk material)',
 2, 8,
 '2026-06-09 10:00:00', '2026-06-14 21:00:00',
 'Galeri Seni Kampus — Gedung Seni & Desain Lt. 1',
 300, '2026-06-04 23:59:59',
 'pending', NULL, 94, '2026-04-10 11:00:00'),

(4,
 'Konser Akhir Semester — Campus Music Fest',
 'Festival musik tahunan yang menampilkan band-band terbaik dari kampus dan tamu istimewa dari industri musik nasional. Line-up mencakup 12 penampil dengan genre beragam: indie, jazz, pop, dan EDM.\n\nEvent ini membutuhkan perizinan tempat dari pihak sarana prasarana.',
 8, 8,
 '2026-05-17 18:00:00', '2026-05-17 23:00:00',
 'Lapangan Basket Utama',
 500, '2026-05-15 23:59:59',
 'rejected',
 'Belum ada izin penggunaan lapangan dari pihak Sarana Prasarana. Mohon lengkapi dokumen perizinan (Form SP-02) dan cap basah dari Wakil Rektor II terlebih dahulu, kemudian ajukan ulang.',
 31, '2026-04-12 14:00:00'),

(5,
 'Turnamen Futsal Antar Fakultas 2026',
 'Turnamen futsal bergengsi yang mempertemukan tim-tim terbaik dari setiap fakultas. Format turnamen: babak grup + sistem knock-out.\n\nJuara mendapatkan trofi bergilir dan beasiswa olahraga senilai Rp 10 juta. Runner-up mendapatkan Rp 5 juta.\n\nKetentuan:\n- 1 tim per fakultas, maksimal 10 pemain (7+3 cadangan)\n- Seragam wajib bermerk nama fakultas\n- Sepatu futsal wajib (sandal/barefoot tidak diizinkan)\n- Daftar sebagai tim (perwakilan ketua tim)',
 3, 2,
 '2026-06-24 07:00:00', '2026-06-26 20:00:00',
 'Lapangan Futsal Indoor Kampus — GOR Mahasiswa',
 120, '2026-06-19 23:59:59',
 'draft', NULL, 0, '2026-04-20 08:00:00'),

(6,
 'Seminar Nasional AI & Machine Learning 2026',
 'Seminar nasional yang menghadirkan para ahli AI dan machine learning dari industri dan akademia. Topik utama: Generative AI, Large Language Models, dan implementasi AI di sektor publik Indonesia.\n\nPembicara:\n1. Dr. Ir. Andi Wahyudi — Kepala Badan AI Nasional\n2. Prof. Suhardiman, Ph.D. — Guru Besar ITS, spesialis Deep Learning\n3. Nadya Kusuma — CTO startup AI terbesar di Indonesia\n4. Tim Research Google DeepMind Asia\n\nSesi:\n• Morning: Keynote & Panel Discussion\n• Afternoon: Workshop Hands-on (Python + TensorFlow)\n• Sesi networking & career booth dari perusahaan teknologi',
 5, 8,
 '2026-06-15 08:00:00', '2026-06-15 17:00:00',
 'Auditorium Serbaguna — Gedung Pasca Sarjana',
 400, '2026-06-10 23:59:59',
 'published', NULL, 312, '2026-04-25 09:00:00'),

(7,
 'Workshop UI/UX Design — From Zero to Portfolio',
 'Workshop intensif 2 hari bagi mahasiswa yang ingin memulai karir di bidang UI/UX Design. Diajarkan langsung oleh senior designer dari perusahaan fintech dan e-commerce terkemuka.\n\nMateri:\n✦ Day 1: Design Thinking, User Research, Wireframing\n✦ Day 2: Prototyping di Figma, Usability Testing, Portfolio Review\n\nPeserta akan mendapatkan:\n• Sertifikat keikutsertaan resmi\n• Template Figma eksklusif (valued Rp 500.000)\n• Akses rekaman workshop seumur hidup\n• Review portfolio 1-on-1 dengan mentor\n\nPrasyarat: Tidak ada (semua level welcome)',
 1, 2,
 '2026-06-20 09:00:00', '2026-06-21 17:00:00',
 'Lab Komputer B — Gedung Teknik, Lantai 2',
 60, '2026-06-17 23:59:59',
 'published', NULL, 158, '2026-05-01 10:00:00'),

(8,
 'Donor Darah & Health Check Up Gratis',
 'Kegiatan donor darah massal bekerja sama dengan PMI Kota dan pemeriksaan kesehatan gratis bagi seluruh civitas akademika.\n\nLayanan yang tersedia:\n• Donor darah (syarat: sehat, berat >45kg, usia 17–65 tahun)\n• Cek tekanan darah & gula darah\n• Konsultasi gizi gratis\n• Tes mata gratis\n• Pemeriksaan HIV/AIDS rahasia\n\nSetiap pendonor mendapatkan:\n- Snack & minuman bergizi\n- Sertifikat donor\n- Kaos donor eksklusif (kuota terbatas)\n- Merchandise dari sponsor',
 7, 8,
 '2026-06-05 07:30:00', '2026-06-05 14:00:00',
 'Aula Serbaguna — Gedung Student Center',
 500, '2026-06-04 23:59:59',
 'published', NULL, 89, '2026-05-05 08:00:00');

-- ── REGISTRATIONS ────────────────────────────────────────────
INSERT INTO `registrations` (`id`, `user_id`, `event_id`, `status`, `registration_code`, `registered_at`, `attended_at`) VALUES
(1,  3, 1, 'confirmed', 'CV-20260510-A1B2C', '2026-05-10 10:30:00', NULL),
(2,  3, 2, 'confirmed', 'CV-20260510-D3E4F', '2026-05-10 10:32:00', NULL),
(3,  3, 6, 'confirmed', 'CV-20260510-G5H6I', '2026-05-10 10:35:00', NULL),
(4,  4, 1, 'confirmed', 'CV-20260510-J7K8L', '2026-05-10 11:00:00', NULL),
(5,  4, 3, 'confirmed', 'CV-20260510-M9N0O', '2026-05-10 11:05:00', NULL),
(6,  5, 2, 'confirmed', 'CV-20260510-P1Q2R', '2026-05-10 12:00:00', NULL),
(7,  5, 7, 'confirmed', 'CV-20260510-S3T4U', '2026-05-10 12:10:00', NULL),
(8,  6, 3, 'confirmed', 'CV-20260510-V5W6X', '2026-05-10 13:00:00', NULL),
(9,  6, 7, 'confirmed', 'CV-20260510-Y7Z8A', '2026-05-10 13:15:00', NULL),
(10, 7, 8, 'confirmed', 'CV-20260510-B9C0D', '2026-05-10 14:00:00', NULL);

-- ── USER INTERESTS ───────────────────────────────────────────
INSERT INTO `user_interests` (`user_id`, `category_id`) VALUES
(3, 1), -- Budi: Teknologi
(3, 4), -- Budi: Kewirausahaan
(3, 5), -- Budi: Penelitian & Akademik
(4, 2), -- Siti: Seni & Budaya
(4, 7), -- Siti: Kesehatan
(5, 4), -- Rizky: Kewirausahaan
(5, 1), -- Rizky: Teknologi
(5, 5), -- Rizky: Penelitian & Akademik
(6, 2), -- Dewi: Seni & Budaya
(6, 8), -- Dewi: Musik
(7, 7), -- Ahmad: Kesehatan
(7, 3), -- Ahmad: Olahraga
(7, 6); -- Ahmad: Sosial & Lingkungan

-- ── NOTIFICATIONS ────────────────────────────────────────────
INSERT INTO `notifications` (`user_id`, `event_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(3, 1, 'event_baru',  'Event Baru Untukmu! 🔔',       'Hackathon Nasional 2026 cocok dengan minat Teknologi kamu. Daftar sekarang sebelum kuota habis!', 1, '2026-04-02 09:00:00'),
(3, 1, 'pendaftaran', 'Pendaftaran Berhasil ✅',       'Kamu telah berhasil mendaftar ke Hackathon Nasional 2026. Kode pendaftaranmu: CV-20260510-A1B2C. Jangan lupa hadir!', 0, '2026-05-10 10:30:00'),
(3, 2, 'pendaftaran', 'Pendaftaran Berhasil ✅',       'Kamu berhasil mendaftar ke Business Pitch Competition. Kode: CV-20260510-D3E4F. Persiapkan pitch terbaikmu!', 0, '2026-05-10 10:32:00'),
(3, 6, 'event_baru',  'Seminar AI — Cocok Untukmu!',   'Seminar Nasional AI & Machine Learning sesuai dengan minat Penelitian & Akademik kamu.', 0, '2026-04-26 10:00:00'),
(3, NULL,'sistem',    'Selamat Datang di CampusVents! 🎉','Akun kamu sudah aktif. Jelajahi ratusan event kampus dan temukan yang paling cocok untukmu. Selamat berkegiatan!', 1, '2026-01-10 10:00:00'),
(4, 3, 'event_baru',  'Pameran Seni Untukmu! 🎨',      'Pameran Seni Kontemporer Mahasiswa 2026 cocok dengan minat Seni & Budaya kamu. Jangan sampai ketinggalan!', 0, '2026-04-11 11:00:00'),
(4, NULL,'sistem',    'Selamat Datang di CampusVents! 🎉','Akun kamu sudah aktif. Mulai jelajahi event yang sesuai dengan minat Seni & Budaya kamu.', 1, '2026-01-12 11:00:00'),
(5, 2, 'event_baru',  'Pitch Competition — Cocok!',    'Business Pitch Competition StartUp Campus 2026 — kesempatan emas untuk startup kamu mendapat pendanaan!', 0, '2026-04-06 10:00:00'),
(5, NULL,'sistem',    'Selamat Datang di CampusVents! 🎉','Selamat bergabung! Akun kamu sudah aktif dan siap digunakan.', 1, '2026-01-15 14:00:00'),
(2, 3, 'validasi',    'Event Diajukan untuk Validasi', 'Event "Pameran Seni Kontemporer Mahasiswa 2026" telah diajukan dan menunggu persetujuan admin.', 0, '2026-04-10 11:00:00'),
(2, 4, 'validasi',    'Event Ditolak Admin',           'Event "Konser Akhir Semester" ditolak. Alasan: Belum ada izin penggunaan lapangan. Silakan revisi dan ajukan ulang.', 1, '2026-04-13 09:00:00'),
(7, 8, 'event_baru',  'Donor Darah — Cocok Untukmu!', 'Kegiatan Donor Darah & Health Check Up Gratis tersedia untuk umum. Yuk berpartisipasi!', 0, '2026-05-06 08:00:00');

-- ── RESET AUTO INCREMENT ─────────────────────────────────────
ALTER TABLE `users`          AUTO_INCREMENT = 9;
ALTER TABLE `categories`     AUTO_INCREMENT = 9;
ALTER TABLE `events`         AUTO_INCREMENT = 9;
ALTER TABLE `registrations`  AUTO_INCREMENT = 11;
ALTER TABLE `user_interests` AUTO_INCREMENT = 14;
ALTER TABLE `notifications`  AUTO_INCREMENT = 13;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  SELESAI
--  Akses aplikasi: http://localhost/campusvents/
--  Setup selesai: http://localhost/campusvents/setup.php
-- ============================================================
