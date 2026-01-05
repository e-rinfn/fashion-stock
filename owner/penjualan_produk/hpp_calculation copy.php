<?php
// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Ambil bulan dan tahun saat ini
$current_month = date('m');
$current_year = date('Y');

// Cek apakah ada parameter periode yang dipilih
$selected_month = isset($_GET['bulan']) ? intval($_GET['bulan']) : $current_month;
$selected_year = isset($_GET['tahun']) ? intval($_GET['tahun']) : $current_year;

// Ambil data periode HPP
$periode = query("SELECT * FROM hpp_periode WHERE bulan = $selected_month AND tahun = $selected_year");
$periode_id = 0;
$total_biaya_produksi = 0;
$total_penjualan = 0;
$total_hpp = 0;
$laba_bersih = 0;

if (!empty($periode)) {
    $periode = $periode[0];
    $periode_id = $periode['id_periode'];
    $total_biaya_produksi = $periode['total_biaya_produksi'];
    $total_penjualan = $periode['total_penjualan'];
    $total_hpp = $periode['total_hpp'];
    $laba_bersih = $periode['laba_bersih'];
}

// Ambil data biaya produksi
$biaya_produksi = [];
if ($periode_id > 0) {
    $biaya_produksi = query("SELECT * FROM hpp_biaya_produksi WHERE id_periode = $periode_id ORDER BY created_at DESC");
}

// Ambil data penjualan untuk periode yang dipilih
$tanggal_awal = "$selected_year-" . str_pad($selected_month, 2, '0', STR_PAD_LEFT) . "-01";
$tanggal_akhir = date('Y-m-t', strtotime($tanggal_awal));

// Query untuk mendapatkan total penjualan per produk
$query_penjualan = "
    SELECT 
        pr.id_produk,
        pr.nama_produk,
        SUM(dp.jumlah) as total_jumlah,
        SUM(dp.subtotal) as total_harga
    FROM detail_penjualan dp
    JOIN penjualan pj ON dp.id_penjualan = pj.id_penjualan
    JOIN produk pr ON dp.id_produk = pr.id_produk
    WHERE DATE(pj.tanggal_penjualan) BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
    GROUP BY pr.id_produk, pr.nama_produk
    ORDER BY pr.nama_produk";

$penjualan_per_produk = query($query_penjualan);

// Hitung total penjualan
$total_penjualan_bulanan = 0;
if (!empty($penjualan_per_produk)) {
    foreach ($penjualan_per_produk as $item) {
        $total_penjualan_bulanan += $item['total_harga'];
    }
}

