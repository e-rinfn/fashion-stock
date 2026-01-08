<?php

$page_title = "STOK PRODUK";

require_once '../includes/header.php';

// Ambil parameter filter
$nama_produk = isset($_GET['nama_produk']) ? trim($_GET['nama_produk']) : '';
$stok = isset($_GET['stok']) ? $_GET['stok'] : 'all';

// Bangun query dengan filter
$sql = "SELECT * FROM produk WHERE 1=1";

if (!empty($nama_produk)) {
    $nama_produk_escaped = $conn->real_escape_string($nama_produk);
    $sql .= " AND nama_produk LIKE '%$nama_produk_escaped%'";
}

if ($stok == 'tersedia') {
    $sql .= " AND stok > 0";
} elseif ($stok == 'habis') {
    $sql .= " AND stok = 0";
}

$sql .= " ORDER BY nama_produk";

$produk = query($sql);
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
                        <input type="text" name="nama_produk" class="form-control" placeholder="Cari Nama Produk"
                            value="<?= htmlspecialchars($nama_produk) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="stok" class="form-select">
                            <option value="all" <?= $stok == 'all' ? 'selected' : '' ?>>Semua Stok</option>
                            <option value="tersedia" <?= $stok == 'tersedia' ? 'selected' : '' ?>>Stok Tersedia</option>
                            <option value="habis" <?= $stok == 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-filter"></i> Filter
                        </button>
                        <?php if (!empty($nama_produk) || $stok != 'all'): ?>
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
                                    <th style="width: 35%;">Nama Produk</th>
                                    <th style="width: 20%;">Tipe Produk</th>
                                    <th style="width: 15%;">Stok (Pcs)</th>
                                    <th style="width: 25%;">Harga Per Pcs</th>

                                    <!-- <th style="width: 35%;">Deskripsi</th> -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1;
                                foreach ($produk as $p): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($p['nama_produk']) ?></td>
                                        <td class="text-start"><?= htmlspecialchars(ucfirst($p['tipe_produk'])) ?></td>
                                        <td class="text-end"><?= $p['stok'] ?></td>
                                        <td class="text-end"><?= formatRupiah($p['harga_jual']) ?></td>
                                        <!-- <td>
                                            <span class="deskripsi-truncated"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deskripsiModal"
                                                data-deskripsi="<?= htmlspecialchars($p['deskripsi']) ?>"
                                                data-nama="<?= htmlspecialchars($p['nama_produk']) ?>">
                                                <?= htmlspecialchars(substr($p['deskripsi'], 0, 50)) ?>...
                                            </span>
                                        </td> -->
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($produk)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Tidak ada data produk</td>
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