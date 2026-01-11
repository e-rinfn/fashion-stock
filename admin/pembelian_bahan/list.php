<?php
// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

$page_title = "DATA PEMBELIAN BAHAN BAKU";

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// // Bangun query berdasarkan filter
// $sql = "SELECT p.*, s.nama_supplier
//         FROM pembelian_bahan p
//         JOIN supplier s ON p.id_supplier = s.id_supplier
//         WHERE 1=1";

// // Filter supplier
// if ($id_supplier > 0) {
//     $sql .= " AND p.id_supplier = $id_supplier";
// }

// // Filter status
// if ($status != 'all') {
//     $sql .= " AND p.status_pembayaran = '$status'";
// }

// $sql .= " ORDER BY p.tanggal_pembelian DESC";

// $pembelian_bahan = query($sql);

// Ambil data dengan filter dan pencarian
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_supplier = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;
$filter_bahan = isset($_GET['bahan']) ? $_GET['bahan'] : [];
$filter_tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : '';
$filter_tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

// Konversi filter_bahan ke array jika string
if (!is_array($filter_bahan) && !empty($filter_bahan)) {
    $filter_bahan = explode(',', $filter_bahan);
} elseif (!is_array($filter_bahan)) {
    $filter_bahan = [];
}

// Konversi ke integer dan hapus nilai kosong
$filter_bahan = array_map('intval', $filter_bahan);
$filter_bahan = array_filter($filter_bahan, function ($value) {
    return $value > 0;
});

// Query utama untuk mendapatkan detail pembelian bahan
$query = "SELECT 
            pb.id_pembelian_bahan,
            pb.no_transaksi,
            pb.tanggal_pembelian,
            pb.status_pembayaran,
            pb.metode_pembayaran,
            s.nama_supplier,
            b.id_bahan,
            b.nama_bahan,
            b.satuan,
            dpb.jumlah,
            dpb.meter,
            dpb.harga_satuan,
            dpb.subtotal,
            (dpb.jumlah * dpb.meter) as total_meter
          FROM detail_pembelian_bahan dpb
          JOIN pembelian_bahan pb ON dpb.id_pembelian_bahan = pb.id_pembelian_bahan
          JOIN supplier s ON pb.id_supplier = s.id_supplier
          JOIN bahan_baku b ON dpb.id_bahan = b.id_bahan
          WHERE 1=1";

// Tambahkan kondisi pencarian
if (!empty($search)) {
    $query .= " AND (pb.no_transaksi LIKE '%$search%' 
                OR s.nama_supplier LIKE '%$search%'
                OR b.nama_bahan LIKE '%$search%')";
}

// Filter supplier
if ($filter_supplier > 0) {
    $query .= " AND pb.id_supplier = $filter_supplier";
}

// Filter bahan (multiple)
if (!empty($filter_bahan)) {
    $bahan_ids = implode(',', $filter_bahan);
    $query .= " AND dpb.id_bahan IN ($bahan_ids)";
}

// Filter tanggal
if (!empty($filter_tanggal_awal) && !empty($filter_tanggal_akhir)) {
    $query .= " AND DATE(pb.tanggal_pembelian) BETWEEN '$filter_tanggal_awal' AND '$filter_tanggal_akhir'";
} elseif (!empty($filter_tanggal_awal)) {
    $query .= " AND DATE(pb.tanggal_pembelian) >= '$filter_tanggal_awal'";
} elseif (!empty($filter_tanggal_akhir)) {
    $query .= " AND DATE(pb.tanggal_pembelian) <= '$filter_tanggal_akhir'";
}

// Order by
$query .= " ORDER BY pb.tanggal_pembelian DESC, pb.id_pembelian_bahan DESC, b.nama_bahan";

$detail_pembelian = query($query);

// Ambil data supplier untuk filter
$suppliers = query("SELECT * FROM supplier ORDER BY nama_supplier");

// Ambil data bahan untuk filter
$bahan_list = query("SELECT * FROM bahan_baku ORDER BY nama_bahan");

