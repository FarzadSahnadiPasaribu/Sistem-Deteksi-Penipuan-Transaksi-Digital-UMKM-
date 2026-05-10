<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');

$user = currentUser();
$db   = getDB();

$type   = $_GET['type']   ?? '';
$format = $_GET['format'] ?? 'csv';

// ── CSV EXPORT ────────────────────────────────────────────────
function outputCsv(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, $headers);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}

// ── SQL BACKUP ────────────────────────────────────────────────
function outputSqlBackup(PDO $db): void {
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="campusvents_backup_' . date('Ymd_His') . '.sql"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $tables = ['users','categories','events','registrations','user_interests','notifications'];
    echo "-- CampusVents Database Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Server: " . (php_uname('n')) . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
    echo "SET NAMES utf8mb4;\n\n";

    foreach ($tables as $table) {
        $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
        if (empty($rows)) {
            echo "-- Table `$table` is empty\n\n";
            continue;
        }

        echo "-- ── TABLE: $table ──\n";
        echo "TRUNCATE TABLE `$table`;\n";

        $cols = array_keys($db->query("SELECT * FROM `$table` LIMIT 1")->fetch(PDO::FETCH_ASSOC));
        $colList = '`' . implode('`, `', $cols) . '`';

        $chunks = array_chunk($rows, 50);
        foreach ($chunks as $chunk) {
            $values = [];
            foreach ($chunk as $row) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $db->quote($v), $row);
                $values[] = '(' . implode(', ', $vals) . ')';
            }
            echo "INSERT INTO `$table` ($colList) VALUES\n" . implode(",\n", $values) . ";\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    echo "-- End of backup\n";
    exit;
}

