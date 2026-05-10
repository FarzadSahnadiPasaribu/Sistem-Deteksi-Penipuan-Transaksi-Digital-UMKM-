<?php
// Muat env.php jika ada (untuk konfigurasi hosting seperti InfinityFree)
$__envFile = __DIR__ . '/env.php';
if (file_exists($__envFile)) require_once $__envFile;
unset($__envFile);

// Auto-detect base URL so the app works from any folder name
if (!defined('BASE_URL')) {
    $__app = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $__doc = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $__rel = ($__doc && str_starts_with($__app, $__doc))
           ? substr($__app, strlen($__doc))
           : '/campusvents';
    define('BASE_URL', rtrim($__rel, '/') ?: '');
    unset($__app, $__doc, $__rel);
}

// ── Konfigurasi Database ────────────────────────────────────
// Lokal: ganti ke nilai default di bawah
// InfinityFree: ganti dengan nilai dari control panel hosting
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'campusvents_db');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Koneksi database gagal.']));
        }
    }
    return $pdo;
}
