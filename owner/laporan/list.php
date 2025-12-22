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
$id_penjahit = isset($_GET['id_penjahit']) ? $_GET['id_penjahit'] : 0; // Bisa string untuk nilai -1

$start_date_default = date('Y-m-01');
$end_date_default   = date('Y-m-t');

// ============================================================================
// HITUNG TOTAL DATA UNTUK FOOTER TABEL
// ============================================================================

/**
 * 1. HITUNG TOTAL TANPA FILTER (SEMUA DATA)
 * Menghitung total hasil potong dan hasil jahit dari semua data tanpa filter
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
 * Menghitung total hasil potong dan hasil jahit dengan filter yang diterapkan user
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
 * Query utama untuk menampilkan data di tabel dengan filter yang diterapkan
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
 * Menghitung upah pemotong, upah penjahit, dan total upah
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
        'seri_numeric' => intval(preg_replace('/[^0-9]/', '', $prod['seri'])), // Ekstrak angka saja
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

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Produksi</title>
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
        }

        .table td {
            font-size: 0.8rem;
        }

        .tarif-info {
            font-size: 0.7rem;
            color: #6c757d;
        }

        .total-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }

        .total-info h5 {
            color: #0d6efd;
            margin-bottom: 15px;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 5px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
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
            padding: 10px;
            border-radius: 3px;
            margin-top: 10px;
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

    <!-- [ Header Topbar ] start -->
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
            <div class="row">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Master Data Produksi</h2>
                    <div class="btn-group">
                        <div>
                            <a href="new.php" class="btn btn-success">
                                <i class="ti ti-circle-plus"></i> Tambah Produksi
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Filter Form -->
                    <div class="col-md-8">



                        <form method="GET" class="row g-3 mb-3">
                            <div class="col-md-2">
                                <label class="form-label">Filter Produk</label>
                                <select name="id_produk" class="form-select">
                                    <option value="0">Semua Produk</option>
                                    <?php foreach ($produk as $p): ?>
                                        <option value="<?= $p['id_produk'] ?>" <?= ($id_produk == $p['id_produk']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nama_produk']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Tambah filter pemotong -->
                            <div class="col-md-2">
                                <label class="form-label">Filter Pemotong</label>
                                <select name="id_pemotong" class="form-select">
                                    <option value="0">Semua Pemotong</option>
                                    <?php foreach ($pemotong as $pm): ?>
                                        <option value="<?= $pm['id_pemotong'] ?>" <?= (isset($_GET['id_pemotong']) && $_GET['id_pemotong'] == $pm['id_pemotong']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($pm['nama_pemotong']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Tambah filter penjahit -->
                            <div class="col-md-2">
                                <label class="form-label">Filter Penjahit</label>
                                <select name="id_penjahit" class="form-select">
                                    <option value="0">Semua Penjahit</option>
                                    <option value="-1" <?= (isset($_GET['id_penjahit']) && $_GET['id_penjahit'] == '-1') ? 'selected' : '' ?>>Belum Ada Penjahit</option>
                                    <?php foreach ($penjahit as $pj): ?>
                                        <option value="<?= $pj['id_penjahit'] ?>" <?= (isset($_GET['id_penjahit']) && $_GET['id_penjahit'] == $pj['id_penjahit']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($pj['nama_penjahit']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Filter Status</label>
                                <select name="status" class="form-select">
                                    <option value="all" <?= ($status == 'all') ? 'selected' : '' ?>>Semua Status</option>
                                    <option value="diproses" <?= ($status == 'diproses') ? 'selected' : '' ?>>Potong</option>
                                    <option value="penjahitan" <?= ($status == 'penjahitan') ? 'selected' : '' ?>>Penjahitan</option>
                                    <option value="selesai" <?= ($status == 'selesai') ? 'selected' : '' ?>>Selesai</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Tanggal Mulai</label>
                                <input type="date" name="start_date" class="form-control"
                                    value="<?= htmlspecialchars($start_date ?: $start_date_default) ?>">
                                <small class="text-muted">Bulan/Tanggal/Tahun</small>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Tanggal Akhir</label>
                                <input type="date" name="end_date" class="form-control"
                                    value="<?= htmlspecialchars($end_date ?: $end_date_default) ?>">
                                <small class="text-muted">Bulan/Tanggal/Tahun</small>
                            </div>

                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="ti ti-filter"></i> Filter
                                </button>
                                <?php
                                // Cek apakah ada filter yang aktif
                                $is_filtered = $id_produk > 0 || $id_pemotong > 0 || $id_penjahit != 0 ||
                                    $status != 'all' || !empty($start_date) || !empty($end_date);
                                ?>

                                <?php if ($is_filtered): ?>
                                    <a href="list.php" class="btn btn-secondary me-2">
                                        <i class="ti ti-rotate"></i> Reset
                                    </a>
                                <?php endif; ?>

                                <button type="button" class="btn btn-danger" id="btnPrintPDF">
                                    <i class="ti ti-file-text"></i> Print PDF
                                </button>
                            </div>
                        </form>

                        <!-- ============================================
                        KARTU INFORMASI FILTER YANG DIGUNAKAN
                        ============================================ -->
                        <?php if ($is_filtered): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card border-primary">
                                        <div class="card-header bg-primary text-white py-2">
                                            <h6 class="mb-0 text-white">
                                                <i class="ti ti-filter-check"></i> Filter Aktif
                                            </h6>
                                        </div>
                                        <div class="card-body py-2">
                                            <div class="row g-2">
                                                <?php
                                                // Fungsi untuk menampilkan nilai filter dengan label yang sesuai
                                                function getFilterLabel($key, $value)
                                                {
                                                    global $produk, $pemotong, $penjahit;

                                                    switch ($key) {
                                                        case 'id_produk':
                                                            if ($value == 0) return null;
                                                            foreach ($produk as $p) {
                                                                if ($p['id_produk'] == $value) {
                                                                    return '<span class="badge bg-primary">Produk: ' . htmlspecialchars($p['nama_produk']) . '</span>';
                                                                }
                                                            }
                                                            break;

                                                        case 'id_pemotong':
                                                            if ($value == 0) return null;
                                                            foreach ($pemotong as $pm) {
                                                                if ($pm['id_pemotong'] == $value) {
                                                                    return '<span class="badge bg-warning text-dark">Pemotong: ' . htmlspecialchars($pm['nama_pemotong']) . '</span>';
                                                                }
                                                            }
                                                            break;

                                                        case 'id_penjahit':
                                                            if ($value == 0) return null;
                                                            if ($value == '-1') {
                                                                return '<span class="badge bg-secondary">Penjahit: Belum Ada</span>';
                                                            }
                                                            foreach ($penjahit as $pj) {
                                                                if ($pj['id_penjahit'] == $value) {
                                                                    return '<span class="badge bg-info text-dark">Penjahit: ' . htmlspecialchars($pj['nama_penjahit']) . '</span>';
                                                                }
                                                            }
                                                            break;

                                                        case 'status':
                                                            if ($value == 'all') return null;
                                                            $status_labels = [
                                                                'diproses' => 'Potong',
                                                                'penjahitan' => 'Penjahitan',
                                                                'selesai' => 'Selesai'
                                                            ];
                                                            return '<span class="badge bg-' .
                                                                ($value == 'selesai' ? 'success' : ($value == 'penjahitan' ? 'info' : 'warning')) .
                                                                '">Status: ' . $status_labels[$value] . '</span>';

                                                        case 'start_date':
                                                            if (empty($value)) return null;
                                                            return '<span class="badge bg-secondary">Mulai: ' . dateIndo($value) . '</span>';

                                                        case 'end_date':
                                                            if (empty($value)) return null;
                                                            return '<span class="badge bg-secondary">Akhir: ' . dateIndo($value) . '</span>';
                                                    }
                                                    return null;
                                                }
                                                ?>

                                                <?php
                                                // Array filter yang akan ditampilkan
                                                $filters_to_display = [
                                                    'id_produk' => $id_produk,
                                                    'id_pemotong' => $id_pemotong,
                                                    'id_penjahit' => $id_penjahit,
                                                    'status' => $status,
                                                    'start_date' => $start_date,
                                                    'end_date' => $end_date
                                                ];

                                                $active_filters = [];

                                                // Loop melalui semua filter
                                                foreach ($filters_to_display as $key => $value) {
                                                    $label = getFilterLabel($key, $value);
                                                    if ($label) {
                                                        $active_filters[] = $label;
                                                    }
                                                }
                                                ?>

                                                <?php if (!empty($active_filters)): ?>
                                                    <div class="col-12">
                                                        <p class="mb-2 small text-muted">
                                                            <i class="ti ti-info-circle"></i> Menampilkan data dengan filter:
                                                        </p>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <?php foreach ($active_filters as $filter): ?>
                                                                <?= $filter ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>

                                                    <!-- Informasi Jumlah Data yang Difilter -->
                                                    <div class="col-12 mt-2">
                                                        <p class="mb-0 small">
                                                            <i class="ti ti-database"></i>
                                                            <strong><?= count($all_data) ?> data</strong> ditemukan dengan filter ini
                                                            (dari total <?= $total_hasil_all ?> Pcs hasil potong)
                                                        </p>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="col-12">
                                                        <p class="mb-0 text-muted">
                                                            <i class="ti ti-info-circle"></i> Tidak ada filter yang aktif
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ============================================
                    BAGIAN TOTAL INFORMASI
                    ============================================ -->
                    <div class="col-md-4 mb-4">
                        <div class="total-info">
                            <h5><i class="ti ti-chart-bar"></i> Ringkasan Produksi</h5>

                            <!-- Total Semua Data (Tanpa Filter) -->
                            <div class="total-row">
                                <span class="total-label">Total Semua Hasil Potong:</span>
                                <span class="total-value">
                                    <?= number_format($total_hasil_all) ?> Pcs
                                </span>
                            </div>

                            <div class="total-row">
                                <span class="total-label">Total Semua Hasil Jahit:</span>
                                <span class="total-value">
                                    <?= number_format($total_hasil_jahit_all) ?> Pcs
                                </span>
                            </div>

                            <!-- Total Dengan Filter (Jika Ada Filter) -->
                            <?php if ($is_filtered): ?>
                                <div class="total-filtered">
                                    <h6><i class="ti ti-filter"></i> Hasil Setelah Filter:</h6>
                                    <div class="total-row">
                                        <span class="total-label">Total Hasil Potong (Filter):</span>
                                        <span class="total-value text-primary">
                                            <?= number_format($total_hasil_filtered) ?> Pcs
                                        </span>
                                    </div>

                                    <div class="total-row">
                                        <span class="total-label">Total Hasil Jahit (Filter):</span>
                                        <span class="total-value text-primary">
                                            <?= number_format($total_hasil_jahit_filtered) ?> Pcs
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>



                <div class="card p-3">
                    <!-- Tampilkan pesan error atau success -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['success']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($error_modal)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error_modal) ?>
                            <button type="button"
                                class="btn-close"
                                data-bs-dismiss="alert"
                                aria-label="Close"></button>
                        </div>
                    <?php endif; ?>


                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th class="align-middle">Status</th>
                                    <th class="bg-warning text-white align-middle">Seri</th>
                                    <th class="bg-warning text-white align-middle">Pemotong</th>
                                    <th class="bg-warning text-white align-middle">Tgl Potong</th>
                                    <th class="bg-warning text-white align-middle">Produk</th>
                                    <th class="bg-warning text-white align-middle">Hasil Potong</th>
                                    <th class="bg-info text-white align-middle">Tgl Kirim Jahit</th>
                                    <th class="bg-info text-white align-middle">Penjahit</th>
                                    <th class="bg-info text-white align-middle">Tgl Jahit</th>
                                    <th class="bg-info text-white align-middle">Hasil Jahit</th>
                                    <th class="align-middle">Sisa</th>
                                    <th class="align-middle">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (empty($all_data)): ?>
                                    <tr>
                                        <td colspan="13" class="text-center">Tidak ada data produksi</td>
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
                                        // Hitung total untuk footer
                                        $total_hasil_potong += $data['total_hasil'] ?? 0;
                                        $total_hasil_jahit += $data['total_hasil_jahit'] ?? 0;

                                        // Hitung sisa
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
                                                $status = $data['status']; // ambil status

                                                // Tentukan warna badge
                                                switch ($status) {
                                                    case 'selesai':
                                                        $badge = 'success';
                                                        $label = 'Selesai';
                                                        break;
                                                    case 'diproses':
                                                        $badge = 'warning';
                                                        $label = 'Potong'; // ubah tampilan
                                                        break;
                                                    case 'penjahitan':
                                                        $badge = 'info';
                                                        $label = 'Penjahitan';
                                                        break;
                                                    case '-':
                                                    default:
                                                        $badge = 'secondary';
                                                        $label = '-';
                                                        break;
                                                }
                                                ?>

                                                <span class="badge bg-<?= $badge ?> p-1 fw-normal">
                                                    <?= $label ?>
                                                </span>

                                            </td>
                                            <td class="text-center"><?= htmlspecialchars($data['seri']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($data['pemotong']) ?>
                                            </td>
                                            <td><?= dateIndo($data['tanggal']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($data['produk']) ?>
                                                <br>
                                                <small class="text-muted">
                                                    <span class="badge bg-<?= $data['tipe_produk'] == 'koko' ? 'info' : 'secondary' ?>">
                                                        <?= strtoupper($data['tipe_produk']) ?>
                                                    </span>
                                                </small>
                                            </td>
                                            <td class="text-center"><?= $data['total_hasil'] ?> Pcs</td>
                                            <td>
                                                <?= !empty($data['tanggal_kirim_jahit']) ? dateIndo($data['tanggal_kirim_jahit']) : '-' ?>
                                            </td>
                                            <td class="">
                                                <?php if (!empty($data['penjahit'])): ?>
                                                    <?= htmlspecialchars($data['penjahit']) ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= !empty($data['tanggal_hasil_jahit']) ? dateIndo($data['tanggal_hasil_jahit']) : '-' ?>
                                            </td>
                                            <td class="text-center">
                                                <?= !empty($data['total_hasil_jahit']) ? $data['total_hasil_jahit'] . ' Pcs' : '-' ?>
                                            </td>
                                            <td class="text-center">
                                                <?= !empty($data['total_hasil_jahit']) ? $sisa . ' Pcs' : '-' ?>
                                            </td>

                                            <td class="text-center">
                                                <div class="btn-group gap-1 text-center">
                                                    <!-- Tombol Detail -->
                                                    <a href="detail.php?id=<?= $data['id'] ?>" class="btn btn-sm btn-primary" title="Detail">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>

                            <!-- TABLE FOOTER UNTUK TOTAL -->
                            <?php if (!empty($all_data)): ?>
                                <tfoot class="table-light">
                                    <tr class="text-center fw-bold">
                                        <td colspan="5" class="text-end">TOTAL:</td>
                                        <td><?= $total_hasil_potong ?> Pcs</td>
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
    </div>

    <!-- Modal Input Tanggal Kirim Jahit (Modal Pertama) -->
    <div class="modal fade" id="modalTanggalPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitleTanggal">Input Tanggal Kirim Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formTanggalPenjahitan">
                    <div class="modal-body">
                        <?php if (isset($error_modal)): ?>
                            <div class="alert alert-danger"><?= $error_modal ?></div>
                        <?php endif; ?>

                        <input type="hidden" name="id_hasil_potong_fix" id="modal_tanggal_id_hasil_potong">
                        <input type="hidden" id="modal_tanggal_tanggal_potong">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Produk</label>
                                <input type="text" class="form-control" id="modal_tanggal_produk" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Seri</label>
                                <input type="text" class="form-control" id="modal_tanggal_seri" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Potong</label>
                            <input type="text" class="form-control" id="modal_tanggal_total_potong" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Penjahit</label>
                            <select name="id_penjahit" class="form-control" id="modal_tanggal_penjahit" required>
                                <option value="">-- Pilih Penjahit --</option>
                                <?php foreach ($penjahit as $j): ?>
                                    <option value="<?= $j['id_penjahit'] ?>">
                                        <?= htmlspecialchars($j['nama_penjahit']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Pilih penjahit yang akan mengerjakan</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Kirim Jahit <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_kirim_jahit" class="form-control"
                                id="modal_tanggal_kirim_jahit" required value="<?= date('Y-m-d') ?>">
                            <small class="text-muted">Tanggal ketika bahan dikirim ke penjahit</small>
                        </div>

                        <div class="alert alert-info">
                            <i class="ti ti-info-circle"></i> Data hasil jahit dapat diinput nanti setelah penjahitan selesai.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="simpan_tanggal_kirim" class="btn btn-primary">Simpan Tanggal Kirim</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Input Hasil Jahit (Modal Kedua) -->
    <div class="modal fade" id="modalHasilPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitleHasil">Input Hasil Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formHasilPenjahitan">
                    <div class="modal-body">
                        <?php if (isset($error_modal)): ?>
                            <div class="alert alert-danger"><?= $error_modal ?></div>
                        <?php endif; ?>

                        <input type="hidden" name="id_hasil_potong_fix" id="modal_hasil_id_hasil_potong">
                        <input type="hidden" id="modal_hasil_tanggal_potong">
                        <input type="hidden" id="modal_hasil_penjahit">
                        <input type="hidden" id="modal_hasil_tanggal_kirim">
                        <input type="hidden" id="modal_hasil_existing" value="0">

                        <!-- Input untuk total upah -->
                        <input type="hidden" name="total_upah_penjahit" id="total_upah_penjahit_hidden">
                        <input type="hidden" name="upah_per_potongan_penjahit" id="upah_per_potongan_penjahit_hidden">

                        <!-- Alert jika sudah ada data -->
                        <div class="alert alert-info d-none" id="modalHasilExistAlert">
                            <i class="ti ti-info-circle"></i>
                            <strong>Perhatian:</strong> Data hasil jahit sudah ada sebelumnya.
                            <div id="modalHasilExistDetail"></div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Produk</label>
                                <input type="text" class="form-control" id="modal_hasil_produk" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Seri</label>
                                <input type="text" class="form-control" id="modal_hasil_seri" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Potong</label>
                            <input type="text" class="form-control" id="modal_hasil_total_potong" readonly>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Penjahit</label>
                                <input type="text" class="form-control" id="modal_hasil_nama_penjahit" readonly>
                            </div>

                            <div hidden class="col-md-6">
                                <label class="form-label">Tanggal Kirim</label>
                                <input type="text" class="form-control" id="modal_hasil_tanggal_kirim_text" readonly>
                            </div>
                        </div>

                        <hr>
                        <div class="row mb-3">

                            <div class=" col-md-6">
                                <label class="form-label">Total Hasil Jahit (Pcs) <span class="text-danger">*</span></label>
                                <input type="number" name="total_hasil_jahit" class="form-control"
                                    min="1" max="" id="modal_hasil_total_jahit" required>
                                <small class="text-muted">Maksimal: <span id="modal_hasil_max_total">0</span> Pcs</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tanggal Hasil Jahit <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_hasil_jahit" class="form-control"
                                    id="modal_hasil_tanggal_jahit" required>
                                <small class="text-muted">bulan/tanggal/tahun</small>
                            </div>

                        </div>


                        <!-- BAGIAN BARU: Input Upah Penjahit -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Upah Penjahit</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Upah per Potongan</label>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" name="upah_per_potongan_manual"
                                                class="form-control" id="upah_per_potongan_manual"
                                                min="0" step="100" value=""
                                                placeholder="Input manual upah">
                                        </div>
                                        <small class="text-muted">Pilih tarif dari dropdown:</small>
                                        <select class="form-control mt-1" id="tarif_penjahit_dropdown">
                                            <option value="">-- Pilih Tarif Standar --</option>
                                            <?php
                                            // Query untuk mendapatkan tarif penjahitan
                                            $tarif_penjahit = query("SELECT * FROM tarif_upah WHERE jenis_tarif = 'penjahitan' ORDER BY berlaku_sejak DESC");
                                            foreach ($tarif_penjahit as $tarif):
                                            ?>
                                                <option value="<?= $tarif['tarif_per_unit'] ?>"
                                                    data-tanggal="<?= $tarif['berlaku_sejak'] ?>">
                                                    Rp <?= number_format($tarif['tarif_per_unit']) ?> sejak <?= dateIndo($tarif['berlaku_sejak']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Total Upah</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="text" class="form-control"
                                                id="total_upah_penjahit_display" readonly>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <span id="detail_upah_perhitungan">
                                                    0 potongan × Rp 0
                                                </span>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- <div class="alert alert-info mt-3 mb-0">
                                    <i class="ti ti-info-circle"></i>
                                    <small>Tarif standar akan otomatis terpilih berdasarkan tanggal hasil jahit.
                                        Anda dapat mengubahnya dengan memilih tarif lain atau input manual.</small>
                                </div> -->
                            </div>


                        </div>
                        <div class="alert alert-warning" id="modal_hasil_alert">
                            <i class="ti ti-alert-triangle"></i>
                            <span id="modal_hasil_alert_text">
                                Pastikan jumlah hasil jahit sesuai dengan kondisi fisik.
                            </span>
                            <div id="modal_hasil_override_info" class="d-none mt-2">
                                <i class="ti ti-alert-triangle text-danger"></i>
                                <strong class="text-danger">Anda akan mengupdate data yang sudah ada!</strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Tutup
                        </button>
                        <button type="submit" name="simpan_hasil_jahit" class="btn btn-success" id="modalHasilSubmitBtn">
                            Simpan Hasil Jahit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Batal Hasil Jahit -->
    <div class="modal fade" id="modalBatalPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Batal Hasil Jahit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formBatalPenjahitan">
                    <div class="modal-body">
                        <?php if (isset($error_modal)): ?>
                            <div class="alert alert-danger"><?= $error_modal ?></div>
                        <?php endif; ?>

                        <input type="hidden" name="id_hasil_potong_fix" id="batal_modal_id">
                        <input type="hidden" name="tipe_produk" id="batal_modal_tipe_produk">

                        <p>Apakah Anda yakin ingin membatalkan <strong>hasil jahit</strong> untuk:</p>
                        <p><strong>Produk:</strong> <span id="batal_modal_produk"></span></p>
                        <p><strong>Seri:</strong> <span id="batal_modal_seri"></span></p>
                        <p><strong>Tipe Produk:</strong> <span id="batal_modal_tipe_text" class="badge"></span></p>

                        <div class="alert alert-info">
                            <i class="ti ti-info-circle"></i>
                            <strong>Catatan:</strong><br>
                            1. Hanya data hasil jahit yang akan dihapus<br>
                            2. Data penjahit dan tanggal kirim tetap tersimpan<br>
                            3. Status akan kembali ke "Penjahitan"<br>
                            4. <span id="batal_modal_keterangan_stok"></span>
                        </div>
                        <p class="text-danger"><strong>Tindakan ini tidak dapat dikembalikan!</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="batal_penjahitan" class="btn btn-danger">Ya, Batalkan Hasil Jahit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Penjahit dan Tanggal Kirim -->
    <div class="modal fade" id="modalHapusPenjahit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus Data Penjahit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formHapusPenjahit">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="hapus_penjahit_id">
                        <p>Apakah Anda yakin ingin menghapus <strong>data penjahit dan tanggal kirim</strong> untuk:</p>
                        <p><strong>Produk:</strong> <span id="hapus_penjahit_produk"></span></p>
                        <p><strong>Seri:</strong> <span id="hapus_penjahit_seri"></span></p>
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-triangle"></i>
                            <strong>Peringatan:</strong><br>
                            1. Semua data penjahit dan tanggal kirim akan dihapus<br>
                            2. Status akan kembali ke "Potong"<br>
                            3. Jika ada hasil jahit, akan dihapus juga
                        </div>
                        <p class="text-danger"><strong>Tindakan ini tidak dapat dikembalikan!</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="hapus_penjahit" class="btn btn-danger">Ya, Hapus Penjahit</button>
                    </div>
                </form>
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

            // Inisialisasi semua modal terlebih dahulu
            const modalTanggalPenjahitan = new bootstrap.Modal(document.getElementById('modalTanggalPenjahitan'));
            const modalHasilPenjahitan = new bootstrap.Modal(document.getElementById('modalHasilPenjahitan'));
            const modalBatalPenjahitan = new bootstrap.Modal(document.getElementById('modalBatalPenjahitan'));
            const modalHapusPenjahit = new bootstrap.Modal(document.getElementById('modalHapusPenjahit'));

            function initModalHasilJahitEvents() {
                const modal = document.getElementById('modalHasilPenjahitan');
                if (!modal) return;

                // Event untuk input total hasil jahit
                const totalJahitInput = modal.querySelector('#modal_hasil_total_jahit');
                const upahManualInput = modal.querySelector('#upah_per_potongan_manual');
                const tarifDropdown = modal.querySelector('#tarif_penjahit_dropdown');
                const tanggalJahitInput = modal.querySelector('#modal_hasil_tanggal_jahit');
                const totalUpahDisplay = modal.querySelector('#total_upah_penjahit_display');
                const detailUpahSpan = modal.querySelector('#detail_upah_perhitungan');
                const totalUpahHidden = modal.querySelector('#total_upah_penjahit_hidden');
                const upahPerPotonganHidden = modal.querySelector('#upah_per_potongan_penjahit_hidden');

                // Fungsi untuk menghitung total upah
                function hitungTotalUpahPenjahit() {
                    const totalHasil = parseInt(totalJahitInput.value) || 0;
                    const upahPerPotongan = parseFloat(upahManualInput.value) || 0;

                    const totalUpah = totalHasil * upahPerPotongan;

                    // Update tampilan
                    totalUpahDisplay.value = formatRupiah(totalUpah);
                    totalUpahHidden.value = totalUpah;
                    upahPerPotonganHidden.value = upahPerPotongan;

                    // Update detail perhitungan
                    detailUpahSpan.innerHTML = `${totalHasil} potongan × Rp ${formatNumber(upahPerPotongan)}`;
                }

                // Format Rupiah
                function formatRupiah(angka) {
                    return 'Rp ' + formatNumber(angka);
                }

                function formatNumber(angka) {
                    return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                }

                // Event listener untuk input total hasil
                totalJahitInput.addEventListener('input', function() {
                    hitungTotalUpahPenjahit();
                });

                // Event listener untuk input upah manual
                upahManualInput.addEventListener('input', function() {
                    // Kosongkan dropdown jika user input manual
                    tarifDropdown.value = '';
                    hitungTotalUpahPenjahit();
                });

                // Event listener untuk dropdown tarif standar
                tarifDropdown.addEventListener('change', function() {
                    const selectedTarif = parseFloat(this.value) || 0;
                    if (selectedTarif > 0) {
                        upahManualInput.value = selectedTarif;
                        hitungTotalUpahPenjahit();
                    }
                });

                // Event listener untuk input manual upah (override dropdown)
                upahManualInput.addEventListener('focus', function() {
                    // Kosongkan dropdown jika user ingin input manual
                    tarifDropdown.value = '';
                });

                // Event untuk tanggal hasil jahit (auto-select tarif berdasarkan tanggal)
                tanggalJahitInput.addEventListener('change', function() {
                    const selectedDate = this.value;
                    if (selectedDate) {
                        // Cari tarif yang berlaku pada tanggal tersebut
                        const options = tarifDropdown.options;
                        let found = false;
                        for (let i = 0; i < options.length; i++) {
                            const option = options[i];
                            const tanggalBerlaku = option.dataset.tanggal;
                            if (tanggalBerlaku && selectedDate >= tanggalBerlaku) {
                                tarifDropdown.value = option.value;
                                upahManualInput.value = option.value;
                                hitungTotalUpahPenjahit();
                                found = true;
                                break;
                            }
                        }

                        // Jika tidak ditemukan, gunakan tarif pertama setelah opsi kosong
                        if (!found && options.length > 1) {
                            tarifDropdown.value = options[1].value;
                            upahManualInput.value = options[1].value;
                            hitungTotalUpahPenjahit();
                        }
                    }
                });

                // Validasi sebelum submit form
                const formHasilPenjahitan = modal.querySelector('#formHasilPenjahitan');
                formHasilPenjahitan.addEventListener('submit', function(e) {
                    // Validasi standar
                    const totalJahit = parseInt(totalJahitInput.value) || 0;
                    const maxJahit = parseInt(totalJahitInput.max) || 0;
                    const tanggalHasilJahit = tanggalJahitInput.value;
                    const totalUpahValue = parseFloat(totalUpahHidden.value) || 0;
                    const upahPerPotonganValue = parseFloat(upahManualInput.value) || 0;

                    let errorMessages = [];

                    if (!tanggalHasilJahit) {
                        errorMessages.push('Tanggal hasil jahit harus diisi');
                    }

                    if (totalJahit <= 0) {
                        errorMessages.push('Total hasil jahit harus lebih dari 0');
                    }

                    if (totalJahit > maxJahit) {
                        errorMessages.push(`Total hasil jahit tidak boleh melebihi ${maxJahit} Pcs`);
                    }

                    if (totalUpahValue <= 0) {
                        errorMessages.push('Total upah harus lebih dari 0!');
                    }

                    // Validasi perhitungan upah
                    const calculated = totalJahit * upahPerPotonganValue;
                    if (Math.abs(calculated - totalUpahValue) > 1) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Perhitungan Upah Tidak Sesuai',
                            html: `Perhitungan upah tidak sesuai!<br>
                      Total Hasil: ${totalJahit} pcs<br>
                      Upah per Potongan: Rp ${formatNumber(upahPerPotonganValue)}<br>
                      Total Seharusnya: Rp ${formatNumber(calculated)}<br><br>
                      Apakah ingin menggunakan perhitungan ini?`,
                            showCancelButton: true,
                            confirmButtonText: 'Ya, Gunakan',
                            cancelButtonText: 'Perbaiki Manual'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Update nilai
                                totalUpahHidden.value = calculated;
                                totalUpahDisplay.value = formatRupiah(calculated);
                                formHasilPenjahitan.submit();
                            }
                        });
                        return;
                    }

                    if (errorMessages.length > 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validasi Error',
                            html: '<div class="text-start">' +
                                errorMessages.map(msg => `<p>• ${msg}</p>`).join('') +
                                '</div>',
                            confirmButtonText: 'Oke'
                        });
                    }
                });

                // Set default date untuk tanggal hasil jahit
                if (!tanggalJahitInput.value) {
                    tanggalJahitInput.value = '<?= date("Y-m-d") ?>';
                    // Trigger change event untuk set tarif default
                    tanggalJahitInput.dispatchEvent(new Event('change'));
                }
            }

            // --- EVENT LISTENER UNTUK TOMBOL INPUT TANGGAL KIRIM ---
            document.addEventListener('click', function(e) {
                // Tombol Input Tanggal Kirim (Status: Diproses)
                if (e.target.closest('.btn-input-tanggal-penjahitan')) {
                    e.preventDefault();
                    console.log('Tombol Input Tanggal Kirim diklik');

                    const button = e.target.closest('.btn-input-tanggal-penjahitan');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const tanggalPotong = button.getAttribute('data-tanggal-potong');

                    console.log('Data:', {
                        id,
                        produk,
                        seri
                    });

                    // Isi data ke modal
                    document.getElementById('modalTitleTanggal').textContent = 'Input Tanggal Kirim Penjahitan';
                    document.getElementById('modal_tanggal_id_hasil_potong').value = id;
                    document.getElementById('modal_tanggal_produk').value = produk;
                    document.getElementById('modal_tanggal_seri').value = seri;
                    document.getElementById('modal_tanggal_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_tanggal_kirim_jahit').value = '<?= date('Y-m-d') ?>';

                    // Reset select penjahit
                    document.getElementById('modal_tanggal_penjahit').selectedIndex = 0;

                    // Tampilkan modal
                    modalTanggalPenjahitan.show();
                }

                // Tombol Input Hasil Jahit (Status: Penjahitan/Selesai)
                if (e.target.closest('.btn-input-hasil-penjahitan')) {
                    e.preventDefault();
                    console.log('Tombol Input Hasil Jahit diklik');

                    const button = e.target.closest('.btn-input-hasil-penjahitan');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const namaPenjahit = button.getAttribute('data-nama-penjahit');
                    const tanggalKirim = button.getAttribute('data-tanggal-kirim');

                    // Isi data ke modal hasil
                    document.getElementById('modal_hasil_id_hasil_potong').value = id;
                    document.getElementById('modal_hasil_produk').value = produk;
                    document.getElementById('modal_hasil_seri').value = seri;
                    document.getElementById('modal_hasil_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_hasil_nama_penjahit').value = namaPenjahit || '-';
                    document.getElementById('modal_hasil_tanggal_kirim_text').value = formatDate(tanggalKirim) || '-';
                    document.getElementById('modal_hasil_total_jahit').value = totalPotong;
                    document.getElementById('modal_hasil_total_jahit').max = totalPotong;
                    document.getElementById('modal_hasil_max_total').textContent = totalPotong;
                    document.getElementById('modal_hasil_tanggal_jahit').value = '<?= date('Y-m-d') ?>';

                    // Reset input upah
                    document.getElementById('upah_per_potongan_manual').value = '';
                    document.getElementById('tarif_penjahit_dropdown').selectedIndex = 0;
                    document.getElementById('total_upah_penjahit_display').value = '';
                    document.getElementById('detail_upah_perhitungan').textContent = '0 potongan × Rp 0';

                    // Reset alert existing
                    document.getElementById('modalHasilExistAlert').classList.add('d-none');
                    document.getElementById('modal_hasil_override_info').classList.add('d-none');
                    document.getElementById('modalHasilSubmitBtn').textContent = 'Simpan Hasil Jahit';

                    // Inisialisasi event listener untuk modal
                    setTimeout(() => {
                        initModalHasilJahitEvents();

                        // Trigger change event untuk tanggal (agar tarif default terpilih)
                        const tanggalInput = document.getElementById('modal_hasil_tanggal_jahit');
                        if (tanggalInput) {
                            tanggalInput.dispatchEvent(new Event('change'));
                        }
                    }, 100);

                    // Tampilkan modal
                    modalHasilPenjahitan.show();
                }

                // Tombol Edit Tanggal Kirim
                if (e.target.closest('.btn-edit-tanggal-penjahitan')) {
                    e.preventDefault();
                    console.log('Tombol Edit Tanggal Kirim diklik');

                    const button = e.target.closest('.btn-edit-tanggal-penjahitan');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const penjahit = button.getAttribute('data-penjahit');
                    const tanggalKirim = button.getAttribute('data-tanggal-kirim');

                    // Isi data ke modal
                    document.getElementById('modalTitleTanggal').textContent = 'Edit Tanggal Kirim Penjahitan';
                    document.getElementById('modal_tanggal_id_hasil_potong').value = id;
                    document.getElementById('modal_tanggal_produk').value = produk;
                    document.getElementById('modal_tanggal_seri').value = seri;
                    document.getElementById('modal_tanggal_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_tanggal_penjahit').value = penjahit || '';
                    document.getElementById('modal_tanggal_kirim_jahit').value = formatDate(tanggalKirim) || '';

                    // Tampilkan modal
                    modalTanggalPenjahitan.show();
                }

                // Tombol Batal Hasil Jahit
                if (e.target.closest('.btn-batal-hasil-jahit')) {
                    e.preventDefault();
                    console.log('Tombol Batal Hasil Jahit diklik');

                    const button = e.target.closest('.btn-batal-hasil-jahit');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const hasilJahit = button.getAttribute('data-hasil-jahit');
                    const tipeProduk = button.getAttribute('data-tipe-produk') || 'mukena';

                    // Isi data ke modal batal
                    document.getElementById('batal_modal_id').value = id;
                    document.getElementById('batal_modal_tipe_produk').value = tipeProduk;
                    document.getElementById('batal_modal_produk').textContent = produk;
                    document.getElementById('batal_modal_seri').textContent = seri;

                    // Tampilkan tipe produk
                    const tipeBadge = document.getElementById('batal_modal_tipe_text');
                    tipeBadge.textContent = tipeProduk.toUpperCase();
                    tipeBadge.className = 'badge bg-' + (tipeProduk === 'koko' ? 'info' : 'secondary');

                    // Tampilkan keterangan
                    const keteranganStok = document.getElementById('batal_modal_keterangan_stok');
                    if (tipeProduk === 'mukena') {
                        keteranganStok.textContent = `Stok produk akan dikurangi ${hasilJahit || 0} Pcs`;
                    } else {
                        keteranganStok.textContent = `Data finishing akan dihapus (${hasilJahit || 0} Pcs)`;
                    }

                    // Tampilkan modal
                    modalBatalPenjahitan.show();
                }

                // Tombol Hapus Penjahit
                if (e.target.closest('.btn-hapus-penjahit')) {
                    e.preventDefault();
                    console.log('Tombol Hapus Penjahit diklik');

                    const button = e.target.closest('.btn-hapus-penjahit');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const penjahit = button.getAttribute('data-penjahit');

                    // Isi data ke modal hapus penjahit
                    document.getElementById('hapus_penjahit_id').value = id;
                    document.getElementById('hapus_penjahit_produk').textContent = produk;
                    document.getElementById('hapus_penjahit_seri').textContent = seri + (penjahit ? ` (Penjahit: ${penjahit})` : '');

                    // Tampilkan modal
                    modalHapusPenjahit.show();
                }

                // Tombol Batal Produksi
                if (e.target.closest('.btn-batal-produksi')) {
                    e.preventDefault();
                    console.log('Tombol Batal Produksi diklik');

                    const button = e.target.closest('.btn-batal-produksi');
                    const id = button.getAttribute('data-id');
                    const pemotong = button.getAttribute('data-pemotong');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const tanggalPotong = button.getAttribute('data-tanggal-potong') || '<?= date("Y-m-d") ?>';
                    const produk = button.getAttribute('data-produk');
                    const tarifPemotong = button.getAttribute('data-tarif-pemotong') || 0;

                    // Hitung upah pemotong untuk informasi
                    // const tarifPemotong = 0; // Default atau ambil dari data atribut jika ada
                    const upahPemotong = totalPotong * tarifPemotong;

                    // Konfirmasi dengan SweetAlert
                    Swal.fire({
                        title: 'Yakin ingin membatalkan produksi ini?',
                        html: `<div class="text-start">
                                <p><strong>Produksi Seri ${seri}</strong> akan dibatalkan.</p>
                                <div class="alert alert-warning mt-2">
                                    <p><strong>Detail Produksi:</strong></p>
                                    <p>Produk: ${produk}</p>
                                    <p>Pemotong: ${pemotong}</p>
                                    <p>Hasil Potong: ${totalPotong} Pcs</p>
                                </div>
                                <p class="text-danger"><strong>Tindakan ini tidak dapat dikembalikan!</strong></p>
                            </div>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, batalkan!',
                        cancelButtonText: 'Batal',
                        width: '600px'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'batal_pemotongan.php?id=' + id;
                        }
                    });
                }
            });

            // Fungsi format tanggal
            function formatDate(dateString) {
                if (!dateString || dateString === '-') return '';
                try {
                    const date = new Date(dateString);
                    return date.toISOString().split('T')[0];
                } catch (e) {
                    return '';
                }
            }

            // Validasi form tanggal kirim
            document.getElementById('formTanggalPenjahitan')?.addEventListener('submit', function(e) {
                const idPenjahit = document.getElementById('modal_tanggal_penjahit')?.value;
                const tanggalKirim = document.getElementById('modal_tanggal_kirim_jahit')?.value;

                if (!idPenjahit) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Penjahit harus dipilih',
                        confirmButtonText: 'Oke'
                    });
                    return;
                }

                if (!tanggalKirim) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Tanggal kirim harus diisi',
                        confirmButtonText: 'Oke'
                    });
                    return;
                }
            });

            // Validasi form hasil jahit
            document.getElementById('formHasilPenjahitan')?.addEventListener('submit', function(e) {
                const totalJahit = parseInt(document.getElementById('modal_hasil_total_jahit')?.value) || 0;
                const maxJahit = parseInt(document.getElementById('modal_hasil_total_jahit')?.max) || 0;
                const tanggalHasilJahit = document.getElementById('modal_hasil_tanggal_jahit')?.value;

                let errorMessages = [];

                if (!tanggalHasilJahit) {
                    errorMessages.push('Tanggal hasil jahit harus diisi');
                }

                if (totalJahit <= 0) {
                    errorMessages.push('Total hasil jahit harus lebih dari 0');
                }

                if (totalJahit > maxJahit) {
                    errorMessages.push(`Total hasil jahit tidak boleh melebihi ${maxJahit} Pcs`);
                }

                if (errorMessages.length > 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Validasi Error',
                        html: '<div class="text-start">' +
                            errorMessages.map(msg => `<p>• ${msg}</p>`).join('') +
                            '</div>',
                        confirmButtonText: 'Oke'
                    });
                }
            });

            // Tombol Print PDF
            document.getElementById('btnPrintPDF')?.addEventListener('click', function() {
                const id_produk = document.querySelector('select[name="id_produk"]')?.value;
                const status = document.querySelector('select[name="status"]')?.value;
                const start_date = document.querySelector('input[name="start_date"]')?.value;
                const end_date = document.querySelector('input[name="end_date"]')?.value;

                let url = 'print_laporan_produksi.php?id_produk=' + (id_produk || 0) +
                    '&status=' + (status || 'all') +
                    '&start_date=' + (start_date || '') +
                    '&end_date=' + (end_date || '');

                window.open(url, '_blank');
            });

            // Set default date range (30 hari terakhir)
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

            setDefaultDateRange();
        });
    </script>
</body>

</html>