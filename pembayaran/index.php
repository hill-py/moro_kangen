<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Pembayaran';
$baseUrl = '../';

$db = getDB();

$message = $_GET['success'] ?? '';
$error = '';

$filter = $_GET['filter'] ?? 'today';

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

    if ($action === 'process_payment') {

        $idPesanan = (int) ($_POST['id_pesanan'] ?? 0);
        $metode = $_POST['metode_pembayaran'] ?? '';
        $nominalBayar = (float) ($_POST['nominal_bayar'] ?? 0);

        if (!in_array($metode, ['cash', 'qris'], true)) {

            $error = 'Metode pembayaran tidak valid.';

        } else {

            $stmt = $db->prepare("
                SELECT
                    p.id_pesanan,
                    p.total_pesanan
                FROM pesanan p
                LEFT JOIN pembayaran pb
                    ON pb.id_pesanan = p.id_pesanan
                WHERE p.id_pesanan = ?
                AND p.status = 'menunggu'
                AND pb.id_pembayaran IS NULL
                LIMIT 1
            ");

            $stmt->bind_param('i', $idPesanan);
            $stmt->execute();

            $pesanan = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$pesanan) {

                $error = 'Pesanan tidak ditemukan atau sudah dibayar.';

            } else {

                $totalTagihan = (float) $pesanan['total_pesanan'];
                $kembalian = 0;

                if ($metode === 'cash') {

                    if ($nominalBayar < $totalTagihan) {

                        $error = 'Uang yang dibayarkan tidak cukup.';

                    } else {

                        $kembalian = $nominalBayar - $totalTagihan;
                    }
                }

                if ($error === '') {

                    $db->begin_transaction();

                    try {

                        $stmt = $db->prepare("
                            INSERT INTO pembayaran
                            (
                                id_pesanan,
                                id_user,
                                metode_pembayaran
                            )
                            VALUES (?, ?, ?)
                        ");

                        $idUser = $_SESSION['id_user'];

                        $stmt->bind_param(
                            'iis',
                            $idPesanan,
                            $idUser,
                            $metode
                        );

                        $stmt->execute();
                        $stmt->close();

                        $stmt = $db->prepare("
                            UPDATE pesanan
                            SET status = 'dibayar'
                            WHERE id_pesanan = ?
                        ");

                        $stmt->bind_param(
                            'i',
                            $idPesanan
                        );

                        $stmt->execute();
                        $stmt->close();

                        $db->commit();

                        if (
                            $metode === 'cash' &&
                            $kembalian > 0
                        ) {

                            header(
                                'Location: index.php?success=' .
                                urlencode(
                                    'Pembayaran berhasil. Kembalian: ' .
                                    rupiah($kembalian)
                                )
                            );

                        } else {

                            header(
                                'Location: index.php?success=' .
                                urlencode(
                                    'Pembayaran berhasil disimpan.'
                                )
                            );
                        }

                        exit;

                    } catch (Throwable $e) {

                        $db->rollback();

                        $error =
                            'Gagal menyimpan pembayaran: ' .
                            $e->getMessage();
                    }
                }
            }
        }
    }
}

$selectedOrder = null;

if (
    isset($_GET['bayar']) &&
    isKaryawan()
) {

    $idBayar = (int) $_GET['bayar'];

    $stmt = $db->prepare("
        SELECT
            p.id_pesanan,
            p.nama_pelanggan,
            p.jenis_pesanan,
            p.total_pesanan,
            GROUP_CONCAT(
                DISTINCT k.nomor_kursi
                ORDER BY k.nomor_kursi
                SEPARATOR ', '
            ) AS kursi
        FROM pesanan p
        LEFT JOIN detail_kursi dk
            ON dk.id_pesanan = p.id_pesanan
        LEFT JOIN kursi k
            ON k.id_kursi = dk.id_kursi
        LEFT JOIN pembayaran pb
            ON pb.id_pesanan = p.id_pesanan
        WHERE p.id_pesanan = ?
        AND p.status = 'menunggu'
        AND pb.id_pembayaran IS NULL
        GROUP BY p.id_pesanan
        LIMIT 1
    ");

    $stmt->bind_param('i', $idBayar);
    $stmt->execute();

    $selectedOrder = $stmt->get_result()->fetch_assoc();

    $stmt->close();
}

$unpaidOrders = [];

$result = $db->query("
    SELECT
        p.id_pesanan,
        p.nama_pelanggan,
        p.jenis_pesanan,
        p.total_pesanan,
        GROUP_CONCAT(
            DISTINCT k.nomor_kursi
            ORDER BY k.nomor_kursi
            SEPARATOR ', '
        ) AS kursi
    FROM pesanan p
    LEFT JOIN detail_kursi dk
        ON dk.id_pesanan = p.id_pesanan
    LEFT JOIN kursi k
        ON k.id_kursi = dk.id_kursi
    LEFT JOIN pembayaran pb
        ON pb.id_pesanan = p.id_pesanan
    WHERE p.status = 'menunggu'
    AND pb.id_pembayaran IS NULL
    GROUP BY p.id_pesanan
    ORDER BY p.created_at DESC
");

while ($row = $result->fetch_assoc()) {
    $unpaidOrders[] = $row;
}

$paymentHistory = [];

if ($filter === 'all') {

    $sql = "
        SELECT
            pb.id_pembayaran,
            p.id_pesanan,
            pb.metode_pembayaran,
            pb.status,
            pb.tanggal_bayar,
            p.nama_pelanggan,
            p.total_pesanan,
            u.username
        FROM pembayaran pb
        JOIN pesanan p
            ON p.id_pesanan = pb.id_pesanan
        JOIN user u
            ON u.id_user = pb.id_user
        ORDER BY pb.tanggal_bayar DESC
    ";

    $stmt = $db->prepare($sql);

} else {

    $sql = "
        SELECT
            pb.id_pembayaran,
            p.id_pesanan,
            pb.metode_pembayaran,
            pb.status,
            pb.tanggal_bayar,
            p.nama_pelanggan,
            p.total_pesanan,
            u.username
        FROM pembayaran pb
        JOIN pesanan p
            ON p.id_pesanan = pb.id_pesanan
        JOIN user u
            ON u.id_user = pb.id_user
        WHERE DATE(pb.tanggal_bayar)=CURDATE()
        ORDER BY pb.tanggal_bayar DESC
    ";

    $stmt = $db->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $paymentHistory[] = $row;
}

$stmt->close();

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

<?php if (isKaryawan() && $selectedOrder): ?>

<div class="card">

    <div class="card-header">
        <h3 class="card-title">Proses Pembayaran</h3>
    </div>

    <table>
        <tbody>
            <tr>
                <th>Pelanggan</th>
                <td><?= h($selectedOrder['nama_pelanggan']) ?></td>
            </tr>

            <tr>
                <th>Jenis Pesanan</th>
                <td>
                    <?= $selectedOrder['jenis_pesanan'] === 'dine_in'
                        ? 'Dine In'
                        : 'Take Away' ?>
                </td>
            </tr>

            <tr>
                <th>Kursi</th>
                <td><?= h($selectedOrder['kursi'] ?: '-') ?></td>
            </tr>

            <tr>
                <th>Total Tagihan</th>
                <td>
                    <strong>
                        <?= rupiah($selectedOrder['total_pesanan']) ?>
                    </strong>
                </td>
            </tr>
        </tbody>
    </table>

    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="process_payment"
        >

        <input
            type="hidden"
            name="id_pesanan"
            value="<?= (int) $selectedOrder['id_pesanan'] ?>"
        >

        <div class="form-group">

            <label>Metode Pembayaran</label>

            <select
                name="metode_pembayaran"
                id="metode_pembayaran"
                required
            >
                <option value="">Pilih Metode</option>
                <option value="cash">Cash</option>
                <option value="qris">QRIS</option>
            </select>

        </div>

        <div
            class="form-group mt-16"
            id="cash-group"
            style="display:none;"
        >

            <label>Nominal Dibayar</label>

            <input
                type="number"
                name="nominal_bayar"
                id="nominal_bayar"
                min="0"
                placeholder="Masukkan nominal uang"
            >

            <p class="text-muted mt-8">
                Kembalian:
                <strong id="kembalian">
                    Rp 0
                </strong>
            </p>

        </div>

        <div class="flex gap-12 mt-16">

            <button
                type="submit"
                class="btn btn-success"
                onclick="
                    return confirm(
                        'Simpan pembayaran ini?'
                    )
                "
            >
                Simpan Pembayaran
            </button>

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                Batal
            </a>

        </div>

    </form>

</div>

<?php endif; ?>

<?php if (isKaryawan()): ?>

<div class="card">

    <div class="card-header">
        <h3 class="card-title">
            Pesanan Belum Dibayar
        </h3>
    </div>

    <?php if (empty($unpaidOrders)): ?>

        <div class="empty-state">
            <h3>Tidak Ada Pesanan</h3>
            <p>Semua pesanan sudah dibayar.</p>
        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th>Jenis</th>
                        <th>Kursi</th>
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($unpaidOrders as $order): ?>

                    <tr>

                        <td>
                            <?= h($order['nama_pelanggan']) ?>
                        </td>

                        <td>
                            <?= $order['jenis_pesanan'] === 'dine_in'
                                ? 'Dine In'
                                : 'Take Away' ?>
                        </td>

                        <td>
                            <?= h($order['kursi'] ?: '-') ?>
                        </td>

                        <td>
                            <?= rupiah($order['total_pesanan']) ?>
                        </td>

                        <td>

                            <a
                                href="?bayar=<?= (int) $order['id_pesanan'] ?>"
                                class="btn btn-success btn-sm"
                            >
                                Bayar
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

<?php endif; ?>

<div class="card">

    <div class="card-header">
        <h3 class="card-title">
            Riwayat Pembayaran
        </h3>

        <form method="GET">
            <select
                name="filter"
                onchange="this.form.submit()"
            >
                <option
                    value="today"
                    <?= $filter === 'today'
                        ? 'selected'
                        : '' ?>
                >
                    Hari Ini
                </option>

                <option
                    value="all"
                    <?= $filter === 'all'
                        ? 'selected'
                        : '' ?>
                >
                    Semua
                </option>
            </select>
        </form>
    </div>

    <?php if (empty($paymentHistory)): ?>

        <div class="empty-state">
            <h3>Belum Ada Pembayaran</h3>
            <p>Riwayat pembayaran akan muncul di sini.</p>
        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Metode</th>
                        <th>Total</th>
                        <th>Kasir</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($paymentHistory as $payment): ?>

                    <tr>
                        <td>
                            #<?= (int)$payment['id_pesanan'] ?>
                        </td>

                        <td>
                            <?= date(
                                'd/m/Y H:i',
                                strtotime($payment['tanggal_bayar'])
                            ) ?>
                        </td>

                        <td>
                            <?= h($payment['nama_pelanggan']) ?>
                        </td>

                        <td>
                            <?= strtoupper(
                                h($payment['metode_pembayaran'])
                            ) ?>
                        </td>

                        <td>
                            <?= rupiah($payment['total_pesanan']) ?>
                        </td>

                        <td>
                            <?= h($payment['username']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

<?php if ($selectedOrder): ?>
<script>

const metode =
    document.getElementById(
        'metode_pembayaran'
    );

const cashGroup =
    document.getElementById(
        'cash-group'
    );

const nominal =
    document.getElementById(
        'nominal_bayar'
    );

const kembali =
    document.getElementById(
        'kembalian'
    );

const total =
    <?= (float)$selectedOrder['total_pesanan'] ?>;

metode.addEventListener(
    'change',
    function () {

        if (this.value === 'cash') {

            cashGroup.style.display =
                'block';

            nominal.required = true;

        } else {

            cashGroup.style.display =
                'none';

            nominal.required = false;

            nominal.value = '';

            kembali.textContent =
                'Rp 0';
        }
    }
);

if (nominal) {

    nominal.addEventListener(
        'input',
        function () {

            let bayar =
                parseInt(this.value) || 0;

            let kembalian =
                bayar - total;

            if (kembalian < 0) {
                kembalian = 0;
            }

            kembali.textContent ='Rp ' + kembalian.toLocaleString('id-ID');
        }
    );
}

</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>