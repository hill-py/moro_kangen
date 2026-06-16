<?php
require_once __DIR__ . '/../config/database.php';

$db = getDB();

function h($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value)
{
  return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function getMenus($db)
{
  $menus  = [];
  $result = $db->query(
    "SELECT id_menu, nama_menu, kategori, harga, deskripsi, status_menu
         FROM   menu
         WHERE  status_menu <> 'nonaktif'
         ORDER  BY kategori, nama_menu"
  );
  while ($row = $result->fetch_assoc()) {
    $menus[(int) $row['id_menu']] = $row;
  }
  return $menus;
}

function getKursi($db)
{
  $kursi  = [];
  $result = $db->query(
    "SELECT id_kursi, nomor_kursi, status FROM kursi ORDER BY nomor_kursi"
  );
  while ($row = $result->fetch_assoc()) {
    $kursi[] = $row;
  }
  return $kursi;
}

function buildOrderItems($menus, $post)
{
  $items = [];
  $total = 0;

  foreach (($post['jumlah'] ?? []) as $idMenu => $jumlah) {
    $idMenu = (int) $idMenu;
    $jumlah = max(0, (int) $jumlah);

    if ($jumlah < 1 || !isset($menus[$idMenu]) || $menus[$idMenu]['status_menu'] !== 'tersedia') {
      continue;
    }

    $catatan  = trim($post['catatan'][$idMenu] ?? '');
    $harga    = (float) $menus[$idMenu]['harga'];
    $subtotal = $harga * $jumlah;
    $total   += $subtotal;

    $items[] = [
      'id_menu'     => $idMenu,
      'nama_menu'   => $menus[$idMenu]['nama_menu'],
      'jumlah'      => $jumlah,
      'catatan'     => $catatan,
      'harga_satuan' => $harga,
      'subtotal'    => $subtotal,
    ];
  }

  return [$items, $total];
}

/* ---------------------------------------------------------------
   Bootstrap
--------------------------------------------------------------- */
$menus  = getMenus($db);
$kursi  = getKursi($db);
// Baca edit id dari GET (form awal) atau dari POST _edit_id (saat confirm/submit)
$editId = (int) ($_GET['edit'] ?? $_POST['_edit_id'] ?? 0);
$isEdit = $editId > 0;
$action = $_POST['action'] ?? '';
$errors = [];

// FIX #3 — tampilkan pesan sukses dari redirect (PRG)
$success = isset($_GET['success']);

$posted = [
  'nama_pelanggan' => trim($_POST['nama_pelanggan'] ?? ''),
  'jenis_pesanan'  => $_POST['jenis_pesanan'] ?? 'dine_in',
  'id_kursi'       => $_POST['id_kursi'] ?? '',
  'jumlah'         => $_POST['jumlah']  ?? [],
  'catatan'        => $_POST['catatan'] ?? [],
];

/* ---------------------------------------------------------------
   Load data pesanan yang akan di-edit (GET request saja)
--------------------------------------------------------------- */
if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {

  $stmt = $db->prepare(
    "SELECT * FROM pesanan WHERE id_pesanan = ? LIMIT 1"
  );
  $stmt->bind_param('i', $editId);
  $stmt->execute();
  $pesanan = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($pesanan) {
    $posted['nama_pelanggan'] = $pesanan['nama_pelanggan'];
    $posted['jenis_pesanan']  = $pesanan['jenis_pesanan'];

    // Ambil kursi yang sedang dipakai
    $stmtKursi = $db->prepare(
      "SELECT id_kursi FROM detail_kursi WHERE id_pesanan = ? LIMIT 1"
    );
    $stmtKursi->bind_param('i', $editId);
    $stmtKursi->execute();
    $kursiLama = $stmtKursi->get_result()->fetch_assoc();
    $stmtKursi->close();

    if ($kursiLama) {
      $posted['id_kursi'] = $kursiLama['id_kursi'];
    }

    // Ambil item pesanan
    $stmtItem = $db->prepare(
      "SELECT id_menu, jumlah, catatan FROM detail_pesanan WHERE id_pesanan = ?"
    );
    $stmtItem->bind_param('i', $editId);
    $stmtItem->execute();
    $resultItem = $stmtItem->get_result();

    while ($row = $resultItem->fetch_assoc()) {
      $posted['jumlah'][$row['id_menu']]  = $row['jumlah'];
      $posted['catatan'][$row['id_menu']] = $row['catatan'];
    }
    $stmtItem->close();
  }
}

[$items, $total] = buildOrderItems($menus, $posted);

/* ---------------------------------------------------------------
   Validasi (confirm & submit)
--------------------------------------------------------------- */
if ($action === 'confirm' || $action === 'submit') {

  if ($posted['nama_pelanggan'] === '') {
    $errors[] = 'Nama pemesan wajib diisi.';
  }

  if (!in_array($posted['jenis_pesanan'], ['dine_in', 'take_away'], true)) {
    $errors[] = 'Jenis pesanan tidak valid.';
  }

  if ($posted['jenis_pesanan'] === 'dine_in') {
    $idKursi = (int) $posted['id_kursi'];

    $stmtKursiCek = $db->prepare(
      "SELECT id_kursi, status FROM kursi WHERE id_kursi = ? LIMIT 1"
    );
    $stmtKursiCek->bind_param('i', $idKursi);
    $stmtKursiCek->execute();
    $selectedKursi = $stmtKursiCek->get_result()->fetch_assoc();
    $stmtKursiCek->close();

    if (!$selectedKursi) {
      $errors[] = 'Pilih kursi yang valid.';
    } elseif (!$isEdit && $selectedKursi['status'] !== 'kosong') {
      // Pesanan baru: kursi harus kosong
      $errors[] = 'Pilih kursi yang masih kosong.';
    } elseif ($isEdit) {
      // Edit: boleh pilih kursi yang sama (milik pesanan ini) atau kursi lain yang kosong
      $stmtCekMilik = $db->prepare(
        "SELECT id_kursi FROM detail_kursi WHERE id_pesanan = ? AND id_kursi = ? LIMIT 1"
      );
      $stmtCekMilik->bind_param('ii', $editId, $idKursi);
      $stmtCekMilik->execute();
      $milik = $stmtCekMilik->get_result()->fetch_assoc();
      $stmtCekMilik->close();

      if (!$milik && $selectedKursi['status'] !== 'kosong') {
        $errors[] = 'Pilih kursi yang masih kosong atau kursi pesanan semula.';
      }
    }
  }

  if (count($items) === 0) {
    $errors[] = 'Pilih minimal satu menu yang tersedia.';
  }
}

$confirmation = ($action === 'confirm' && count($errors) === 0);

/* ---------------------------------------------------------------
   Proses submit
--------------------------------------------------------------- */
if ($action === 'submit' && count($errors) === 0) {
  $db->begin_transaction();

  try {

    /* ── EDIT pesanan yang sudah ada ── */
    if ($isEdit) {

      // 1. Hapus detail item lama
      $stmt = $db->prepare("DELETE FROM detail_pesanan WHERE id_pesanan = ?");
      $stmt->bind_param('i', $editId);
      $stmt->execute();
      $stmt->close();

      // 2. Insert item baru
      $stmt = $db->prepare(
        "INSERT INTO detail_pesanan
                     (id_pesanan, id_menu, jumlah, catatan, harga_satuan, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)"
      );
      foreach ($items as $item) {
        $stmt->bind_param(
          'iiisdd',
          $editId,
          $item['id_menu'],
          $item['jumlah'],
          $item['catatan'],
          $item['harga_satuan'],
          $item['subtotal']
        );
        $stmt->execute();
      }
      $stmt->close();

      // FIX #1 — update nama_pelanggan, jenis_pesanan, dan total
      $stmt = $db->prepare(
        "UPDATE pesanan
                 SET    nama_pelanggan = ?,
                        jenis_pesanan  = ?,
                        total_pesanan  = ?
                 WHERE  id_pesanan = ?"
      );
      $stmt->bind_param('ssdi', $posted['nama_pelanggan'], $posted['jenis_pesanan'], $total, $editId);
      $stmt->execute();
      $stmt->close();

      // FIX #2 — reset kursi lama ke 'kosong'
      $stmtOld = $db->prepare(
        "SELECT id_kursi FROM detail_kursi WHERE id_pesanan = ?"
      );
      $stmtOld->bind_param('i', $editId);
      $stmtOld->execute();
      $oldRows = $stmtOld->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmtOld->close();

      foreach ($oldRows as $oldRow) {
        $oldKursiId = (int) $oldRow['id_kursi'];
        $stmtReset  = $db->prepare("UPDATE kursi SET status = 'kosong' WHERE id_kursi = ?");
        $stmtReset->bind_param('i', $oldKursiId);
        $stmtReset->execute();
        $stmtReset->close();
      }

      // Hapus relasi kursi lama
      $stmt = $db->prepare("DELETE FROM detail_kursi WHERE id_pesanan = ?");
      $stmt->bind_param('i', $editId);
      $stmt->execute();
      $stmt->close();

      // FIX #2 (lanjut) — assign kursi baru jika dine_in
      if ($posted['jenis_pesanan'] === 'dine_in' && !empty($posted['id_kursi'])) {
        $idKursiNew = (int) $posted['id_kursi'];

        $stmt = $db->prepare(
          "INSERT INTO detail_kursi (id_pesanan, id_kursi) VALUES (?, ?)"
        );
        $stmt->bind_param('ii', $editId, $idKursiNew);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare(
          "UPDATE kursi SET status = 'terisi' WHERE id_kursi = ?"
        );
        $stmt->bind_param('i', $idKursiNew);
        $stmt->execute();
        $stmt->close();
      }

      $db->commit();

      // FIX #3 — PRG: redirect agar refresh tidak resubmit
      header('Location: ../pesanan/index.php?success=update');
      exit;
    }

    /* ── PESANAN BARU ── */
    $stmt = $db->prepare(
      "INSERT INTO pesanan (nama_pelanggan, jenis_pesanan, total_pesanan, status)
             VALUES (?, ?, ?, 'menunggu')"
    );
    $stmt->bind_param('ssd', $posted['nama_pelanggan'], $posted['jenis_pesanan'], $total);
    $stmt->execute();
    $idPesanan = $stmt->insert_id;
    $stmt->close();

    if ($posted['jenis_pesanan'] === 'dine_in') {
      $idKursi = (int) $posted['id_kursi'];

      $stmt = $db->prepare(
        "INSERT INTO detail_kursi (id_pesanan, id_kursi) VALUES (?, ?)"
      );
      $stmt->bind_param('ii', $idPesanan, $idKursi);
      $stmt->execute();
      $stmt->close();

      $stmt = $db->prepare(
        "UPDATE kursi SET status = 'terisi' WHERE id_kursi = ? AND status = 'kosong'"
      );
      $stmt->bind_param('i', $idKursi);
      $stmt->execute();

      if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('Kursi sudah terisi. Silakan pilih kursi lain.');
      }
      $stmt->close();
    }

    $stmt = $db->prepare(
      "INSERT INTO detail_pesanan
                 (id_pesanan, id_menu, jumlah, catatan, harga_satuan, subtotal)
             VALUES (?, ?, ?, ?, ?, ?)"
    );
    foreach ($items as $item) {
      $stmt->bind_param(
        'iiisdd',
        $idPesanan,
        $item['id_menu'],
        $item['jumlah'],
        $item['catatan'],
        $item['harga_satuan'],
        $item['subtotal']
      );
      $stmt->execute();
    }
    $stmt->close();

    $db->commit();

    // FIX #3 — PRG untuk pesanan baru
    header('Location: ../pelanggan/index.php?success=1');
    exit;
  } catch (Throwable $e) {
    $db->rollback();
    $errors[] = $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pesan Menu - Moro Kangen</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="pelanggan-page">
  <main class="pelanggan-container">
    <header class="pelanggan-header">
      <h1>Moro Kangen</h1>
      <p>Mie Ayam Bakso - Karanggede, Boyolali</p>
    </header>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <?= $isEdit
          ? 'Pesanan berhasil diperbarui. Silakan tunggu pesanan diproses.'
          : 'Pesanan berhasil dikirim. Silakan tunggu pesanan diproses.' ?>
      </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <?php if ($confirmation): ?>
      <!-- ── Halaman konfirmasi ── -->
      <section class="panel">
        <h2><?= $isEdit ? 'Konfirmasi Tambah Pesanan' : 'Konfirmasi Pesanan' ?></h2>
        <p class="text-muted mb-16">Periksa pesanan sebelum dikirim ke kasir.</p>

        <table class="data-table mb-24">
          <thead>
            <tr>
              <th>Menu</th>
              <th>Jumlah</th>
              <th>Catatan</th>
              <th class="text-right">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= h($item['nama_menu']) ?></td>
                <td><?= $item['jumlah'] ?></td>
                <td><?= h($item['catatan'] ?: '-') ?></td>
                <td class="text-right"><?= rupiah($item['subtotal']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="order-summary order-summary-static">
          <div>
            <strong><?= h($posted['nama_pelanggan']) ?></strong>
            <p class="text-muted">
              <?= $posted['jenis_pesanan'] === 'dine_in' ? 'Dine in' : 'Take away' ?>
            </p>
          </div>
          <div class="order-total">Total <span><?= rupiah($total) ?></span></div>
        </div>

        <form method="POST" class="mt-24">
          <?php if ($isEdit): ?>
            <input type="hidden" name="action" value="submit">
            <!-- Sertakan edit id agar isEdit tetap true saat submit -->
            <input type="hidden" name="_edit_id" value="<?= $editId ?>">
          <?php else: ?>
            <input type="hidden" name="action" value="submit">
          <?php endif; ?>

          <input type="hidden" name="nama_pelanggan" value="<?= h($posted['nama_pelanggan']) ?>">
          <input type="hidden" name="jenis_pesanan" value="<?= h($posted['jenis_pesanan']) ?>">
          <input type="hidden" name="id_kursi" value="<?= h($posted['id_kursi']) ?>">

          <?php foreach ($posted['jumlah'] as $idMenu => $jumlah): ?>
            <input type="hidden"
              name="jumlah[<?= (int) $idMenu ?>]"
              value="<?= (int) $jumlah ?>">
          <?php endforeach; ?>

          <?php foreach ($posted['catatan'] as $idMenu => $catatan): ?>
            <input type="hidden"
              name="catatan[<?= (int) $idMenu ?>]"
              value="<?= h($catatan) ?>">
          <?php endforeach; ?>

          <div class="flex gap-12">
            <button type="submit" class="btn btn-primary">Kirim Pesanan</button>
            <button type="submit" name="action" value="edit" class="btn btn-secondary">
              Ubah Pesanan
            </button>
          </div>
        </form>
      </section>

    <?php else: ?>
      <!-- ── Form pemesanan ── -->
      <form method="POST">
        <input type="hidden" name="action" value="confirm">
        <?php if ($isEdit): ?>
          <!-- Sertakan edit id agar isEdit tetap true saat confirm -->
          <input type="hidden" name="_edit_id" value="<?= $editId ?>">
        <?php endif; ?>

        <section class="panel mb-24">
          <div class="form-row">
            <div class="form-group">
              <label for="nama_pelanggan">Nama Pemesan</label>
              <input type="text"
                id="nama_pelanggan"
                name="nama_pelanggan"
                value="<?= h($posted['nama_pelanggan']) ?>"
                required>
            </div>

            <div class="form-group">
              <label for="jenis_pesanan">Jenis Pesanan</label>
              <select id="jenis_pesanan" name="jenis_pesanan">
                <option value="dine_in"
                  <?= $posted['jenis_pesanan'] === 'dine_in' ? 'selected' : '' ?>>
                  Dine in
                </option>
                <option value="take_away"
                  <?= $posted['jenis_pesanan'] === 'take_away' ? 'selected' : '' ?>>
                  Take away
                </option>
              </select>
            </div>
          </div>
        </section>

        <section class="panel mb-24">
          <h2>Pilih Kursi</h2>
          <p class="text-muted mb-16">Untuk take away, bagian kursi boleh dikosongkan.</p>

          <?php
          // Ambil sekali semua id_kursi milik pesanan ini (jika edit),
          // lalu gunakan array untuk cek — tidak perlu query per kursi.
          $kursiMilikPesanan = [];
          if ($isEdit) {
            $stmtMilik = $db->prepare(
              "SELECT id_kursi FROM detail_kursi WHERE id_pesanan = ?"
            );
            $stmtMilik->bind_param('i', $editId);
            $stmtMilik->execute();
            $resMilik = $stmtMilik->get_result();
            while ($mRow = $resMilik->fetch_assoc()) {
              $kursiMilikPesanan[(int) $mRow['id_kursi']] = true;
            }
            $stmtMilik->close();
          }

          $grupKursi = [];
          foreach ($kursi as $row) {
            preg_match('/^[A-Z]+/', $row['nomor_kursi'], $match);
            $huruf             = $match[0] ?? 'LAIN';
            $grupKursi[$huruf][] = $row;
          }
          $pasanganGrup = array_chunk(array_keys($grupKursi), 2);
          ?>

          <?php foreach ($pasanganGrup as $pair): ?>
            <div class="denah-grid area-kursi">
              <?php foreach ($pair as $huruf): ?>
                <div class="denah-kelompok-wrap">
                  <h4 class="judul-grup">Meja <?= h($huruf) ?></h4>
                  <div class="denah-kelompok">
                    <?php foreach ($grupKursi[$huruf] as $row): ?>
                      <?php
                      $isKosong       = $row['status'] === 'kosong';
                      $isMilikPesanan = isset($kursiMilikPesanan[(int) $row['id_kursi']]);
                      $bisaDipilih    = $isKosong || $isMilikPesanan;
                      ?>
                      <label class="kursi-choice">
                        <input
                          type="radio"
                          name="id_kursi"
                          value="<?= (int) $row['id_kursi'] ?>"
                          <?= !$bisaDipilih ? 'disabled' : '' ?>
                          <?= (string) $posted['id_kursi'] === (string) $row['id_kursi'] ? 'checked' : '' ?>>
                        <span class="kursi-box <?= $bisaDipilih ? 'kosong' : 'terisi' ?>">
                          <span><?= h($row['nomor_kursi']) ?></span>
                          <small><?= $bisaDipilih ? 'Kosong' : 'Terisi' ?></small>
                        </span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </section>

        <?php
        $menuMakanan = [];
        $menuMinuman = [];
        foreach ($menus as $menu) {
          if (strtolower($menu['kategori']) === 'minuman') {
            $menuMinuman[] = $menu;
          } else {
            $menuMakanan[] = $menu;
          }
        }

        // Helper untuk render kartu menu (hindari duplikasi kode)
        $renderMenuCard = function ($menu) use ($posted, $isEdit, &$db, $editId) {
          $idMenu      = (int) $menu['id_menu'];
          $isAvailable = $menu['status_menu'] === 'tersedia';
          $jumlah      = (int) ($posted['jumlah'][$idMenu] ?? 0);
          $catatan     = $posted['catatan'][$idMenu] ?? '';

          $imageName = strtolower(str_replace(' ', '-', $menu['nama_menu'])) . '.jpg';
          $imagePath = '../img/' . $imageName;
          if (!file_exists(__DIR__ . '/../img/' . $imageName)) {
            $imagePath = '../img/default-food.jpg';
          }
        ?>
          <section class="menu-card <?= !$isAvailable ? 'menu-habis' : '' ?>">
            <img src="<?= h($imagePath) ?>"
              alt="<?= h($menu['nama_menu']) ?>"
              class="menu-image">
            <div class="menu-content">
              <?php if (!empty($menu['kategori'])): ?>
                <p class="menu-kategori"><?= h($menu['kategori']) ?></p>
              <?php endif; ?>
              <h3 class="menu-nama"><?= h($menu['nama_menu']) ?></h3>
              <strong class="menu-harga"><?= rupiah($menu['harga']) ?></strong>
              <div class="menu-status-wrapper">
                <span class="menu-status-badge status-<?= h($menu['status_menu']) ?>">
                  <?= ucfirst(h($menu['status_menu'])) ?>
                </span>
              </div>
              <div class="form-group mt-16">
                <label for="jumlah_<?= $idMenu ?>">Jumlah</label>
                <input
                  type="number"
                  id="jumlah_<?= $idMenu ?>"
                  name="jumlah[<?= $idMenu ?>]"
                  value="<?= $jumlah ?>"
                  min="0"
                  max="99"
                  <?= !$isAvailable ? 'disabled' : '' ?>>
              </div>
              <div class="form-group">
                <label for="catatan_<?= $idMenu ?>">Catatan</label>
                <textarea
                  id="catatan_<?= $idMenu ?>"
                  name="catatan[<?= $idMenu ?>]"
                  rows="2"
                  <?= !$isAvailable ? 'disabled' : '' ?>><?= h($catatan) ?></textarea>
              </div>
            </div>
          </section>
        <?php
        };
        ?>

        <section class="panel mb-24">
          <h2>Pilih Menu</h2>
          <p class="text-muted mb-16">Menu habis tidak bisa dipilih.</p>

          <h3 class="kategori-judul">Kategori Makanan</h3>
          <div class="menu-grid">
            <?php foreach ($menuMakanan as $menu): $renderMenuCard($menu);
            endforeach; ?>
          </div>

          <h3 class="kategori-judul">Kategori Minuman</h3>
          <div class="menu-grid">
            <?php foreach ($menuMinuman as $menu): $renderMenuCard($menu);
            endforeach; ?>
          </div>
        </section>

        <div class="order-summary">
          <div>
            <strong><?= $isEdit ? 'Edit Pesanan' : 'Pesanan Baru' ?></strong>
            <p class="text-muted">Total dihitung setelah tombol lanjut ditekan.</p>
          </div>
          <button type="submit" class="btn btn-primary">
            <?= $isEdit ? 'Lanjut Konfirmasi' : 'Lanjut Konfirmasi' ?>
          </button>
        </div>
      </form>
    <?php endif; ?>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const jenisPesanan = document.getElementById('jenis_pesanan');
      const areaKursi = document.querySelectorAll('.area-kursi');
      const radioKursi = document.querySelectorAll('input[name="id_kursi"]');

      function toggleKursi() {
        if (jenisPesanan.value === 'take_away') {
          areaKursi.forEach(function(item) {
            item.classList.add('disabled');
          });
          radioKursi.forEach(function(radio) {
            radio.checked = false;
          });
        } else {
          areaKursi.forEach(function(item) {
            item.classList.remove('disabled');
          });
        }
      }

      toggleKursi();
      jenisPesanan.addEventListener('change', toggleKursi);

      // Hilangkan ?success=1 dari URL agar notifikasi hilang saat refresh
      if (window.location.search.includes('success')) {
        const url = new URL(window.location.href);
        url.searchParams.delete('success');
        window.history.replaceState({}, document.title, url.toString());
      }
    });
  </script>
</body>

</html>