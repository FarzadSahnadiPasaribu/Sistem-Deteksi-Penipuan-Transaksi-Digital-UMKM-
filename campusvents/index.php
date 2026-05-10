<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();

// Stats
$totalEvents    = $db->query("SELECT COUNT(*) FROM events WHERE status='published'")->fetchColumn();
$totalUsers     = $db->query("SELECT COUNT(*) FROM users WHERE role='mahasiswa'")->fetchColumn();
$totalRegs      = $db->query("SELECT COUNT(*) FROM registrations WHERE status != 'cancelled'")->fetchColumn();
$totalCategories = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Latest published events
$latestEvents = $db->query("
    SELECT e.*, c.name as category_name, c.icon as category_icon,
           u.name as organizer_name,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status != 'cancelled') as registered_count
    FROM events e
    JOIN categories c ON e.category_id = c.id
    JOIN users u ON e.organizer_id = u.id
    WHERE e.status = 'published'
    ORDER BY e.created_at DESC
    LIMIT 6
")->fetchAll();

// Categories with event counts
$allCategories = $db->query("
    SELECT c.*, COUNT(e.id) as event_count
    FROM categories c
    LEFT JOIN events e ON e.category_id=c.id AND e.status='published'
    GROUP BY c.id
    ORDER BY event_count DESC
")->fetchAll();

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Beranda');
include __DIR__ . '/includes/header.php';
?>

<!-- Public Navbar -->
<nav class="public-nav" id="main-nav">
  <div class="container">
    <div class="nav-inner">
      <a href="<?= BASE_URL ?>/index.php" class="nav-logo">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Campus<span class="accent">Vents</span>
      </a>
      <div class="nav-links">
        <a href="#events" class="nav-link">Event</a>
        <a href="#cara-kerja" class="nav-link">Cara Kerja</a>
        <?php if (isLoggedIn()): ?>
        <a href="<?= BASE_URL ?>/<?= $_SESSION['user_role'] ?>/dashboard.php" class="nav-link">Dashboard</a>
        <?php endif; ?>
      </div>
      <div class="nav-actions">
        <?php if (isLoggedIn()): ?>
        <a href="<?= BASE_URL ?>/<?= $_SESSION['user_role'] ?>/dashboard.php" class="btn btn-primary btn-sm">
          Ke Dashboard
        </a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost btn-sm" style="color:rgba(255,255,255,0.8)">Masuk</a>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent btn-sm">Daftar Gratis</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- Hero -->
<section class="hero-section">
  <div class="container">
    <div class="hero-inner">
      <div class="hero-content">
        <div class="hero-eyebrow">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          Platform Event Kampus #1
        </div>
        <h1 class="hero-title">
          Satu Tempat<br>
          untuk Semua<br>
          <span class="accent">Event Kampus</span>
        </h1>
        <p class="hero-text">
          Temukan ratusan event kampus yang sesuai dengan minat dan passionmu. Daftar, hadir, dan jadilah bagian dari komunitas mahasiswa aktif.
        </p>
        <div class="hero-cta">
          <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent btn-lg">
            Mulai Sekarang — Gratis
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
          <a href="#events" class="btn btn-outline btn-lg" style="border-color:rgba(255,255,255,0.3);color:white;background:rgba(255,255,255,0.08)">
            Jelajahi Event
          </a>
        </div>
      </div>

      <div class="hero-illustration">
        <svg width="480" height="420" viewBox="0 0 480 420" fill="none" xmlns="http://www.w3.org/2000/svg">
          <!-- Background circle -->
          <circle cx="240" cy="210" r="180" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
          <!-- Calendar base -->
          <rect x="100" y="80" width="280" height="220" rx="16" fill="rgba(255,255,255,0.06)" stroke="rgba(255,255,255,0.12)" stroke-width="1.5"/>
          <!-- Calendar header -->
          <rect x="100" y="80" width="280" height="56" rx="16" fill="rgba(233,69,96,0.25)"/>
          <rect x="100" y="112" width="280" height="24" fill="rgba(233,69,96,0.25)"/>
          <!-- Calendar title -->
          <text x="240" y="114" text-anchor="middle" fill="white" font-family="serif" font-size="18" font-style="italic" opacity="0.9">Event Kampus</text>
          <!-- Calendar grid dots -->
          <?php
          $cols = [140,176,212,248,284,320,356];
          $rows = [168,204,240,276];
          foreach ($rows as $r) {
              foreach ($cols as $c) {
                  $fill = ($r === 204 && $c === 212) || ($r === 240 && $c === 320)
                      ? 'rgba(233,69,96,0.9)' : 'rgba(255,255,255,0.15)';
                  echo "<rect x=\"".($c-12)."\" y=\"".($r-12)."\" width=\"24\" height=\"24\" rx=\"6\" fill=\"{$fill}\"/>";
              }
          }
          ?>
          <!-- Floating event cards -->
          <rect x="300" y="40"  width="160" height="72" rx="10" fill="rgba(26,26,46,0.85)" stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
          <rect x="308" y="52"  width="60"  height="8"  rx="3" fill="rgba(233,69,96,0.7)"/>
          <rect x="308" y="66"  width="140" height="6"  rx="3" fill="rgba(255,255,255,0.4)"/>
          <rect x="308" y="78"  width="100" height="6"  rx="3" fill="rgba(255,255,255,0.2)"/>
          <rect x="308" y="92"  width="80"  height="6"  rx="3" fill="rgba(255,255,255,0.15)"/>

          <rect x="20"  y="160" width="150" height="72" rx="10" fill="rgba(26,26,46,0.85)" stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
          <rect x="28"  y="172" width="50"  height="8"  rx="3" fill="rgba(0,201,167,0.7)"/>
          <rect x="28"  y="186" width="130" height="6"  rx="3" fill="rgba(255,255,255,0.4)"/>
          <rect x="28"  y="198" width="90"  height="6"  rx="3" fill="rgba(255,255,255,0.2)"/>
          <rect x="28"  y="210" width="70"  height="6"  rx="3" fill="rgba(255,255,255,0.15)"/>

          <rect x="320" y="300" width="150" height="72" rx="10" fill="rgba(26,26,46,0.85)" stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
          <rect x="328" y="312" width="60"  height="8"  rx="3" fill="rgba(245,166,35,0.7)"/>
          <rect x="328" y="326" width="130" height="6"  rx="3" fill="rgba(255,255,255,0.4)"/>
          <rect x="328" y="338" width="90"  height="6"  rx="3" fill="rgba(255,255,255,0.2)"/>
          <rect x="328" y="350" width="70"  height="6"  rx="3" fill="rgba(255,255,255,0.15)"/>

          <!-- Accent dots -->
          <circle cx="392" cy="210" r="8" fill="rgba(233,69,96,0.6)"/>
          <circle cx="60"  cy="100" r="6" fill="rgba(0,201,167,0.4)"/>
          <circle cx="440" cy="380" r="10" fill="rgba(245,166,35,0.3)"/>
        </svg>
      </div>
    </div>
  </div>
</section>

<!-- Stats Bar -->
<section class="stats-section">
  <div class="container">
    <div class="stats-row">
      <div class="stat-item">
        <div class="stat-big-number" data-counter="<?= (int)$totalEvents ?: 127 ?>">0</div>
        <div class="stat-big-label">Event Aktif</div>
      </div>
      <div class="stat-item">
        <div class="stat-big-number" data-counter="<?= (int)$totalUsers ?: 3840 ?>">0</div>
        <div class="stat-big-label">Mahasiswa Terdaftar</div>
      </div>
      <div class="stat-item">
        <div class="stat-big-number" data-counter="<?= (int)$totalRegs ?: 12400 ?>">0</div>
        <div class="stat-big-label">Total Pendaftaran</div>
      </div>
      <div class="stat-item">
        <div class="stat-big-number" data-counter="<?= (int)$totalCategories ?: 8 ?>">0</div>
        <div class="stat-big-label">Kategori Event</div>
      </div>
    </div>
  </div>
</section>

<!-- Latest Events -->
<section class="public-section" id="events">
  <div class="container">
    <h2 class="public-section-title">Event Terbaru</h2>
    <p class="public-section-sub">Temukan event kampus terbaru yang dipublikasikan oleh berbagai organisasi dan UKM.</p>
    <div class="accent-divider"></div>

    <?php if (empty($latestEvents)): ?>
    <div class="empty-state">
      <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <h3>Belum ada event tersedia</h3>
      <p>Event terbaru akan muncul di sini. Cek kembali nanti atau daftar untuk mendapat notifikasi.</p>
      <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary">Daftar Sekarang</a>
    </div>
    <?php else: ?>
    <div class="cards-grid">
      <?php foreach ($latestEvents as $ev):
        $quota = getQuotaStatus((int)$ev['registered_count'], (int)$ev['quota']);
        $badgeClass = getCategoryBadgeClass($ev['category_name']);
      ?>
      <div class="card event-card">
        <?php if ($ev['poster']): ?>
        <img src="<?= BASE_URL ?>/uploads/posters/<?= htmlspecialchars($ev['poster']) ?>"
             alt="<?= htmlspecialchars($ev['title']) ?>" class="card-img">
        <?php else: ?>
        <div class="card-img-placeholder">
          <?= htmlspecialchars($ev['category_icon']) ?>
        </div>
        <?php endif; ?>

        <div class="card-body">
          <div style="margin-bottom:var(--space-3)">
            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($ev['category_name']) ?></span>
          </div>
          <h3 class="card-title"><?= htmlspecialchars($ev['title']) ?></h3>
          <div class="card-meta">
            <div class="meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?= formatDatetime($ev['date_start']) ?>
            </div>
            <div class="meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <?= htmlspecialchars(truncate($ev['location'], 45)) ?>
            </div>
            <div class="meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <?= htmlspecialchars($ev['organizer_name']) ?>
            </div>
          </div>
          <div class="quota-bar">
            <div class="quota-bar-track">
              <div class="quota-bar-fill <?= $quota['status'] === 'full' ? 'full' : ($quota['status'] === 'almost-full' ? 'almost-full' : '') ?>"
                   style="width:<?= $quota['percent'] ?>%"></div>
            </div>
            <div class="quota-text">
              <span><?= $quota['remaining'] ?> tempat tersisa</span>
              <span><?= $ev['registered_count'] ?>/<?= $ev['quota'] ?></span>
            </div>
          </div>
        </div>

        <div class="card-footer">
          <?php if (isLoggedIn()): ?>
          <a href="<?= BASE_URL ?>/mahasiswa/detail_event.php?id=<?= $ev['id'] ?>" class="btn btn-primary btn-sm btn-block">
            Lihat Detail
          </a>
          <?php else: ?>
          <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline btn-sm btn-block">
            Login untuk Daftar
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (isLoggedIn()): ?>
    <div style="text-align:center;margin-top:var(--space-10)">
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn btn-outline btn-lg">
        Lihat Semua Event
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<!-- Categories Showcase -->
<section class="public-section-alt">
  <div class="container">
    <h2 class="public-section-title">Jelajahi Berdasarkan Kategori</h2>
    <p class="public-section-sub">Dari teknologi hingga seni — temukan event sesuai passion dan minatmu.</p>
    <div class="accent-divider"></div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:var(--space-4)">
      <?php foreach ($allCategories as $cat):
        $badgeClass = getCategoryBadgeClass($cat['name']);
        $catUrl = isLoggedIn() ? '/campusvents/mahasiswa/katalog.php?cat=' . $cat['id'] : '/campusvents/register.php';
      ?>
      <a href="<?= $catUrl ?>" style="display:flex;flex-direction:column;align-items:center;gap:var(--space-3);padding:var(--space-6) var(--space-4);background:var(--clr-bg-card);border:1.5px solid var(--clr-border);border-radius:var(--radius-lg);text-decoration:none;transition:all var(--dur-normal);text-align:center"
         onmouseenter="this.style.borderColor='<?= htmlspecialchars($cat['color']) ?>';this.style.boxShadow='0 8px 24px rgba(0,0,0,0.08)';this.style.transform='translateY(-3px)'"
         onmouseleave="this.style.borderColor='';this.style.boxShadow='';this.style.transform=''">
        <div style="font-size:2.25rem;line-height:1"><?= $cat['icon'] ?></div>
        <div style="font-weight:700;font-size:.9375rem;color:var(--clr-text-primary)"><?= htmlspecialchars($cat['name']) ?></div>
        <span class="badge <?= $badgeClass ?>"><?= $cat['event_count'] ?> event</span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Features Section -->
<section class="public-section" style="background:var(--clr-bg)">
  <div class="container">
    <h2 class="public-section-title">Kenapa CampusVents?</h2>
    <p class="public-section-sub">Platform yang dirancang khusus untuk ekosistem kampus Indonesia.</p>
    <div class="accent-divider"></div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-6)" class="features-grid">
      <?php
      $features = [
        ['🎯', 'Rekomendasi Personal',     'Sistem merekomendasikan event berdasarkan minat yang kamu pilih saat daftar. Tidak perlu scroll panjang — event terbaik langsung tampil.', '#E94560'],
        ['🔔', 'Notifikasi Real-Time',     'Dapatkan notifikasi instan saat event baru yang cocok dengan minatmu diterbitkan. Tidak perlu cek manual setiap hari.', '#00C9A7'],
        ['📱', 'Kode Pendaftaran Unik',    'Setiap pendaftaran menghasilkan kode unik format CV-YYYYMMDD-XXXXX. Tunjukkan saat hadir untuk absensi yang cepat dan akurat.', '#F5A623'],
        ['🛡️', 'Validasi Event Ketat',     'Setiap event divalidasi admin sebelum dipublikasikan. Hanya event resmi dan berkualitas yang tampil di platform.', '#0069D9'],
        ['📊', 'Dashboard Panitia Lengkap','Panitia bisa memantau pendaftar, melakukan absensi, dan melihat statistik event secara real-time melalui dashboard khusus.', '#B845CB'],
        ['📈', 'Laporan & Ekspor Data',    'Admin dapat melihat laporan lengkap dan mengekspor data ke CSV atau SQL backup kapan saja untuk keperluan analitik.', '#0A7C59'],
      ];
      foreach ($features as [$icon, $title, $desc, $color]):
      ?>
      <div style="padding:var(--space-6);background:var(--clr-bg-card);border:1px solid var(--clr-border);border-radius:var(--radius-lg);border-top:3px solid <?= $color ?>">
        <div style="font-size:2rem;margin-bottom:var(--space-4)"><?= $icon ?></div>
        <h4 style="font-size:1rem;font-weight:700;margin-bottom:var(--space-2);color:var(--clr-text-primary)"><?= $title ?></h4>
        <p style="font-size:.875rem;color:var(--clr-text-secondary);line-height:1.6;margin:0"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- How It Works -->
