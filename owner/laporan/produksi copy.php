<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';

// ============================================================================
// FUNGSI VALIDASI DAN UTILITAS
// ============================================================================

/**
 * Cek apakah data koko sudah ada untuk produk tertentu
 * @param int $id_produk ID produk yang akan dicek
 * @return bool True jika data koko sudah ada, false jika belum
 */
function isKokoExist($id_produk)
{
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM koko WHERE id_produk = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] > 0;
}

/**
 * Mendapatkan stok koko untuk produk tertentu
 * @param int $id_produk ID produk yang akan dicek stoknya
 * @return int Jumlah stok koko, 0 jika tidak ada
 */
function getStokKoko($id_produk)
{
    global $conn;
    $sql = "SELECT stok FROM koko WHERE id_produk = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['stok'];
    }
    return 0;
}

/**
 * Mendapatkan tarif upah terkini berdasarkan jenis tarif dan tanggal referensi
 * @param string $jenis_tarif Jenis tarif ('pemotongan' atau 'penjahitan')
 * @param string|null $tanggal_referensi Tanggal referensi untuk mencari tarif yang berlaku
 * @return float Tarif per unit
 */
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
    return 0.00;
}

// ============================================================================
// AMBIL DATA DARI DATABASE UNTUK DROPDOWN DAN FILTER
// ============================================================================

// Ambil semua produk untuk dropdown
$produk = query("SELECT * FROM produk");
$pemotong = query("SELECT * FROM pemotong");
$penjahit = query("SELECT * FROM penjahit");

// ============================================================================
// SET FILTER DARI REQUEST GET
// ============================================================================

// Cek filter yang diterapkan
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Tambah filter pemotong dan penjahit
$id_pemotong = isset($_GET['id_pemotong']) ? (int)$_GET['id_pemotong'] : 0;
$id_penjahit = isset($_GET['id_penjahit']) ? $_GET['id_penjahit'] : 0;

$start_date_default = date('Y-m-01');
$end_date_default   = date('Y-m-t');

// ============================================================================
// HITUNG TOTAL DATA UNTUK FOOTER TABEL
// ============================================================================

/**
 * 1. HITUNG TOTAL TANPA FILTER (SEMUA DATA)
 */
$sql_total_all = "SELECT 
    SUM(h.total_hasil) as total_hasil_all,
    SUM(COALESCE(h.total_hasil_jahit, 0)) as total_hasil_jahit_all
FROM hasil_potong_fix h 
WHERE 1=1";

$total_all = query($sql_total_all)[0];
$total_hasil_all = $total_all['total_hasil_all'] ?? 0;
$total_hasil_jahit_all = $total_all['total_hasil_jahit_all'] ?? 0;

/**
 * 2. HITUNG TOTAL DENGAN FILTER YANG DITERAPKAN
 */
$sql_total_filtered = "SELECT 
    SUM(h.total_hasil) as total_hasil_filtered,
    SUM(COALESCE(h.total_hasil_jahit, 0)) as total_hasil_jahit_filtered
FROM hasil_potong_fix h 
JOIN produk p ON h.id_produk = p.id_produk 
JOIN pemotong pem ON h.id_pemotong = pem.id_pemotong 
LEFT JOIN penjahit pen ON h.id_penjahit = pen.id_penjahit 
WHERE 1=1";

// Filter produk
if ($id_produk > 0) {
    $sql_total_filtered .= " AND h.id_produk = $id_produk";
}

// Filter pemotong
if ($id_pemotong > 0) {
    $sql_total_filtered .= " AND h.id_pemotong = $id_pemotong";
}

// Filter penjahit
if ($id_penjahit == '-1') {
    $sql_total_filtered .= " AND (h.id_penjahit IS NULL OR h.id_penjahit = 0)";
} elseif ($id_penjahit > 0) {
    $sql_total_filtered .= " AND h.id_penjahit = $id_penjahit";
}

// Filter status
if ($status != 'all') {
    $sql_total_filtered .= " AND h.status_potong = '$status'";
}

// Filter periode
if (!empty($start_date)) {
    $sql_total_filtered .= " AND h.tanggal_hasil_potong >= '$start_date'";
}

if (!empty($end_date)) {
    $sql_total_filtered .= " AND h.tanggal_hasil_potong <= '$end_date'";
}

$total_filtered = query($sql_total_filtered)[0];
$total_hasil_filtered = $total_filtered['total_hasil_filtered'] ?? 0;
$total_hasil_jahit_filtered = $total_filtered['total_hasil_jahit_filtered'] ?? 0;

/**
 * 3. QUERY UNTUK DATA TABEL DENGAN FILTER
 */
