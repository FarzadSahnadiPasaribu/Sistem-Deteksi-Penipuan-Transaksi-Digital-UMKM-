<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('panitia');

$user  = currentUser();
$db    = getDB();
$error = '';

// Blokir panitia yang belum terverifikasi
$vRow = $db->prepare("SELECT verified, is_active FROM users WHERE id=?");
$vRow->execute([$user['id']]);
$vData = $vRow->fetch();
if (!($vData['verified'] ?? 0) || !($vData['is_active'] ?? 0)) {
    setFlash('Akun kamu belum diverifikasi admin. Kamu belum bisa membuat event.', 'error');
    header('Location: ' . BASE_URL . '/panitia/dashboard.php');
    exit;
}

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
        $submit   = $_POST['submit_type'] ?? 'draft'; // 'draft' or 'submit'

        if (empty($title) || empty($desc) || !$catId || empty($dateS) || empty($dateE) || empty($location) || empty($deadline)) {
            $error = 'Semua field wajib diisi.';
        } elseif (strtotime($dateS) === false || strtotime($dateE) === false) {
            $error = 'Format tanggal tidak valid.';
        } elseif (strtotime($dateE) < strtotime($dateS)) {
            $error = 'Tanggal selesai harus setelah tanggal mulai.';
        } elseif (strtotime($deadline) >= strtotime($dateE)) {
            $error = 'Deadline pendaftaran harus sebelum tanggal selesai event.';
        } else {
            $status = $submit === 'publish' ? 'pending' : 'draft';
            $ins = $db->prepare("
                INSERT INTO events (title, description, category_id, organizer_id, date_start, date_end, location, quota, registration_deadline, status)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $ins->execute([$title, $desc, $catId, $user['id'], $dateS, $dateE, $location, $quota, $deadline, $status]);
            $newId = (int)$db->lastInsertId();

            // Upload poster
            if (!empty($_FILES['poster']['name']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
                $filename = uploadPoster($_FILES['poster'], $newId);
                if ($filename) {
                    $db->prepare("UPDATE events SET poster=? WHERE id=?")->execute([$filename, $newId]);
                }
            }

            // Notify interested users if submitted for publishing
            if ($status === 'pending') {
                sendEventNotificationToInterestedUsers($newId, $catId, $title);
            }

            $msg = $status === 'pending' ? 'Event berhasil diajukan untuk validasi admin!' : 'Draft event berhasil disimpan.';
            setFlash($msg, 'success');
            header('Location: ' . BASE_URL . '/panitia/daftar_event.php');
            exit;
        }
    }
}

$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$csrf       = generateCsrfToken();
$minDate    = date('Y-m-d\TH:i', strtotime('+1 day'));

