<?php
require_once __DIR__ . '/../config/session.php';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php if (isset($autoRefreshSeconds)): ?>
    <meta http-equiv="refresh" content="<?= (int) $autoRefreshSeconds ?>">
  <?php endif; ?>
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>Moro Kangen</title>
  <link rel="stylesheet" href="<?= $baseUrl ?? '../' ?>assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php require_once __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <h2 class="page-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?></h2>
      <div class="topbar-right">
        <span class="topbar-user">
          <?= htmlspecialchars($user['username'] ?? '') ?>
          <span class="role-badge role-<?= htmlspecialchars($user['role'] ?? '') ?>">
            <?= ($user['role'] ?? '') === 'karyawan' ? 'Karyawan' : 'Pemilik' ?>
          </span>
        </span>
        <a href="<?= $baseUrl ?? '../' ?>auth/logout.php" class="btn btn-logout">Keluar</a>
      </div>
    </div>
    <div class="content-area">
