<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /campusvents/mahasiswa/dashboard.php');
    exit;
}

$error    = '';
$step     = 1;
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $nim      = trim($_POST['nim'] ?? '') ?: null;
        $prodi    = trim($_POST['prodi'] ?? '') ?: null;
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
            $db   = getDB();
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'Email sudah terdaftar. Gunakan email lain atau login.';
                $step  = 1;
            } else {
                $role     = 'mahasiswa'; // hardcoded — registrasi publik = mahasiswa
                $hash     = password_hash($password, PASSWORD_BCRYPT);
                $stmt     = $db->prepare("INSERT INTO users (name, email, password, nim, prodi, role) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$name, $email, $hash, $nim, $prodi, $role]);
                $userId   = (int) $db->lastInsertId();

                foreach ($interests as $catId) {
                    $ci = (int) $catId;
                    if ($ci > 0) {
                        $ins = $db->prepare("INSERT IGNORE INTO user_interests (user_id, category_id) VALUES (?,?)");
                        $ins->execute([$userId, $ci]);
                    }
                }

                // Welcome notification
                $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)")
                   ->execute([$userId, 'sistem', 'Selamat Datang di CampusVents!', 'Akun kamu sudah aktif. Jelajahi event kampus dan temukan yang paling cocok untukmu!']);

                session_regenerate_id(true);
                $_SESSION['user_id']    = $userId;
                $_SESSION['user_name']  = $name;
                $_SESSION['user_role']  = $role;
                $_SESSION['user_email'] = $email;

                setFlash('Akun berhasil dibuat! Selamat datang di CampusVents.', 'success');
                header('Location: /campusvents/mahasiswa/dashboard.php');
                exit;
            }
        }
        $formData = compact('name', 'email', 'nim', 'prodi', 'interests');
    }
}

$db         = getDB();
$categories = $db->query("SELECT id, name, icon FROM categories ORDER BY name")->fetchAll();
$csrf       = generateCsrfToken();

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Daftar Akun');
include __DIR__ . '/includes/header.php';
?>

<div class="auth-layout">
  <!-- Brand Side -->
  <div class="auth-brand">
    <a href="/campusvents/index.php" class="auth-brand-logo">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Campus<span class="accent">Vents</span>
    </a>

    <div class="auth-brand-content">
      <h2 class="auth-brand-tagline">
        Bergabung dengan<br>
        <span class="gold">Ribuan</span> Mahasiswa
      </h2>
      <p class="auth-brand-sub">
        Buat akun gratis dan mulai temukan event kampus yang paling relevan dengan minat dan passionmu. Rekomendasi personal langsung dari hari pertama.
      </p>
    </div>

    <svg class="auth-brand-decoration" width="320" height="320" viewBox="0 0 320 320" fill="none">
      <circle cx="160" cy="160" r="140" stroke="white" stroke-width="1" stroke-dasharray="8 6"/>
      <polygon points="160,40 270,230 50,230" stroke="white" stroke-width="1" fill="none" opacity="0.4"/>
      <circle cx="160" cy="160" r="8" fill="white" opacity="0.6"/>
      <circle cx="80" cy="80" r="5" fill="white" opacity="0.25"/>
      <circle cx="240" cy="80" r="5" fill="white" opacity="0.25"/>
      <circle cx="80" cy="240" r="5" fill="white" opacity="0.25"/>
      <circle cx="240" cy="240" r="5" fill="white" opacity="0.25"/>
    </svg>
  </div>

  <!-- Form Side -->
  <div class="auth-form-side">
    <div class="auth-form-inner" style="max-width:560px">
      <h1 class="auth-form-title">Buat Akun Baru</h1>
      <p class="auth-form-sub">
        Sudah punya akun?
        <a href="/campusvents/login.php">Masuk di sini</a>
      </p>

      <!-- Step Indicators -->
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

      <form action="/campusvents/register.php" method="POST" id="register-form" data-multistep="2" data-validate novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- STEP 1: Data Diri -->
        <div data-step="1">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="name">Nama Lengkap <span style="color:var(--clr-accent)">*</span></label>
              <input type="text" id="name" name="name" class="form-control"
                     placeholder="Nama lengkap sesuai KTM"
                     value="<?= htmlspecialchars($formData['name'] ?? '') ?>"
                     required>
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
                       placeholder="Min. 6 karakter" required data-minlength="6"
                       autocomplete="new-password">
                <button type="button" class="input-action" data-pw-toggle="password">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <div class="password-strength">
                <div class="strength-bars">
                  <div class="strength-bar"></div>
                  <div class="strength-bar"></div>
                  <div class="strength-bar"></div>
                </div>
                <div class="strength-label"></div>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="confirm_password">Konfirmasi Password <span style="color:var(--clr-accent)">*</span></label>
              <div class="input-group has-action">
                <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                       placeholder="Ulangi password" required data-match="password"
                       autocomplete="new-password">
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

<?php include __DIR__ . '/includes/footer.php'; ?>
