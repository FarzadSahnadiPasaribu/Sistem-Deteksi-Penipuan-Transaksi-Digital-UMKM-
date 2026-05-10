<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');

$user = currentUser();
$db   = getDB();

$totalEvents  = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
$published    = $db->query("SELECT COUNT(*) FROM events WHERE status='published'")->fetchColumn();
$pending      = $db->query("SELECT COUNT(*) FROM events WHERE status='pending'")->fetchColumn();
$totalUsers   = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalRegs    = $db->query("SELECT COUNT(*) FROM registrations WHERE status!='cancelled'")->fetchColumn();
$totalMhs     = $db->query("SELECT COUNT(*) FROM users WHERE role='mahasiswa'")->fetchColumn();

// Last 6 months events for chart
$chartMonths = [];
$chartData   = [];
for ($i = 5; $i >= 0; $i--) {
    $m  = date('Y-m', strtotime("-$i months"));
    $mn = date('M', strtotime("-$i months"));
    $chartMonths[] = $mn;
    $cnt = $db->query("SELECT COUNT(*) FROM events WHERE status='published' AND DATE_FORMAT(created_at,'%Y-%m')='$m'")->fetchColumn();
    $chartData[] = (int)$cnt;
}

// Category distribution
$catDistrib = $db->query("
    SELECT c.name, COUNT(r.id) as total
    FROM categories c
    LEFT JOIN events e ON e.category_id=c.id AND e.status='published'
    LEFT JOIN registrations r ON r.event_id=e.id AND r.status!='cancelled'
    GROUP BY c.id, c.name
    ORDER BY total DESC
")->fetchAll();

// Pending events
$pendingEvents = $db->query("
    SELECT e.*, c.name as category_name, u.name as organizer_name
    FROM events e
    JOIN categories c ON e.category_id=c.id
    JOIN users u ON e.organizer_id=u.id
    WHERE e.status='pending'
    ORDER BY e.created_at ASC
    LIMIT 5
")->fetchAll();

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Dashboard Admin');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="greeting-card">
      <div class="greeting-date">Panel Administrator</div>
      <h2 class="greeting-title">Selamat datang, <strong><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></strong></h2>
      <p class="greeting-sub">Monitor dan kelola seluruh ekosistem CampusVents.</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:var(--space-8)">
      <?php
      $stats = [
        ['stat-icon-brand', '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>', $totalEvents, 'Total Event'],
        ['stat-icon-mint',  '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', $published, 'Event Published'],
        ['stat-icon-gold',  '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>', $pending, 'Menunggu Validasi'],
        ['stat-icon-accent','<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>', $totalMhs, 'Total Mahasiswa'],
      ];
      foreach ($stats as [$iconClass, $iconPath, $val, $label]):
      ?>
      <div class="stat-card">
        <div class="stat-icon <?= $iconClass ?>">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $iconPath ?></svg>
        </div>
        <div class="stat-info"><div class="stat-number"><?= $val ?></div><div class="stat-label"><?= $label ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Charts + Pending -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6);margin-bottom:var(--space-8)" class="admin-charts">
      <!-- Line Chart -->
      <div class="chart-card">
        <div class="chart-card-header">
          <h4>Event Dipublish (6 Bulan)</h4>
        </div>
        <div class="chart-card-body">
          <div class="chart-wrapper" style="height:220px">
            <canvas id="lineChart"></canvas>
          </div>
        </div>
      </div>
      <!-- Doughnut Chart -->
      <div class="chart-card">
        <div class="chart-card-header">
          <h4>Distribusi Pendaftar per Kategori</h4>
        </div>
        <div class="chart-card-body">
          <div class="chart-wrapper" style="height:220px">
            <canvas id="doughnutChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending Validation -->
    <div class="section-header">
      <div>
        <h2 class="section-title">Menunggu Validasi</h2>
        <div class="section-title-bar"></div>
      </div>
      <?php if ($pending > 0): ?>
      <a href="<?= BASE_URL ?>/admin/validasi_event.php" class="btn btn-accent btn-sm">
        <?= $pending ?> event pending
      </a>
      <?php endif; ?>
    </div>

    <?php if (empty($pendingEvents)): ?>
    <div class="alert alert-success">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>
      Tidak ada event yang menunggu validasi. Semua sudah ditangani.
    </div>
    <?php else: ?>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr><th>Judul Event</th><th>Kategori</th><th>Panitia</th><th>Diajukan</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($pendingEvents as $ev): ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars(truncate($ev['title'], 50)) ?></td>
            <td><span class="badge <?= getCategoryBadgeClass($ev['category_name']) ?>"><?= htmlspecialchars($ev['category_name']) ?></span></td>
            <td style="font-size:.875rem"><?= htmlspecialchars($ev['organizer_name']) ?></td>
            <td style="font-size:.8125rem;color:var(--clr-text-muted)"><?= timeAgo($ev['created_at']) ?></td>
            <td><a href="<?= BASE_URL ?>/admin/validasi_event.php" class="btn btn-primary btn-sm">Validasi</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const lineLabels = <?= json_encode($chartMonths) ?>;
const lineValues = <?= json_encode($chartData) ?>;
const catLabels  = <?= json_encode(array_column($catDistrib, 'name')) ?>;
const catValues  = <?= json_encode(array_column($catDistrib, 'total')) ?>;

const colors = ['#E94560','#00C9A7','#F5A623','#0069D9','#B845CB','#E86A00','#0A7C59','#5B45CB'];

new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: {
    labels: lineLabels,
    datasets: [{
      label: 'Event Published',
      data: lineValues,
      borderColor: '#E94560',
      backgroundColor: 'rgba(233,69,96,0.08)',
      tension: 0.4,
      fill: true,
      pointBackgroundColor: '#E94560',
      pointRadius: 4,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(26,26,46,0.05)' } },
      x: { grid: { display: false } }
    }
  }
});

new Chart(document.getElementById('doughnutChart'), {
  type: 'doughnut',
  data: {
    labels: catLabels,
    datasets: [{ data: catValues, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
    },
    cutout: '60%'
  }
});
</script>

<style>@media(max-width:1023px){.admin-charts{grid-template-columns:1fr!important}}</style>
<?php include '../includes/footer.php'; ?>
