<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('mahasiswa');

$user    = currentUser();
$db      = getDB();
$eventId = (int)($_GET['id'] ?? 0);

if (!$eventId) {
    header('Location: ' . BASE_URL . '/mahasiswa/katalog.php');
    exit;
}

// Load event
$stmt = $db->prepare("
    SELECT e.*, c.name as category_name, c.icon as category_icon, c.id as cat_id,
           u.name as organizer_name, u.email as organizer_email,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status!='cancelled') as registered_count
    FROM events e
    JOIN categories c ON e.category_id=c.id
    JOIN users u ON e.organizer_id=u.id
    WHERE e.id=? AND e.status='published'
");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: ' . BASE_URL . '/mahasiswa/katalog.php');
    exit;
}

// Increment view count
$db->prepare("UPDATE events SET view_count=view_count+1 WHERE id=?")->execute([$eventId]);

$quota    = getQuotaStatus((int)$event['registered_count'], (int)$event['quota']);
$isReg    = isEventRegistered($user['id'], $eventId);
$deadlinePast = strtotime($event['registration_deadline']) < time();
$bc       = getCategoryBadgeClass($event['category_name']);

// Registration exists data
$regData = null;
if ($isReg) {
    $rs = $db->prepare("SELECT * FROM registrations WHERE user_id=? AND event_id=?");
    $rs->execute([$user['id'], $eventId]);
    $regData = $rs->fetch();
}

// Handle POST registration
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid. Refresh dan coba lagi.';
    } elseif ($isReg) {
        $error = 'Kamu sudah terdaftar di event ini.';
    } elseif ($quota['status'] === 'full') {
        $error = 'Maaf, kuota event ini sudah penuh.';
    } elseif ($deadlinePast) {
        $error = 'Pendaftaran sudah ditutup.';
    } else {
        $code = generateRegistrationCode();
        $ins  = $db->prepare("INSERT INTO registrations (user_id, event_id, status, registration_code) VALUES (?,?,?,?)");
        $ins->execute([$user['id'], $eventId, 'confirmed', $code]);

        // Notification
        sendNotification($user['id'], 'Pendaftaran Berhasil!',
            "Kamu berhasil mendaftar ke \"{$event['title']}\". Kode: $code", 'pendaftaran', $eventId);

        setFlash("Berhasil mendaftar! Kode pendaftaran kamu: $code", 'success');
        header("Location: " . BASE_URL . "/mahasiswa/detail_event.php?id=$eventId");
        exit;
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid.';
    } elseif ($isReg) {
        $db->prepare("UPDATE registrations SET status='cancelled' WHERE user_id=? AND event_id=?")->execute([$user['id'], $eventId]);
        setFlash('Pendaftaran berhasil dibatalkan.', 'info');
        header("Location: " . BASE_URL . "/mahasiswa/detail_event.php?id=$eventId");
        exit;
    }
}

