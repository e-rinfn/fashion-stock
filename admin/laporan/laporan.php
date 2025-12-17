<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';

// Aktifkan error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cek session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Cek koneksi database
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan!");
}

// Ambil parameter filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : '';
$filter_tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
$filter_tipe = isset($_GET['tipe']) ? $_GET['tipe'] : '';

// Daftar bulan
$bulan_list = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember'
];

// Daftar tahun
$tahun_sekarang = date('Y');
$tahun_list = [];
for ($i = $tahun_sekarang; $i >= $tahun_sekarang - 4; $i--) {
    $tahun_list[$i] = $i;
}

// **Query untuk ringkasan per kelompok**
$sql_laporan = "SELECT 
    kk.kelompok_kategori,
    kk.tipe_kategori,
    COUNT(DISTINCT kk.id_kategori) as jumlah_kategori,
    COUNT(kt.id_transaksi) as jumlah_transaksi,
    COALESCE(SUM(kt.jumlah), 0) as total
FROM kas_kategori kk
LEFT JOIN kas_transaksi kt ON kk.id_kategori = kt.id_kategori";

// Build WHERE clause
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(kk.kelompok_kategori LIKE '%$search%' OR kk.nama_kategori LIKE '%$search%')";
}

if (!empty($filter_tipe)) {
    $where_conditions[] = "kk.tipe_kategori = '$filter_tipe'";
}

// Untuk LEFT JOIN, perlu handle NULL untuk filter tanggal
if (!empty($filter_bulan)) {
    $where_conditions[] = "(kt.tanggal IS NULL OR MONTH(kt.tanggal) = '$filter_bulan')";
}

if (!empty($filter_tahun)) {
    $where_conditions[] = "(kt.tanggal IS NULL OR YEAR(kt.tanggal) = '$filter_tahun')";
}

