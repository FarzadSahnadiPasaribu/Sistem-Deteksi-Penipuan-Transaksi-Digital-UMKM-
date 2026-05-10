<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');

$user = currentUser();
$db   = getDB();

$csrf = generateCsrfToken();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('Token tidak valid.', 'error');
        header('Location: ' . BASE_URL . '/admin/validasi_event.php');
        exit;
    }

    $eventId = (int)($_POST['event_id'] ?? 0);
    $action  = $_POST['action'] ?? '';

    $evStmt = $db->prepare("SELECT * FROM events WHERE id=?");
    $evStmt->execute([$eventId]);
    $ev = $evStmt->fetch();

    if (!$ev || $ev['status'] !== 'pending') {
        setFlash('Event tidak valid atau sudah divalidasi.', 'error');
        header('Location: ' . BASE_URL . '/admin/validasi_event.php');
        exit;
    }

    if ($action === 'approve') {
        $db->prepare("UPDATE events SET status='published', updated_at=NOW() WHERE id=?")->execute([$eventId]);

        // Notify organizer
        sendNotification($ev['organizer_id'], 'Event Disetujui!',
            "Event \"{$ev['title']}\" telah disetujui dan kini tampil di katalog.", 'validasi', $eventId);

        setFlash("Event \"{$ev['title']}\" berhasil dipublikasikan.", 'success');

    } elseif ($action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? '');
        if (empty($reason)) {
            setFlash('Masukkan alasan penolakan.', 'error');
            header('Location: ' . BASE_URL . '/admin/validasi_event.php');
            exit;
        }
        $db->prepare("UPDATE events SET status='rejected', rejection_reason=?, updated_at=NOW() WHERE id=?")
           ->execute([$reason, $eventId]);

        sendNotification($ev['organizer_id'], 'Event Ditolak',
            "Event \"{$ev['title']}\" ditolak. Alasan: $reason. Silakan revisi dan ajukan ulang.", 'validasi', $eventId);

        setFlash("Event \"{$ev['title']}\" ditolak.", 'info');
    }

    header('Location: ' . BASE_URL . '/admin/validasi_event.php');
    exit;
}

// Load pending events
$pendingEvents = $db->query("
    SELECT e.*, c.name as category_name, c.icon as category_icon,
           u.name as organizer_name, u.email as organizer_email,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status!='cancelled') as registered_count
    FROM events e
    JOIN categories c ON e.category_id=c.id
    JOIN users u ON e.organizer_id=u.id
    WHERE e.status='pending'
    ORDER BY e.created_at ASC
")->fetchAll();

define('PAGE_TITLE', 'Validasi Event');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="page-header">
      <h1 class="page-title">Validasi Event</h1>
      <p class="page-subtitle">
        <?= count($pendingEvents) ?> event menunggu persetujuan
      </p>
    </div>

    <?php if (empty($pendingEvents)): ?>
    <div class="empty-state">
      <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
      <h3>Semua sudah divalidasi!</h3>
      <p>Tidak ada event yang menunggu persetujuan saat ini.</p>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:var(--space-5)">
      <?php foreach ($pendingEvents as $ev):
        $bc = getCategoryBadgeClass($ev['category_name']);
      ?>
      <div class="card">
        <div style="display:grid;grid-template-columns:auto 1fr auto;gap:var(--space-5);align-items:start;padding:var(--space-5)" class="val-row">
          <!-- Poster thumb -->
          <div style="width:120px;flex-shrink:0">
            <?php if ($ev['poster']): ?>
            <img src="<?= BASE_URL ?>/uploads/posters/<?= htmlspecialchars($ev['poster']) ?>"
                 style="width:120px;aspect-ratio:16/9;object-fit:cover;border-radius:var(--radius-md)" alt="">
            <?php else: ?>
            <div style="width:120px;aspect-ratio:16/9;background:linear-gradient(135deg,var(--clr-brand),#2D3561);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:2rem">
              <?= htmlspecialchars($ev['category_icon']) ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Info -->
          <div style="min-width:0">
            <div style="display:flex;align-items:center;gap:var(--space-3);margin-bottom:var(--space-2);flex-wrap:wrap">
              <span class="badge <?= $bc ?>"><?= htmlspecialchars($ev['category_name']) ?></span>
              <span class="status-badge status-pending">Menunggu Validasi</span>
              <span style="font-size:.75rem;color:var(--clr-text-muted)">Diajukan <?= timeAgo($ev['created_at']) ?></span>
            </div>
            <h3 style="font-size:1.125rem;margin-bottom:var(--space-2)"><?= htmlspecialchars($ev['title']) ?></h3>
            <div style="display:flex;flex-wrap:wrap;gap:var(--space-4);font-size:.8125rem;color:var(--clr-text-muted);margin-bottom:var(--space-3)">
              <span style="display:flex;align-items:center;gap:4px">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= formatDate($ev['date_start']) ?>
              </span>
              <span style="display:flex;align-items:center;gap:4px">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= htmlspecialchars($ev['location']) ?>
              </span>
              <span style="display:flex;align-items:center;gap:4px">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?= htmlspecialchars($ev['organizer_name']) ?>
              </span>
              <span>Kuota: <?= $ev['quota'] ?></span>
            </div>
            <p style="font-size:.875rem;color:var(--clr-text-secondary);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden">
              <?= htmlspecialchars($ev['description']) ?>
            </p>
          </div>

          <!-- Actions -->
          <div style="display:flex;flex-direction:column;gap:var(--space-2);min-width:140px">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button type="submit" class="btn btn-success btn-sm btn-block"
                      data-confirm="Setujui dan publish event '<?= htmlspecialchars($ev['title']) ?>'?">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Setujui
              </button>
            </form>
            <button type="button" class="btn btn-danger btn-sm btn-block" data-modal-open="modal-reject-<?= $ev['id'] ?>">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Tolak
            </button>
          </div>
        </div>
      </div>

      <!-- Reject Modal -->
      <div id="modal-reject-<?= $ev['id'] ?>" class="modal-backdrop">
        <div class="modal-dialog">
          <div class="modal-header">
            <h3 class="modal-title">Tolak Event</h3>
            <button class="modal-close" data-modal-close>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <div class="modal-body">
              <div style="margin-bottom:var(--space-4)">
                <strong>Event:</strong> <?= htmlspecialchars($ev['title']) ?>
              </div>
              <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Alasan Penolakan *</label>
                <textarea name="rejection_reason" class="form-control" rows="4" required
                          placeholder="Jelaskan kenapa event ini ditolak dan apa yang perlu diperbaiki panitia..."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-ghost" data-modal-close>Batal</button>
              <button type="submit" class="btn btn-danger">Konfirmasi Tolak</button>
            </div>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>

<style>@media(max-width:767px){.val-row{grid-template-columns:1fr!important}}</style>
<?php include '../includes/footer.php'; ?>
