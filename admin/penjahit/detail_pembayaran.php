<?php
require_once __DIR__ . '../../includes/header.php';
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

$id_pembayaran = $_GET['id'] ?? null;

if (!$id_pembayaran) {
    $_SESSION['error'] = "ID Pembayaran tidak valid";
    header("Location: riwayat_hasil_pemotongan.php");
    exit;
}

// Ambil data pembayaran
$pembayaran = query("
    SELECT pb.*, 
           CASE pb.jenis_penerima 
               WHEN 'pemotong' THEN pm.nama_pemotong 
               ELSE 'Unknown' 
           END as nama_penerima
    FROM pembayaran_upah pb
    LEFT JOIN pemotong pm ON pb.id_penerima = pm.id_pemotong AND pb.jenis_penerima = 'pemotong'
    WHERE pb.id_pembayaran = $id_pembayaran
")[0] ?? null;

if (!$pembayaran) {
    $_SESSION['error'] = "Data pembayaran tidak ditemukan";
    header("Location: riwayat_hasil_pemotongan.php");
    exit;
}

// Ambil detail hasil yang dibayar
$detail_hasil = query("
    SELECT d.*, 
           h.tanggal_selesai, 
           h.jumlah_hasil,
           h.total_upah,
           bb.nama_bahan,
           pm.nama_pemotong
    FROM detail_pembayaran_upah d
    JOIN hasil_pemotongan h ON d.id_hasil = h.id_hasil_potong AND d.jenis_hasil = 'potong'
    JOIN pengiriman_pemotong pg ON h.id_pengiriman_potong = pg.id_pengiriman_potong
    JOIN bahan_baku bb ON pg.id_bahan = bb.id_bahan
    JOIN pemotong pm ON pg.id_pemotong = pm.id_pemotong
    WHERE d.id_pembayaran = $id_pembayaran
");

// Ambil data cicilan
$cicilan = query("
    SELECT * FROM cicilan_upah 
    WHERE id_pembayaran = $id_pembayaran 
    ORDER BY tanggal_cicilan DESC
");

// Hitung total cicilan yang sudah dibayar
$total_cicilan = query("
    SELECT COALESCE(SUM(jumlah_cicilan), 0) as total 
    FROM cicilan_upah 
    WHERE id_pembayaran = $id_pembayaran
")[0]['total'];

// Hitung sisa yang belum dibayar
$sisa_upah = $pembayaran['total_upah'] - $total_cicilan;

// Update sisa upah di database jika berbeda
if ($pembayaran['sisa_upah'] != $sisa_upah) {
    $conn->query("UPDATE pembayaran_upah SET sisa_upah = $sisa_upah WHERE id_pembayaran = $id_pembayaran");
    $pembayaran['sisa_upah'] = $sisa_upah;
}

// Proses tambah cicilan jika ada POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_cicilan'])) {
    $jumlah_cicilan = str_replace('.', '', $_POST['jumlah_cicilan']);
    $tanggal_cicilan = $_POST['tanggal_cicilan'];
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $keterangan = $_POST['keterangan'];

    if ($jumlah_cicilan <= 0) {
        $_SESSION['error'] = "Jumlah cicilan harus lebih dari 0";
    } elseif ($jumlah_cicilan > $sisa_upah) {
        $_SESSION['error'] = "Jumlah cicilan tidak boleh melebihi sisa upah yang belum dibayar";
    } else {
        $insert = "
            INSERT INTO cicilan_upah (
                id_pembayaran, 
                jumlah_cicilan, 
                tanggal_cicilan, 
                metode_pembayaran, 
                keterangan
            ) VALUES (
                $id_pembayaran, 
                $jumlah_cicilan, 
                '$tanggal_cicilan', 
                '$metode_pembayaran', 
                '$keterangan'
            )
        ";

        if ($conn->query($insert)) {
            // Update sisa upah
            $sisa_baru = $sisa_upah - $jumlah_cicilan;
            $conn->query("UPDATE pembayaran_upah SET sisa_upah = $sisa_baru WHERE id_pembayaran = $id_pembayaran");

            // Jika sudah lunas, update status
            if ($sisa_baru <= 0) {
                $conn->query("UPDATE pembayaran_upah SET status = 'dibayar' WHERE id_pembayaran = $id_pembayaran");
            }

            $_SESSION['success'] = "Cicilan berhasil ditambahkan";
            header("Location: detail_pembayaran.php?id=$id_pembayaran");
            exit;
        } else {
            $_SESSION['error'] = "Gagal menambahkan cicilan: " . $conn->error;
        }
    }
}
?>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>
            <!-- / Sidebar -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <?php include '../includes/navbar.php'; ?>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2>Detail Pembayaran Upah</h2>
                            <div class="btn-group ms-auto" role="group" aria-label="Navigasi Form">
                                <a href="biaya_upah_pemotong.php" class="btn btn-secondary">Kembali</a>
                            </div>
                        </div>

                        <div class="card p-4 shadow-sm">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_SESSION['error']) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_SESSION['success']) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>

                            <!-- Informasi Pembayaran -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5>Informasi Pembayaran</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="40%">Penerima</th>
                                            <td><?= htmlspecialchars($pembayaran['nama_penerima']) ?></td>
                                        </tr>
                                        <tr hidden>
                                            <th>Metode Pembayaran</th>
                                            <td><?= $pembayaran['metode_pembayaran'] ?></td>
                                        </tr>
                                        <tr hidden>
                                            <th>Status</th>
                                            <td>
                                                <?php if ($pembayaran['status'] == 'dibayar'): ?>
                                                    <span class="badge bg-success">Lunas</span>
                                                <?php elseif ($pembayaran['status'] == 'terhitung'): ?>
                                                    <span class="badge bg-warning">Cicilan</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?= $pembayaran['status'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h5>Ringkasan Pembayaran</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="40%">Total Upah</th>
                                            <td class="text-end">Rp <?= number_format($pembayaran['total_upah'], 0, ',', '.') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Total Cicilan Dibayar</th>
                                            <td class="text-end">Rp <?= number_format($total_cicilan, 0, ',', '.') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Sisa Upah</th>
                                            <td class="text-end fw-bold">Rp <?= number_format($sisa_upah, 0, ',', '.') ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Form Tambah Cicilan -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5>Tambah Cicilan Pembayaran</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label for="jumlah_cicilan" class="form-label">Jumlah Cicilan</label>
                                                <input type="text" class="form-control" id="jumlah_cicilan" name="jumlah_cicilan"
                                                    onkeyup="formatCurrency(this)" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="tanggal_cicilan" class="form-label">Tanggal Cicilan</label>
                                                <input type="date" class="form-control" name="tanggal_cicilan"
                                                    value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="metode_pembayaran" class="form-label">Metode Pembayaran</label>
                                                <select class="form-select" name="metode_pembayaran" required>
                                                    <option value="transfer">Transfer Bank</option>
                                                    <option value="tunai">Tunai</option>
                                                    <option value="e-wallet">E-Wallet</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="keterangan" class="form-label">Keterangan</label>
                                                <input type="text" class="form-control" name="keterangan">
                                            </div>
                                            <div class="col-md-12">
                                                <button type="submit" name="tambah_cicilan" class="btn btn-primary">
                                                    Tambah Cicilan
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Daftar Cicilan -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5>Riwayat Cicilan</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($cicilan)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>No</th>
                                                        <th>Tanggal</th>
                                                        <th>Jumlah</th>
                                                        <th>Metode</th>
                                                        <th>Keterangan</th>
                                                        <th>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $no = 1;
                                                    foreach ($cicilan as $c): ?>
                                                        <tr>
                                                            <td><?= $no++ ?></td>
                                                            <td><?= dateIndo($c['tanggal_cicilan']) ?></td>
                                                            <td class="text-end">Rp <?= number_format($c['jumlah_cicilan'], 0, ',', '.') ?></td>
                                                            <td><?= $c['metode_pembayaran'] ?></td>
                                                            <td><?= htmlspecialchars($c['keterangan']) ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-danger btn-hapus-cicilan"
                                                                    data-id="<?= $c['id_cicilan'] ?>"
                                                                    data-jumlah="<?= $c['jumlah_cicilan'] ?>">
                                                                    Hapus
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center">Belum ada cicilan yang dicatat</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Detail Hasil Pemotongan -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>Detail Hasil Pemotongan</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>No</th>
                                                    <th>Tanggal</th>
                                                    <th>Bahan Baku</th>
                                                    <th>Pemotong</th>
                                                    <th>Jumlah Hasil</th>
                                                    <th>Upah</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $no = 1;
                                                foreach ($detail_hasil as $dh): ?>
                                                    <tr>
                                                        <td><?= $no++ ?></td>
                                                        <td><?= date('d F Y', strtotime($dh['tanggal_selesai'])) ?></td>
                                                        <td><?= htmlspecialchars($dh['nama_bahan']) ?></td>
                                                        <td><?= htmlspecialchars($dh['nama_pemotong']) ?></td>
                                                        <td class="text-end"><?= number_format($dh['jumlah_hasil'], 0, ',', '.') ?> pcs</td>
                                                        <td class="text-end">Rp <?= number_format($dh['total_upah'], 0, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Modal Hapus Cicilan -->
        <div class="modal fade" id="modalHapusCicilan" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Konfirmasi Hapus Cicilan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="formHapusCicilan" method="post" action="hapus_cicilan.php">
                        <div class="modal-body">
                            <p>Anda yakin ingin menghapus cicilan ini?</p>
                            <p>Jumlah: <strong id="jumlahCicilanHapus"></strong></p>
                            <input type="hidden" name="id_cicilan" id="id_cicilan_hapus">
                            <input type="hidden" name="id_pembayaran" value="<?= $id_pembayaran ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // Format input currency
            function formatCurrency(input) {
                // Hapus semua karakter selain angka
                let value = input.value.replace(/[^0-9]/g, '');

                // Format dengan titik sebagai pemisah ribuan
                if (value.length > 0) {
                    value = parseInt(value).toLocaleString('id-ID');
                }

                input.value = value;
            }

            // Tangani klik tombol hapus cicilan
            document.querySelectorAll('.btn-hapus-cicilan').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const jumlah = this.getAttribute('data-jumlah');

                    document.getElementById('id_cicilan_hapus').value = id;
                    document.getElementById('jumlahCicilanHapus').textContent = 'Rp ' + parseInt(jumlah).toLocaleString('id-ID');

                    var modal = new bootstrap.Modal(document.getElementById('modalHapusCicilan'));
                    modal.show();
                });
            });
        </script>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS footer -->
    <?php include '../includes/footer.php'; ?>
    <!-- /Core JS footer -->
</body>

</html>