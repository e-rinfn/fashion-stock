<?php
$pageTitle = "Laporan Stok";
require_once '../includes/header.php';

// Query data stok
$bahanBaku = query("SELECT * FROM bahan_baku ORDER BY nama_bahan");
$produk = query("SELECT * FROM produk ORDER BY nama_produk");
?>

<style>
    /* Paksa SweetAlert berada di atas segalanya */
    .swal2-container {
        z-index: 99999 !important;
    }
</style>

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
                            <h2>Data Produk</h2>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Bahan</th>
                                            <th>Stok</th>
                                            <th>Satuan</th>
                                            <th>Harga/Satuan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($bahanBaku)) : ?>
                                            <tr>
                                                <td colspan="5" class="text-center">Tidak ada data bahan baku</td>
                                            </tr>
                                        <?php else : ?>
                                            <?php $no = 1; ?>
                                            <?php foreach ($bahanBaku as $bahan) : ?>
                                                <tr>
                                                    <td><?= $no++; ?></td>
                                                    <td><?= htmlspecialchars($bahan['nama_bahan']); ?></td>
                                                    <td><?= $bahan['jumlah_stok']; ?></td>
                                                    <td><?= htmlspecialchars($bahan['satuan']); ?></td>
                                                    <td><?= formatRupiah($bahan['harga_per_satuan']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
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

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- SweetAlert2 -->
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
    </script>







    <!-- Core JS footer -->
    <?php include '../includes/footer.php'; ?>
    <!-- /Core JS footer -->


</body>

</html>