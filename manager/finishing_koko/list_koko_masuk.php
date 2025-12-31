<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';

// Ambil semua petugas finishing untuk dropdown
$petugas_finishing = query("SELECT * FROM petugas_finishing");

// Cek filter yang diterapkan
$id_petugas_finishing = isset($_GET['id_petugas_finishing']) ? (int)$_GET['id_petugas_finishing'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Bangun query berdasarkan filter
$sql = "SELECT p.*, r.nama_petugas 
        FROM koko_masuk p 
        JOIN petugas_finishing r ON p.id_petugas_finishing = r.id_petugas_finishing 
        WHERE 1=1";

// Filter petugas finishing
if ($id_petugas_finishing > 0) {
    $sql .= " AND p.id_petugas_finishing = $id_petugas_finishing";
}

// Filter status
if ($status != 'all') {
    $sql .= " AND p.status_pembayaran = '$status'";
}

$sql .= " ORDER BY p.tanggal_koko_masuk DESC";

$koko_masuk = query($sql);
?>

<style>
    /* Paksa SweetAlert berada di atas segalanya */
    .swal2-container {
        z-index: 99999 !important;
    }
</style>

<!-- [Body] Start -->

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    <!-- Sidebar Start -->
    <?php include_once '../includes/sidebar.php'; ?>
    <!-- Sidebar End -->

    <?php include_once '../includes/navbar.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">

            <!-- [ Main Content ] start -->
            <div class="row">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>DATA KIRIM KOKO</h2>
                    <div>
                        <a href="koko_masuk.php" class="btn btn-success">
                            <i class="ti ti-file-plus"></i> Tambah Pesanan
                        </a>
                    </div>
                </div>


                <div class="card p-3">
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

                    <!-- Filter Form -->
                    <form method="GET" class="row g-3 mb-3">
                        <div class="col-md-6">
                            <select name="id_petugas_finishing" class="form-select">
                                <option value="0">Semua Petugas Finishing</option>
                                <?php foreach ($petugas_finishing as $petugas): ?>
                                    <option value="<?= $petugas['id_petugas_finishing'] ?>" <?= ($id_petugas_finishing == $petugas['id_petugas_finishing']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($petugas['nama_petugas']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="all" <?= ($status == 'all') ? 'selected' : '' ?>>Semua Status</option>
                                <option value="lunas" <?= ($status == 'lunas') ? 'selected' : '' ?>>Lunas</option>
                                <option value="cicilan" <?= ($status == 'cicilan') ? 'selected' : '' ?>>Cicilan</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-filter"></i> Filter
                            </button>
                            <?php if ($id_petugas_finishing > 0 || $status != 'all'): ?>
                                <a href="list_koko_masuk.php" class="btn btn-secondary ms-2">
                                    <i class="bx bx-reset"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th style="width: 50px;">No</th>
                                    <th>Tanggal</th>
                                    <th>Pelanggan</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th style="width: 200px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($koko_masuk)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Tidak ada data koko masuk</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($koko_masuk as $masuk): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td><?= dateIndo($masuk['tanggal_koko_masuk']) ?></td>
                                            <td><?= htmlspecialchars($masuk['nama_petugas']) ?></td>
                                            <td><?= formatRupiah($masuk['total_harga']) ?></td>
                                            <td class="text-center">
                                                <?php if ($masuk['status_pembayaran'] == 'lunas'): ?>
                                                    <span class="badge bg-success">LUNAS</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">CICILAN</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group" aria-label="Aksi Penjualan">

                                                    <button class="btn btn-sm btn-danger btn-batal" data-id="<?= $masuk['id_koko_masuk'] ?>" title="Batalkan Penjualan">
                                                        <i class="ti ti-circle-x"></i>
                                                    </button>
                                                    <a href="detail_koko_masuk.php?id=<?= $masuk['id_koko_masuk'] ?>" class="btn btn-sm btn-warning" title="Pembayaran">
                                                        <i class="ti ti-report-money"></i>
                                                    </a>
                                                    <!-- <a href="detail.php?id=<?= $masuk['id_koko_masuk'] ?>" class="btn btn-sm btn-primary" title="Detail">
                                                                <i class="bx bx-detail"></i>
                                                            </a> -->


                                                    <!-- <a href="nota.php?id=<?= $masuk['id_koko_masuk'] ?>" target="_blank" class="btn btn-sm btn-info" title="Nota">
                                                        <i class="ti ti-printer"></i>
                                                    </a> -->


                                                </div>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.btn-hapus');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                Swal.fire({
                    title: 'Yakin hapus data produk?',
                    text: "Data yang dihapus tidak bisa dikembalikan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'delete.php?id=' + id;
                    }
                });
            });
        });
    });

    document.querySelectorAll('.btn-batal').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            Swal.fire({
                title: 'Yakin ingin membatalkan penjualan ini?',
                text: "Tindakan ini akan menghapus data penjualan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, batalkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'batal.php?id=' + id;
                }
            });
        });
    });
</script>

</html>