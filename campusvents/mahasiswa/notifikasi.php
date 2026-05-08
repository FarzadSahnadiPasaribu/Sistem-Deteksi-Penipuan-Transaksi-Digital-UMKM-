<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('mahasiswa');

$user = currentUser();
$db   = getDB();

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'read_all') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
        setFlash('Semua notifikasi ditandai sudah dibaca.', 'success');
        header('Location: /campusvents/mahasiswa/notifikasi.php');
        exit;
    }
}

// Mark single as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['read'], $user['id']]);
}

$stmt = $db->prepare("
    SELECT n.*, e.title as event_title
    FROM notifications n
    LEFT JOIN events e ON n.event_id=e.id
    WHERE n.user_id=?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->execute([$user['id']]);
$notifs = $stmt->fetchAll();

$unreadCount = getUnreadNotificationCount($user['id']);
$csrf        = generateCsrfToken();

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Notifikasi');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:var(--space-4)">
      <div>
        <h1 class="page-title">Notifikasi</h1>
        <p class="page-subtitle">
          <?= $unreadCount > 0 ? "$unreadCount notifikasi belum dibaca" : 'Semua notifikasi sudah dibaca' ?>
        </p>
      </div>
      <?php if ($unreadCount > 0): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="read_all">
        <button type="submit" class="btn btn-outline btn-sm">Tandai Semua Dibaca</button>
      </form>
      <?php endif; ?>
    </div>

    <?php if (empty($notifs)): ?>
    <div class="empty-state">
      <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 01-3.46 0"/>
      </svg>
      <h3>Belum ada notifikasi</h3>
      <p>Notifikasi event, pendaftaran, dan informasi penting akan muncul di sini.</p>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:var(--space-3)">
      <?php foreach ($notifs as $n):
        $typeIcons = [
          'event_baru'  => ['icon' => '🔔', 'color' => 'var(--clr-accent)'],
          'pendaftaran' => ['icon' => '✅', 'color' => 'var(--clr-mint)'],
          'validasi'    => ['icon' => '📋', 'color' => 'var(--clr-gold)'],
          'pengingat'   => ['icon' => '⏰', 'color' => '#4A90E2'],
          'sistem'      => ['icon' => 'ℹ️', 'color' => 'var(--clr-brand)'],
        ];
        $ti = $typeIcons[$n['type']] ?? $typeIcons['sistem'];
      ?>
      <div class="card" style="<?= !$n['is_read'] ? 'border-left:3px solid var(--clr-accent)' : '' ?>">
        <div class="card-body" style="display:flex;align-items:flex-start;gap:var(--space-4)">
          <div style="width:40px;height:40px;border-radius:50%;background:rgba(26,26,46,.06);display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0">
            <?= $ti['icon'] ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-3);flex-wrap:wrap">
              <h4 style="font-size:.9375rem;font-weight:700">
                <?= htmlspecialchars($n['title']) ?>
                <?php if (!$n['is_read']): ?>
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--clr-accent);margin-left:6px;vertical-align:middle"></span>
                <?php endif; ?>
              </h4>
              <span class="tiny text-muted"><?= timeAgo($n['created_at']) ?></span>
            </div>
            <p style="font-size:.875rem;margin-top:4px;color:var(--clr-text-secondary)">
              <?= htmlspecialchars($n['message']) ?>
            </p>
            <?php if ($n['event_id'] && $n['event_title']): ?>
            <a href="/campusvents/mahasiswa/detail_event.php?id=<?= $n['event_id'] ?>&read=<?= $n['id'] ?>"
               class="btn btn-ghost btn-sm" style="margin-top:var(--space-2);padding-left:0">
              Lihat Event: <?= htmlspecialchars(truncate($n['event_title'], 40)) ?>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
