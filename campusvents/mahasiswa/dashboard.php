<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('mahasiswa');

$user = currentUser();
$db   = getDB();

// Stats
$recommended = count(getRecommendedEvents($user['id'], 10));
$registered  = $db->prepare("SELECT COUNT(*) FROM registrations WHERE user_id=? AND status!='cancelled'");
$registered->execute([$user['id']]);
$registeredCount = (int)$registered->fetchColumn();

$attended = $db->prepare("SELECT COUNT(*) FROM registrations WHERE user_id=? AND status='attended'");
$attended->execute([$user['id']]);
$attendedCount = (int)$attended->fetchColumn();

$unread = getUnreadNotificationCount($user['id']);

// Recommended Events (by interest)
$recEvents = getRecommendedEvents($user['id'], 3);

// Latest published events (not registered)
$latestStmt = $db->prepare("
    SELECT e.*, c.name as category_name, c.icon as category_icon, u.name as organizer_name,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status!='cancelled') as registered_count
    FROM events e
    JOIN categories c ON e.category_id=c.id
    JOIN users u ON e.organizer_id=u.id
    WHERE e.status='published' AND e.registration_deadline > NOW()
    ORDER BY e.created_at DESC
    LIMIT 3
");
$latestStmt->execute();
$latestEvents = $latestStmt->fetchAll();