define('PAGE_TITLE', 'Buat Event');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="breadcrumb">
      <a href="<?= BASE_URL ?>/panitia/dashboard.php">Dashboard</a>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">Buat Event Baru</span>
    </div>

    <div class="page-header">
      <h1 class="page-title">Buat Event Baru</h1>
      <p class="page-subtitle">Isi detail event kamu dan ajukan untuk validasi admin.</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:var(--space-6)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:var(--space-8);align-items:start" class="create-layout">
      <div>
        <form method="POST" enctype="multipart/form-data" id="create-event-form" data-validate novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="submit_type" id="submit_type" value="draft">

          <div class="card" style="margin-bottom:var(--space-5)">
            <div class="card-body">
              <h4 style="margin-bottom:var(--space-5)">Informasi Dasar</h4>

              <div class="form-group">
                <label class="form-label">Judul Event *</label>
                <input type="text" name="title" class="form-control" required
                       placeholder="Contoh: Hackathon Nasional 2026"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Kategori *</label>
                  <select name="category_id" class="form-control form-select" required>
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Kuota Peserta *</label>
                  <input type="number" name="quota" class="form-control" required min="1" max="10000"
                         value="<?= htmlspecialchars($_POST['quota'] ?? '100') ?>">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Lokasi / Tempat *</label>
                <input type="text" name="location" class="form-control" required
                       placeholder="Contoh: Gedung Teknik A, Lantai 3"
                       value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Tanggal & Waktu Mulai *</label>
                  <input type="datetime-local" name="date_start" class="form-control" required
                         min="<?= $minDate ?>"
                         value="<?= htmlspecialchars($_POST['date_start'] ?? '') ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Tanggal & Waktu Selesai *</label>
                  <input type="datetime-local" name="date_end" class="form-control" required
                         value="<?= htmlspecialchars($_POST['date_end'] ?? '') ?>">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Deadline Pendaftaran *</label>
                <input type="datetime-local" name="registration_deadline" class="form-control" required
                       value="<?= htmlspecialchars($_POST['registration_deadline'] ?? '') ?>">
                <span class="form-hint">Harus sebelum tanggal mulai event.</span>
              </div>
            </div>
          </div>

          <div class="card" style="margin-bottom:var(--space-5)">
            <div class="card-body">
              <h4 style="margin-bottom:var(--space-5)">Deskripsi Event</h4>
              <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Deskripsi Lengkap *</label>
                <textarea name="description" class="form-control" rows="8" required
                          placeholder="Deskripsikan eventmu secara detail — apa yang akan dipelajari, jadwal, persyaratan peserta, dll..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
              </div>
            </div>
          </div>

          <div class="card" style="margin-bottom:var(--space-6)">
            <div class="card-body">
              <h4 style="margin-bottom:var(--space-5)">Poster Event</h4>
              <div style="border:2px dashed var(--clr-border);border-radius:var(--radius-lg);padding:var(--space-8);text-align:center">
                <img id="poster-preview" src="" alt="" style="display:none;max-height:200px;margin:0 auto var(--space-4);border-radius:var(--radius-md);object-fit:cover">
                <div style="margin-bottom:var(--space-3)">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--clr-text-muted)" stroke-width="1.5" style="margin:0 auto"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <p style="font-size:.875rem;color:var(--clr-text-muted);margin-bottom:var(--space-3)">
                  JPG, PNG, WebP · Maks. 2MB · Rasio 16:9 direkomendasikan
                </p>
                <label class="btn btn-outline btn-sm" style="cursor:pointer">
                  Pilih Poster
                  <input type="file" id="poster-input" name="poster" accept="image/*" style="display:none">
                </label>
              </div>
            </div>
          </div>

          <div style="display:flex;gap:var(--space-3);flex-wrap:wrap">
            <button type="submit" class="btn btn-outline" onclick="document.getElementById('submit_type').value='draft'">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Simpan sebagai Draft
            </button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('submit_type').value='publish'">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Ajukan ke Admin
            </button>
          </div>
        </form>
      </div>

      <!-- Sidebar Tips -->
      <div>
        <div class="card">
          <div class="card-body">
            <h4 style="margin-bottom:var(--space-4)">Tips Membuat Event</h4>
            <div style="display:flex;flex-direction:column;gap:var(--space-4)">
              <?php
              $tips = [
                ['🎯', 'Judul Jelas', 'Gunakan judul yang spesifik dan menarik. Hindari judul generik.'],
                ['📝', 'Deskripsi Lengkap', 'Jelaskan apa yang akan peserta pelajari, jadwal, dan persyaratan.'],
                ['🖼️', 'Poster Menarik', 'Upload poster berkualitas tinggi dengan rasio 16:9 untuk tampilan terbaik.'],
                ['⏰', 'Deadline Tepat', 'Beri waktu cukup untuk pendaftaran — minimal 3 hari sebelum event.'],
              ];
              foreach ($tips as [$icon, $title, $desc]):
              ?>
              <div style="display:flex;gap:var(--space-3);align-items:flex-start">
                <span style="font-size:1.25rem;flex-shrink:0"><?= $icon ?></span>
                <div>
                  <div style="font-weight:600;font-size:.875rem;margin-bottom:2px"><?= $title ?></div>
                  <div style="font-size:.8125rem;color:var(--clr-text-muted)"><?= $desc ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:var(--space-4)">
          <div class="card-body">
            <h5 style="margin-bottom:var(--space-3)">Status Alur Event</h5>
            <?php
            $flow = [
              ['Draft', 'Tersimpan, belum diajukan', 'draft'],
              ['Pending', 'Menunggu validasi admin', 'pending'],
              ['Published', 'Tampil publik, bisa didaftar', 'published'],
              ['Rejected', 'Ditolak admin, perlu revisi', 'rejected'],
            ];
            foreach ($flow as [$status, $desc, $cls]):
            ?>
            <div style="display:flex;align-items:center;gap:var(--space-3);padding:var(--space-2) 0;border-bottom:1px solid var(--clr-border)">
              <span class="status-badge status-<?= $cls ?>"><?= $status ?></span>
              <span style="font-size:.8125rem;color:var(--clr-text-muted)"><?= $desc ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<style>@media(max-width:1023px){.create-layout{grid-template-columns:1fr!important}}</style>
<?php include '../includes/footer.php'; ?>
