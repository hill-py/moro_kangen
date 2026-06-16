<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB(); // ✅ Mengambil koneksi database global

$pageTitle = "Stok Bahan Baku";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$message = '';
$error = '';

// ======================================================
// PROSES ADD / UPDATE STOK (POST METHOD)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isKaryawan()) {
    $action = $_POST['action'] ?? '';

    // 1. TAMBAH VARIANT BAHAN BAKU BARU
    if ($action === 'add') {
        $namaBahan = trim($_POST['nama_bahan'] ?? '');
        $stokAwal  = (int)($_POST['stok'] ?? 0);
        $satuan    = trim($_POST['satuan'] ?? '');

        if ($namaBahan === '' || $satuan === '') {
            $error = 'Semua field wajib diisi.';
        } elseif ($stokAwal < 0) {
            $error = 'Stok awal tidak boleh minus.';
        } else {
            $sql_cek = "SELECT id_bahan FROM `morokangen`.`Stock Bahan Baku` WHERE nama_bahan = ? LIMIT 1";
            $stmt = $db->prepare($sql_cek);
            $stmt->bind_param('s', $namaBahan);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists) {
                $error = 'Bahan baku tersebut sudah terdaftar.';
            } else {
                $sql_insert = "INSERT INTO `morokangen`.`Stock Bahan Baku` (nama_bahan, stok, satuan) VALUES (?, ?, ?)";
                $stmt = $db->prepare($sql_insert);
                $stmt->bind_param('sis', $namaBahan, $stokAwal, $satuan);
                $stmt->execute();
                $stmt->close();
                $message = 'Bahan baku baru berhasil ditambahkan.';
            }
        }
    }

    // 2. MODIFIKASI JUMLAH KUANTITAS STOK (+/-)
    if ($action === 'update_stok') {
        $idBahan  = (int)($_POST['id_bahan'] ?? 0);
        $jumlah   = (int)($_POST['jumlah'] ?? 0);
        $operasi  = $_POST['operasi'] ?? '';

        $sql_cari = "SELECT nama_bahan, stok FROM `morokangen`.`Stock Bahan Baku` WHERE id_bahan = ? LIMIT 1";
        $stmt = $db->prepare($sql_cari);
        $stmt->bind_param('i', $idBahan);
        $stmt->execute();
        $bahan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bahan) {
            $error = 'Bahan baku tidak ditemukan.';
        } elseif ($jumlah <= 0) {
            $error = 'Jumlah perubahan stok harus lebih besar dari 0.';
        } else {
            $stokBaru = ($operasi === 'tambah') ? ($bahan['stok'] + $jumlah) : ($bahan['stok'] - $jumlah);

            if ($stokBaru < 0) {
                $error = 'Stok tidak mencukupi untuk dikurangi.';
            } else {
                $sql_update = "UPDATE `morokangen`.`Stock Bahan Baku` SET stok = ? WHERE id_bahan = ?";
                $stmt = $db->prepare($sql_update);
                $stmt->bind_param('ii', $stokBaru, $idBahan);
                $stmt->execute();
                $stmt->close();
                $message = 'Stok ' . $bahan['nama_bahan'] . ' berhasil diperbarui.';
            }
        }
    }
}

// ======================================================
// ✅ FIX LINE 91: Menggunakan query langsung tanpa prepare 
// agar spasi nama tabel tidak memicu mysqli_sql_exception
// ======================================================
$result_count = $db->query("SELECT COUNT(*) AS total FROM `moro_kangen`.`Stock Bahan Baku`");
$total_jenis_bahan = $result_count ? $result_count->fetch_assoc()['total'] : 0;
?>

