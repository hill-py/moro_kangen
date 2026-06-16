<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Dashboard Monitoring';
$baseUrl = '../';

require_once __DIR__ . '/../includes/header.php';

// Folder foto: moro_kangen/img/
$imagePath = '../img/';
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
    background-color: #cbd5e0; /* Default abu-abu sebelum ditarik datanya */
  }

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
</style>

<!-- 4 KARTU METRIK UTAMA DASHBOARD -->
<div class="dashboard-grid">
  
  <!-- 1. Pesanan Hari Ini -->
  <div class="card-stat stat-pesanan">
    <div class="stat-info">
      <h3>1. Pesanan Hari Ini</h3>
      <blockquote>0 Order</blockquote>
    </div>
    <div class="stat-icon">📝</div>
  </div>

  <!-- 2. Pemasukan Hari Ini -->
  <div class="card-stat stat-pemasukan">
    <div class="stat-info">
      <h3>2. Pemasukan Hari Ini</h3>
      <blockquote>Rp 0</blockquote>
    </div>
    <div class="stat-icon">💰</div>
  </div>

  <!-- 3. Kursi Kosong -->
  <div class="card-stat stat-kosong">
    <div class="stat-info">
      <h3>3. Kursi Kosong</h3>
      <blockquote>0 Meja</blockquote>
    </div>
    <div class="stat-icon">🟢</div>
  </div>

  <!-- 4. Kursi Terisi -->
  <div class="card-stat stat-terisi">
    <div class="stat-info">
      <h3>4. Kursi Terisi (Dipakai)</h3>
      <blockquote>0 Meja</blockquote>
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
            <th>Pesanan</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <!-- Menggunakan placeholder kosong sebelum di-looping database -->
          <tr>
            <td colspan="5" class="text-empty">Belum ada transaksi masuk hari ini.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Denah Keterisian Meja Secara Real-time -->
    <div class="monitor-box">
      <div class="box-title">🪑 Peta Visual Keterisian Meja</div>
      <div class="mini-seat-grid">
        <!-- Struktur elemen HTML siap pakai, tinggal ganti class 'green' atau 'red' lewat PHP loop -->
        <div class="mini-seat">A1</div>
        <div class="mini-seat">A2</div>
        <div class="mini-seat">A3</div>
        <div class="mini-seat">A4</div>
        <div class="mini-seat">B1</div>
        <div class="mini-seat">B2</div>
        <div class="mini-seat">B3</div>
        <div class="mini-seat">B4</div>
        <div class="mini-seat">C1</div>
        <div class="mini-seat">C2</div>
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
      
      <!-- Placeholder ketika data menu kosong -->
      <div class="text-empty">Belum ada data penjualan menu.</div>

      <!-- TEMPLATE KERANGKA LOOPING MENUMU (Jangan dihapus, nanti tinggal dicopy-paste di dalam query PHP)
      <div class="rank-item">
        <img src="<= $imagePath ?>nama-file.jpg" class="rank-img" onerror="this.src='https://placehold.co/80x80?text=Moro+Kangen'">
        <div class="rank-details">
          <div class="rank-name">Nama Menu</div>
          <div class="rank-count">🔥 Terjual 0 Porsi</div>
        </div>
      </div> 
      -->

    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>