<?php
require_once __DIR__ . '/includes/auth.php';
session_destroy();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: ' . BASE_URL . '/login.php');
exit;
