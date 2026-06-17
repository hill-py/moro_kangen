<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Dashboard Monitoring';
$baseUrl = '../';

// Hubungkan ke file koneksi database proyekmu (sesuaikan path-nya jika berbeda)
require_once __DIR__ . '/../config/database.php'; 
require_once __DIR__ . '/../includes/header.php';

// Folder foto: moro_kangen/img/
$imagePath = '../img/';

// Ambil tanggal hari ini (Format: YYYY-MM-DD) untuk filter live monitoring
$hariIni = date('Y-m-d');

// --- 1. QUERY METRIK UTAMA ---

// A. Jumlah Pesanan Hari Ini (Menghitung baris di tabel pesanan)
$queryPesanan = mysqli_query($conn, "SELECT COUNT(*) as total_order FROM pesanan WHERE DATE(created_at) = '$hariIni'");
$dataPesanan = mysqli_fetch_assoc($queryPesanan);
$totalOrder = $dataPesanan['total_order'] ?? 0;

// B. Total Pemasukan Hari Ini (Menjumlahkan kolom total_pesanan dari tabel pesanan yang statusnya Selesai)
$queryPemasukan = mysqli_query($conn, "SELECT SUM(total_pesanan) as total_duit FROM pesanan WHERE DATE(created_at) = '$hariIni' AND status = 'Selesai'");
$dataPemasukan = mysqli_fetch_assoc($queryPemasukan);
$totalPemasukan = $dataPemasukan['total_duit'] ?? 0;

// C. Jumlah Kursi Kosong (Kursi yang tidak ada di detail_kursi dengan pesanan berstatus 'Proses')
$queryKosong = mysqli_query($conn, "SELECT COUNT(*) as total_kosong FROM kursi WHERE id_kursi NOT IN (SELECT DISTINCT id_kursi FROM detail_kursi JOIN pesanan ON detail_kursi.id_pesanan = pesanan.id_pesanan WHERE pesanan.status = 'Proses')");
$dataKosong = mysqli_fetch_assoc($queryKosong);
$kursiKosong = $dataKosong['total_kosong'] ?? 0;

// D. Jumlah Kursi Terisi (Kursi yang sedang digunakan oleh pesanan berstatus 'Proses')
$queryTerisi = mysqli_query($conn, "SELECT COUNT(DISTINCT id_kursi) as total_terisi FROM detail_kursi JOIN pesanan ON detail_kursi.id_pesanan = pesanan.id_pesanan WHERE pesanan.status = 'Proses'");
$dataTerisi = mysqli_fetch_assoc($queryTerisi);
$kursiTerisi = $dataTerisi['total_terisi'] ?? 0;


// --- 2. QUERY DAFTAR TRANSAKSI MASUK TERBARU ---
$queryLiveTransaksi = mysqli_query($conn, "SELECT created_at, jenis_pesanan, total_pesanan, status FROM pesanan WHERE DATE(created_at) = '$hariIni' ORDER BY created_at DESC LIMIT 5");


