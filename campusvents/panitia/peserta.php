<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('panitia');

$user    = currentUser();
$db      = getDB();
$eventId = (int)($_GET['event_id'] ?? 0);

// Load events by this panitia for dropdown
$myEventsStmt = $db->prepare("SELECT id, title FROM events WHERE organizer_id=? AND status='published' ORDER BY date_start DESC");
$myEventsStmt->execute([$user['id']]);
$myEvents = $myEventsStmt->fetchAll();

$event = null;
if ($eventId) {
    $evStmt = $db->prepare("SELECT * FROM events WHERE id=? AND organizer_id=?");
    $evStmt->execute([$eventId, $user['id']]);
    $event = $evStmt->fetch();
}

$registrations = [];
if ($event) {
    $regStmt = $db->prepare("
        SELECT r.*, u.name, u.email, u.nim, u.prodi
        FROM registrations r
        JOIN users u ON r.user_id=u.id
        WHERE r.event_id=?
        ORDER BY r.registered_at ASC
    ");
    $regStmt->execute([$eventId]);
    $registrations = $regStmt->fetchAll();
}

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Data Peserta');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="page-header">
      <h1 class="page-title">Data Peserta</h1>
      <p class="page-subtitle">Daftar pendaftar event kamu</p>
    </div>

    <!-- Event Selector -->
    <div class="filter-bar" style="margin-bottom:var(--space-6)">
      <select class="form-control form-select" id="event-select" onchange="window.location.href='/campusvents/panitia/peserta.php?event_id='+this.value" style="max-width:400px">
        <option value="">-- Pilih Event --</option>
        <?php foreach ($myEvents as $ev): ?>
        <option value="<?= $ev['id'] ?>" <?= $eventId == $ev['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($ev['title']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if (!$event): ?>
    <div class="empty-state">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
      </svg>
      <h3>Pilih event terlebih dahulu</h3>
      <p>Pilih salah satu event dari dropdown di atas untuk melihat daftar peserta.</p>
    </div>

    <?php elseif (empty($registrations)): ?>
    <div class="empty-state">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
      </svg>
      <h3>Belum ada peserta</h3>
      <p>Belum ada yang mendaftar ke event ini.</p>
    </div>

    <?php else: ?>
    <?php
      $quota  = getQuotaStatus(count($registrations), (int)$event['quota']);
      $confirmed = count(array_filter($registrations, fn($r) => $r['status'] === 'confirmed'));
      $attended  = count(array_filter($registrations, fn($r) => $r['status'] === 'attended'));
      $cancelled = count(array_filter($registrations, fn($r) => $r['status'] === 'cancelled'));
    ?>

    <!-- Summary -->
    <div class="stats-grid" style="margin-bottom:var(--space-6)">
      <div class="stat-card">
        <div class="stat-icon stat-icon-brand"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
        <div class="stat-info"><div class="stat-number"><?= count($registrations) ?></div><div class="stat-label">Total Pendaftar</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-mint"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></div>
        <div class="stat-info"><div class="stat-number"><?= $attended ?></div><div class="stat-label">Sudah Hadir</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-gold"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/></svg></div>
        <div class="stat-info"><div class="stat-number"><?= $confirmed ?></div><div class="stat-label">Terkonfirmasi</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-accent"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
        <div class="stat-info"><div class="stat-number"><?= $cancelled ?></div><div class="stat-label">Dibatalkan</div></div>
      </div>
    </div>

    <div style="margin-bottom:var(--space-5)">
      <h3 style="margin-bottom:var(--space-2)"><?= htmlspecialchars($event['title']) ?></h3>
      <div class="quota-bar">
        <div class="quota-bar-track" style="height:6px">
          <div class="quota-bar-fill <?= $quota['status']==='full'?'full':($quota['status']==='almost-full'?'almost-full':'') ?>" style="width:<?= $quota['percent'] ?>%"></div>
        </div>
        <div class="quota-text"><span><?= $quota['remaining'] ?> sisa dari <?= $event['quota'] ?> kuota</span><span><?= $quota['percent'] ?>% terisi</span></div>
      </div>
    </div>

    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama Peserta</th>
            <th>Email</th>
            <th>NIM</th>
            <th>Prodi</th>
            <th>Kode Daftar</th>
            <th>Status</th>
            <th>Waktu Daftar</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($registrations as $i => $reg): ?>
          <tr>
            <td style="color:var(--clr-text-muted)"><?= $i + 1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:var(--space-2)">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--clr-accent);color:white;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0">
                  <?= htmlspecialchars(getInitials($reg['name'])) ?>
                </div>
                <span style="font-weight:600"><?= htmlspecialchars($reg['name']) ?></span>
              </div>
            </td>
            <td style="font-size:.8125rem"><?= htmlspecialchars($reg['email']) ?></td>
            <td style="font-size:.8125rem"><?= htmlspecialchars($reg['nim'] ?? '-') ?></td>
            <td style="font-size:.8125rem;max-width:140px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($reg['prodi'] ?? '-') ?></td>
            <td><span class="reg-code"><?= htmlspecialchars($reg['registration_code']) ?></span></td>
            <td><span class="status-badge status-<?= htmlspecialchars($reg['status']) ?>"><?= ucfirst($reg['status']) ?></span></td>
            <td style="font-size:.8125rem;color:var(--clr-text-muted)"><?= timeAgo($reg['registered_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
