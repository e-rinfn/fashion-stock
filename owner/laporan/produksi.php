<?php
$pageTitle = "Laporan Produksi";
require_once '../includes/header.php';

// Query data produksi
$produksi = query("SELECT pp.tanggal_kirim, p.nama_pemotong, h.jumlah_hasil, 
                  t.nama_penjahit, hp.jumlah_produk_jadi, pr.nama_produk
                  FROM pengiriman_pemotong pp
                  JOIN pemotong p ON pp.id_pemotong = p.id_pemotong
                  JOIN hasil_pemotongan h ON pp.id_pengiriman_potong = h.id_pengiriman_potong
                  JOIN pengiriman_penjahit pj ON h.id_hasil_potong = pj.id_hasil_potong
                  JOIN penjahit t ON pj.id_penjahit = t.id_penjahit
                  JOIN hasil_penjahitan hp ON pj.id_pengiriman_jahit = hp.id_pengiriman_jahit
                  JOIN produk pr ON hp.id_produk = pr.id_produk
                  ORDER BY pp.tanggal_kirim DESC");
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
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Pemotong</th>
                                                <th>Hasil Potong</th>
                                                <th>Penjahit</th>
                                                <th>Produk Jadi</th>
                                                <th>Jenis Produk</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($produksi as $prod) : ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($prod['tanggal_kirim'])) ?></td>
                                                    <td><?= htmlspecialchars($prod['nama_pemotong']) ?></td>
                                                    <td><?= $prod['jumlah_hasil'] ?> pcs</td>
                                                    <td><?= htmlspecialchars($prod['nama_penjahit']) ?></td>
                                                    <td><?= $prod['jumlah_produk_jadi'] ?> pcs</td>
                                                    <td><?= htmlspecialchars($prod['nama_produk']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->


    <!-- Core JS footer -->
    <!-- /Core JS footer -->


</body>

</html>