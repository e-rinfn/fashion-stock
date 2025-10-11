<?php
// owner/index.php
require_once '../config/functions.php';
require_once './includes/header.php';

// Query data statistik
$total_produk = query("SELECT COUNT(*) as total FROM produk")[0]['total'];
$total_reseller = query("SELECT COUNT(*) as total FROM reseller")[0]['total'];
$penjualan_hari_ini = query("SELECT SUM(total_harga) as total FROM penjualan WHERE DATE(tanggal_penjualan) = CURDATE()")[0]['total'] ?? 0;
$penjualan_bulan_ini = query("SELECT SUM(total_harga) as total FROM penjualan WHERE MONTH(tanggal_penjualan) = MONTH(CURDATE())")[0]['total'] ?? 0;

// Data untuk chart (contoh: penjualan 7 hari terakhir)
$penjualan_7hari = query("
    SELECT DATE(tanggal_penjualan) as tanggal, SUM(total_harga) as total 
    FROM penjualan 
    WHERE tanggal_penjualan >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(tanggal_penjualan)
    ORDER BY tanggal
");

// Data stok bahan baku
$stok_bahan = query("SELECT nama_bahan, jumlah_stok FROM bahan_baku ORDER BY jumlah_stok ASC LIMIT 5");
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
                <div class="col-md-2">
                    <div class="card text-dark bg-white border">
                        <div class="card-body">
                            <h5 class="card-title text-danger">Total Produk</h5>
                            <h2 class="card-text"><?= $total_produk ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-dark bg-white border">
                        <div class="card-body">
                            <h5 class="card-title text-warning">Total Reseller</h5>
                            <h2 class="card-text"><?= $total_reseller ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-dark bg-white border">
                        <div class="card-body">
                            <h5 class="card-title text-primary">Penjualan Hari Ini</h5>
                            <h2 class="card-text"><?= formatRupiah($penjualan_hari_ini) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-dark bg-white border">
                        <div class="card-body">
                            <h5 class="card-title text-success">Penjualan Bulan Ini</h5>
                            <h2 class="card-text"><?= formatRupiah($penjualan_bulan_ini) ?></h2>
                        </div>
                    </div>
                </div>

            </div>

            <div class="row">
                <div class="col-md-8 mt-3">
                    <div class="card">

                        <!-- Grafik Penjualan -->

                        <div class="card-header">
                            <h5>Penjualan 7 Hari Terakhir</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" width="auto"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mt-3">
                    <div class="card">
                        <div class="card-header">
                            <h5>Aksi Cepat</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="laporan/penjualan.php" class="btn btn-primary">Laporan Penjualan</a>
                                <a href="laporan/produksi.php" class="btn btn-secondary">Laporan Produksi</a>
                                <a href="laporan/keuangan.php" class="btn btn-success">Laporan Keuangan</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once './includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        // Grafik Penjualan
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($penjualan_7hari as $data): ?> '<?= date('d M', strtotime($data['tanggal'])) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Total Penjualan',
                    data: [
                        <?php foreach ($penjualan_7hari as $data): ?>
                            <?= $data['total'] ?? 0 ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

    <!-- [Page Specific JS] start -->
    <script src="<?= $base_url ?>/assets/js/plugins/apexcharts.min.js"></script>
    <script src="<?= $base_url ?>/assets/js/pages/dashboard-default.js"></script>
    <!-- [Page Specific JS] end -->
    <!-- Required Js -->
    <script src="<?= $base_url ?>/assets/js/plugins/popper.min.js"></script>
    <script src="<?= $base_url ?>/assets/js/plugins/simplebar.min.js"></script>
    <script src="<?= $base_url ?>/assets/js/plugins/bootstrap.min.js"></script>
    <script src="<?= $base_url ?>/assets/js/fonts/custom-font.js"></script>
    <script src="<?= $base_url ?>/assets/js/pcoded.js"></script>
    <script src="<?= $base_url ?>/assets/js/plugins/feather.min.js"></script>

    <script>
        layout_change("light");
    </script>

    <script>
        change_box_container("false");
    </script>

    <script>
        layout_rtl_change("false");
    </script>

    <script>
        preset_change("preset-1");
    </script>

    <script>
        font_change("Public-Sans");
    </script>


</body>
<!-- [Body] end -->

</html>