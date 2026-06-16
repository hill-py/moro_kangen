<?php
require_once __DIR__ . '/../config/database.php';

$db = getDB();

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value) {
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function getMenus($db) {
    $menus = [];
    $result = $db->query("SELECT id_menu, nama_menu, kategori, harga, status_menu FROM menu WHERE status_menu <> 'nonaktif' ORDER BY kategori, nama_menu");
    while ($row = $result->fetch_assoc()) {
        $menus[(int) $row['id_menu']] = $row;
    }
    return $menus;
}

function getKursi($db) {
    $kursi = [];
    $result = $db->query("SELECT id_kursi, nomor_kursi, status FROM kursi ORDER BY nomor_kursi");
    while ($row = $result->fetch_assoc()) {
        $kursi[] = $row;
    }
    return $kursi;
}

function buildOrderItems($menus, $post) {
    $items = [];
    $total = 0;

    foreach (($post['jumlah'] ?? []) as $idMenu => $jumlah) {
        $idMenu = (int) $idMenu;
        $jumlah = max(0, (int) $jumlah);

        if ($jumlah < 1 || !isset($menus[$idMenu]) || $menus[$idMenu]['status_menu'] !== 'tersedia') {
            continue;
        }

        $catatan = trim($post['catatan'][$idMenu] ?? '');
        $harga = (float) $menus[$idMenu]['harga'];
        $subtotal = $harga * $jumlah;
        $total += $subtotal;

        $items[] = [
            'id_menu' => $idMenu,
            'nama_menu' => $menus[$idMenu]['nama_menu'],
            'jumlah' => $jumlah,
            'catatan' => $catatan,
            'harga_satuan' => $harga,
            'subtotal' => $subtotal,
        ];
    }

    return [$items, $total];
}

$menus = getMenus($db);
$kursi = getKursi($db);
$editId = (int) ($_GET['edit'] ?? 0);
$isEdit = $editId > 0;
$action = $_POST['action'] ?? '';
$errors = [];
$success = false;
$confirmation = false;
$posted = [
    'nama_pelanggan' => trim($_POST['nama_pelanggan'] ?? ''),
    'jenis_pesanan' => $_POST['jenis_pesanan'] ?? 'dine_in',
    'id_kursi' => $_POST['id_kursi'] ?? '',
    'jumlah' => $_POST['jumlah'] ?? [],
    'catatan' => $_POST['catatan'] ?? [],
];

if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {

    $stmt = $db->prepare("
        SELECT *
        FROM pesanan
        WHERE id_pesanan = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        'i',
        $editId
    );

    $stmt->execute();

    $pesanan = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();

    if ($pesanan) {

        $posted['nama_pelanggan']
            = $pesanan['nama_pelanggan'];

        $posted['jenis_pesanan']
            = $pesanan['jenis_pesanan'];

        $stmtKursi = $db->prepare("
    SELECT id_kursi
    FROM detail_kursi
    WHERE id_pesanan = ?
    LIMIT 1
");

$stmtKursi->bind_param(
    'i',
    $editId
);

$stmtKursi->execute();

$kursiLama = $stmtKursi
    ->get_result()
    ->fetch_assoc();

$stmtKursi->close();

if ($kursiLama) {

    $posted['id_kursi']
        = $kursiLama['id_kursi'];

}

        $stmt = $db->prepare("
            SELECT
                id_menu,
                jumlah,
                catatan
            FROM detail_pesanan
            WHERE id_pesanan = ?
        ");

        $stmt->bind_param(
            'i',
            $editId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        while ($row =
            $result->fetch_assoc()) {

            $posted['jumlah']
                [$row['id_menu']]
                = $row['jumlah'];

            $posted['catatan']
                [$row['id_menu']]
                = $row['catatan'];
        }

        $stmt->close();
    }
}

[$items, $total] = buildOrderItems($menus, $posted);

if ($action === 'confirm' || $action === 'submit') {
    if ($posted['nama_pelanggan'] === '') {
        $errors[] = 'Nama pemesan wajib diisi.';
    }

    if (!in_array($posted['jenis_pesanan'], ['dine_in', 'take_away'], true)) {
        $errors[] = 'Jenis pesanan tidak valid.';
    }

    if ($posted['jenis_pesanan'] === 'dine_in') {

    $idKursi = (int) $posted['id_kursi'];

    $stmt = $db->prepare("
        SELECT status
        FROM kursi
        WHERE id_kursi = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        'i',
        $idKursi
    );

    $stmt->execute();

    $selectedKursi =
        $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$isEdit) {

        if (
            !$selectedKursi ||
            $selectedKursi['status'] !== 'kosong'
        ) {
            $errors[] =
                'Pilih kursi yang masih kosong.';
        }

    }
}
    }

    if ($action === 'confirm' || $action === 'submit') {

    if (count($items) === 0) {
        $errors[] = 'Pilih minimal satu menu yang tersedia.';
    }

    $confirmation =
        $action === 'confirm'
        && count($errors) === 0;
}

if ($action === 'submit' && count($errors) === 0) {
    $db->begin_transaction();

    try {
      if ($isEdit) {

    $stmt = $db->prepare("
        DELETE FROM detail_pesanan
        WHERE id_pesanan = ?
    ");

    $stmt->bind_param(
        'i',
        $editId
    );

    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("
        INSERT INTO detail_pesanan
        (
            id_pesanan,
            id_menu,
            jumlah,
            catatan,
            harga_satuan,
            subtotal
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

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

    $stmt = $db->prepare("
        UPDATE pesanan
        SET total_pesanan = ?
        WHERE id_pesanan = ?
    ");

    $stmt->bind_param(
        'di',
        $total,
        $editId
    );

    $stmt->execute();
    $stmt->close();

    $db->commit();

    header('Location: ../pesanan/index.php?success=update');
    exit;
}
        $stmt = $db->prepare("INSERT INTO pesanan (nama_pelanggan, jenis_pesanan, total_pesanan, status) VALUES (?, ?, ?, 'menunggu')");
        $stmt->bind_param('ssd', $posted['nama_pelanggan'], $posted['jenis_pesanan'], $total);
        $stmt->execute();
        $idPesanan = $stmt->insert_id;
        $stmt->close();

        if ($posted['jenis_pesanan'] === 'dine_in') {
            $idKursi = (int) $posted['id_kursi'];

            $stmt = $db->prepare("INSERT INTO detail_kursi (id_pesanan, id_kursi) VALUES (?, ?)");
            $stmt->bind_param('ii', $idPesanan, $idKursi);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare("UPDATE kursi SET status = 'terisi' WHERE id_kursi = ? AND status = 'kosong'");
            $stmt->bind_param('i', $idKursi);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException('Kursi sudah terisi. Silakan pilih kursi lain.');
            }

            $stmt->close();
        }

        $stmt = $db->prepare("INSERT INTO detail_pesanan (id_pesanan, id_menu, jumlah, catatan, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?, ?)");

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

        $success = true;
        $posted = [
            'nama_pelanggan' => '',
            'jenis_pesanan' => 'dine_in',
            'id_kursi' => '',
            'jumlah' => [],
            'catatan' => [],
        ];
        $items = [];
        $total = 0;
        $kursi = getKursi($db);
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
      <div class="alert alert-success">Pesanan berhasil dikirim. Silakan tunggu pesanan diproses.</div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <?php if ($confirmation): ?>
      <section class="panel">
        <h2> 
          <?= $isEdit ? 'Konfirmasi Tambah Pesanan' : 'Konfirmasi Pesanan' ?>
        </h2>
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
            <p class="text-muted"><?= $posted['jenis_pesanan'] === 'dine_in' ? 'Dine in' : 'Take away' ?></p>
          </div>
          <div class="order-total">Total <span><?= rupiah($total) ?></span></div>
        </div>

        <form method="POST" class="mt-24">
          <input type="hidden" name="action" value="submit">
          <input type="hidden" name="nama_pelanggan" value="<?= h($posted['nama_pelanggan']) ?>">
          <input type="hidden" name="jenis_pesanan" value="<?= h($posted['jenis_pesanan']) ?>">
          <input type="hidden" name="id_kursi" value="<?= h($posted['id_kursi']) ?>">

          <?php foreach ($posted['jumlah'] as $idMenu => $jumlah): ?>
            <input type="hidden" name="jumlah[<?= (int) $idMenu ?>]" value="<?= (int) $jumlah ?>">
          <?php endforeach; ?>

          <?php foreach ($posted['catatan'] as $idMenu => $catatan): ?>
            <input type="hidden" name="catatan[<?= (int) $idMenu ?>]" value="<?= h($catatan) ?>">
          <?php endforeach; ?>

          <div class="flex gap-12">
            <button type="submit" class="btn btn-primary">Kirim Pesanan</button>
            <button type="submit" name="action" value="edit" class="btn btn-secondary">Ubah Pesanan</button>
          </div>
        </form>
      </section>
    <?php else: ?>
      <form method="POST">
        <input type="hidden" name="action" value="confirm">

        <section class="panel mb-24">
          <div class="form-row">
            <div class="form-group">
              <label for="nama_pelanggan">Nama Pemesan</label>
              <input type="text" id="nama_pelanggan" name="nama_pelanggan" value="<?= h($posted['nama_pelanggan']) ?>" required>
            </div>

            <div class="form-group">
              <label for="jenis_pesanan">Jenis Pesanan</label>
              <select id="jenis_pesanan" name="jenis_pesanan">
                <option value="dine_in" <?= $posted['jenis_pesanan'] === 'dine_in' ? 'selected' : '' ?>>Dine in</option>
                <option value="take_away" <?= $posted['jenis_pesanan'] === 'take_away' ? 'selected' : '' ?>>Take away</option>
              </select>
            </div>
          </div>
        </section>

        <section class="panel mb-24">
          <h2>Pilih Kursi</h2>
          <p class="text-muted mb-16">Untuk take away, bagian kursi boleh dikosongkan.</p>

          <?php
$grupKursi = [];

foreach ($kursi as $row) {

    preg_match('/^[A-Z]+/', $row['nomor_kursi'], $match);

    $huruf = $match[0] ?? 'LAIN';

    $grupKursi[$huruf][] = $row;
}

$pasanganGrup = array_chunk(
    array_keys($grupKursi),
    2
);
?>

<?php foreach ($pasanganGrup as $pair): ?>

<div class="denah-grid area-kursi">

    <?php foreach ($pair as $huruf): ?>

        <div class="denah-kelompok-wrap">

            <h4 class="judul-grup">
                Meja <?= h($huruf) ?>
            </h4>

            <div class="denah-kelompok">

                <?php foreach ($grupKursi[$huruf] as $row): ?>

                    <?php $isKosong = $row['status'] === 'kosong'; ?>

                    <label class="kursi-choice">

                        <input
                            type="radio"
                            name="id_kursi"
                            value="<?= (int) $row['id_kursi'] ?>"
                            <?= !$isKosong ? 'disabled' : '' ?>
                            <?= (string) $posted['id_kursi'] === (string) $row['id_kursi'] ? 'checked' : '' ?>
                        >

                        <span class="kursi-box <?= $isKosong ? 'kosong' : 'terisi' ?>">
                            <span><?= h($row['nomor_kursi']) ?></span>
                            <small><?= $isKosong ? 'Kosong' : 'Terisi' ?></small>
                        </span>

                    </label>

                <?php endforeach; ?>

            </div>

        </div>

    <?php endforeach; ?>

</div>

<?php endforeach; ?>

        </section>

        <section class="panel mb-24">
          <h2>Pilih Menu</h2>
          <p class="text-muted mb-16">Menu habis tidak bisa dipilih.</p>

          <div class="menu-grid">
            <?php foreach ($menus as $menu): ?>
              <?php
                $idMenu = (int) $menu['id_menu'];
                $isAvailable = $menu['status_menu'] === 'tersedia';
                $jumlah = (int) ($posted['jumlah'][$idMenu] ?? 0);
                $catatan = $posted['catatan'][$idMenu] ?? '';
              ?>
              <section class="menu-card <?= !$isAvailable ? 'menu-habis' : '' ?>">
                <h3 class="menu-nama"><?= h($menu['nama_menu']) ?></h3>
                <p class="menu-kategori"><?= h($menu['kategori']) ?></p>
                <strong class="menu-harga"><?= rupiah($menu['harga']) ?></strong>
                <p class="text-muted mt-8"><?= $isAvailable ? 'Tersedia' : 'Habis' ?></p>

                <div class="form-group mt-16">
                  <label for="jumlah_<?= $idMenu ?>">Jumlah</label>
                  <input
                    type="number"
                    id="jumlah_<?= $idMenu ?>"
                    name="jumlah[<?= $idMenu ?>]"
                    value="<?= $jumlah ?>"
                    min="0"
                    max="99"
                    <?= !$isAvailable ? 'disabled' : '' ?>
                  >
                </div>

                <div class="form-group">
                  <label for="catatan_<?= $idMenu ?>">Catatan</label>
                  <textarea
                    id="catatan_<?= $idMenu ?>"
                    name="catatan[<?= $idMenu ?>]"
                    rows="2"
                    <?= !$isAvailable ? 'disabled' : '' ?>
                  ><?= h($catatan) ?></textarea>
                </div>
              </section>
            <?php endforeach; ?>
          </div>
        </section>

        <div class="order-summary">
          <div>
            <strong>
              <?= $isEdit ? 'Tambah Pesanan' : 'Pesanan Baru' ?>
            </strong>
            <p class="text-muted">Total dihitung setelah tombol lanjut ditekan.</p>
          </div>
          <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Tambah Pesanan' : 'Lanjut Konfirmasi' ?>
          </button>
        </div>
      </form>
    <?php endif; ?>
  </main>
  <script>

document.addEventListener('DOMContentLoaded', function () {

    const jenisPesanan =
        document.getElementById('jenis_pesanan');

    const areaKursi =
        document.querySelectorAll('.area-kursi');

    const radioKursi =
        document.querySelectorAll(
            'input[name="id_kursi"]'
        );

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

    jenisPesanan.addEventListener(
        'change',
        toggleKursi
    );

});

</script>
</body>
</html>
