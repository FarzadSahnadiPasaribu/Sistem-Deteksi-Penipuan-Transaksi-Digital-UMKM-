<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');

$user = currentUser();
$db   = getDB();
$csrf = generateCsrfToken();

$search = trim($_GET['q']    ?? '');
$role   = $_GET['role']      ?? 'all';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = ["1=1"];
$params = [];
if ($search) {
    $where[]  = "(u.name LIKE ? OR u.email LIKE ? OR u.nim LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role !== 'all' && in_array($role, ['mahasiswa','panitia','admin'])) {
    $where[]  = "u.role = '$role'";
}
$wc = implode(' AND ', $where);

$total = (int)$db->prepare("SELECT COUNT(*) FROM users u WHERE $wc")->execute($params) && ($rs=$db->prepare("SELECT COUNT(*) FROM users u WHERE $wc")) && $rs->execute($params) ? $rs->fetchColumn() : 0;
// Cleaner:
$cntStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $wc");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);

$stmt = $db->prepare("SELECT * FROM users u WHERE $wc ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Handle update role / toggle active
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('Token tidak valid.', 'error');
    } else {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $action   = $_POST['action'] ?? '';

        if ($targetId === $user['id']) {
            setFlash('Tidak bisa mengubah akun diri sendiri.', 'error');
        } elseif ($action === 'update_role') {
            $newRole = $_POST['new_role'] ?? '';
            if (in_array($newRole, ['mahasiswa','panitia','admin'])) {
                $db->prepare("UPDATE users SET role=?,updated_at=NOW() WHERE id=?")->execute([$newRole, $targetId]);
                setFlash('Role pengguna berhasil diperbarui.', 'success');
            }
        } elseif ($action === 'toggle_active') {
            $db->prepare("UPDATE users SET is_active = NOT is_active, updated_at=NOW() WHERE id=?")->execute([$targetId]);
            setFlash('Status akun berhasil diubah.', 'success');
        }
    }
    header('Location: ' . BASE_URL . '/admin/kelola_pengguna.php?' . http_build_query(['q' => $search, 'role' => $role, 'page' => $page]));
    exit;
}

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Kelola Pengguna');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="page-header">
      <h1 class="page-title">Kelola Pengguna</h1>
      <p class="page-subtitle">Manajemen akun dan peran pengguna platform</p>
    </div>

    <!-- Filter -->
    <form method="GET">
      <div class="filter-bar">
        <div class="input-group filter-search">
          <span class="input-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Cari nama, email, NIM...">
        </div>
        <select name="role" class="form-control form-select" style="max-width:180px">
          <option value="all" <?= $role==='all' ? 'selected' : '' ?>>Semua Role</option>
          <option value="mahasiswa" <?= $role==='mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
          <option value="panitia"   <?= $role==='panitia' ? 'selected' : '' ?>>Panitia</option>
          <option value="admin"     <?= $role==='admin' ? 'selected' : '' ?>>Admin</option>
        </select>
        <button type="submit" class="btn btn-primary">Cari</button>
        <?php if ($search || $role !== 'all'): ?>
        <a href="<?= BASE_URL ?>/admin/kelola_pengguna.php" class="btn btn-ghost">Reset</a>
        <?php endif; ?>
      </div>
    </form>

    <p style="font-size:.875rem;color:var(--clr-text-muted);margin-bottom:var(--space-4)">
      Menampilkan <strong style="color:var(--clr-text-primary)"><?= $total ?></strong> pengguna
    </p>

    <?php if (empty($users)): ?>
    <div class="empty-state">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      <h3>Tidak ada pengguna</h3>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr><th>Pengguna</th><th>Email</th><th>NIM</th><th>Prodi</th><th>Role</th><th>Status</th><th>Bergabung</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u):
            $roleColors = ['admin'=>'badge-health','panitia'=>'badge-tech','mahasiswa'=>'badge-research'];
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:var(--space-2)">
                <div style="width:34px;height:34px;border-radius:50%;background:<?= $u['role']==='admin'?'var(--clr-accent)':($u['role']==='panitia'?'#0069D9':'var(--clr-brand)') ?>;color:white;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0">
                  <?= htmlspecialchars(getInitials($u['name'])) ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($u['name']) ?></div>
                  <?php if ($u['id'] === $user['id']): ?>
                  <div style="font-size:.6875rem;color:var(--clr-accent)">Kamu</div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td style="font-size:.8125rem"><?= htmlspecialchars($u['email']) ?></td>
            <td style="font-size:.8125rem"><?= htmlspecialchars($u['nim'] ?? '-') ?></td>
            <td style="font-size:.8125rem;max-width:120px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars(truncate($u['prodi'] ?? '-', 25)) ?></td>
            <td><span class="badge <?= $roleColors[$u['role']] ?? 'badge-research' ?>"><?= ucfirst($u['role']) ?></span></td>
            <td>
              <span class="status-badge <?= $u['is_active'] ? 'status-published' : 'status-cancelled' ?>">
                <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
              </span>
            </td>
            <td style="font-size:.8125rem;color:var(--clr-text-muted)"><?= formatDate($u['created_at']) ?></td>
            <td>
              <?php if ($u['id'] !== $user['id']): ?>
              <button type="button" class="btn btn-ghost btn-sm" data-modal-open="modal-user-<?= $u['id'] ?>">Edit</button>
              <?php endif; ?>
            </td>
          </tr>
          <!-- Edit User Modal -->
          <?php if ($u['id'] !== $user['id']): ?>
          <tr style="display:none">
            <td colspan="8">
              <div id="modal-user-<?= $u['id'] ?>" class="modal-backdrop">
                <div class="modal-dialog">
                  <div class="modal-header">
                    <h3 class="modal-title">Edit Pengguna</h3>
                    <button class="modal-close" data-modal-close><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                  </div>
                  <div class="modal-body">
                    <div style="background:rgba(26,26,46,.04);border-radius:var(--radius-md);padding:var(--space-4);margin-bottom:var(--space-5)">
                      <div style="font-weight:700"><?= htmlspecialchars($u['name']) ?></div>
                      <div style="font-size:.875rem;color:var(--clr-text-muted)"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                    <form method="POST" style="margin-bottom:var(--space-4)">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="action" value="update_role">
                      <div class="form-group" style="margin-bottom:var(--space-3)">
                        <label class="form-label">Role</label>
                        <select name="new_role" class="form-control form-select">
                          <option value="mahasiswa" <?= $u['role']==='mahasiswa'?'selected':'' ?>>Mahasiswa</option>
                          <option value="panitia"   <?= $u['role']==='panitia'?'selected':'' ?>>Panitia</option>
                          <option value="admin"     <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                        </select>
                      </div>
                      <button type="submit" class="btn btn-primary btn-sm">Ubah Role</button>
                    </form>

                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="action" value="toggle_active">
                      <button type="submit" class="btn <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm"
                              data-confirm="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> akun <?= htmlspecialchars($u['name']) ?>?">
                        <?= $u['is_active'] ? 'Nonaktifkan Akun' : 'Aktifkan Akun' ?>
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="pagination">
      <?php
      $buildUrl = fn($p) => '/campusvents/admin/kelola_pengguna.php?' . http_build_query(['q'=>$search,'role'=>$role,'page'=>$p]);
      ?>
      <a href="<?= $buildUrl($page-1) ?>" class="page-link <?= $page<=1?'disabled':'' ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      </a>
      <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
      <a href="<?= $buildUrl($p) ?>" class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a href="<?= $buildUrl($page+1) ?>" class="page-link <?= $page>=$totalPages?'disabled':'' ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </a>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
