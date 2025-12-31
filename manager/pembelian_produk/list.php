<?php

// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

require_once '../includes/header.php';
require_once '../../config/functions.php';

// Ambil semua supplier untuk dropdown
$suppliers = query("SELECT * FROM supplier ORDER BY nama_supplier");

// Ambil semua produk untuk filter multiple
$produk_list = query("SELECT * FROM produk ORDER BY nama_produk");

// Cek filter yang diterima
$search = isset($_GET['search']) ? $_GET['search'] : '';
$id_supplier = isset($_GET['id_supplier']) ? intval($_GET['id_supplier']) : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : '';
$filter_tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';
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

// Query utama untuk mendapatkan detail pembelian produk
$query = "SELECT 
            p.id_pembelian,
            p.tanggal_pembelian,
            p.total_harga,
            p.status_pembayaran,
            p.metode_pembayaran,
            s.nama_supplier,
            pr.id_produk,
            pr.nama_produk,
            dp.jumlah,
            dp.harga_satuan,
            dp.subtotal
          FROM detail_pembelian dp
          JOIN pembelian p ON dp.id_pembelian = p.id_pembelian
          JOIN supplier s ON p.id_supplier = s.id_supplier
          JOIN produk pr ON dp.id_produk = pr.id_produk
          WHERE 1=1";

// Filter pencarian
if (!empty($search)) {
    $query .= " AND (p.id_pembelian LIKE '%$search%' 
                OR s.nama_supplier LIKE '%$search%'
                OR pr.nama_produk LIKE '%$search%')";
}

// Filter supplier
if ($id_supplier > 0) {
    $query .= " AND p.id_supplier = $id_supplier";
}

// Filter status
if ($status != 'all') {
    $query .= " AND p.status_pembayaran = '$status'";
}

// Filter produk (multiple)
if (!empty($filter_produk)) {
    $produk_ids = implode(',', $filter_produk);
    $query .= " AND dp.id_produk IN ($produk_ids)";
}

// Filter tanggal
if (!empty($filter_tanggal_awal) && !empty($filter_tanggal_akhir)) {
    $query .= " AND DATE(p.tanggal_pembelian) BETWEEN '$filter_tanggal_awal' AND '$filter_tanggal_akhir'";
} elseif (!empty($filter_tanggal_awal)) {
    $query .= " AND DATE(p.tanggal_pembelian) >= '$filter_tanggal_awal'";
} elseif (!empty($filter_tanggal_akhir)) {
    $query .= " AND DATE(p.tanggal_pembelian) <= '$filter_tanggal_akhir'";
}

$query .= " ORDER BY p.tanggal_pembelian DESC, p.id_pembelian DESC, pr.nama_produk";

$detail_pembelian = query($query);

// Hitung summary total secara manual dari $detail_pembelian
$summary_manual = [
    'total_transaksi' => 0,
    'total_item' => 0,
    'total_jumlah' => 0,
    'total_nilai' => 0
];

$transaksi_ids = [];
if (!empty($detail_pembelian)) {
    foreach ($detail_pembelian as $item) {
        // Hitung total transaksi unik
        if (!in_array($item['id_pembelian'], $transaksi_ids)) {
            $transaksi_ids[] = $item['id_pembelian'];
            $summary_manual['total_transaksi']++;
        }

        // Hitung total item
        $summary_manual['total_item']++;

        // Hitung total jumlah
        $summary_manual['total_jumlah'] += $item['jumlah'];

        // Hitung total nilai (subtotal)
        $summary_manual['total_nilai'] += $item['subtotal'];
    }
}

// Hitung summary per produk DARI DATA DETAIL YANG SUDAH DIFILTER
$summary_per_produk = [];
$produk_data = [];