// --- 3. QUERY PETA KURSI REAL-TIME ---
$queryPetaKursi = mysqli_query($conn, "SELECT k.nomor_kursi, 
    IF(dk.id_pesanan IS NOT NULL, 'red', 'green') as warna_status 
    FROM kursi k 
    LEFT JOIN detail_kursi dk ON k.id_kursi = dk.id_kursi 
    LEFT JOIN pesanan p ON dk.id_pesanan = p.id_pesanan AND p.status = 'Proses'
    ORDER BY k.nomor_kursi ASC");


// --- 4. QUERY JUARA KULINER (MENU TERLARIS HARI INI) ---
// Menghitung menu paling laku hari ini dan memfilter hanya menu yang berstatus 'tersedia' sesuai isi phpMyAdmin kamu
$queryTerlaris = mysqli_query($conn, "SELECT m.nama_menu, m.status_menu, SUM(dp.jumlah) as total_terjual 
    FROM detail_pesanan dp 
    JOIN menu m ON dp.id_menu = m.id_menu 
    JOIN pesanan p ON dp.id_pesanan = p.id_pesanan 
    WHERE DATE(p.created_at) = '$hariIni' AND m.status_menu = 'tersedia'
    GROUP BY dp.id_menu 
    ORDER BY total_terjual DESC LIMIT 3");
?>

<!-- STYLE PREMIUM DASHBOARD MONITORING MORO KANGEN -->
<style>
  /* 1. Grid 4 Utama */
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
  
  /* Garis aksen warna vertikal di kiri kartu */
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
  .stat-info blockquote {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
  }
  .stat-icon {
    font-size: 32px;
    opacity: 0.25;
  }

  /* 2. Layout Baris Kedua (Monitoring Detil) */
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
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 2px solid #edf2f7;
    padding-bottom: 10px;
  }

  /* 3. Komponen Tabel & List di Dalam Monitor */
  .modern-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
  }
  .modern-table th {
    background: #f7fafc;
    padding: 12px 10px;
    color: #718096;
    font-weight: 600;
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

  /* Grid Monitor Meja Mini */
  .mini-seat-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
  }
  .mini-seat {
    padding: 10px 0;
    text-align: center;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
  }
  /* Class dinamis warna kursi */
  .seat-green { background-color: #2ecc71; }
  .seat-red { background-color: #e74c3c; }

  /* List Menu Terlaris dengan Gambar */
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
    background-color: #f7fafc;
  }
  .rank-details { flex-grow: 1; }
  .rank-name { font-weight: 600; font-size: 13px; color: #2d3748; }
  .rank-count { font-size: 12px; color: #e67e22; font-weight: 700; margin-top: 2px; }
  
  /* Badge Status Transaksi */
  .badge { padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
  .badge-proses { background: #fef3c7; color: #d97706; }
  .badge-selesai { background: #d1fae5; color: #059669; }
</style>

<!-- 4 KARTU METRIK UTAMA DASHBOARD -->
<div class="dashboard-grid">
  
  <!-- 1. Pesanan Hari Ini -->
  <div class="card-stat stat-pesanan">
    <div class="stat-info">
      <h3>1. Pesanan Hari Ini</h3>
      <blockquote><?= $totalOrder; ?> Order</blockquote>
    </div>
    <div class="stat-icon">📝</div>
  </div>

  <!-- 2. Pemasukan Hari Ini -->
  <div class="card-stat stat-pemasukan">
    <div class="stat-info">
      <h3>2. Pemasukan Hari Ini</h3>
      <blockquote>Rp <?= number_format($totalPemasukan, 0, ',', '.'); ?></blockquote>
    </div>
    <div class="stat-icon">💰</div>
  </div>

  <!-- 3. Kursi Kosong -->
  <div class="card-stat stat-kosong">
    <div class="stat-info">
      <h3>3. Kursi Kosong</h3>
      <blockquote><?= $kursiKosong; ?> Meja</blockquote>
    </div>
    <div class="stat-icon">🟢</div>
  </div>

  <!-- 4. Kursi Terisi -->
  <div class="card-stat stat-terisi">
    <div class="stat-info">
      <h3>4. Kursi Terisi (Dipakai)</h3>
      <blockquote><?= $kursiTerisi; ?> Meja</blockquote>
    </div>
    <div class="stat-icon">🔴</div>
  </div>

</div>

<!-- DETIL LIVE MONITORING BISNIS -->
<div class="dashboard-row-two">
  
  <!-- KOLOM KIRI: MONITOR TRANSAKSI & DENAH -->
  <div>
    <!-- Aktivitas Transaksi Terbaru -->
    <div class="monitor-box">
      <div class="box-title">📊 Transaksi Masuk Terbaru</div>
      <table class="modern-table">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Tipe/Meja</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($queryLiveTransaksi) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($queryLiveTransaksi)): ?>
              <tr>
                <td><?= date('H:i', strtotime($row['created_at'])); ?> WIB</td>
                <td><?= htmlspecialchars($row['jenis_pesanan']); ?></td>
                <td>Rp <?= number_format($row['total_pesanan'], 0, ',', '.'); ?></td>
                <td>
                  <?php if($row['status'] == 'Proses'): ?>
                    <span class="badge badge-proses">Proses</span>
                  <?php else: ?>
                    <span class="badge badge-selesai">Selesai</span>
                  <?php endif; ?>
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

    <!-- Denah Keterisian Meja Secara Real-time -->
    <div class="monitor-box">
      <div class="box-title">🪑 Peta Visual Keterisian Meja</div>
      <div class="mini-seat-grid">
        <?php if (mysqli_num_rows($queryPetaKursi) > 0): ?>
          <?php while($kursi = mysqli_fetch_assoc($queryPetaKursi)): ?>
            <div class="mini-seat <?= ($kursi['warna_status'] == 'red') ? 'seat-red' : 'seat-green'; ?>">
              <?= htmlspecialchars($kursi['nomor_kursi']); ?>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="text-empty" style="grid-column: span 5;">Data denah meja belum diatur.</div>
        <?php endif; ?>
      </div>
      <div style="margin-top: 15px; display: flex; gap: 15px; font-size: 11px; justify-content: center;">
        <span style="color: #2ecc71;">● Hijau = Kosong</span>
        <span style="color: #e74c3c;">● Merah = Terisi (Sedang Makan)</span>
      </div>
    </div>
  </div>

  <!-- KOLOM KANAN: MONITOR MAKANAN TERLARIS -->
  <div>
    <div class="monitor-box">
      <div class="box-title">🏆 Juara Kuliner Hari Ini</div>
      
      <?php if (mysqli_num_rows($queryTerlaris) > 0): ?>
        <?php while($menu = mysqli_fetch_assoc($queryTerlaris)): 
          // Menyusun file gambar otomatis dari nama menu (misal: "Mie Ayam Biasa" -> "mie-ayam-biasa.jpg")
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
        <div class="text-empty">Belum ada data penjualan menu hari ini.</div>
      <?php endif; ?>

    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>