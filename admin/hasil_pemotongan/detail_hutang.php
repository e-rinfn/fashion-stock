<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

function dateIndo($tanggal)
{
    $bulanIndo = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $tanggal = date('Y-m-d', strtotime($tanggal));
    $pecah = explode('-', $tanggal);
    return $pecah[2] . ' ' . $bulanIndo[(int)$pecah[1]] . ' ' . $pecah[0];
}

$id_hutang = intval($_GET['id']);
$detail = getDetailHutang($id_hutang);

// Ambil riwayat pembayaran
$pembayaran = query("SELECT * FROM pembayaran_upah_2 WHERE id_hutang = $id_hutang ORDER BY tanggal_bayar DESC");

// Proses batal pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['batal_pembayaran'])) {
    $id_pembayaran = intval($_POST['id_pembayaran']);

    if (batalPembayaranUpah($id_pembayaran)) {
        $_SESSION['success'] = "Pembayaran berhasil dibatalkan";
        header("Location: detail_hutang.php?id=$id_hutang");
        exit();
    } else {
        $error = "Gagal membatalkan pembayaran";
    }
}
?>

<div class="pc-container">
    <div class="pc-content">
        <div class="row">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Detail Hutang Upah</h2>
                <a href="hutang_upah.php" class="btn btn-secondary">Kembali</a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Informasi Hutang</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Periode</th>
                                    <td><?= date('F Y', strtotime($detail['periode'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Karyawan</th>
                                    <td><?= htmlspecialchars($detail['nama_karyawan']) ?></td>
                                </tr>
                                <tr>
                                    <th>Jenis</th>
                                    <td><?= ucfirst($detail['jenis_karyawan']) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Upah</th>
                                    <td><?= formatRupiah($detail['total_upah']) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Dibayar</th>
                                    <td><?= formatRupiah($detail['total_dibayar']) ?></td>
                                </tr>
                                <tr>
                                    <th>Sisa Hutang</th>
                                    <td class="text-danger fw-bold"><?= formatRupiah($detail['sisa_hutang']) ?></td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <span class="badge bg-<?= $detail['status'] == 'lunas' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($detail['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Riwayat Pembayaran</h5>
                            <span class="badge bg-primary"><?= count($pembayaran) ?> Pembayaran</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pembayaran)): ?>
                                <p class="text-muted">Belum ada pembayaran</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Jumlah</th>
                                                <th>Metode</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pembayaran as $bayar): ?>
                                                <tr>
                                                    <td><?= dateIndo($bayar['tanggal_bayar']) ?></td>
                                                    <td><?= formatRupiah($bayar['jumlah_bayar']) ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?= ucfirst($bayar['metode_bayar']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="id_pembayaran" value="<?= $bayar['id_pembayaran'] ?>">
                                                            <button type="submit" name="batal_pembayaran"
                                                                class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Yakin ingin membatalkan pembayaran ini?')">
                                                                <i class="ti ti-x"></i> Batal
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>