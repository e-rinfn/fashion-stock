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

            <!-- Row 1: 2 cards -->
            <div class="row mb-4">
                <!-- Card Bahan -->
                <div class="col-md-6 col-xxl-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Bahan Baku</h5>
                            <a href="<?= $base_url ?>/admin/bahan_baku/list.php" class="btn btn-primary">
                                <i class="ti ti-arrow-right me-1"></i> Lihat Data
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Card Produk -->
                <div class="col-md-6 col-xxl-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Produk</h5>
                            <a href="<?= $base_url ?>/admin/produk/list.php" class="btn btn-success">
                                <i class="ti ti-arrow-right me-1"></i> Lihat Data
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: 4 cards -->
            <div class="row mb-4">

                <!-- Card Pemotong -->
                <div class="col-md-3 col-xxl-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Pemotong</h5>
                            <a href="<?= $base_url ?>/admin/pemotong/list.php" class="btn btn-info">
                                <i class="ti ti-arrow-right me-1"></i> Lihat Data
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Card Bordir -->
                <div class="col-md-3 col-xxl-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Bordir</h5>
                            <a href="<?= $base_url ?>/admin/petugas_bordir/list.php" class="btn btn-warning">
                                <i class="ti ti-arrow-right me-1"></i> Lihat Data
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Card Penjahit -->
                <div class="col-md-3 col-xxl-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Penjahit</h5>
                            <a href="<?= $base_url ?>/admin/penjahit/list.php" class="btn btn-danger">
                                <i class="ti ti-arrow-right me-1"></i> Lihat Data
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Card Finishing -->
                <div class="col-md-3 col-xxl-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Finishing</h5>
                            <a href="<?= $base_url ?>/admin/petugas_finishing/list.php" class="btn btn-secondary">
                                <i class="ti ti-arrow-right me-1"></i> Lihat Data
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: 3 cards -->
            <div class="row">
                <!-- Card Reseller -->
                <div class="col-md-4 col-xxl-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Reseller</h5>
                            <a href="<?= $base_url ?>/admin/reseller/list.php" class="btn btn-primary">
                                <i class="ti ti-arrow-right me-1"></i> Lihat Data
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Card Supplier -->
                <div class="col-md-4 col-xxl-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Supplier</h5>
                            <a href="<?= $base_url ?>/admin/supplier/list.php" class="btn btn-success">
                                <i class="ti ti-arrow-right me-1"></i> Lihat Data
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Card Tarif Upah -->
                <div class="col-md-4 col-xxl-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Tarif Upah</h5>
                            <a href="<?= $base_url ?>/admin/upah/upah_settings.php" class="btn btn-info">
                                <i class="ti ti-arrow-right me-1"></i> Lihat Data
                            </a>
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