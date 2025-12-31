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

// Array untuk menyimpan HPP yang diinput pengguna
$hpp_values = [];

// Jika form HPP disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simpan nilai HPP ke session
    foreach ($_POST['hpp'] as $produk_id => $hpp_value) {
        $hpp_values[$produk_id] = floatval($hpp_value);
    }
    $_SESSION['hpp_values'] = $hpp_values;
} elseif (isset($_SESSION['hpp_values'])) {
    // Jika sudah ada di session, gunakan yang ada
    $hpp_values = $_SESSION['hpp_values'];
}

// Hitung summary total
$summary_manual = [
    'total_transaksi' => 0,
    'total_item' => 0,
    'total_jumlah' => 0,
    'total_penjualan' => 0,
    'total_hpp' => 0,
    'total_laba_kotor' => 0,
    'total_laba_bersih' => 0 // Laba kotor setelah HPP
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
                'hpp_per_unit' => $hpp_per_unit
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

    // Hitung laba bersih (laba kotor - total HPP)
    $summary_manual['total_laba_bersih'] = $summary_manual['total_laba_kotor'];
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

    .form-control-hpp {
        border: 1px solid #0d6efd;
        font-weight: 500;
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
                    </div>

                    <!-- Card Summary HPP -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3 col-sm-6">
                            <div class="card card-summary-hpp transactions">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= number_format($summary_manual['total_transaksi'], 0, ',', '.') ?>
                                    </h4>
                                    <small>Total Transaksi</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="card card-summary-hpp sales">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= formatRupiah($summary_manual['total_penjualan']) ?>
                                    </h4>
                                    <small>Total Penjualan</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="card card-summary-hpp hpp">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= formatRupiah($summary_manual['total_hpp']) ?>
                                    </h4>
                                    <small>Total HPP</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="card card-summary-hpp profit">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1 <?= $summary_manual['total_laba_kotor'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                        <?= formatRupiah($summary_manual['total_laba_kotor']) ?>
                                    </h4>
                                    <small>Laba Kotor</small>
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
                                    <label class="form-label">Tanggal Penjualan</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                        <input type="date" class="form-control" name="tanggal_awal"
                                            value="<?= htmlspecialchars($filter_tanggal_awal) ?>">
                                        <span class="input-group-text">s/d</span>
                                        <input type="date" class="form-control" name="tanggal_akhir"
                                            value="<?= htmlspecialchars($filter_tanggal_akhir) ?>">
                                    </div>
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

                    <!-- Form Input HPP -->
                    <?php if (!empty($summary_per_produk)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="ti ti-calculator me-2"></i>Input HPP per Produk</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="hppForm">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="5%">No</th>
                                                    <th width="30%">Nama Produk</th>
                                                    <th width="15%" class="text-end">Jumlah Terjual</th>
                                                    <th width="20%" class="text-end">Total Penjualan</th>
                                                    <th width="20%" class="text-center">HPP per Unit</th>
                                                    <th width="20%" class="text-end">Laba Kotor</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($summary_per_produk as $index => $produk): ?>
                                                    <?php
                                                    $hpp_per_unit = $produk['hpp_per_unit'];
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
                                                                    value="<?= number_format($hpp_per_unit, 0, '', '') ?>"
                                                                    min="0"
                                                                    step="100">
                                                            </div>
                                                        </td>
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
                                                    <th class="text-end"><?= formatRupiah($summary_manual['total_hpp']) ?></th>
                                                    <th class="text-end <?= $summary_manual['total_laba_kotor'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                        <?= formatRupiah($summary_manual['total_laba_kotor']) ?>
                                                    </th>
                                                </tr>
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <small class="text-muted">
                                                            <i class="ti ti-info-circle me-1"></i>
                                                            Laba Kotor = Total Penjualan - Total HPP
                                                        </small>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <span class="text-muted">
                                                <i class="ti ti-alert-circle me-1"></i>
                                                Masukkan HPP (Harga Pokok Penjualan) per unit untuk setiap produk
                                            </span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="reset" class="btn btn-secondary">
                                                <i class="ti ti-rotate me-1"></i> Reset HPP
                                            </button>
                                            <button type="submit" class="btn btn-success">
                                                <i class="ti ti-calculator me-1"></i> Hitung Ulang Laba
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Detail Transaksi -->
                    <?php if (!empty($detail_penjualan)): ?>
                        <div class="card">
                            <div class="card-header bg-light border-bottom">
                                <h5 class="mb-0 d-flex align-items-center gap-2">
                                    <i class="ti ti-table"></i>
                                    Detail Penjualan dengan Perhitungan HPP
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="120">Tanggal</th>
                                                <th>Reseller</th>
                                                <th>Produk</th>
                                                <th width="80" class="text-center">Jumlah</th>
                                                <th width="120" class="text-end">Harga Jual</th>
                                                <th width="120" class="text-end">HPP/Unit</th>
                                                <th width="120" class="text-end">Total HPP</th>
                                                <th width="120" class="text-end">Laba</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $current_penjualan_id = null;
                                            $row_number = 0;
                                            $current_transaksi_total = 0;
                                            $current_transaksi_hpp = 0;
                                            $current_transaksi_laba = 0;
                                            $grand_total_penjualan = 0;
                                            $grand_total_hpp = 0;
                                            $grand_total_laba = 0;
                                            ?>

                                            <?php foreach ($detail_penjualan as $item): ?>
                                                <?php
                                                $row_number++;

                                                // Hitung HPP per transaksi
                                                $hpp_per_unit = isset($hpp_values[$item['id_produk']]) ? $hpp_values[$item['id_produk']] : 0;
                                                $total_hpp = $hpp_per_unit * $item['jumlah'];
                                                $laba = $item['subtotal'] - $total_hpp;

                                                $grand_total_penjualan += $item['subtotal'];
                                                $grand_total_hpp += $total_hpp;
                                                $grand_total_laba += $laba;
                                                ?>

                                                <!-- Baris Header Transaksi -->
                                                <?php if ($current_penjualan_id != $item['id_penjualan']):
                                                    if ($current_penjualan_id !== null): ?>
                                                        <!-- Baris total transaksi -->
                                                        <tr class="table-secondary">
                                                            <td colspan="4" class="text-end fw-bold">Total Transaksi:</td>
                                                            <td class="text-end fw-bold"><?= formatRupiah($current_transaksi_hpp) ?></td>
                                                            <td class="text-end fw-bold <?= $current_transaksi_laba >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                                <?= formatRupiah($current_transaksi_laba) ?>
                                                            </td>
                                                            <td></td>
                                                            <td></td>
                                                        </tr>
                                                    <?php endif;

                                                    $current_penjualan_id = $item['id_penjualan'];
                                                    $current_transaksi_total = 0;
                                                    $current_transaksi_hpp = 0;
                                                    $current_transaksi_laba = 0;
                                                    ?>
                                                    <tr class="table-active">
                                                        <td>
                                                            <div class="fw-bold"><?= dateIndo($item['tanggal_penjualan']) ?></div>
                                                            <small class="text-muted">ID: <?= $item['id_penjualan'] ?></small>
                                                        </td>
                                                        <td colspan="3">
                                                            <div class="fw-bold"><?= htmlspecialchars($item['nama_reseller']) ?></div>
                                                        </td>
                                                        <td colspan="3"></td>
                                                        <td></td>
                                                    </tr>
                                                <?php endif; ?>

                                                <!-- Baris Detail Produk -->
                                                <?php
                                                $current_transaksi_total += $item['subtotal'];
                                                $current_transaksi_hpp += $total_hpp;
                                                $current_transaksi_laba += $laba;
                                                ?>
                                                <tr>
                                                    <td></td>
                                                    <td></td>
                                                    <td><?= htmlspecialchars($item['nama_produk']) ?></td>
                                                    <td class="text-center"><?= number_format($item['jumlah'], 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= formatRupiah($item['harga_satuan']) ?></td>
                                                    <td class="text-end"><?= formatRupiah($hpp_per_unit) ?></td>
                                                    <td class="text-end"><?= formatRupiah($total_hpp) ?></td>
                                                    <td class="text-end <?= $laba >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                        <?= formatRupiah($laba) ?>
                                                    </td>

                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Total Transaksi Terakhir -->
                                            <?php if ($current_penjualan_id !== null): ?>
                                                <tr class="table-secondary">
                                                    <td colspan="4" class="text-end fw-bold">Total Transaksi:</td>
                                                    <td class="text-end fw-bold"><?= formatRupiah($current_transaksi_hpp) ?></td>
                                                    <td class="text-end fw-bold <?= $current_transaksi_laba >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                        <?= formatRupiah($current_transaksi_laba) ?>
                                                    </td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot class="table-info">
                                            <tr>
                                                <th colspan="4" class="text-end">GRAND TOTAL:</th>
                                                <th class="text-end"><?= formatRupiah($grand_total_penjualan) ?></th>
                                                <th class="text-end"><?= formatRupiah($grand_total_hpp) ?></th>
                                                <th></th>
                                                <th class="text-end <?= $grand_total_laba >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                    <?= formatRupiah($grand_total_laba) ?>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
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
                                <button type="button" class="btn btn-success" onclick="exportHPPToExcel()">
                                    <i class="ti ti-download me-1"></i> Export ke Excel
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
            // Set tanggal default
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

            // Auto-calculate laba saat input HPP berubah
            $('.hpp-input').on('input', function() {
                calculateProfit();
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
                    row.find('td:nth-child(6)').removeClass('profit-positive profit-negative profit-neutral');
                    row.find('td:nth-child(6)').addClass(laba >= 0 ? 'profit-positive' : (laba < 0 ? 'profit-negative' : 'profit-neutral'));
                    row.find('td:nth-child(6)').text(formatRupiah(laba));

                    totalPenjualan += penjualan;
                    totalHPP += hppTotal;
                }
            });

            // Update total di footer
            const totalLaba = totalPenjualan - totalHPP;
            $('tfoot tr th:nth-child(4)').text(formatRupiah(totalPenjualan));
            $('tfoot tr th:nth-child(5)').text(formatRupiah(totalHPP));
            $('tfoot tr th:nth-child(6)').removeClass('profit-positive profit-negative profit-neutral');
            $('tfoot tr th:nth-child(6)').addClass(totalLaba >= 0 ? 'profit-positive' : 'profit-negative');
            $('tfoot tr th:nth-child(6)').text(formatRupiah(totalLaba));
        }

        // Format Rupiah di JavaScript
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
            const table = $('table').first();
            const hasData = table.find('tbody tr').length > 0;

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
                                'Nama Produk',
                                'Jumlah Terjual',
                                'Total Penjualan',
                                'HPP per Unit',
                                'Total HPP',
                                'Laba Kotor'
                            ];
                            csvContent += headers.join(",") + "\r\n";

                            // Data summary produk
                            <?php if (!empty($summary_per_produk)): ?>
                                <?php foreach ($summary_per_produk as $index => $produk): ?>
                                    const rowData = [
                                        "<?= $index + 1 ?>",
                                        "<?= addslashes($produk['nama_produk']) ?>",
                                        "<?= $produk['total_jumlah'] ?>",
                                        "<?= $produk['total_penjualan'] ?>",
                                        "<?= $produk['hpp_per_unit'] ?>",
                                        "<?= $produk['total_hpp'] ?>",
                                        "<?= $produk['total_laba'] ?>"
                                    ];
                                    csvContent += rowData.map(cell => `"${cell}"`).join(",") + "\r\n";
                                <?php endforeach; ?>
                            <?php endif; ?>

                            // Download
                            const encodedUri = encodeURI(csvContent);
                            const link = document.createElement("a");
                            link.setAttribute("href", encodedUri);
                            link.setAttribute("download", `laporan_hpp_penjualan_${new Date().toISOString().split('T')[0]}.csv`);
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
</body>
<!-- [Body] end -->

</html>