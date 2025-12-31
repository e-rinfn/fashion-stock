<?php

// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Ambil data dengan filter dan pencarian
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_supplier = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;
$filter_bahan = isset($_GET['bahan']) ? intval($_GET['bahan']) : 0;
$filter_tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : '';
$filter_tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

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
            (dpb.jumlah * dpb.meter) as total_meter,
            (dpb.harga_satuan * dpb.meter) as hitung_subtotal
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

// Filter bahan
if ($filter_bahan > 0) {
    $query .= " AND dpb.id_bahan = $filter_bahan";
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

// Hitung ulang subtotal untuk memastikan perhitungan benar
foreach ($detail_pembelian as &$item) {
    // Hitung subtotal = harga_satuan × meter
    $item['hitung_subtotal'] = $item['harga_satuan'] * $item['meter'];
    // Untuk perbandingan, hitung juga total jika dikali jumlah
    $item['hitung_total'] = $item['harga_satuan'] * $item['meter'] * $item['jumlah'];
}

// Ambil data supplier untuk filter
$suppliers = query("SELECT * FROM supplier ORDER BY nama_supplier");

// Ambil data bahan untuk filter
$bahan_list = query("SELECT * FROM bahan_baku ORDER BY nama_bahan");

// Hitung summary total dengan perhitungan manual
$total_transaksi = 0;
$total_item = 0;
$total_nilai = 0;
$total_jumlah = 0;
$total_meter = 0;
$total_meter_all = 0;

if (!empty($detail_pembelian)) {
    $transaksi_ids = [];
    foreach ($detail_pembelian as $item) {
        if (!in_array($item['id_pembelian_bahan'], $transaksi_ids)) {
            $transaksi_ids[] = $item['id_pembelian_bahan'];
            $total_transaksi++;
        }

        $total_item++;
        $total_jumlah += $item['jumlah'];
        $total_meter += $item['meter'];
        $total_meter_all += ($item['jumlah'] * $item['meter']);

        // Gunakan perhitungan manual untuk total nilai
        $total_nilai += ($item['harga_satuan'] * $item['meter']);
    }
}

// Hitung summary per bahan jika tidak difilter bahan tertentu
$summary_per_bahan = [];
if ($filter_bahan == 0 && !empty($detail_pembelian)) {
    $bahan_data = [];

    foreach ($detail_pembelian as $item) {
        $bahan_id = $item['id_bahan'];

        if (!isset($bahan_data[$bahan_id])) {
            $bahan_data[$bahan_id] = [
                'id_bahan' => $bahan_id,
                'nama_bahan' => $item['nama_bahan'],
                'satuan' => $item['satuan'],
                'total_jumlah' => 0,
                'total_meter' => 0,
                'total_subtotal' => 0,
                'harga_rata_rata' => 0,
                'total_nilai' => 0
            ];
        }

        $bahan_data[$bahan_id]['total_jumlah'] += $item['jumlah'];
        $bahan_data[$bahan_id]['total_meter'] += $item['meter'];
        $bahan_data[$bahan_id]['total_subtotal'] += $item['harga_satuan'];
        $bahan_data[$bahan_id]['total_nilai'] += ($item['harga_satuan'] * $item['meter']);
    }

    // Hitung harga rata-rata per bahan
    foreach ($bahan_data as &$bahan) {
        if ($bahan['total_jumlah'] > 0) {
            $bahan['harga_rata_rata'] = $bahan['total_subtotal'] / $bahan['total_jumlah'];
        }
    }

    $summary_per_bahan = array_values($bahan_data);
}

// Siapkan data untuk summary
$summary = [
    'total_transaksi' => $total_transaksi,
    'total_item' => $total_item,
    'total_nilai' => $total_nilai,
    'total_jumlah' => $total_jumlah,
    'total_meter' => $total_meter,
    'total_meter_all' => $total_meter_all
];
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

        .summary-card.blue {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
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

        .table-container {
            max-height: 600px;
            overflow-y: auto;
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
                        <h2><i class="bi bi-clipboard-data me-2"></i>LAPORAN PEMBELIAN BAHAN BAKU</h2>
                        <div>
                            <button onclick="printReport()" class="btn btn-primary">
                                <i class="bi bi-printer me-1"></i> Cetak Laporan
                            </button>
                        </div>
                    </div>

                    <!-- Card Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="summary-card purple">
                                <h3><?= number_format($summary['total_transaksi'], 0, ',', '.') ?></h3>
                                <p>Total Transaksi</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h3><?= number_format($summary['total_item'], 0, ',', '.') ?></h3>
                                <p>Total Item Dibeli</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card orange">
                                <h3><?= number_format($summary['total_meter_all'], 1, ',', '.') ?> m</h3>
                                <p>Total Meter Pembelian</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card green">
                                <h3><?= formatRupiah($summary['total_nilai']) ?></h3>
                                <p>Total Nilai Pembelian</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Filter -->
                    <div class="card filter-card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Laporan</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Pencarian</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" name="search"
                                            placeholder="No. Transaksi/Supplier/Bahan..."
                                            value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>

                                <div class="col-md-2">
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

                                <div class="col-md-2">
                                    <label class="form-label">Bahan Baku</label>
                                    <select class="form-control" name="bahan">
                                        <option value="">Semua Bahan</option>
                                        <?php foreach ($bahan_list as $b): ?>
                                            <option value="<?= $b['id_bahan'] ?>"
                                                <?= $filter_bahan == $b['id_bahan'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($b['nama_bahan']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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

                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="d-flex w-100">
                                        <a href="list.php" class="btn btn-secondary me-2 flex-grow-1">
                                            <i class="bi bi-arrow-clockwise me-1"></i> Reset
                                        </a>
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="bi bi-search me-1"></i> Filter
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Summary per Bahan (jika tidak difilter bahan tertentu) -->
                    <?php if (empty($filter_bahan) && !empty($summary_per_bahan)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Ringkasan per Bahan Baku</h5>
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
                                                <th width="15%" class="text-end">Harga Rata-rata/m</th>
                                                <th width="20%" class="text-end">Total Nilai</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $grand_total_jumlah = 0;
                                            $grand_total_meter = 0;
                                            $grand_total_nilai = 0;
                                            ?>
                                            <?php foreach ($summary_per_bahan as $index => $bahan): ?>
                                                <?php
                                                $grand_total_jumlah += $bahan['total_jumlah'];
                                                $grand_total_meter += $bahan['total_meter'];
                                                $grand_total_nilai += $bahan['total_nilai'];
                                                ?>
                                                <tr>
                                                    <td class="text-center"><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($bahan['nama_bahan']) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($bahan['satuan']) ?></td>
                                                    <td class="text-end"><?= number_format($bahan['total_jumlah'], 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= number_format($bahan['total_meter'], 2, ',', '.') ?> m</td>
                                                    <td class="text-end text-money">
                                                        <?= formatRupiah($bahan['harga_rata_rata']) ?>/m
                                                    </td>
                                                    <td class="text-end text-money">
                                                        <?= formatRupiah($bahan['total_nilai']) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <th colspan="3" class="text-end">TOTAL:</th>
                                                <th class="text-end"><?= number_format($grand_total_jumlah, 0, ',', '.') ?></th>
                                                <th class="text-end"><?= number_format($grand_total_meter, 2, ',', '.') ?> m</th>
                                                <th class="text-end">-</th>
                                                <th class="text-end text-primary"><?= formatRupiah($grand_total_nilai) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Card Data Detail -->
                    <div class="card">
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

                            <div class="table-container">
                                <table class="table table-hover" id="dataTable">
                                    <thead>
                                        <tr>
                                            <th width="50">No</th>
                                            <th width="100">Tanggal</th>
                                            <th>Supplier</th>
                                            <th>Bahan Baku</th>
                                            <th width="80" class="text-center">Jumlah</th>
                                            <th width="80" class="text-center">Meter (m)</th>
                                            <th width="100" class="text-end">Harga/m</th>
                                            <th width="120" class="text-end">Subtotal</th>
                                            <th width="80" class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($detail_pembelian)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-5">
                                                    <div class="alert alert-info mb-0 d-flex flex-column align-items-center">
                                                        <i class="bi bi-inbox mb-2" style="font-size: 2rem;"></i>
                                                        <p class="mb-0">Tidak ada data pembelian bahan</p>
                                                        <?php if (!empty($search) || $filter_supplier > 0 || $filter_bahan > 0): ?>
                                                            <small class="text-muted">Coba gunakan filter yang berbeda</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php
                                            $current_pembelian_id = null;
                                            $row_number = 0;
                                            $total_per_transaksi = 0;
                                            $current_transaksi_total = 0;
                                            ?>

                                            <?php foreach ($detail_pembelian as $item): ?>
                                                <?php
                                                $row_number++;

                                                // Hitung subtotal manual
                                                $hitung_subtotal = $item['harga_satuan'] * $item['meter'];

                                                // Tampilkan baris supplier jika pembelian berbeda
                                                if ($current_pembelian_id != $item['id_pembelian_bahan']):
                                                    // Tampilkan total transaksi sebelumnya
                                                    if ($current_pembelian_id !== null): ?>
                                                        <tr class="table-secondary">
                                                            <td colspan="7" class="text-end"><strong>Total Transaksi:</strong></td>
                                                            <td class="text-end text-money">
                                                                <strong><?= formatRupiah($current_transaksi_total) ?></strong>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                    <?php endif;

                                                    // Reset untuk transaksi baru
                                                    $current_pembelian_id = $item['id_pembelian_bahan'];
                                                    $current_transaksi_total = 0;
                                                    $status_class = 'status-' . $item['status_pembayaran'];
                                                    ?>
                                                    <tr class="supplier-row">
                                                        <td class="text-center"><?= $row_number ?></td>
                                                        <td>
                                                            <strong><?= date('d/m/Y', strtotime($item['tanggal_pembelian'])) ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?= date('H:i', strtotime($item['tanggal_pembelian'])) ?></small>
                                                        </td>
                                                        <td colspan="2">
                                                            <strong><?= htmlspecialchars($item['nama_supplier']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">No. Transaksi: <?= htmlspecialchars($item['no_transaksi']) ?></small>
                                                        </td>
                                                        <td colspan="3"></td>
                                                        <td></td>
                                                        <td class="text-center">
                                                            <span class="status-badge <?= $status_class ?>">
                                                                <?= ucfirst($item['status_pembayaran']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>

                                                <!-- Baris detail bahan -->
                                                <?php
                                                // Tambahkan ke total transaksi
                                                $current_transaksi_total += $hitung_subtotal;
                                                $total_per_transaksi += $hitung_subtotal;
                                                ?>
                                                <tr class="bahan-row">
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($item['nama_bahan']) ?></div>
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
                                                                DB: <?= formatRupiah($item['subtotal']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Tampilkan total untuk transaksi terakhir -->
                                            <?php if ($current_pembelian_id !== null): ?>
                                                <tr class="table-secondary">
                                                    <td colspan="7" class="text-end"><strong>Total Transaksi:</strong></td>
                                                    <td class="text-end text-money">
                                                        <strong><?= formatRupiah($current_transaksi_total) ?></strong>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <th colspan="7" class="text-end">GRAND TOTAL:</th>
                                            <th class="text-end text-primary">
                                                <?= formatRupiah($summary['total_nilai']) ?>
                                            </th>
                                            <th></th>
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
                                    • Grand Total = Jumlah semua subtotal dari seluruh data
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
        // Inisialisasi DataTables
        $(document).ready(function() {
            $('#dataTable').DataTable({
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data per halaman",
                    "zeroRecords": "Data tidak ditemukan",
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
        });

        // Cetak laporan
        function printReport() {
            window.print();
        }

        // Export data ke Excel
        function exportToExcel() {
            Swal.fire({
                title: 'Export ke Excel',
                text: 'Sedang menyiapkan data...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();

                    setTimeout(() => {
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

                        // Data
                        <?php
                        $export_number = 0;
                        $current_export_id = null;
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

                        // Download
                        const encodedUri = encodeURI(csvContent);
                        const link = document.createElement("a");
                        link.setAttribute("href", encodedUri);
                        link.setAttribute("download", `laporan_pembelian_bahan_${new Date().toISOString().split('T')[0]}.csv`);
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        Swal.close();
                    }, 1000);
                }
            });
        }

        // Reset filter tanggal saat halaman dimuat ulang
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('tanggal_awal') && !urlParams.has('tanggal_akhir')) {
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

                document.querySelector('input[name="tanggal_awal"]').value = firstDay.toISOString().split('T')[0];
                document.querySelector('input[name="tanggal_akhir"]').value = lastDay.toISOString().split('T')[0];
            }
        });
    </script>
</body>

</html>