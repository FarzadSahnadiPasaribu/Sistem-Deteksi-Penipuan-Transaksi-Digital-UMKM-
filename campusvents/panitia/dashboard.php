<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('panitia');

$user = currentUser();
$db   = getDB();

$myEvents     = $db->prepare("SELECT COUNT(*) FROM events WHERE organizer_id=?"); $myEvents->execute([$user['id']]); $myEventsCount = (int)$myEvents->fetchColumn();
$published    = $db->prepare("SELECT COUNT(*) FROM events WHERE organizer_id=? AND status='published'"); $published->execute([$user['id']]); $publishedCount = (int)$published->fetchColumn();
$pending      = $db->prepare("SELECT COUNT(*) FROM events WHERE organizer_id=? AND status='pending'"); $pending->execute([$user['id']]); $pendingCount = (int)$pending->fetchColumn();

$totalRegs    = $db->prepare("
    SELECT COUNT(*) FROM registrations r
    JOIN events e ON r.event_id=e.id
    WHERE e.organizer_id=? AND r.status!='cancelled'
"); $totalRegs->execute([$user['id']]); $totalRegsCount = (int)$totalRegs->fetchColumn();

$recentEvents = $db->prepare("
    SELECT e.*, c.name as category_name,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status!='cancelled') as registered_count
    FROM events e
    JOIN categories c ON e.category_id=c.id
    WHERE e.organizer_id=?
    ORDER BY e.created_at DESC
    LIMIT 5
");
$recentEvents->execute([$user['id']]);
$recentEvts = $recentEvents->fetchAll();

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Dashboard Panitia');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">

    <?php
    // Cek status verified langsung dari DB (lebih akurat dari session)
    $verifiedStatus = (int)$db->prepare("SELECT verified FROM users WHERE id=?")->execute([$user['id']]) ?
        $db->prepare("SELECT verified FROM users WHERE id=?")->execute([$user['id']]) : 1;
    $verifiedRow = $db->prepare("SELECT verified, is_active FROM users WHERE id=?");
    $verifiedRow->execute([$user['id']]);
    $verifiedData = $verifiedRow->fetch();
    $isVerified   = (int)($verifiedData['verified'] ?? 1);
    $isActive     = (int)($verifiedData['is_active'] ?? 1);
    ?>

    <?php if (!$isActive): ?>
    <div class="alert" style="background:#fff0f0;border:2px solid var(--clr-accent);color:#7a0000;margin-bottom:var(--space-6);padding:var(--space-5);border-radius:var(--radius-lg)">
      <div style="font-weight:700;font-size:1.05rem;margin-bottom:var(--space-2)">❌ Pendaftaran Panitia Ditolak</div>
      <p style="margin:0">Maaf, pendaftaran akun panitia kamu ditolak oleh admin. Silakan hubungi admin untuk informasi lebih lanjut atau daftarkan akun baru.</p>
    </div>
    <?php elseif (!$isVerified): ?>
    <div class="alert" style="background:#fffbeb;border:2px solid #f5a623;color:#7a4f00;margin-bottom:var(--space-6);padding:var(--space-5);border-radius:var(--radius-lg)">
      <div style="font-weight:700;font-size:1.05rem;margin-bottom:var(--space-2)">⏳ Akun Menunggu Verifikasi Admin</div>
      <p style="margin:0 0 var(--space-3)">Akun panitia kamu sedang dalam proses verifikasi oleh admin CampusVents. Proses ini membutuhkan waktu maksimal <strong>1×24 jam</strong>.</p>
      <p style="margin:0;font-size:.875rem;opacity:.8">Kamu belum bisa membuat event sampai akun diverifikasi. Cek notifikasi untuk update status verifikasi.</p>
    </div>
    <?php endif; ?>

    <div class="greeting-card">
      <div class="greeting-date">Dashboard Panitia</div>
      <h2 class="greeting-title">Halo, <strong><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></strong></h2>
      <p class="greeting-sub">Kelola event kampusmu dengan mudah dan efisien.</p>
      <?php if ($isVerified && $isActive): ?>
      <a href="<?= BASE_URL ?>/panitia/buat_event.php" class="btn btn-accent" style="margin-top:var(--space-5);display:inline-flex">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Buat Event Baru
      </a>
      <?php else: ?>
      <span class="btn" style="margin-top:var(--space-5);display:inline-flex;background:var(--clr-border);color:var(--clr-text-muted);cursor:not-allowed" title="Akun belum terverifikasi">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Buat Event (Perlu Verifikasi)
      </span>
      <?php endif; ?>
    </div>

    <div class="stats-grid" style="margin-bottom:var(--space-8)">
      <div class="stat-card">
        <div class="stat-icon stat-icon-brand">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-info"><div class="stat-number"><?= $myEventsCount ?></div><div class="stat-label">Total Event Saya</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-mint">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-info"><div class="stat-number"><?= $publishedCount ?></div><div class="stat-label">Event Dipublish</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-gold">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="stat-info"><div class="stat-number"><?= $pendingCount ?></div><div class="stat-label">Menunggu Validasi</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-accent">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-info"><div class="stat-number"><?= $totalRegsCount ?></div><div class="stat-label">Total Pendaftar</div></div>
      </div>
    </div>

    <div class="section-header">
      <div><h2 class="section-title">Event Terbaru Saya</h2><div class="section-title-bar"></div></div>
      <a href="<?= BASE_URL ?>/panitia/daftar_event.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
    </div>

    <?php if (empty($recentEvts)): ?>
    <div class="empty-state">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <h3>Belum ada event</h3>
      <p>Buat event pertamamu sekarang!</p>
      <a href="<?= BASE_URL ?>/panitia/buat_event.php" class="btn btn-primary">Buat Event</a>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Judul Event</th>
            <th>Kategori</th>
            <th>Tanggal</th>
            <th>Kuota</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentEvts as $ev):
            $bc = getCategoryBadgeClass($ev['category_name']);
            $q  = getQuotaStatus((int)$ev['registered_count'], (int)$ev['quota']);
          ?>
          <tr>
            <td style="font-weight:600;max-width:220px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($ev['title']) ?></td>
            <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($ev['category_name']) ?></span></td>
            <td style="font-size:.8125rem"><?= formatDate($ev['date_start']) ?></td>
            <td>
              <div style="min-width:100px">
                <div style="font-size:.8125rem;font-weight:600"><?= $ev['registered_count'] ?>/<?= $ev['quota'] ?></div>
                <div class="quota-bar-track" style="height:3px;margin-top:4px">
                  <div class="quota-bar-fill <?= $q['status']==='full'?'full':($q['status']==='almost-full'?'almost-full':'') ?>" style="width:<?= $q['percent'] ?>%"></div>
                </div>
              </div>
            </td>
            <td><span class="status-badge status-<?= htmlspecialchars($ev['status']) ?>"><?= ucfirst($ev['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a href="<?= BASE_URL ?>/panitia/edit_event.php?id=<?= $ev['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
              <a href="<?= BASE_URL ?>/panitia/peserta.php?event_id=<?= $ev['id'] ?>" class="btn btn-outline btn-sm">Peserta</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
