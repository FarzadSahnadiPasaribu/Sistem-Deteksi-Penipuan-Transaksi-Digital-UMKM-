<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(string $redirect = '/campusvents/login.php'): void {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

function requireRole(string|array $roles, string $redirect = '/campusvents/login.php'): void {
    requireLogin($redirect);
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['user_role'] ?? '', $allowed, true)) {
        header("Location: /campusvents/login.php");
        exit;
    }
}

function currentUser(): array {
    return [
        'id'    => $_SESSION['user_id'] ?? 0,
        'name'  => $_SESSION['user_name'] ?? '',
        'role'  => $_SESSION['user_role'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
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
