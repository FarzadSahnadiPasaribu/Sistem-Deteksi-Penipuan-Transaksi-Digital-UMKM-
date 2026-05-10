<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    $r = $_SESSION['user_role'] ?? 'mahasiswa';
    header('Location: ' . BASE_URL . '/' . $r . '/dashboard.php');
    exit;
}

$error    = '';
$step     = 1;
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';
        $nim       = trim($_POST['nim'] ?? '') ?: null;
        $prodi     = trim($_POST['prodi'] ?? '') ?: null;
        $role      = in_array($_POST['role'] ?? '', ['mahasiswa','panitia']) ? $_POST['role'] : 'mahasiswa';
        $interests = $_POST['interests'] ?? [];

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Nama, email, dan password wajib diisi.';
            $step  = 1;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
            $step  = 1;
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
            $step  = 1;
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
            $step  = 1;
        } elseif (empty($interests)) {
            $error = 'Pilih minimal satu minat.';
            $step  = 2;
        } else {
            $db    = getDB();
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'Email sudah terdaftar. Gunakan email lain atau login.';
                $step  = 1;
            } else {
                // Panitia baru = belum terverifikasi (verified=0), mahasiswa langsung aktif
                $verified = ($role === 'panitia') ? 0 : 1;
                $hash     = password_hash($password, PASSWORD_BCRYPT);
                $stmt     = $db->prepare(
                    "INSERT INTO users (name, email, password, nim, prodi, role, verified) VALUES (?,?,?,?,?,?,?)"
                );
                $stmt->execute([$name, $email, $hash, $nim, $prodi, $role, $verified]);
                $userId = (int) $db->lastInsertId();

                foreach ($interests as $catId) {
                    $ci = (int) $catId;
                    if ($ci > 0) {
                        $db->prepare("INSERT IGNORE INTO user_interests (user_id, category_id) VALUES (?,?)")
                           ->execute([$userId, $ci]);
                    }
                }

                if ($role === 'panitia') {
                    $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)")
                       ->execute([$userId, 'sistem', 'Akun Panitia Menunggu Verifikasi',
                           'Akun panitia kamu berhasil dibuat dan sedang menunggu verifikasi admin. Kamu akan mendapat notifikasi setelah disetujui (maks. 1×24 jam).']);
                    $admins = $db->query("SELECT id FROM users WHERE role='admin'")->fetchAll();
                    foreach ($admins as $adm) {
                        $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)")
                           ->execute([$adm['id'], 'sistem', 'Pendaftaran Panitia Baru',
                               "Ada pendaftaran akun panitia baru dari $name ($email). Segera verifikasi di halaman Verifikasi Panitia."]);
                    }
                } else {
                    $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)")
                       ->execute([$userId, 'sistem', 'Selamat Datang di CampusVents!',
                           'Akun kamu sudah aktif. Jelajahi event kampus dan temukan yang paling cocok untukmu!']);
                }

                session_regenerate_id(true);
                $_SESSION['user_id']       = $userId;
                $_SESSION['user_name']     = $name;
                $_SESSION['user_role']     = $role;
                $_SESSION['user_email']    = $email;
                $_SESSION['user_verified'] = $verified;

                if ($role === 'panitia') {
                    setFlash('Akun panitia berhasil dibuat! Menunggu verifikasi admin sebelum bisa membuat event.', 'warning');
                    header('Location: ' . BASE_URL . '/panitia/dashboard.php');
                } else {
                    setFlash('Akun berhasil dibuat! Selamat datang di CampusVents.', 'success');
                    header('Location: ' . BASE_URL . '/mahasiswa/dashboard.php');
                }
                exit;
            }
        }
        $formData = compact('name', 'email', 'nim', 'prodi', 'interests', 'role');
    }
}

$db         = getDB();
$categories = $db->query("SELECT id, name, icon FROM categories ORDER BY name")->fetchAll();
$csrf       = generateCsrfToken();
$selRole    = $formData['role'] ?? 'mahasiswa';

define('PAGE_TITLE', 'Daftar Akun');
include __DIR__ . '/includes/header.php';
?>

