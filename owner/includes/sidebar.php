<?php

$current_uri = $_SERVER['REQUEST_URI'];

function isActive($path)
{
    global $current_uri;
    return strpos($current_uri, $path) !== false ? 'active' : '';
}

?>

<!-- [ Sidebar Menu ] start -->
<nav class="pc-sidebar">
    <div class="navbar-wrapper">
        <div class="p-3">
            <a href="<?= $base_url ?>/owner/index.php"
                class="b-brand text-primary d-inline-flex align-items-center gap-2 text-decoration-none">

                <!-- Logo -->
                <img
                    src="<?= $base_url ?>/assets/img/Logo.png"
                    style="max-height: 50px; object-fit: contain;"
                    class="img-fluid logo-lg"
                    alt="logo" />

                <!-- Teks di samping logo -->
                <span class="fw-bold fs-5 text-dark">IRVEENA <br>FASHION STOCK</span>
            </a>
        </div>
        <div class="navbar-content">
            <ul class="pc-navbar <?= isActive('/owner/index.php') ?>">
                <li class="pc-item">
                    <a href="<?= $base_url ?>/owner/index.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-smart-home"></i></span>
                        <span class="pc-mtext">Dashboard</span>
                    </a>
                </li>
                <!-- 
                <li class="pc-item pc-caption">
                    <label>PRODUKSI</label>
                    <i class="ti ti-dashboard"></i>
                </li> -->

                <!-- <li class="pc-item <?= isActive('/penjualan') ?>">
                    <a href="<?= $base_url ?>/owner/laporan/penjualan.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-settings"></i></span>
                        <span class="pc-mtext">Penjualan</span>
                    </a>
                </li> -->

                <li class="pc-item <?= isActive('/produksi') ?>">
                    <a href="<?= $base_url ?>/owner/laporan/produksi.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-settings"></i></span>
                        <span class="pc-mtext">Produksi</span>
                    </a>
                </li>

                <li class="pc-item <?= isActive('/finishing_koko') ?>">
                    <a href="<?= $base_url ?>/owner/laporan/finishing_koko.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-settings"></i></span>
                        <span class="pc-mtext">Produksi Koko</span>
                    </a>
                </li>

                <li class="pc-item pc-caption">
                    <label>KEUANGAN</label>
                    <i class="ti ti-dashboard"></i>
                </li>


                <li class="pc-item <?= isActive('/keuangan') ?>">
                    <a href="<?= $base_url ?>/owner/laporan/keuangan.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-report-money"></i></span>
                        <span class="pc-mtext">Keuangan</span>
                    </a>
                </li>

                <li class="pc-item <?= isActive('/upah') ?>">
                    <a href="<?= $base_url ?>/owner/laporan/upah.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-report-money"></i></span>
                        <span class="pc-mtext">Upah</span>
                    </a>
                </li>

            </ul>
        </div>
    </div>
</nav>
<!-- [ Sidebar Menu ] end -->