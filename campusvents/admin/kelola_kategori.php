<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');

$user = currentUser();
$db   = getDB();
$csrf = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('Token tidak valid.', 'error');
        header('Location: ' . BASE_URL . '/admin/kelola_kategori.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name']  ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $icon  = trim($_POST['icon']  ?? '🏷️');
        $color = trim($_POST['color'] ?? '#1A1A2E');

        if (empty($name)) {
            setFlash('Nama kategori wajib diisi.', 'error');
        } else {
            $db->prepare("INSERT INTO categories (name, description, icon, color) VALUES (?,?,?,?)")->execute([$name, $desc, $icon, $color]);
            setFlash("Kategori \"$name\" berhasil ditambahkan.", 'success');
        }

    } elseif ($action === 'update') {
        $catId = (int)($_POST['cat_id'] ?? 0);
        $name  = trim($_POST['name']  ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $icon  = trim($_POST['icon']  ?? '🏷️');
        $color = trim($_POST['color'] ?? '#1A1A2E');

        if (!$catId || empty($name)) {
            setFlash('Data tidak valid.', 'error');
        } else {
            $db->prepare("UPDATE categories SET name=?,description=?,icon=?,color=? WHERE id=?")->execute([$name, $desc, $icon, $color, $catId]);
            setFlash("Kategori berhasil diperbarui.", 'success');
        }

    } elseif ($action === 'delete') {
        $catId  = (int)($_POST['cat_id'] ?? 0);
        $events = $db->prepare("SELECT COUNT(*) FROM events WHERE category_id=?");
        $events->execute([$catId]);
        if ($events->fetchColumn() > 0) {
            setFlash('Kategori tidak bisa dihapus karena masih digunakan oleh event.', 'error');
        } else {
            $db->prepare("DELETE FROM categories WHERE id=?")->execute([$catId]);
            setFlash('Kategori berhasil dihapus.', 'success');
        }
    }

    header('Location: ' . BASE_URL . '/admin/kelola_kategori.php');
    exit;
}

$categories = $db->query("
    SELECT c.*, COUNT(e.id) as event_count
    FROM categories c
    LEFT JOIN events e ON e.category_id=c.id AND e.status='published'
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Kelola Kategori');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="actions-bar">
      <div>
        <h1 class="page-title">Kelola Kategori</h1>
        <p class="page-subtitle">Manajemen kategori event platform</p>
      </div>
      <button type="button" class="btn btn-primary" data-modal-open="modal-create-cat">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Tambah Kategori
      </button>
    </div>

    <div class="cards-grid">
      <?php foreach ($categories as $cat): ?>
      <div class="card">
        <div class="card-body" style="display:flex;align-items:center;gap:var(--space-4)">
          <div style="width:56px;height:56px;border-radius:var(--radius-md);background:rgba(26,26,46,.06);display:flex;align-items:center;justify-content:center;font-size:1.75rem;flex-shrink:0">
            <?= htmlspecialchars($cat['icon']) ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:1rem"><?= htmlspecialchars($cat['name']) ?></div>
            <div style="font-size:.8125rem;color:var(--clr-text-muted);margin-top:2px"><?= $cat['event_count'] ?> event aktif</div>
            <?php if ($cat['description']): ?>
            <div style="font-size:.8125rem;color:var(--clr-text-muted);margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= htmlspecialchars(truncate($cat['description'], 50)) ?>
            </div>
            <?php endif; ?>
          </div>
          <div style="display:flex;flex-direction:column;gap:var(--space-2)">
            <button type="button" class="btn btn-ghost btn-sm" data-modal-open="modal-edit-cat-<?= $cat['id'] ?>">Edit</button>
            <?php if ($cat['event_count'] == 0): ?>
            <form method="POST" style="margin:0">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="Hapus kategori '<?= htmlspecialchars($cat['name']) ?>'?">Hapus</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Edit Category Modal -->
      <div id="modal-edit-cat-<?= $cat['id'] ?>" class="modal-backdrop">
        <div class="modal-dialog">
          <div class="modal-header">
            <h3 class="modal-title">Edit Kategori</h3>
            <button class="modal-close" data-modal-close><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
            <div class="modal-body">
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Nama *</label>
                  <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($cat['name']) ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Ikon (emoji)</label>
                  <input type="text" name="icon" class="form-control" value="<?= htmlspecialchars($cat['icon']) ?>">
                </div>
              </div>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Deskripsi</label>
                  <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($cat['description'] ?? '') ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Warna (hex)</label>
                  <input type="color" name="color" class="form-control" value="<?= htmlspecialchars($cat['color']) ?>" style="height:44px;padding:4px">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-ghost" data-modal-close>Batal</button>
              <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>

<!-- Create Category Modal -->
<div id="modal-create-cat" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Kategori Baru</h3>
      <button class="modal-close" data-modal-close><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Nama Kategori *</label>
            <input type="text" name="name" class="form-control" required placeholder="Contoh: Penelitian">
          </div>
          <div class="form-group">
            <label class="form-label">Ikon (emoji)</label>
            <input type="text" name="icon" class="form-control" placeholder="🏷️" value="🏷️">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Deskripsi</label>
          <input type="text" name="description" class="form-control" placeholder="Deskripsi singkat kategori">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Warna</label>
          <input type="color" name="color" class="form-control" value="#1A1A2E" style="height:44px;padding:4px">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary">Tambah Kategori</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