<section class="public-section-alt" id="cara-kerja">
  <div class="container">
    <h2 class="public-section-title">Cara Kerja CampusVents</h2>
    <p class="public-section-sub">Tiga langkah mudah untuk mulai menjelajahi dan mendaftar event kampus.</p>
    <div class="accent-divider"></div>

    <div class="how-steps">
      <div class="how-step">
        <div class="how-step-number">01</div>
        <div class="how-step-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--clr-brand)" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <h4>Buat Akun & Pilih Minat</h4>
        <p>Daftar gratis dalam 2 menit. Pilih topik yang kamu minati — Teknologi, Seni, Olahraga, dan lainnya.</p>
      </div>
      <div class="how-step">
        <div class="how-step-number">02</div>
        <div class="how-step-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--clr-accent)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
        <h4>Temukan Event yang Relevan</h4>
        <p>Sistem rekomendasi kami menampilkan event yang paling sesuai dengan minatmu secara otomatis.</p>
      </div>
      <div class="how-step">
        <div class="how-step-number">03</div>
        <div class="how-step-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--clr-mint)" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        </div>
        <h4>Daftar & Hadir</h4>
        <p>Daftar dengan satu klik, dapatkan kode unik pendaftaranmu, dan hadir di hari-H dengan mudah.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="public-section">
  <div class="container" style="text-align:center">
    <h2 style="font-family:'DM Serif Display',serif;font-size:clamp(2rem,4vw,3rem);margin-bottom:var(--space-4)">
      Siap Menjelajahi Event Kampus?
    </h2>
    <p style="color:var(--clr-text-secondary);font-size:1.0625rem;max-width:520px;margin:0 auto var(--space-8)">
      Bergabung dengan ribuan mahasiswa yang sudah menggunakan CampusVents untuk menemukan event terbaik.
    </p>
    <?php if (!isLoggedIn()): ?>
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-lg">
      Daftar Sekarang — Gratis
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
    <?php else: ?>
    <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn btn-primary btn-lg">
      Jelajahi Event
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
    <?php endif; ?>
  </div>
