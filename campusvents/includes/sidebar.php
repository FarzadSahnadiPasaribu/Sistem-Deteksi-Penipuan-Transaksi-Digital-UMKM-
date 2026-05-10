<?php
if (!isset($user)) $user = currentUser();
$role           = $user['role'] ?? 'mahasiswa';
$unreadCount    = isLoggedIn() ? getUnreadNotificationCount($user['id']) : 0;
$currentPage    = basename($_SERVER['PHP_SELF']);
$currentDir     = basename(dirname($_SERVER['PHP_SELF']));

function isActive(string $page, string $dir = ''): string {
    global $currentPage, $currentDir;
    $pageMatch = ($currentPage === $page);
    $dirMatch  = empty($dir) || ($currentDir === $dir);
    return ($pageMatch && $dirMatch) ? ' active' : '';
}
?>
<!-- Mobile Topbar -->
<div class="mobile-topbar">
  <a href="<?= BASE_URL ?>/index.php" class="mobile-topbar-logo">Campus<span class="accent">Vents</span></a>
  <button class="btn-icon" data-sidebar-toggle aria-label="Buka menu">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <line x1="3" y1="6"  x2="21" y2="6"/>
      <line x1="3" y1="12" x2="21" y2="12"/>
      <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>
</div>

<div class="sidebar-overlay"></div>

<aside class="sidebar dashboard-sidebar" role="navigation" aria-label="Navigasi utama">
  <div class="sidebar-header">
    <a href="<?= BASE_URL ?>/index.php" class="sidebar-logo">
      <div class="sidebar-logo-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
          <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </div>
      Campus<span class="accent">Vents</span>
    </a>
  </div>

  <nav class="sidebar-nav">
    <?php if ($role === 'mahasiswa'): ?>
      <span class="sidebar-section-label">Menu Utama</span>
      <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php" class="sidebar-link<?= isActive('dashboard.php','mahasiswa') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="sidebar-link<?= isActive('katalog.php','mahasiswa') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Katalog Event
      </a>
      <a href="<?= BASE_URL ?>/mahasiswa/my_events.php" class="sidebar-link<?= isActive('my_events.php','mahasiswa') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>
        Event Saya
      </a>
      <a href="<?= BASE_URL ?>/mahasiswa/notifikasi.php" class="sidebar-link<?= isActive('notifikasi.php','mahasiswa') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        Notifikasi
        <?php if ($unreadCount > 0): ?>
        <span class="sidebar-badge"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>/mahasiswa/profil.php" class="sidebar-link<?= isActive('profil.php','mahasiswa') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profil Saya
      </a>

    <?php elseif ($role === 'panitia'): ?>
      <span class="sidebar-section-label">Manajemen Event</span>
      <a href="<?= BASE_URL ?>/panitia/dashboard.php" class="sidebar-link<?= isActive('dashboard.php','panitia') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
      <a href="<?= BASE_URL ?>/panitia/buat_event.php" class="sidebar-link<?= isActive('buat_event.php','panitia') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Buat Event
      </a>
      <a href="<?= BASE_URL ?>/panitia/daftar_event.php" class="sidebar-link<?= isActive('daftar_event.php','panitia') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Daftar Event Saya
      </a>
      <a href="<?= BASE_URL ?>/panitia/peserta.php" class="sidebar-link<?= isActive('peserta.php','panitia') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        Peserta
      </a>
      <a href="<?= BASE_URL ?>/panitia/kehadiran.php" class="sidebar-link<?= isActive('kehadiran.php','panitia') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Kehadiran
      </a>

    <?php elseif ($role === 'admin'): ?>
      <span class="sidebar-section-label">Administrasi</span>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link<?= isActive('dashboard.php','admin') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
      <a href="<?= BASE_URL ?>/admin/validasi_event.php" class="sidebar-link<?= isActive('validasi_event.php','admin') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Validasi Event
      </a>
      <?php $pendingPanitiaCount = 0; try { $pendingPanitiaCount = (int)getDB()->query("SELECT COUNT(*) FROM users WHERE role='panitia' AND verified=0 AND is_active=1")->fetchColumn(); } catch(Exception $e){} ?>
      <a href="<?= BASE_URL ?>/admin/verifikasi_panitia.php" class="sidebar-link<?= isActive('verifikasi_panitia.php','admin') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Verifikasi Panitia
        <?php if ($pendingPanitiaCount > 0): ?><span class="sidebar-badge"><?= $pendingPanitiaCount ?></span><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>/admin/kelola_pengguna.php" class="sidebar-link<?= isActive('kelola_pengguna.php','admin') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        Kelola Pengguna
      </a>
      <a href="<?= BASE_URL ?>/admin/kelola_kategori.php" class="sidebar-link<?= isActive('kelola_kategori.php','admin') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        Kelola Kategori
      </a>
      <a href="<?= BASE_URL ?>/admin/laporan.php" class="sidebar-link<?= isActive('laporan.php','admin') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6"  y1="20" x2="6"  y2="14"/></svg>
        Laporan
      </a>
      <a href="<?= BASE_URL ?>/admin/export.php" class="sidebar-link<?= isActive('export.php','admin') ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Ekspor Data
      </a>
    <?php endif; ?>

    <div class="sidebar-divider"></div>
    <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link danger"
       data-confirm="Yakin ingin keluar dari akun?">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Keluar
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-user-avatar"><?= htmlspecialchars(getInitials($user['name'])) ?></div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="sidebar-user-role"><?= htmlspecialchars($role) ?></div>
      </div>
    </div>
  </div>
</aside>