// Hitung summary total secara manual dari $detail_pembelian
$summary_manual = [
    'total_transaksi' => 0,
    'total_item' => 0,
    'total_jumlah' => 0,
    'total_meter' => 0,
    'total_meter_all' => 0,
    'total_nilai' => 0
];

$transaksi_ids = [];
if (!empty($detail_pembelian)) {
    foreach ($detail_pembelian as $item) {
        // Hitung total transaksi unik
        if (!in_array($item['id_pembelian_bahan'], $transaksi_ids)) {
            $transaksi_ids[] = $item['id_pembelian_bahan'];
            $summary_manual['total_transaksi']++;
        }

        // Hitung total item
        $summary_manual['total_item']++;

        // Hitung total jumlah
        $summary_manual['total_jumlah'] += $item['jumlah'];

        // Hitung total meter
        $summary_manual['total_meter'] += $item['meter'];

        // Hitung total meter all (jumlah × meter)
        $summary_manual['total_meter_all'] += ($item['jumlah'] * $item['meter']);

        // Hitung total nilai (harga_satuan × meter)
        $summary_manual['total_nilai'] += ($item['harga_satuan'] * $item['meter']);
    }
}

// Hitung summary per bahan DARI DATA DETAIL YANG SUDAH DIFILTER
$summary_per_bahan = [];
$bahan_data = [];