$sql = "SELECT h.*, p.nama_produk, p.tipe_produk, pem.nama_pemotong, 
               pen.nama_penjahit,
               (SELECT SUM(jumlah) FROM detail_hasil_potong_fix WHERE id_hasil_potong_fix = h.id_hasil_potong_fix) as total_hasil_potong
        FROM hasil_potong_fix h 
        JOIN produk p ON h.id_produk = p.id_produk 
        JOIN pemotong pem ON h.id_pemotong = pem.id_pemotong 
        LEFT JOIN penjahit pen ON h.id_penjahit = pen.id_penjahit 
        WHERE 1=1";

// Filter produk
if ($id_produk > 0) {
    $sql .= " AND h.id_produk = $id_produk";
}

// Filter pemotong
if ($id_pemotong > 0) {
    $sql .= " AND h.id_pemotong = $id_pemotong";
}

// Filter penjahit
if ($id_penjahit == '-1') {
    $sql .= " AND (h.id_penjahit IS NULL OR h.id_penjahit = 0)";
} elseif ($id_penjahit > 0) {
    $sql .= " AND h.id_penjahit = $id_penjahit";
}

// Filter status
if ($status != 'all') {
    $sql .= " AND h.status_potong = '$status'";
}

// Filter periode
if (!empty($start_date)) {
    $sql .= " AND h.tanggal_hasil_potong >= '$start_date'";
}

if (!empty($end_date)) {
    $sql .= " AND h.tanggal_hasil_potong <= '$end_date'";
}

$sql .= " ORDER BY CAST(h.seri AS UNSIGNED) DESC, h.tanggal_hasil_potong DESC";

$produksi = query($sql);

// ============================================================================
// PERSIAPAN DATA UNTUK DITAMPILKAN DI TABEL
// ============================================================================

/**
 * Gabungkan data produksi untuk tampilan dengan perhitungan upah
 */
$all_data = [];
foreach ($produksi as $prod) {
    // Dapatkan tarif upah berdasarkan tanggal produksi
    $tarif_pemotong = getTarifUpah('pemotongan', $prod['tanggal_hasil_potong']);
    $tarif_penjahit = !empty($prod['tanggal_hasil_jahit']) ?
        getTarifUpah('penjahitan', $prod['tanggal_hasil_jahit']) :
        getTarifUpah('penjahitan', $prod['tanggal_hasil_potong']);

    // Hitung upah
    $upah_pemotong = $prod['total_hasil'] * $tarif_pemotong;
    $upah_penjahit = !empty($prod['total_hasil_jahit']) ? $prod['total_hasil_jahit'] * $tarif_penjahit : 0;
    $total_upah = $upah_pemotong + $upah_penjahit;

    $all_data[] = [
        'type' => 'produksi',
        'id' => $prod['id_hasil_potong_fix'],
        'tanggal' => $prod['tanggal_hasil_potong'],
        'produk' => $prod['nama_produk'],
        'tipe_produk' => $prod['tipe_produk'],
        'seri' => $prod['seri'],
        'seri_numeric' => intval(preg_replace('/[^0-9]/', '', $prod['seri'])),
        'pemotong' => $prod['nama_pemotong'],
        'penjahit' => $prod['nama_penjahit'],
        'id_penjahit' => $prod['id_penjahit'],
        'status' => $prod['status_potong'],
        'total_hasil' => $prod['total_hasil'],
        'total_harga' => $prod['total_harga'],
        'tanggal_kirim_jahit' => $prod['tanggal_kirim_jahit'],
        'tanggal_hasil_jahit' => $prod['tanggal_hasil_jahit'],
        'total_hasil_jahit' => $prod['total_hasil_jahit'],
        'upah_pemotong' => $upah_pemotong,
        'upah_penjahit' => $upah_penjahit,
        'total_upah' => $total_upah,
        'rate_pemotong' => $tarif_pemotong,
        'rate_penjahit' => $tarif_penjahit
    ];
}

// Urutkan data berdasarkan seri (descending)
usort($all_data, function ($a, $b) {
    return (int)$b['seri'] <=> (int)$a['seri'];
});

