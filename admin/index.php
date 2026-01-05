<?php
require_once '../config/functions.php';
require_once './includes/header.php';
?>


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
    <?php include_once './includes/sidebar.php'; ?>
    <!-- Sidebar End -->

    <?php include_once './includes/navbar.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">

            <!-- [ Main Content ] start -->
            <div class="row">
                <div class="col-lg-4 col-md-4 order-1">
                    <div class="row">
                        <div class="col-lg-6 col-md-12 col-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="avatar flex-shrink-0">
                                            <i class="bx bx-box fs-2 text-warning"></i>
                                        </div>
                                    </div>
                                    <span class="fw-semibold d-block mb-1">Jenis Bahan</span>
                                    <?php
                                    $sql = "SELECT COUNT(*) as total FROM bahan_baku";
                                    $result = query($sql);
                                    $total_bahan = $result[0]['total'];
                                    ?>
                                    <h3 class="card-title mb-2"><?= $total_bahan ?></h3>
                                </div>

                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12 col-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="avatar flex-shrink-0">
                                            <i class="bx bx-package fs-2 text-success"></i>
                                        </div>
                                    </div>
                                    <span class="fw-semibold d-block mb-1">Jenis Produk</span>
                                    <?php
                                    $sql = "SELECT COUNT(*) as total FROM produk";
                                    $result = query($sql);
                                    $total_produk = $result[0]['total'];
                                    ?>
                                    <h3 class="card-title text-nowrap mb-2"><?= $total_produk ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8 mb-4 order-1">
                    <div class="card">
                        <div class="d-flex align-items-end row">
                            <div class="col-sm-12">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">Selamat Datang, Admin</h5>
                                    <p class="mb-4" style="text-align: justify;">
                                        Selamat datang di dashboard sistem manajemen produksi Mukena dan Koko.
                                        Melalui halaman ini, Anda dapat melakukan pencatatan dan pengelolaan data
                                        operasional seperti pembelian bahan, proses produksi, penjualan produk,
                                        serta pengelolaan laporan dan kas.
                                        Admin memiliki akses terbatas untuk menjaga konsistensi data, sehingga
                                        tidak dapat menghapus master data yang telah ada maupun mengelola data pengguna.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!--/ Total Revenue -->
                <!-- <div class="col-12 col-md-8 col-lg-4 order-3 order-md-2">
                                <div class="row">
                                    <div class="col-12 mb-4">
                                        <div class="card shadow-sm border-0 h-100">
                                            <div class="card-body d-flex flex-column justify-content-between">
                                                <?php
                                                $today = date('Y-m-d');
                                                $sql = "SELECT COUNT(*) as total FROM penjualan WHERE DATE(tanggal_penjualan) = '$today'";
                                                $result = query($sql);
                                                $total_penjualan = $result[0]['total'];

                                                $sql = "SELECT SUM(total_harga) as total FROM penjualan WHERE DATE(tanggal_penjualan) = '$today'";
                                                $result = query($sql);
                                                $total_harga = $result[0]['total'] ?? 0;
                                                ?>
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div>
                                                        <h6 class="text-muted mb-1">Penjualan Hari Ini</h6>
                                                        <h3 class="mb-0 text-warning fw-semibold"><?= formatRupiah($total_harga) ?></h3>
                                                        <small class="text-muted"><?= $total_penjualan ?> Transaksi</small>
                                                    </div>
                                                    <div class="text-white bg-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                                        <i class="bx bx-chart fs-2"></i>
                                                    </div>
                                                </div>
                                                <div class="mt-auto text-end">
                                                    <a href="penjualan/new.php" class="btn btn-sm btn-success">
                                                        <i class="bx bx-plus"></i> Tambah Penjualan
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> -->


                <!-- Total Aktivitas Terakhir -->
                <div class="col-12 col-lg-12 order-2 order-md-3 order-lg-2 mb-4">
                    <div class="card">
                        <div class="row row-bordered g-0">
                            <div class="col-12">
                                <h5 class="card-header m-0 me-2">Aktivitas Terakhir</h5>

                                <!-- <div class="table-responsive text-nowrap px-3 pb-3"> -->
                                <div class="table-responsive text-nowrap px-3 pb-3 mb-3 mt-3" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-striped table-bordered align-middle">
                                        <thead class="table-light">
                                            <tr class="text-center">
                                                <th>Tanggal</th>
                                                <th>Aktivitas</th>
                                                <th>Detail</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Gabungkan beberapa tabel untuk menampilkan aktivitas terakhir
                                            $sql = "

                                                            (SELECT 'pembelian' as type, tanggal_pembelian as waktu, 
                                                                CONCAT('Pembelian ke: ', nama_supplier) as aktivitas, 
                                                                CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail
                                                            FROM pembelian p 
                                                            JOIN supplier r ON p.id_supplier = r.id_supplier 
                                                            ORDER BY waktu DESC LIMIT 3)

                                                            UNION

                                                            (SELECT 'penjualan' as type, tanggal_penjualan as waktu, 
                                                                CONCAT('Penjualan Barang ke: ', nama_reseller) as aktivitas, 
                                                                CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail
                                                            FROM penjualan p 
                                                            JOIN reseller r ON p.id_reseller = r.id_reseller 
                                                            ORDER BY waktu DESC LIMIT 3)

                                                            UNION

                                                            (SELECT 'produksi' as type, created_at as waktu, 
                                                                'Hasil Penjahitan' as aktivitas, 
                                                                CONCAT(jumlah_produk_jadi, ' pcs produk jadi') as detail
                                                            FROM hasil_penjahitan 
                                                            ORDER BY waktu DESC LIMIT 3)

                                                            UNION

                                                            (SELECT 'pembelian_bahan' as type, tanggal_pembelian as waktu, 
                                                                CONCAT('Pembelian Bahan dari: ', nama_supplier) as aktivitas, 
                                                                CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail
                                                            FROM pembelian_bahan pb 
                                                            JOIN supplier s ON pb.id_supplier = s.id_supplier 
                                                            ORDER BY waktu DESC LIMIT 3)

                                                            UNION

                                                            (SELECT 'hasil_jahit_mukena' as type, hpf.tanggal_hasil_jahit as waktu, 
                                                                CONCAT('Hasil Jahit Mukena: ', p.nama_produk) as aktivitas, 
                                                                CONCAT(hpf.total_hasil_jahit, ' pcs dari ', hpf.total_hasil, ' pcs potong (Seri: ', hpf.seri, ')') as detail
                                                            FROM hasil_potong_fix hpf 
                                                            JOIN produk p ON hpf.id_produk = p.id_produk 
                                                            WHERE p.tipe_produk = 'mukena' 
                                                                AND hpf.tanggal_hasil_jahit IS NOT NULL
                                                                AND hpf.total_hasil_jahit IS NOT NULL
                                                            ORDER BY waktu DESC LIMIT 3)

                                                            UNION

                                                            (SELECT 'penjualan_bahan' as type, tanggal_penjualan_bahan as waktu, 
                                                                CONCAT('Penjualan Bahan ke: ', nama_reseller) as aktivitas, 
                                                                CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail
                                                            FROM penjualan_bahan pb 
                                                            JOIN reseller r ON pb.id_reseller = r.id_reseller 
                                                            ORDER BY waktu DESC LIMIT 3)

                                                            UNION

                                                            (SELECT 'hasil_finishing_koko' as type, dhfk.tanggal_finishing as waktu, 
                                                                CONCAT('Hasil Finishing Koko: ', COALESCE(p.nama_produk, 'Produk')) as aktivitas, 
                                                                CONCAT(dhfk.jumlah_selesai, ' pcs selesai, ', dhfk.jumlah_rusak, ' pcs kembali dari ', dh.jumlah, ' pcs dikirim') as detail
                                                            FROM detail_hasil_finishing_koko dhfk 
                                                            JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
                                                            LEFT JOIN produk p ON dh.id_produk = p.id_produk 
                                                            WHERE dhfk.tanggal_finishing IS NOT NULL
                                                            ORDER BY waktu DESC LIMIT 3)

                                                            ORDER BY waktu DESC LIMIT 5
                                                        ";

                                            $activities = query($sql);

                                            if (empty($activities)) {
                                                echo "<tr><td colspan='3' class='text-center'>Tidak ada aktivitas terakhir</td></tr>";
                                            } else {
                                                foreach ($activities as $activity) {
                                                    echo "<tr>";
                                                    echo "<td>" . dateIndo($activity['waktu']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($activity['aktivitas']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($activity['detail']) . "</td>";
                                                    echo "</tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once './includes/footer.php'; ?>

</body>
<!-- [Body] end -->

</html>