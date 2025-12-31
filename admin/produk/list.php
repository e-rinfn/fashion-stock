<?php
require_once '../includes/header.php';

$sql = "SELECT * FROM produk ORDER BY nama_produk";
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

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Data Produk</h2>
                    <!-- <div>
                        <a href="add.php" class="btn btn-success">
                            <i class="ti ti-circle-plus"></i> Tambah Produk
                        </a>
                    </div> -->
                </div>


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
                        <table class="table table-striped table-bordered table-hover">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 5%;">No</th>
                                    <th style="width: 30%;">Nama Produk</th>
                                    <th style="width: 20%;">Tipe Produk</th>
                                    <th style="width: 20%;">Stok (Pcs)</th>
                                    <th style="width: 15%;">Harga Per Pcs</th>
                                    <!-- <th style="width: 10%;">Aksi</th> -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($produk)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Tidak ada data produk</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($produk as $p) : ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td><?= htmlspecialchars($p['nama_produk']) ?></td>
                                            <td class="text-center"><?= htmlspecialchars(ucfirst($p['tipe_produk'])) ?></td>
                                            <td class="text-end"><?= $p['stok'] ?></td>
                                            <td class="text-end"><?= formatRupiah($p['harga_jual']) ?></td>
                                            <!-- <td><?= htmlspecialchars(substr($p['deskripsi'], 0, 50)) ?>...</td> -->
                                            <!-- <td>
                                                <div class="text-center">
                                                    <a href="edit.php?id=<?= $p['id_produk'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="ti ti-pencil"></i>
                                                    </a>
                                                    <a href="#"
                                                        class="btn btn-sm btn-danger btn-hapus"
                                                        data-id="<?= $p['id_produk'] ?>">
                                                        <i class="ti ti-trash"></i>
                                                    </a>
                                                </div>
                                            </td> -->
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