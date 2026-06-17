<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB(); // ✅ FIX UTAMA

$pageTitle = "Laporan";
require_once __DIR__ . '/../includes/header.php';

// FILTER TANGGAL
$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');


// ======================================================
// TOTAL PEMASUKAN
// ======================================================
$sql = "
SELECT COALESCE(SUM(p.total_pesanan),0) AS total
FROM pembayaran b
JOIN pesanan p ON p.id_pesanan = b.id_pesanan
WHERE b.status = 'lunas'
AND DATE(b.tanggal_bayar) BETWEEN ? AND ?
";

$stmt = $db->prepare($sql);
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$total_pemasukan = $stmt->get_result()->fetch_assoc()['total'];


// ======================================================
// TOTAL PENGELUARAN
// ======================================================
$sql = "
SELECT COALESCE(SUM(nominal),0) AS total
FROM pengeluaran
WHERE tanggal BETWEEN ? AND ?
";

$stmt = $db->prepare($sql);
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$total_pengeluaran = $stmt->get_result()->fetch_assoc()['total'];


// ======================================================
// TOTAL PESANAN
// ======================================================
$sql = "
SELECT COUNT(*) AS total
FROM pesanan
WHERE DATE(created_at) BETWEEN ? AND ?
";

$stmt = $db->prepare($sql);
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$total_pesanan = $stmt->get_result()->fetch_assoc()['total'];

// KEUNTUNGAN
$keuntungan = $total_pemasukan - $total_pengeluaran;

$sql = "
SELECT
    m.nama_menu,
    SUM(dp.jumlah) AS total_terjual
FROM detail_pesanan dp
JOIN menu m
    ON m.id_menu = dp.id_menu
JOIN pesanan p
    ON p.id_pesanan = dp.id_pesanan
WHERE DATE(p.created_at)
BETWEEN ? AND ?
GROUP BY m.id_menu
ORDER BY total_terjual DESC
LIMIT 5
";

$stmt = $db->prepare($sql);
$stmt->bind_param(
    "ss",
    $tgl_awal,
    $tgl_akhir
);
$stmt->execute();

$menuTerlaris =
    $stmt->get_result();
?>

      <!-- FILTER -->
      <div class="card">
        <form method="GET" class="form-row">
          <div class="form-group">
            <label>Tanggal Awal</label>
            <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>">
          </div>

          <div class="form-group">
            <label>Tanggal Akhir</label>
            <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>">
          </div>

          <div class="form-group" style="display:flex; align-items:end;">
            <button class="btn btn-primary">Filter</button>
          </div>
        </form>
      </div>

      <!-- SUMMARY -->
      <div class="dashboard-grid">

        <div class="summary-card">
          <div class="summary-label">Total Pesanan</div>
          <div class="summary-value"><?= $total_pesanan ?></div>
        </div>

        <div class="summary-card">
          <div class="summary-label">Total Pemasukan</div>
          <div class="summary-value">
            Rp <?= number_format($total_pemasukan,0,',','.') ?>
          </div>
        </div>

        <div class="summary-card">
          <div class="summary-label">Total Pengeluaran</div>
          <div class="summary-value">
            Rp <?= number_format($total_pengeluaran,0,',','.') ?>
          </div>
        </div>

        <div class="summary-card">
          <div class="summary-label">Keuntungan</div>
          <div class="summary-value">
            Rp <?= number_format($keuntungan,0,',','.') ?>
          </div>
        </div>

      </div>

      <!-- DETAIL PEMASUKAN -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Detail Pemasukan</div>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Pelanggan</th>
                <th>Total</th>
                <th>Metode</th>
                <th>Tanggal</th>
              </tr>
            </thead>
            <tbody>

            <?php
            $sql = "
            SELECT p.id_pesanan, p.nama_pelanggan, p.total_pesanan,
                   b.metode_pembayaran, b.tanggal_bayar
            FROM pembayaran b
            JOIN pesanan p ON p.id_pesanan = b.id_pesanan
            WHERE b.status = 'lunas'
            AND DATE(b.tanggal_bayar) BETWEEN ? AND ?
            ORDER BY b.tanggal_bayar DESC
            ";

            $stmt = $db->prepare($sql);
            $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()):
            ?>

              <tr>
                <td>#<?= $row['id_pesanan'] ?></td>
                <td><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                <td>Rp <?= number_format($row['total_pesanan'],0,',','.') ?></td>
                <td>
                  <span class="badge badge-<?= $row['metode_pembayaran'] ?>">
                    <?= strtoupper($row['metode_pembayaran']) ?>
                  </span>
                </td>
                <td><?= date(
                      'd/m/Y H:i',
                      strtotime($row['tanggal_bayar'])
                      )
                    ?>
                </td>
              </tr>

            <?php endwhile; ?>

            </tbody>
          </table>
        </div>
      </div>

      <!-- DETAIL PENGELUARAN -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Pengeluaran</div>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Keterangan</th>
                <th>Nominal</th>
                <th>User</th>
              </tr>
            </thead>
            <tbody>

            <?php
            $sql = "
            SELECT p.tanggal, p.keterangan, p.nominal, u.username
            FROM pengeluaran p
            JOIN user u ON u.id_user = p.id_user
            WHERE p.tanggal BETWEEN ? AND ?
            ORDER BY p.tanggal DESC
            ";

            $stmt = $db->prepare($sql);
            $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()):
            ?>

              <tr>
                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                <td>Rp <?= number_format($row['nominal'],0,',','.') ?></td>
                <td><?= $row['username'] ?></td>
              </tr>

            <?php endwhile; ?>

            </tbody>
          </table>
        </div>
      </div>

      <!-- Menu Terlaris -->
      <div class="card">
          <div class="card-header">
              <div class="card-title">
                  Menu Terlaris
              </div>
          </div>
          <?php if ($menuTerlaris->num_rows === 0): ?>
              <div class="empty-state">
                  <h3>Belum Ada Data</h3>
                  <p>
                      Belum ada menu yang terjual
                      pada periode ini.
                  </p>
              </div>
          <?php else: ?>
          <div class="table-wrap">
              <table>
                  <thead>
                      <tr>
                          <th>Peringkat</th>
                          <th>Menu</th>
                          <th>Total Terjual</th>
                      </tr>
                  </thead>
                  <tbody>
                  <?php
                  $no = 1;
                  while (
                      $row =
                      $menuTerlaris->fetch_assoc()
                  ):
                  ?>
                      <tr>
                          <td>
                              #<?= $no++ ?>
                          </td>
                          <td>
                              <?= htmlspecialchars(
                                  $row['nama_menu']
                              ) ?>
                          </td>
                          <td>
                              <?= (int)
                                  $row['total_terjual']
                              ?>
                              porsi
                          </td>
                      </tr>
                  <?php endwhile; ?>
                  </tbody>
              </table>
          </div>
          <?php endif; ?>
      </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>