$csrf = generateCsrfToken();
define('PAGE_TITLE', htmlspecialchars($event['title']));
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php">Dashboard</a>
      <span class="breadcrumb-sep">›</span>
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php">Katalog Event</a>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current"><?= htmlspecialchars(truncate($event['title'], 40)) ?></span>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:var(--space-5)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="event-detail-layout">
      <!-- Left: Content -->
      <div>
        <!-- Poster -->
        <?php if ($event['poster']): ?>
        <img src="<?= BASE_URL ?>/uploads/posters/<?= htmlspecialchars($event['poster']) ?>"
             alt="Poster <?= htmlspecialchars($event['title']) ?>"
             style="width:100%;border-radius:var(--radius-lg);aspect-ratio:16/7;object-fit:cover;margin-bottom:var(--space-6)">
        <?php else: ?>
        <div style="width:100%;aspect-ratio:16/7;background:linear-gradient(135deg,var(--clr-brand) 0%,#2D3561 100%);border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;margin-bottom:var(--space-6);font-size:4rem">
          <?= htmlspecialchars($event['category_icon']) ?>
        </div>
        <?php endif; ?>

        <!-- Badge + Title -->
        <span class="badge <?= $bc ?>" style="margin-bottom:var(--space-4)"><?= htmlspecialchars($event['category_name']) ?></span>
        <h1 style="font-family:'DM Serif Display',serif;font-size:clamp(1.5rem,3vw,2.25rem);margin-bottom:var(--space-5);margin-top:var(--space-3)">
          <?= htmlspecialchars($event['title']) ?>
        </h1>

        <!-- Info Grid -->
        <div class="event-info-grid">
          <div class="event-info-item">
            <span class="event-info-label">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:4px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Tanggal Mulai
            </span>
            <span class="event-info-value"><?= formatDatetime($event['date_start']) ?></span>
          </div>
          <div class="event-info-item">
            <span class="event-info-label">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:4px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Tanggal Selesai
            </span>
            <span class="event-info-value"><?= formatDatetime($event['date_end']) ?></span>
          </div>
          <div class="event-info-item">
            <span class="event-info-label">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:4px"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              Lokasi
            </span>
            <span class="event-info-value"><?= htmlspecialchars($event['location']) ?></span>
          </div>
          <div class="event-info-item">
            <span class="event-info-label">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:4px"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Penyelenggara
            </span>
            <span class="event-info-value"><?= htmlspecialchars($event['organizer_name']) ?></span>
          </div>
        </div>

        <!-- Divider -->
        <div style="border-top:2px solid var(--clr-brand);width:40px;margin:var(--space-6) 0"></div>

        <!-- Description -->
        <h3 style="margin-bottom:var(--space-4)">Tentang Event</h3>
        <div style="color:var(--clr-text-secondary);line-height:1.8;font-size:.9375rem;white-space:pre-line">
          <?= nl2br(htmlspecialchars($event['description'])) ?>
        </div>

        <?php if ($isReg && $regData): ?>
        <div class="alert alert-success" style="margin-top:var(--space-8)">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>
          <div>
            <div style="font-weight:700">Kamu sudah terdaftar di event ini!</div>
            <div style="margin-top:4px;font-size:.875rem">
              Kode pendaftaran: <span class="reg-code"><?= htmlspecialchars($regData['registration_code']) ?></span>
              &nbsp;·&nbsp; Status: <span class="status-badge status-<?= $regData['status'] ?>"><?= ucfirst($regData['status']) ?></span>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right: Registration Card -->
      <div class="event-detail-sidebar">
        <div class="reg-card">
          <div class="reg-card-header">
            <h4>Detail Pendaftaran</h4>
          </div>
          <div class="reg-card-body">
            <div class="reg-stat-row">
              <span class="reg-stat-label">Kuota Total</span>
              <span class="reg-stat-value"><?= $event['quota'] ?> orang</span>
            </div>
            <div class="reg-stat-row">
              <span class="reg-stat-label">Sudah Daftar</span>
              <span class="reg-stat-value"><?= $event['registered_count'] ?> orang</span>
            </div>
            <div class="reg-stat-row">
              <span class="reg-stat-label">Sisa Kuota</span>
              <span class="reg-stat-value" style="color:<?= $quota['status']==='full' ? 'var(--clr-accent)' : ($quota['status']==='almost-full' ? 'var(--clr-gold)' : 'var(--clr-mint)') ?>">
                <?= $quota['remaining'] ?> tempat
              </span>
            </div>

            <div class="quota-bar" style="margin:var(--space-4) 0">
              <div class="quota-bar-track" style="height:6px">
                <div class="quota-bar-fill <?= $quota['status'] === 'full' ? 'full' : ($quota['status'] === 'almost-full' ? 'almost-full' : '') ?>"
                     style="width:<?= $quota['percent'] ?>%"></div>
              </div>
              <div class="quota-text"><span><?= $quota['percent'] ?>% terisi</span></div>
            </div>

            <div class="reg-stat-row">
              <span class="reg-stat-label">Deadline Daftar</span>
              <span class="reg-stat-value" style="font-size:.875rem"><?= formatDatetime($event['registration_deadline']) ?></span>
            </div>
          </div>

          <div class="reg-card-footer">
            <?php if ($isReg): ?>
              <div class="btn btn-success btn-block" style="cursor:default;margin-bottom:var(--space-3)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Sudah Terdaftar
              </div>
              <?php if ($regData && $regData['status'] === 'confirmed'): ?>
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-ghost btn-sm btn-block"
                        data-confirm="Yakin ingin membatalkan pendaftaran di event ini?"
                        style="color:var(--clr-accent)">
                  Batalkan Pendaftaran
                </button>
              </form>
              <?php endif; ?>

            <?php elseif ($quota['status'] === 'full'): ?>
              <div class="btn btn-block" style="background:rgba(26,26,46,.06);color:var(--clr-text-muted);cursor:not-allowed;border:1.5px solid var(--clr-border);border-radius:var(--radius-md);padding:var(--space-3) var(--space-5);text-align:center;font-weight:600">
                Kuota Penuh
              </div>

            <?php elseif ($deadlinePast): ?>
              <div class="btn btn-block" style="background:rgba(26,26,46,.06);color:var(--clr-text-muted);cursor:not-allowed;border:1.5px solid var(--clr-border);border-radius:var(--radius-md);padding:var(--space-3) var(--space-5);text-align:center;font-weight:600">
                Pendaftaran Ditutup
              </div>

            <?php else: ?>
              <button type="button" class="btn btn-primary btn-block btn-lg" data-modal-open="modal-register">
                Daftar Event Ini
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              </button>
            <?php endif; ?>
          </div>
        </div>

        <div style="margin-top:var(--space-4);padding:var(--space-4);background:var(--clr-bg-card);border:1px solid var(--clr-border);border-radius:var(--radius-md)">
          <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--clr-text-muted);margin-bottom:var(--space-2)">Total Dilihat</div>
          <div style="font-family:'DM Serif Display',serif;font-style:italic;font-size:1.5rem;color:var(--clr-brand)"><?= number_format($event['view_count']) ?></div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Registration Confirmation Modal -->
