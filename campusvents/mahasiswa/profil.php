<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('mahasiswa');

$user = currentUser();
$db   = getDB();

// Load full user data
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

// Load interests
$istmt = $db->prepare("SELECT c.id, c.name, c.icon FROM user_interests ui JOIN categories c ON ui.category_id=c.id WHERE ui.user_id=?");
$istmt->execute([$user['id']]);
$myInterests = $istmt->fetchAll();

$allCats = $db->query("SELECT id, name, icon FROM categories ORDER BY name")->fetchAll();
$intIds  = array_column($myInterests, 'id');

$error   = '';
$success = '';
$csrf    = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $name  = trim($_POST['name'] ?? '');
            $nim   = trim($_POST['nim'] ?? '') ?: null;
            $prodi = trim($_POST['prodi'] ?? '') ?: null;
            $bio   = trim($_POST['bio'] ?? '') ?: null;

            if (empty($name)) {
                $error = 'Nama tidak boleh kosong.';
            } else {
                $db->prepare("UPDATE users SET name=?,nim=?,prodi=?,bio=?,updated_at=NOW() WHERE id=?")
                   ->execute([$name, $nim, $prodi, $bio, $user['id']]);
                $_SESSION['user_name'] = $name;
                setFlash('Profil berhasil diperbarui.', 'success');
                header('Location: ' . BASE_URL . '/mahasiswa/profil.php');
                exit;
            }
        }

        if ($action === 'update_password') {
            $oldPw  = $_POST['old_password']     ?? '';
            $newPw  = $_POST['new_password']      ?? '';
            $confPw = $_POST['confirm_new_password'] ?? '';

            if (!password_verify($oldPw, $userData['password'])) {
                $error = 'Password lama salah.';
            } elseif (strlen($newPw) < 6) {
                $error = 'Password baru minimal 6 karakter.';
            } elseif ($newPw !== $confPw) {
                $error = 'Konfirmasi password tidak cocok.';
            } else {
                $hash = password_hash($newPw, PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET password=?,updated_at=NOW() WHERE id=?")->execute([$hash, $user['id']]);
                setFlash('Password berhasil diubah.', 'success');
                header('Location: ' . BASE_URL . '/mahasiswa/profil.php');
                exit;
            }
        }

        if ($action === 'update_interests') {
            $newInts = $_POST['interests'] ?? [];
            $db->prepare("DELETE FROM user_interests WHERE user_id=?")->execute([$user['id']]);
            foreach ($newInts as $catId) {
                $ci = (int)$catId;
                if ($ci > 0) {
                    $db->prepare("INSERT IGNORE INTO user_interests (user_id, category_id) VALUES (?,?)")->execute([$user['id'], $ci]);
                }
            }
            setFlash('Minat berhasil diperbarui.', 'success');
            header('Location: ' . BASE_URL . '/mahasiswa/profil.php');
            exit;
        }
    }
}

// Reload
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

define('PAGE_TITLE', 'Profil Saya');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <!-- Profile Header -->
    <div class="profile-header">
      <div class="profile-avatar-lg"><?= htmlspecialchars(getInitials($userData['name'])) ?></div>
      <div class="profile-info">
        <h2 class="profile-name"><?= htmlspecialchars($userData['name']) ?></h2>
        <div class="profile-email"><?= htmlspecialchars($userData['email']) ?></div>
        <?php if ($userData['prodi']): ?>
        <div class="profile-email" style="margin-top:2px"><?= htmlspecialchars($userData['prodi']) ?></div>
        <?php endif; ?>
        <div class="profile-role">
          <span class="status-badge status-confirmed">Mahasiswa Aktif</span>
        </div>
      </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:var(--space-5)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6)" class="profile-grid">
      <!-- Edit Profile -->
      <div class="card">
        <div class="card-body">
          <h3 style="margin-bottom:var(--space-5)">Edit Profil</h3>
          <form method="POST" data-validate novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
              <label class="form-label">Nama Lengkap *</label>
              <input type="text" name="name" class="form-control" required
                     value="<?= htmlspecialchars($userData['name']) ?>">
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">NIM</label>
                <input type="text" name="nim" class="form-control"
                       value="<?= htmlspecialchars($userData['nim'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Program Studi</label>
                <input type="text" name="prodi" class="form-control"
                       value="<?= htmlspecialchars($userData['prodi'] ?? '') ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Bio Singkat</label>
              <textarea name="bio" class="form-control" rows="3"
                        placeholder="Ceritakan sedikit tentang dirimu..."><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" disabled
                     style="opacity:.6;cursor:not-allowed">
              <span class="form-hint">Email tidak dapat diubah.</span>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:var(--space-5)">Simpan Perubahan</button>
          </form>
        </div>
      </div>

      <!-- Change Password -->
      <div class="card">
        <div class="card-body">
          <h3 style="margin-bottom:var(--space-5)">Ubah Password</h3>
          <form method="POST" data-validate novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="update_password">

            <div class="form-group">
              <label class="form-label">Password Lama *</label>
              <div class="input-group has-action">
                <input type="password" name="old_password" id="old_password" class="form-control" required>
                <button type="button" class="input-action" data-pw-toggle="old_password">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Password Baru *</label>
              <div class="input-group has-action">
                <input type="password" name="new_password" id="new_password_field" class="form-control" required data-minlength="6">
                <button type="button" class="input-action" data-pw-toggle="new_password_field">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Konfirmasi Password Baru *</label>
              <div class="input-group has-action">
                <input type="password" name="confirm_new_password" id="confirm_new_pw" class="form-control" required data-match="new_password_field">
                <button type="button" class="input-action" data-pw-toggle="confirm_new_pw">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <button type="submit" class="btn btn-danger">Ubah Password</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Interests -->
    <div class="card" style="margin-top:var(--space-6)">
      <div class="card-body">
        <h3 style="margin-bottom:var(--space-2)">Minat Saya</h3>
        <p style="color:var(--clr-text-muted);font-size:.9rem;margin-bottom:var(--space-5)">
          Pilih topik yang kamu minati untuk mendapatkan rekomendasi event yang relevan.
        </p>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="update_interests">
          <div class="interest-grid" style="margin-bottom:var(--space-5)">
            <?php foreach ($allCats as $cat): ?>
            <div class="interest-item <?= in_array($cat['id'], $intIds) ? 'selected' : '' ?>">
              <input type="checkbox" name="interests[]" value="<?= $cat['id'] ?>" <?= in_array($cat['id'], $intIds) ? 'checked' : '' ?>>
              <div class="interest-check">✓</div>
              <div class="interest-icon"><?= $cat['icon'] ?></div>
              <div class="interest-name"><?= htmlspecialchars($cat['name']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary">Simpan Minat</button>
        </form>
      </div>
    </div>
  </main>
</div>

<style>@media(max-width:767px){.profile-grid{grid-template-columns:1fr!important}}</style>

<?php include '../includes/footer.php'; ?>
