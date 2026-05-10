<?php
/**
 * CampusVents — Database Setup
 * Jalankan sekali: http://localhost/campusvents/setup.php
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('DB_NAME', 'campusvents_db');

set_time_limit(120);

function setupDB(): PDO {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    return $pdo;
}

$log = [];

try {
    $db = setupDB();
    $log[] = ['ok', 'Database `campusvents_db` siap.'];

    // ── TABLES ──────────────────────────────────────────
    $db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(150) NOT NULL,
        email        VARCHAR(200) NOT NULL UNIQUE,
        password     VARCHAR(255) NOT NULL,
        nim          VARCHAR(30)  NULL,
        prodi        VARCHAR(100) NULL,
        role         ENUM('mahasiswa','panitia','admin') NOT NULL DEFAULT 'mahasiswa',
        avatar       VARCHAR(255) NULL,
        bio          TEXT         NULL,
        is_active    TINYINT(1)   NOT NULL DEFAULT 1,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
    ");
    $log[] = ['ok', 'Tabel users dibuat.'];

    $db->exec("
    CREATE TABLE IF NOT EXISTS categories (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        description VARCHAR(255) NULL,
        icon        VARCHAR(100) NOT NULL DEFAULT 'tag',
        color       VARCHAR(20)  NOT NULL DEFAULT '#1A1A2E',
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
    ");
    $log[] = ['ok', 'Tabel categories dibuat.'];

    $db->exec("
    CREATE TABLE IF NOT EXISTS events (
        id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title                 VARCHAR(255)  NOT NULL,
        description           TEXT          NOT NULL,
        category_id           INT UNSIGNED  NOT NULL,
        organizer_id          INT UNSIGNED  NOT NULL,
        date_start            DATETIME      NOT NULL,
        date_end              DATETIME      NOT NULL,
        location              VARCHAR(255)  NOT NULL,
        quota                 INT UNSIGNED  NOT NULL DEFAULT 100,
        registration_deadline DATETIME      NOT NULL,
        poster                VARCHAR(255)  NULL,
        status                ENUM('draft','pending','published','rejected','cancelled') NOT NULL DEFAULT 'draft',
        rejection_reason      TEXT          NULL,
        view_count            INT UNSIGNED  NOT NULL DEFAULT 0,
        created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id)  REFERENCES categories(id) ON DELETE RESTRICT,
        FOREIGN KEY (organizer_id) REFERENCES users(id)      ON DELETE CASCADE
    ) ENGINE=InnoDB
    ");
    $log[] = ['ok', 'Tabel events dibuat.'];

    $db->exec("
    CREATE TABLE IF NOT EXISTS registrations (
        id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id           INT UNSIGNED  NOT NULL,
        event_id          INT UNSIGNED  NOT NULL,
        status            ENUM('pending','confirmed','attended','cancelled') NOT NULL DEFAULT 'confirmed',
        registration_code VARCHAR(25)   NOT NULL UNIQUE,
        registered_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        attended_at       DATETIME      NULL,
        UNIQUE KEY uq_user_event (user_id, event_id),
        FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
    ");
    $log[] = ['ok', 'Tabel registrations dibuat.'];

    $db->exec("
    CREATE TABLE IF NOT EXISTS user_interests (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     INT UNSIGNED NOT NULL,
        category_id INT UNSIGNED NOT NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_cat (user_id, category_id),
        FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
    ");
    $log[] = ['ok', 'Tabel user_interests dibuat.'];

    $db->exec("
    CREATE TABLE IF NOT EXISTS notifications (
        id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id   INT UNSIGNED NOT NULL,
        event_id  INT UNSIGNED NULL,
        type      ENUM('event_baru','pendaftaran','validasi','pengingat','sistem') NOT NULL DEFAULT 'sistem',
        title     VARCHAR(255) NOT NULL,
        message   TEXT         NOT NULL,
        is_read   TINYINT(1)   NOT NULL DEFAULT 0,
        created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
    ) ENGINE=InnoDB
    ");
    $log[] = ['ok', 'Tabel notifications dibuat.'];

    // ── SEED CATEGORIES ─────────────────────────────────
    $catCount = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($catCount == 0) {
        $cats = [
            ['Teknologi',             'Event seputar teknologi, coding, dan inovasi digital', '💻', '#0069D9'],
            ['Seni & Budaya',         'Pertunjukan seni, festival budaya, dan pameran',       '🎨', '#B845CB'],
            ['Olahraga',              'Turnamen, lomba, dan kegiatan olahraga kampus',        '⚽', '#E86A00'],
            ['Kewirausahaan',         'Seminar bisnis, pitching, dan entrepreneurship',       '💼', '#0A7C59'],
            ['Penelitian & Akademik', 'Konferensi, seminar ilmiah, dan kompetisi akademik',  '🔬', '#1A1A2E'],
            ['Sosial & Lingkungan',   'Kegiatan sosial, bakti masyarakat, dan lingkungan',   '🌱', '#C94B00'],
            ['Kesehatan',             'Seminar kesehatan, donor darah, dan olahraga sehat',  '❤️', '#B80D2C'],
            ['Musik',                 'Konser, festival musik, dan pertunjukan seni suara',   '🎵', '#5B45CB'],
        ];
        $stmt = $db->prepare("INSERT INTO categories (name, description, icon, color) VALUES (?,?,?,?)");
        foreach ($cats as $c) $stmt->execute($c);
        $log[] = ['ok', '8 kategori berhasil ditambahkan.'];
    }

    // ── SEED USERS ──────────────────────────────────────
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount == 0) {
        $users = [
            ['Admin CampusVents',  'admin@campusvents.id',    password_hash('Admin@2026',    PASSWORD_BCRYPT), null, null, 'admin'],
            ['Panitia CampusVents','panitia@campusvents.id',  password_hash('Panitia@2026',  PASSWORD_BCRYPT), null, null, 'panitia'],
            ['Budi Mahasiswa',     'mahasiswa@campusvents.id',password_hash('Mahasiswa@2026',PASSWORD_BCRYPT), '20210001', 'Teknik Informatika', 'mahasiswa'],
        ];
        $stmt = $db->prepare("INSERT INTO users (name, email, password, nim, prodi, role) VALUES (?,?,?,?,?,?)");
        foreach ($users as $u) $stmt->execute($u);
        $log[] = ['ok', '3 user demo (admin, panitia, mahasiswa) ditambahkan.'];

        // Minat mahasiswa: Teknologi (id=1) + Kewirausahaan (id=4)
        $mhsId = $db->lastInsertId();
        // wait - mahasiswa is last inserted, find actual id
        $mhsId = $db->query("SELECT id FROM users WHERE role='mahasiswa' LIMIT 1")->fetchColumn();
        $panitiaId = $db->query("SELECT id FROM users WHERE role='panitia' LIMIT 1")->fetchColumn();
        $cat1 = $db->query("SELECT id FROM categories WHERE name='Teknologi'")->fetchColumn();
        $cat4 = $db->query("SELECT id FROM categories WHERE name='Kewirausahaan'")->fetchColumn();

        $db->prepare("INSERT INTO user_interests (user_id, category_id) VALUES (?,?)")->execute([$mhsId, $cat1]);
        $db->prepare("INSERT INTO user_interests (user_id, category_id) VALUES (?,?)")->execute([$mhsId, $cat4]);
        $log[] = ['ok', 'Minat mahasiswa demo ditambahkan.'];

        // ── SEED EVENTS ─────────────────────────────────
        $now = new DateTime();
        $events = [
            // published 1 — Teknologi
            [
                'Hackathon Nasional 2026 — Code For Future',
                'Hackathon selama 48 jam berturut-turut yang menantang mahasiswa untuk membangun solusi teknologi inovatif. Peserta akan dibagi dalam tim beranggotakan 3-5 orang dan berkompetisi untuk memenangkan total hadiah senilai 50 juta rupiah. Topik tahun ini: Sustainable Tech & Smart City. Tersedia mentoring dari engineer senior industri.',
                $cat1, $panitiaId,
                (clone $now)->modify('+14 days')->format('Y-m-d 08:00:00'),
                (clone $now)->modify('+16 days')->format('Y-m-d 17:00:00'),
                'Gedung Teknik A, Lantai 3 — Lab Komputer Terpadu',
                150, (clone $now)->modify('+10 days')->format('Y-m-d 23:59:59'),
                'published'
            ],
            // published 2 — Kewirausahaan
            [
                'Business Pitch Competition — StartUp Campus 2026',
                'Ajang bergengsi bagi mahasiswa wirausaha muda untuk mempresentasikan ide bisnis inovatif di hadapan investor dan mentor berpengalaman. Tahun ini membuka kesempatan bagi 20 tim terbaik untuk mendapatkan pendanaan awal hingga 100 juta rupiah dari venture capital partner kami. Workshop persiapan pitching tersedia gratis untuk semua peserta terdaftar.',
                $cat4, $panitiaId,
                (clone $now)->modify('+21 days')->format('Y-m-d 09:00:00'),
                (clone $now)->modify('+21 days')->format('Y-m-d 18:00:00'),
                'Auditorium Utama Kampus — Gedung Rektorat',
                200, (clone $now)->modify('+18 days')->format('Y-m-d 23:59:59'),
                'published'
            ],
            // pending — Seni & Budaya
            [
                'Pameran Seni Kontemporer Mahasiswa 2026',
                'Pameran seni terbuka yang menampilkan karya mahasiswa dari berbagai program studi. Meliputi lukisan, patung, instalasi, dan karya digital. Terbuka untuk umum selama 5 hari berturut-turut. Tersedia workshop singkat oleh seniman-seniman tamu dari berbagai kota.',
                $db->query("SELECT id FROM categories WHERE name='Seni & Budaya'")->fetchColumn(),
                $panitiaId,
                (clone $now)->modify('+30 days')->format('Y-m-d 10:00:00'),
                (clone $now)->modify('+35 days')->format('Y-m-d 21:00:00'),
                'Galeri Seni Kampus — Gedung Seni & Desain',
                300, (clone $now)->modify('+25 days')->format('Y-m-d 23:59:59'),
                'pending'
            ],
            // rejected — Musik
            [
                'Konser Akhir Tahun — Campus Music Festival',
                'Festival musik tahunan yang menampilkan band-band terbaik dari kampus dan tamu istimewa dari industri musik nasional. Line-up mencakup 12 penampil dengan genre beragam: indie, jazz, pop, dan EDM.',
                $db->query("SELECT id FROM categories WHERE name='Musik'")->fetchColumn(),
                $panitiaId,
                (clone $now)->modify('+7 days')->format('Y-m-d 18:00:00'),
                (clone $now)->modify('+7 days')->format('Y-m-d 23:00:00'),
                'Lapangan Basket Utama',
                500, (clone $now)->modify('+5 days')->format('Y-m-d 23:59:59'),
                'rejected'
            ],
            // draft — Olahraga
            [
                'Turnamen Futsal Antar Fakultas 2026',
                'Turnamen futsal bergengsi mempertemukan tim-tim terbaik dari setiap fakultas. Format turnamen: grup + knock-out. Juara mendapatkan trofi bergilir dan beasiswa olahraga. Daftarkan timmu sekarang!',
                $db->query("SELECT id FROM categories WHERE name='Olahraga'")->fetchColumn(),
                $panitiaId,
                (clone $now)->modify('+45 days')->format('Y-m-d 07:00:00'),
                (clone $now)->modify('+47 days')->format('Y-m-d 20:00:00'),
                'Lapangan Futsal Indoor Kampus',
                120, (clone $now)->modify('+40 days')->format('Y-m-d 23:59:59'),
                'draft'
            ],
        ];

        $stmtE = $db->prepare("
            INSERT INTO events (title, description, category_id, organizer_id, date_start, date_end, location, quota, registration_deadline, status)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");

        $eventIds = [];
        foreach ($events as $ev) {
            $stmtE->execute($ev);
            $eventIds[] = $db->lastInsertId();
        }

        // Set rejection reason untuk event ke-4
        $db->prepare("UPDATE events SET rejection_reason=? WHERE id=?")
           ->execute(['Belum ada izin penggunaan lapangan dari pihak sarana prasarana. Mohon lengkapi dokumen perizinan terlebih dahulu.', $eventIds[3]]);

        $log[] = ['ok', '5 event contoh ditambahkan (2 published, 1 pending, 1 rejected, 1 draft).'];

        // ── SEED REGISTRATIONS ───────────────────────────
        function genCode(PDO $db): string {
            do {
                $code = 'CV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $exists = $db->prepare("SELECT id FROM registrations WHERE registration_code=?");
                $exists->execute([$code]);
            } while ($exists->fetch());
            return $code;
        }

        $db->prepare("INSERT INTO registrations (user_id, event_id, status, registration_code) VALUES (?,?,?,?)")
           ->execute([$mhsId, $eventIds[0], 'confirmed', genCode($db)]);
        $db->prepare("INSERT INTO registrations (user_id, event_id, status, registration_code) VALUES (?,?,?,?)")
           ->execute([$mhsId, $eventIds[1], 'confirmed', genCode($db)]);
        $log[] = ['ok', 'Mahasiswa demo terdaftar di 2 event.'];

        // ── SEED NOTIFICATIONS ───────────────────────────
        $notifs = [
            [$mhsId, $eventIds[0], 'event_baru',     'Event Baru Untukmu!',        'Hackathon Nasional 2026 cocok dengan minat Teknologi kamu. Daftar sekarang sebelum kuota habis!'],
            [$mhsId, $eventIds[0], 'pendaftaran',    'Pendaftaran Berhasil',        'Kamu telah berhasil mendaftar ke Hackathon Nasional 2026. Selamat datang!'],
            [$mhsId, null,         'sistem',         'Selamat Datang di CampusVents!','Akun kamu sudah aktif. Jelajahi ratusan event kampus dan temukan yang paling cocok untukmu.'],
        ];
        $stmtN = $db->prepare("INSERT INTO notifications (user_id, event_id, type, title, message) VALUES (?,?,?,?,?)");
        foreach ($notifs as $n) $stmtN->execute($n);
        $log[] = ['ok', '3 notifikasi demo ditambahkan.'];
    } else {
        $log[] = ['info', 'User & data sudah ada, seed dilewati.'];
    }

    $log[] = ['ok', 'Setup selesai! Silakan hapus file setup.php untuk keamanan.'];

} catch (Throwable $e) {
    $log[] = ['error', 'ERROR: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CampusVents — Setup</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#F5F4F0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
.box{background:#fff;border:1px solid #E4E2DC;border-radius:16px;padding:2.5rem;max-width:640px;width:100%;box-shadow:0 12px 32px rgba(26,26,46,0.1)}
h1{font-family:'DM Serif Display',serif;font-size:2rem;color:#1A1A2E;margin-bottom:.25rem}
.sub{color:#5A6070;margin-bottom:2rem}
.log-item{display:flex;align-items:flex-start;gap:.75rem;padding:.625rem .875rem;border-radius:8px;margin-bottom:.5rem;font-size:.9rem}
.log-ok   {background:#E8FEF5;color:#0A7C59}
.log-error{background:#FEE8ED;color:#B80D2C}
.log-info {background:#E8F0FE;color:#0069D9}
.icon{flex-shrink:0;font-weight:700}
.links{margin-top:2rem;display:flex;gap:1rem;flex-wrap:wrap}
a.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;background:#1A1A2E;color:#F5F4F0;border-radius:10px;font-weight:600;text-decoration:none;font-size:.9375rem;transition:all .15s}
a.btn:hover{background:#16213E;box-shadow:4px 4px 0 #E94560;transform:translate(-2px,-2px)}
a.btn.outline{background:transparent;border:2px solid #1A1A2E;color:#1A1A2E}
a.btn.outline:hover{background:#1A1A2E;color:#F5F4F0}
.creds{margin-top:1.5rem;background:rgba(26,26,46,.04);border:1px solid #E4E2DC;border-radius:10px;padding:1rem 1.25rem}
.creds h4{font-size:.875rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9AA0AD;margin-bottom:.75rem}
.cred-row{display:flex;align-items:center;justify-content:space-between;font-size:.875rem;padding:.375rem 0;border-bottom:1px solid #E4E2DC}
.cred-row:last-child{border-bottom:none}
.cred-role{font-weight:600;color:#1A1A2E}
.cred-val{font-family:'JetBrains Mono',monospace;font-size:.8125rem;color:#5A6070}
</style>
</head>
<body>
<div class="box">
  <h1>CampusVents</h1>
  <p class="sub">Setup Database &amp; Seed Data</p>

  <?php foreach ($log as [$type, $msg]): ?>
  <div class="log-item log-<?= $type ?>">
    <span class="icon"><?= $type === 'ok' ? '✓' : ($type === 'error' ? '✕' : 'ℹ') ?></span>
    <span><?= htmlspecialchars($msg) ?></span>
  </div>
  <?php endforeach; ?>

  <div class="creds">
    <h4>Akun Demo</h4>
    <div class="cred-row"><span class="cred-role">Admin</span><span class="cred-val">admin@campusvents.id / Admin@2026</span></div>
    <div class="cred-row"><span class="cred-role">Panitia</span><span class="cred-val">panitia@campusvents.id / Panitia@2026</span></div>
    <div class="cred-row"><span class="cred-role">Mahasiswa</span><span class="cred-val">mahasiswa@campusvents.id / Mahasiswa@2026</span></div>
  </div>

  <div class="links">
    <a href="/campusvents/index.php" class="btn">Buka CampusVents</a>
    <a href="/campusvents/login.php" class="btn outline">Halaman Login</a>
    <a href="/campusvents/campusvents.sql" class="btn outline" download>
      ↓ Download SQL
    </a>
  </div>

  <div class="info-box" style="margin-top:1.5rem;background:rgba(0,201,167,.08);border:1px solid rgba(0,201,167,.25);border-radius:10px;padding:1rem 1.25rem;font-size:.875rem;color:#0A7C59;display:flex;gap:.75rem;align-items:flex-start">
    <span style="flex-shrink:0;font-size:1.1rem">ℹ️</span>
    <span>File <strong>campusvents.sql</strong> tersedia untuk import langsung ke phpMyAdmin atau MySQL CLI. Alternatifnya, kamu sudah bisa langsung login menggunakan akun demo di atas.</span>
  </div>

  <p style="margin-top:1.25rem;font-size:.8rem;color:#9AA0AD">
    ⚠️ Hapus file <code style="font-family:'JetBrains Mono',monospace;font-size:.8rem">setup.php</code> setelah instalasi selesai untuk keamanan.
  </p>
</div>
</body>
</html>
