<?php
// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

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
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .summary-card h3 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }

        .summary-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 13px;
        }

        .summary-card.orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .summary-card.green {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .summary-card.purple {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #333;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .text-money {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

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

        .selected-bahan-badge .close {
            margin-left: 5px;
            cursor: pointer;
            font-size: 14px;
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>LAPORAN PEMBELIAN BAHAN BAKU</h2>
                    </div>

                    <!-- Card Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="summary-card purple">
                                <h3><?= number_format($summary_manual['total_transaksi'], 0, ',', '.') ?></h3>
                                <p>Total Transaksi</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h3><?= number_format($summary_manual['total_item'], 0, ',', '.') ?></h3>
                                <p>Total Item Dibeli</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card orange">
                                <h3><?= number_format($summary_manual['total_meter_all'], 1, ',', '.') ?> m</h3>
                                <p>Total Meter Pembelian</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card green">
                                <h3><?= formatRupiah($summary_manual['total_nilai']) ?></h3>
                                <p>Total Nilai Pembelian</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Filter Utama (untuk semua data) -->
                    <div class="card filter-card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Data Pembelian</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="mainFilterForm" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Pencarian</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" name="search"
                                            placeholder="No. Transaksi/Supplier/Bahan..."
                                            value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>

                                <div class="col-md-3">
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

                                <div class="col-md-3">
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

                                <div class="col-md-3">
                                    <label class="form-label">Tanggal Pembelian</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                        <input type="date" class="form-control" name="tanggal_awal"
                                            value="<?= htmlspecialchars($filter_tanggal_awal) ?>">
                                        <span class="input-group-text">s/d</span>
                                        <input type="date" class="form-control" name="tanggal_akhir"
                                            value="<?= htmlspecialchars($filter_tanggal_akhir) ?>">
                                    </div>
                                </div>

                                <div class="col-12 filter-actions">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <?php if (!empty($filter_bahan)): ?>
                                                <span class="text-muted">
                                                    <i class="bi bi-info-circle"></i>
                                                    Menampilkan <?= count($filter_bahan) ?> bahan terpilih
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="list.php" class="btn btn-secondary">
                                                <i class="bi bi-arrow-clockwise me-1"></i> Reset Semua
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-filter me-1"></i> Terapkan Filter
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Summary per Bahan (selalu tampil jika ada data) -->
                    <?php if (!empty($summary_per_bahan)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>
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
                                                <th width="5%">No</th>
                                                <th width="30%">Nama Bahan</th>
                                                <th width="10%">Satuan</th>
                                                <th width="10%" class="text-end">Jumlah (pcs)</th>
                                                <th width="10%" class="text-end">Total Meter (m)</th>
                                                <th width="20%" class="text-end">Harga Rata-rata/m</th>
                                                <th width="20%" class="text-end">Total Nilai</th>
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
                                                    <td class="text-end"><?= number_format($bahan['total_meter'], 2, ',', '.') ?> m</td>
                                                    <td class="text-end text-money">
                                                        <?= formatRupiah($harga_rata_rata) ?>/m
                                                    </td>
                                                    <td class="text-end text-money"><?= formatRupiah($total_nilai_bahan) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <th colspan="3" class="text-end">TOTAL:</th>
                                                <th class="text-end"><?= number_format($total_jumlah_summary, 0, ',', '.') ?></th>
                                                <th class="text-end"><?= number_format($total_meter_summary, 2, ',', '.') ?> m</th>
                                                <th class="text-end">-</th>
                                                <th class="text-end text-primary"><?= formatRupiah($total_nilai_summary) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Card Data Detail -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="bi bi-table me-2"></i>Detail Pembelian
                                <?php if (!empty($filter_bahan)): ?>
                                    <small class="text-muted">(Filter: <?= count($filter_bahan) ?> bahan terpilih)</small>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <div><?= htmlspecialchars($_SESSION['success']) ?></div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <div><?= htmlspecialchars($_SESSION['error']) ?></div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table class="table table-hover" id="dataTable">
                                    <thead>
                                        <tr>
                                            <th width="50">No</th>
                                            <th width="100">Tanggal</th>
                                            <th>Supplier</th>
                                            <th>Bahan Baku</th>
                                            <th width="80" class="text-center">Jumlah</th>
                                            <th width="80" class="text-center">Meter</th>
                                            <th width="100" class="text-end">Harga/m</th>
                                            <th width="120" class="text-end">Subtotal</th>
                                            <th width="80" class="text-center">Status</th>
                                            <th width="20" class="text-center">Aksi</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($detail_pembelian)): ?>
                                            <!-- Baris kosong dengan 9 TD (sesuai jumlah kolom) -->
                                            <tr>
                                                <td class="text-center py-5" colspan="9">
                                                    <div class="alert alert-info mb-0 d-flex flex-column align-items-center">
                                                        <i class="bi bi-inbox mb-2" style="font-size: 2rem;"></i>
                                                        <p class="mb-0">Tidak ada data pembelian bahan</p>
                                                        <?php if (!empty($search) || $filter_supplier > 0 || !empty($filter_bahan)): ?>
                                                            <small class="text-muted">Coba gunakan filter yang berbeda</small>
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

                                                // Hitung subtotal manual: harga_satuan × meter
                                                $hitung_subtotal = $item['harga_satuan'] * $item['meter'];
                                                $grand_total += $hitung_subtotal;

                                                // Tampilkan baris supplier jika pembelian berbeda
                                                if ($current_pembelian_id != $item['id_pembelian_bahan']):
                                                    // Tampilkan total transaksi sebelumnya jika ada
                                                    if ($current_pembelian_id !== null): ?>
                                                        <!-- Baris total transaksi - PASTIKAN ADA 10 TD -->
                                                        <tr class="table-secondary">
                                                            <td colspan="6" class="text-end"><strong>Total Transaksi:</strong></td>
                                                            <td></td> <!-- Kolom Harga/m -->
                                                            <td class="text-end text-money">
                                                                <strong><?= formatRupiah($current_transaksi_total) ?></strong>
                                                            </td>
                                                            <td></td> <!-- Kolom Status -->
                                                            <td></td> <!-- Kolom Aksi -->
                                                        </tr>
                                                    <?php endif;

                                                    // Reset untuk transaksi baru
                                                    $current_pembelian_id = $item['id_pembelian_bahan'];
                                                    $current_transaksi_total = 0;
                                                    $status_class = $item['status_pembayaran'] == 'lunas' ? 'status-lunas' : 'status-cicilan';
                                                    ?>
                                                    <!-- Baris supplier - PASTIKAN ADA 10 TD -->
                                                    <tr class="supplier-row">
                                                        <td class="text-center"><?= $row_number ?></td>
                                                        <td>
                                                            <strong><?= date('d/m/Y', strtotime($item['tanggal_pembelian'])) ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?= date('H:i', strtotime($item['tanggal_pembelian'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($item['nama_supplier']) ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?= $item['no_transaksi'] ?></small>
                                                        </td>
                                                        <td></td> <!-- Kolom Bahan Baku (kosong di baris supplier) -->
                                                        <td></td> <!-- Kolom Jumlah -->
                                                        <td></td> <!-- Kolom Meter -->
                                                        <td></td> <!-- Kolom Harga/m -->
                                                        <td></td> <!-- Kolom Subtotal -->
                                                        <td class="text-center">
                                                            <span class="status-badge <?= $status_class ?>">
                                                                <?= ucfirst($item['status_pembayaran']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="btn-group btn-group-sm" role="group" aria-label="Aksi Pembelian">
                                                                <?php if ($item['status_pembayaran'] != 'batal'): ?>
                                                                    <button class="btn btn-outline-danger btn-batal"
                                                                        data-id="<?= $item['id_pembelian_bahan'] ?>"
                                                                        data-no="<?= $item['no_transaksi'] ?>"
                                                                        title="Batalkan Pembelian">
                                                                        <i class="bi bi-x-circle"></i>
                                                                    </button>
                                                                    <?php if ($item['status_pembayaran'] == 'cicilan'): ?>
                                                                        <a href="cicilan.php?id=<?= $item['id_pembelian_bahan'] ?>"
                                                                            class="btn btn-outline-warning"
                                                                            title="Pembayaran Cicilan">
                                                                            <i class="bi bi-cash-stack"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <a href="detail.php?id=<?= $item['id_pembelian_bahan'] ?>"
                                                                        class="btn btn-outline-primary"
                                                                        title="Detail Pembelian">
                                                                        <i class="bi bi-eye"></i>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Dibatalkan</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>

                                                <!-- Baris detail bahan - PASTIKAN ADA 9 TD -->
                                                <?php
                                                // Tambahkan ke total transaksi
                                                $current_transaksi_total += $hitung_subtotal;
                                                ?>
                                                <tr class="bahan-row">
                                                    <td></td> <!-- Kolom No -->
                                                    <td></td> <!-- Kolom Tanggal -->
                                                    <td></td> <!-- Kolom Supplier -->
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($item['nama_bahan']) ?></div>
                                                        <?php if (!empty($filter_bahan) && in_array($item['id_bahan'], $filter_bahan)): ?>
                                                            <span class="badge bg-success ms-1">Terpilih</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary rounded-pill"><?= $item['jumlah'] ?> pcs</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= number_format($item['meter'], 2, ',', '.') ?> m
                                                    </td>
                                                    <td class="text-end text-money">
                                                        <?= formatRupiah($item['harga_satuan']) ?>
                                                    </td>
                                                    <td class="text-end text-money">
                                                        <strong><?= formatRupiah($hitung_subtotal) ?></strong>
                                                        <?php if (abs($hitung_subtotal - $item['subtotal']) > 0.01): ?>
                                                            <br>
                                                            <small class="text-danger">
                                                                <i class="bi bi-exclamation-triangle"></i>
                                                                DB: <?= formatRupiah($item['subtotal']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td></td> <!-- Kolom Status (kosong) -->
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Tampilkan total untuk transaksi terakhir - PASTIKAN ADA 9 TD -->
                                            <?php if ($current_pembelian_id !== null): ?>
                                                <tr class="table-secondary">
                                                    <td colspan="5" class="text-end"><strong>Total Transaksi:</strong></td>
                                                    <td></td> <!-- Kolom Meter -->
                                                    <td></td> <!-- Kolom Harga/m -->
                                                    <td class="text-end text-money">
                                                        <strong><?= formatRupiah($current_transaksi_total) ?></strong>
                                                    </td>
                                                    <td></td> <!-- Kolom Status -->
                                                </tr>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <th colspan="5" class="text-end">GRAND TOTAL:</th>
                                            <th></th> <!-- Kolom Meter -->
                                            <th></th> <!-- Kolom Harga/m -->
                                            <th class="text-end text-primary">
                                                <?= isset($grand_total) ? formatRupiah($grand_total) : formatRupiah(0) ?>
                                            </th>
                                            <th></th> <!-- Kolom Status -->
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Info Perhitungan -->
                            <div class="alert alert-info mt-3">
                                <h6><i class="bi bi-info-circle me-2"></i>Info Perhitungan:</h6>
                                <small>
                                    • Subtotal = Harga per Meter × Meter<br>
                                    • Total per Transaksi = Jumlah semua subtotal dalam transaksi yang sama<br>
                                    • Grand Total = Jumlah semua subtotal dari seluruh data<br>
                                    • <span class="text-danger"><i class="bi bi-exclamation-triangle"></i> DB:</span> menunjukkan nilai subtotal yang tersimpan di database (jika berbeda dengan perhitungan)
                                </small>
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
            // Cek apakah ada data pembelian
            const hasDetailPembelian = <?= !empty($detail_pembelian) ? 'true' : 'false' ?>;

            if (hasDetailPembelian) {
                // Inisialisasi DataTables hanya jika ada data
                $('#dataTable').DataTable({
                    "language": {
                        "search": "Cari:",
                        "lengthMenu": "Tampilkan _MENU_ data per halaman",
                        "zeroRecords": "Tidak ada data yang ditemukan",
                        "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                        "infoEmpty": "Tidak ada data",
                        "infoFiltered": "(disaring dari _MAX_ total data)",
                        "paginate": {
                            "first": "Pertama",
                            "last": "Terakhir",
                            "next": "Selanjutnya",
                            "previous": "Sebelumnya"
                        }
                    },
                    "order": [
                        [1, "desc"]
                    ],
                    "pageLength": 50,
                    "responsive": true,
                    "dom": '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                    "columnDefs": [{
                        "orderable": false,
                        "targets": [0, 2, 3, 4, 5, 6, 7, 8]
                    }]
                });
            }

            if (hasData) {
                // Jika ada data, inisialisasi DataTables normal
                $('#dataTable').DataTable(dtConfig);
            } else {
                // Jika tidak ada data, nonaktifkan beberapa fitur
                dtConfig.searching = false;
                dtConfig.ordering = false;
                dtConfig.info = false;
                dtConfig.lengthChange = false;
                dtConfig.paging = false;
                $('#dataTable').DataTable(dtConfig);
            }

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
        document.querySelectorAll('.btn-batal').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');

                Swal.fire({
                    title: 'Yakin ingin membatalkan pembelian bahan ini?',
                    text: "Tindakan ini akan mengembalikan stok produk dan menghapus data pembelian bahan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, batalkan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'batal.php?id=' + id;
                    }
                });
            });
        });
    </script>

</body>

</html>