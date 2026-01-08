<?php
// Aktifkan error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

$id_hasil_kirim_finishing = isset($_GET['id']) ? intval($_GET['id']) : 0;

// PERBAIKAN QUERY: Hapus JOIN yang salah ke penjahit karena tabel hasil_kirim_finishing tidak ada id_penjahit
$produksi = query("SELECT h.*, pem.nama_petugas
                   FROM hasil_kirim_finishing h
                   LEFT JOIN petugas_finishing pem ON h.id_petugas_finishing = pem.id_petugas_finishing 
                   WHERE h.id_hasil_kirim_finishing = $id_hasil_kirim_finishing")[0] ?? null;

if (!$produksi) {
    header("Location: list.php");
    exit();
}

// Ambil detail koko yang digunakan
$detail = query("SELECT d.*, b.nama_koko, b.harga_jual, 
                        (d.harga_satuan * d.jumlah) as subtotal_manual
                 FROM detail_hasil_kirim_finishing d
                 JOIN koko b ON d.id_koko = b.id_koko
                 WHERE d.id_hasil_kirim_finishing = $id_hasil_kirim_finishing");

// Ambil data produk jika ada id_produk
$nama_produk = '-';
if (!empty($produksi['id_produk'])) {
    $produk_data = query("SELECT nama_produk FROM produk WHERE id_produk = " . $produksi['id_produk']);
    if (!empty($produk_data)) {
        $nama_produk = $produk_data[0]['nama_produk'];
    }
}

// Hitung total bahan yang digunakan
$total_bahan = 0;
foreach ($detail as $d) {
    $total_bahan += $d['jumlah'];
}

// Fungsi untuk mendapatkan tarif upah terkini
function getTarifUpah($jenis_tarif, $tanggal_referensi = null)
{
    global $conn;

    if ($tanggal_referensi === null) {
        $tanggal_referensi = date('Y-m-d');
    }

    $sql = "SELECT tarif_per_unit 
            FROM tarif_upah 
            WHERE jenis_tarif = ? 
            AND berlaku_sejak <= ? 
            ORDER BY berlaku_sejak DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $jenis_tarif, $tanggal_referensi);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['tarif_per_unit'];
    }

    // Default value jika tidak ada tarif
    return 700.00;
}

// Hitung upah petugas finishing
$upah_petugas_finishing = 0;
$tarif_petugas_finishing = 0;
if ($produksi['total_hasil_finishing'] > 0) {
    $tarif_petugas_finishing = getTarifUpah('finishing', $produksi['tanggal_hasil_finishing'] ?? $produksi['tanggal_kirim_finishing']);
    $upah_petugas_finishing = $produksi['total_hasil_finishing'] * $tarif_petugas_finishing;
}

// PERBAIKAN: Tabel hasil_kirim_finishing tidak ada id_penjahit, jadi hapus perhitungan upah penjahit
$upah_penjahit = 0;
$tarif_penjahit = 0;

$total_upah = $upah_petugas_finishing + $upah_penjahit;

