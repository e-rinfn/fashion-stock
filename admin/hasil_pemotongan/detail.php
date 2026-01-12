<?php

$page_title = "DETAIL PRODUKSI";

require_once '../includes/header.php';
require_once '../../config/functions.php';


$id_hasil_potong_fix = isset($_GET['id']) ? intval($_GET['id']) : 0;

// PERBAIKAN: Tambah join tabel bordir
$produksi = query("SELECT h.*, p.nama_produk, pem.nama_pemotong, 
                          bor.nama_bordir, pen.nama_penjahit 
                   FROM hasil_potong_fix h
                   JOIN produk p ON h.id_produk = p.id_produk 
                   JOIN pemotong pem ON h.id_pemotong = pem.id_pemotong 
                   LEFT JOIN bordir bor ON h.id_bordir = bor.id_bordir  -- TAMBAH JOIN BORDIR
                   LEFT JOIN penjahit pen ON h.id_penjahit = pen.id_penjahit 
                   WHERE h.id_hasil_potong_fix = $id_hasil_potong_fix")[0] ?? null;

if (!$produksi) {
    header("Location: list.php");
    exit();
}

// Ambil detail bahan yang digunakan
$detail = query("SELECT d.*, b.nama_bahan, b.harga_per_satuan, 
                        COALESCE(d.meter_per_roll, 0) as meter_per_roll,
                        COALESCE(d.total_meter, 0) as total_meter
                 FROM detail_hasil_potong_fix d
                 JOIN bahan_baku b ON d.id_bahan = b.id_bahan
                 WHERE d.id_hasil_potong_fix = $id_hasil_potong_fix");

// TAMBAHKAN: Ambil data ATK finishing yang digunakan
$atk_finishing = query("SELECT * FROM atk_finishing 
                       WHERE id_hasil_potong_fix = $id_hasil_potong_fix
                       ORDER BY created_at DESC");

// Hitung apakah ada ATK finishing
$has_atk_finishing = !empty($atk_finishing);

// Hitung total ATK yang digunakan
$total_atk_items = 0;
foreach ($atk_finishing as $atk) {
    $total_atk_items += $atk['jumlah'];
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

// Hitung upah berdasarkan status
$tarif_pemotong = getTarifUpah('pemotongan', $produksi['tanggal_hasil_potong']);
$upah_pemotong = $produksi['total_upah'];

// Tentukan tarif bordir berdasarkan tanggal yang sesuai
// $tarif_bordir = 0;
// $upah_bordir = 0;
// if (!empty($produksi['tanggal_hasil_bordir'])) {
//     $tarif_bordir = getTarifUpah('bordir', $produksi['tanggal_hasil_bordir']);
//     $upah_bordir = $produksi['total_upah_bordir'] ?? 0;
// }

// Tentukan tarif bordir berdasarkan tanggal yang sesuai
$tarif_bordir = getTarifUpah('bordir', $produksi['tanggal_hasil_bordir'] ?? date('Y-m-d'));
$upah_bordir = !empty($produksi['total_hasil_bordir']) ?
    $produksi['total_hasil_bordir'] * $produksi['tarif_upah_bordir'] : 0;


// Tentukan tarif penjahit berdasarkan tanggal yang sesuai
$tarif_penjahit = getTarifUpah('penjahitan', $produksi['tanggal_hasil_jahit'] ?? date('Y-m-d'));
$upah_penjahit = !empty($produksi['total_hasil_jahit']) ?
    $produksi['total_hasil_jahit'] * $produksi['tarif_upah'] : 0;

$total_upah = $upah_pemotong + $upah_bordir + $upah_penjahit;

// Tentukan warna badge berdasarkan status
$badge_class = '';
switch ($produksi['status_potong']) {
    case 'selesai':
        $badge_class = 'bg-success';
        break;
    case 'penjahitan':
        $badge_class = 'bg-info';
        break;
    case 'bordir':  // TAMBAHKAN STATUS BORDIR
        $badge_class = 'bg-primary';
        break;
    case 'diproses':
        $badge_class = 'bg-warning';
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

    .bordir-card {
        border-left: 4px solid #1890ff;
        /* Warna ungu untuk bordir */
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

    <?php include_once '../includes/navbar.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <!-- [ Main Content ] start -->
            <div class="row">
                <div class="d-flex justify-content-between align-items-center mb-3">

                    <a href="list.php" class="btn btn-secondary m-1">
                        <i class="ti ti-arrow-back"></i> Kembali ke Daftar
                    </a>

                    <a href="print_detail.php?id=<?= $id_hasil_potong_fix ?>"
                        class="btn btn-danger" target="_blank">
                        <i class="ti ti-printer me-1"></i> Cetak Laporan
                    </a>
                </div>
            </div>

            <!-- Informasi Utama Produksi -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Informasi Produksi</h5>
                        <span class="badge <?= $badge_class ?> status-badge">
                            <?= strtoupper($produksi['status_potong']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center fw-bold">PEMOTONGAN</div>
                            <table class="table table-bordered">
                                <tr>
                                    <th style="width: 40%;">Seri Produksi</th>
                                    <td><?= htmlspecialchars($produksi['seri']) ?></td>
                                </tr>
                                <tr>
                                    <th>Nama Produk</th>
                                    <td><?= htmlspecialchars($produksi['nama_produk']) ?></td>
                                </tr>
                                <tr>
                                    <th>Pemotong</th>
                                    <td>
                                        <?= htmlspecialchars($produksi['nama_pemotong']) ?>
                                        <br>
                                        <small class="text-muted">Rate: <?= formatRupiah($upah_pemotong / $produksi['total_hasil'])  ?>/pcs</small>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Tanggal Potong</th>
                                    <td><?= dateIndo($produksi['tanggal_hasil_potong']) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Hasil Potong</th>
                                    <td><?= $produksi['total_hasil'] ?> Pcs</td>
                                </tr>
                                <tr>
                                    <th>Upah Pemotong</th>
                                    <td class="fw-bold"><?= formatRupiah($upah_pemotong) ?></td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-4">
                            <div class="text-center fw-bold">BORDIR</div>
                            <table class="table table-bordered">
                                <!-- Informasi Bordir (jika ada) -->
                                <?php if ($produksi['status_potong'] == 'bordir' || $produksi['status_potong'] == 'penjahitan' || $produksi['status_potong'] == 'selesai'): ?>
                                    <?php if (!empty($produksi['nama_bordir']) || !empty($produksi['total_hasil_bordir'])): ?>
                                        <tr>
                                            <th style="width: 40%;">Bordir</th>
                                            <td>
                                                <?php if (!empty($produksi['nama_bordir'])): ?>
                                                    <?= htmlspecialchars($produksi['nama_bordir']) ?>
                                                    <br>
                                                    <?php if (!empty($produksi['total_upah_bordir'])): ?>
                                                        <small class="text-muted">Rate: <?= formatRupiah($produksi['tarif_upah_bordir'] ?? 0) ?>/pcs</small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Belum ditentukan</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <?php if (!empty($produksi['tanggal_kirim_bordir'])): ?>
                                            <tr>
                                                <th>Tanggal Kirim Bordir</th>
                                                <td><?= dateIndo($produksi['tanggal_kirim_bordir']) ?></td>
                                            </tr>
                                        <?php endif; ?>

                                        <?php if (!empty($produksi['tanggal_hasil_bordir'])): ?>
                                            <tr>
                                                <th>Tanggal Selesai Bordir</th>
                                                <td><?= dateIndo($produksi['tanggal_hasil_bordir']) ?></td>
                                            </tr>
                                        <?php endif; ?>

                                        <?php if (!empty($produksi['total_hasil_bordir'])): ?>
                                            <tr>
                                                <th>Total Hasil Bordir</th>
                                                <td><?= $produksi['total_hasil_bordir'] ?> Pcs</td>
                                            </tr>


                                            <tr>
                                                <th>Upah Bordir</th>
                                                <td class="fw-bold">
                                                    <?= formatRupiah($upah_bordir) ?>
                                                </td>
                                            </tr>

                                        <?php endif; ?>


                                    <?php endif; ?>
                                <?php endif; ?>
                            </table>
                        </div>

                        <div class="col-md-4">
                            <div class="text-center fw-bold">PENJAHITAN</div>
                            <table class="table table-bordered">

                                <!-- Informasi Penjahitan (jika ada) -->
                                <?php if ($produksi['status_potong'] == 'penjahitan' || $produksi['status_potong'] == 'selesai'): ?>
                                    <tr>
                                        <th style="width: 40%;">Penjahit</th>
                                        <td>
                                            <?php if (!empty($produksi['nama_penjahit'])): ?>
                                                <?= htmlspecialchars($produksi['nama_penjahit']) ?>
                                                <br>
                                                <small class="text-muted">Rate: <?= formatRupiah($produksi['tarif_upah']) ?>/pcs</small>
                                            <?php else: ?>
                                                <span class="text-muted">Belum ditentukan</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php if (!empty($produksi['tanggal_kirim_jahit'])): ?>
                                        <tr>
                                            <th>Tanggal Kirim Jahit</th>
                                            <td><?= dateIndo($produksi['tanggal_kirim_jahit']) ?></td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php if (!empty($produksi['tanggal_hasil_jahit'])): ?>
                                        <tr>
                                            <th>Tanggal Selesai Jahit</th>
                                            <td><?= dateIndo($produksi['tanggal_hasil_jahit']) ?></td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php if (!empty($produksi['total_hasil_jahit'])): ?>
                                        <tr>
                                            <th>Total Hasil Jahit</th>
                                            <td><?= $produksi['total_hasil_jahit'] ?> Pcs</td>
                                        </tr>

                                        <tr>
                                            <th>Upah Penjahit</th>
                                            <td class="fw-bold">
                                                <?= formatRupiah($upah_penjahit) ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php if ($produksi['status_potong'] == 'selesai'): ?>
                                        <tr>
                                            <th>Total Upah Produksi</th>
                                            <td class="fw-bold text-primary"><?= formatRupiah($total_upah) ?></td>
                                        </tr>
                                    <?php endif; ?>

                                <?php elseif ($produksi['status_potong'] == 'bordir'): ?>
                                    <!-- Status Bordir (belum ada penjahitan) -->
                                    <tr>
                                        <th>Informasi Penjahitan</th>
                                        <td class="text-muted">
                                            <i class="ti ti-info-circle"></i>
                                            Data penjahitan belum diinput. Produksi masih dalam tahap bordir.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <!-- Status Diproses (belum ada bordir) -->
                                    <tr>
                                        <th>Informasi Bordir</th>
                                        <td class="text-muted">
                                            <i class="ti ti-info-circle"></i>
                                            Data bordir belum diinput. Produksi masih dalam tahap pemotongan.
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
                <div class="col-md-9 row">
                    <div class="col-md-12">
                        <div class="card shadow-sm border-primary">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">Ringkasan Produksi</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card bg-light h-100">
                                            <div class="card-body text-center">
                                                <h6 class="card-title text-muted">Status Saat Ini</h6>
                                                <h3 class="mt-2">
                                                    <span class="badge <?= $badge_class ?> p-2">
                                                        <?= strtoupper($produksi['status_potong']) ?>
                                                    </span>
                                                </h3>
                                                <p class="mt-2 mb-0">
                                                    <?php
                                                    $status_text = '';
                                                    switch ($produksi['status_potong']) {
                                                        case 'diproses':
                                                            $status_text = 'Sedang dalam proses pemotongan';
                                                            break;
                                                        case 'bordir':
                                                            $status_text = 'Sedang dalam proses bordir';
                                                            break;
                                                        case 'penjahitan':
                                                            $status_text = 'Sedang dalam proses penjahitan';
                                                            break;
                                                        case 'selesai':
                                                            $status_text = 'Produksi telah selesai';
                                                            break;
                                                    }
                                                    echo $status_text;
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card bg-light h-100">
                                            <div class="card-body text-center">
                                                <h6 class="card-title text-muted">Total Hasil</h6>
                                                <h1 class="mt-2 text-primary">
                                                    <?php
                                                    if ($produksi['status_potong'] == 'selesai') {
                                                        echo $produksi['total_hasil_jahit'] . ' Pcs';
                                                    } elseif ($produksi['status_potong'] == 'penjahitan') {
                                                        echo ($produksi['total_hasil_bordir'] ?? $produksi['total_hasil']) . ' Pcs';
                                                        if ($produksi['total_hasil_bordir']) {
                                                            echo '<br><small class="text-muted">(Bordir)</small>';
                                                        } else {
                                                            echo '<br><small class="text-muted">(Potong)</small>';
                                                        }
                                                    } elseif ($produksi['status_potong'] == 'bordir') {
                                                        echo $produksi['total_hasil'] . ' Pcs';
                                                        echo '<br><small class="text-muted">(Potong)</small>';
                                                    } else {
                                                        echo $produksi['total_hasil'] . ' Pcs';
                                                    }
                                                    ?>
                                                </h1>
                                                <p class="mt-2 mb-0">
                                                    <?php if ($produksi['status_potong'] == 'selesai'): ?>
                                                        Hasil jahit final
                                                    <?php elseif ($produksi['status_potong'] == 'penjahitan'): ?>
                                                        <?= $produksi['total_hasil_bordir'] ? 'Hasil bordir' : 'Total potong' ?> (dalam penjahitan)
                                                    <?php elseif ($produksi['status_potong'] == 'bordir'): ?>
                                                        Total potong (dalam bordir)
                                                    <?php else: ?>
                                                        Hasil potong awal
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card bg-light h-100">
                                            <div class="card-body text-center">
                                                <h6 class="card-title text-muted">Total Biaya Upah</h6>
                                                <h1 class="mt-2 text-success"><?= formatRupiah($total_upah) ?></h1>
                                                <p class="mt-2 mb-0">
                                                    <?php if ($upah_pemotong > 0 && $upah_bordir > 0 && $upah_penjahit > 0): ?>
                                                        Total: <?= formatRupiah($total_upah) ?>
                                                    <?php elseif ($upah_pemotong > 0 && $upah_bordir > 0): ?>
                                                        Pemotong: <?= formatRupiah($upah_pemotong) ?> +
                                                        Bordir: <?= formatRupiah($upah_bordir) ?>
                                                    <?php elseif ($upah_pemotong > 0 && $upah_penjahit > 0): ?>
                                                        Pemotong: <?= formatRupiah($upah_pemotong) ?> +
                                                        Penjahit: <?= formatRupiah($upah_penjahit) ?>
                                                    <?php elseif ($upah_pemotong > 0): ?>
                                                        Pemotong: <?= formatRupiah($upah_pemotong) ?>
                                                    <?php else: ?>
                                                        Belum ada perhitungan upah
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bahan Baku yang Digunakan -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="card shadow-sm">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">Bahan Baku Roll yang Digunakan</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover align-middle">
                                            <thead class="table-secondary text-center">
                                                <tr>
                                                    <th style="width: 50px;">No</th>
                                                    <th>Nama Bahan</th>
                                                    <th colspan="2" style="width: 120px;">Roll</th>
                                                    <th style="width: 120px;">Total Meter</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($detail)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted">Tidak ada data bahan</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php
                                                    $total_harga_bahan = 0;
                                                    $total_meter_used = 0;
                                                    $total_roll_used = 0;
                                                    foreach ($detail as $i => $d):
                                                        // Hitung total meter jika ada data meter_per_roll
                                                        $meter_per_roll = isset($d['meter_per_roll']) ? $d['meter_per_roll'] : 0;
                                                        $total_meter = isset($d['total_meter']) ? $d['total_meter'] : ($d['jumlah'] * $meter_per_roll);

                                                        $subtotal = $d['jumlah'] * ($d['harga_per_satuan'] * $total_meter);
                                                        $total_harga_bahan += $subtotal;
                                                        $total_meter_used += $total_meter;
                                                        $total_roll_used += $d['jumlah'];
                                                    ?>
                                                        <tr>
                                                            <td class="text-center"><?= $i + 1 ?></td>
                                                            <td><?= htmlspecialchars($d['nama_bahan']) ?></td>
                                                            <td class="text-center"><?= $d['jumlah'] ?> Roll</td>
                                                            <td colspan="3" class="text-end">
                                                                <?= rtrim(rtrim($d['total_meter'], '0'), '.') ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <?php if (!empty($detail)): ?>
                                                <tfoot class="table-light">
                                                    <tr>
                                                        <td colspan="3" class="text-end fw-bold">Total Roll Digunakan:</td>
                                                        <td colspan="2" class="text-end fw-bold"><?= $total_roll_used ?> Roll</td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="3" class="text-end fw-bold">Total Meter Digunakan:</td>
                                                        <td colspan="2" class="text-end fw-bold"><?= number_format($total_meter_used) ?> Meter</td>
                                                    </tr>
                                                </tfoot>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- ATK Finishing yang Digunakan -->
                            <?php if ($has_atk_finishing && in_array($produksi['status_potong'], ['penjahitan', 'selesai'])): ?>
                                <div class="row mb-3">
                                    <div class="col-lg-12">
                                        <div class="card shadow-sm">
                                            <div class="card-header bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h5 class="card-title mb-0">
                                                        ATK Finishing yang Digunakan
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-hover align-middle">
                                                        <thead class="table-info text-center">
                                                            <tr>
                                                                <th style="width: 50px;">No</th>
                                                                <th>ATK Finishing</th>
                                                                <th style="width: 100px;">Jumlah</th>
                                                                <th style="width: 100px;">Satuan</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($atk_finishing)): ?>
                                                                <tr>
                                                                    <td colspan="5" class="text-center text-muted">
                                                                        Tidak ada data ATK finishing
                                                                    </td>
                                                                </tr>
                                                            <?php else: ?>
                                                                <?php foreach ($atk_finishing as $i => $atk): ?>
                                                                    <tr>
                                                                        <td class="text-center"><?= $i + 1 ?></td>
                                                                        <td><?= htmlspecialchars($atk['nama_atk']) ?></td>
                                                                        <td class="text-center"><?= $atk['jumlah'] ?></td>
                                                                        <td class="text-center"><?= ucfirst($atk['satuan']) ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                        <?php if (!empty($atk_finishing)): ?>
                                                            <tfoot class="table-light">
                                                                <tr>
                                                                    <td colspan="2" class="text-end fw-bold">Total ATK:</td>
                                                                    <td class="text-center fw-bold">
                                                                        <?= $total_atk_items ?> Item
                                                                    </td>
                                                                    <td colspan="2" class="text-center fw-bold">
                                                                        <?= count($atk_finishing) ?> Jenis ATK
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
                            <?php elseif (in_array($produksi['status_potong'], ['penjahitan']) && !$has_atk_finishing): ?>
                                <!-- Status Penjahitan (belum ada ATK) -->
                                <div class="row g-3 mb-3">
                                    <div class="col-lg-12">
                                        <div class="card shadow-sm border-secondary">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">
                                                    ATK Finishing
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="text-center py-4">
                                                    <div class="mb-3">
                                                        <i class="ti ti-clock fs-1 text-secondary"></i>
                                                    </div>
                                                    <h5 class="text-muted">Belum Ada ATK Finishing</h5>
                                                    <p class="text-muted mb-0">
                                                        ATK finishing akan diinput bersamaan dengan hasil penjahitan
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Status lainnya (tidak memerlukan ATK) -->
                                <div class="row g-3 mb-3">
                                    <div class="col-lg-12">
                                        <div class="card shadow-sm border-light">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">
                                                    ATK Finishing
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="text-center py-3">
                                                    <p class="text-muted mb-0">

                                                        ATK finishing pada halaman ini hanya ditampilkan jika tipe produk adalah mukena.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <!-- Timeline Status Produksi -->
                <div class="col-md-3 card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Status & Timeline Produksi</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="timeline">
                                    <!-- Step 1: Diproses (Pemotongan) -->
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="timeline-step bg-success">
                                            1
                                        </div>
                                        <div class="flex-grow-1 <?= $produksi['status_potong'] == 'diproses' ? 'info-card p-3' : '' ?>">
                                            <h6 class="mb-1">Pemotongan <?= $produksi['status_potong'] == 'diproses' ? '<span class="badge bg-primary ms-2">SEDANG BERJALAN</span>' : '' ?></h6>
                                            <p class="mb-1">Tanggal: <?= dateIndo($produksi['tanggal_hasil_potong']) ?></p>
                                            <p class="mb-1">Oleh: <?= htmlspecialchars($produksi['nama_pemotong']) ?></p>
                                            <p class="mb-0">Hasil: <?= $produksi['total_hasil'] ?> Pcs</p>
                                            <div class="mt-2">
                                                <span class="badge bg-success">SELESAI</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 2: Bordir (jika ada) -->
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="timeline-step 
                                        <?= $produksi['status_potong'] == 'bordir' ? 'bg-primary' : ($produksi['status_potong'] == 'penjahitan' || $produksi['status_potong'] == 'selesai' ? 'bg-success' : 'bg-secondary') ?>">
                                            2
                                        </div>
                                        <div class="flex-grow-1 <?= $produksi['status_potong'] == 'bordir' ? 'bordir-card p-3' : '' ?>">
                                            <h6 class="mb-1">Bordir
                                                <?php if ($produksi['status_potong'] == 'bordir'): ?>
                                                    <span class="badge bg-primary ms-2">SEDANG BERJALAN</span>
                                                <?php endif; ?>
                                            </h6>

                                            <?php if ($produksi['status_potong'] == 'bordir' || $produksi['status_potong'] == 'penjahitan' || $produksi['status_potong'] == 'selesai'): ?>
                                                <?php if (!empty($produksi['nama_bordir'])): ?>
                                                    <p class="mb-1">Bordir: <?= htmlspecialchars($produksi['nama_bordir']) ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($produksi['tanggal_kirim_bordir'])): ?>
                                                    <p class="mb-1">Tanggal Kirim: <?= dateIndo($produksi['tanggal_kirim_bordir']) ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($produksi['tanggal_hasil_bordir'])): ?>
                                                    <p class="mb-1">Tanggal Selesai: <?= dateIndo($produksi['tanggal_hasil_bordir']) ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($produksi['total_hasil_bordir'])): ?>
                                                    <p class="mb-1">Hasil: <?= $produksi['total_hasil_bordir'] ?> Pcs</p>
                                                <?php endif; ?>

                                                <?php if (empty($produksi['nama_bordir']) && empty($produksi['total_hasil_bordir'])): ?>
                                                    <p class="mb-0 text-muted">Produksi tanpa proses bordir</p>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p class="mb-0 text-muted">Menunggu proses bordir...</p>
                                            <?php endif; ?>

                                            <div class="mt-2">
                                                <?php if (in_array($produksi['status_potong'], ['penjahitan', 'selesai']) && (!empty($produksi['nama_bordir']) || !empty($produksi['total_hasil_bordir']))): ?>
                                                    <span class="badge bg-success">SELESAI</span>
                                                <?php elseif ($produksi['status_potong'] == 'bordir'): ?>
                                                    <span class="badge bg-primary">SEDANG PROSES</span>
                                                <?php elseif ($produksi['status_potong'] == 'penjahitan' && empty($produksi['nama_bordir'])): ?>
                                                    <span class="badge bg-secondary">DILEWATKAN</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">MENUNGGU</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 3: Penjahitan -->
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="timeline-step 
                                        <?= $produksi['status_potong'] == 'penjahitan' ? 'bg-warning' : ($produksi['status_potong'] == 'selesai' ? 'bg-success' : 'bg-secondary') ?>">
                                            3
                                        </div>
                                        <div class="flex-grow-1 <?= $produksi['status_potong'] == 'penjahitan' ? 'warning-card p-3' : '' ?>">
                                            <h6 class="mb-1">Penjahitan
                                                <?php if ($produksi['status_potong'] == 'penjahitan'): ?>
                                                    <span class="badge bg-warning ms-2">SEDANG BERJALAN</span>
                                                <?php endif; ?>
                                            </h6>

                                            <?php if ($produksi['status_potong'] == 'penjahitan' || $produksi['status_potong'] == 'selesai'): ?>
                                                <?php if (!empty($produksi['tanggal_kirim_jahit'])): ?>
                                                    <p class="mb-1">Tanggal Kirim: <?= dateIndo($produksi['tanggal_kirim_jahit']) ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($produksi['nama_penjahit'])): ?>
                                                    <p class="mb-1">Penjahit: <?= htmlspecialchars($produksi['nama_penjahit']) ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($produksi['tanggal_hasil_jahit'])): ?>
                                                    <p class="mb-1">Tanggal Selesai: <?= dateIndo($produksi['tanggal_hasil_jahit']) ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($produksi['total_hasil_jahit'])): ?>
                                                    <p class="mb-1">Hasil: <?= $produksi['total_hasil_jahit'] ?> Pcs</p>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p class="mb-0 text-muted">Menunggu proses penjahitan...</p>
                                            <?php endif; ?>

                                            <div class="mt-2">
                                                <?php if ($produksi['status_potong'] == 'selesai'): ?>
                                                    <span class="badge bg-success">SELESAI</span>
                                                <?php elseif ($produksi['status_potong'] == 'penjahitan'): ?>
                                                    <span class="badge bg-warning">SEDANG PROSES</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">MENUNGGU</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 4: Selesai -->
                                    <div class="d-flex align-items-center">
                                        <div class="timeline-step <?= $produksi['status_potong'] == 'selesai' ? 'bg-success' : 'bg-secondary' ?>">
                                            4
                                        </div>
                                        <div class="flex-grow-1 <?= $produksi['status_potong'] == 'selesai' ? 'success-card p-3' : '' ?>">
                                            <h6 class="mb-1">Selesai
                                                <?php if ($produksi['status_potong'] == 'selesai'): ?>
                                                    <span class="badge bg-success ms-2">SELESAI</span>
                                                <?php endif; ?>
                                            </h6>

                                            <?php if ($produksi['status_potong'] == 'selesai'): ?>
                                                <p class="mb-1">Produksi telah selesai</p>
                                                <p class="mb-1">Total Hasil: <?= $produksi['total_hasil_jahit'] ?? $produksi['total_hasil'] ?> Pcs</p>
                                                <p class="mb-0">Total Upah: <?= formatRupiah($total_upah) ?></p>
                                                <div class="mt-2">
                                                    <span class="badge bg-success">SELESAI</span>
                                                </div>
                                            <?php else: ?>
                                                <p class="mb-0 text-muted">Menunggu proses sebelumnya selesai...</p>
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
            </div>

        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>
</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script> -->

<script>
    // Sertakan script modal dari list.php jika diperlukan
    // Atau buat modals di sini untuk action penjahitan
</script>

</html>