<?php if (!$isReg && $quota['status'] !== 'full' && !$deadlinePast): ?>
<div id="modal-register" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">Konfirmasi Pendaftaran</h3>
      <button class="modal-close" data-modal-close aria-label="Tutup">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:var(--space-5)">Kamu akan mendaftar ke event berikut:</p>
      <div style="background:rgba(26,26,46,.04);border:1px solid var(--clr-border);border-radius:var(--radius-md);padding:var(--space-4) var(--space-5)">
        <div style="font-weight:700;font-size:1rem;margin-bottom:var(--space-3)"><?= htmlspecialchars($event['title']) ?></div>
        <div style="display:flex;flex-direction:column;gap:var(--space-2)">
          <div style="font-size:.875rem;color:var(--clr-text-muted);display:flex;gap:var(--space-2);align-items:center">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?= formatDatetime($event['date_start']) ?>
          </div>
          <div style="font-size:.875rem;color:var(--clr-text-muted);display:flex;gap:var(--space-2);align-items:center">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= htmlspecialchars($event['location']) ?>
          </div>
          <div style="font-size:.875rem;color:var(--clr-text-muted);display:flex;gap:var(--space-2);align-items:center">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            Sisa kuota: <?= $quota['remaining'] ?> dari <?= $event['quota'] ?> tempat
          </div>
        </div>
      </div>
      <p style="margin-top:var(--space-4);font-size:.875rem;color:var(--clr-text-muted)">
        Kode pendaftaran unik akan dikirimkan ke notifikasi kamu setelah berhasil mendaftar.
      </p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" data-modal-close>Batal</button>
      <form method="POST" style="margin:0">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="register">
        <button type="submit" class="btn btn-primary">
          Ya, Daftar Sekarang
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        </button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
