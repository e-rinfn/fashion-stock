<?php
// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

require_once '../includes/header.php';
require_once '../../config/functions.php';

// Ambil semua reseller untuk dropdown
$resellers = query("SELECT * FROM reseller ORDER BY nama_reseller");

// Ambil semua produk untuk filter multiple
$produk_list = query("SELECT * FROM produk ORDER BY nama_produk");

// Cek filter yang diterima
$search = isset($_GET['search']) ? $_GET['search'] : '';
$id_reseller = isset($_GET['id_reseller']) ? intval($_GET['id_reseller']) : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Default tanggal: 1 sampai akhir bulan ini
$default_tanggal_awal = date('Y-m-01');
$default_tanggal_akhir = date('Y-m-t');

$filter_tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : $default_tanggal_awal;
$filter_tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : $default_tanggal_akhir;
$filter_produk = isset($_GET['produk']) ? $_GET['produk'] : [];

// Konversi filter_produk ke array jika string
if (!is_array($filter_produk) && !empty($filter_produk)) {
    $filter_produk = explode(',', $filter_produk);
} elseif (!is_array($filter_produk)) {
    $filter_produk = [];
}

// Konversi ke integer dan hapus nilai kosong
$filter_produk = array_map('intval', $filter_produk);
$filter_produk = array_filter($filter_produk, function ($value) {
    return $value > 0;
});

// Ambil bulan dan tahun dari filter tanggal
$selected_month = date('m', strtotime($filter_tanggal_awal));
$selected_year = date('Y', strtotime($filter_tanggal_awal));
$period_key = $selected_year . '-' . $selected_month;

// Inisialisasi array untuk menyimpan biaya produksi per periode
$production_costs = [];

// Jika form biaya produksi disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_costs'])) {
    // Simpan biaya produksi ke session
    $production_costs[$period_key] = [
        'bahan_baku' => floatval($_POST['bahan_baku']),
        'tenaga_kerja' => floatval($_POST['tenaga_kerja']),
        'biaya_produksi' => floatval($_POST['biaya_produksi']),
        'biaya_lainnya' => floatval($_POST['biaya_lainnya']),
        'total_produksi' => floatval($_POST['bahan_baku']) + floatval($_POST['tenaga_kerja']) +
            floatval($_POST['biaya_produksi']) + floatval($_POST['biaya_lainnya']),
        'catatan' => $_POST['catatan']
    ];

    $_SESSION['production_costs'] = $production_costs;

    // Simpan juga HPP per produk jika ada
    if (isset($_POST['hpp'])) {
        $hpp_values = [];
        foreach ($_POST['hpp'] as $produk_id => $hpp_value) {
            $hpp_values[$produk_id] = floatval($hpp_value);
        }
        $_SESSION['hpp_values'] = $hpp_values;
    }

    $_SESSION['success'] = "Biaya produksi periode " . date('F Y', strtotime($filter_tanggal_awal)) . " berhasil disimpan!";
} elseif (isset($_SESSION['production_costs'])) {
    $production_costs = $_SESSION['production_costs'];
}

// Ambil biaya produksi untuk periode yang dipilih
$current_costs = isset($production_costs[$period_key]) ? $production_costs[$period_key] : [
    'bahan_baku' => 0,
    'tenaga_kerja' => 0,
    'biaya_produksi' => 0,
    'biaya_lainnya' => 0,
    'total_produksi' => 0,
    'catatan' => ''
];

// Ambil HPP per produk dari session
$hpp_values = [];
if (isset($_SESSION['hpp_values'])) {
    $hpp_values = $_SESSION['hpp_values'];
}

// Query utama untuk mendapatkan detail penjualan produk
$query = "SELECT 
            pj.id_penjualan,
            pj.tanggal_penjualan,
            pj.total_harga,
            pj.status_pembayaran,
            pj.metode_pembayaran,
            r.nama_reseller,
            pr.id_produk,
            pr.nama_produk,
            dp.jumlah,
            dp.harga_satuan,
            dp.subtotal
          FROM detail_penjualan dp
          JOIN penjualan pj ON dp.id_penjualan = pj.id_penjualan
          JOIN reseller r ON pj.id_reseller = r.id_reseller
          JOIN produk pr ON dp.id_produk = pr.id_produk
          WHERE 1=1";

// Filter pencarian
if (!empty($search)) {
    $query .= " AND (pj.id_penjualan LIKE '%$search%' 
                OR r.nama_reseller LIKE '%$search%'
                OR pr.nama_produk LIKE '%$search%')";
}

// Filter reseller
if ($id_reseller > 0) {
    $query .= " AND pj.id_reseller = $id_reseller";
}

// Filter status
if ($status != 'all') {
    $query .= " AND pj.status_pembayaran = '$status'";
}

// Filter produk (multiple)
if (!empty($filter_produk)) {
    $produk_ids = implode(',', $filter_produk);
    $query .= " AND dp.id_produk IN ($produk_ids)";
}

// Filter tanggal
if (!empty($filter_tanggal_awal) && !empty($filter_tanggal_akhir)) {
    $query .= " AND DATE(pj.tanggal_penjualan) BETWEEN '$filter_tanggal_awal' AND '$filter_tanggal_akhir'";
} elseif (!empty($filter_tanggal_awal)) {
    $query .= " AND DATE(pj.tanggal_penjualan) >= '$filter_tanggal_awal'";
} elseif (!empty($filter_tanggal_akhir)) {
    $query .= " AND DATE(pj.tanggal_penjualan) <= '$filter_tanggal_akhir'";
}

$query .= " ORDER BY pj.tanggal_penjualan DESC, pj.id_penjualan DESC, pr.nama_produk";

$detail_penjualan = query($query);

