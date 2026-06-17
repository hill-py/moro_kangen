<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Dashboard Monitoring';
$baseUrl   = '../';

// Menggunakan fungsi bawaan tim kamu agar terhindar dari Undefined Variable $conn
$db = getDB();

// Folder foto menu Moro Kangen
$imagePath = '../img/';

// Ambil tanggal hari ini (Format: YYYY-MM-DD)
$hariIni = date('Y-m-d');

// --- 1. QUERY METRIK UTAMA (Menggunakan Object-Oriented MySQLi bawaan getDB) ---

// A. Jumlah Pesanan Hari Ini
$queryPesanan = $db->query("SELECT COUNT(*) as total_order FROM pesanan WHERE DATE(created_at) = '$hariIni'");
$dataPesanan  = $queryPesanan->fetch_assoc();
$totalOrder   = $dataPesanan['total_order'] ?? 0;

// B. Total Pemasukan Hari Ini (Menghitung yang statusnya 'dibayar' atau 'selesai')
$queryPemasukan = $db->query("SELECT SUM(total_pesanan) as total_duit FROM pesanan WHERE DATE(created_at) = '$hariIni' AND status IN ('dibayar', 'selesai')");
$dataPemasukan  = $queryPemasukan->fetch_assoc();
$totalPemasukan = $dataPemasukan['total_duit'] ?? 0;

// C. Jumlah Kursi Kosong
$queryKosong = $db->query("SELECT COUNT(*) as total_kosong FROM kursi WHERE status = 'kosong'");
$dataKosong  = $queryKosong->fetch_assoc();
$kursiKosong = $dataKosong['total_kosong'] ?? 0;

// D. Jumlah Kursi Terisi
$queryTerisi = $db->query("SELECT COUNT(*) as total_terisi FROM kursi WHERE status <> 'kosong'");
$dataTerisi  = $queryTerisi->fetch_assoc();
$kursiTerisi = $dataTerisi['total_terisi'] ?? 0;


// --- 2. QUERY DAFTAR TRANSAKSI MASUK TERBARU ---
$queryLiveTransaksi = $db->query("SELECT created_at, jenis_pesanan, total_pesanan, status FROM pesanan WHERE DATE(created_at) = '$hariIni' ORDER BY created_at DESC LIMIT 5");


// --- 3. QUERY PETA KURSI REAL-TIME ---
$queryPetaKursi = $db->query("SELECT nomor_kursi, status FROM kursi ORDER BY nomor_kursi ASC");


