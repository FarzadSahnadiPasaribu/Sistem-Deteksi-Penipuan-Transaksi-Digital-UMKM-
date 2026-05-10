<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('panitia');

$user = currentUser();
$db   = getDB();

$eventId = (int)($_GET['event_id'] ?? 0);

// My published events
$myEventsStmt = $db->prepare("SELECT id, title FROM events WHERE organizer_id=? AND status='published' ORDER BY date_start DESC");
$myEventsStmt->execute([$user['id']]);
$myEvents = $myEventsStmt->fetchAll();

$event = null;
if ($eventId) {
    $evStmt = $db->prepare("SELECT * FROM events WHERE id=? AND organizer_id=?");
    $evStmt->execute([$eventId, $user['id']]);
    $event = $evStmt->fetch();
}

$error   = '';
$success = '';
$csrf    = generateCsrfToken();

// Mark attendance by registration code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $event) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid.';
    } else {
        $code = trim(strtoupper($_POST['reg_code'] ?? ''));
        if (empty($code)) {
            $error = 'Masukkan kode pendaftaran.';
        } else {
            $rs = $db->prepare("SELECT r.*, u.name FROM registrations r JOIN users u ON r.user_id=u.id WHERE r.registration_code=? AND r.event_id=?");
            $rs->execute([$code, $eventId]);
            $reg = $rs->fetch();

            if (!$reg) {
                $error = "Kode \"$code\" tidak ditemukan untuk event ini.";
            } elseif ($reg['status'] === 'attended') {
                $error = htmlspecialchars($reg['name']) . ' sudah dicatat hadir sebelumnya.';
            } elseif ($reg['status'] === 'cancelled') {
                $error = htmlspecialchars($reg['name']) . ' telah membatalkan pendaftarannya.';
            } else {
                $db->prepare("UPDATE registrations SET status='attended', attended_at=NOW() WHERE id=?")->execute([$reg['id']]);
                $success = htmlspecialchars($reg['name']) . ' berhasil dicatat hadir!';
            }
        }
    }
}

// Registrations list
$registrations = [];
if ($event) {
    $regStmt = $db->prepare("
        SELECT r.*, u.name, u.nim
        FROM registrations r
        JOIN users u ON r.user_id=u.id
        WHERE r.event_id=?
        ORDER BY r.status='attended' DESC, r.registered_at ASC
    ");
    $regStmt->execute([$eventId]);
    $registrations = $regStmt->fetchAll();
}

define('PAGE_TITLE', 'Absensi Kehadiran');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="page-header">
      <h1 class="page-title">Absensi Kehadiran</h1>
      <p class="page-subtitle">Scan kode pendaftaran peserta untuk mencatat kehadiran</p>
    </div>

    <!-- Event Selector -->
    <div class="filter-bar" style="margin-bottom:var(--space-6)">
      <select class="form-control form-select" onchange="window.location.href='<?= BASE_URL ?>/panitia/kehadiran.php?event_id='+this.value" style="max-width:400px">
        <option value="">-- Pilih Event --</option>
        <?php foreach ($myEvents as $ev): ?>
        <option value="<?= $ev['id'] ?>" <?= $eventId == $ev['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($ev['title']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if ($event): ?>
    <div style="display:grid;grid-template-columns:400px 1fr;gap:var(--space-6);align-items:start" class="attend-layout">
      <!-- Input Form -->
      <div>
        <div class="card">
          <div class="card-body">
            <h4 style="margin-bottom:var(--space-5)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:8px"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
              Catat Kehadiran
            </h4>
            <p style="font-size:.875rem;color:var(--clr-text-muted);margin-bottom:var(--space-4)">
              Masukkan kode pendaftaran peserta (contoh: <span class="reg-code">CV-20260108-AB3F2</span>)
            </p>

            <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:var(--space-4)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom:var(--space-4)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>
              <?= $success ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="?event_id=<?= $eventId ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <div class="form-group">
                <label class="form-label">Kode Pendaftaran</label>
                <input type="text" name="reg_code" class="form-control" required
                       placeholder="CV-YYYYMMDD-XXXXX"
                       style="font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:.05em"
                       autofocus autocomplete="off">
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/></svg>
                Catat Hadir
              </button>
            </form>
          </div>
        </div>

        <?php if (!empty($registrations)):
          $hadirCount = count(array_filter($registrations, fn($r) => $r['status'] === 'attended'));
          $total      = count(array_filter($registrations, fn($r) => $r['status'] !== 'cancelled'));
        ?>
        <div class="card" style="margin-top:var(--space-4)">
          <div class="card-body">
            <h5 style="margin-bottom:var(--space-3)">Ringkasan Kehadiran</h5>
            <div class="quota-bar">
              <div class="quota-bar-track" style="height:8px">
                <div class="quota-bar-fill" style="width:<?= $total > 0 ? round(($hadirCount/$total)*100) : 0 ?>%"></div>
              </div>
              <div class="quota-text" style="margin-top:var(--space-2)">
                <span style="font-weight:600;color:var(--clr-mint)"><?= $hadirCount ?> hadir</span>
                <span style="color:var(--clr-text-muted)"><?= $total ?> terdaftar</span>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Registrations List -->
      <div>
        <h3 style="margin-bottom:var(--space-4)"><?= htmlspecialchars(truncate($event['title'], 50)) ?></h3>
        <?php if (empty($registrations)): ?>
        <div class="empty-state" style="padding:var(--space-10)">
          <p>Belum ada peserta terdaftar.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Peserta</th>
                <th>NIM</th>
                <th>Kode</th>
                <th>Status</th>
                <th>Waktu Hadir</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($registrations as $reg): ?>
              <tr style="<?= $reg['status']==='attended' ? 'background:rgba(0,201,167,.04)' : '' ?>">
                <td>
                  <div style="display:flex;align-items:center;gap:var(--space-2)">
                    <div style="width:28px;height:28px;border-radius:50%;background:<?= $reg['status']==='attended' ? 'var(--clr-mint)' : 'var(--clr-brand)' ?>;color:white;display:flex;align-items:center;justify-content:center;font-size:.625rem;font-weight:700;flex-shrink:0">
                      <?= htmlspecialchars(getInitials($reg['name'])) ?>
                    </div>
                    <span style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($reg['name']) ?></span>
                  </div>
                </td>
                <td style="font-size:.8125rem"><?= htmlspecialchars($reg['nim'] ?? '-') ?></td>
                <td><span class="reg-code" style="font-size:.75rem"><?= htmlspecialchars($reg['registration_code']) ?></span></td>
                <td><span class="status-badge status-<?= htmlspecialchars($reg['status']) ?>"><?= ucfirst($reg['status']) ?></span></td>
                <td style="font-size:.8125rem;color:var(--clr-text-muted)">
                  <?= $reg['attended_at'] ? timeAgo($reg['attended_at']) : '-' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      <h3>Pilih event terlebih dahulu</h3>
      <p>Pilih event dari dropdown di atas untuk mulai mencatat kehadiran.</p>
    </div>
    <?php endif; ?>
  </main>
</div>

<style>@media(max-width:1023px){.attend-layout{grid-template-columns:1fr!important}}</style>
<?php include '../includes/footer.php'; ?>
