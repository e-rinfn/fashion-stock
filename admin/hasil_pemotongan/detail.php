<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';

function dateIndo($tanggal)
{
    $bulanIndo = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $tanggal = date('Y-m-d', strtotime($tanggal));
    $pecah = explode('-', $tanggal);
    return $pecah[2] . ' ' . $bulanIndo[(int)$pecah[1]] . ' ' . $pecah[0];
}

$id_hasil_potong_fix = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data produksi
$produksi = query("SELECT h.*, p.nama_produk, pem.nama_pemotong, pen.nama_penjahit 
                   FROM hasil_potong_fix h
                   JOIN produk p ON h.id_produk = p.id_produk 
                   JOIN pemotong pem ON h.id_pemotong = pem.id_pemotong 
                   LEFT JOIN penjahit pen ON h.id_penjahit = pen.id_penjahit 
                   WHERE h.id_hasil_potong_fix = $id_hasil_potong_fix")[0] ?? null;

if (!$produksi) {
    header("Location: list.php");
    exit();
}

// Ambil detail bahan yang digunakan
$detail = query("SELECT d.*, b.nama_bahan, b.harga_per_satuan 
                FROM detail_hasil_potong_fix d
                JOIN bahan_baku b ON d.id_bahan = b.id_bahan
                WHERE d.id_hasil_potong_fix = $id_hasil_potong_fix");

// Hitung total bahan yang digunakan
$total_bahan = 0;
foreach ($detail as $d) {
    $total_bahan += $d['jumlah'];
}
?>

<style>
    .swal2-container {
        z-index: 99999 !important;
    }

    .badge-produksi {
        background-color: #0d6efd;
    }

    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .btn-group-actions {
        display: flex;
        gap: 5px;
        flex-wrap: nowrap;
    }

    .btn-group-actions .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .upah-column {
        background-color: #e8f5e8 !important;
        font-weight: bold;
    }

    .table th {
        font-size: 0.8rem;
    }

    .table td {
        font-size: 0.8rem;
    }

    .tarif-info {
        font-size: 0.7rem;
        color: #6c757d;
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

    <!-- [ Header Topbar ] start -->
    <header class="pc-header">
        <div class="header-wrapper">
            <!-- [Mobile Media Block] start -->
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
                    <!-- ======= Menu collapse Icon ===== -->
                    <li class="pc-h-item pc-sidebar-collapse">
                        <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                    <li class="pc-h-item pc-sidebar-popup">
                        <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- [Mobile Media Block end] -->

        </div>
    </header>
    <!-- [ Header ] end -->
    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">

            <!-- [ Main Content ] start -->
            <div class="row">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Detail Produksi</h2>
                    <div>
                        <a href="edit.php?id=<?= $id_hasil_potong_fix ?>" class="btn btn-warning m-1">
                            <i class="ti ti-pencil"></i> Edit Produksi
                        </a>
                        <a href="list.php" class="btn btn-secondary m-1">
                            <i class="ti ti-arrow-back"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>

            <!-- Informasi Utama Produksi -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informasi Produksi</h5>
                    <hr>
                    Nama Produk : <strong><?= htmlspecialchars($produksi['nama_produk']) ?></strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">

                                <tr>
                                    <th>Seri</th>
                                    <td><?= htmlspecialchars($produksi['seri']) ?></td>
                                </tr>
                                <tr>
                                    <th>Pemotong</th>
                                    <td><?= htmlspecialchars($produksi['nama_pemotong']) ?></td>
                                </tr>
                                <tr>
                                    <th>Tanggal Potong</th>
                                    <td><?= dateIndo($produksi['tanggal_hasil_potong']) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Hasil Potong</th>
                                    <td><?= $produksi['total_hasil'] ?> Pcs</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <span class="badge bg-<?= $produksi['status_potong'] == 'selesai' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($produksi['status_potong']) ?>
                                        </span>
                                    </td>
                                </tr>

                                <?php if ($produksi['status_potong'] == 'selesai'): ?>
                                    <tr>
                                        <th>Penjahit</th>
                                        <td><?= !empty($produksi['nama_penjahit']) ? htmlspecialchars($produksi['nama_penjahit']) : '-' ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal Selesai Jahit</th>
                                        <td><?= !empty($produksi['tanggal_hasil_jahit']) ? dateIndo($produksi['tanggal_hasil_jahit']) : '-' ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Hasil Jahit</th>
                                        <td><?= !empty($produksi['total_hasil_jahit']) ? $produksi['total_hasil_jahit'] . ' Pcs' : '-' ?></td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Ringkasan Produksi -->
            <div class="row g-3 mb-3">
                <!-- Bahan Baku yang Digunakan -->
                <div class="col-lg-7 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">Bahan Baku yang Digunakan</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-secondary text-center">
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Nama Bahan</th>
                                            <th style="width: 120px;">Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($detail)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Tidak ada data bahan</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($detail as $i => $d): ?>
                                                <tr>
                                                    <td class="text-center"><?= $i + 1 ?></td>
                                                    <td><?= htmlspecialchars($d['nama_bahan']) ?></td>
                                                    <td class="text-center"><?= $d['jumlah'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <!-- <tfoot>
                                                    <tr class="table-light fw-bold">
                                                        <td colspan="2" class="text-end">Total Bahan Digunakan</td>
                                                        <td class="text-center"><?= $total_bahan ?></td>
                                                    </tr>
                                                </tfoot> -->
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Produksi -->
                <div class="col-lg-5 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">Status Produksi</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">

                                <!-- Pemotongan -->
                                <li class="list-group-item d-flex align-items-start">
                                    <span class="me-2 mt-1 bullet bg-success"></span>
                                    <div>
                                        <strong>Pemotongan</strong><br>
                                        <?php if (!empty($produksi['tanggal_hasil_potong'])): ?>
                                            <small class="text-muted"><?= dateIndo($produksi['tanggal_hasil_potong']) ?></small><br>
                                            <small>Oleh: <?= htmlspecialchars($produksi['nama_pemotong']) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Menunggu proses</small><br>
                                            <small>Status: Dalam Proses</small>
                                        <?php endif; ?>
                                    </div>
                                </li>


                                <!-- Penjahitan -->
                                <li class="list-group-item d-flex align-items-start">
                                    <?php
                                    $penjahitan_selesai = ($produksi['status_potong'] == 'selesai' && !empty($produksi['tanggal_hasil_jahit']));
                                    ?>
                                    <span class="me-2 mt-1 bullet <?= $penjahitan_selesai ? 'bg-success' : 'bg-warning' ?>"></span>
                                    <div>
                                        <strong>Penjahitan</strong><br>
                                        <?php if ($penjahitan_selesai): ?>
                                            <small class="text-muted"><?= dateIndo($produksi['tanggal_hasil_jahit']) ?></small><br>
                                            <small>Oleh: <?= htmlspecialchars($produksi['nama_penjahit']) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Menunggu proses</small><br>
                                            <small>Status: Dalam Proses</small>
                                        <?php endif; ?>
                                    </div>
                                </li>

                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Tambahkan sedikit CSS agar bullet terlihat rapi -->
                <style>
                    .bullet {
                        display: inline-block;
                        width: 10px;
                        height: 10px;
                        border-radius: 50%;
                    }

                    .bg-success {
                        background-color: #28a745 !important;
                    }

                    .bg-warning {
                        background-color: #ffc107 !important;
                    }
                </style>


            </div>
        </div>
    </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</html>