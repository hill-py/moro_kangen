<?php
require_once __DIR__ . '/../config/session.php';
$folder = basename(dirname($_SERVER['PHP_SELF']));

function navLink($href, $label, $folder, $targetFolder, $baseUrl = '../') {
    $active = ($folder === $targetFolder) ? ' active' : '';
    echo '<a href="' . $baseUrl . $href . '" class="nav-link' . $active . '">' . htmlspecialchars($label) . '</a>';
}
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <span class="brand-name">Moro Kangen</span>
    <span class="brand-sub">Mie Ayam Bakso</span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">
      <?php navLink('dashboard/index.php', 'Dashboard', $folder, 'dashboard') ?>
      <?php navLink('pesanan/index.php', 'Pesanan', $folder, 'pesanan') ?>
      <?php navLink('pembayaran/index.php', 'Pembayaran', $folder, 'pembayaran') ?>
      <?php navLink('menu/index.php', 'Menu', $folder, 'menu') ?>
      <?php navLink('kursi/index.php', 'Kursi', $folder, 'kursi') ?>
    <?php navLink('Stock Bahan Baku/index.php', 'Stock Bahan Baku', $folder, 'Stock Bahan Baku') ?>
    </div>

    <div class="nav-section">
      <span class="nav-section-label">Keuangan</span>
      <?php navLink('pengeluaran/index.php', 'Pengeluaran', $folder, 'pengeluaran') ?>
      <?php navLink('laporan/index.php', 'Laporan', $folder, 'laporan') ?>
    </div>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= $baseUrl ?? '../' ?>auth/logout.php" class="btn-logout-sidebar">Keluar</a>
  </div>
</aside>
