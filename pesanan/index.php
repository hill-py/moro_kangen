<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Pesanan';
$baseUrl = '../';
$autoRefreshSeconds = 5;
$db = getDB();
$message = '';
$error = '';

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value) {
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function getPesanan($db, $idPesanan) {
    $stmt = $db->prepare("SELECT id_pesanan, status FROM pesanan WHERE id_pesanan = ? LIMIT 1");
    $stmt->bind_param('i', $idPesanan);
    $stmt->execute();
    $pesanan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $pesanan;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isKaryawan()) {
        $error = 'Pemilik hanya bisa melihat data pesanan.';
    } else {
        $action = $_POST['action'] ?? '';
        $idPesanan = (int) ($_POST['id_pesanan'] ?? 0);
        $pesanan = getPesanan($db, $idPesanan);

        if (!$pesanan) {
            $error = 'Pesanan tidak ditemukan.';
        } elseif ($action === 'set_status') {
            $status = $_POST['status'] ?? '';

            if (!in_array($status, ['dibayar', 'selesai'], true)) {
                $error = 'Status pesanan tidak valid.';
            } elseif (
                ($pesanan['status'] === 'menunggu' && $status !== 'dibayar') ||
                ($pesanan['status'] === 'dibayar' && $status !== 'selesai') ||
                ($pesanan['status'] === 'selesai')
            ) {
                $error = 'Perubahan status tidak valid.';
            } else {
                  $db->begin_transaction();

                  try {

                      $stmt = $db->prepare("
                          UPDATE pesanan
                          SET status = ?
                          WHERE id_pesanan = ?
                      ");

                      $stmt->bind_param('si', $status, $idPesanan);
                      $stmt->execute();
                      $stmt->close();

                      if ($status === 'selesai') {

                          $stmt = $db->prepare("
                              SELECT id_kursi
                              FROM detail_kursi
                              WHERE id_pesanan = ?
                          ");

                          $stmt->bind_param('i', $idPesanan);
                          $stmt->execute();

                          $result = $stmt->get_result();
                          $kursiIds = [];

                          while ($row = $result->fetch_assoc()) {
                              $kursiIds[] = (int) $row['id_kursi'];
                          }

                          $stmt->close();

                          if (!empty($kursiIds)) {

                              $stmt = $db->prepare("
                                  UPDATE kursi
                                  SET status = 'kosong'
                                  WHERE id_kursi = ?
                              ");

                              foreach ($kursiIds as $idKursi) {
                                  $stmt->bind_param('i', $idKursi);
                                  $stmt->execute();
                              }

                              $stmt->close();
                          }
                      }

                      $db->commit();
                      $message = 'Status pesanan berhasil diperbarui.';

                  } catch (Throwable $e) {

                      $db->rollback();

                      $error = 'Gagal memperbarui status: ' . $e->getMessage();
                  }
              }
              
        } 
    }
}

$orders = [];
$result = $db->query("
    SELECT
        p.id_pesanan,
        p.nama_pelanggan,
        p.jenis_pesanan,
        p.total_pesanan,
        p.status,
        p.created_at,
        GROUP_CONCAT(DISTINCT k.nomor_kursi ORDER BY k.nomor_kursi SEPARATOR ', ') AS kursi
    FROM pesanan p
    LEFT JOIN detail_kursi dk ON dk.id_pesanan = p.id_pesanan
    LEFT JOIN kursi k ON k.id_kursi = dk.id_kursi
    WHERE p.status <> 'selesai'
    GROUP BY p.id_pesanan
    ORDER BY p.created_at DESC
");

while ($row = $result->fetch_assoc()) {
    $row['items'] = [];
    $orders[(int) $row['id_pesanan']] = $row;
}

if (count($orders) > 0) {
    $ids = implode(',', array_map('intval', array_keys($orders)));
    $detailResult = $db->query("
        SELECT dp.id_pesanan, m.nama_menu, dp.jumlah, dp.catatan
        FROM detail_pesanan dp
        JOIN menu m ON m.id_menu = dp.id_menu
        WHERE dp.id_pesanan IN ($ids)
        ORDER BY dp.id_detail_pesanan
    ");

    while ($item = $detailResult->fetch_assoc()) {
        $orders[(int) $item['id_pesanan']]['items'][] = $item;
    }
}

require_once __DIR__ . '/../includes/header.php';

if (isset($_GET['success']) && $_GET['success'] === 'update') {
    $message = 'Tambah Pesanan Berhasil';
}
?>

<?php if ($message !== ''): ?>
  <div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
  <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Daftar Pesanan Masuk</h3>
    <span class="text-muted">Refresh otomatis setiap 5 detik</span>
  </div>

  <?php if (count($orders) === 0): ?>
    <div class="empty-state">
      <h3>Belum ada pesanan</h3>
      <p>Pesanan dari halaman pelanggan akan muncul di sini.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Pelanggan</th>
            <th>Jenis</th>
            <th>Kursi</th>
            <th>Item</th>
            <th class="text-right">Total</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
              <td><?= h($order['nama_pelanggan']) ?></td>
              <td><?= $order['jenis_pesanan'] === 'dine_in' ? 'Dine in' : 'Take away' ?></td>
              <td><?= h($order['kursi'] ?: '-') ?></td>
              <td>
                <?php foreach ($order['items'] as $item): ?>
                  <div class="order-item-line">
                    <strong><?= (int) $item['jumlah'] ?>x <?= h($item['nama_menu']) ?></strong>
                    <?php if ($item['catatan'] !== ''): ?>
                      <span class="text-muted">- <?= h($item['catatan']) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </td>
              <td class="text-right"><?= rupiah($order['total_pesanan']) ?></td>
              <td><span class="badge badge-<?= h($order['status']) ?>"><?= h($order['status']) ?></span></td>
              <td>
                <?php if (isKaryawan()): ?>
                  <div class="td-actions">
                    <?php if ($order['status'] === 'menunggu'): ?>
                      <a
                          href="../pelanggan/index.php?edit=<?= (int) $order['id_pesanan'] ?>"
                          class="btn btn-primary btn-sm">Edit
                      </a>
                      <a
                          href="../pembayaran/index.php?bayar=<?= (int) $order['id_pesanan'] ?>"
                          class="btn btn-success btn-sm">Dibayar
                      </a>
                      <?php endif; ?>

                    <?php if ($order['status'] === 'dibayar'): ?>
                    <form method="POST">
                      <input type="hidden" name="action" value="set_status">
                      <input type="hidden" name="id_pesanan" value="<?= (int) $order['id_pesanan'] ?>">
                      <input type="hidden" name="status" value="selesai">
                      <button type="submit" class="btn btn-secondary btn-sm">
                        Selesai
                      </button>
                    </form>
                  <?php endif; ?>

                    
                  </div>
                <?php else: ?>
                  <span class="text-muted">Lihat saja</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