// Handle form tambah biaya produksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_biaya'])) {
        $keterangan = $conn->real_escape_string($_POST['keterangan']);
        $biaya = floatval($_POST['biaya']);

        // Cek apakah periode sudah ada
        if ($periode_id == 0) {
            // Buat periode baru
            $sql_periode = "INSERT INTO hpp_periode (bulan, tahun, total_biaya_produksi, total_penjualan) 
                           VALUES ($selected_month, $selected_year, $biaya, $total_penjualan_bulanan)";
            if ($conn->query($sql_periode)) {
                $periode_id = $conn->insert_id;
            }
        } else {
            // Update total biaya produksi di periode
            $new_total_biaya = $total_biaya_produksi + $biaya;
            $sql_update = "UPDATE hpp_periode SET total_biaya_produksi = $new_total_biaya 
                          WHERE id_periode = $periode_id";
            $conn->query($sql_update);
        }

        // Simpan detail biaya
        if ($periode_id > 0) {
            $sql_biaya = "INSERT INTO hpp_biaya_produksi (id_periode, keterangan, biaya) 
                         VALUES ($periode_id, '$keterangan', $biaya)";
            if ($conn->query($sql_biaya)) {
                $_SESSION['success'] = "Biaya produksi berhasil ditambahkan!";

                // Simpan/update data penjualan ke tabel hpp_detail_penjualan
                if (!empty($penjualan_per_produk)) {
                    // Hapus data penjualan lama untuk periode ini
                    $conn->query("DELETE FROM hpp_detail_penjualan WHERE id_periode = $periode_id");

                    // Insert data penjualan baru
                    foreach ($penjualan_per_produk as $item) {
                        $id_produk = $item['id_produk'];
                        $nama_produk = $conn->real_escape_string($item['nama_produk']);
                        $jumlah = $item['total_jumlah'];
                        $total_harga = $item['total_harga'];

                        $sql_detail = "INSERT INTO hpp_detail_penjualan 
                                      (id_periode, id_produk, nama_produk, jumlah, total_harga) 
                                      VALUES ($periode_id, $id_produk, '$nama_produk', $jumlah, $total_harga)";
                        $conn->query($sql_detail);
                    }
                }

                // Update total penjualan dan hitung HPP
                $new_total_biaya = $total_biaya_produksi + $biaya;
                $new_hpp = $new_total_biaya;
                $new_laba = $total_penjualan_bulanan - $new_hpp;

                $sql_update_hpp = "UPDATE hpp_periode 
                                  SET total_penjualan = $total_penjualan_bulanan,
                                      total_hpp = $new_hpp,
                                      laba_bersih = $new_laba
                                  WHERE id_periode = $periode_id";
                $conn->query($sql_update_hpp);

                // Refresh halaman
                header("Location: hpp_calculation.php?bulan=$selected_month&tahun=$selected_year");
                exit();
            }
        }
    }

    // Handle edit biaya
    if (isset($_POST['edit_biaya'])) {
        $id_biaya = intval($_POST['id_biaya']);
        $keterangan = $conn->real_escape_string($_POST['keterangan']);
        $biaya = floatval($_POST['biaya']);

        // Ambil biaya lama
        $biaya_lama = query("SELECT biaya FROM hpp_biaya_produksi WHERE id_biaya = $id_biaya")[0]['biaya'];

        // Update biaya
        $sql_update = "UPDATE hpp_biaya_produksi SET keterangan = '$keterangan', biaya = $biaya 
                      WHERE id_biaya = $id_biaya";
        if ($conn->query($sql_update)) {
            // Update total biaya produksi di periode
            $selisih = $biaya - $biaya_lama;
            $new_total_biaya = $total_biaya_produksi + $selisih;
            $new_hpp = $new_total_biaya;
            $new_laba = $total_penjualan_bulanan - $new_hpp;

            $sql_update_periode = "UPDATE hpp_periode 
                                  SET total_biaya_produksi = $new_total_biaya,
                                      total_hpp = $new_hpp,
                                      laba_bersih = $new_laba
                                  WHERE id_periode = $periode_id";
            $conn->query($sql_update_periode);

            $_SESSION['success'] = "Biaya produksi berhasil diubah!";
            header("Location: hpp_calculation.php?bulan=$selected_month&tahun=$selected_year");
            exit();
        }
    }

    // Handle hapus biaya
    if (isset($_POST['hapus_biaya'])) {
        $id_biaya = intval($_POST['id_biaya']);

        // Ambil biaya yang akan dihapus
        $biaya_hapus = query("SELECT biaya FROM hpp_biaya_produksi WHERE id_biaya = $id_biaya")[0]['biaya'];

        // Hapus biaya
        $sql_delete = "DELETE FROM hpp_biaya_produksi WHERE id_biaya = $id_biaya";
        if ($conn->query($sql_delete)) {
            // Update total biaya produksi di periode
            $new_total_biaya = $total_biaya_produksi - $biaya_hapus;
            $new_hpp = $new_total_biaya;
            $new_laba = $total_penjualan_bulanan - $new_hpp;

            $sql_update = "UPDATE hpp_periode 
                          SET total_biaya_produksi = $new_total_biaya,
                              total_hpp = $new_hpp,
                              laba_bersih = $new_laba
                          WHERE id_periode = $periode_id";
            $conn->query($sql_update);

            $_SESSION['success'] = "Biaya produksi berhasil dihapus!";
            header("Location: hpp_calculation.php?bulan=$selected_month&tahun=$selected_year");
            exit();
        }
    }

    // Handle refresh data penjualan
    if (isset($_POST['refresh_penjualan'])) {
        if ($periode_id > 0) {
            // Hapus data penjualan lama
            $conn->query("DELETE FROM hpp_detail_penjualan WHERE id_periode = $periode_id");

            // Insert data penjualan baru
            if (!empty($penjualan_per_produk)) {
                foreach ($penjualan_per_produk as $item) {
                    $id_produk = $item['id_produk'];
                    $nama_produk = $conn->real_escape_string($item['nama_produk']);
                    $jumlah = $item['total_jumlah'];
                    $total_harga = $item['total_harga'];

                    $sql_detail = "INSERT INTO hpp_detail_penjualan 
                                  (id_periode, id_produk, nama_produk, jumlah, total_harga) 
                                  VALUES ($periode_id, $id_produk, '$nama_produk', $jumlah, $total_harga)";
                    $conn->query($sql_detail);
                }
            }

            // Update total penjualan dan hitung ulang HPP
            $new_hpp = $total_biaya_produksi;
            $new_laba = $total_penjualan_bulanan - $new_hpp;

            $sql_update = "UPDATE hpp_periode 
                          SET total_penjualan = $total_penjualan_bulanan,
                              total_hpp = $new_hpp,
                              laba_bersih = $new_laba
                          WHERE id_periode = $periode_id";
            $conn->query($sql_update);

            $_SESSION['success'] = "Data penjualan berhasil diperbarui!";
            header("Location: hpp_calculation.php?bulan=$selected_month&tahun=$selected_year");
            exit();
        }
    }
}

