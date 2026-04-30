<?php
/**
 * App header — used on every authenticated page.
 * Expects $page_title (optional) defined before include.
 */
require_once __DIR__ . '/auth.php';
$_user = Auth::user();
$initials = $_user
    ? strtoupper(substr($_user['display_name'] ?: $_user['email'], 0, 1))
    : '?';
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title ?? 'nikahin') ?> — nikahin</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<header class="app-header">
  <div class="app-header-inner">
    <a href="<?= APP_URL ?>/" class="app-logo">nika<span>hin</span></a>
    <nav class="app-header-nav">
      <?php if ($_user): ?>
        <a href="<?= APP_URL ?>/dashboard/">Dasbor</a>
        <a href="<?= APP_URL ?>/">Beranda</a>
        <?php if (!empty($_user['is_admin'])): ?>
          <a href="<?= APP_URL ?>/admin/">Admin</a>
        <?php endif; ?>
        <span class="app-user-pill">
          <span class="app-user-avatar"><?= e($initials) ?></span>
          <span><?= e($_user['display_name'] ?: $_user['email']) ?></span>
        </span>
        <a class="btn btn-ghost btn-sm" href="<?= APP_URL ?>/auth/logout.php">Keluar</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline btn-sm">Masuk</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
