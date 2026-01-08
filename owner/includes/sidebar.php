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
    <div class="navbar-wrapper mb-5">
        <div class="p-3">
            <a href="<?= $base_url ?>/owner/index.php"
                class="b-brand text-primary d-inline-flex align-items-center gap-2 text-decoration-none">

                <!-- Logo -->
                <img
                    src="<?= $base_url ?>/assets/img/Logo-Ipenk.png"
                    style="max-height: 50px; object-fit: contain;"
                    class="img-fluid logo-lg"
                    alt="logo" />

                <!-- Teks di samping logo -->
                <span class="fw-bold fs-5 text-dark">IPENK LEGEND <br>INVENTORY STOCK</span>
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

                <!-- <li class="pc-item pc-caption">
                    <label>MASTER DATA</label>
                    <i class="ti ti-dashboard"></i>
                </li>

                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link"><span class="pc-micon"><i class="ti ti-menu"></i></span><span class="pc-mtext">Master Data</span><span class="pc-arrow"><i data-feather="chevron-right"></i></span></a>
                    <ul class="pc-submenu">
                        <li class="pc-item <?= isActive('/owner/bahan_baku') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/bahan_baku/list.php">Bahan</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/produk') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/produk/list.php">Produk</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/pemotong') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/pemotong/list.php">Pemotong</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/petugas_bordir') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/petugas_bordir/list.php">Bordir</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/penjahit') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/penjahit/list.php">Penjahit</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/petugas_finishing') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/petugas_finishing/list.php">Finishing</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/reseller') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/reseller/list.php">Reseller</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/supplier') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/supplier/list.php">Supplier</a>
                        </li>

                        <li class="pc-item <?= isActive('/owner/upah') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/upah/upah_settings.php">Tarif Upah</a>
                        </li>
                    </ul>
                </li> -->

                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link"><span class="pc-micon"><i class="ti ti-layout-2"></i></span><span class="pc-mtext">Stok</span><span class="pc-arrow"><i data-feather="chevron-right"></i></span></a>
                    <ul class="pc-submenu">
                        <li class="pc-item <?= isActive('/owner/stok_bahan') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/stok_bahan/index.php">Bahan</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/stok_produk') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/stok_produk/index.php">Produk</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/stok_koko') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/stok_koko/index.php">Koko Mentah</a>
                        </li>
                    </ul>
                </li>

                <!-- <li class="pc-item <?= isActive('/stok') ?>">
                    <a href="<?= $base_url ?>/owner/stok.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-layout-2"></i></span>
                        <span class="pc-mtext">Stok</span>
                    </a>
                </li> -->

                <li class="pc-item pc-caption">
                    <label>PRODUKSI</label>
                    <i class="ti ti-dashboard"></i>
                </li>

                <li class="pc-item <?= isActive('/hasil_pemotongan') ?>">
                    <a href="<?= $base_url ?>/owner/hasil_pemotongan/list.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-settings"></i></span>
                        <span class="pc-mtext">Proses Produksi</span>
                    </a>
                </li>

                <li class="pc-item <?= isActive('/finishing_koko') ?>">
                    <a href="<?= $base_url ?>/owner/finishing_koko/finishing.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-shirt"></i></span>
                        <span class="pc-mtext">Finishing Koko</span>
                    </a>
                </li>

                <li class="pc-item <?= isActive('/upah_produksi') ?>">
                    <a href="<?= $base_url ?>/owner/upah_produksi/hutang_upah.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-report-money"></i></span>
                        <span class="pc-mtext">Upah Produksi</span>
                    </a>
                </li>

                <li class="pc-item pc-caption">
                    <label>TRANSAKSI</label>
                    <i class="ti ti-dashboard"></i>
                </li>

                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link"><span class="pc-micon"><i class="ti ti-shopping-cart"></i></span><span class="pc-mtext">Beli</span><span class="pc-arrow"><i data-feather="chevron-right"></i></span></a>
                    <ul class="pc-submenu">
                        <li class="pc-item <?= isActive('/owner/pembelian_bahan') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/pembelian_bahan/list.php">Bahan</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/pembelian_produk') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/pembelian_produk/list.php">Produk</a>
                        </li>
                    </ul>
                </li>

                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link"><span class="pc-micon"><i class="ti ti-shopping-cart-plus"></i></span><span class="pc-mtext">Jual</span><span class="pc-arrow"><i data-feather="chevron-right"></i></span></a>
                    <ul class="pc-submenu">
                        <li class="pc-item <?= isActive('/owner/penjualan_bahan') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/penjualan_bahan/list.php">Bahan</a>
                        </li>
                        <li class="pc-item <?= isActive('/owner/penjualan_produk') ?>">
                            <a class="pc-link" href="<?= $base_url ?>/owner/penjualan_produk/list.php">Produk</a>
                        </li>
                    </ul>
                </li>

                <li class="pc-item pc-caption">
                    <label>LAOPORAN</label>
                    <i class="ti ti-dashboard"></i>
                </li>

                <li class="pc-item <?= isActive('/laporan') ?>">
                    <a href="<?= $base_url ?>/owner/laporan/laporan.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-file"></i></span>
                        <span class="pc-mtext">Laporan</span>
                    </a>
                </li>

            </ul>
        </div>
    </div>
</nav>
<!-- [ Sidebar Menu ] end -->