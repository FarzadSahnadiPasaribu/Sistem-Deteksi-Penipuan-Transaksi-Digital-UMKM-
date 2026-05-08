<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');

$user = currentUser();
$db   = getDB();

// Overall stats
$stats = [
    'total_events'   => $db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'published'      => $db->query("SELECT COUNT(*) FROM events WHERE status='published'")->fetchColumn(),
    'total_users'    => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_mhs'      => $db->query("SELECT COUNT(*) FROM users WHERE role='mahasiswa'")->fetchColumn(),
    'total_panitia'  => $db->query("SELECT COUNT(*) FROM users WHERE role='panitia'")->fetchColumn(),
    'total_regs'     => $db->query("SELECT COUNT(*) FROM registrations WHERE status!='cancelled'")->fetchColumn(),
    'total_attended' => $db->query("SELECT COUNT(*) FROM registrations WHERE status='attended'")->fetchColumn(),
];

// Monthly new users (12 months)
$userMonths = []; $userData = [];
for ($i = 11; $i >= 0; $i--) {
    $m  = date('Y-m', strtotime("-$i months"));
    $mn = date('M Y', strtotime("-$i months"));
    $userMonths[] = $mn;
    $cnt = $db->query("SELECT COUNT(*) FROM users WHERE DATE_FORMAT(created_at,'%Y-%m')='$m'")->fetchColumn();
    $userData[] = (int)$cnt;
}

