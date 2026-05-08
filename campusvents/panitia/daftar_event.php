<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('panitia');

$user  = currentUser();
$db    = getDB();

$status = $_GET['status'] ?? 'all';
$validStatuses = ['draft','pending','published','rejected','cancelled'];
$where = "e.organizer_id = ?";
$params = [$user['id']];
if ($status !== 'all' && in_array($status, $validStatuses)) {
    $where .= " AND e.status = '$status'";
}

$stmt = $db->prepare("
    SELECT e.*, c.name as category_name,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status!='cancelled') as registered_count
    FROM events e
    JOIN categories c ON e.category_id=c.id
    WHERE $where
    ORDER BY e.created_at DESC
");
$stmt->execute($params);
$events = $stmt->fetchAll();

// Delete draft
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('Token tidak valid.', 'error');
    } else {
        $delId = (int)($_POST['event_id'] ?? 0);
        $check = $db->prepare("SELECT id, status, poster FROM events WHERE id=? AND organizer_id=?");
        $check->execute([$delId, $user['id']]);
        $ev = $check->fetch();
        if ($ev && $ev['status'] === 'draft') {
            if ($ev['poster']) {
                @unlink(__DIR__ . '/../uploads/posters/' . $ev['poster']);
            }
            $db->prepare("DELETE FROM events WHERE id=?")->execute([$delId]);
            setFlash('Event draft berhasil dihapus.', 'success');
        } else {
            setFlash('Hanya event berstatus draft yang bisa dihapus.', 'error');
        }
    }
    header('Location: /campusvents/panitia/daftar_event.php');
    exit;
}

$csrf = generateCsrfToken();
define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Daftar Event Saya');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="actions-bar">
      <div>
        <h1 class="page-title">Event Saya</h1>
        <p class="page-subtitle">Kelola semua event yang kamu buat</p>
      </div>
      <a href="/campusvents/panitia/buat_event.php" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Buat Event Baru
      </a>
    </div>

    <!-- Filter Tabs -->
    <div style="display:flex;gap:var(--space-2);margin-bottom:var(--space-6);flex-wrap:wrap">
      <?php
      $tabs = ['all'=>'Semua','published'=>'Published','pending'=>'Pending','draft'=>'Draft','rejected'=>'Ditolak'];
      foreach ($tabs as $val => $label):
      ?>
      <a href="?status=<?= $val ?>" class="btn <?= $status === $val ? 'btn-primary' : 'btn-outline' ?> btn-sm"><?= $label ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($events)): ?>
    <div class="empty-state">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <h3>Tidak ada event</h3>
      <p>Belum ada event dengan status ini.</p>
      <a href="/campusvents/panitia/buat_event.php" class="btn btn-primary">Buat Event</a>
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
            <th>Dibuat</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $ev):
            $q = getQuotaStatus((int)$ev['registered_count'], (int)$ev['quota']);
            $bc = getCategoryBadgeClass($ev['category_name']);
          ?>
          <tr>
            <td>
              <div style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($ev['title']) ?></div>
              <?php if ($ev['rejection_reason']): ?>
              <div style="font-size:.75rem;color:var(--clr-accent);margin-top:2px">Alasan: <?= htmlspecialchars(truncate($ev['rejection_reason'], 60)) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($ev['category_name']) ?></span></td>
            <td style="font-size:.8125rem"><?= formatDate($ev['date_start']) ?></td>
            <td>
              <div style="font-size:.8125rem;font-weight:600"><?= $ev['registered_count'] ?>/<?= $ev['quota'] ?></div>
              <div class="quota-bar-track" style="height:3px;margin-top:4px;width:80px">
                <div class="quota-bar-fill <?= $q['status']==='full'?'full':($q['status']==='almost-full'?'almost-full':'') ?>" style="width:<?= $q['percent'] ?>%"></div>
              </div>
            </td>
            <td><span class="status-badge status-<?= htmlspecialchars($ev['status']) ?>"><?= ucfirst($ev['status']) ?></span></td>
            <td style="font-size:.8125rem;color:var(--clr-text-muted)"><?= timeAgo($ev['created_at']) ?></td>
            <td style="white-space:nowrap">
              <a href="/campusvents/panitia/edit_event.php?id=<?= $ev['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
              <a href="/campusvents/panitia/peserta.php?event_id=<?= $ev['id'] ?>" class="btn btn-outline btn-sm">Peserta</a>
              <?php if ($ev['status'] === 'draft'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="Yakin hapus draft event ini? Tindakan ini tidak bisa dibatalkan.">Hapus</button>
              </form>
              <?php endif; ?>
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
