<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Dashboard';
$baseUrl = '../';
$db = getDB();
$today = date('Y-m-d');

$pesananHariIni = 0;
$pemasukanHariIni = 0;
$kursiKosong = 0;
$kursiTerisi = 0;

$stmt = $db->prepare("SELECT COUNT(*) AS total FROM pesanan WHERE DATE(created_at) = ?");
$stmt->bind_param('s', $today);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$pesananHariIni = (int) ($row['total'] ?? 0);
$stmt->close();

$stmt = $db->prepare("SELECT COALESCE(SUM(p.total_pesanan), 0) AS total FROM pembayaran pb JOIN pesanan p ON p.id_pesanan = pb.id_pesanan WHERE pb.status = 'lunas' AND DATE(pb.tanggal_bayar) = ?");
$stmt->bind_param('s', $today);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$pemasukanHariIni = (float) ($row['total'] ?? 0);
$stmt->close();

$result = $db->query("SELECT status, COUNT(*) AS total FROM kursi GROUP BY status");
while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'kosong') {
        $kursiKosong = (int) $row['total'];
    }
    if ($row['status'] === 'terisi') {
        $kursiTerisi = (int) $row['total'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-grid">
  <section class="summary-card">
    <span class="summary-label">Pesanan Hari Ini</span>
    <strong class="summary-value"><?= $pesananHariIni ?></strong>
  </section>

  <section class="summary-card">
    <span class="summary-label">Pemasukan Hari Ini</span>
    <strong class="summary-value">Rp <?= number_format($pemasukanHariIni, 0, ',', '.') ?></strong>
  </section>

  <section class="summary-card">
    <span class="summary-label">Kursi Kosong</span>
    <strong class="summary-value"><?= $kursiKosong ?></strong>
  </section>

  <section class="summary-card">
    <span class="summary-label">Kursi Terisi</span>
    <strong class="summary-value"><?= $kursiTerisi ?></strong>
  </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