// Cek apakah ada filter yang aktif
$is_filtered = $id_produk > 0 || $id_pemotong > 0 || $id_penjahit != 0 ||
    $status != 'all' || !empty($start_date) || !empty($end_date);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Produksi</title>

    <!-- CSS untuk mobile responsiveness -->
    <style>
        .swal2-container {
            z-index: 99999 !important;
        }

        /* Styling untuk mobile */
        @media (max-width: 768px) {

            /* Hide desktop table on mobile */
            .desktop-table {
                display: none !important;
            }

            /* Show mobile cards */
            .mobile-view {
                display: block !important;
            }

            /* Mobile card styling */
            .mobile-card {
                background: white;
                border-radius: 10px;
                padding: 15px;
                margin-bottom: 15px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                border: 1px solid #e0e0e0;
            }

            .mobile-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
                padding-bottom: 10px;
                border-bottom: 2px solid #f0f0f0;
            }

            .mobile-card-title {
                font-weight: 600;
                font-size: 1.1rem;
                color: #2c3e50;
            }

            .mobile-card-content {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .mobile-card-row {
                display: flex;
                flex-direction: column;
                margin-bottom: 8px;
            }

            .mobile-label {
                font-size: 0.8rem;
                color: #7f8c8d;
                margin-bottom: 3px;
                font-weight: 500;
            }

            .mobile-value {
                font-size: 0.9rem;
                font-weight: 500;
                color: #2c3e50;
            }

            .mobile-badge {
                font-size: 0.75rem !important;
                padding: 3px 8px !important;
            }

            .mobile-actions {
                display: flex;
                gap: 8px;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #f0f0f0;
            }

            .mobile-actions .btn {
                flex: 1;
                font-size: 0.85rem;
                padding: 8px 12px;
            }

            /* Responsive form elements */
            .filter-form .form-control,
            .filter-form .form-select {
                font-size: 0.9rem !important;
                padding: 8px 12px !important;
            }

            .filter-form .btn {
                font-size: 0.9rem !important;
                padding: 8px 12px !important;
            }

            /* Adjust columns for mobile */
            .row.g-3>div {
                margin-bottom: 10px;
            }

            /* Responsive tabs */
            .nav-tabs .nav-link {
                font-size: 0.85rem !important;
                padding: 8px 12px !important;
            }

            /* Responsive table */
            .table-responsive {
                font-size: 0.85rem;
            }

            .table th,
            .table td {
                padding: 8px !important;
            }

            /* Compact total info */
            .total-info {
                padding: 12px !important;
            }

            .total-info h5 {
                font-size: 1rem !important;
            }

            .total-row {
                font-size: 0.9rem !important;
            }
        }

        /* Desktop styling (default) */
        .mobile-view {
            display: none;
        }

        .desktop-table {
            display: block;
        }

        /* Common styling */
        .badge-produksi {
            background-color: #0d6efd;
        }

        .upah-column {
            background-color: #e8f5e8 !important;
            font-weight: bold;
        }

        .table th {
            font-size: 0.85rem;
        }

        .table td {
            font-size: 0.85rem;
        }

        .total-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .total-info h5 {
            color: #0d6efd;
            margin-bottom: 15px;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 8px;
            font-size: 1.1rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 6px 0;
            border-bottom: 1px dashed #dee2e6;
        }

        .total-label {
            font-weight: 600;
            color: #495057;
        }

        .total-value {
            font-weight: 700;
            color: #198754;
        }

        .total-filtered {
            background-color: #e7f1ff;
            padding: 12px;
            border-radius: 6px;
            margin-top: 12px;
        }

        /* Badge colors for status */
        .badge-status-potong {
            background-color: #ffc107;
            color: #000;
        }

        .badge-status-penjahitan {
            background-color: #0dcaf0;
            color: #000;
        }

        .badge-status-selesai {
            background-color: #198754;
            color: #fff;
        }

        /* Responsive card for filter info */
        .filter-info-card {
            font-size: 0.9rem;
        }

        /* Mobile tab navigation */
        @media (max-width: 576px) {
            .mobile-tab-content {
                margin-top: 15px;
            }

            .mobile-card-content {
                grid-template-columns: 1fr !important;
            }

            .col-12.mb-4 {
                margin-bottom: 10px !important;
            }
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

    <!-- [ Header ] start -->
    <header class="pc-header">
        <div class="header-wrapper">
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
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
        </div>
    </header>
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <!-- Mobile Title Bar -->
            <div class="d-md-none d-flex align-items-center justify-content-between mb-3 py-2 border-bottom">
                <h4 class="mb-0">
                    <i class="ti ti-factory me-2"></i>Master Produksi
                </h4>
            </div>

            <!-- Desktop Title Bar -->
            <div class="d-none d-md-block">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="ti ti-factory me-2"></i>Master Data Produksi
                    </h2>
                </div>
            </div>

            <!-- Notifikasi -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="ti ti-alert-circle me-2"></i>
                        <div><?= htmlspecialchars($_SESSION['error']) ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="ti ti-check me-2"></i>
                        <div><?= htmlspecialchars($_SESSION['success']) ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- Mobile Tabs Navigation -->
            <div class="d-md-none mb-3">
                <ul class="nav nav-pills nav-fill" id="mobileTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="mobile-data-tab" data-bs-toggle="tab" data-bs-target="#mobile-data" type="button" role="tab">
                            <i class="ti ti-table me-1"></i> Data
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="mobile-filter-tab" data-bs-toggle="tab" data-bs-target="#mobile-filter" type="button" role="tab">
                            <i class="ti ti-filter me-1"></i> Filter
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="mobile-summary-tab" data-bs-toggle="tab" data-bs-target="#mobile-summary" type="button" role="tab">
                            <i class="ti ti-chart-bar me-1"></i> Ringkasan
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content" id="mobileTabContent">
                <!-- Tab Data (Mobile) -->
                <div class="tab-pane fade show active" id="mobile-data" role="tabpanel">
                    <div class="row">
                        <div class="col-12">
                            <!-- Desktop Filter Form -->
                            <div class="d-none d-md-block">
                                <div class="card mb-4">
                                    <div class="card-header bg-light py-3">
                                        <h5 class="mb-0">
                                            <i class="ti ti-filter me-2"></i>Filter Data
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="GET" class="row g-3 filter-form">
                                            <!-- Filter Produk -->
                                            <div class="col-md-2 col-6">
                                                <label class="form-label small">Produk</label>
                                                <select name="id_produk" class="form-select form-select-sm">
                                                    <option value="0">Semua Produk</option>
                                                    <?php foreach ($produk as $p): ?>
                                                        <option value="<?= $p['id_produk'] ?>" <?= ($id_produk == $p['id_produk']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($p['nama_produk']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- Filter Pemotong -->
                                            <div class="col-md-2 col-6">
                                                <label class="form-label small">Pemotong</label>
                                                <select name="id_pemotong" class="form-select form-select-sm">
                                                    <option value="0">Semua Pemotong</option>
                                                    <?php foreach ($pemotong as $pm): ?>
                                                        <option value="<?= $pm['id_pemotong'] ?>" <?= (isset($_GET['id_pemotong']) && $_GET['id_pemotong'] == $pm['id_pemotong']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($pm['nama_pemotong']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- Filter Penjahit -->
                                            <div class="col-md-2 col-6">
                                                <label class="form-label small">Penjahit</label>
                                                <select name="id_penjahit" class="form-select form-select-sm">
                                                    <option value="0">Semua Penjahit</option>
                                                    <option value="-1" <?= (isset($_GET['id_penjahit']) && $_GET['id_penjahit'] == '-1') ? 'selected' : '' ?>>
                                                        Belum Ada
                                                    </option>
                                                    <?php foreach ($penjahit as $pj): ?>
                                                        <option value="<?= $pj['id_penjahit'] ?>" <?= (isset($_GET['id_penjahit']) && $_GET['id_penjahit'] == $pj['id_penjahit']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($pj['nama_penjahit']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- Filter Status -->
                                            <div class="col-md-2 col-6">
                                                <label class="form-label small">Status</label>
                                                <select name="status" class="form-select form-select-sm">
                                                    <option value="all">Semua Status</option>
                                                    <option value="diproses" <?= ($status == 'diproses') ? 'selected' : '' ?>>Potong</option>
                                                    <option value="penjahitan" <?= ($status == 'penjahitan') ? 'selected' : '' ?>>Penjahitan</option>
                                                    <option value="selesai" <?= ($status == 'selesai') ? 'selected' : '' ?>>Selesai</option>
                                                </select>
                                            </div>

                                            <!-- Filter Tanggal -->
                                            <div class="col-md-2 col-6">
                                                <label class="form-label small">Mulai</label>
                                                <input type="date" name="start_date" class="form-control form-control-sm"
                                                    value="<?= htmlspecialchars($start_date ?: $start_date_default) ?>">
                                            </div>

                                            <div class="col-md-2 col-6">
                                                <label class="form-label small">Akhir</label>
                                                <input type="date" name="end_date" class="form-control form-control-sm"
                                                    value="<?= htmlspecialchars($end_date ?: $end_date_default) ?>">
                                            </div>

                                            <!-- Tombol Aksi -->
                                            <div class="col-md-4 d-flex align-items-end gap-2">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="ti ti-filter me-1"></i> Terapkan
                                                </button>
                                                <?php if ($is_filtered): ?>
                                                    <a href="produksi.php" class="btn btn-secondary btn-sm">
                                                        <i class="ti ti-rotate me-1"></i> Reset
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-danger btn-sm" id="btnPrintPDF">
                                                    <i class="ti ti-file-text me-1"></i> PDF
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Mobile Filter Form (in separate tab) -->
                            <div class="d-md-none">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <!-- Filter Info Card -->
                                        <?php if ($is_filtered && !empty($active_filters)): ?>
                                            <div class="alert alert-info py-2 mb-3">
                                                <small>
                                                    <i class="ti ti-filter-check me-1"></i>
                                                    <strong><?= count($all_data) ?> data</strong> ditemukan
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Informasi Filter Aktif -->
                            <?php if ($is_filtered && !empty($active_filters)): ?>
                                <div class="mb-3 filter-info-card">
                                    <div class="alert alert-light border">
                                        <p class="mb-2 small text-muted">
                                            <i class="ti ti-filter me-1"></i> Filter aktif:
                                        </p>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($active_filters as $filter): ?>
                                                <?= $filter ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- DESKTOP TABLE VIEW -->
                            <div class="desktop-table">
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover align-middle">
                                                <thead class="table-light">
                                                    <tr class="text-center">
                                                        <th class="align-middle">Status</th>
                                                        <th class="bg-warning text-white align-middle">Seri</th>
                                                        <th class="bg-warning text-white align-middle">Pemotong</th>
                                                        <th class="bg-warning text-white align-middle">Tgl</th>
                                                        <th class="bg-warning text-white align-middle">Produk</th>
                                                        <th class="bg-warning text-white align-middle">Hasil</th>
                                                        <th class="bg-info text-white align-middle">Kirim</th>
                                                        <th class="bg-info text-white align-middle">Penjahit</th>
                                                        <th class="bg-info text-white align-middle">Jahit</th>
                                                        <th class="bg-info text-white align-middle">Hasil</th>
                                                        <th class="align-middle">Sisa</th>
                                                        <th class="align-middle">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($all_data)): ?>
                                                        <tr>
                                                            <td colspan="12" class="text-center py-4">
                                                                <div class="text-muted">
                                                                    <i class="ti ti-database-off me-2"></i>
                                                                    Tidak ada data produksi
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php
                                                        $no = 1;
                                                        $total_hasil_potong = 0;
                                                        $total_hasil_jahit = 0;
                                                        $total_sisa = 0;
                                                        ?>
                                                        <?php foreach ($all_data as $data): ?>
                                                            <?php
                                                            $total_hasil_potong += $data['total_hasil'] ?? 0;
                                                            $total_hasil_jahit += $data['total_hasil_jahit'] ?? 0;
                                                            $totalHasil = $data['total_hasil'] ?? 0;
                                                            $totalHasilJahit = $data['total_hasil_jahit'] ?? 0;
                                                            $sisa = 0;

                                                            if (!empty($data['total_hasil_jahit']) && $totalHasilJahit > 0) {
                                                                $sisa = $totalHasil - $totalHasilJahit;
                                                                $total_sisa += $sisa;
                                                            }
                                                            ?>
                                                            <tr>
                                                                <td class="text-center">
                                                                    <?php
                                                                    $status = $data['status'];
                                                                    $badge_class = '';
                                                                    $label = '';

                                                                    switch ($status) {
                                                                        case 'selesai':
                                                                            $badge_class = 'badge-status-selesai';
                                                                            $label = 'Selesai';
                                                                            break;
                                                                        case 'diproses':
                                                                            $badge_class = 'badge-status-potong';
                                                                            $label = 'Potong';
                                                                            break;
                                                                        case 'penjahitan':
                                                                            $badge_class = 'badge-status-penjahitan';
                                                                            $label = 'Jahit';
                                                                            break;
                                                                        default:
                                                                            $badge_class = 'badge bg-secondary';
                                                                            $label = '-';
                                                                    }
                                                                    ?>
                                                                    <span class="badge <?= $badge_class ?> p-1 fw-normal">
                                                                        <?= $label ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <strong><?= htmlspecialchars($data['seri']) ?></strong>
                                                                </td>
                                                                <td>
                                                                    <small><?= htmlspecialchars($data['pemotong']) ?></small>
                                                                </td>
                                                                <td>
                                                                    <small><?= dateIndo($data['tanggal']) ?></small>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex flex-column">
                                                                        <small class="text-truncate" style="max-width: 120px;">
                                                                            <?= htmlspecialchars($data['produk']) ?>
                                                                        </small>
                                                                        <span class="badge bg-<?= $data['tipe_produk'] == 'koko' ? 'info' : 'secondary' ?> mobile-badge">
                                                                            <?= strtoupper($data['tipe_produk']) ?>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td class="text-center">
                                                                    <strong><?= $data['total_hasil'] ?></strong>
                                                                    <div class="text-muted small">Pcs</div>
                                                                </td>
                                                                <td>
                                                                    <small><?= !empty($data['tanggal_kirim_jahit']) ? dateIndo($data['tanggal_kirim_jahit']) : '-' ?></small>
                                                                </td>
                                                                <td>
                                                                    <small><?= !empty($data['penjahit']) ? htmlspecialchars($data['penjahit']) : '-' ?></small>
                                                                </td>
                                                                <td>
                                                                    <small><?= !empty($data['tanggal_hasil_jahit']) ? dateIndo($data['tanggal_hasil_jahit']) : '-' ?></small>
                                                                </td>
                                                                <td class="text-center">
                                                                    <?php if (!empty($data['total_hasil_jahit'])): ?>
                                                                        <strong class="text-success"><?= $data['total_hasil_jahit'] ?></strong>
                                                                        <div class="text-muted small">Pcs</div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <?php if (!empty($data['total_hasil_jahit'])): ?>
                                                                        <strong class="<?= $sisa > 0 ? 'text-danger' : 'text-success' ?>">
                                                                            <?= $sisa ?>
                                                                        </strong>
                                                                        <div class="text-muted small">Pcs</div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <a href="detail.php?id=<?= $data['id'] ?>"
                                                                        class="btn btn-sm btn-primary"
                                                                        title="Detail">
                                                                        <i class="ti ti-eye"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                                <?php if (!empty($all_data)): ?>
                                                    <tfoot class="table-light">
                                                        <tr class="text-center fw-bold">
                                                            <td colspan="5" class="text-end">TOTAL:</td>
                                                            <td class="text-primary"><?= $total_hasil_potong ?> Pcs</td>
                                                            <td colspan="3"></td>
                                                            <td class="text-success"><?= $total_hasil_jahit ?> Pcs</td>
                                                            <td class="text-danger"><?= $total_sisa ?> Pcs</td>
                                                            <td></td>
                                                        </tr>
                                                    </tfoot>
                                                <?php endif; ?>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MOBILE CARD VIEW -->
                            <div class="mobile-view">
                                <?php if (empty($all_data)): ?>
                                    <div class="text-center py-5">
                                        <i class="ti ti-database-off text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-3">Tidak ada data produksi</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($all_data as $data): ?>
                                        <?php
                                        // Hitung sisa
                                        $totalHasil = $data['total_hasil'] ?? 0;
                                        $totalHasilJahit = $data['total_hasil_jahit'] ?? 0;
                                        $sisa = 0;

                                        if (!empty($data['total_hasil_jahit']) && $totalHasilJahit > 0) {
                                            $sisa = $totalHasil - $totalHasilJahit;
                                        }

                                        // Status styling
                                        $status = $data['status'];
                                        $badge_class = '';
                                        $label = '';

                                        switch ($status) {
                                            case 'selesai':
                                                $badge_class = 'badge-status-selesai';
                                                $label = 'Selesai';
                                                break;
                                            case 'diproses':
                                                $badge_class = 'badge-status-potong';
                                                $label = 'Potong';
                                                break;
                                            case 'penjahitan':
                                                $badge_class = 'badge-status-penjahitan';
                                                $label = 'Penjahitan';
                                                break;
                                            default:
                                                $badge_class = 'badge bg-secondary';
                                                $label = '-';
                                        }
                                        ?>
                                        <div class="mobile-card">
                                            <div class="mobile-card-header">
                                                <div class="mobile-card-title">
                                                    <i class="ti ti-package me-1"></i>
                                                    <?= htmlspecialchars($data['produk']) ?>
                                                </div>
                                                <div>
                                                    <span class="badge <?= $badge_class ?> mobile-badge">
                                                        <?= $label ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="mobile-card-content">
                                                <div class="mobile-card-row">
                                                    <span class="mobile-label">Seri</span>
                                                    <span class="mobile-value"><?= htmlspecialchars($data['seri']) ?></span>
                                                </div>

                                                <div class="mobile-card-row">
                                                    <span class="mobile-label">Tipe</span>
                                                    <span class="mobile-value">
                                                        <span class="badge bg-<?= $data['tipe_produk'] == 'koko' ? 'info' : 'secondary' ?> mobile-badge">
                                                            <?= strtoupper($data['tipe_produk']) ?>
                                                        </span>
                                                    </span>
                                                </div>

                                                <div class="mobile-card-row">
                                                    <span class="mobile-label">Pemotong</span>
                                                    <span class="mobile-value"><?= htmlspecialchars($data['pemotong']) ?></span>
                                                </div>

                                                <div class="mobile-card-row">
                                                    <span class="mobile-label">Tanggal</span>
                                                    <span class="mobile-value"><?= dateIndo($data['tanggal']) ?></span>
                                                </div>

                                                <div class="mobile-card-row">
                                                    <span class="mobile-label">Hasil Potong</span>
                                                    <span class="mobile-value text-primary">
                                                        <?= $data['total_hasil'] ?> Pcs
                                                    </span>
                                                </div>

                                                <div class="mobile-card-row">
                                                    <span class="mobile-label">Tanggal Kirim</span>
                                                    <span class="mobile-value">
                                                        <?= !empty($data['tanggal_kirim_jahit']) ? dateIndo($data['tanggal_kirim_jahit']) : '-' ?>
                                                    </span>
                                                </div>

                                                <div class="mobile-card-row">
                                                    <span class="mobile-label">Penjahit</span>
                                                    <span class="mobile-value">
                                                        <?= !empty($data['penjahit']) ? htmlspecialchars($data['penjahit']) : '-' ?>
                                                    </span>
                                                </div>

                                                <div class="mobile-card-row">
                                                    <span class="mobile-label">Tanggal Jahit</span>
                                                    <span class="mobile-value">
                                                        <?= !empty($data['tanggal_hasil_jahit']) ? dateIndo($data['tanggal_hasil_jahit']) : '-' ?>
                                                    </span>
                                                </div>

                                                <div class="mobile-card-row">
                                                    <span class="mobile-label">Hasil Jahit</span>
                                                    <span class="mobile-value text-success">
                                                        <?= !empty($data['total_hasil_jahit']) ? $data['total_hasil_jahit'] . ' Pcs' : '-' ?>
                                                    </span>
                                                </div>

                                                <div class="mobile-card-row">
                                                    <span class="mobile-label">Sisa</span>
                                                    <span class="mobile-value <?= $sisa > 0 ? 'text-danger' : 'text-success' ?>">
                                                        <?= !empty($data['total_hasil_jahit']) ? $sisa . ' Pcs' : '-' ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="mobile-actions">
                                                <a href="detail.php?id=<?= $data['id'] ?>"
                                                    class="btn btn-primary">
                                                    <i class="ti ti-eye me-1"></i> Detail
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Filter (Mobile) -->
                <div class="tab-pane fade" id="mobile-filter" role="tabpanel">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="ti ti-filter me-2"></i>Filter Data Produksi
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <!-- Produk -->
                                <div class="col-12">
                                    <label class="form-label">Produk</label>
                                    <select name="id_produk" class="form-select">
                                        <option value="0">Semua Produk</option>
                                        <?php foreach ($produk as $p): ?>
                                            <option value="<?= $p['id_produk'] ?>" <?= ($id_produk == $p['id_produk']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['nama_produk']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Pemotong -->
                                <div class="col-12">
                                    <label class="form-label">Pemotong</label>
                                    <select name="id_pemotong" class="form-select">
                                        <option value="0">Semua Pemotong</option>
                                        <?php foreach ($pemotong as $pm): ?>
                                            <option value="<?= $pm['id_pemotong'] ?>" <?= (isset($_GET['id_pemotong']) && $_GET['id_pemotong'] == $pm['id_pemotong']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pm['nama_pemotong']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Penjahit -->
                                <div class="col-12">
                                    <label class="form-label">Penjahit</label>
                                    <select name="id_penjahit" class="form-select">
                                        <option value="0">Semua Penjahit</option>
                                        <option value="-1" <?= (isset($_GET['id_penjahit']) && $_GET['id_penjahit'] == '-1') ? 'selected' : '' ?>>
                                            Belum Ada Penjahit
                                        </option>
                                        <?php foreach ($penjahit as $pj): ?>
                                            <option value="<?= $pj['id_penjahit'] ?>" <?= (isset($_GET['id_penjahit']) && $_GET['id_penjahit'] == $pj['id_penjahit']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pj['nama_penjahit']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Status -->
                                <div class="col-12">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="all">Semua Status</option>
                                        <option value="diproses" <?= ($status == 'diproses') ? 'selected' : '' ?>>Potong</option>
                                        <option value="penjahitan" <?= ($status == 'penjahitan') ? 'selected' : '' ?>>Penjahitan</option>
                                        <option value="selesai" <?= ($status == 'selesai') ? 'selected' : '' ?>>Selesai</option>
                                    </select>
                                </div>

                                <!-- Tanggal -->
                                <div class="col-12">
                                    <label class="form-label">Tanggal Mulai</label>
                                    <input type="date" name="start_date" class="form-control"
                                        value="<?= htmlspecialchars($start_date ?: $start_date_default) ?>">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Tanggal Akhir</label>
                                    <input type="date" name="end_date" class="form-control"
                                        value="<?= htmlspecialchars($end_date ?: $end_date_default) ?>">
                                </div>

                                <!-- Tombol -->
                                <div class="col-12">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ti ti-filter me-2"></i> Terapkan Filter
                                        </button>
                                        <?php if ($is_filtered): ?>
                                            <a href=".php" class="btn btn-secondary">
                                                <i class="ti ti-rotate me-2"></i> Reset Filter
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-danger" id="btnPrintPDFMobile">
                                            <i class="ti ti-file-text me-2"></i> Cetak PDF
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tab Ringkasan (Mobile) -->
                <div class="tab-pane fade" id="mobile-summary" role="tabpanel">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="ti ti-chart-bar me-2"></i>Ringkasan Produksi
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Total Semua -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="ti ti-database me-2"></i>Total Semua Data
                                </h6>
                                <div class="list-group">
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Total Hasil Potong</span>
                                        <span class="badge bg-primary rounded-pill">
                                            <?= number_format($total_hasil_all) ?> Pcs
                                        </span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Total Hasil Jahit</span>
                                        <span class="badge bg-success rounded-pill">
                                            <?= number_format($total_hasil_jahit_all) ?> Pcs
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Dengan Filter -->
                            <?php if ($is_filtered): ?>
                                <div class="mb-4">
                                    <h6 class="text-warning mb-3">
                                        <i class="ti ti-filter me-2"></i>Dengan Filter Aktif
                                    </h6>
                                    <div class="list-group">
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Total Hasil Potong</span>
                                            <span class="badge bg-warning text-dark rounded-pill">
                                                <?= number_format($total_hasil_filtered) ?> Pcs
                                            </span>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Total Hasil Jahit</span>
                                            <span class="badge bg-info text-dark rounded-pill">
                                                <?= number_format($total_hasil_jahit_filtered) ?> Pcs
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Statistik -->
                            <div class="mb-4">
                                <h6 class="text-info mb-3">
                                    <i class="ti ti-chart-pie me-2"></i>Statistik Data
                                </h6>
                                <div class="list-group">
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Jumlah Data Ditemukan</span>
                                        <span class="badge bg-info rounded-pill">
                                            <?= count($all_data) ?> Data
                                        </span>
                                    </div>
                                    <?php if (!empty($all_data)): ?>
                                        <?php
                                        // Hitung statistik status
                                        $status_counts = [];
                                        foreach ($all_data as $data) {
                                            $status = $data['status'];
                                            $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
                                        }
                                        ?>
                                        <?php foreach ($status_counts as $status => $count): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>Status <?= $status == 'diproses' ? 'Potong' : ($status == 'penjahitan' ? 'Penjahitan' : 'Selesai') ?></span>
                                                <span class="badge <?= $status == 'selesai' ? 'bg-success' : ($status == 'penjahitan' ? 'bg-info' : 'bg-warning') ?> rounded-pill">
                                                    <?= $count ?> Data
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');

            // Fungsi print PDF
            function printPDF() {
                const id_produk = document.querySelector('select[name="id_produk"]')?.value;
                const status = document.querySelector('select[name="status"]')?.value;
                const start_date = document.querySelector('input[name="start_date"]')?.value;
                const end_date = document.querySelector('input[name="end_date"]')?.value;
                const id_pemotong = document.querySelector('select[name="id_pemotong"]')?.value;
                const id_penjahit = document.querySelector('select[name="id_penjahit"]')?.value;

                let url = 'print_laporan_produksi.php?id_produk=' + (id_produk || 0) +
                    '&status=' + (status || 'all') +
                    '&start_date=' + (start_date || '') +
                    '&end_date=' + (end_date || '') +
                    '&id_pemotong=' + (id_pemotong || 0) +
                    '&id_penjahit=' + (id_penjahit || 0);

                window.open(url, '_blank');
            }

            // Tombol Print PDF
            document.getElementById('btnPrintPDF')?.addEventListener('click', printPDF);
            document.getElementById('btnPrintPDFMobile')?.addEventListener('click', printPDF);

            // Set default date range
            function setDefaultDateRange() {
                const startInput = document.querySelector('input[name="start_date"]');
                const endInput = document.querySelector('input[name="end_date"]');

                if (startInput && !startInput.value) {
                    const startDate = new Date();
                    startDate.setDate(startDate.getDate() - 30);
                    startInput.value = startDate.toISOString().split('T')[0];
                }

                if (endInput && !endInput.value) {
                    const endDate = new Date();
                    endInput.value = endDate.toISOString().split('T')[0];
                }
            }

            // Auto-switch to data tab on mobile after filter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('id_produk') || urlParams.has('status') || urlParams.has('start_date') ||
                urlParams.has('end_date') || urlParams.has('id_pemotong') || urlParams.has('id_penjahit')) {

                // On mobile, switch to data tab
                if (window.innerWidth < 768) {
                    const dataTab = new bootstrap.Tab(document.getElementById('mobile-data-tab'));
                    dataTab.show();
                }
            }

            setDefaultDateRange();

            // Responsive behavior
            function handleResize() {
                const isMobile = window.innerWidth < 768;

                if (isMobile) {
                    // Mobile-specific adjustments
                    document.querySelectorAll('.table-responsive').forEach(table => {
                        table.style.fontSize = '0.85rem';
                    });
                } else {
                    // Desktop-specific adjustments
                    document.querySelectorAll('.table-responsive').forEach(table => {
                        table.style.fontSize = '';
                    });
                }
            }

            // Initial call
            handleResize();

            // Listen for resize
            window.addEventListener('resize', handleResize);
        });
    </script>
</body>

</html>