<?php
$base_url = '/mukena-inventory';
?>

<div id="sidebar" class="active">
    <div class="sidebar-wrapper active">
        <div class="sidebar-header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="logo d-flex align-items-center">
                    <a href="<?= $base_url ?>/index.php">
                        <img src="<?= $base_url ?>/assets/images/Logo.png" alt="Logo Inventory" class="me-2" style="height: 40px;">
                    </a>
                    <span class="fw-bold text-uppercase fs-4">Inventory Fashion</span>
                </div>
                <div class="toggler">
                    <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
                </div>
            </div>
        </div>

        <div class="sidebar-menu">
            <ul class="menu">
                <li class="sidebar-title">Menu</li>

                <li class="sidebar-item active ">
                    <a href="<?= $base_url ?>/admin/dashboard/index.php" class='sidebar-link'>
                        <i class="bi bi-grid-fill"></i>
                        <span>DASHBOARD</span>
                    </a>
                </li>
                <li class="sidebar-title">Kelola Produk</li>
                <li class="sidebar-item">
                    <a href="<?= $base_url ?>/admin/products/index.php" class='sidebar-link'>
                        <i class="bi bi-box"></i>
                        <span>PRODUK</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= $base_url ?>/admin/suppliers/index.php" class='sidebar-link'>
                        <i class="bi bi-box"></i>
                        <span>SUPPLIER</span>
                    </a>
                </li>
                <li class="sidebar-title">Kelola Transaksi</li>

                <li class="sidebar-item  has-sub">
                    <a href="#" class='sidebar-link'>
                        <i class="bi bi-stack"></i>
                        <span>TRANSAKSI</span>
                    </a>
                    <ul class="submenu ">
                        <li class="submenu-item ">
                            <a href="<?= $base_url ?>/admin/transactions/in/index.php">MASUK</a>
                        </li>
                        <li class="submenu-item ">
                            <a href="<?= $base_url ?>/admin/transactions/out/index.php">KELUAR</a>
                        </li>
                    </ul>
                </li>
                <li class="sidebar-title">Laporan</li>
                <li class="sidebar-item">
                    <a href="<?= $base_url ?>/admin/invoices/index.php" class='sidebar-link'>
                        <i class="bi bi-box"></i>
                        <span>INVOICES</span>
                    </a>
                </li>
                <li class="sidebar-item  has-sub">
                    <a href="#" class='sidebar-link'>
                        <i class="bi bi-stack"></i>
                        <span>LAPORAN</span>
                    </a>
                    <ul class="submenu ">
                        <li class="submenu-item ">
                            <a href="<?= $base_url ?>/admin/reports/daily_sales.php">PENJUALAN</a>
                        </li>
                        <li class="submenu-item ">
                            <a href="<?= $base_url ?>/admin/reports/stock.php">STOK</a>
                        </li>
                    </ul>
                </li>
                <hr>
                <li class="sidebar-item text-center mb-5">
                    <a href="<?= $base_url ?>/admin/dashboard/index.php"
                        class="sidebar-link text-white d-flex align-items-center px-3 py-2"
                        style="background-color: rgb(255, 67, 67); border-radius: 5px; transition: background-color 0.3s;">
                        <i class="bi bi-box-arrow-right me-2 text-white"></i>
                        <span>Logout</span>
                    </a>
                </li>

                <style>
                    .sidebar-link:hover {
                        background-color: rgb(220, 53, 69);
                        /* warna sedikit lebih gelap */
                        text-decoration: none;
                        color: white;
                    }
                </style>

            </ul>
        </div>
        <button class="sidebar-toggler btn x"><i data-feather="x"></i></button>
    </div>
    </d>