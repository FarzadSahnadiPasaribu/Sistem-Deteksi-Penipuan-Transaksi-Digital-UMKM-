<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// BASE_URL detection — available to all files that only include auth.php
if (!defined('BASE_URL')) {
    $__app = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $__doc = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $__rel = ($__doc && str_starts_with($__app, $__doc)) ? substr($__app, strlen($__doc)) : '/campusvents';
    define('BASE_URL', rtrim($__rel, '/') ?: '');
    unset($__app, $__doc, $__rel);
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    // Prevent browser back-button cache on protected pages
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireRole(string|array $roles): void {
    requireLogin();
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['user_role'] ?? '', $allowed, true)) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id']    ?? 0,
        'name'     => $_SESSION['user_name']  ?? '',
        'role'     => $_SESSION['user_role']  ?? '',
        'email'    => $_SESSION['user_email'] ?? '',
        'verified' => $_SESSION['user_verified'] ?? 1,
    ];
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function setFlash(string $message, string $type = 'info'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