// --- 4. QUERY JUARA KULINER (MENU TERLARIS HARI INI) ---
$queryTerlaris = $db->query("SELECT m.nama_menu, SUM(dp.jumlah) as total_terjual 
    FROM detail_pesanan dp 
    JOIN menu m ON dp.id_menu = m.id_menu 
    JOIN pesanan p ON dp.id_pesanan = p.id_pesanan 
    WHERE DATE(p.created_at) = '$hariIni'
    GROUP BY dp.id_menu 
    ORDER BY total_terjual DESC LIMIT 3");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- STYLE DASHBOARD PREMIUM -->
<style>
  .dashboard-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
  }
  
  .card-stat {
    background: #fff;
    padding: 25px 20px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
  }
  
  .card-stat::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
  }
  .stat-pesanan::before { background: #3498db; }
  .stat-pemasukan::before { background: #2ecc71; }
  .stat-kosong::before { background: #e67e22; }
  .stat-terisi::before { background: #e74c3c; }

  .stat-info h3 {
    margin: 0 0 6px 0;
    font-size: 13px;
    color: #a0aec0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
  }
  .stat-info .stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #2d3748;
  }
  .stat-icon {
    font-size: 32px;
    opacity: 0.25;
  }

  .dashboard-row-two {
    display: grid;
    grid-template-columns: 1.8fr 1.2fr;
    gap: 25px;
  }

  .monitor-box {
    background: #fff;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    border: 1px solid #e2e8f0;
    margin-bottom: 25px;
  }

  .box-title {
    font-size: 16px;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 18px 0;
    border-bottom: 2px solid #edf2f7;
    padding-bottom: 10px;
  }

  .modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }
  .modern-table th {
    background: #f7fafc;
    padding: 12px 10px;
    color: #718096;
    font-weight: 600;
    text-align: left;
  }
  .modern-table td {
    padding: 14px 10px;
    border-bottom: 1px solid #edf2f7;
    color: #4a5568;
  }
  
  .text-empty {
    color: #a0aec0;
    font-style: italic;
    text-align: center;
    padding: 25px 0;
  }

  .mini-seat-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
  }
  .mini-seat {
    padding: 10px 0;
    text-align: center;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
  }
  .seat-green { background-color: #2ecc71; }
  .seat-red { background-color: #e74c3c; }

  .rank-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px dashed #edf2f7;
  }
  .rank-item:last-child { border-bottom: none; }
  .rank-img {
    width: 45px;
    height: 45px;
    object-fit: cover;
    border-radius: 6px;
  }
  .rank-details { flex-grow: 1; }
  .rank-name { font-weight: 600; font-size: 13px; color: #2d3748; }
  .rank-count { font-size: 12px; color: #e67e22; font-weight: 700; }
  
  .badge { padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize;}
  .badge-menunggu { background: #fef3c7; color: #d97706; }
  .badge-dibayar { background: #e0f2fe; color: #0369a1; }
  .badge-selesai { background: #d1fae5; color: #059669; }
</style>

<!-- METRIK DASHBOARD -->
<div class="dashboard-grid">
  <div class="card-stat stat-pesanan">
    <div class="stat-info">
      <h3>Pesanan Hari Ini</h3>
      <div class="stat-value"><?= $totalOrder; ?> Order</div>
    </div>
    <div class="stat-icon">📝</div>
  </div>

  <div class="card-stat stat-pemasukan">
    <div class="stat-info">
      <h3>Pemasukan Hari Ini</h3>
      <div class="stat-value">Rp <?= number_format($totalPemasukan, 0, ',', '.'); ?></div>
    </div>
    <div class="stat-icon">💰</div>
  </div>

  <div class="card-stat stat-kosong">
    <div class="stat-info">
      <h3>Kursi Kosong</h3>
      <div class="stat-value"><?= $kursiKosong; ?> Meja</div>
    </div>
    <div class="stat-icon">🟢</div>
  </div>

  <div class="card-stat stat-terisi">
    <div class="stat-info">
      <h3>Kursi Terisi</h3>
      <div class="stat-value"><?= $kursiTerisi; ?> Meja</div>
    </div>
    <div class="stat-icon">🔴</div>
  </div>
</div>

<!-- DATA LIVE MONITORING -->
<div class="dashboard-row-two">
  <div>
    <!-- Tabel Pesanan Masuk Terbaru -->
    <div class="monitor-box">
      <div class="box-title">📊 Transaksi Masuk Terbaru</div>
      <table class="modern-table">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Tipe Pesanan</th>
            <th>Total Belanja</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($queryLiveTransaksi->num_rows > 0): ?>
            <?php while($row = $queryLiveTransaksi->fetch_assoc()): ?>
              <tr>
                <td><?= date('H:i', strtotime($row['created_at'])); ?> WIB</td>
                <td><?= $row['jenis_pesanan'] === 'dine_in' ? 'Dine In' : 'Take Away'; ?></td>
                <td>Rp <?= number_format($row['total_pesanan'], 0, ',', '.'); ?></td>
                <td>
                  <span class="badge badge-<?= $row['status']; ?>"><?= $row['status']; ?></span>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" class="text-empty">Belum ada transaksi masuk hari ini.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Denah Keterisian Meja -->
    <div class="monitor-box">
      <div class="box-title">🪑 Peta Visual Keterisian Meja</div>
      <div class="mini-seat-grid">
        <?php if ($queryPetaKursi->num_rows > 0): ?>
          <?php while($kursi = $queryPetaKursi->fetch_assoc()): ?>
            <div class="mini-seat <?= ($kursi['status'] !== 'kosong') ? 'seat-red' : 'seat-green'; ?>">
              <?= htmlspecialchars($kursi['nomor_kursi']); ?>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="text-empty" style="grid-column: span 5;">Data meja kosong.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Juara Kuliner / Menu Terlaris -->
  <div>
    <div class="monitor-box">
      <div class="box-title">🏆 Juara Kuliner Hari Ini</div>
      <?php if ($queryTerlaris->num_rows > 0): ?>
        <?php while($menu = $queryTerlaris->fetch_assoc()): 
          $slugNama = strtolower(str_replace(' ', '-', $menu['nama_menu'])) . '.jpg';
        ?>
          <div class="rank-item">
            <img src="<?= $imagePath . $slugNama; ?>" class="rank-img" onerror="this.src='https://placehold.co/80x80?text=Moro+Kangen'">
            <div class="rank-details">
              <div class="rank-name"><?= htmlspecialchars($menu['nama_menu']); ?></div>
              <div class="rank-count">🔥 Terjual <?= $menu['total_terjual']; ?> Porsi</div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="text-empty">Belum ada menu terjual hari ini.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>