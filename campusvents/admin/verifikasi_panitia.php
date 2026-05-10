<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');

$user = currentUser();
$db   = getDB();

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $targetId = (int)($_POST['user_id'] ?? 0);
    $action   = $_POST['action'] ?? '';
    $reason   = trim($_POST['reason'] ?? '');

    if ($targetId > 0 && in_array($action, ['approve','reject'])) {
        $target = $db->prepare("SELECT id, name, email, role, verified FROM users WHERE id=? AND role='panitia'");
        $target->execute([$targetId]);
        $panitia = $target->fetch();

        if ($panitia) {
            if ($action === 'approve') {
                $db->prepare("UPDATE users SET verified=1 WHERE id=?")->execute([$targetId]);
                $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)")
                   ->execute([$targetId, 'sistem', '✅ Akun Panitia Diverifikasi!',
                       'Selamat! Akun panitia kamu telah diverifikasi oleh admin. Kamu sekarang bisa membuat dan mempublikasikan event di CampusVents.']);
                setFlash("Akun panitia {$panitia['name']} berhasil diverifikasi.", 'success');
            } elseif ($action === 'reject') {
                $msg = $reason ?: 'Akun tidak memenuhi syarat sebagai panitia resmi.';
                $db->prepare("UPDATE users SET verified=0, is_active=0 WHERE id=?")->execute([$targetId]);
                $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)")
                   ->execute([$targetId, 'sistem', '❌ Pendaftaran Panitia Ditolak',
                       "Maaf, pendaftaran akun panitia kamu ditolak oleh admin. Alasan: $msg. Silakan hubungi admin untuk informasi lebih lanjut."]);
                setFlash("Pendaftaran panitia {$panitia['name']} ditolak.", 'error');
            }
        }
    }
    header('Location: ' . BASE_URL . '/admin/verifikasi_panitia.php');
    exit;
}