// Hitung total produksi per produk untuk periode ini
$total_produksi_per_produk = [];
if (!empty($detail_penjualan)) {
    foreach ($detail_penjualan as $item) {
        $produk_id = $item['id_produk'];
        if (!isset($total_produksi_per_produk[$produk_id])) {
            $total_produksi_per_produk[$produk_id] = [
                'nama_produk' => $item['nama_produk'],
                'total_jumlah' => 0
            ];
        }
        $total_produksi_per_produk[$produk_id]['total_jumlah'] += $item['jumlah'];
    }
}

// Hitung summary total
$summary_manual = [
    'total_transaksi' => 0,
    'total_item' => 0,
    'total_jumlah' => 0,
    'total_penjualan' => 0,
    'total_hpp' => 0,
    'total_laba_kotor' => 0,
    'total_biaya_produksi' => $current_costs['total_produksi'],
    'laba_bersih' => 0
];

$transaksi_ids = [];
$summary_per_produk = [];
$produk_data = [];

if (!empty($detail_penjualan)) {
    foreach ($detail_penjualan as $item) {
        // Hitung total transaksi unik
        if (!in_array($item['id_penjualan'], $transaksi_ids)) {
            $transaksi_ids[] = $item['id_penjualan'];
            $summary_manual['total_transaksi']++;
        }

        // Hitung total item
        $summary_manual['total_item']++;

        // Hitung total jumlah
        $summary_manual['total_jumlah'] += $item['jumlah'];

        // Hitung total penjualan
        $summary_manual['total_penjualan'] += $item['subtotal'];

        // Hitung HPP per produk
        $produk_id = $item['id_produk'];
        $hpp_per_unit = isset($hpp_values[$produk_id]) ? $hpp_values[$produk_id] : 0;
        $hpp_total = $hpp_per_unit * $item['jumlah'];

        // Hitung laba kotor
        $laba_kotor = $item['subtotal'] - $hpp_total;

        $summary_manual['total_hpp'] += $hpp_total;
        $summary_manual['total_laba_kotor'] += $laba_kotor;

        // Hitung summary per produk
        if (!isset($produk_data[$produk_id])) {
            $produk_data[$produk_id] = [
                'id_produk' => $produk_id,
                'nama_produk' => $item['nama_produk'],
                'total_jumlah' => 0,
                'total_penjualan' => 0,
                'total_hpp' => 0,
                'total_laba' => 0,
                'hpp_per_unit' => $hpp_per_unit,
                'persentase_hpp' => 0
            ];
        }

        $produk_data[$produk_id]['total_jumlah'] += $item['jumlah'];
        $produk_data[$produk_id]['total_penjualan'] += $item['subtotal'];
        $produk_data[$produk_id]['total_hpp'] += $hpp_total;
        $produk_data[$produk_id]['total_laba'] += $laba_kotor;
        $produk_data[$produk_id]['hpp_per_unit'] = $hpp_per_unit;
    }

    // Ubah ke array indexed
    $summary_per_produk = array_values($produk_data);

    // Hitung persentase HPP per produk berdasarkan total produksi
    $total_seluruh_hpp = array_sum(array_column($produk_data, 'total_hpp'));
    foreach ($summary_per_produk as &$produk) {
        if ($total_seluruh_hpp > 0) {
            $produk['persentase_hpp'] = ($produk['total_hpp'] / $total_seluruh_hpp) * 100;
        }
    }

    // Hitung alokasi biaya produksi per produk
    foreach ($summary_per_produk as &$produk) {
        $produk['alokasi_biaya'] = ($produk['persentase_hpp'] / 100) * $current_costs['total_produksi'];
        $produk['hpp_per_unit_final'] = ($produk['total_hpp'] + $produk['alokasi_biaya']) / $produk['total_jumlah'];
        $produk['laba_bersih'] = $produk['total_penjualan'] - ($produk['total_hpp'] + $produk['alokasi_biaya']);
    }

    // Hitung laba bersih (laba kotor - total biaya produksi)
    $summary_manual['laba_bersih'] = $summary_manual['total_laba_kotor'] - $current_costs['total_produksi'];
}
?>