<div class="auth-layout">
  <!-- Brand Side -->
  <div class="auth-brand">
    <a href="<?= BASE_URL ?>/index.php" class="auth-brand-logo">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Campus<span class="accent">Vents</span>
    </a>
    <div class="auth-brand-content">
      <h2 class="auth-brand-tagline">Bergabung dengan<br><span class="gold">Ribuan</span> Mahasiswa</h2>
      <p class="auth-brand-sub">Buat akun gratis dan mulai temukan event kampus yang paling relevan dengan minat dan passionmu.</p>
      <div style="margin-top:var(--space-8);display:flex;flex-direction:column;gap:var(--space-3)">
        <div style="display:flex;align-items:center;gap:var(--space-3);color:rgba(255,255,255,0.85);font-size:.9rem">
          <span style="font-size:1.2rem">🎓</span>
          <span>Daftar sebagai <strong>Mahasiswa</strong> — langsung aktif</span>
        </div>
        <div style="display:flex;align-items:center;gap:var(--space-3);color:rgba(255,255,255,0.85);font-size:.9rem">
          <span style="font-size:1.2rem">🎪</span>
          <span>Daftar sebagai <strong>Panitia</strong> — buat &amp; kelola event</span>
        </div>
        <div style="display:flex;align-items:center;gap:var(--space-3);color:rgba(255,255,255,0.7);font-size:.85rem">
          <span style="font-size:1.2rem">🛡️</span>
          <span>Akun panitia diverifikasi admin agar bebas hoax</span>
        </div>
      </div>
    </div>
    <svg class="auth-brand-decoration" width="320" height="320" viewBox="0 0 320 320" fill="none">
      <circle cx="160" cy="160" r="140" stroke="white" stroke-width="1" stroke-dasharray="8 6"/>
      <polygon points="160,40 270,230 50,230" stroke="white" stroke-width="1" fill="none" opacity="0.4"/>
      <circle cx="160" cy="160" r="8" fill="white" opacity="0.6"/>
      <circle cx="80"  cy="80"  r="5" fill="white" opacity="0.25"/>
      <circle cx="240" cy="80"  r="5" fill="white" opacity="0.25"/>
      <circle cx="80"  cy="240" r="5" fill="white" opacity="0.25"/>
      <circle cx="240" cy="240" r="5" fill="white" opacity="0.25"/>
    </svg>
  </div>

  <!-- Form Side -->
  <div class="auth-form-side">
    <div class="auth-form-inner" style="max-width:580px">
      <h1 class="auth-form-title">Buat Akun Baru</h1>
      <p class="auth-form-sub">Sudah punya akun? <a href="<?= BASE_URL ?>/login.php">Masuk di sini</a></p>

      <div class="step-indicators">
        <div class="step-indicator <?= $step === 1 ? 'active' : 'done' ?>">
          <div class="step-dot"><?= $step > 1 ? '✓' : '1' ?></div>
          <span>Data Diri</span>
        </div>
        <div class="step-line"></div>
        <div class="step-indicator <?= $step === 2 ? 'active' : '' ?>">
          <div class="step-dot">2</div>
          <span>Pilih Minat</span>
        </div>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:var(--space-5)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <form action="<?= BASE_URL ?>/register.php" method="POST" id="register-form" data-multistep="2" data-validate novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- STEP 1: Data Diri -->
        <div data-step="1">

          <!-- Role Selection -->
          <div class="form-group">
            <label class="form-label">Daftar Sebagai <span style="color:var(--clr-accent)">*</span></label>
            <div class="role-select-grid">
              <label class="role-card <?= $selRole === 'mahasiswa' ? 'selected' : '' ?>" for="role_mhs">
                <input type="radio" id="role_mhs" name="role" value="mahasiswa"
                       <?= $selRole === 'mahasiswa' ? 'checked' : '' ?> class="role-radio">
                <div class="role-card-icon">🎓</div>
                <div class="role-card-info">
                  <div class="role-card-title">Mahasiswa</div>
                  <div class="role-card-desc">Cari &amp; daftar event kampus. <strong>Langsung aktif</strong> setelah mendaftar.</div>
                </div>
                <div class="role-card-check">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
              </label>

              <label class="role-card <?= $selRole === 'panitia' ? 'selected' : '' ?>" for="role_pan">
                <input type="radio" id="role_pan" name="role" value="panitia"
                       <?= $selRole === 'panitia' ? 'checked' : '' ?> class="role-radio">
                <div class="role-card-icon">🎪</div>
                <div class="role-card-info">
                  <div class="role-card-title">Panitia / Penyelenggara</div>
                  <div class="role-card-desc">Buat &amp; kelola event kampus.</div>
                  <div class="role-card-badge">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Perlu verifikasi admin (maks. 1×24 jam)
                  </div>
                </div>
                <div class="role-card-check">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
              </label>
            </div>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="name">Nama Lengkap <span style="color:var(--clr-accent)">*</span></label>
              <input type="text" id="name" name="name" class="form-control"
                     placeholder="Nama lengkap sesuai KTM"
                     value="<?= htmlspecialchars($formData['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="nim">NIM (Opsional)</label>
              <input type="text" id="nim" name="nim" class="form-control"
                     placeholder="Nomor Induk Mahasiswa"
                     value="<?= htmlspecialchars($formData['nim'] ?? '') ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="email">Email Aktif <span style="color:var(--clr-accent)">*</span></label>
            <div class="input-group">
              <span class="input-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </span>
              <input type="email" id="email" name="email" class="form-control"
                     placeholder="nama@email.com"
                     value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                     required autocomplete="email">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="prodi">Program Studi (Opsional)</label>
            <input type="text" id="prodi" name="prodi" class="form-control"
                   placeholder="Contoh: Teknik Informatika"
                   value="<?= htmlspecialchars($formData['prodi'] ?? '') ?>">
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="password">Password <span style="color:var(--clr-accent)">*</span></label>
              <div class="input-group has-action">
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Min. 6 karakter" required data-minlength="6" autocomplete="new-password">
                <button type="button" class="input-action" data-pw-toggle="password">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <div class="password-strength">
                <div class="strength-bars"><div class="strength-bar"></div><div class="strength-bar"></div><div class="strength-bar"></div></div>
                <div class="strength-label"></div>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="confirm_password">Konfirmasi Password <span style="color:var(--clr-accent)">*</span></label>
              <div class="input-group has-action">
                <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                       placeholder="Ulangi password" required data-match="password" autocomplete="new-password">
                <button type="button" class="input-action" data-pw-toggle="confirm_password">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
          </div>

          <button type="button" class="btn btn-primary btn-block btn-lg" data-next>
            Lanjut — Pilih Minat
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </div>

        <!-- STEP 2: Pilih Minat -->
        <div data-step="2" style="display:none">
          <p style="color:var(--clr-text-secondary);margin-bottom:var(--space-5)">
            Pilih minimal 1 topik yang kamu minati. Kami akan merekomendasikan event yang relevan.
          </p>
          <div class="interest-grid" style="margin-bottom:var(--space-6)">
            <?php foreach ($categories as $cat): ?>
            <?php $sel = in_array($cat['id'], (array)($formData['interests'] ?? [])); ?>
            <div class="interest-item <?= $sel ? 'selected' : '' ?>">
              <input type="checkbox" name="interests[]" value="<?= $cat['id'] ?>" <?= $sel ? 'checked' : '' ?>>
              <div class="interest-check">✓</div>
              <div class="interest-icon"><?= $cat['icon'] ?></div>
              <div class="interest-name"><?= htmlspecialchars($cat['name']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;gap:var(--space-3)">
            <button type="button" class="btn btn-outline" data-prev style="flex:1">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
              Kembali
            </button>
            <button type="submit" class="btn btn-primary" style="flex:2">
              Buat Akun Sekarang
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.role-select-grid{display:flex;flex-direction:column;gap:var(--space-3);margin-bottom:var(--space-2)}
.role-card{display:flex;align-items:flex-start;gap:var(--space-4);padding:var(--space-4) var(--space-5);border:2px solid var(--clr-border);border-radius:var(--radius-lg);cursor:pointer;transition:border-color .2s,background .2s;position:relative;background:var(--clr-surface)}
.role-card:hover{border-color:var(--clr-brand)}
.role-card.selected{border-color:var(--clr-brand);background:color-mix(in srgb,var(--clr-brand) 6%,transparent)}
.role-radio{position:absolute;opacity:0;pointer-events:none}
.role-card-icon{font-size:1.75rem;flex-shrink:0;margin-top:2px}
.role-card-info{flex:1}
.role-card-title{font-weight:700;font-size:1rem;color:var(--clr-text);margin-bottom:2px}
.role-card-desc{font-size:.8125rem;color:var(--clr-text-muted);line-height:1.5}
.role-card-badge{display:inline-flex;align-items:center;gap:4px;margin-top:var(--space-2);padding:2px 8px;background:rgba(245,166,35,.12);color:#b87a00;border-radius:999px;font-size:.7rem;font-weight:600}
.role-card-check{width:22px;height:22px;border-radius:50%;border:2px solid var(--clr-border);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;transition:background .2s,border-color .2s}
.role-card.selected .role-card-check{background:var(--clr-brand);border-color:var(--clr-brand)}
.role-card:not(.selected) .role-card-check svg{opacity:0}
</style>
<script>
document.querySelectorAll('.role-card').forEach(card=>{
  card.addEventListener('click',()=>{
    document.querySelectorAll('.role-card').forEach(c=>c.classList.remove('selected'));
    card.classList.add('selected');
    card.querySelector('.role-radio').checked=true;
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