if (!empty($detail_pembelian)) {
    foreach ($detail_pembelian as $item) {
        $produk_id = $item['id_produk'];

        if (!isset($produk_data[$produk_id])) {
            $produk_data[$produk_id] = [
                'id_produk' => $produk_id,
                'nama_produk' => $item['nama_produk'],
                'total_jumlah' => 0,
                'total_harga_satuan' => 0, // untuk menghitung rata-rata
                'total_subtotal' => 0
            ];
        }

        $produk_data[$produk_id]['total_jumlah'] += $item['jumlah'];
        $produk_data[$produk_id]['total_harga_satuan'] += $item['harga_satuan'];
        $produk_data[$produk_id]['total_subtotal'] += $item['subtotal'];
    }

    // Ubah ke array indexed
    $summary_per_produk = array_values($produk_data);
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
                        <h2>DATA PEMBELIAN PRODUK</h2>
                        <div>
                            <a href="new.php" class="btn btn-success">
                                <i class="ti ti-file-plus"></i> Tambah Pesanan
                            </a>
                        </div>
                    </div>
                    <!-- Card Summary -->
                    <div class="row g-3 mb-4">
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

                        <div class="col-md-3 col-sm-6">
                            <div class="card text-center shadow-sm border-0">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= number_format($summary_manual['total_item'], 0, ',', '.') ?>
                                    </h4>
                                    <small class="text-muted">Total Item Dibeli</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="card text-center shadow-sm border-0">
                                <div class="card-body py-3">
                                    <h4 class="fw-bold mb-1">
                                        <?= number_format($summary_manual['total_jumlah'], 0, ',', '.') ?>
                                    </h4>
                                    <small class="text-muted">Total Jumlah Barang</small>
                                </div>
                            </div>
                        </div>

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
                            <!-- Card Filter -->
                            <div class="card filter-card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="ti ti-filter me-2"></i>Filter Data Pembelian</h5>
                                </div>
                                <div class="card-body">
                                    <form method="GET" id="mainFilterForm" class="row g-3">

                                        <div class="col-md-6">
                                            <label class="form-label">Supplier</label>
                                            <select class="form-control" name="id_supplier">
                                                <option value="">Semua Supplier</option>
                                                <?php foreach ($suppliers as $s): ?>
                                                    <option value="<?= $s['id_supplier'] ?>"
                                                        <?= $id_supplier == $s['id_supplier'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($s['nama_supplier']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
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
                                            <!-- Container untuk menampilkan produk yang dipilih -->
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
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <?php if (!empty($filter_produk)): ?>
                                                        <span class="text-muted">
                                                            <i class="ti ti-info-circle"></i>
                                                            Menampilkan <?= count($filter_produk) ?> produk terpilih
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
                            <!-- Summary per Produk -->
                            <?php if (!empty($summary_per_produk)): ?>
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0">
                                            <i class="ti ti-chart-bar me-2"></i>Ringkasan Pembelian per Produk
                                            <?php if (!empty($filter_produk)): ?>
                                                <small class="text-muted">(Filter: <?= count($filter_produk) ?> produk terpilih)</small>
                                            <?php endif; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0 summary-produk-table">
                                                <thead>
                                                    <tr>
                                                        <th width="5%">No</th>
                                                        <th width="35%">Nama Produk</th>
                                                        <th width="15%" class="text-end">Jumlah Dibeli</th>
                                                        <!-- <th width="20%" class="text-end">Harga Per Produk</th> -->
                                                        <th width="20%" class="text-end">Total Nilai</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $total_jumlah_summary = 0;
                                                    $total_nilai_summary = 0;
                                                    ?>

                                                    <?php foreach ($summary_per_produk as $index => $produk): ?>
                                                        <?php
                                                        // Hitung total nilai
                                                        $total_nilai_produk = $produk['total_subtotal'];

                                                        // Akumulasi untuk total
                                                        $total_jumlah_summary += $produk['total_jumlah'];
                                                        $total_nilai_summary += $total_nilai_produk;
                                                        ?>
                                                        <tr>
                                                            <td class="text-center"><?= $index + 1 ?></td>
                                                            <td>
                                                                <?= htmlspecialchars($produk['nama_produk']) ?>
                                                                <?php if (!empty($filter_produk) && in_array($produk['id_produk'], $filter_produk)): ?>
                                                                    <span class="badge bg-success ms-1">Terpilih</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-end"><?= number_format($produk['total_jumlah'], 0, ',', '.') ?></td>
                                                            <!-- <td class="text-end text-money">
                                                                <?= formatRupiah($produk['total_subtotal'] / $produk['total_jumlah']) ?>
                                                            </td> -->
                                                            <td class="text-end text-money"><?= formatRupiah($total_nilai_produk) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr class="table-light">
                                                        <th colspan="2" class="text-end">TOTAL</th>
                                                        <th class="text-end"><?= number_format($total_jumlah_summary, 0, ',', '.') ?></th>
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
                                Detail Pembelian Produk
                                <?php if (!empty($filter_produk)): ?>
                                    <span class="badge bg-info ms-2">
                                        Filter: <?= count($filter_produk) ?> produk terpilih
                                    </span>
                                <?php endif; ?>
                            </h5>
                        </div>

                        <div class="card-body">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                                    <i class="ti ti-alert-circle me-2"></i>
                                    <div><?= htmlspecialchars($_SESSION['error']) ?></div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                                    <i class="ti ti-check me-2"></i>
                                    <div><?= htmlspecialchars($_SESSION['success']) ?></div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>

                                            <th width="120">Tanggal</th>
                                            <th>Supplier</th>
                                            <th>Produk</th>
                                            <th width="90" class="text-center">Jumlah (Pcs)</th>
                                            <th width="120" class="text-end">Harga Satuan</th>
                                            <th width="140" class="text-end">Subtotal</th>
                                            <th width="100" class="text-center">Status</th>
                                            <th width="120" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($detail_pembelian)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                                                        Tidak ada data pembelian produk
                                                        <?php if (!empty($search) || $id_supplier > 0 || $status != 'all' || !empty($filter_produk)): ?>
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
                                                $grand_total += $item['subtotal'];

                                                // Tampilkan baris supplier jika pembelian berbeda
                                                if ($current_pembelian_id != $item['id_pembelian']):
                                                    // Tampilkan total transaksi sebelumnya jika ada
                                                    if ($current_pembelian_id !== null): ?>
                                                        <!-- Baris total transaksi -->
                                                        <tr class="table-light">
                                                            <td colspan="5" class="text-end fw-bold">Total Transaksi:</td>
                                                            <td class="text-end fw-bold text-primary">
                                                                <?= formatRupiah($current_transaksi_total) ?>
                                                            </td>
                                                            <td colspan="2"></td>
                                                        </tr>
                                                    <?php endif;

                                                    // Reset untuk transaksi baru
                                                    $current_pembelian_id = $item['id_pembelian'];
                                                    $current_transaksi_total = 0;
                                                    ?>
                                                    <!-- Baris Header Transaksi -->
                                                    <tr class="table-active">
                                                        <td>
                                                            <div class="fw-bold"><?= dateIndo($item['tanggal_pembelian']) ?></div>
                                                            <small class="text-muted">ID Transaksi: <?= $item['id_pembelian'] ?></small>

                                                        </td>
                                                        <td colspan="2">
                                                            <div class="fw-bold"><?= htmlspecialchars($item['nama_supplier']) ?></div>
                                                        </td>
                                                        <td colspan="2"></td>
                                                        <td></td>
                                                        <td class="text-center">
                                                            <span class="badge <?= $item['status_pembayaran'] == 'lunas' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                                <?= ucfirst($item['status_pembayaran']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="btn-group btn-group-sm">
                                                                <?php if ($item['status_pembayaran'] != 'batal'): ?>
                                                                    <button class="btn btn-danger btn-batal"
                                                                        data-id="<?= $item['id_pembelian'] ?>"
                                                                        data-no="<?= $item['id_pembelian'] ?>"
                                                                        title="Batalkan Pembelian">
                                                                        <i class="ti ti-x"></i>
                                                                    </button>
                                                                    <?php if ($item['status_pembayaran'] == 'cicilan'): ?>
                                                                        <a href="cicilan.php?id=<?= $item['id_pembelian'] ?>"
                                                                            class="btn btn-warning"
                                                                            title="Pembayaran Cicilan">
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

                                                <!-- Baris Detail Produk -->
                                                <?php $current_transaksi_total += $item['subtotal']; ?>
                                                <tr>

                                                    <td></td>
                                                    <td></td>
                                                    <td>
                                                        <div><?= htmlspecialchars($item['nama_produk']) ?></div>
                                                        <?php if (!empty($filter_produk) && in_array($item['id_produk'], $filter_produk)): ?>
                                                            <small class="text-success">
                                                                <i class="ti ti-check"></i> Terpilih
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= number_format($item['jumlah'], 0, ',', '.') ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <?= formatRupiah($item['harga_satuan']) ?>
                                                    </td>
                                                    <td class="text-end fw-bold">
                                                        <?= formatRupiah($item['subtotal']) ?>
                                                    </td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Total Transaksi Terakhir -->
                                            <?php if ($current_pembelian_id !== null): ?>
                                                <tr class="table-light">
                                                    <td colspan="5" class="text-end fw-bold">Total Transaksi:</td>
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
            // Set tanggal default jika belum diisi
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

            // Update tombol dropdown
            const dropdownBtn = $('#produkDropdown');
            if (selectedProduk.length === 0) {
                dropdownBtn.text('Pilih Produk...');
            } else {
                dropdownBtn.text(selectedProduk.length + ' produk dipilih');
            }

            // Update tampilan badge
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

        // Fungsi untuk update select all checkbox
        function updateSelectAllCheckbox() {
            const totalCheckboxes = $('.multiselect-dropdown input[name="produk[]"]').length;
            const checkedCheckboxes = $('.multiselect-dropdown input[name="produk[]"]:checked').length;

            $('#selectAllProduk').prop('checked', totalCheckboxes === checkedCheckboxes);
            $('#selectAllProduk').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        }

        // Fungsi untuk menghapus produk dari pilihan
        function removeProduk(produkId) {
            $(`#produk_${produkId}`).prop('checked', false);
            updateSelectedProduk();
            updateSelectAllCheckbox();

            // Submit form secara otomatis saat menghapus produk
            $('#mainFilterForm').submit();
        }

        // Handle tombol batal pembelian
        $(document).on('click', '.btn-batal', function(e) {
            e.preventDefault();

            const pembelianId = $(this).data('id');
            const noTransaksi = $(this).data('no');

            Swal.fire({
                title: 'Batalkan Pembelian?',
                html: `Apakah Anda yakin ingin membatalkan pembelian <strong>#${noTransaksi}</strong>?<br>
                       <small class="text-danger">Aksi ini akan mengembalikan stok produk!</small>`,
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
<!-- [Body] end -->

</html>