// Pending panitia list
$pending = $db->query("
    SELECT u.id, u.name, u.email, u.nim, u.prodi, u.created_at, u.is_active,
           COUNT(DISTINCT ui.category_id) as interest_count
    FROM users u
    LEFT JOIN user_interests ui ON ui.user_id=u.id
    WHERE u.role='panitia' AND u.verified=0 AND u.is_active=1
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

// Already verified
$verified = $db->query("
    SELECT u.id, u.name, u.email, u.nim, u.prodi, u.created_at,
           COUNT(DISTINCT e.id) as event_count
    FROM users u
    LEFT JOIN events e ON e.organizer_id=u.id
    WHERE u.role='panitia' AND u.verified=1
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT 20
")->fetchAll();

// Rejected
$rejected = $db->query("
    SELECT id, name, email, nim, prodi, created_at
    FROM users
    WHERE role='panitia' AND verified=0 AND is_active=0
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll();

$csrf = generateCsrfToken();
define('PAGE_TITLE', 'Verifikasi Panitia');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Verifikasi Panitia</h1>
        <p class="page-subtitle">Tinjau dan setujui pendaftaran akun panitia baru</p>
      </div>
      <?php if (count($pending) > 0): ?>
      <span class="badge" style="background:var(--clr-accent);color:white;font-size:.9rem;padding:.4em .9em;border-radius:999px">
        <?= count($pending) ?> Menunggu
      </span>
      <?php endif; ?>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>" style="margin-bottom:var(--space-6)">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Pending -->
    <div class="section-header"><div><h2 class="section-title">Menunggu Verifikasi</h2><div class="section-title-bar"></div></div></div>

    <?php if (empty($pending)): ?>
    <div class="empty-state" style="margin-bottom:var(--space-8)">
      <div class="empty-icon">✅</div>
      <p class="empty-text">Tidak ada pendaftaran panitia yang menunggu verifikasi.</p>
    </div>
    <?php else: ?>
    <div style="display:grid;gap:var(--space-4);margin-bottom:var(--space-8)">
      <?php foreach ($pending as $p): ?>
      <div class="card" style="padding:var(--space-5)">
        <div style="display:flex;align-items:flex-start;gap:var(--space-5);flex-wrap:wrap">
          <div style="width:52px;height:52px;border-radius:50%;background:var(--clr-brand);color:white;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:700;flex-shrink:0">
            <?= htmlspecialchars(getInitials($p['name'])) ?>
          </div>
          <div style="flex:1;min-width:200px">
            <div style="font-weight:700;font-size:1.05rem;color:var(--clr-text)"><?= htmlspecialchars($p['name']) ?></div>
            <div style="color:var(--clr-text-muted);font-size:.875rem;margin-top:2px"><?= htmlspecialchars($p['email']) ?></div>
            <div style="display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:var(--space-3)">
              <?php if ($p['nim']): ?>
              <span class="badge badge-outline">NIM: <?= htmlspecialchars($p['nim']) ?></span>
              <?php endif; ?>
              <?php if ($p['prodi']): ?>
              <span class="badge badge-outline"><?= htmlspecialchars($p['prodi']) ?></span>
              <?php endif; ?>
              <span class="badge badge-outline">Minat: <?= $p['interest_count'] ?> kategori</span>
              <span style="font-size:.8rem;color:var(--clr-text-muted)">Daftar: <?= date('d M Y H:i', strtotime($p['created_at'])) ?></span>
            </div>
          </div>
          <div style="display:flex;gap:var(--space-3);flex-shrink:0;align-items:flex-start">
            <!-- Approve -->
            <form method="POST" onsubmit="return confirm('Setujui akun panitia <?= htmlspecialchars(addslashes($p['name'])) ?>?')">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button type="submit" class="btn btn-primary btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Setujui
              </button>
            </form>
            <!-- Reject with reason -->
            <button type="button" class="btn btn-outline btn-sm" style="color:var(--clr-accent);border-color:var(--clr-accent)"
                    onclick="document.getElementById('reject-form-<?= $p['id'] ?>').style.display='block';this.style.display='none'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Tolak
            </button>
          </div>
        </div>
        <!-- Reject form (hidden) -->
        <form id="reject-form-<?= $p['id'] ?>" method="POST" style="display:none;margin-top:var(--space-4);border-top:1px solid var(--clr-border);padding-top:var(--space-4)">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
          <input type="hidden" name="action" value="reject">
          <div class="form-group" style="margin-bottom:var(--space-3)">
            <label class="form-label">Alasan Penolakan (opsional)</label>
            <input type="text" name="reason" class="form-control" placeholder="Contoh: Data tidak lengkap, bukan mahasiswa aktif, dll.">
          </div>
          <div style="display:flex;gap:var(--space-3)">
            <button type="submit" class="btn btn-sm" style="background:var(--clr-accent);color:white">Konfirmasi Tolak</button>
            <button type="button" class="btn btn-outline btn-sm"
                    onclick="this.closest('form').style.display='none';this.closest('.card').querySelector('[onclick*=reject-form]').style.display=''">
              Batal
            </button>
          </div>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Verified -->
    <div class="section-header" style="margin-top:0"><div><h2 class="section-title">Sudah Terverifikasi (20 Terbaru)</h2><div class="section-title-bar"></div></div></div>
    <div class="table-wrapper" style="margin-bottom:var(--space-8)">
      <table class="table">
        <thead><tr><th>Nama</th><th>Email</th><th>Prodi</th><th>Event Dibuat</th><th>Bergabung</th></tr></thead>
        <tbody>
          <?php foreach ($verified as $v): ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars($v['name']) ?></td>
            <td style="font-size:.875rem;color:var(--clr-text-muted)"><?= htmlspecialchars($v['email']) ?></td>
            <td><?= htmlspecialchars($v['prodi'] ?? '-') ?></td>
            <td><strong><?= $v['event_count'] ?></strong> event</td>
            <td style="font-size:.8rem;color:var(--clr-text-muted)"><?= date('d M Y', strtotime($v['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($verified)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--clr-text-muted)">Belum ada panitia terverifikasi</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Rejected -->
    <?php if (!empty($rejected)): ?>
    <div class="section-header" style="margin-top:0"><div><h2 class="section-title">Ditolak</h2><div class="section-title-bar"></div></div></div>
    <div class="table-wrapper">
      <table class="table">
        <thead><tr><th>Nama</th><th>Email</th><th>Prodi</th><th>Tanggal Daftar</th></tr></thead>
        <tbody>
          <?php foreach ($rejected as $rj): ?>
          <tr>
            <td style="font-weight:600;color:var(--clr-text-muted)"><?= htmlspecialchars($rj['name']) ?></td>
            <td style="font-size:.875rem;color:var(--clr-text-muted)"><?= htmlspecialchars($rj['email']) ?></td>
            <td><?= htmlspecialchars($rj['prodi'] ?? '-') ?></td>
            <td style="font-size:.8rem;color:var(--clr-text-muted)"><?= date('d M Y', strtotime($rj['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </main>
</div>

<?php include '../includes/footer.php'; ?>
