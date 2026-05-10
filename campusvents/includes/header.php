<?php
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'CampusVents');
if (!defined('BASE_URL')) {
    $__app = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $__doc = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $__rel = ($__doc && str_starts_with($__app, $__doc)) ? substr($__app, strlen($__doc)) : '/campusvents';
    define('BASE_URL', rtrim($__rel, '/') ?: '');
    unset($__app, $__doc, $__rel);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="CampusVents — Platform Agregator dan Manajemen Event Kampus">
<title><?= htmlspecialchars(PAGE_TITLE) ?> — CampusVents</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