// Tentukan warna badge berdasarkan status
$badge_class = '';
switch ($produksi['status_finishing']) {
    case 'selesai':
        $badge_class = 'bg-success';
        break;
    case 'diproses':
        $badge_class = 'bg-warning';
        break;
    case 'pengiriman':
        $badge_class = 'bg-secondary';
        break;
    default:
        $badge_class = 'bg-secondary';
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
        border-color: #dee2e6;
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
        vertical-align: middle;
    }

    .table td {
        font-size: 0.8rem;
        vertical-align: middle;
    }

    .table tfoot tr td {
        background-color: #f8f9fa;
        font-weight: bold;
    }

    .efficiency-info {
        font-size: 0.75rem;
        color: #28a745;
    }

    .tarif-info {
        font-size: 0.7rem;
        color: #6c757d;
    }

    .status-badge {
        font-size: 0.85rem;
        padding: 0.35rem 0.75rem;
    }

    .info-card {
        border-left: 4px solid #17a2b8;
    }

    .warning-card {
        border-left: 4px solid #ffc107;
    }

    .success-card {
        border-left: 4px solid #28a745;
    }

    .secondary-card {
        border-left: 4px solid #6c757d;
    }

    .bullet {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 8px;
    }

    .bg-success {
        background-color: #28a745 !important;
    }

    .bg-warning {
        background-color: #ffc107 !important;
    }

    .bg-info {
        background-color: #17a2b8 !important;
    }

    .bg-secondary {
        background-color: #6c757d !important;
    }

    .timeline-step {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
        margin-right: 15px;
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
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
                    <h2>Detail Kirim Finishing</h2>
                    <div>
                        <!-- <a href="edit.php?id=<?= $id_hasil_kirim_finishing ?>" class="btn btn-warning m-1">
                            <i class="ti ti-pencil"></i> Edit
                        </a> -->
                        <a href="finishing.php" class="btn btn-secondary m-1">
                            <i class="ti ti-arrow-back"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Informasi Utama Kirim Finishing -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Informasi Kirim Finishing</h5>
                        <span class="badge <?= $badge_class ?> status-badge">
                            <?= strtoupper($produksi['status_finishing']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th style="width: 40%;">Seri Pengiriman</th>
                                    <td><?= htmlspecialchars($produksi['seri']) ?></td>
                                </tr>
                                <tr>
                                    <th>Produk</th>
                                    <td><?= htmlspecialchars($nama_produk) ?></td>
                                </tr>
                                <tr>
                                    <th>Petugas Finishing</th>
                                    <td>
                                        <?= htmlspecialchars($produksi['nama_petugas']) ?>
                                        <?php if ($upah_petugas_finishing > 0): ?>
                                            <br>
                                            <small class="text-muted">Rate: <?= formatRupiah($tarif_petugas_finishing) ?>/pcs</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Tanggal Kirim</th>
                                    <td><?= dateIndo($produksi['tanggal_kirim_finishing']) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Kirim</th>
                                    <td><?= $produksi['total_kirim'] ?> Roll</td>
                                </tr>
                                <tr>
                                    <th>Total Harga</th>
                                    <td class="fw-bold"><?= formatRupiah($produksi['total_harga']) ?></td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <!-- Informasi Hasil Finishing -->
                                <?php if ($produksi['status_finishing'] == 'selesai'): ?>
                                    <tr>
                                        <th style="width: 40%;">Tanggal Selesai</th>
                                        <td>
                                            <?= !empty($produksi['tanggal_hasil_finishing']) ? dateIndo($produksi['tanggal_hasil_finishing']) : '-' ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Hasil Finishing</th>
                                        <td><?= $produksi['total_hasil_finishing'] ?? 0 ?> Pcs</td>
                                    </tr>
                                    <tr>
                                        <th>Upah Finishing</th>
                                        <td class="fw-bold"><?= formatRupiah($upah_petugas_finishing) ?></td>
                                    </tr>

                                <?php elseif ($produksi['status_finishing'] == 'diproses'): ?>
                                    <tr>
                                        <th>Informasi Finishing</th>
                                        <td class="text-muted">
                                            <i class="ti ti-info-circle"></i>
                                            Masih dalam proses finishing.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <th>Status</th>
                                        <td class="text-muted">
                                            <i class="ti ti-info-circle"></i>
                                            Dalam tahap pengiriman ke finishing.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Summary Card -->
                <div class="col-md-6">
                    <div class="card shadow-sm border-primary mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Ringkasan Kirim Finishing</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light h-100">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Status Saat Ini</h6>
                                            <h3 class="mt-2">
                                                <span class="badge <?= $badge_class ?> p-2">
                                                    <?= strtoupper($produksi['status_finishing']) ?>
                                                </span>
                                            </h3>
                                            <p class="mt-2 mb-0">
                                                <?php
                                                $status_text = '';
                                                switch ($produksi['status_finishing']) {
                                                    case 'pengiriman':
                                                        $status_text = 'Dalam pengiriman ke finishing';
                                                        break;
                                                    case 'diproses':
                                                        $status_text = 'Sedang dalam proses finishing';
                                                        break;
                                                    case 'selesai':
                                                        $status_text = 'Finishing telah selesai';
                                                        break;
                                                }
                                                echo $status_text;
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light h-100">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Total Hasil</h6>
                                            <h1 class="mt-2 text-primary">
                                                <?php
                                                if ($produksi['status_finishing'] == 'selesai' && !empty($produksi['total_hasil_finishing'])) {
                                                    echo $produksi['total_hasil_finishing'] . ' Pcs';
                                                } else {
                                                    echo $produksi['total_kirim'] . ' Roll';
                                                }
                                                ?>
                                            </h1>
                                            <p class="mt-2 mb-0">
                                                <?php if ($produksi['status_finishing'] == 'selesai'): ?>
                                                    Hasil finishing
                                                <?php else: ?>
                                                    Koko dikirim
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card bg-light h-100">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Total Biaya Bahan</h6>
                                            <h1 class="mt-2 text-success"><?= formatRupiah($produksi['total_harga']) ?></h1>
                                            <p class="mt-2 mb-0">Total harga koko yang dikirim</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card bg-light h-100">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Total Biaya Upah</h6>
                                            <h1 class="mt-2 text-info"><?= formatRupiah($total_upah) ?></h1>
                                            <p class="mt-2 mb-0">
                                                <?php if ($upah_petugas_finishing > 0): ?>
                                                    Upah finishing
                                                <?php else: ?>
                                                    Belum ada upah
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline Status -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Status & Timeline</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <!-- Step 1: Pengiriman -->
                                <div class="d-flex align-items-center mb-4">
                                    <div class="timeline-step 
                                        <?= $produksi['status_finishing'] == 'pengiriman' ? 'bg-warning' : ($produksi['status_finishing'] == 'diproses' || $produksi['status_finishing'] == 'selesai' ? 'bg-success' : 'bg-secondary') ?>">
                                        1
                                    </div>
                                    <div class="flex-grow-1 <?= $produksi['status_finishing'] == 'pengiriman' ? 'warning-card p-3' : '' ?>">
                                        <h6 class="mb-1">Pengiriman
                                            <?php if ($produksi['status_finishing'] == 'pengiriman'): ?>
                                                <span class="badge bg-warning ms-2">SEDANG BERJALAN</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1">Tanggal: <?= dateIndo($produksi['tanggal_kirim_finishing']) ?></p>
                                        <p class="mb-1">Jumlah: <?= $produksi['total_kirim'] ?> Roll</p>
                                        <p class="mb-0">Ke: <?= htmlspecialchars($produksi['nama_petugas']) ?></p>
                                        <div class="mt-2">
                                            <?php if ($produksi['status_finishing'] == 'pengiriman'): ?>
                                                <span class="badge bg-warning">SEDANG PROSES</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">SELESAI</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 2: Proses Finishing -->
                                <div class="d-flex align-items-center mb-4">
                                    <div class="timeline-step 
                                        <?= $produksi['status_finishing'] == 'diproses' ? 'bg-warning' : ($produksi['status_finishing'] == 'selesai' ? 'bg-success' : 'bg-secondary') ?>">
                                        2
                                    </div>
                                    <div class="flex-grow-1 <?= $produksi['status_finishing'] == 'diproses' ? 'warning-card p-3' : '' ?>">
                                        <h6 class="mb-1">Proses Finishing
                                            <?php if ($produksi['status_finishing'] == 'diproses'): ?>
                                                <span class="badge bg-warning ms-2">SEDANG BERJALAN</span>
                                            <?php endif; ?>
                                        </h6>

                                        <?php if ($produksi['status_finishing'] == 'diproses' || $produksi['status_finishing'] == 'selesai'): ?>
                                            <?php if (!empty($produksi['tanggal_hasil_finishing'])): ?>
                                                <p class="mb-1">Tanggal Selesai: <?= dateIndo($produksi['tanggal_hasil_finishing']) ?></p>
                                            <?php endif; ?>

                                            <?php if (!empty($produksi['total_hasil_finishing'])): ?>
                                                <p class="mb-1">Hasil: <?= $produksi['total_hasil_finishing'] ?> Pcs</p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p class="mb-0 text-muted">Menunggu proses finishing...</p>
                                        <?php endif; ?>

                                        <div class="mt-2">
                                            <?php if ($produksi['status_finishing'] == 'selesai'): ?>
                                                <span class="badge bg-success">SELESAI</span>
                                            <?php elseif ($produksi['status_finishing'] == 'diproses'): ?>
                                                <span class="badge bg-warning">SEDANG PROSES</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">MENUNGGU</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 3: Selesai -->
                                <div class="d-flex align-items-center">
                                    <div class="timeline-step <?= $produksi['status_finishing'] == 'selesai' ? 'bg-success' : 'bg-secondary' ?>">
                                        3
                                    </div>
                                    <div class="flex-grow-1 <?= $produksi['status_finishing'] == 'selesai' ? 'success-card p-3' : '' ?>">
                                        <h6 class="mb-1">Selesai
                                            <?php if ($produksi['status_finishing'] == 'selesai'): ?>
                                                <span class="badge bg-success ms-2">SELESAI</span>
                                            <?php endif; ?>
                                        </h6>

                                        <?php if ($produksi['status_finishing'] == 'selesai'): ?>
                                            <p class="mb-1">Finishing telah selesai</p>
                                            <?php if (!empty($produksi['total_hasil_finishing'])): ?>
                                                <p class="mb-1">Hasil: <?= $produksi['total_hasil_finishing'] ?> Pcs</p>
                                            <?php endif; ?>
                                            <?php if ($upah_petugas_finishing > 0): ?>
                                                <p class="mb-0">Upah: <?= formatRupiah($upah_petugas_finishing) ?></p>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <span class="badge bg-success">SELESAI</span>
                                            </div>
                                        <?php else: ?>
                                            <p class="mb-0 text-muted">Menunggu proses finishing selesai...</p>
                                            <div class="mt-2">
                                                <span class="badge bg-secondary">MENUNGGU</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Koko yang Dikirim -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">Koko yang Dikirim</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-secondary text-center">
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Nama Koko</th>
                                            <th style="width: 120px;">Jumlah (Roll)</th>
                                            <th style="width: 150px;">Harga Satuan</th>
                                            <th style="width: 150px;">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($detail)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Tidak ada data koko</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php
                                            $total_harga_bahan = 0;
                                            $total_roll_used = 0;
                                            foreach ($detail as $i => $d):
                                                // Gunakan subtotal dari database jika ada, jika tidak hitung manual
                                                $subtotal = isset($d['subtotal']) ? $d['subtotal'] : ($d['subtotal_manual'] ?? ($d['jumlah'] * $d['harga_satuan']));
                                                $total_harga_bahan += $subtotal;
                                                $total_roll_used += $d['jumlah'];
                                            ?>
                                                <tr>
                                                    <td class="text-center"><?= $i + 1 ?></td>
                                                    <td><?= htmlspecialchars($d['nama_koko']) ?></td>
                                                    <td class="text-center"><?= $d['jumlah'] ?> Roll</td>
                                                    <td class="text-end"><?= formatRupiah($d['harga_satuan']) ?></td>
                                                    <td class="text-end fw-bold"><?= formatRupiah($subtotal) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <?php if (!empty($detail)): ?>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">Total Roll Dikirim:</td>
                                                <td colspan="2" class="text-end fw-bold"><?= $total_roll_used ?> Roll</td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">Total Harga Koko:</td>
                                                <td colspan="2" class="text-end fw-bold text-primary"><?= formatRupiah($total_harga_bahan) ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">Rata-rata Harga per Roll:</td>
                                                <td colspan="2" class="text-end fw-bold">
                                                    <?php
                                                    if ($total_roll_used > 0) {
                                                        $avg_price = $total_harga_bahan / $total_roll_used;
                                                        echo formatRupiah($avg_price);
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>
</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</html>