if (!empty($detail_pembelian)) {
    foreach ($detail_pembelian as $item) {
        $bahan_id = $item['id_bahan'];

        if (!isset($bahan_data[$bahan_id])) {
            $bahan_data[$bahan_id] = [
                'id_bahan' => $bahan_id,
                'nama_bahan' => $item['nama_bahan'],
                'satuan' => $item['satuan'],
                'total_jumlah' => 0,
                'total_meter' => 0,
                'total_harga_satuan' => 0, // untuk menghitung rata-rata
                'total_subtotal' => 0 // total dari harga_satuan × meter
            ];
        }

        $bahan_data[$bahan_id]['total_jumlah'] += $item['jumlah'];
        $bahan_data[$bahan_id]['total_meter'] += $item['meter'];
        $bahan_data[$bahan_id]['total_harga_satuan'] += $item['harga_satuan'];
        $bahan_data[$bahan_id]['total_subtotal'] += ($item['harga_satuan'] * $item['meter']);
    }

    // Ubah ke array indexed
    $summary_per_bahan = array_values($bahan_data);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pembelian Bahan Baku</title>
    <!-- Tambahkan CSS DataTables -->
    <!-- <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"> -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .filter-card {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }

        .bahan-row {
            border-left: 4px solid #0d6efd;
        }

        .supplier-row {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-cicilan {
            background-color: #fff3e0;
            color: #ef6c00;
        }

        .status-lunas {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .summary-bahan-table {
            font-size: 13px;
        }

        .summary-bahan-table th {
            background-color: #e9ecef;
        }

        .multiselect-dropdown {
            position: relative;
            width: 100%;
        }

        .multiselect-dropdown .dropdown-toggle {
            width: 100%;
            text-align: left;
            background-color: white;
            border: 1px solid #ced4da;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            height: 38px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .multiselect-dropdown .dropdown-toggle::after {
            float: right;
            margin-top: 8px;
        }

        .multiselect-dropdown .dropdown-menu {
            width: 100%;
            max-height: 300px;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .multiselect-dropdown .dropdown-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
        }

        .multiselect-dropdown .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .multiselect-dropdown .dropdown-item input[type="checkbox"] {
            margin-right: 10px;
        }

        .selected-bahan-badge {
            display: inline-block;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 15px;
            padding: 2px 8px;
            margin: 2px;
            font-size: 12px;
        }

        .selected-bahan-container {
            margin-top: 5px;
            min-height: 30px;
        }

        .filter-actions {
            margin-top: 15px;
        }
    </style>
</head>

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
                <div class="col-12">
                    <div class="d-flex justify-content-end align-items-center mb-4">
                        <div>
                            <a href="new.php" class="btn btn-success">
                                <i class="ti ti-file-plus"></i> Tambah Pesanan
                            </a>
                        </div>
                    </div>

                    <!-- Card Summary -->
                    <div class="row mb-4 g-3">

                        <!-- Total Transaksi -->
                        <div class="col-md-3 col-sm-6">
                            <div class="card text-center shadow-sm border-0">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= number_format($summary_manual['total_transaksi'], 0, ',', '.') ?>
                                    </h4>
                                    <small class="text-muted">Total Transaksi</small>
                                </div>
                            </div>
                        </div>

                        <!-- Total Item -->
                        <div class="col-md-3 col-sm-6">
                            <div class="card text-center shadow-sm border-0">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">

                                        <h4 class="fw-bold mb-0">
                                            <?= number_format($summary_manual['total_item'], 0, ',', '.') ?>
                                        </h4>
                                        <small class="text-muted">Total Item Dibeli</small>
                                </div>
                            </div>
                        </div>

                        <!-- Total Meter -->
                        <div class="col-md-3 col-sm-6">
                            <div class="card text-center shadow-sm border-0">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= number_format($summary_manual['total_meter_all'], 0, ',', '.') ?> m
                                    </h4>
                                    <small class="text-muted">Total Meter Pembelian</small>
                                </div>
                            </div>
                        </div>

                        <!-- Total Nilai -->
                        <div class="col-md-3 col-sm-6">
                            <div class="card text-center shadow-sm border-0">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1 text-primary">
                                        <?= formatRupiah($summary_manual['total_nilai']) ?>
                                    </h4>
                                    <small class="text-muted">Total Nilai Pembelian</small>
                                </div>
                            </div>
                        </div>

                    </div>



                    <div class="row">
                        <div class="col-md-5">
                            <!-- Card Filter Utama (untuk semua data) -->
                            <div class="card filter-card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="ti ti-filter me-2"></i>Filter Data Pembelian</h5>
                                </div>
                                <div class="card-body">
                                    <form method="GET" id="mainFilterForm" class="row g-3">

                                        <div class="col-md-6">
                                            <label class="form-label">Supplier</label>
                                            <select class="form-control" name="supplier">
                                                <option value="">Semua Supplier</option>
                                                <?php foreach ($suppliers as $s): ?>
                                                    <option value="<?= $s['id_supplier'] ?>"
                                                        <?= $filter_supplier == $s['id_supplier'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($s['nama_supplier']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Bahan Baku</label>
                                            <div class="multiselect-dropdown">
                                                <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                                    id="bahanDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <?php if (empty($filter_bahan)): ?>
                                                        Pilih Bahan...
                                                    <?php else: ?>
                                                        <?= count($filter_bahan) ?> bahan dipilih
                                                    <?php endif; ?>
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="bahanDropdown">
                                                    <li>
                                                        <div class="dropdown-item">
                                                            <input type="checkbox" id="selectAllBahan"
                                                                <?= count($filter_bahan) == count($bahan_list) ? 'checked' : '' ?>>
                                                            <label for="selectAllBahan" style="cursor: pointer; margin-bottom: 0;">
                                                                <strong>Pilih Semua</strong>
                                                            </label>
                                                        </div>
                                                    </li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <?php foreach ($bahan_list as $b): ?>
                                                        <li>
                                                            <div class="dropdown-item">
                                                                <input type="checkbox" name="bahan[]"
                                                                    value="<?= $b['id_bahan'] ?>"
                                                                    id="bahan_<?= $b['id_bahan'] ?>"
                                                                    <?= in_array($b['id_bahan'], $filter_bahan) ? 'checked' : '' ?>>
                                                                <label for="bahan_<?= $b['id_bahan'] ?>"
                                                                    style="cursor: pointer; margin-bottom: 0;">
                                                                    <?= htmlspecialchars($b['nama_bahan']) ?>
                                                                </label>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <!-- Container untuk menampilkan bahan yang dipilih -->
                                            <div class="selected-bahan-container mt-2">
                                                <?php foreach ($filter_bahan as $bahan_id):
                                                    $bahan_nama = '';
                                                    foreach ($bahan_list as $b) {
                                                        if ($b['id_bahan'] == $bahan_id) {
                                                            $bahan_nama = $b['nama_bahan'];
                                                            break;
                                                        }
                                                    }
                                                    if (!empty($bahan_nama)): ?>
                                                        <span class="selected-bahan-badge" data-id="<?= $bahan_id ?>">
                                                            <?= htmlspecialchars($bahan_nama) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label">Tanggal Pembelian</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                                <?php
                                                // Hitung tanggal awal bulan ini (tanggal 1)
                                                $tanggal_awal_bulan_ini = date('Y-m-01');
                                                // Hitung tanggal akhir bulan ini
                                                $tanggal_akhir_bulan_ini = date('Y-m-t');

                                                // Gunakan nilai dari filter jika sudah ada, jika tidak gunakan tanggal default
                                                $default_tanggal_awal = !empty($filter_tanggal_awal) ? $filter_tanggal_awal : $tanggal_awal_bulan_ini;
                                                $default_tanggal_akhir = !empty($filter_tanggal_akhir) ? $filter_tanggal_akhir : $tanggal_akhir_bulan_ini;
                                                ?>
                                                <input type="date" class="form-control" name="tanggal_awal"
                                                    value="<?= htmlspecialchars($default_tanggal_awal) ?>">
                                                <span class="input-group-text">s/d</span>
                                                <input type="date" class="form-control" name="tanggal_akhir"
                                                    value="<?= htmlspecialchars($default_tanggal_akhir) ?>">
                                            </div>
                                        </div>

                                        <div class="col-12 filter-actions">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <?php if (!empty($filter_bahan)): ?>
                                                        <span class="text-muted">
                                                            <i class="ti ti-info-circle"></i>
                                                            Menampilkan <?= count($filter_bahan) ?> bahan terpilih
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <a href="list.php" class="btn btn-sm btn-secondary">
                                                        <i class="ti ti-rotate me-1"></i> Reset
                                                    </a>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="ti ti-filter me-1"></i> Filter
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <!-- Summary per Bahan (selalu tampil jika ada data) -->
                            <?php if (!empty($summary_per_bahan)): ?>
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="ti ti-chart-bar me-2"></i>
                                            <?php if (!empty($filter_bahan)): ?>
                                                Ringkasan <?= count($filter_bahan) ?> Bahan Terpilih
                                            <?php else: ?>
                                                Ringkasan Semua Bahan Baku
                                            <?php endif; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0 summary-bahan-table">
                                                <thead>
                                                    <tr>
                                                        <th>No</th>
                                                        <th>Nama Bahan</th>
                                                        <th>Satuan</th>
                                                        <th class="text-end">Jumlah (Roll)</th>
                                                        <th class="text-end">Total Meter</th>
                                                        <!-- <th class="text-end">Harga Per Meter</th> -->
                                                        <th class="text-end">Total Nilai</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $total_jumlah_summary = 0;
                                                    $total_meter_summary = 0;
                                                    $total_nilai_summary = 0;
                                                    ?>

                                                    <?php foreach ($summary_per_bahan as $index => $bahan): ?>
                                                        <?php
                                                        // Hitung harga rata-rata per meter
                                                        $harga_rata_rata = ($bahan['total_harga_satuan'] > 0 && $bahan['total_jumlah'] > 0)
                                                            ? $bahan['total_harga_satuan'] / $bahan['total_jumlah']
                                                            : 0;

                                                        // Hitung total nilai
                                                        $total_nilai_bahan = $bahan['total_subtotal'];

                                                        // Akumulasi untuk total
                                                        $total_jumlah_summary += $bahan['total_jumlah'];
                                                        $total_meter_summary += $bahan['total_meter'];
                                                        $total_nilai_summary += $total_nilai_bahan;
                                                        ?>
                                                        <tr>
                                                            <td class="text-center"><?= $index + 1 ?></td>
                                                            <td>
                                                                <?= htmlspecialchars($bahan['nama_bahan']) ?>
                                                                <?php if (!empty($filter_bahan) && in_array($bahan['id_bahan'], $filter_bahan)): ?>
                                                                    <span class="badge bg-success ms-1">Terpilih</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center"><?= htmlspecialchars($bahan['satuan']) ?></td>
                                                            <td class="text-end"><?= number_format($bahan['total_jumlah'], 0, ',', '.') ?></td>
                                                            <td class="text-end"><?= number_format($bahan['total_meter'], 0, ',', '.') ?> m</td>
                                                            <!-- <td class="text-end text-money">
                                                                <?= formatRupiah($harga_rata_rata) ?>/m
                                                            </td> -->
                                                            <td class="text-end text-money"><?= formatRupiah($total_nilai_bahan) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr class="table-light">
                                                        <th colspan="3" class="text-end">TOTAL</th>
                                                        <th class="text-end"><?= number_format($total_jumlah_summary, 0, ',', '.') ?></th>
                                                        <th class="text-end"><?= number_format($total_meter_summary, 0, ',', '.') ?> m</th>
                                                        <!-- <th class="text-end">-</th> -->
                                                        <th class="text-end text-primary"><?= formatRupiah($total_nilai_summary) ?></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Data Detail -->
                    <div class="card">


                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0 d-flex align-items-center gap-2">
                                <i class="ti ti-table"></i>
                                Detail Pembelian Bahan Baku
                                <?php if (!empty($filter_bahan)): ?>
                                    <span class="badge bg-info ms-2">
                                        Filter: <?= count($filter_bahan) ?> bahan baku terpilih
                                    </span>
                                <?php endif; ?>
                            </h5>
                        </div>

                        <div class="card-body">
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                                    <i class="ti ti-check me-2"></i>
                                    <div><?= htmlspecialchars($_SESSION['success']) ?></div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>

                            <?php
                            $showErrorPopup = false;
                            $errorMessage = '';
                            if (isset($_SESSION['error'])):
                                $showErrorPopup = true;
                                $errorMessage = $_SESSION['error'];
                            ?>
                                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                                    <i class="ti ti-alert-circle me-2"></i>
                                    <div><?= htmlspecialchars($_SESSION['error']) ?></div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0" id="dataTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Supplier</th>
                                            <th>Bahan Baku</th>
                                            <th class="text-center">Jumlah</th>
                                            <th class="text-center">Meter</th>
                                            <th class="text-end">Harga/m</th>
                                            <th class="text-end">Subtotal</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($detail_pembelian)): ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                                                        Tidak ada data pembelian bahan
                                                        <?php if (!empty($search) || $filter_supplier > 0 || !empty($filter_bahan) || !empty($filter_status)): ?>
                                                            <br><small>Coba gunakan filter yang berbeda</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php
                                            $current_pembelian_id = null;
                                            $row_number = 0;
                                            $current_transaksi_total = 0;
                                            $grand_total = 0;
                                            ?>

                                            <?php foreach ($detail_pembelian as $item): ?>
                                                <?php
                                                $row_number++;
                                                $hitung_subtotal = $item['harga_satuan'] * $item['meter'];
                                                $grand_total += $hitung_subtotal;
                                                ?>

                                                <!-- Baris Transaksi Baru (Supplier) -->
                                                <?php if ($current_pembelian_id != $item['id_pembelian_bahan']): ?>
                                                    <?php if ($current_pembelian_id !== null): ?>
                                                        <!-- Total Transaksi Sebelumnya -->
                                                        <tr class="table-light">
                                                            <td colspan="6" class="text-end fw-bold">Total Transaksi:</td>
                                                            <td class="text-end fw-bold text-primary">
                                                                <?= formatRupiah($current_transaksi_total) ?>
                                                            </td>
                                                            <td colspan="2"></td>
                                                        </tr>
                                                    <?php endif; ?>

                                                    <?php
                                                    $current_pembelian_id = $item['id_pembelian_bahan'];
                                                    $current_transaksi_total = 0;
                                                    ?>

                                                    <!-- Baris Header Transaksi -->
                                                    <tr class="table-active">
                                                        <td>
                                                            <div class="fw-bold"><?= dateIndo($item['tanggal_pembelian']) ?></div>
                                                            <small class="text-muted">ID Transaksi: <?= $item['id_pembelian_bahan'] ?></small>
                                                        </td>
                                                        <td colspan="2">
                                                            <div class="fw-bold"><?= htmlspecialchars($item['nama_supplier']) ?></div>
                                                        </td>
                                                        <td colspan="2"></td> <!-- Ini kolom 4 & 5 -->
                                                        <td></td> <!-- Ini kolom 6 -->
                                                        <td></td> <!-- Ini kolom 7 -->
                                                        <td class="text-center">
                                                            <span class="badge <?= $item['status_pembayaran'] == 'lunas' ? 'bg-success' : ($item['status_pembayaran'] == 'cicilan' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                                                <?= ucfirst($item['status_pembayaran']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="btn-group btn-group-sm">
                                                                <?php if ($item['status_pembayaran'] != 'batal'): ?>
                                                                    <button class="btn btn-sm btn-danger btn-batal"
                                                                        data-id="<?= $item['id_pembelian_bahan'] ?>"
                                                                        data-no="<?= $item['no_transaksi'] ?>"
                                                                        title="Batalkan">
                                                                        <i class="ti ti-x"></i>
                                                                    </button>
                                                                    <?php if ($item['status_pembayaran'] == 'cicilan'): ?>
                                                                        <a href="cicilan.php?id=<?= $item['id_pembelian_bahan'] ?>"
                                                                            class="btn btn-sm btn-warning"
                                                                            title="Cicilan">
                                                                            <i class="ti ti-cash"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Dibatalkan</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>

                                                <!-- Baris Detail Bahan -->
                                                <?php $current_transaksi_total += $hitung_subtotal; ?>
                                                <!-- Baris Detail Bahan -->
                                                <tr>
                                                    <td></td>
                                                    <td></td>
                                                    <td>
                                                        <div><?= htmlspecialchars($item['nama_bahan']) ?></div>
                                                        <?php if (!empty($filter_bahan) && in_array($item['id_bahan'], $filter_bahan)): ?>
                                                            <small class="text-success">
                                                                <i class="bi bi-check-circle"></i> Terpilih
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= number_format($item['jumlah'], 0, ',', '.') ?> Roll
                                                    </td>
                                                    <td class="text-center">
                                                        <?= number_format($item['meter'], 0, ',', '.') ?> Meter
                                                    </td>
                                                    <td class="text-end">
                                                        <div><?= formatRupiah($item['harga_satuan']) ?></div>
                                                    </td>
                                                    <td class="text-end fw-bold">
                                                        <?= formatRupiah($hitung_subtotal) ?>
                                                    </td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Total Transaksi Terakhir -->
                                            <?php if ($current_pembelian_id !== null): ?>
                                                <!-- Total Transaksi Sebelumnya -->
                                                <tr class="table-light">
                                                    <td colspan="6" class="text-end fw-bold">Total Transaksi:</td>
                                                    <td class="text-end fw-bold text-primary">
                                                        <?= formatRupiah($current_transaksi_total) ?>
                                                    </td>
                                                    <td colspan="2"></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <!-- <tfoot>
                                        <tr class="table-info">
                                            <th colspan="6" class="text-end">GRAND TOTAL:</th>
                                            <th class="text-end">
                                                <?= isset($grand_total) ? formatRupiah($grand_total) : formatRupiah(0) ?>
                                            </th>
                                            <th colspan="2"></th>
                                        </tr>
                                    </tfoot> -->
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <!-- DataTables Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {

            // Handle dropdown tidak menutup saat klik checkbox
            $(document).on('click', '.multiselect-dropdown .dropdown-item', function(e) {
                e.stopPropagation();
            });

            // Handle select all checkbox
            $('#selectAllBahan').change(function() {
                const isChecked = $(this).is(':checked');
                $('.multiselect-dropdown input[name="bahan[]"]').prop('checked', isChecked);
                updateSelectedBahan();
            });

            // Update selected bahan ketika checkbox berubah
            $('.multiselect-dropdown input[name="bahan[]"]').change(function() {
                updateSelectedBahan();
                updateSelectAllCheckbox();
            });

            // Update tampilan bahan yang dipilih saat pertama kali load
            updateSelectedBahan();

            // Update tanggal default
            setDefaultDates();
        });

        // Fungsi untuk update tampilan bahan yang dipilih
        function updateSelectedBahan() {
            const selectedBahan = [];
            const selectedNames = [];

            $('.multiselect-dropdown input[name="bahan[]"]:checked').each(function() {
                const id = $(this).val();
                const name = $(this).next('label').text().trim();
                selectedBahan.push({
                    id: id,
                    name: name
                });
                selectedNames.push(name);
            });

            // Update tombol dropdown
            const dropdownBtn = $('#bahanDropdown');
            if (selectedBahan.length === 0) {
                dropdownBtn.text('Pilih Bahan...');
            } else {
                dropdownBtn.text(selectedBahan.length + ' bahan dipilih');
            }

            // Update tampilan badge
            const badgeContainer = $('.selected-bahan-container');
            badgeContainer.empty();

            selectedBahan.forEach(function(bahan) {
                const badge = $(`
                <span class="selected-bahan-badge" data-id="${bahan.id}">
                    ${bahan.name}
                    <span class="close" onclick="removeBahan(${bahan.id})">&times;</span>
                </span>
            `);
                badgeContainer.append(badge);
            });
        }

        // Fungsi untuk update select all checkbox
        function updateSelectAllCheckbox() {
            const totalCheckboxes = $('.multiselect-dropdown input[name="bahan[]"]').length;
            const checkedCheckboxes = $('.multiselect-dropdown input[name="bahan[]"]:checked').length;

            $('#selectAllBahan').prop('checked', totalCheckboxes === checkedCheckboxes);
            $('#selectAllBahan').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        }

        // Fungsi untuk menghapus bahan dari pilihan
        function removeBahan(bahanId) {
            $(`#bahan_${bahanId}`).prop('checked', false);
            updateSelectedBahan();
            updateSelectAllCheckbox();

            // Submit form secara otomatis saat menghapus bahan
            $('#mainFilterForm').submit();
        }

        // Fungsi untuk set tanggal default
        function setDefaultDates() {
            const urlParams = new URLSearchParams(window.location.search);
            const tanggalAwalInput = $('input[name="tanggal_awal"]');
            const tanggalAkhirInput = $('input[name="tanggal_akhir"]');

            if (!urlParams.has('tanggal_awal') && !tanggalAwalInput.val()) {
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

                tanggalAwalInput.val(firstDay.toISOString().split('T')[0]);
                tanggalAkhirInput.val(lastDay.toISOString().split('T')[0]);
            }
        }

        // Cetak laporan
        function printReport() {
            window.print();
        }

        // Export data ke Excel dengan validasi
        function exportToExcel() {
            const table = $('#dataTable');
            const hasData = table.find('tbody tr').length > 1 ||
                (table.find('tbody tr').length === 1 &&
                    !table.find('tbody tr td[colspan]').length);

            if (!hasData) {
                Swal.fire({
                    title: 'Tidak Ada Data',
                    text: 'Tidak ada data untuk di-export.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            Swal.fire({
                title: 'Export ke Excel',
                text: 'Sedang menyiapkan data...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();

                    setTimeout(() => {
                        try {
                            let csvContent = "data:text/csv;charset=utf-8,\uFEFF";

                            // Header
                            const headers = [
                                'No',
                                'Tanggal',
                                'Supplier',
                                'Bahan Baku',
                                'Jumlah (pcs)',
                                'Meter (m)',
                                'Harga per Meter',
                                'Subtotal',
                                'Status'
                            ];
                            csvContent += headers.join(",") + "\r\n";

                            // Data - hanya ambil dari baris yang memiliki data (bukan baris kosong)
                            <?php if (!empty($detail_pembelian)): ?>
                                <?php
                                $export_number = 0;
                                foreach ($detail_pembelian as $item):
                                    $export_number++;
                                    $hitung_subtotal = $item['harga_satuan'] * $item['meter'];
                                ?>
                                    const rowData = [
                                        "<?= $export_number ?>",
                                        "<?= date('d/m/Y H:i', strtotime($item['tanggal_pembelian'])) ?>",
                                        "<?= addslashes($item['nama_supplier']) ?>",
                                        "<?= addslashes($item['nama_bahan']) ?>",
                                        "<?= $item['jumlah'] ?>",
                                        "<?= number_format($item['meter'], 2, ',', '.') ?>",
                                        "<?= $item['harga_satuan'] ?>",
                                        "<?= $hitung_subtotal ?>",
                                        "<?= ucfirst($item['status_pembayaran']) ?>"
                                    ];
                                    csvContent += rowData.map(cell => `"${cell}"`).join(",") + "\r\n";
                                <?php endforeach; ?>
                            <?php endif; ?>

                            // Download
                            const encodedUri = encodeURI(csvContent);
                            const link = document.createElement("a");
                            link.setAttribute("href", encodedUri);
                            link.setAttribute("download", `laporan_pembelian_bahan_${new Date().toISOString().split('T')[0]}.csv`);
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);

                            Swal.close();
                        } catch (error) {
                            Swal.fire({
                                title: 'Error',
                                text: 'Terjadi kesalahan saat export data: ' + error.message,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    }, 1000);
                }
            });
        }
    </script>

    <script>
        // document.querySelectorAll('.btn-batal').forEach(button => {
        //     button.addEventListener('click', function(e) {
        //         e.preventDefault();
        //         const id = this.getAttribute('data-id');
        //         const noTransaksi = this.getAttribute('data-no') || id;
        //         const statusPembayaran = this.closest('tr').querySelector('.badge')?.textContent.trim().toLowerCase() || '';

        //         let warningMessage = 'Tindakan ini akan mengurangi stok bahan dan menghapus data pembelian bahan!';

        //         // Tambahkan peringatan khusus untuk status cicilan
        //         if (statusPembayaran === 'cicilan') {
        //             warningMessage = '<strong>⚠️ Perhatian:</strong> Jika sudah ada pembayaran cicilan, pembelian tidak dapat dibatalkan. Silakan batalkan cicilan terlebih dahulu!<br><br>' + warningMessage;
        //         }

        //         Swal.fire({
        //             title: 'Batalkan Pembelian?',
        //             html: `Nomor Transaksi: <strong>${noTransaksi}</strong><br><br>${warningMessage}`,
        //             icon: 'warning',
        //             showCancelButton: true,
        //             confirmButtonColor: '#d33',
        //             cancelButtonColor: '#6c757d',
        //             confirmButtonText: 'Ya, batalkan!',
        //             cancelButtonText: 'Batal'
        //         }).then((result) => {
        //             if (result.isConfirmed) {
        //                 window.location.href = 'batal.php?id=' + id;
        //             }
        //         });
        //     });
        // });

        // Handle tombol batal pembelian
        $(document).on('click', '.btn-batal', function(e) {
            e.preventDefault();

            const pembelianId = $(this).data('id');
            const id = this.getAttribute('data-id');
            const noTransaksi = this.getAttribute('data-no') || id;

            Swal.fire({
                title: 'Batalkan Pembelian?',
                html: `Apakah Anda yakin ingin membatalkan pembelian <strong>#${noTransaksi}</strong>?<br>
                       <small class="text-danger">Aksi ini akan mengembalikan stok bahan!</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Batalkan!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'batal.php?id=' + pembelianId;
                }
            });
        });
    </script>

</body>

</html>