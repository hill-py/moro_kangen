<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Kursi';
$baseUrl = '../';

$db = getDB();

$message = '';
$error = '';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isKaryawan()) {

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {

        $nomorKursi = trim($_POST['nomor_kursi'] ?? '');

        if ($nomorKursi === '') {

            $error = 'Nomor kursi wajib diisi.';

        } else {

            $stmt = $db->prepare("
                SELECT id_kursi
                FROM kursi
                WHERE nomor_kursi = ?
                LIMIT 1
            ");

            $stmt->bind_param('s', $nomorKursi);
            $stmt->execute();

            $exists = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if ($exists) {

                $error = 'Nomor kursi sudah digunakan.';

            } else {

                $stmt = $db->prepare("
                    INSERT INTO kursi
                    (
                        nomor_kursi,
                        status
                    )
                    VALUES
                    (
                        ?,
                        'kosong'
                    )
                ");

                $stmt->bind_param('s', $nomorKursi);
                $stmt->execute();
                $stmt->close();

                $message = 'Kursi berhasil ditambahkan.';
            }
        }
    }

    if ($action === 'change_status') {

        $idKursi = (int) ($_POST['id_kursi'] ?? 0);
        $statusBaru = $_POST['status'] ?? '';

        $stmt = $db->prepare("
            SELECT status
            FROM kursi
            WHERE id_kursi = ?
            LIMIT 1
        ");

        $stmt->bind_param('i', $idKursi);
        $stmt->execute();

        $kursi = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$kursi) {

            $error = 'Kursi tidak ditemukan.';

        } elseif ($kursi['status'] === 'terisi') {

            $error = 'Kursi yang sedang terisi tidak dapat diubah.';

        } elseif (!in_array($statusBaru, ['kosong', 'nonaktif'], true)) {

            $error = 'Status tidak valid.';

        } else {

            $stmt = $db->prepare("
                UPDATE kursi
                SET status = ?
                WHERE id_kursi = ?
            ");

            $stmt->bind_param(
                'si',
                $statusBaru,
                $idKursi
            );

            $stmt->execute();
            $stmt->close();

            $message = 'Status kursi berhasil diperbarui.';
        }
    }
}

$kursiList = [];

$result = $db->query("
    SELECT
        id_kursi,
        nomor_kursi,
        status
    FROM kursi
    ORDER BY nomor_kursi
");

while ($row = $result->fetch_assoc()) {
    $kursiList[] = $row;
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

<div class="card">

    <div class="card-header">
        <h3 class="card-title">
            Tambah Kursi
        </h3>
    </div>

    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="add"
        >

        <div class="form-group">

            <label>Nomor Kursi</label>

            <input
                type="text"
                name="nomor_kursi"
                required
                placeholder="Contoh: A5"
            >

        </div>

        <button
            type="submit"
            class="btn btn-primary"
        >
            Tambah Kursi
        </button>

    </form>

</div>

<?php endif; ?>

<div class="card">

    <div class="card-header">
        <h3 class="card-title">
            Daftar Kursi
        </h3>
    </div>

    <?php if (empty($kursiList)): ?>

        <div class="empty-state">
            <h3>Belum Ada Kursi</h3>
            <p>Tambahkan kursi terlebih dahulu.</p>
        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Nomor Kursi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($kursiList as $kursi): ?>

                    <tr>

                        <td>
                            <?= h($kursi['nomor_kursi']) ?>
                        </td>

                        <td>
                            <span class="badge badge-<?= h($kursi['status']) ?>">
                                <?= h($kursi['status']) ?>
                            </span>
                        </td>

                        <td>

                            <?php if (isKaryawan()): ?>

                                <?php if ($kursi['status'] === 'kosong'): ?>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="change_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_kursi"
                                            value="<?= (int) $kursi['id_kursi'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="nonaktif"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-secondary btn-sm"
                                        >
                                            Nonaktifkan
                                        </button>

                                    </form>

                                <?php elseif ($kursi['status'] === 'nonaktif'): ?>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="change_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_kursi"
                                            value="<?= (int) $kursi['id_kursi'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="kosong"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-success btn-sm"
                                        >
                                            Aktifkan
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Sedang digunakan
                                    </span>

                                <?php endif; ?>

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