// Upcoming registered events
$upcomingStmt = $db->prepare("
    SELECT e.title, e.date_start, e.location, r.status
    FROM registrations r
    JOIN events e ON r.event_id=e.id
    WHERE r.user_id=? AND r.status IN ('confirmed','attended') AND e.date_start > NOW()
    ORDER BY e.date_start ASC
    LIMIT 3
");
$upcomingStmt->execute([$user['id']]);
$upcoming = $upcomingStmt->fetchAll();

$days_id = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$months_id = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$today = $days_id[date('w')] . ', ' . date('j') . ' ' . $months_id[date('n')-1] . ' ' . date('Y');

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Dashboard');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <!-- Greeting Card -->
    <div class="greeting-card">
      <div class="greeting-date"><?= $today ?></div>
      <h2 class="greeting-title">
        Selamat datang, <strong><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></strong>
      </h2>
      <p class="greeting-sub">Temukan event kampus yang sesuai dengan minatmu hari ini.</p>
    </div>

    <!-- Stats Row -->
    <div class="stats-grid" style="margin-bottom:var(--space-8)">
      <div class="stat-card">
        <div class="stat-icon stat-icon-accent">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </div>
        <div class="stat-info">
          <div class="stat-number"><?= $recommended ?></div>
          <div class="stat-label">Direkomendasikan</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-brand">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>
        </div>
        <div class="stat-info">
          <div class="stat-number"><?= $registeredCount ?></div>
          <div class="stat-label">Sudah Didaftar</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-mint">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        </div>
        <div class="stat-info">
          <div class="stat-number"><?= $attendedCount ?></div>
          <div class="stat-label">Sudah Dihadiri</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-gold">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        </div>
        <div class="stat-info">
          <div class="stat-number"><?= $unread ?></div>
          <div class="stat-label">Notifikasi Baru</div>
        </div>
      </div>
    </div>

    <!-- Rekomendasi -->
    <?php if (!empty($recEvents)): ?>
    <div class="section-header">
      <div>
        <h2 class="section-title">Rekomendasi Untukmu</h2>
        <div class="section-title-bar"></div>
      </div>
      <a href="/campusvents/mahasiswa/katalog.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
    </div>
    <div class="cards-grid" style="margin-bottom:var(--space-10)">
      <?php foreach ($recEvents as $ev):
        $quota = getQuotaStatus((int)$ev['registered_count'], (int)$ev['quota']);
        $bc = getCategoryBadgeClass($ev['category_name']);
      ?>
      <div class="card event-card">
        <?php if ($ev['poster']): ?>
        <img src="/campusvents/uploads/posters/<?= htmlspecialchars($ev['poster']) ?>" class="card-img" alt="">
        <?php else: ?>
        <div class="card-img-placeholder"><?= htmlspecialchars($ev['category_icon']) ?></div>
        <?php endif; ?>
        <div class="card-body">
          <div style="margin-bottom:var(--space-2)">
            <span class="badge <?= $bc ?>"><?= htmlspecialchars($ev['category_name']) ?></span>
          </div>
          <h3 class="card-title"><?= htmlspecialchars($ev['title']) ?></h3>
          <div class="card-meta">
            <div class="meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?= formatDate($ev['date_start']) ?>
            </div>
            <div class="meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <?= htmlspecialchars(truncate($ev['location'], 40)) ?>
            </div>
          </div>
          <div class="quota-bar">
            <div class="quota-bar-track">
              <div class="quota-bar-fill <?= $quota['status'] === 'full' ? 'full' : ($quota['status'] === 'almost-full' ? 'almost-full' : '') ?>"
                   style="width:<?= $quota['percent'] ?>%"></div>
            </div>
            <div class="quota-text"><span><?= $quota['remaining'] ?> tersisa</span><span><?= $ev['registered_count'] ?>/<?= $ev['quota'] ?></span></div>
          </div>
        </div>
        <div class="card-footer">
          <a href="/campusvents/mahasiswa/detail_event.php?id=<?= $ev['id'] ?>" class="btn btn-primary btn-sm btn-block">Daftar Sekarang</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Grid: Latest Events + Upcoming -->
    <div style="display:grid;grid-template-columns:1fr 340px;gap:var(--space-8);align-items:start" class="dashboard-two-col">
      <!-- Latest Events -->
      <div>
        <div class="section-header">
          <div>
            <h2 class="section-title">Event Terbaru</h2>
            <div class="section-title-bar"></div>
          </div>
          <a href="/campusvents/mahasiswa/katalog.php" class="btn btn-ghost btn-sm">Semua Event</a>
        </div>
        <?php if (empty($latestEvents)): ?>
        <div class="empty-state" style="padding:var(--space-10)">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <p>Belum ada event baru.</p>
        </div>
        <?php else: ?>
        <div class="cards-grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
          <?php foreach ($latestEvents as $ev):
            $bc = getCategoryBadgeClass($ev['category_name']);
          ?>
          <div class="card event-card">
            <?php if ($ev['poster']): ?>
            <img src="/campusvents/uploads/posters/<?= htmlspecialchars($ev['poster']) ?>" class="card-img" alt="">
            <?php else: ?>
            <div class="card-img-placeholder" style="font-size:.9rem"><?= htmlspecialchars($ev['category_icon']) ?></div>
            <?php endif; ?>
            <div class="card-body">
              <span class="badge <?= $bc ?>" style="margin-bottom:var(--space-2);display:inline-flex"><?= htmlspecialchars($ev['category_name']) ?></span>
              <h3 class="card-title"><?= htmlspecialchars($ev['title']) ?></h3>
              <div class="meta-item"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?= formatDate($ev['date_start']) ?></div>
            </div>
            <div class="card-footer">
              <a href="/campusvents/mahasiswa/detail_event.php?id=<?= $ev['id'] ?>" class="btn btn-outline btn-sm btn-block">Lihat Detail</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Upcoming Events I've registered -->
      <div>
        <div class="section-header">
          <div>
            <h2 class="section-title" style="font-size:1.375rem">Jadwal Mendatang</h2>
            <div class="section-title-bar"></div>
          </div>
        </div>
        <?php if (empty($upcoming)): ?>
        <div class="card" style="padding:var(--space-6);text-align:center">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--clr-text-muted)" stroke-width="1.5" style="margin:0 auto var(--space-3)"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <p style="font-size:.875rem">Kamu belum terdaftar di event apapun.</p>
          <a href="/campusvents/mahasiswa/katalog.php" class="btn btn-primary btn-sm" style="margin-top:var(--space-3)">Cari Event</a>
        </div>
        <?php else: ?>
        <div class="upcoming-list">
          <?php foreach ($upcoming as $ev):
            $dt = new DateTime($ev['date_start']);
          ?>
          <a href="/campusvents/mahasiswa/my_events.php" class="upcoming-item">
            <div class="upcoming-date-box">
              <div class="upcoming-date-day"><?= $dt->format('d') ?></div>
              <div class="upcoming-date-mon"><?= ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][$dt->format('n')-1] ?></div>
            </div>
            <div class="upcoming-info">
              <div class="upcoming-title"><?= htmlspecialchars($ev['title']) ?></div>
              <div class="upcoming-meta"><?= $dt->format('H:i') ?> WIB · <?= htmlspecialchars(truncate($ev['location'], 30)) ?></div>
            </div>
            <span class="status-badge status-<?= htmlspecialchars($ev['status']) ?>"><?= ucfirst($ev['status']) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
        <a href="/campusvents/mahasiswa/my_events.php" class="btn btn-ghost btn-sm" style="margin-top:var(--space-4);width:100%">Lihat Semua</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<style>
@media(max-width:1279px){.dashboard-two-col{grid-template-columns:1fr!important}}
</style>

<?php include '../includes/footer.php'; ?>