<div class="layout">
  <div class="main-content">
    
    <div class="topbar">
      <div class="page-title">Manajemen Stok Bahan Baku</div>
    </div>

    <div class="content-area">

      <?php if ($message !== ''): ?>
          <div class="alert alert-success" style="background-color: #ecfdf5; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500;">
              <?= htmlspecialchars($message) ?>
          </div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
          <div class="alert alert-error" style="background-color: #fef2f2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500;">
              <?= htmlspecialchars($error) ?>
          </div>
      <?php endif; ?>

      <div class="dashboard-grid" style="margin-bottom: 25px;">
        <div class="summary-card">
          <div class="summary-label">Total Jenis Bahan Logistik</div>
          <div class="summary-value"><?= $total_jenis_bahan ?></div>
        </div>
      </div>

      <?php if (isKaryawan()): ?>
      <div class="card" style="margin-bottom: 25px;">
          <div class="card-header">
              <div class="card-title">Tambah Kategori Bahan Baku</div>
          </div>
          <form method="POST" style="padding: 20px; display: flex; flex-direction: column; gap: 15px;">
              <input type="hidden" name="action" value="add">

              <div class="form-group">
                  <label style="display: block; margin-bottom: 5px; font-weight: 500;">Nama Bahan Baku</label>
                  <input type="text" name="nama_bahan" required placeholder="Contoh: Mie Mentah Basah" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
              </div>

              <div class="form-group">
                  <label style="display: block; margin-bottom: 5px; font-weight: 500;">Stok Awal</label>
                  <input type="number" name="stok" min="0" required placeholder="Contoh: 50" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
              </div>

              <div class="form-group">
                  <label style="display: block; margin-bottom: 5px; font-weight: 500;">Satuan Ukur</label>
                  <input type="text" name="satuan" required placeholder="Contoh: Porsi, Kg, Lembar" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
              </div>

              <div>
                  <button type="submit" class="btn btn-primary">Tambah Komponen</button>
              </div>
          </form>
      </div>
      <?php endif; ?>

      <div class="card">
          <div class="card-header">
              <div class="card-title">Daftar Logistik Bahan Baku</div>
          </div>

          <div class="table-wrap">
              <table>
                  <thead>
                      <tr>
                          <th>Nama Bahan</th>
                          <th>Jumlah Stok Tersedia</th>
                          <th>Status Logistik</th>
                          <th>Manajemen Kuantitas</th>
                      </tr>
                  </thead>
                  <tbody>

                  <?php
                  // Menggunakan query direct agar terhindar dari limitasi karakter spasi prepare statement XAMPP
                  $result = $db->query("SELECT id_bahan, nama_bahan, stok, satuan FROM `moro_kangen`.`Stock Bahan Baku` ORDER BY nama_bahan ASC");

                  if ($result && $result->num_rows > 0):
                      while ($row = $result->fetch_assoc()):
                  ?>
                      <tr>
                          <td><strong><?= htmlspecialchars($row['nama_bahan']) ?></strong></td>
                          <td><?= htmlspecialchars($row['stok']) ?> <span class="text-muted"><?= htmlspecialchars($row['satuan']) ?></span></td>
                          <td>
                              <?php if ($row['stok'] <= 10): ?>
                                  <span class="badge" style="background-color: #fef2f2; color: #ef4444; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                      HAMPIR HABIS
                                  </span>
                              <?php else: ?>
                                  <span class="badge" style="background-color: #ecfdf5; color: #10b981; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                      STABIL
                                  </span>
                              <?php endif; ?>
                          </td>
                          <td>
                              <?php if (isKaryawan()): ?>
                                  <div style="display: flex; gap: 12px; align-items: center;">
                                      
                                      <form method="POST" style="display: inline-flex; gap: 4px; margin: 0;">
                                          <input type="hidden" name="action" value="update_stok">
                                          <input type="hidden" name="id_bahan" value="<?= (int)$row['id_bahan'] ?>">
                                          <input type="hidden" name="operasi" value="tambah">
                                          <input type="number" name="jumlah" min="1" required placeholder="Qty" style="width: 55px; padding: 4px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px;">
                                          <button type="submit" style="padding: 4px 10px; font-weight: bold; background-color: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer;">+</button>
                                      </form>

                                      <form method="POST" style="display: inline-flex; gap: 4px; margin: 0;">
                                          <input type="hidden" name="action" value="update_stok">
                                          <input type="hidden" name="id_bahan" value="<?= (int)$row['id_bahan'] ?>">
                                          <input type="hidden" name="operasi" value="kurang">
                                          <input type="number" name="jumlah" min="1" required placeholder="Qty" style="width: 55px; padding: 4px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px;">
                                          <button type="submit" style="padding: 4px 11px; background-color: #ef4444; color: #ffffff; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">-</button>
                                      </form>

                                  </div>
                              <?php else: ?>
                                  <span class="text-muted" style="font-size: 13px;">Akses Terbatas (Read-Only)</span>
                              <?php endif; ?>
                          </td>
                      </tr>
                  <?php 
                      endwhile; 
                  else:
                  ?>
                      <tr>
                          <td colspan="4" style="text-align: center; padding: 20px; color: #6b7280;">Belum ada data bahan baku.</td>
                      </tr>
                  <?php endif; ?>

                  </tbody>
              </table>
          </div>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
