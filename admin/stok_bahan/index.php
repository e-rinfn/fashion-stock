<?php

$page_title = "STOK BAHAN BAKU";

require_once '../includes/header.php';

// Ambil parameter filter
$nama_bahan = isset($_GET['nama_bahan']) ? trim($_GET['nama_bahan']) : '';
$jumlah_stok = isset($_GET['jumlah_stok']) ? $_GET['jumlah_stok'] : 'all';

// Bangun query dengan filter
$sql = "SELECT * FROM bahan_baku WHERE 1=1";

if (!empty($nama_bahan)) {
    $nama_bahan_escaped = $conn->real_escape_string($nama_bahan);
    $sql .= " AND nama_bahan LIKE '%$nama_bahan_escaped%'";
}

if ($jumlah_stok == 'tersedia') {
    $sql .= " AND jumlah_stok > 0";
} elseif ($jumlah_stok == 'habis') {
    $sql .= " AND jumlah_stok = 0";
}

$sql .= " ORDER BY nama_bahan";

$bahan = query($sql);
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

                <!-- Filter -->
                <form method="GET" class="row g-3 mb-3">
                    <div class="col-md-6">
                        <input type="text" name="nama_bahan" class="form-control" placeholder="Cari Nama Bahan"
                            value="<?= htmlspecialchars($nama_bahan) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="jumlah_stok" class="form-select">
                            <option value="all" <?= $jumlah_stok == 'all' ? 'selected' : '' ?>>Semua Stok</option>
                            <option value="tersedia" <?= $jumlah_stok == 'tersedia' ? 'selected' : '' ?>>Stok Tersedia</option>
                            <option value="habis" <?= $jumlah_stok == 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-filter"></i> Filter
                        </button>
                        <?php if (!empty($nama_bahan) || $jumlah_stok != 'all'): ?>
                            <a href="index.php" class="btn btn-secondary ms-2">
                                <i class="ti ti-rotate"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>


                <div class="card p-3">
                    <!-- Tampilkan pesan error atau success -->
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
                    <!-- /Tampilkan pesan error atau success -->

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 5%;">No</th>
                                    <th style="width: 35%;">Nama Bahan</th>
                                    <th colspan="2" style="width: 20%;">Stok</th>
                                    <th style="width: 15%;">Total (Meter)</th>
                                    <th style="width: 25%;">Harga Per Meter</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1;
                                foreach ($bahan as $b): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($b['nama_bahan']) ?></td>
                                        <td class="text-end"><?= $b['jumlah_stok'] ?></td>
                                        <td class="text-start"><?= $b['satuan'] ?></td>
                                        <td class="text-end"><?= $b['jumlah_meter'] ?></td>
                                        <td class="text-end"><?= formatRupiah($b['harga_per_satuan']) ?></td>
                                        <!-- <td><?= htmlspecialchars(substr($b['supplier'], 0, 50)) ?>...</td> -->
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($bahan)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Tidak ada data bahan</td>
                                    </tr>
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
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.btn-hapus');

        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');

                // Cek relasi produk via AJAX
                fetch(`check_produk.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.can_delete) {
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
                        } else {
                            Swal.fire({
                                title: 'Tidak Dapat Dihapus',
                                html: data.message,
                                icon: 'error',
                                confirmButtonColor: '#3085d6',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
            });
        });
    });
</script>


</html>