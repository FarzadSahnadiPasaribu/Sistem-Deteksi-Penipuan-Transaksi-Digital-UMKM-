<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('mahasiswa');

$user = currentUser();
$db   = getDB();

$filter = $_GET['status'] ?? 'all';
$whereStatus = $filter !== 'all' ? "AND r.status = '" . $db->quote($filter) . "'" : '';

// Safer approach:
$statusOptions = ['confirmed','attended','cancelled'];
$whereStatusClean = ($filter !== 'all' && in_array($filter, $statusOptions))
    ? "AND r.status = '$filter'"
    : '';

$stmt = $db->prepare("
    SELECT r.*, e.title, e.date_start, e.date_end, e.location, e.poster, e.status as event_status,
           c.name as category_name, c.icon as category_icon, u.name as organizer_name
    FROM registrations r
    JOIN events e ON r.event_id=e.id
    JOIN categories c ON e.category_id=c.id
    JOIN users u ON e.organizer_id=u.id
    WHERE r.user_id=? $whereStatusClean
    ORDER BY r.registered_at DESC
");
$stmt->execute([$user['id']]);
$myEvents = $stmt->fetchAll();

define('PAGE_TITLE', 'Event Saya');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="page-header">
      <h1 class="page-title">Event Saya</h1>
      <p class="page-subtitle">Daftar semua event yang pernah kamu daftarkan</p>
    </div>

    <!-- Filter Tabs -->
    <div style="display:flex;gap:var(--space-2);margin-bottom:var(--space-6);flex-wrap:wrap">
      <?php
      $tabs = ['all'=>'Semua','confirmed'=>'Terdaftar','attended'=>'Sudah Hadir','cancelled'=>'Dibatalkan'];
      foreach ($tabs as $val => $label):
      ?>
      <a href="?status=<?= $val ?>" class="btn <?= $filter === $val ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($myEvents)): ?>
    <div class="empty-state">
      <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>
      </svg>
      <h3>Belum ada event</h3>
      <p>Kamu belum mendaftarkan diri ke event apapun<?= $filter !== 'all' ? ' dengan status ini' : '' ?>.</p>
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn btn-primary">Jelajahi Event</a>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Event</th>
            <th>Kategori</th>
            <th>Tanggal</th>
            <th>Lokasi</th>
            <th>Kode Daftar</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($myEvents as $reg):
            $bc = getCategoryBadgeClass($reg['category_name']);
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:var(--space-3)">
                <div style="width:40px;height:40px;border-radius:var(--radius-sm);background:linear-gradient(135deg,var(--clr-brand),#2D3561);display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0">
                  <?= htmlspecialchars($reg['category_icon']) ?>
                </div>
                <div style="min-width:0">
                  <div style="font-weight:600;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px">
                    <?= htmlspecialchars($reg['title']) ?>
                  </div>
                  <div style="font-size:.75rem;color:var(--clr-text-muted)"><?= htmlspecialchars($reg['organizer_name']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($reg['category_name']) ?></span></td>
            <td style="font-size:.8125rem"><?= formatDate($reg['date_start']) ?></td>
            <td style="font-size:.8125rem;max-width:140px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars(truncate($reg['location'], 30)) ?></td>
            <td><span class="reg-code"><?= htmlspecialchars($reg['registration_code']) ?></span></td>
            <td><span class="status-badge status-<?= htmlspecialchars($reg['status']) ?>"><?= ucfirst($reg['status']) ?></span></td>
            <td>
              <a href="<?= BASE_URL ?>/mahasiswa/detail_event.php?id=<?= $reg['event_id'] ?>" class="btn btn-ghost btn-sm">
                Detail
              </a>
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