<style>
    .filter-card {
        margin-bottom: 20px;
        border: 1px solid #e0e0e0;
    }

    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    .summary-produk-table {
        font-size: 13px;
    }

    .summary-produk-table th {
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

    .selected-produk-badge {
        display: inline-block;
        background-color: #e9ecef;
        border: 1px solid #ced4da;
        border-radius: 15px;
        padding: 2px 8px;
        margin: 2px;
        font-size: 12px;
    }

    .selected-produk-container {
        margin-top: 5px;
        min-height: 30px;
    }

    .filter-actions {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
    }

    .hpp-input {
        width: 120px;
        text-align: right;
    }

    .profit-positive {
        color: #198754;
        font-weight: bold;
    }

    .profit-negative {
        color: #dc3545;
        font-weight: bold;
    }

    .profit-neutral {
        color: #6c757d;
    }

    .card-summary-hpp {
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .card-summary-hpp.sales {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .card-summary-hpp.hpp {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }

    .card-summary-hpp.profit {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
    }

    .card-summary-hpp.transactions {
        background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        color: #333;
    }

    .card-summary-hpp.production {
        background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
        color: white;
    }

    .form-control-hpp {
        border: 1px solid #0d6efd;
        font-weight: 500;
    }

    .biaya-input-group {
        margin-bottom: 15px;
    }

    .biaya-input-group label {
        font-weight: 500;
        margin-bottom: 5px;
        color: #495057;
    }

    .period-info {
        background-color: #e7f3ff;
        border-left: 4px solid #0d6efd;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .cost-breakdown {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .cost-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e0e0e0;
    }

    .cost-item:last-child {
        border-bottom: none;
    }

    .cost-item.total {
        font-weight: bold;
        color: #0d6efd;
        font-size: 1.1em;
    }

    .tab-content {
        border: 1px solid #dee2e6;
        border-top: none;
        padding: 20px;
        border-radius: 0 0 5px 5px;
    }

    .nav-tabs .nav-link.active {
        background-color: #fff;
        border-color: #dee2e6 #dee2e6 #fff;
    }

    .allocation-table {
        font-size: 12px;
    }

    .allocation-table th {
        background-color: #f1f8ff;
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
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>PERHITUNGAN HPP (HARGA POKOK PENJUALAN)</h2>
                        <div class="period-info">
                            <h5 class="mb-1">
                                <i class="ti ti-calendar me-2"></i>
                                Periode: <?= date('F Y', strtotime($filter_tanggal_awal)) ?>
                            </h5>
                            <small class="text-muted">
                                <?= date('d F Y', strtotime($filter_tanggal_awal)) ?> - <?= date('d F Y', strtotime($filter_tanggal_akhir)) ?>
                            </small>
                        </div>
                    </div>

                    <!-- Card Summary HPP -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-2 col-sm-6">
                            <div class="card card-summary-hpp transactions">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= number_format($summary_manual['total_transaksi'], 0, ',', '.') ?>
                                    </h4>
                                    <small>Total Transaksi</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="card card-summary-hpp sales">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= formatRupiah($summary_manual['total_penjualan']) ?>
                                    </h4>
                                    <small>Total Penjualan</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="card card-summary-hpp hpp">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= formatRupiah($summary_manual['total_hpp']) ?>
                                    </h4>
                                    <small>Total HPP Awal</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="card card-summary-hpp production">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= formatRupiah($summary_manual['total_biaya_produksi']) ?>
                                    </h4>
                                    <small>Total Biaya Produksi</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="card card-summary-hpp profit">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1 <?= $summary_manual['laba_bersih'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                        <?= formatRupiah($summary_manual['laba_bersih']) ?>
                                    </h4>
                                    <small>Laba Bersih</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Filter -->
                    <div class="card filter-card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="ti ti-filter me-2"></i>Filter Data Penjualan</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="filterForm" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Reseller</label>
                                    <select class="form-control" name="id_reseller">
                                        <option value="">Semua Reseller</option>
                                        <?php foreach ($resellers as $r): ?>
                                            <option value="<?= $r['id_reseller'] ?>"
                                                <?= $id_reseller == $r['id_reseller'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($r['nama_reseller']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" name="status">
                                        <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>Semua Status</option>
                                        <option value="lunas" <?= $status == 'lunas' ? 'selected' : '' ?>>Lunas</option>
                                        <option value="cicilan" <?= $status == 'cicilan' ? 'selected' : '' ?>>Cicilan</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Produk</label>
                                    <div class="multiselect-dropdown">
                                        <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                            id="produkDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <?php if (empty($filter_produk)): ?>
                                                Pilih Produk...
                                            <?php else: ?>
                                                <?= count($filter_produk) ?> produk dipilih
                                            <?php endif; ?>
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="produkDropdown">
                                            <li>
                                                <div class="dropdown-item">
                                                    <input type="checkbox" id="selectAllProduk"
                                                        <?= count($filter_produk) == count($produk_list) ? 'checked' : '' ?>>
                                                    <label for="selectAllProduk" style="cursor: pointer; margin-bottom: 0;">
                                                        <strong>Pilih Semua</strong>
                                                    </label>
                                                </div>
                                            </li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <?php foreach ($produk_list as $pr): ?>
                                                <li>
                                                    <div class="dropdown-item">
                                                        <input type="checkbox" name="produk[]"
                                                            value="<?= $pr['id_produk'] ?>"
                                                            id="produk_<?= $pr['id_produk'] ?>"
                                                            <?= in_array($pr['id_produk'], $filter_produk) ? 'checked' : '' ?>>
                                                        <label for="produk_<?= $pr['id_produk'] ?>"
                                                            style="cursor: pointer; margin-bottom: 0;">
                                                            <?= htmlspecialchars($pr['nama_produk']) ?>
                                                        </label>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="selected-produk-container mt-2">
                                        <?php foreach ($filter_produk as $produk_id):
                                            $produk_nama = '';
                                            foreach ($produk_list as $pr) {
                                                if ($pr['id_produk'] == $produk_id) {
                                                    $produk_nama = $pr['nama_produk'];
                                                    break;
                                                }
                                            }
                                            if (!empty($produk_nama)): ?>
                                                <span class="selected-produk-badge" data-id="<?= $produk_id ?>">
                                                    <?= htmlspecialchars($produk_nama) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Periode Penjualan</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                        <input type="month" class="form-control" name="tanggal_awal"
                                            value="<?= date('Y-m', strtotime($filter_tanggal_awal)) ?>"
                                            onchange="updateDateRange(this.value)">
                                        <input type="hidden" name="tanggal_akhir" id="tanggal_akhir_hidden">
                                    </div>
                                    <small class="text-muted">Pilih bulan dan tahun untuk melihat periode tertentu</small>
                                </div>

                                <div class="col-12 filter-actions">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="hpp.php" class="btn btn-sm btn-secondary">
                                            <i class="ti ti-rotate me-1"></i> Reset Filter
                                        </a>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="ti ti-filter me-1"></i> Terapkan Filter
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Form Input Biaya Produksi -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="ti ti-calculator me-2"></i>Input Biaya Produksi Periode <?= date('F Y', strtotime($filter_tanggal_awal)) ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="costForm">
                                <input type="hidden" name="save_costs" value="1">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="cost-breakdown">
                                            <h6 class="mb-3"><i class="ti ti-cash me-2"></i>Detail Biaya Produksi</h6>

                                            <div class="biaya-input-group">
                                                <label for="bahan_baku">
                                                    <i class="ti ti-package me-1"></i> Biaya Bahan Baku
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number"
                                                        id="bahan_baku"
                                                        name="bahan_baku"
                                                        class="form-control form-control-hpp"
                                                        value="<?= number_format($current_costs['bahan_baku'], 0, '', '') ?>"
                                                        min="0"
                                                        step="1000"
                                                        required>
                                                </div>
                                                <small class="text-muted">Total biaya bahan baku yang digunakan</small>
                                            </div>

                                            <div class="biaya-input-group">
                                                <label for="tenaga_kerja">
                                                    <i class="ti ti-users me-1"></i> Biaya Tenaga Kerja
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number"
                                                        id="tenaga_kerja"
                                                        name="tenaga_kerja"
                                                        class="form-control form-control-hpp"
                                                        value="<?= number_format($current_costs['tenaga_kerja'], 0, '', '') ?>"
                                                        min="0"
                                                        step="1000"
                                                        required>
                                                </div>
                                                <small class="text-muted">Gaji dan upah pekerja produksi</small>
                                            </div>

                                            <div class="biaya-input-group">
                                                <label for="biaya_produksi">
                                                    <i class="ti ti-tools me-1"></i> Biaya Overhead Produksi
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number"
                                                        id="biaya_produksi"
                                                        name="biaya_produksi"
                                                        class="form-control form-control-hpp"
                                                        value="<?= number_format($current_costs['biaya_produksi'], 0, '', '') ?>"
                                                        min="0"
                                                        step="1000"
                                                        required>
                                                </div>
                                                <small class="text-muted">Listrik, air, maintenance mesin, dll</small>
                                            </div>

                                            <div class="biaya-input-group">
                                                <label for="biaya_lainnya">
                                                    <i class="ti ti-receipt me-1"></i> Biaya Lainnya
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number"
                                                        id="biaya_lainnya"
                                                        name="biaya_lainnya"
                                                        class="form-control form-control-hpp"
                                                        value="<?= number_format($current_costs['biaya_lainnya'], 0, '', '') ?>"
                                                        min="0"
                                                        step="1000">
                                                </div>
                                                <small class="text-muted">Biaya administrasi, transportasi, dll</small>
                                            </div>

                                            <div class="biaya-input-group">
                                                <label for="catatan">
                                                    <i class="ti ti-notes me-1"></i> Catatan
                                                </label>
                                                <textarea id="catatan"
                                                    name="catatan"
                                                    class="form-control"
                                                    rows="3"
                                                    placeholder="Catatan tambahan..."><?= htmlspecialchars($current_costs['catatan']) ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="cost-breakdown">
                                            <h6 class="mb-3"><i class="ti ti-sum me-2"></i>Ringkasan Biaya</h6>

                                            <div class="cost-item">
                                                <span>Biaya Bahan Baku:</span>
                                                <span id="summary_bahan_baku"><?= formatRupiah($current_costs['bahan_baku']) ?></span>
                                            </div>
                                            <div class="cost-item">
                                                <span>Biaya Tenaga Kerja:</span>
                                                <span id="summary_tenaga_kerja"><?= formatRupiah($current_costs['tenaga_kerja']) ?></span>
                                            </div>
                                            <div class="cost-item">
                                                <span>Biaya Overhead Produksi:</span>
                                                <span id="summary_biaya_produksi"><?= formatRupiah($current_costs['biaya_produksi']) ?></span>
                                            </div>
                                            <div class="cost-item">
                                                <span>Biaya Lainnya:</span>
                                                <span id="summary_biaya_lainnya"><?= formatRupiah($current_costs['biaya_lainnya']) ?></span>
                                            </div>
                                            <div class="cost-item total">
                                                <span>TOTAL BIAYA PRODUKSI:</span>
                                                <span id="summary_total"><?= formatRupiah($current_costs['total_produksi']) ?></span>
                                            </div>

                                            <hr>

                                            <div class="mt-4">
                                                <h6><i class="ti ti-info-circle me-2"></i>Rumus Perhitungan</h6>
                                                <small class="text-muted">
                                                    <strong>HPP Final per Unit =</strong><br>
                                                    (HPP Awal + Alokasi Biaya Produksi) ÷ Total Jumlah Produksi<br><br>

                                                    <strong>Alokasi Biaya Produksi =</strong><br>
                                                    (HPP Awal Produk ÷ Total HPP Semua Produk) × Total Biaya Produksi<br><br>

                                                    <strong>Laba Bersih =</strong><br>
                                                    Total Penjualan - (Total HPP + Total Biaya Produksi)
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div>
                                        <span class="text-muted">
                                            <i class="ti ti-alert-circle me-1"></i>
                                            Simpan biaya produksi untuk menghitung HPP final dan laba bersih
                                        </span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="reset" class="btn btn-secondary">
                                            <i class="ti ti-rotate me-1"></i> Reset
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="ti ti-device-floppy me-1"></i> Simpan Biaya Produksi
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabs untuk HPP dan Alokasi -->
                    <?php if (!empty($summary_per_produk)): ?>
                        <ul class="nav nav-tabs mb-3" id="hppTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="hpp-tab" data-bs-toggle="tab" data-bs-target="#hpp-tab-pane" type="button" role="tab">
                                    <i class="ti ti-calculator me-1"></i> Input HPP per Produk
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="allocation-tab" data-bs-toggle="tab" data-bs-target="#allocation-tab-pane" type="button" role="tab">
                                    <i class="ti ti-chart-pie me-1"></i> Alokasi Biaya Produksi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="detail-tab" data-bs-toggle="tab" data-bs-target="#detail-tab-pane" type="button" role="tab">
                                    <i class="ti ti-table me-1"></i> Detail Perhitungan
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="hppTabsContent">
                            <!-- Tab 1: Input HPP -->
                            <div class="tab-pane fade show active" id="hpp-tab-pane" role="tabpanel">
                                <form method="POST" id="hppForm">
                                    <input type="hidden" name="save_costs" value="1">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="5%">No</th>
                                                    <th width="25%">Nama Produk</th>
                                                    <th width="10%" class="text-end">Jumlah</th>
                                                    <th width="15%" class="text-end">Total Penjualan</th>
                                                    <th width="15%" class="text-center">HPP Awal per Unit</th>
                                                    <th width="15%" class="text-end">Total HPP Awal</th>
                                                    <th width="15%" class="text-end">Laba Kotor</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($summary_per_produk as $index => $produk): ?>
                                                    <?php
                                                    $laba_kotor = $produk['total_laba'];
                                                    $profit_class = $laba_kotor >= 0 ? 'profit-positive' : ($laba_kotor < 0 ? 'profit-negative' : 'profit-neutral');
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($produk['nama_produk']) ?></td>
                                                        <td class="text-end"><?= number_format($produk['total_jumlah'], 0, ',', '.') ?></td>
                                                        <td class="text-end"><?= formatRupiah($produk['total_penjualan']) ?></td>
                                                        <td class="text-center">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">Rp</span>
                                                                <input type="number"
                                                                    name="hpp[<?= $produk['id_produk'] ?>]"
                                                                    class="form-control form-control-hpp hpp-input"
                                                                    value="<?= number_format($produk['hpp_per_unit'], 0, '', '') ?>"
                                                                    min="0"
                                                                    step="100">
                                                            </div>
                                                        </td>
                                                        <td class="text-end"><?= formatRupiah($produk['total_hpp']) ?></td>
                                                        <td class="text-end <?= $profit_class ?>">
                                                            <?= formatRupiah($laba_kotor) ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <th colspan="3" class="text-end">TOTAL:</th>
                                                    <th class="text-end"><?= formatRupiah($summary_manual['total_penjualan']) ?></th>
                                                    <th class="text-end">-</th>
                                                    <th class="text-end"><?= formatRupiah($summary_manual['total_hpp']) ?></th>
                                                    <th class="text-end <?= $summary_manual['total_laba_kotor'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                        <?= formatRupiah($summary_manual['total_laba_kotor']) ?>
                                                    </th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <small class="text-muted">
                                                <i class="ti ti-info-circle me-1"></i>
                                                HPP Awal adalah biaya langsung per unit produk sebelum dialokasikan biaya produksi
                                            </small>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-success">
                                                <i class="ti ti-device-floppy me-1"></i> Simpan HPP & Hitung Ulang
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Tab 2: Alokasi Biaya -->
                            <div class="tab-pane fade" id="allocation-tab-pane" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover allocation-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="5%">No</th>
                                                <th width="20%">Nama Produk</th>
                                                <th width="10%" class="text-end">Jumlah</th>
                                                <th width="15%" class="text-end">HPP Awal</th>
                                                <th width="10%" class="text-end">% HPP</th>
                                                <th width="15%" class="text-end">Alokasi Biaya</th>
                                                <th width="15%" class="text-end">HPP Final/Unit</th>
                                                <th width="10%" class="text-end">Laba Bersih</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($summary_per_produk as $index => $produk): ?>
                                                <?php
                                                $laba_bersih = $produk['laba_bersih'];
                                                $profit_class = $laba_bersih >= 0 ? 'profit-positive' : ($laba_bersih < 0 ? 'profit-negative' : 'profit-neutral');
                                                ?>
                                                <tr>
                                                    <td class="text-center"><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($produk['nama_produk']) ?></td>
                                                    <td class="text-end"><?= number_format($produk['total_jumlah'], 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= formatRupiah($produk['total_hpp']) ?></td>
                                                    <td class="text-end"><?= number_format($produk['persentase_hpp'], 2, ',', '.') ?>%</td>
                                                    <td class="text-end"><?= formatRupiah($produk['alokasi_biaya']) ?></td>
                                                    <td class="text-end"><?= formatRupiah($produk['hpp_per_unit_final']) ?></td>
                                                    <td class="text-end <?= $profit_class ?>">
                                                        <?= formatRupiah($laba_bersih) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="3" class="text-end">TOTAL:</th>
                                                <th class="text-end"><?= formatRupiah($summary_manual['total_hpp']) ?></th>
                                                <th class="text-end">100%</th>
                                                <th class="text-end"><?= formatRupiah($current_costs['total_produksi']) ?></th>
                                                <th class="text-end">-</th>
                                                <th class="text-end <?= $summary_manual['laba_bersih'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                    <?= formatRupiah($summary_manual['laba_bersih']) ?>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="alert alert-info mt-3">
                                    <h6><i class="ti ti-info-circle me-2"></i>Cara Perhitungan Alokasi:</h6>
                                    <ol class="mb-0">
                                        <li><strong>HPP Awal:</strong> Biaya langsung per produk (input manual)</li>
                                        <li><strong>% HPP:</strong> (HPP Awal Produk ÷ Total HPP Semua Produk) × 100%</li>
                                        <li><strong>Alokasi Biaya:</strong> % HPP × Total Biaya Produksi</li>
                                        <li><strong>HPP Final per Unit:</strong> (HPP Awal + Alokasi Biaya) ÷ Total Jumlah Produk</li>
                                        <li><strong>Laba Bersih:</strong> Total Penjualan - (HPP Awal + Alokasi Biaya)</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Tab 3: Detail Perhitungan -->
                            <div class="tab-pane fade" id="detail-tab-pane" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header">
                                                <h6 class="mb-0"><i class="ti ti-cash me-2"></i>Ringkasan Pendapatan & Biaya</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="cost-item">
                                                    <span>Total Pendapatan Penjualan:</span>
                                                    <span class="fw-bold"><?= formatRupiah($summary_manual['total_penjualan']) ?></span>
                                                </div>
                                                <div class="cost-item">
                                                    <span>Total HPP Awal:</span>
                                                    <span class="fw-bold"><?= formatRupiah($summary_manual['total_hpp']) ?></span>
                                                </div>
                                                <hr>
                                                <div class="cost-item">
                                                    <span>Laba Kotor:</span>
                                                    <span class="fw-bold <?= $summary_manual['total_laba_kotor'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                        <?= formatRupiah($summary_manual['total_laba_kotor']) ?>
                                                    </span>
                                                </div>
                                                <div class="cost-item">
                                                    <span>Total Biaya Produksi:</span>
                                                    <span class="fw-bold"><?= formatRupiah($summary_manual['total_biaya_produksi']) ?></span>
                                                </div>
                                                <hr>
                                                <div class="cost-item total">
                                                    <span>LABA BERSIH:</span>
                                                    <span class="<?= $summary_manual['laba_bersih'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                        <?= formatRupiah($summary_manual['laba_bersih']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><i class="ti ti-chart-bar me-2"></i>Analisis Profitabilitas</h6>
                                            </div>
                                            <div class="card-body">
                                                <?php
                                                $margin_laba_kotor = $summary_manual['total_penjualan'] > 0 ?
                                                    ($summary_manual['total_laba_kotor'] / $summary_manual['total_penjualan']) * 100 : 0;

                                                $margin_laba_bersih = $summary_manual['total_penjualan'] > 0 ?
                                                    ($summary_manual['laba_bersih'] / $summary_manual['total_penjualan']) * 100 : 0;

                                                $rasio_hpp = $summary_manual['total_penjualan'] > 0 ?
                                                    ($summary_manual['total_hpp'] / $summary_manual['total_penjualan']) * 100 : 0;

                                                $rasio_biaya_produksi = $summary_manual['total_penjualan'] > 0 ?
                                                    ($summary_manual['total_biaya_produksi'] / $summary_manual['total_penjualan']) * 100 : 0;
                                                ?>

                                                <div class="mb-3">
                                                    <label>Margin Laba Kotor:</label>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-success" role="progressbar"
                                                            style="width: <?= min($margin_laba_kotor, 100) ?>%">
                                                            <?= number_format($margin_laba_kotor, 2) ?>%
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label>Margin Laba Bersih:</label>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar <?= $margin_laba_bersih >= 0 ? 'bg-primary' : 'bg-danger' ?>"
                                                            role="progressbar"
                                                            style="width: <?= min(abs($margin_laba_bersih), 100) ?>%">
                                                            <?= number_format($margin_laba_bersih, 2) ?>%
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label>Rasio HPP terhadap Penjualan:</label>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-warning" role="progressbar"
                                                            style="width: <?= min($rasio_hpp, 100) ?>%">
                                                            <?= number_format($rasio_hpp, 2) ?>%
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label>Rasio Biaya Produksi terhadap Penjualan:</label>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-info" role="progressbar"
                                                            style="width: <?= min($rasio_biaya_produksi, 100) ?>%">
                                                            <?= number_format($rasio_biaya_produksi, 2) ?>%
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tombol Export -->
                    <?php if (!empty($detail_penjualan)): ?>
                        <div class="card mt-3">
                            <div class="card-body text-center">
                                <button type="button" class="btn btn-primary me-2" onclick="printHPPReport()">
                                    <i class="ti ti-printer me-1"></i> Cetak Laporan HPP
                                </button>
                                <button type="button" class="btn btn-success me-2" onclick="exportHPPToExcel()">
                                    <i class="ti ti-download me-1"></i> Export ke Excel
                                </button>
                                <button type="button" class="btn btn-info" onclick="clearAllData()">
                                    <i class="ti ti-trash me-1"></i> Hapus Data Sementara
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once '../includes/footer.php'; ?>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set tanggal akhir berdasarkan bulan yang dipilih
            function updateDateRange(monthValue) {
                const [year, month] = monthValue.split('-');
                const lastDay = new Date(year, month, 0).getDate();
                document.getElementById('tanggal_akhir_hidden').value = `${year}-${month}-${lastDay.toString().padStart(2, '0')}`;
            }

            // Inisialisasi tanggal
            const monthInput = $('input[name="tanggal_awal"]');
            if (monthInput.val()) {
                updateDateRange(monthInput.val());
            }

            // Handle dropdown tidak menutup saat klik checkbox
            $(document).on('click', '.multiselect-dropdown .dropdown-item', function(e) {
                e.stopPropagation();
            });

            // Handle select all checkbox
            $('#selectAllProduk').change(function() {
                const isChecked = $(this).is(':checked');
                $('.multiselect-dropdown input[name="produk[]"]').prop('checked', isChecked);
                updateSelectedProduk();
            });

            // Update selected produk ketika checkbox berubah
            $('.multiselect-dropdown input[name="produk[]"]').change(function() {
                updateSelectedProduk();
                updateSelectAllCheckbox();
            });

            // Update tampilan produk yang dipilih saat pertama kali load
            updateSelectedProduk();

            // Auto-calculate total biaya produksi
            $('#bahan_baku, #tenaga_kerja, #biaya_produksi, #biaya_lainnya').on('input', function() {
                calculateTotalCost();
            });

            // Auto-calculate laba saat input HPP berubah
            $('.hpp-input').on('input', function() {
                calculateProfit();
            });

            // Format input biaya dengan titik pemisah ribuan
            $('.form-control-hpp').on('blur', function() {
                const value = $(this).val();
                if (value) {
                    $(this).val(formatNumber(value));
                }
            }).on('focus', function() {
                const value = $(this).val().replace(/\./g, '');
                $(this).val(value);
            });
        });

        // Fungsi untuk update tampilan produk yang dipilih
        function updateSelectedProduk() {
            const selectedProduk = [];
            const selectedNames = [];

            $('.multiselect-dropdown input[name="produk[]"]:checked').each(function() {
                const id = $(this).val();
                const name = $(this).next('label').text().trim();
                selectedProduk.push({
                    id: id,
                    name: name
                });
                selectedNames.push(name);
            });

            const dropdownBtn = $('#produkDropdown');
            if (selectedProduk.length === 0) {
                dropdownBtn.text('Pilih Produk...');
            } else {
                dropdownBtn.text(selectedProduk.length + ' produk dipilih');
            }

            const badgeContainer = $('.selected-produk-container');
            badgeContainer.empty();

            selectedProduk.forEach(function(produk) {
                const badge = $(`
                <span class="selected-produk-badge" data-id="${produk.id}">
                    ${produk.name}
                </span>
            `);
                badgeContainer.append(badge);
            });
        }

        function updateSelectAllCheckbox() {
            const totalCheckboxes = $('.multiselect-dropdown input[name="produk[]"]').length;
            const checkedCheckboxes = $('.multiselect-dropdown input[name="produk[]"]:checked').length;

            $('#selectAllProduk').prop('checked', totalCheckboxes === checkedCheckboxes);
            $('#selectAllProduk').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        }

        // Fungsi untuk menghitung total biaya produksi
        function calculateTotalCost() {
            const bahan_baku = parseFloat($('#bahan_baku').val().replace(/\./g, '')) || 0;
            const tenaga_kerja = parseFloat($('#tenaga_kerja').val().replace(/\./g, '')) || 0;
            const biaya_produksi = parseFloat($('#biaya_produksi').val().replace(/\./g, '')) || 0;
            const biaya_lainnya = parseFloat($('#biaya_lainnya').val().replace(/\./g, '')) || 0;

            const total = bahan_baku + tenaga_kerja + biaya_produksi + biaya_lainnya;

            // Update summary
            $('#summary_bahan_baku').text(formatRupiah(bahan_baku));
            $('#summary_tenaga_kerja').text(formatRupiah(tenaga_kerja));
            $('#summary_biaya_produksi').text(formatRupiah(biaya_produksi));
            $('#summary_biaya_lainnya').text(formatRupiah(biaya_lainnya));
            $('#summary_total').text(formatRupiah(total));
        }

        // Format angka dengan pemisah ribuan
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Format Rupiah
        function formatRupiah(angka) {
            if (!angka) return 'Rp 0';
            const number_string = angka.toString().replace(/[^,\d]/g, '').toString();
            const split = number_string.split(',');
            const sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            const ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                const separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return 'Rp ' + rupiah;
        }

        // Cetak Laporan HPP
        function printHPPReport() {
            window.print();
        }

        // Export ke Excel
        function exportHPPToExcel() {
            const hasData = <?= !empty($detail_penjualan) ? 'true' : 'false' ?>;

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

                            // Header Laporan HPP
                            csvContent += "LAPORAN HPP PERIODE <?= date('F Y', strtotime($filter_tanggal_awal)) ?>\r\n";
                            csvContent += "Tanggal Export: <?= date('d/m/Y H:i:s') ?>\r\n\r\n";

                            // Bagian 1: Biaya Produksi
                            csvContent += "BIAYA PRODUKSI\r\n";
                            csvContent += "BIAYA PRODUKSI\r\n";
                            csvContent += "Kategori,Jumlah\r\n";
                            csvContent += "Biaya Bahan Baku,<?= $current_costs['bahan_baku'] ?>\r\n";
                            csvContent += "Biaya Tenaga Kerja,<?= $current_costs['tenaga_kerja'] ?>\r\n";
                            csvContent += "Biaya Overhead Produksi,<?= $current_costs['biaya_produksi'] ?>\r\n";
                            csvContent += "Biaya Lainnya,<?= $current_costs['biaya_lainnya'] ?>\r\n";
                            csvContent += "TOTAL BIAYA PRODUKSI,<?= $current_costs['total_produksi'] ?>\r\n\r\n";

                            // Bagian 2: Ringkasan Keuangan
                            csvContent += "RINGKASAN KEUANGAN\r\n";
                            csvContent += "Item,Jumlah\r\n";
                            csvContent += "Total Penjualan,<?= $summary_manual['total_penjualan'] ?>\r\n";
                            csvContent += "Total HPP Awal,<?= $summary_manual['total_hpp'] ?>\r\n";
                            csvContent += "Laba Kotor,<?= $summary_manual['total_laba_kotor'] ?>\r\n";
                            csvContent += "Total Biaya Produksi,<?= $summary_manual['total_biaya_produksi'] ?>\r\n";
                            csvContent += "LABA BERSIH,<?= $summary_manual['laba_bersih'] ?>\r\n\r\n";

                            // Bagian 3: Detail HPP per Produk
                            csvContent += "DETAIL HPP PER PRODUK\r\n";
                            csvContent += "No,Nama Produk,Jumlah Terjual,Total Penjualan,HPP per Unit,Total HPP Awal,% HPP,Alokasi Biaya,HPP Final per Unit,Laba Bersih\r\n";

                            <?php if (!empty($summary_per_produk)): ?>
                                <?php foreach ($summary_per_produk as $index => $produk): ?>
                                    csvContent += "<?= $index + 1 ?>,<?= addslashes($produk['nama_produk']) ?>,<?= $produk['total_jumlah'] ?>,<?= $produk['total_penjualan'] ?>,<?= $produk['hpp_per_unit'] ?>,<?= $produk['total_hpp'] ?>,<?= number_format($produk['persentase_hpp'], 2) ?>%,<?= $produk['alokasi_biaya'] ?>,<?= number_format($produk['hpp_per_unit_final'], 2) ?>,<?= $produk['laba_bersih'] ?>\r\n";
                                <?php endforeach; ?>
                            <?php endif; ?>

                            csvContent += "\r\nTOTAL, ,<?= $summary_manual['total_jumlah'] ?>,<?= $summary_manual['total_penjualan'] ?>, ,<?= $summary_manual['total_hpp'] ?>,100%,<?= $current_costs['total_produksi'] ?>, ,<?= $summary_manual['laba_bersih'] ?>\r\n\r\n";

                            // Bagian 4: Analisis Profitabilitas
                            <?php
                            $margin_laba_kotor = $summary_manual['total_penjualan'] > 0 ?
                                ($summary_manual['total_laba_kotor'] / $summary_manual['total_penjualan']) * 100 : 0;
                            $margin_laba_bersih = $summary_manual['total_penjualan'] > 0 ?
                                ($summary_manual['laba_bersih'] / $summary_manual['total_penjualan']) * 100 : 0;
                            ?>
                            csvContent += "ANALISIS PROFITABILITAS\r\n";
                            csvContent += "Rasio,Nilai\r\n";
                            csvContent += "Margin Laba Kotor,<?= number_format($margin_laba_kotor, 2) ?>%\r\n";
                            csvContent += "Margin Laba Bersih,<?= number_format($margin_laba_bersih, 2) ?>%\r\n";
                            csvContent += "Rasio HPP terhadap Penjualan,<?= number_format(($summary_manual['total_hpp'] / $summary_manual['total_penjualan']) * 100, 2) ?>%\r\n";
                            csvContent += "Rasio Biaya Produksi terhadap Penjualan,<?= number_format(($summary_manual['total_biaya_produksi'] / $summary_manual['total_penjualan']) * 100, 2) ?>%\r\n\r\n";

                            // Bagian 5: Catatan
                            csvContent += "CATATAN\r\n";
                            csvContent += "<?= addslashes($current_costs['catatan']) ?>\r\n";

                            // Download
                            const encodedUri = encodeURI(csvContent);
                            const link = document.createElement("a");
                            link.setAttribute("href", encodedUri);
                            link.setAttribute("download", `laporan_hpp_<?= date('Y_m', strtotime($filter_tanggal_awal)) ?>.csv`);
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

        // Hapus data sementara
        function clearAllData() {
            Swal.fire({
                title: 'Hapus Data Sementara?',
                html: `Apakah Anda yakin ingin menghapus semua data HPP dan biaya produksi yang disimpan?<br>
                   <small class="text-danger">Data yang dihapus tidak dapat dikembalikan!</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Kirim request untuk hapus session data
                    $.ajax({
                        url: 'clear_hpp_data.php',
                        type: 'POST',
                        success: function(response) {
                            Swal.fire({
                                title: 'Berhasil!',
                                text: 'Data HPP sementara telah dihapus',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload();
                            });
                        },
                        error: function() {
                            Swal.fire({
                                title: 'Error!',
                                text: 'Terjadi kesalahan saat menghapus data',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
        }

        // Fungsi untuk menghitung laba secara real-time
        function calculateProfit() {
            let totalPenjualan = 0;
            let totalHPP = 0;

            // Loop melalui setiap baris produk
            $('table tbody tr').each(function() {
                const row = $(this);
                if (row.find('.hpp-input').length > 0) {
                    const jumlah = parseFloat(row.find('td:nth-child(3)').text().replace(/\./g, '')) || 0;
                    const penjualanText = row.find('td:nth-child(4)').text().replace(/[^\d]/g, '');
                    const penjualan = parseFloat(penjualanText) || 0;
                    const hppInput = row.find('.hpp-input').val();
                    const hppPerUnit = parseFloat(hppInput) || 0;
                    const hppTotal = hppPerUnit * jumlah;
                    const laba = penjualan - hppTotal;

                    // Update laba di kolom terakhir
                    row.find('td:nth-child(7)').removeClass('profit-positive profit-negative profit-neutral');
                    row.find('td:nth-child(7)').addClass(laba >= 0 ? 'profit-positive' : (laba < 0 ? 'profit-negative' : 'profit-neutral'));
                    row.find('td:nth-child(7)').text(formatRupiah(laba));

                    // Update total HPP
                    row.find('td:nth-child(6)').text(formatRupiah(hppTotal));

                    totalPenjualan += penjualan;
                    totalHPP += hppTotal;
                }
            });

            // Update total di footer
            const totalLaba = totalPenjualan - totalHPP;
            const totalBiaya = parseFloat($('#summary_total').text().replace(/[^\d]/g, '')) || 0;
            const labaBersih = totalLaba - totalBiaya;

            $('tfoot tr th:nth-child(4)').text(formatRupiah(totalPenjualan));
            $('tfoot tr th:nth-child(6)').text(formatRupiah(totalHPP));
            $('tfoot tr th:nth-child(7)').removeClass('profit-positive profit-negative profit-neutral');
            $('tfoot tr th:nth-child(7)').addClass(totalLaba >= 0 ? 'profit-positive' : 'profit-negative');
            $('tfoot tr th:nth-child(7)').text(formatRupiah(totalLaba));

            // Update laba bersih di summary card
            const labaBersihElement = $('.card-summary-hpp.profit h4');
            labaBersihElement.removeClass('profit-positive profit-negative');
            labaBersihElement.addClass(labaBersih >= 0 ? 'profit-positive' : 'profit-negative');
            labaBersihElement.text(formatRupiah(labaBersih));
        }

        // Fungsi untuk mengupdate rentang tanggal
        function updateDateRange(monthValue) {
            const [year, month] = monthValue.split('-');
            const lastDay = new Date(year, month, 0).getDate();
            document.getElementById('tanggal_akhir_hidden').value = `${year}-${month}-${lastDay.toString().padStart(2, '0')}`;
        }
    </script>
</body> <!-- [Body] end -->

</html>