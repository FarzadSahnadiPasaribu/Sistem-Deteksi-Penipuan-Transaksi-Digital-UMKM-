<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('mahasiswa');

$user = currentUser();
$db   = getDB();

$search   = trim($_GET['q']        ?? '');
$catId    = (int)($_GET['cat']     ?? 0);
$sort     = $_GET['sort']          ?? 'terbaru';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 9;
$offset   = ($page - 1) * $perPage;

$where  = ["e.status = 'published'", "e.date_end > NOW()"];
$params = [];

if ($search) {
    $where[]  = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($catId) {
    $where[]  = "e.category_id = ?";
    $params[] = $catId;
}

$orderBy = match($sort) {
    'terlama'  => 'e.date_start ASC',
    'kuota'    => 'quota_sisa DESC',
    default    => 'e.created_at DESC',
};

$whereClause = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM events e WHERE $whereClause");
$countStmt->execute($params);
$total     = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($total / $perPage);

$query = "
    SELECT e.*, c.name as category_name, c.icon as category_icon, u.name as organizer_name,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status!='cancelled') as registered_count,
           (e.quota - (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status!='cancelled')) as quota_sisa
    FROM events e
    JOIN categories c ON e.category_id=c.id
    JOIN users u ON e.organizer_id=u.id
    WHERE $whereClause
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$hasFilter = $search || $catId || $sort !== 'terbaru';

define('PAGE_TITLE', 'Katalog Event');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="page-header">
      <h1 class="page-title">Katalog Event</h1>
      <p class="page-subtitle">Jelajahi semua event kampus yang tersedia</p>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="<?= BASE_URL ?>/mahasiswa/katalog.php">
      <div class="filter-bar">
        <div class="input-group filter-search">
          <span class="input-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </span>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                 class="form-control" placeholder="Cari nama event, lokasi...">
        </div>

        <select name="cat" class="form-control form-select filter-select">
          <option value="">Semua Kategori</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $catId == $cat['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>

        <select name="sort" class="form-control form-select filter-select">
          <option value="terbaru" <?= $sort === 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
          <option value="terlama" <?= $sort === 'terlama' ? 'selected' : '' ?>>Terlama</option>
          <option value="kuota"   <?= $sort === 'kuota'   ? 'selected' : '' ?>>Kuota Terbanyak</option>
        </select>

        <button type="submit" class="btn btn-primary">Cari</button>

        <?php if ($hasFilter): ?>
        <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn btn-ghost">Reset</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Result Info -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-5)">
      <p style="color:var(--clr-text-muted);font-size:.875rem">
        Menampilkan <strong style="color:var(--clr-text-primary)"><?= $total ?></strong> event
        <?= $search ? "untuk \"<em>" . htmlspecialchars($search) . "</em>\"" : '' ?>
      </p>
    </div>

    <!-- Events Grid -->
    <?php if (empty($events)): ?>
    <div class="empty-state">
      <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        <line x1="8" y1="11" x2="14" y2="11"/>
      </svg>
      <h3>Tidak ada event ditemukan</h3>
      <p>Coba ubah kata kunci pencarian atau reset filter untuk melihat semua event.</p>
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn btn-primary">Reset Filter</a>
    </div>
    <?php else: ?>
    <div class="cards-grid">
      <?php foreach ($events as $ev):
        $quota  = getQuotaStatus((int)$ev['registered_count'], (int)$ev['quota']);
        $bc     = getCategoryBadgeClass($ev['category_name']);
        $isReg  = isEventRegistered($user['id'], $ev['id']);
        $closed = strtotime($ev['registration_deadline']) < time();
      ?>
      <div class="card event-card">
        <?php if ($ev['poster']): ?>
        <img src="<?= BASE_URL ?>/uploads/posters/<?= htmlspecialchars($ev['poster']) ?>" class="card-img" alt="">
        <?php else: ?>
        <div class="card-img-placeholder"><?= htmlspecialchars($ev['category_icon']) ?></div>
        <?php endif; ?>
        <div class="card-body">
          <div style="margin-bottom:var(--space-2)">
            <span class="badge <?= $bc ?>"><?= htmlspecialchars($ev['category_name']) ?></span>
          </div>
          <h3 class="card-title"><?= htmlspecialchars($ev['title']) ?></h3>
          <div class="card-meta">
            <div class="meta-item">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?= formatDate($ev['date_start']) ?>
            </div>
            <div class="meta-item">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <?= htmlspecialchars(truncate($ev['location'], 40)) ?>
            </div>
          </div>
          <div class="quota-bar">
            <div class="quota-bar-track">
              <div class="quota-bar-fill <?= $quota['status'] === 'full' ? 'full' : ($quota['status'] === 'almost-full' ? 'almost-full' : '') ?>"
                   style="width:<?= $quota['percent'] ?>%"></div>
            </div>
            <div class="quota-text"><span><?= $quota['remaining'] ?> tersisa</span><span><?= $ev['registered_count'] ?>/<?= $ev['quota'] ?></span></div>
          </div>
        </div>
        <div class="card-footer">
          <?php if ($isReg): ?>
          <span class="btn btn-success btn-sm btn-block" style="cursor:default">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Sudah Terdaftar
          </span>
          <?php elseif ($quota['status'] === 'full' || $closed): ?>
          <span class="btn btn-sm btn-block" style="background:rgba(26,26,46,.06);color:var(--clr-text-muted);cursor:not-allowed;border:1.5px solid var(--clr-border)">
            <?= $quota['status'] === 'full' ? 'Kuota Penuh' : 'Pendaftaran Ditutup' ?>
          </span>
          <?php else: ?>
          <a href="<?= BASE_URL ?>/mahasiswa/detail_event.php?id=<?= $ev['id'] ?>" class="btn btn-primary btn-sm btn-block">Daftar Sekarang</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Navigasi halaman">
      <?php
      $buildUrl = fn($p) => '/campusvents/mahasiswa/katalog.php?' . http_build_query(array_merge($_GET, ['page' => $p]));
      ?>
      <a href="<?= $buildUrl($page - 1) ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>" aria-label="Sebelumnya">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      </a>
      <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
      <a href="<?= $buildUrl($p) ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a href="<?= $buildUrl($page + 1) ?>" class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>" aria-label="Berikutnya">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </a>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