// Ambil data penjualan dari tabel hpp_detail_penjualan jika sudah disimpan
$penjualan_simpan = [];
if ($periode_id > 0) {
    $penjualan_simpan = query("SELECT * FROM hpp_detail_penjualan WHERE id_periode = $periode_id ORDER BY nama_produk");
}

// Gunakan data yang sudah disimpan jika ada, jika tidak gunakan data langsung dari penjualan
$data_penjualan = !empty($penjualan_simpan) ? $penjualan_simpan : $penjualan_per_produk;

// Hitung ulang total jika menggunakan data langsung
if (empty($penjualan_simpan) && !empty($penjualan_per_produk)) {
    $total_penjualan_bulanan = 0;
    foreach ($penjualan_per_produk as $item) {
        $total_penjualan_bulanan += $item['total_harga'];
    }
}

// Update variabel jika ada data periode
if ($periode_id > 0) {
    $periode = query("SELECT * FROM hpp_periode WHERE id_periode = $periode_id")[0];
    $total_biaya_produksi = $periode['total_biaya_produksi'];
    $total_penjualan = $periode['total_penjualan'];
    $total_hpp = $periode['total_hpp'];
    $laba_bersih = $periode['laba_bersih'];
}
?>

<style>
    .card-hpp {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        height: 100%;
    }

    .card-header-hpp {
        border-radius: 10px 10px 0 0 !important;
        padding: 15px 20px;
        font-weight: 600;
    }

    .biaya-item {
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 10px;
        background-color: #fff;
        transition: all 0.3s ease;
    }

    .biaya-item:hover {
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .biaya-item .actions {
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .biaya-item:hover .actions {
        opacity: 1;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }

    .summary-box {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-top: 15px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #dee2e6;
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    .profit-positive {
        color: #198754;
        font-weight: bold;
    }

    .profit-negative {
        color: #dc3545;
        font-weight: bold;
    }

    .produk-row {
        border-left: 3px solid #0d6efd;
        padding-left: 10px;
        margin-bottom: 10px;
        padding: 10px;
        background-color: #f8f9fa;
        border-radius: 5px;
    }

    .btn-add {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        border: none;
        color: white;
        font-weight: 500;
    }

    .btn-add:hover {
        color: white;
        opacity: 0.9;
    }

    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .badge-period {
        font-size: 0.9em;
        padding: 5px 10px;
        border-radius: 20px;
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

                    <!-- Period Selector -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <div class="row align-items-center g-3">

                                <!-- Periode Info -->
                                <div class="col-md-4">
                                    <div class="fw-semibold mb-1">
                                        Periode
                                    </div>
                                    <h5 class="mb-0">
                                        <?= date('F Y', strtotime($tanggal_awal)) ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?= date('d F Y', strtotime($tanggal_awal)) ?>
                                        &nbsp;–&nbsp;
                                        <?= date('d F Y', strtotime($tanggal_akhir)) ?>
                                    </small>
                                </div>

                                <!-- Filter Form -->
                                <div class="col-md-5">
                                    <form method="GET" class="row g-2 align-items-end">
                                        <div class="col-6">
                                            <label class="form-label small mb-1">Bulan</label>
                                            <select class="form-select" name="bulan">
                                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                                    <option value="<?= $i ?>" <?= $selected_month == $i ? 'selected' : '' ?>>
                                                        <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label small mb-1">Tahun</label>
                                            <select class="form-select" name="tahun">
                                                <?php for ($i = date('Y') - 2; $i <= date('Y'); $i++): ?>
                                                    <option value="<?= $i ?>" <?= $selected_year == $i ? 'selected' : '' ?>>
                                                        <?= $i ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-2">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="ti ti-filter"></i> Filter
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Status -->
                                <div class="col-md-3 text-md-end text-start">
                                    <?php if ($periode_id > 0): ?>
                                        <span class="badge bg-success px-3 py-2">
                                            <i class="ti ti-check me-1"></i> Data Tersimpan
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark px-3 py-2">
                                            <i class="ti ti-alert-circle me-1"></i> Data Baru
                                        </span>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>


                    <!-- Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                            <i class="ti ti-check me-2"></i>
                            <div><?= htmlspecialchars($_SESSION['success']) ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                            <i class="ti ti-alert-circle me-2"></i>
                            <div><?= htmlspecialchars($_SESSION['error']) ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Card Biaya Produksi -->
                        <div class="col-md-6">
                            <div class="card shadow-sm border-0">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="ti ti-calculator me-1"></i> Biaya Produksi
                                    </h6>
                                    <span class="fw-semibold text-primary">
                                        <?= formatRupiah($total_biaya_produksi) ?>
                                    </span>
                                </div>

                                <div class="card-body">

                                    <!-- Form Tambah Biaya -->
                                    <form method="POST" class="mb-3">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-6">
                                                <label class="form-label small mb-1">Keterangan</label>
                                                <input type="text"
                                                    name="keterangan"
                                                    class="form-control"
                                                    placeholder="Contoh: Bahan baku"
                                                    required>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Biaya</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number"
                                                        name="biaya"
                                                        class="form-control"
                                                        min="0"
                                                        step="1000"
                                                        required>
                                                </div>
                                            </div>

                                            <div class="col-md-2">
                                                <button type="submit"
                                                    name="tambah_biaya"
                                                    class="btn btn-primary w-100">
                                                    Simpan
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    <hr class="my-3">

                                    <!-- Daftar Biaya -->
                                    <?php if (empty($biaya_produksi)): ?>
                                        <div class="text-center text-muted py-4">
                                            Belum ada biaya produksi
                                        </div>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($biaya_produksi as $biaya): ?>
                                                <li class="list-group-item px-0">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <div class="fw-semibold">
                                                                <?= htmlspecialchars($biaya['keterangan']) ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?= date('d/m/Y H:i', strtotime($biaya['created_at'])) ?>
                                                            </small>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="fw-semibold text-primary">
                                                                <?= formatRupiah($biaya['biaya']) ?>
                                                            </div>
                                                            <div class="btn-group btn-group-sm mt-1">
                                                                <button type="button"
                                                                    class="btn btn-outline-secondary btn-edit-biaya"
                                                                    data-id="<?= $biaya['id_biaya'] ?>"
                                                                    data-keterangan="<?= htmlspecialchars($biaya['keterangan']) ?>"
                                                                    data-biaya="<?= $biaya['biaya'] ?>">
                                                                    <i class="ti ti-edit"></i>
                                                                </button>
                                                                <button type="button"
                                                                    class="btn btn-outline-danger btn-hapus-biaya"
                                                                    data-id="<?= $biaya['id_biaya'] ?>">
                                                                    <i class="ti ti-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <!-- Summary -->
                                    <div class="border-top pt-3 mt-3 d-flex justify-content-between small">
                                        <span>Total Item</span>
                                        <span class="fw-semibold"><?= count($biaya_produksi) ?> item</span>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Card Kanan: Penjualan Produk -->
                        <div class="col-md-6">
                            <div class="card card-hpp">
                                <div class="card-header card-header-hpp bg-success text-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="ti ti-shopping-cart me-2"></i>Penjualan Produk
                                        </h5>
                                        <form method="POST" class="d-inline">
                                            <button type="submit" name="refresh_penjualan" class="btn btn-sm btn-light">
                                                <i class="ti ti-refresh me-1"></i> Refresh
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Daftar Penjualan per Produk -->
                                    <div class="penjualan-list mb-3" style="max-height: 400px; overflow-y: auto;">
                                        <?php if (empty($data_penjualan)): ?>
                                            <div class="text-center py-4 text-muted">
                                                <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                                                Tidak ada data penjualan untuk periode ini
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($data_penjualan as $item): ?>
                                                <div class="produk-row">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <strong><?= htmlspecialchars($item['nama_produk']) ?></strong>
                                                        </div>
                                                        <div class="col-md-3 text-end">
                                                            <span class="badge bg-primary rounded-pill">
                                                                <?= number_format($item['jumlah'], 0, ',', '.') ?> pcs
                                                            </span>
                                                        </div>
                                                        <div class="col-md-3 text-end">
                                                            <strong><?= formatRupiah($item['total_harga']) ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Summary Penjualan dan HPP -->
                                    <div class="summary-box">
                                        <div class="summary-item">
                                            <span>Total Penjualan:</span>
                                            <span class="fw-bold text-success"><?= formatRupiah($total_penjualan) ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span>Total Produk Terjual:</span>
                                            <span>
                                                <?php
                                                $total_produk = 0;
                                                if (!empty($data_penjualan)) {
                                                    foreach ($data_penjualan as $item) {
                                                        $total_produk += $item['jumlah'];
                                                    }
                                                }
                                                echo number_format($total_produk, 0, ',', '.') . ' pcs';
                                                ?>
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span>Jumlah Produk:</span>
                                            <span><?= count($data_penjualan) ?> jenis</span>
                                        </div>
                                        <hr>
                                        <div class="summary-item">
                                            <span>HPP (Biaya Produksi):</span>
                                            <span class="fw-bold text-danger"><?= formatRupiah($total_hpp) ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span>Laba Bersih:</span>
                                            <span class="fw-bold <?= $laba_bersih >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                <?= formatRupiah($laba_bersih) ?>
                                            </span>
                                        </div>

                                        <!-- Rumus Perhitungan -->
                                        <div class="mt-3 p-2 bg-light rounded">
                                            <small class="text-muted">
                                                <strong>Rumus Perhitungan:</strong><br>
                                                HPP = Total Biaya Produksi<br>
                                                Laba Bersih = Total Penjualan - HPP
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Informasi Periode -->
                                    <?php if ($periode_id > 0): ?>
                                        <div class="alert alert-info mt-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <small>
                                                        <i class="ti ti-info-circle me-1"></i>
                                                        Data disimpan pada: <?= date('d/m/Y H:i', strtotime($periode['updated_at'])) ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <small class="text-muted">
                                                        Status: <span class="badge bg-success">Tersimpan di Database</span>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning mt-3">
                                            <small>
                                                <i class="ti ti-alert-circle me-1"></i>
                                                Data belum disimpan di database. Tambahkan biaya produksi untuk menyimpan.
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Export & Action Buttons -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center">
                                    <button type="button" class="btn btn-primary me-2" onclick="printHPPReport()">
                                        <i class="ti ti-printer me-1"></i> Cetak Laporan HPP
                                    </button>
                                    <button type="button" class="btn btn-success me-2" onclick="exportHPPToExcel()">
                                        <i class="ti ti-download me-1"></i> Export ke Excel
                                    </button>
                                    <a href="hpp_history.php" class="btn btn-info">
                                        <i class="ti ti-history me-1"></i> Lihat Riwayat HPP
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Modal Edit Biaya -->
    <div class="modal fade" id="editBiayaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Biaya Produksi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editBiayaForm">
                    <div class="modal-body">
                        <input type="hidden" name="id_biaya" id="edit_id_biaya">
                        <div class="mb-3">
                            <label for="edit_keterangan" class="form-label">Keterangan</label>
                            <input type="text" class="form-control" id="edit_keterangan" name="keterangan" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_biaya" class="form-label">Jumlah Biaya</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" id="edit_biaya" name="biaya" min="0" step="1000" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_biaya" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Biaya -->
    <div class="modal fade" id="hapusBiayaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Hapus Biaya Produksi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="hapusBiayaForm">
                    <div class="modal-body">
                        <input type="hidden" name="id_biaya" id="hapus_id_biaya">
                        <p>Apakah Anda yakin ingin menghapus biaya: <strong id="hapus_keterangan"></strong>?</p>
                        <p class="text-danger"><small>Aksi ini tidak dapat dibatalkan!</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="hapus_biaya" class="btn btn-danger">Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle edit biaya
            $('.btn-edit-biaya').click(function() {
                const id = $(this).data('id');
                const keterangan = $(this).data('keterangan');
                const biaya = $(this).data('biaya');

                $('#edit_id_biaya').val(id);
                $('#edit_keterangan').val(keterangan);
                $('#edit_biaya').val(biaya);

                $('#editBiayaModal').modal('show');
            });

            // Handle hapus biaya
            $('.btn-hapus-biaya').click(function() {
                const id = $(this).data('id');
                const keterangan = $(this).data('keterangan');

                $('#hapus_id_biaya').val(id);
                $('#hapus_keterangan').text(keterangan);

                $('#hapusBiayaModal').modal('show');
            });

            // Auto-format input biaya dengan titik
            $('input[name="biaya"], #edit_biaya').on('blur', function() {
                const value = $(this).val();
                if (value) {
                    const formatted = new Intl.NumberFormat('id-ID').format(value);
                    $(this).val(formatted);
                }
            }).on('focus', function() {
                const value = $(this).val().replace(/\./g, '');
                $(this).val(value);
            });
        });

        // Cetak Laporan HPP
        function printHPPReport() {
            window.print();
        }

        // Export ke Excel
        function exportHPPToExcel() {
            const hasData = <?= !empty($data_penjualan) ? 'true' : 'false' ?>;

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
                            csvContent += "LAPORAN HPP PERIODE <?= date('F Y', strtotime($tanggal_awal)) ?>\r\n";
                            csvContent += "Tanggal Export: <?= date('d/m/Y H:i:s') ?>\r\n\r\n";

                            // Bagian 1: Biaya Produksi
                            csvContent += "BIAYA PRODUKSI\r\n";
                            csvContent += "No,Keterangan,Biaya\r\n";
                            <?php if (!empty($biaya_produksi)): ?>
                                <?php foreach ($biaya_produksi as $index => $biaya): ?>
                                    csvContent += "<?= $index + 1 ?>,<?= addslashes($biaya['keterangan']) ?>,<?= $biaya['biaya'] ?>\r\n";
                                <?php endforeach; ?>
                            <?php endif; ?>
                            csvContent += "TOTAL,,<?= $total_biaya_produksi ?>\r\n\r\n";

                            // Bagian 2: Penjualan Produk
                            csvContent += "PENJUALAN PRODUK\r\n";
                            csvContent += "No,Nama Produk,Jumlah,Total Harga\r\n";
                            <?php if (!empty($data_penjualan)): ?>
                                <?php foreach ($data_penjualan as $index => $item): ?>
                                    csvContent += "<?= $index + 1 ?>,<?= addslashes($item['nama_produk']) ?>,<?= $item['jumlah'] ?>,<?= $item['total_harga'] ?>\r\n";
                                <?php endforeach; ?>
                            <?php endif; ?>
                            csvContent += "TOTAL, ,<?= $total_produk ?>,<?= $total_penjualan ?>\r\n\r\n";

                            // Bagian 3: Perhitungan HPP
                            csvContent += "PERHITUNGAN HPP\r\n";
                            csvContent += "Item,Jumlah\r\n";
                            csvContent += "Total Penjualan,<?= $total_penjualan ?>\r\n";
                            csvContent += "Total Biaya Produksi (HPP),<?= $total_hpp ?>\r\n";
                            csvContent += "Laba Bersih,<?= $laba_bersih ?>\r\n\r\n";

                            // Download
                            const encodedUri = encodeURI(csvContent);
                            const link = document.createElement("a");
                            link.setAttribute("href", encodedUri);
                            link.setAttribute("download", `laporan_hpp_<?= date('Y_m', strtotime($tanggal_awal)) ?>.csv`);
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