// ── HANDLE DOWNLOAD REQUESTS ──────────────────────────────────
if ($type && $format) {
    if ($format === 'sql') {
        outputSqlBackup($db);
    }

    switch ($type) {
        case 'events':
            $rows = $db->query("
                SELECT e.id, e.title, c.name as kategori, u.name as penyelenggara,
                       e.date_start, e.date_end, e.location, e.quota,
                       (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status!='cancelled') as pendaftar,
                       e.status, e.view_count, e.created_at
                FROM events e
                JOIN categories c ON e.category_id=c.id
                JOIN users u ON e.organizer_id=u.id
                ORDER BY e.created_at DESC
            ")->fetchAll();
            outputCsv('campusvents_events_' . date('Ymd') . '.csv',
                ['ID','Judul','Kategori','Penyelenggara','Mulai','Selesai','Lokasi','Kuota','Pendaftar','Status','Views','Dibuat'],
                array_map(fn($r) => [$r['id'],$r['title'],$r['kategori'],$r['penyelenggara'],$r['date_start'],$r['date_end'],$r['location'],$r['quota'],$r['pendaftar'],$r['status'],$r['view_count'],$r['created_at']], $rows)
            );
            break;

        case 'users':
            $rows = $db->query("
                SELECT id, name, email, nim, prodi, role,
                       IF(is_active,1,0) as aktif, created_at
                FROM users ORDER BY created_at DESC
            ")->fetchAll();
            outputCsv('campusvents_users_' . date('Ymd') . '.csv',
                ['ID','Nama','Email','NIM','Prodi','Role','Aktif','Bergabung'],
                array_map(fn($r) => [$r['id'],$r['name'],$r['email'],$r['nim']??'',$r['prodi']??'',$r['role'],$r['aktif'],$r['created_at']], $rows)
            );
            break;

        case 'registrations':
            $rows = $db->query("
                SELECT r.id, r.registration_code, u.name as peserta, u.email, u.nim,
                       e.title as event, c.name as kategori,
                       r.status, r.registered_at, r.attended_at
                FROM registrations r
                JOIN users u ON r.user_id=u.id
                JOIN events e ON r.event_id=e.id
                JOIN categories c ON e.category_id=c.id
                ORDER BY r.registered_at DESC
            ")->fetchAll();
            outputCsv('campusvents_registrations_' . date('Ymd') . '.csv',
                ['ID','Kode','Peserta','Email','NIM','Event','Kategori','Status','Waktu Daftar','Waktu Hadir'],
                array_map(fn($r) => [$r['id'],$r['registration_code'],$r['peserta'],$r['email'],$r['nim']??'',$r['event'],$r['kategori'],$r['status'],$r['registered_at'],$r['attended_at']??''], $rows)
            );
            break;

        case 'categories':
            $rows = $db->query("
                SELECT c.id, c.name, c.description, c.icon, c.color,
                       COUNT(e.id) as total_event,
                       SUM(CASE WHEN e.status='published' THEN 1 ELSE 0 END) as published_event,
                       COALESCE((SELECT COUNT(*) FROM registrations r JOIN events ev ON r.event_id=ev.id WHERE ev.category_id=c.id AND r.status!='cancelled'),0) as total_pendaftar
                FROM categories c
                LEFT JOIN events e ON e.category_id=c.id
                GROUP BY c.id ORDER BY c.name
            ")->fetchAll();
            outputCsv('campusvents_categories_' . date('Ymd') . '.csv',
                ['ID','Nama','Deskripsi','Icon','Warna','Total Event','Published','Total Pendaftar'],
                array_map(fn($r) => [$r['id'],$r['name'],$r['description']??'',$r['icon'],$r['color'],$r['total_event'],$r['published_event']??0,$r['total_pendaftar']], $rows)
            );
            break;
    }
}

// ── STATS FOR DISPLAY ─────────────────────────────────────────
$stats = [
    'events'         => $db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'users'          => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'registrations'  => $db->query("SELECT COUNT(*) FROM registrations")->fetchColumn(),
    'categories'     => $db->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    'notifications'  => $db->query("SELECT COUNT(*) FROM notifications")->fetchColumn(),
    'db_size'        => $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024, 2) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn(),
];

define('PAGE_TITLE', 'Ekspor Data');
include '../includes/header.php';
?>

<div class="dashboard-layout">
  <?php include '../includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="page-header">
      <h1 class="page-title">Ekspor Data</h1>
      <p class="page-subtitle">Download data platform dalam format CSV atau SQL backup</p>
    </div>

    <!-- Database Overview -->
    <div class="stats-grid" style="margin-bottom:var(--space-8)">
      <?php
      $dbStats = [
        ['stat-icon-brand',  '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>',  $stats['events'],        'Total Event'],
        ['stat-icon-accent', '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>',         $stats['users'],         'Total Pengguna'],
        ['stat-icon-mint',   '<path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>',                                 $stats['registrations'], 'Total Pendaftaran'],
        ['stat-icon-gold',   '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>', $stats['db_size'] . ' KB', 'Ukuran Database'],
      ];
      foreach ($dbStats as [$ic, $path, $val, $lbl]):
      ?>
      <div class="stat-card">
        <div class="stat-icon <?= $ic ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $path ?></svg></div>
        <div class="stat-info"><div class="stat-number"><?= is_numeric($val) ? number_format((int)$val) : $val ?></div><div class="stat-label"><?= $lbl ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- SQL Backup -->
    <div class="section-header"><div><h2 class="section-title">Backup Database SQL</h2><div class="section-title-bar"></div></div></div>
    <div class="card" style="margin-bottom:var(--space-8)">
      <div class="card-body" style="display:flex;align-items:center;gap:var(--space-6);flex-wrap:wrap">
        <div style="flex-shrink:0;width:56px;height:56px;background:rgba(26,26,46,.06);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--clr-brand)" stroke-width="1.8">
            <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
          </svg>
        </div>
        <div style="flex:1;min-width:0">
          <h4 style="margin-bottom:var(--space-1)">Full Database Backup</h4>
          <p style="font-size:.875rem;color:var(--clr-text-muted);margin:0">
            Backup semua tabel: users, categories, events, registrations, user_interests, notifications
            — <?= number_format((int)$stats['events']) ?> event, <?= number_format((int)$stats['users']) ?> pengguna, <?= number_format((int)$stats['registrations']) ?> pendaftaran
          </p>
        </div>
        <a href="?type=backup&format=sql" class="btn btn-primary" style="flex-shrink:0">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Download SQL Backup
        </a>
      </div>
    </div>

    <!-- CSV Exports -->
    <div class="section-header" style="margin-top:0"><div><h2 class="section-title">Ekspor CSV per Tabel</h2><div class="section-title-bar"></div></div></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:var(--space-5);margin-bottom:var(--space-8)">
      <?php
      $exports = [
        [
          'type'    => 'events',
          'label'   => 'Data Event',
          'desc'    => 'Judul, kategori, penyelenggara, tanggal, kuota, pendaftar, status, views',
          'count'   => $stats['events'],
          'icon'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>',
          'color'   => 'stat-icon-brand',
          'filename'=> 'campusvents_events_YYYYMMDD.csv',
        ],
        [
          'type'    => 'users',
          'label'   => 'Data Pengguna',
          'desc'    => 'Nama, email, NIM, prodi, role, status aktif, tanggal bergabung (tanpa password)',
          'count'   => $stats['users'],
          'icon'    => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>',
          'color'   => 'stat-icon-accent',
          'filename'=> 'campusvents_users_YYYYMMDD.csv',
        ],
        [
          'type'    => 'registrations',
          'label'   => 'Data Pendaftaran',
          'desc'    => 'Kode unik, peserta, email, NIM, event, kategori, status, waktu daftar & hadir',
          'count'   => $stats['registrations'],
          'icon'    => '<path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>',
          'color'   => 'stat-icon-mint',
          'filename'=> 'campusvents_registrations_YYYYMMDD.csv',
        ],
        [
          'type'    => 'categories',
          'label'   => 'Data Kategori',
          'desc'    => 'Nama, deskripsi, icon, warna, total event, event published, total pendaftar',
          'count'   => $stats['categories'],
          'icon'    => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
          'color'   => 'stat-icon-gold',
          'filename'=> 'campusvents_categories_YYYYMMDD.csv',
        ],
      ];
      foreach ($exports as $ex):
      ?>
      <div class="card">
        <div class="card-body">
          <div style="display:flex;align-items:flex-start;gap:var(--space-4);margin-bottom:var(--space-4)">
            <div class="stat-icon <?= $ex['color'] ?>" style="flex-shrink:0">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $ex['icon'] ?></svg>
            </div>
            <div style="flex:1;min-width:0">
              <h4 style="font-size:1rem;margin-bottom:var(--space-1)"><?= $ex['label'] ?></h4>
              <div style="font-size:.8125rem;color:var(--clr-text-muted)"><?= number_format((int)$ex['count']) ?> record</div>
            </div>
          </div>
          <p style="font-size:.8125rem;color:var(--clr-text-secondary);margin-bottom:var(--space-4);line-height:1.6">
            <?= $ex['desc'] ?>
          </p>
          <div style="font-size:.75rem;font-family:'JetBrains Mono',monospace;color:var(--clr-text-muted);background:rgba(26,26,46,.04);padding:var(--space-2) var(--space-3);border-radius:var(--radius-sm);margin-bottom:var(--space-4)">
            <?= $ex['filename'] ?>
          </div>
          <a href="?type=<?= $ex['type'] ?>&format=csv" class="btn btn-outline btn-sm btn-block">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download CSV
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Info Box -->
    <div class="alert alert-info">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      <div>
        <strong>Catatan Keamanan</strong>
        <div style="font-size:.875rem;margin-top:4px">
          Data CSV pengguna tidak menyertakan kolom password. File SQL backup berisi data lengkap — simpan dengan aman dan jangan bagikan sembarangan. Lakukan backup secara berkala untuk menjaga keamanan data.
        </div>
      </div>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
