<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Menu';
$baseUrl = '../';

$db = getDB();

$message = '';
$error = '';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value)
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$allowedStatus = ['tersedia', 'habis', 'nonaktif'];

$formData = [
    'id_menu' => 0,
    'nama_menu' => '',
    'kategori' => '',
    'harga' => '',
    'deskripsi' => '',
    'status_menu' => 'tersedia'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isKaryawan()) {

    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {

        $idMenu = (int) ($_POST['id_menu'] ?? 0);

        $namaMenu = trim($_POST['nama_menu'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $harga = (float) ($_POST['harga'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $statusMenu = $_POST['status_menu'] ?? 'tersedia';

        $formData = [
            'id_menu' => $idMenu,
            'nama_menu' => $namaMenu,
            'kategori' => $kategori,
            'harga' => $harga,
            'deskripsi' => $deskripsi,
            'status_menu' => $statusMenu
        ];

        if ($namaMenu === '') {
            $error = 'Nama menu wajib diisi.';
        } elseif ($kategori === '') {
            $error = 'Kategori wajib diisi.';
        } elseif ($harga <= 0) {
            $error = 'Harga harus lebih dari 0.';
        } elseif (!in_array($statusMenu, $allowedStatus, true)) {
            $error = 'Status menu tidak valid.';
        } else {

            if ($action === 'create') {

                $stmt = $db->prepare("
                    SELECT id_menu
                    FROM menu
                    WHERE nama_menu = ?
                    LIMIT 1
                ");

                $stmt->bind_param('s', $namaMenu);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

            } else {

                $stmt = $db->prepare("
                    SELECT id_menu
                    FROM menu
                    WHERE nama_menu = ?
                    AND id_menu != ?
                    LIMIT 1
                ");

                $stmt->bind_param('si', $namaMenu, $idMenu);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            if ($exists) {

                $error = 'Nama menu sudah digunakan.';

            } else {

                if ($action === 'create') {

                    $stmt = $db->prepare("
                        INSERT INTO menu
                        (
                            nama_menu,
                            kategori,
                            harga,
                            deskripsi,
                            status_menu
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    $stmt->bind_param(
                        'ssdss',
                        $namaMenu,
                        $kategori,
                        $harga,
                        $deskripsi,
                        $statusMenu
                    );

                    $stmt->execute();
                    $stmt->close();

                    $message = 'Menu berhasil ditambahkan.';

                    $formData = [
                        'id_menu' => 0,
                        'nama_menu' => '',
                        'kategori' => '',
                        'harga' => '',
                        'deskripsi' => '',
                        'status_menu' => 'tersedia'
                    ];

                } else {

                    $stmt = $db->prepare("
                        UPDATE menu
                        SET
                            nama_menu = ?,
                            kategori = ?,
                            harga = ?,
                            deskripsi = ?,
                            status_menu = ?
                        WHERE id_menu = ?
                    ");

                    $stmt->bind_param(
                        'ssdssi',
                        $namaMenu,
                        $kategori,
                        $harga,
                        $deskripsi,
                        $statusMenu,
                        $idMenu
                    );

                    $stmt->execute();
                    $stmt->close();

                    $message = 'Menu berhasil diperbarui.';
                }
            }
        }

    } elseif ($action === 'change_status') {

        $idMenu = (int) ($_POST['id_menu'] ?? 0);
        $statusMenu = $_POST['status_menu'] ?? '';

        if (!in_array($statusMenu, $allowedStatus, true)) {

            $error = 'Status menu tidak valid.';

        } else {

            $stmt = $db->prepare("
                UPDATE menu
                SET status_menu = ?
                WHERE id_menu = ?
            ");

            $stmt->bind_param('si', $statusMenu, $idMenu);
            $stmt->execute();
            $stmt->close();

            $message = 'Status menu berhasil diperbarui.';
        }
    }
}

if (isset($_GET['edit'])) {

    $idEdit = (int) $_GET['edit'];

    $stmt = $db->prepare("
        SELECT *
        FROM menu
        WHERE id_menu = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $idEdit);
    $stmt->execute();

    $menuEdit = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if ($menuEdit) {

        $formData = [
            'id_menu' => $menuEdit['id_menu'],
            'nama_menu' => $menuEdit['nama_menu'],
            'kategori' => $menuEdit['kategori'],
            'harga' => $menuEdit['harga'],
            'deskripsi' => $menuEdit['deskripsi'],
            'status_menu' => $menuEdit['status_menu']
        ];
    }
}

$menus = [];

$result = $db->query("
    SELECT *
    FROM menu
    ORDER BY nama_menu ASC
");

while ($row = $result->fetch_assoc()) {
    $menus[] = $row;
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message !== ''): ?>
    <div class="alert alert-success">
        <?= h($message) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<?php if (isKaryawan()): ?>

    <!-- ==================== BAGIAN: GALERI PREVIEW VISUAL BERDASARKAN KATEGORI ==================== -->
    <div class="menu-preview-section" style="margin-bottom: 35px;">
        
        <!-- 1. KATEGORI MAKANAN -->
        <h4 style="margin-bottom: 12px; color: #2d3748; font-weight: 600; border-left: 4px solid #e67e22; padding-left: 8px;">Kategori Makanan</h4>
        <div class="menu-gallery-row" style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 25px;">
            <?php 
            $hasMakanan = false;
            foreach ($menus as $menu): 
                // Jika kategorinya adalah minuman, lewati (karena ini baris makanan)
                if (strtolower($menu['kategori']) === 'minuman') continue;
                $hasMakanan = true;

                $imageName = strtolower(str_replace(' ', '-', $menu['nama_menu'])) . '.jpg';
                $imagePath = '../img/' . $imageName;

                if (!file_exists(__DIR__ . '/../img/' . $imageName)) {
                    $imagePath = '../img/default-food.jpg';
                }
            ?>
                <div class="menu-preview-card" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); width: 190px; overflow: hidden; border: 1px solid #e2e8f0;">
                    <img src="<?= h($imagePath) ?>" alt="<?= h($menu['nama_menu']) ?>" style="width: 100%; height: 130px; object-fit: cover;">
                    <div style="padding: 12px;">
                        <span style="font-size: 10px; color: #a0aec0; text-transform: uppercase; font-weight: bold;"><?= h($menu['kategori']) ?></span>
                        <h4 style="margin: 4px 0; font-size: 14px; color: #2d3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= h($menu['nama_menu']) ?>">
                            <?= h($menu['nama_menu']) ?>
                        </h4>
                        <p style="margin: 0 0 10px 0; font-weight: bold; color: #e67e22; font-size: 13px;"><?= rupiah($menu['harga']) ?></p>
                        <span class="badge badge-<?= h($menu['status_menu']) ?>" style="font-size: 10px; padding: 2px 6px; border-radius: 4px;">
                            <?= h($menu['status_menu']) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (!$hasMakanan): ?>
                <p style="color: #a0aec0; font-style: italic; font-size: 13px; margin-left: 5px;">Belum ada data menu makanan.</p>
            <?php endif; ?>
        </div>

        <!-- 2. KATEGORI MINUMAN -->
        <h4 style="margin-bottom: 12px; color: #2d3748; font-weight: 600; border-left: 4px solid #3498db; padding-left: 8px;">Kategori Minuman</h4>
        <div class="menu-gallery-row" style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 10px;">
            <?php 
            $hasMinuman = false;
            foreach ($menus as $menu): 
                // Hanya tampilkan jika kata kategorinya tepat "minuman"
                if (strtolower($menu['kategori']) !== 'minuman') continue;
                $hasMinuman = true;

                $imageName = strtolower(str_replace(' ', '-', $menu['nama_menu'])) . '.jpg';
                $imagePath = '../img/' . $imageName;

                if (!file_exists(__DIR__ . '/../img/' . $imageName)) {
                    $imagePath = '../img/default-food.jpg';
                }
            ?>
                <div class="menu-preview-card" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); width: 190px; overflow: hidden; border: 1px solid #e2e8f0;">
                    <img src="<?= h($imagePath) ?>" alt="<?= h($menu['nama_menu']) ?>" style="width: 100%; height: 130px; object-fit: cover;">
                    <div style="padding: 12px;">
                        <span style="font-size: 10px; color: #a0aec0; text-transform: uppercase; font-weight: bold;"><?= h($menu['kategori']) ?></span>
                        <h4 style="margin: 4px 0; font-size: 14px; color: #2d3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= h($menu['nama_menu']) ?>">
                            <?= h($menu['nama_menu']) ?>
                        </h4>
                        <p style="margin: 0 0 10px 0; font-weight: bold; color: #e67e22; font-size: 13px;"><?= rupiah($menu['harga']) ?></p>
                        <span class="badge badge-<?= h($menu['status_menu']) ?>" style="font-size: 10px; padding: 2px 6px; border-radius: 4px;">
                            <?= h($menu['status_menu']) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$hasMinuman): ?>
                <p style="color: #a0aec0; font-style: italic; font-size: 13px; margin-left: 5px;">Belum ada data menu minuman.</p>
            <?php endif; ?>
        </div>

    </div>
    <!-- ==================== END BAGIAN: GALERI PREVIEW VISUAL ==================== -->

<div class="card">

    <div class="card-header">
        <h3 class="card-title">
            <?= $formData['id_menu'] > 0 ? 'Edit Menu' : 'Tambah Menu' ?>
        </h3>
    </div>

    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="<?= $formData['id_menu'] > 0 ? 'update' : 'create' ?>"
        >

        <input
            type="hidden"
            name="id_menu"
            value="<?= (int) $formData['id_menu'] ?>"
        >

        <div class="form-row">

            <div class="form-group">
                <label>Nama Menu</label>
                <input
                    type="text"
                    name="nama_menu"
                    value="<?= h($formData['nama_menu']) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Kategori</label>
                <input
                    type="text"
                    name="kategori"
                    value="<?= h($formData['kategori']) ?>"
                    placeholder="Ketik 'Minuman' atau kategori makanan (Bakso/Mie)"
                    required
                >
            </div>

        </div>

        <div class="form-row">

            <div class="form-group">
                <label>Harga</label>
                <input
                    type="number"
                    name="harga"
                    min="1"
                    value="<?= h($formData['harga']) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status_menu">

                    <option value="tersedia"
                        <?= $formData['status_menu'] === 'tersedia' ? 'selected' : '' ?>>
                        Tersedia
                    </option>

                    <option value="habis"
                        <?= $formData['status_menu'] === 'habis' ? 'selected' : '' ?>>
                        Habis
                    </option>

                    <option value="nonaktif"
                        <?= $formData['status_menu'] === 'nonaktif' ? 'selected' : '' ?>>
                        Nonaktif
                    </option>

                </select>
            </div>

        </div>

        <div class="form-group">
            <label>Deskripsi</label>

            <textarea
                name="deskripsi"
                rows="4"
            ><?= h($formData['deskripsi']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">
            <?= $formData['id_menu'] > 0 ? 'Simpan Perubahan' : 'Tambah Menu' ?>
        </button>

        <?php if ($formData['id_menu'] > 0): ?>
            <a href="index.php" class="btn btn-secondary">
                Batal
            </a>
        <?php endif; ?>

    </form>

</div>
<?php endif; ?>

<div class="card">

    <div class="card-header">
        <h3 class="card-title">Daftar Menu</h3>
    </div>

    <?php if (empty($menus)): ?>

        <div class="empty-state">
            <h3>Belum ada menu</h3>
            <p>Silakan tambahkan menu terlebih dahulu.</p>
        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table>

                <thead>
                <tr>
                    <th>Nama Menu</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Status</th>
                    <th>Terakhir Diubah</th>
                    <th>Aksi</th>
                </tr>
                </thead>

                <tbody>

                <?php foreach ($menus as $menu): ?>

                    <tr>

                        <td><?= h($menu['nama_menu']) ?></td>

                        <td><?= h($menu['kategori']) ?></td>

                        <td><?= rupiah($menu['harga']) ?></td>

                        <td>
                            <span class="badge badge-<?= h($menu['status_menu']) ?>">
                                <?= h($menu['status_menu']) ?>
                            </span>
                        </td>

                        <td>
                            <?= date('d/m/Y H:i', strtotime($menu['updated_at'])) ?>
                        </td>

                        <td>

                            <?php if (isKaryawan()): ?>

                                <div class="td-actions">

                                    <a
                                        href="?edit=<?= (int) $menu['id_menu'] ?>"
                                        class="btn btn-secondary btn-sm"
                                    >
                                        Edit
                                    </a>

                                    <?php if ($menu['status_menu'] === 'tersedia'): ?>

                                        <form method="POST">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="id_menu" value="<?= (int) $menu['id_menu'] ?>">
                                            <input type="hidden" name="status_menu" value="habis">
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                Habis
                                            </button>
                                        </form>

                                        <form method="POST">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="id_menu" value="<?= (int) $menu['id_menu'] ?>">
                                            <input type="hidden" name="status_menu" value="nonaktif">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                Nonaktifkan
                                            </button>
                                        </form>

                                    <?php elseif ($menu['status_menu'] === 'habis'): ?>

                                        <form method="POST">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="id_menu" value="<?= (int) $menu['id_menu'] ?>">
                                            <input type="hidden" name="status_menu" value="tersedia">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                Tersedia
                                            </button>
                                        </form>

                                        <form method="POST">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="id_menu" value="<?= (int) $menu['id_menu'] ?>">
                                            <input type="hidden" name="status_menu" value="nonaktif">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                Nonaktifkan
                                            </button>
                                        </form>

                                    <?php else: ?>

                                        <form method="POST">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="id_menu" value="<?= (int) $menu['id_menu'] ?>">
                                            <input type="hidden" name="status_menu" value="tersedia">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                Aktifkan
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </div>

                            <?php else: ?>

                                <span class="text-muted">
                                    Lihat saja
                                </span>

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