if (!empty($where_conditions)) {
    $sql_laporan .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql_laporan .= " GROUP BY kk.kelompok_kategori, kk.tipe_kategori 
                  ORDER BY kk.tipe_kategori DESC, total DESC";

// Debug: Tampilkan query
// echo "Query Ringkasan: " . $sql_laporan . "<hr>";

// Eksekusi query
$result_laporan = $conn->query($sql_laporan);
if (!$result_laporan) {
    die("Query error: " . $conn->error);
}

$laporan_data = [];
$total_masuk_all = 0;
$total_keluar_all = 0;
$total_keseluruhan = 0;

while ($row = $result_laporan->fetch_assoc()) {
    $laporan_data[] = $row;
    $total_keseluruhan += $row['total'];
    if ($row['tipe_kategori'] == 'MASUK') {
        $total_masuk_all += $row['total'];
    } else {
        $total_keluar_all += $row['total'];
    }
}

// **Query total pemasukan dan pengeluaran**
$sql_total = "SELECT 
    COALESCE(SUM(CASE WHEN kt.tipe = 'MASUK' THEN kt.jumlah ELSE 0 END), 0) as total_masuk,
    COALESCE(SUM(CASE WHEN kt.tipe = 'KELUAR' THEN kt.jumlah ELSE 0 END), 0) as total_keluar
FROM kas_transaksi kt";

$where_total = [];
if (!empty($filter_bulan) && !empty($filter_tahun)) {
    $where_total[] = "MONTH(kt.tanggal) = '$filter_bulan' AND YEAR(kt.tanggal) = '$filter_tahun'";
} elseif (!empty($filter_tahun)) {
    $where_total[] = "YEAR(kt.tanggal) = '$filter_tahun'";
}

if (!empty($where_total)) {
    $sql_total .= " WHERE " . implode(" AND ", $where_total);
}

$result_total = $conn->query($sql_total);
$total_data = $result_total->fetch_assoc();
$total_masuk = $total_data['total_masuk'] ?? 0;
$total_keluar = $total_data['total_keluar'] ?? 0;

// **Query untuk detail per kategori dengan filter yang sama**
$sql_detail = "SELECT 
    kk.kelompok_kategori,
    kk.nama_kategori,
    kk.tipe_kategori,
    COUNT(kt.id_transaksi) as jumlah_transaksi,
    COALESCE(SUM(kt.jumlah), 0) as total
FROM kas_kategori kk
LEFT JOIN kas_transaksi kt ON kk.id_kategori = kt.id_kategori";

// Gunakan kondisi yang sama dengan query ringkasan
if (!empty($where_conditions)) {
    $sql_detail .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql_detail .= " GROUP BY kk.kelompok_kategori, kk.nama_kategori, kk.tipe_kategori 
                 ORDER BY kk.kelompok_kategori, kk.tipe_kategori DESC, total DESC";

// Debug: Tampilkan query detail
// echo "Query Detail: " . $sql_detail . "<hr>";

$result_detail = $conn->query($sql_detail);
$detail_data = [];
$stat_kelompok_count = 0;
$stat_total_kategori = 0;
$stat_total_transaksi = 0;
$stat_total_nilai = 0;

// Array untuk menyimpan kelompok yang sudah diproses
$kelompok_processed = [];

while ($row = $result_detail->fetch_assoc()) {
    $kelompok = $row['kelompok_kategori'];

    // Hitung statistik
    $stat_total_kategori++;
    $stat_total_transaksi += $row['jumlah_transaksi'];
    $stat_total_nilai += $row['total'];

    if (!isset($detail_data[$kelompok])) {
        $detail_data[$kelompok] = [];
        $stat_kelompok_count++;
    }
    $detail_data[$kelompok][] = $row;
}
?>
<style>
    .summary-card {
        border-radius: 10px;
        padding: 15px;
        color: white;
        margin-bottom: 15px;
        height: 100%;
    }

    .stat-card {
        padding: 15px;
        border-radius: 8px;
        background: #f8f9fa;
        text-align: center;
        height: 100%;
    }

    .stat-value {
        font-size: 1.5rem;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .stat-label {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .badge-masuk {
        background-color: #28a745 !important;
        color: white !important;
    }

    .badge-keluar {
        background-color: #dc3545 !important;
        color: white !important;
    }

    .table-card {
        height: 100%;
        border: 1px solid #dee2e6;
    }

    .table-card .card-body {
        padding: 0;
    }

    .table-card table {
        margin-bottom: 0;
    }

    .grid-card {
        transition: transform 0.2s, box-shadow 0.2s;
        height: 100%;
    }

    .grid-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
    }

    .section-title {
        color: #495057;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .filter-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    /* Warna untuk kartu summary */
    .card-masuk {
        background: linear-gradient(135deg, #00b09b, #96c93d);
    }

    .card-keluar {
        background: linear-gradient(135deg, #ff416c, #ff4b2b);
    }

    .card-total {
        background: linear-gradient(135deg, #2196f3, #21cbf3);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .summary-card {
            padding: 12px;
        }

        .summary-card h3 {
            font-size: 1.2rem;
        }

        .summary-card h6 {
            font-size: 0.85rem;
        }
    }
</style>

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
            <!-- [Mobile Media Block] start -->
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
                    <!-- ======= Menu collapse Icon ===== -->
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
            <!-- [Mobile Media Block end] -->
        </div>
    </header>
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <!-- [ Main Content ] start -->
            <div class="row">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Laporan</h2>

                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">

                <!-- Total Pemasukan -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="text-muted">Total Pemasukan</h3>
                                <h4 class="fw-bold text-success mb-1">
                                    <?= formatRupiah($total_masuk) ?>
                                </h4>
                                <small class="text-muted">
                                    <?= $filter_bulan ? $bulan_list[$filter_bulan] : 'Tahun' ?>
                                    <?= $filter_tahun ?>
                                </small>
                            </div>
                            <div class="text-success opacity-75 fs-2">
                                <i class="bi bi-arrow-down-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Pengeluaran -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="text-muted">Total Pengeluaran</h3>
                                <h4 class="fw-bold text-danger mb-1">
                                    <?= formatRupiah($total_keluar) ?>
                                </h4>
                                <small class="text-muted">
                                    <?= $filter_bulan ? $bulan_list[$filter_bulan] : 'Tahun' ?>
                                    <?= $filter_tahun ?>
                                </small>
                            </div>
                            <div class="text-danger opacity-75 fs-2">
                                <i class="bi bi-arrow-up-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Saldo Bersih -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="text-muted">Saldo Bersih</h3>
                                <h4 class="fw-bold <?= ($total_masuk - $total_keluar) >= 0 ? 'text-primary' : 'text-warning' ?> mb-1">
                                    <?= formatRupiah($total_masuk - $total_keluar) ?>
                                </h4>
                                <small class="text-muted">
                                    <?= $filter_bulan ? $bulan_list[$filter_bulan] : 'Tahun' ?>
                                    <?= $filter_tahun ?>
                                </small>
                            </div>
                            <div class="opacity-75 fs-2 text-primary">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                    </div>
                </div>

            </div>


            <!-- Filter Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <!-- Search -->
                        <div class="col-12 col-lg-3">
                            <label class="form-label">Pencarian</label>
                            <input type="text" name="search" class="form-control"
                                placeholder="Cari kelompok atau kategori..."
                                value="<?= htmlspecialchars($search ?? '') ?>">
                        </div>

                        <!-- Tipe -->
                        <div class="col-6 col-lg-2">
                            <label class="form-label">Tipe</label>
                            <select name="tipe" class="form-select">
                                <option value="">Semua</option>
                                <option value="MASUK" <?= ($filter_tipe ?? '') === 'MASUK' ? 'selected' : '' ?>>Masuk</option>
                                <option value="KELUAR" <?= ($filter_tipe ?? '') === 'KELUAR' ? 'selected' : '' ?>>Keluar</option>
                            </select>
                        </div>

                        <!-- Bulan -->
                        <div class="col-6 col-lg-2">
                            <label class="form-label">Bulan</label>
                            <select name="bulan" class="form-select">
                                <option value="">Semua</option>
                                <?php foreach ($bulan_list as $key => $nama): ?>
                                    <option value="<?= $key ?>" <?= ($filter_bulan ?? '') == $key ? 'selected' : '' ?>>
                                        <?= $nama ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Tahun -->
                        <div class="col-6 col-lg-2">
                            <label class="form-label">Tahun</label>
                            <select name="tahun" class="form-select">
                                <?php foreach ($tahun_list as $tahun): ?>
                                    <option value="<?= $tahun ?>" <?= ($filter_tahun ?? '') == $tahun ? 'selected' : '' ?>>
                                        <?= $tahun ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Button -->
                        <div class="col-12 col-lg-3">
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <i class="ti ti-filter me-1"></i> Terapkan Filter
                            </button>
                            <a href="laporan.php" class="btn btn-outline-secondary w-100">
                                <i class="ti ti-rotate me-1"></i> Reset
                            </a>
                        </div>
                    </form>

                    <!-- Tombol Cetak PDF -->
                    <div class="d-flex justify-content-start mt-3">
                        <form method="POST" action="cetak_laporan.php" target="_blank" class="d-inline">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="tipe" value="<?= htmlspecialchars($filter_tipe) ?>">
                            <input type="hidden" name="bulan" value="<?= htmlspecialchars($filter_bulan) ?>">
                            <input type="hidden" name="tahun" value="<?= htmlspecialchars($filter_tahun) ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="ti ti-printer me-1"></i> Cetak Laporan PDF
                            </button>
                        </form>
                    </div>

                    <!-- Active Filters -->
                    <?php if (!empty($search) || !empty($filter_tipe) || !empty($filter_bulan)): ?>
                        <div class="mt-3">
                            <small class="text-muted">Filter aktif:</small>
                            <?php if (!empty($search)): ?>
                                <span class="badge bg-light text-dark me-2">
                                    <i class="bi bi-search me-1"></i> "<?= htmlspecialchars($search) ?>"
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($filter_tipe)): ?>
                                <span class="badge bg-light text-dark me-2">
                                    <i class="bi bi-tag me-1"></i> <?= $filter_tipe ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($filter_bulan)): ?>
                                <span class="badge bg-light text-dark me-2">
                                    <i class="bi bi-calendar me-1"></i> <?= $bulan_list[$filter_bulan] ?> <?= $filter_tahun ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div hidden class="row">
                <!-- Left Column: Ringkasan per Kelompok -->
                <div class="col-lg-8 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-file-text me-2"></i>Ringkasan per Kelompok
                                <?php if (!empty($laporan_data)): ?>
                                    <span class="badge bg-primary ms-2">
                                        <?= count($laporan_data) ?> Kelompok
                                    </span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($laporan_data)): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Tidak ada data ditemukan.</strong><br>
                                    Coba gunakan filter yang berbeda atau pastikan sudah ada data transaksi.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Kelompok Kategori</th>
                                                <th>Tipe</th>
                                                <th class="text-center">Jumlah Kategori</th>
                                                <th class="text-center">Jumlah Transaksi</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($laporan_data as $row): ?>
                                                <tr>
                                                    <td>
                                                        <i class="bi bi-folder me-2"></i>
                                                        <strong><?= htmlspecialchars($row['kelompok_kategori']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $row['tipe_kategori'] == 'MASUK' ? 'badge-masuk' : 'badge-keluar' ?>">
                                                            <?= $row['tipe_kategori'] ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center"><?= $row['jumlah_kategori'] ?></td>
                                                    <td class="text-center">
                                                        <?php if ($row['jumlah_transaksi'] > 0): ?>
                                                            <span class="badge bg-primary rounded-pill">
                                                                <?= $row['jumlah_transaksi'] ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end <?= $row['tipe_kategori'] == 'MASUK' ? 'text-success fw-bold' : 'text-danger fw-bold' ?>">
                                                        <?= formatRupiah($row['total']) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <td colspan="2"><strong>TOTAL KESELURUHAN</strong></td>
                                                <td class="text-center fw-bold"><?= array_sum(array_column($laporan_data, 'jumlah_kategori')) ?></td>
                                                <td class="text-center fw-bold"><?= array_sum(array_column($laporan_data, 'jumlah_transaksi')) ?></td>
                                                <td class="text-end fw-bold"><?= formatRupiah($total_keseluruhan) ?></td>
                                            </tr>
                                            <tr class="table-success">
                                                <td colspan="2">• Total Pemasukan</td>
                                                <td class="text-center">-</td>
                                                <td class="text-center">-</td>
                                                <td class="text-end text-success fw-bold"><?= formatRupiah($total_masuk_all) ?></td>
                                            </tr>
                                            <tr class="table-danger">
                                                <td colspan="2">• Total Pengeluaran</td>
                                                <td class="text-center">-</td>
                                                <td class="text-center">-</td>
                                                <td class="text-end text-danger fw-bold"><?= formatRupiah($total_keluar_all) ?></td>
                                            </tr>
                                            <tr class="table-primary">
                                                <td colspan="2"><strong>SALDO BERSIH</strong></td>
                                                <td class="text-center">-</td>
                                                <td class="text-center">-</td>
                                                <td class="text-end text-primary fw-bold"><?= formatRupiah($total_masuk_all - $total_keluar_all) ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Statistics -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bar-chart me-2"></i>Statistik
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value text-primary"><?= $stat_kelompok_count ?></div>
                                        <div class="stat-label">Kelompok</div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value text-success"><?= $stat_total_kategori ?></div>
                                        <div class="stat-label">Kategori</div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value text-warning"><?= $stat_total_transaksi ?></div>
                                        <div class="stat-label">Transaksi</div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value text-danger"><?= formatRupiah($stat_total_nilai) ?></div>
                                        <div class="stat-label">Total Nilai</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <h6 class="mb-3">Rasio Pemasukan vs Pengeluaran</h6>
                                <?php
                                $total_seluruh = $total_masuk_all + $total_keluar_all;
                                ?>
                                <?php if ($total_seluruh > 0): ?>
                                    <?php
                                    $masuk_percent = ($total_masuk_all / $total_seluruh) * 100;
                                    $keluar_percent = ($total_keluar_all / $total_seluruh) * 100;
                                    ?>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar bg-success"
                                            style="width: <?= round($masuk_percent, 2) ?>%"
                                            role="progressbar"
                                            aria-valuenow="<?= round($masuk_percent, 2) ?>"
                                            aria-valuemin="0"
                                            aria-valuemax="100">
                                            <?= round($masuk_percent, 1) ?>%
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-success">
                                            <i class="bi bi-arrow-down-circle"></i>
                                            Pemasukan: <?= formatRupiah($total_masuk_all) ?>
                                        </small>
                                        <small class="text-danger">
                                            <i class="bi bi-arrow-up-circle"></i>
                                            Pengeluaran: <?= formatRupiah($total_keluar_all) ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-secondary text-center mb-0">
                                        <i class="bi bi-info-circle"></i>
                                        Belum ada data pemasukan atau pengeluaran
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail per Kelompok Grid -->
            <?php if (!empty($detail_data)): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-list-check me-2"></i>Detail per Kategori
                                    <span class="badge bg-primary ms-2">
                                        <?= $stat_total_kategori ?> Kategori
                                    </span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    $counter = 0;
                                    foreach ($detail_data as $kelompok => $kategories):
                                        $counter++;
                                    ?>
                                        <div class="col-lg-6 col-md-6 mb-4">
                                            <div class="card grid-card h-100">
                                                <div class="card-header bg-light py-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0">
                                                            <i class="bi bi-folder2-open me-2"></i>
                                                            <strong><?= htmlspecialchars($kelompok) ?></strong>
                                                        </h6>
                                                        <span class="badge bg-primary">
                                                            <?= count($kategories) ?> Kategori
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="card-body p-0">
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered table-sm table-hover mb-0">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th width="45%">Kategori</th>
                                                                    <th width="20%" class="text-center">Tipe</th>
                                                                    <th width="20%" class="text-center">Jml.</th>
                                                                    <th width="15%" class="text-end">Total</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $sub_total = 0;
                                                                $sub_transaksi = 0;
                                                                ?>
                                                                <?php foreach ($kategories as $kategori): ?>
                                                                    <?php
                                                                    $sub_total += $kategori['total'];
                                                                    $sub_transaksi += $kategori['jumlah_transaksi'];
                                                                    ?>
                                                                    <tr>
                                                                        <td class="text-truncate" title="<?= htmlspecialchars($kategori['nama_kategori']) ?>">
                                                                            <i class="bi bi-tag me-1"></i>
                                                                            <small><?= htmlspecialchars($kategori['nama_kategori']) ?></small>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <span class="badge badge-sm <?= $kategori['tipe_kategori'] == 'MASUK' ? 'badge-masuk' : 'badge-keluar' ?>">
                                                                                <?= $kategori['tipe_kategori'] == 'MASUK' ? 'M' : 'K' ?>
                                                                            </span>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <?php if ($kategori['jumlah_transaksi'] > 0): ?>
                                                                                <span class="badge bg-primary rounded-pill">
                                                                                    <?= $kategori['jumlah_transaksi'] ?>
                                                                                </span>
                                                                            <?php else: ?>
                                                                                <span class="text-muted">0</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td class="text-end <?= $kategori['tipe_kategori'] == 'MASUK' ? 'text-success' : 'text-danger' ?>">
                                                                            <small><?= formatRupiah($kategori['total']) ?></small>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                            <tfoot class="table-light">
                                                                <tr>
                                                                    <td colspan="2" class="fw-bold">
                                                                        <small>Sub Total</small>
                                                                    </td>
                                                                    <td class="text-center fw-bold">
                                                                        <small><?= $sub_transaksi ?></small>
                                                                    </td>
                                                                    <td class="text-end fw-bold">
                                                                        <small><?= formatRupiah($sub_total) ?></small>
                                                                    </td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($counter % 2 == 0): ?>
                                            <div class="w-100 d-none d-md-block"></div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // Auto hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);

            // Auto focus pada search input jika ada parameter search
            <?php if (!empty($search)): ?>
                $('input[name="search"]').focus().select();
            <?php endif; ?>

            // Submit form filter dengan Enter pada search field
            $('input[name="search"]').on('keypress', function(e) {
                if (e.key === 'Enter') {
                    $(this).closest('form').submit();
                }
            });

            // Tooltip untuk truncated text
            $('[title]').tooltip({
                trigger: 'hover'
            });
        });
    </script>
</body>

</html>