</section>

<!-- Footer -->
<footer class="public-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand-name">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Campus<span class="accent">Vents</span>
        </div>
        <p class="footer-desc">Platform agregator dan manajemen event kampus dengan sistem rekomendasi berbasis minat. Satu platform untuk semua kegiatan mahasiswa.</p>
      </div>
      <div>
        <div class="footer-col-title">Navigasi</div>
        <div class="footer-links">
          <a href="<?= BASE_URL ?>/index.php" class="footer-link">Beranda</a>
          <a href="<?= BASE_URL ?>/register.php" class="footer-link">Daftar</a>
          <a href="<?= BASE_URL ?>/login.php" class="footer-link">Masuk</a>
        </div>
      </div>
      <div>
        <div class="footer-col-title">Informasi</div>
        <div class="footer-links">
          <a href="#" class="footer-link">Tentang Kami</a>
          <a href="#" class="footer-link">Syarat & Ketentuan</a>
          <a href="#" class="footer-link">Kebijakan Privasi</a>
        </div>
      </div>
    </div>
    <div class="footer-divider"></div>
    <div class="footer-bottom">
      <span class="footer-copy">&copy; <?= date('Y') ?> CampusVents. Dibuat dengan ❤ untuk mahasiswa Indonesia.</span>
      <span class="footer-copy">PHP Native + MySQL</span>
    </div>
  </div>
</footer>

<style>
@media(max-width:1023px){.features-grid{grid-template-columns:repeat(2,1fr)!important}}
@media(max-width:600px){.features-grid{grid-template-columns:1fr!important}}
</style>
<?php include __DIR__ . '/includes/footer.php'; ?>