// Events by category
$byCategory = $db->query("
    SELECT c.name, c.icon, c.color,
           COUNT(e.id) as event_count,
           SUM(CASE WHEN e.status='published' THEN 1 ELSE 0 END) as published_count,
           COALESCE(SUM((SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status!='cancelled')),0) as total_regs
    FROM categories c
    LEFT JOIN events e ON e.category_id=c.id
    GROUP BY c.id, c.name, c.icon, c.color
    ORDER BY total_regs DESC
")->fetchAll();

// Top events by registrations
$topEvents = $db->query("
    SELECT e.title, c.name as cat, u.name as organizer,
           COUNT(r.id) as reg_count, e.quota
    FROM events e
    JOIN categories c ON e.category_id=c.id
    JOIN users u ON e.organizer_id=u.id
    LEFT JOIN registrations r ON r.event_id=e.id AND r.status!='cancelled'
    WHERE e.status='published'
    GROUP BY e.id
    ORDER BY reg_count DESC
    LIMIT 10
")->fetchAll();

// Recent registrations
$recentRegs = $db->query("
    SELECT r.registration_code, r.registered_at, r.status,
           u.name as user_name, e.title as event_title, c.name as cat_name
    FROM registrations r
    JOIN users u ON r.user_id=u.id
    JOIN events e ON r.event_id=e.id
    JOIN categories c ON e.category_id=c.id
    ORDER BY r.registered_at DESC
    LIMIT 10
")->fetchAll();

define('BASE_URL', '/campusvents');
define('PAGE_TITLE', 'Laporan & Statistik');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="page-header">
      <h1 class="page-title">Laporan & Statistik</h1>
      <p class="page-subtitle">Ringkasan data platform CampusVents — <?= date('d M Y') ?></p>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid" style="margin-bottom:var(--space-8)">
      <?php
      $sData = [
        ['stat-icon-brand',  '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>',        $stats['total_events'],   'Total Event'],
        ['stat-icon-mint',   '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',         $stats['published'],      'Event Published'],
        ['stat-icon-accent', '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>',               $stats['total_users'],    'Total Pengguna'],
        ['stat-icon-gold',   '<path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>',                                       $stats['total_regs'],     'Total Pendaftaran'],
        ['stat-icon-brand',  '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>', $stats['total_attended'], 'Total Kehadiran'],
        ['stat-icon-mint',   '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/>', $stats['total_panitia'], 'Panitia'],
      ];
      foreach ($sData as [$ic, $path, $val, $lbl]):
      ?>
      <div class="stat-card">
        <div class="stat-icon <?= $ic ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $path ?></svg></div>
        <div class="stat-info"><div class="stat-number"><?= number_format($val) ?></div><div class="stat-label"><?= $lbl ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Charts -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6);margin-bottom:var(--space-8)" class="report-charts">
      <div class="chart-card">
        <div class="chart-card-header"><h4>Pengguna Baru per Bulan</h4></div>
        <div class="chart-card-body"><div style="height:240px"><canvas id="userChart"></canvas></div></div>
      </div>
      <div class="chart-card">
        <div class="chart-card-header"><h4>Event & Pendaftar per Kategori</h4></div>
        <div class="chart-card-body"><div style="height:240px"><canvas id="catChart"></canvas></div></div>
      </div>
    </div>

    <!-- By Category Table -->
    <div class="section-header"><div><h2 class="section-title">Statistik per Kategori</h2><div class="section-title-bar"></div></div></div>
    <div class="table-wrapper" style="margin-bottom:var(--space-8)">
      <table class="table">
        <thead>
          <tr><th>Kategori</th><th>Total Event</th><th>Published</th><th>Total Pendaftar</th><th>Rasio</th></tr>
        </thead>
        <tbody>
          <?php foreach ($byCategory as $cat):
            $ratio = $cat['published_count'] > 0 ? round(($cat['total_regs'] / max($cat['event_count'],1)), 1) : 0;
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:var(--space-2)">
                <span style="font-size:1.25rem"><?= htmlspecialchars($cat['icon']) ?></span>
                <span style="font-weight:600"><?= htmlspecialchars($cat['name']) ?></span>
              </div>
            </td>
            <td><?= $cat['event_count'] ?></td>
            <td><span class="status-badge status-published"><?= $cat['published_count'] ?></span></td>
            <td><strong><?= number_format($cat['total_regs']) ?></strong></td>
            <td style="color:var(--clr-text-muted)"><?= $ratio ?> per event</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Top Events -->
    <div class="section-header" style="margin-top:0"><div><h2 class="section-title">Top 10 Event Terpopuler</h2><div class="section-title-bar"></div></div></div>
    <div class="table-wrapper" style="margin-bottom:var(--space-8)">
      <table class="table">
        <thead>
          <tr><th>#</th><th>Judul Event</th><th>Kategori</th><th>Penyelenggara</th><th>Kuota</th><th>Pendaftar</th><th>%</th></tr>
        </thead>
        <tbody>
          <?php foreach ($topEvents as $i => $ev):
            $pct = $ev['quota'] > 0 ? round(($ev['reg_count']/$ev['quota'])*100) : 0;
          ?>
          <tr>
            <td style="color:var(--clr-text-muted);font-weight:700">
              <?php if ($i < 3): ?>
              <span style="color:<?= ['var(--clr-gold)','var(--clr-text-muted)','#cd7f32'][$i] ?>;font-family:'DM Serif Display',serif;font-style:italic;font-size:1.25rem"><?= $i+1 ?></span>
              <?php else: echo $i+1; endif; ?>
            </td>
            <td style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($ev['title']) ?></td>
            <td><span class="badge <?= getCategoryBadgeClass($ev['cat']) ?>"><?= htmlspecialchars($ev['cat']) ?></span></td>
            <td style="font-size:.875rem"><?= htmlspecialchars($ev['organizer']) ?></td>
            <td><?= $ev['quota'] ?></td>
            <td><strong><?= $ev['reg_count'] ?></strong></td>
            <td>
              <div style="display:flex;align-items:center;gap:var(--space-2)">
                <div class="quota-bar-track" style="width:60px;height:4px">
                  <div class="quota-bar-fill <?= $pct>=100?'full':($pct>=75?'almost-full':'') ?>" style="width:<?= min($pct,100) ?>%"></div>
                </div>
                <span style="font-size:.75rem;color:var(--clr-text-muted)"><?= $pct ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Registrations -->
    <div class="section-header" style="margin-top:0"><div><h2 class="section-title">Pendaftaran Terbaru</h2><div class="section-title-bar"></div></div></div>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr><th>Pengguna</th><th>Event</th><th>Kategori</th><th>Kode</th><th>Status</th><th>Waktu</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recentRegs as $reg): ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars($reg['user_name']) ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;font-size:.875rem"><?= htmlspecialchars(truncate($reg['event_title'],45)) ?></td>
            <td><span class="badge <?= getCategoryBadgeClass($reg['cat_name']) ?>"><?= htmlspecialchars($reg['cat_name']) ?></span></td>
            <td><span class="reg-code" style="font-size:.75rem"><?= htmlspecialchars($reg['registration_code']) ?></span></td>
            <td><span class="status-badge status-<?= htmlspecialchars($reg['status']) ?>"><?= ucfirst($reg['status']) ?></span></td>
            <td style="font-size:.8125rem;color:var(--clr-text-muted)"><?= timeAgo($reg['registered_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const uMonths = <?= json_encode($userMonths) ?>;
const uData   = <?= json_encode($userData) ?>;
const cLabels = <?= json_encode(array_column($byCategory, 'name')) ?>;
const cEvents = <?= json_encode(array_column($byCategory, 'event_count')) ?>;
const cRegs   = <?= json_encode(array_column($byCategory, 'total_regs')) ?>;

new Chart(document.getElementById('userChart'), {
  type: 'bar',
  data: {
    labels: uMonths,
    datasets: [{
      label: 'Pengguna Baru',
      data: uData,
      backgroundColor: 'rgba(26,26,46,0.75)',
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { grid: { display: false } } }
  }
});

new Chart(document.getElementById('catChart'), {
  type: 'bar',
  data: {
    labels: cLabels,
    datasets: [
      { label: 'Event', data: cEvents, backgroundColor: 'rgba(26,26,46,0.6)', borderRadius: 4 },
      { label: 'Pendaftar', data: cRegs, backgroundColor: 'rgba(233,69,96,0.7)', borderRadius: 4 },
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
    scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
  }
});
</script>

<style>@media(max-width:1023px){.report-charts{grid-template-columns:1fr!important}}</style>
<?php include '../includes/footer.php'; ?>
