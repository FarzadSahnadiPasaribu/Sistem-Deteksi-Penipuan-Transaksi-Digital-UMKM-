<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'mahasiswa';
    header("Location: /campusvents/{$role}/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email dan password wajib diisi.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, name, email, password, role, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Email atau password salah. Silakan coba lagi.';
            } elseif (!$user['is_active']) {
                $error = 'Akun kamu nonaktif. Hubungi administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['user_email'] = $user['email'];

                setFlash('Selamat datang kembali, ' . $user['name'] . '!', 'success');
                header("Location: /campusvents/{$user['role']}/dashboard.php");
                exit;
            }
        }
    }
}

$csrf = generateCsrfToken();
define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Masuk');
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
        Semua Event Kampus,<br>
        Satu <span class="gold">Platform</span> Terpadu
      </h2>
      <p class="auth-brand-sub">
        Temukan ratusan event kampus yang sesuai minatmu — dari hackathon teknologi hingga festival seni budaya. Daftar, hadir, dan berkembang bersama komunitas kampus.
      </p>
    </div>

    <!-- Decorative SVG -->
    <svg class="auth-brand-decoration" width="320" height="320" viewBox="0 0 320 320" fill="none">
      <circle cx="160" cy="160" r="140" stroke="white" stroke-width="1" stroke-dasharray="8 6"/>
      <circle cx="160" cy="160" r="100" stroke="white" stroke-width="0.5"/>
      <rect x="120" y="120" width="80" height="80" rx="8" stroke="white" stroke-width="1.5"/>
      <line x1="20"  y1="160" x2="120" y2="160" stroke="white" stroke-width="0.5"/>
      <line x1="200" y1="160" x2="300" y2="160" stroke="white" stroke-width="0.5"/>
      <line x1="160" y1="20"  x2="160" y2="120" stroke="white" stroke-width="0.5"/>
      <line x1="160" y1="200" x2="160" y2="300" stroke="white" stroke-width="0.5"/>
      <circle cx="160" cy="160" r="8" fill="white" opacity="0.6"/>
      <circle cx="40"  cy="40"  r="4" fill="white" opacity="0.3"/>
      <circle cx="280" cy="40"  r="4" fill="white" opacity="0.3"/>
      <circle cx="40"  cy="280" r="4" fill="white" opacity="0.3"/>
      <circle cx="280" cy="280" r="4" fill="white" opacity="0.3"/>
    </svg>
  </div>

  <!-- Form Side -->
  <div class="auth-form-side">
    <div class="auth-form-inner">
      <h1 class="auth-form-title">Masuk ke Akun</h1>
      <p class="auth-form-sub">
        Belum punya akun?
        <a href="/campusvents/register.php">Daftar sekarang</a>
      </p>

      <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:var(--space-5)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <form action="/campusvents/login.php" method="POST" data-validate novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <div class="form-group">
          <label class="form-label" for="email">Email</label>
          <div class="input-group">
            <span class="input-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </span>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="nama@email.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autocomplete="email">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-group has-action">
            <span class="input-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            </span>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="Masukkan password" required autocomplete="current-password">
            <button type="button" class="input-action" data-pw-toggle="password" aria-label="Tampilkan password">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:var(--space-3)">
          Masuk Sekarang
        </button>
      </form>

      <p style="margin-top:var(--space-6);text-align:center;font-size:.875rem;color:var(--clr-text-muted)">
        Dengan masuk, kamu menyetujui <a href="#" style="color:var(--clr-accent);text-decoration:none">Syarat &amp; Ketentuan</a> CampusVents.
      </p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
