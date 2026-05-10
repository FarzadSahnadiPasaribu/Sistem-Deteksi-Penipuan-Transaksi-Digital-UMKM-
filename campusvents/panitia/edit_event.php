<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('panitia');

$user    = currentUser();
$db      = getDB();
$eventId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM events WHERE id=? AND organizer_id=?");
$stmt->execute([$eventId, $user['id']]);
$event = $stmt->fetch();

if (!$event) {
    setFlash('Event tidak ditemukan.', 'error');
    header('Location: ' . BASE_URL . '/panitia/daftar_event.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid.';
    } else {
        $title    = trim($_POST['title']       ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $catId    = (int)($_POST['category_id'] ?? 0);
        $dateS    = trim($_POST['date_start']   ?? '');
        $dateE    = trim($_POST['date_end']     ?? '');
        $location = trim($_POST['location']     ?? '');
        $quota    = max(1, (int)($_POST['quota'] ?? 100));
        $deadline = trim($_POST['registration_deadline'] ?? '');
        $submit   = $_POST['submit_type'] ?? 'save';

        if (empty($title) || empty($desc) || !$catId || empty($dateS) || empty($dateE) || empty($location) || empty($deadline)) {
            $error = 'Semua field wajib diisi.';
        } elseif (strtotime($dateE) < strtotime($dateS)) {
            $error = 'Tanggal selesai harus setelah tanggal mulai.';
        } elseif (strtotime($deadline) > strtotime($dateS)) {
            $error = 'Deadline pendaftaran harus sebelum tanggal mulai event.';
        } else {
            $newStatus = $event['status'];
            if ($submit === 'resubmit' && in_array($event['status'], ['draft','rejected'])) {
                $newStatus = 'pending';
            }

            $up = $db->prepare("
                UPDATE events SET title=?, description=?, category_id=?, date_start=?, date_end=?,
                location=?, quota=?, registration_deadline=?, status=?, rejection_reason=NULL, updated_at=NOW()
                WHERE id=?
            ");
            $up->execute([$title, $desc, $catId, $dateS, $dateE, $location, $quota, $deadline, $newStatus, $eventId]);

            if (!empty($_FILES['poster']['name']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
                $filename = uploadPoster($_FILES['poster'], $eventId);
                if ($filename) {
                    if ($event['poster']) {
                        @unlink(__DIR__ . '/../uploads/posters/' . $event['poster']);
                    }
                    $db->prepare("UPDATE events SET poster=? WHERE id=?")->execute([$filename, $eventId]);
                }
            }

            $msg = $newStatus === 'pending' ? 'Event diajukan ulang untuk validasi.' : 'Event berhasil diperbarui.';
            setFlash($msg, 'success');
            header('Location: ' . BASE_URL . '/panitia/daftar_event.php');
            exit;
        }
    }
}

$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$csrf       = generateCsrfToken();

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Edit Event');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="breadcrumb">
      <a href="<?= BASE_URL ?>/panitia/dashboard.php">Dashboard</a>
      <span class="breadcrumb-sep">›</span>
      <a href="<?= BASE_URL ?>/panitia/daftar_event.php">Daftar Event</a>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">Edit Event</span>
    </div>

    <div class="actions-bar">
      <div>
        <h1 class="page-title">Edit Event</h1>
        <p class="page-subtitle"><?= htmlspecialchars(truncate($event['title'], 60)) ?></p>
      </div>
      <span class="status-badge status-<?= htmlspecialchars($event['status']) ?>" style="font-size:.875rem;padding:6px 14px"><?= ucfirst($event['status']) ?></span>
    </div>

    <?php if ($event['rejection_reason']): ?>
    <div class="alert alert-error" style="margin-bottom:var(--space-5)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div>
        <strong>Event Ditolak Admin</strong>
        <div style="margin-top:4px;font-size:.875rem"><?= htmlspecialchars($event['rejection_reason']) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:var(--space-5)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" data-validate novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="submit_type" id="submit_type_edit" value="save">

      <div class="card" style="margin-bottom:var(--space-5)">
        <div class="card-body">
          <h4 style="margin-bottom:var(--space-5)">Informasi Dasar</h4>

          <div class="form-group">
            <label class="form-label">Judul Event *</label>
            <input type="text" name="title" class="form-control" required
                   value="<?= htmlspecialchars($_POST['title'] ?? $event['title']) ?>">
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Kategori *</label>
              <select name="category_id" class="form-control form-select" required>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($event['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Kuota *</label>
              <input type="number" name="quota" class="form-control" required min="1"
                     value="<?= htmlspecialchars($_POST['quota'] ?? $event['quota']) ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Lokasi *</label>
            <input type="text" name="location" class="form-control" required
                   value="<?= htmlspecialchars($_POST['location'] ?? $event['location']) ?>">
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Tanggal Mulai *</label>
              <input type="datetime-local" name="date_start" class="form-control" required
                     value="<?= htmlspecialchars($_POST['date_start'] ?? date('Y-m-d\TH:i', strtotime($event['date_start']))) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Tanggal Selesai *</label>
              <input type="datetime-local" name="date_end" class="form-control" required
                     value="<?= htmlspecialchars($_POST['date_end'] ?? date('Y-m-d\TH:i', strtotime($event['date_end']))) ?>">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Deadline Pendaftaran *</label>
            <input type="datetime-local" name="registration_deadline" class="form-control" required
                   value="<?= htmlspecialchars($_POST['registration_deadline'] ?? date('Y-m-d\TH:i', strtotime($event['registration_deadline']))) ?>">
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:var(--space-5)">
        <div class="card-body">
          <h4 style="margin-bottom:var(--space-4)">Deskripsi</h4>
          <textarea name="description" class="form-control" rows="8" required><?= htmlspecialchars($_POST['description'] ?? $event['description']) ?></textarea>
        </div>
      </div>

      <div class="card" style="margin-bottom:var(--space-6)">
        <div class="card-body">
          <h4 style="margin-bottom:var(--space-4)">Poster</h4>
          <?php if ($event['poster']): ?>
          <img src="<?= BASE_URL ?>/uploads/posters/<?= htmlspecialchars($event['poster']) ?>"
               style="max-height:180px;border-radius:var(--radius-md);object-fit:cover;margin-bottom:var(--space-4)">
          <?php endif; ?>
          <img id="poster-preview" src="" alt="" style="display:none;max-height:180px;border-radius:var(--radius-md);margin-bottom:var(--space-4)">
          <label class="btn btn-outline btn-sm" style="cursor:pointer">
            <?= $event['poster'] ? 'Ganti Poster' : 'Upload Poster' ?>
            <input type="file" id="poster-input" name="poster" accept="image/*" style="display:none">
          </label>
          <span class="form-hint" style="display:block;margin-top:var(--space-2)">JPG, PNG, WebP · Maks. 2MB</span>
        </div>
      </div>

      <div style="display:flex;gap:var(--space-3);flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/panitia/daftar_event.php" class="btn btn-ghost">Batal</a>
        <button type="submit" class="btn btn-outline" onclick="document.getElementById('submit_type_edit').value='save'">
          Simpan Perubahan
        </button>
        <?php if (in_array($event['status'], ['draft','rejected'])): ?>
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('submit_type_edit').value='resubmit'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Ajukan ke Admin
        </button>
        <?php endif; ?>
      </div>
    </form>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
