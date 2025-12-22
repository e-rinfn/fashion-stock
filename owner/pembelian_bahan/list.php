<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';

// Ambil semua supplier untuk dropdown
$suppliers = query("SELECT * FROM supplier");

// Cek filter yang diterapkan
$id_supplier = isset($_GET['id_supplier']) ? (int)$_GET['id_supplier'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Bangun query berdasarkan filter
$sql = "SELECT p.*, s.nama_supplier
        FROM pembelian_bahan p
        JOIN supplier s ON p.id_supplier = s.id_supplier
        WHERE 1=1";

// Filter supplier
if ($id_supplier > 0) {
    $sql .= " AND p.id_supplier = $id_supplier";
}

// Filter status
if ($status != 'all') {
    $sql .= " AND p.status_pembayaran = '$status'";
}

$sql .= " ORDER BY p.tanggal_pembelian DESC";

$pembelian_bahan = query($sql);

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
                    <h2>DATA PEMBELIAN BAHAN BAKU</h2>
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
                            <select name="id_supplier" class="form-select">
                                <option value="0">Semua Supplier</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['id_supplier'] ?>" <?= ($id_supplier == $sup['id_supplier']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sup['nama_supplier']) ?>
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
                                <i class="ti ti-filter"></i> Filter
                            </button>
                            <?php if ($id_supplier > 0 || $status != 'all'): ?>
                                <a href="list.php" class="btn btn-secondary ms-2">
                                    <i class="ti ti-rotate"></i> Reset
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
                                    <th>Supplier</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th style="width: 200px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pembelian_bahan)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Tidak ada data pembelian bahan</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($pembelian_bahan as $p): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td><?= dateIndo($p['tanggal_pembelian']) ?></td>
                                            <td><?= htmlspecialchars($p['nama_supplier']) ?></td>
                                            <td><?= formatRupiah($p['total_harga']) ?></td>
                                            <td class="text-center">
                                                <?php if ($p['status_pembayaran'] == 'lunas'): ?>
                                                    <span class="badge bg-success">LUNAS</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">CICILAN</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group" aria-label="Aksi Pembelian">

                                                    <a href="cicilan.php?id=<?= $p['id_pembelian_bahan'] ?>" class="btn btn-sm btn-primary" title="Pembayaran">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                    <!-- <a href="detail.php?id=<?= $p['id_pembelian_bahan'] ?>" class="btn btn-sm btn-primary" title="Detail">
                                                                <i class="bx bx-detail"></i>
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
<script>
    document.querySelectorAll('.btn-batal').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');

            Swal.fire({
                title: 'Yakin ingin membatalkan pembelian bahan ini?',
                text: "Tindakan ini akan mengembalikan stok produk dan menghapus data pembelian bahan!",
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