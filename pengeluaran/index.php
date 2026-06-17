<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Pengeluaran';
$baseUrl = '../';

$db = getDB();

$message = '';
$error = '';

if (isset($_GET['success'])) {

    if ($_GET['success'] === 'add') {
        $message = 'Pengeluaran berhasil ditambahkan.';
    }

    if ($_GET['success'] === 'edit') {
        $message = 'Pengeluaran berhasil diperbarui.';
    }
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value)
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isKaryawan()) {

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {

        $tanggal = $_POST['tanggal'] ?? '';
        $nominal = (float) ($_POST['nominal'] ?? 0);
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($tanggal === '' || $nominal <= 0 || $keterangan === '') {

            $error = 'Semua field wajib diisi.';

        } else {

            $user = currentUser();

            $stmt = $db->prepare("
                INSERT INTO pengeluaran
                (
                    id_user,
                    tanggal,
                    nominal,
                    keterangan
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->bind_param(
                'isds',
                $user['id_user'],
                $tanggal,
                $nominal,
                $keterangan
            );

            $stmt->execute();
            $stmt->close();

            header('Location: index.php?success=add');
            exit;
        }
    }

    if ($action === 'edit') {

        $idPengeluaran =
            (int) ($_POST['id_pengeluaran'] ?? 0);

        $tanggal =
            $_POST['tanggal'] ?? '';

        $nominal =
            (float) ($_POST['nominal'] ?? 0);

        $keterangan =
            trim($_POST['keterangan'] ?? '');

        if (
            $tanggal === '' ||
            $nominal <= 0 ||
            $keterangan === ''
        ) {

            $error =
                'Semua field wajib diisi.';

        } else {

            $stmt = $db->prepare("
                UPDATE pengeluaran
                SET
                    tanggal = ?,
                    nominal = ?,
                    keterangan = ?
                WHERE id_pengeluaran = ?
            ");

            $stmt->bind_param(
                'sdsi',
                $tanggal,
                $nominal,
                $keterangan,
                $idPengeluaran
            );

            $stmt->execute();
            $stmt->close();

            header('Location: index.php?success=edit');
            exit;
        }
    }
}

$editData = null;

if (
    isset($_GET['edit']) &&
    isKaryawan()
) {

    $idEdit =
        (int) $_GET['edit'];

    $stmt = $db->prepare("
        SELECT *
        FROM pengeluaran
        WHERE id_pengeluaran = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        'i',
        $idEdit
    );

    $stmt->execute();

    $editData =
        $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();
}

$pengeluaran = [];

$result = $db->query("
    SELECT
        p.id_pengeluaran,
        p.tanggal,
        p.nominal,
        p.keterangan,
        u.username
    FROM pengeluaran p
    JOIN user u
        ON u.id_user = p.id_user
    ORDER BY p.tanggal DESC, p.id_pengeluaran DESC
");

while ($row = $result->fetch_assoc()) {
    $pengeluaran[] = $row;
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
            <?= $editData
                ? 'Edit Pengeluaran'
                : 'Tambah Pengeluaran' ?>
        </h3>
    </div>

    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="<?= $editData ? 'edit' : 'add' ?>"
        >

        <?php if ($editData): ?>

        <input
            type="hidden"
            name="id_pengeluaran"
            value="<?= (int)$editData['id_pengeluaran'] ?>"
        >

        <?php endif; ?>

        <div class="form-group">
            <label>Tanggal</label>
            <input
                type="date"
                name="tanggal"
                value="<?= h(
                    $editData['tanggal']
                    ?? date('Y-m-d')
                ) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Nominal</label>
            <input
                type="number"
                name="nominal"
                min="1"
                value="<?= h(
                    $editData['nominal']
                    ?? ''
                ) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Keterangan</label>
            <input
                type="text"
                name="keterangan"
                value="<?= h(
                    $editData['keterangan']
                    ?? ''
                ) ?>"
                required
            >
        </div>

        <button
            type="submit"
            class="btn btn-primary"
        >
            <?= $editData
                ? 'Update'
                : 'Simpan' ?>
        </button>

    </form>

</div>

<?php endif; ?>

<div class="card">

    <div class="card-header">
        <h3 class="card-title">
            Riwayat Pengeluaran
        </h3>
    </div>

    <?php if (empty($pengeluaran)): ?>

        <div class="empty-state">
            <h3>Belum Ada Data</h3>
            <p>Belum ada pengeluaran yang dicatat.</p>
        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nominal</th>
                        <th>Keterangan</th>
                        <th>User</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($pengeluaran as $row): ?>

                    <tr>

                        <td>
                            <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                        </td>

                        <td>
                            <?= rupiah($row['nominal']) ?>
                        </td>

                        <td>
                            <?= h($row['keterangan']) ?>
                        </td>

                        <td>
                            <?= h($row['username']) ?>
                        </td>

                        <td>

                            <?php if (isKaryawan()): ?>

                                <a
                                    href="?edit=<?= (int)$row['id_pengeluaran'] ?>"
                                    class="btn btn-secondary btn-sm"
                                >
                                    Edit
                                </a>

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