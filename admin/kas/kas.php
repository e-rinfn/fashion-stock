<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';

// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil data kategori untuk dropdown filter
$sql_kategori = "SELECT * FROM kas_kategori ORDER BY tipe_kategori, nama_kategori";
$result_kategori = $conn->query($sql_kategori);
$kategori_masuk = [];
$kategori_keluar = [];
$all_kategori = [];

while ($row = $result_kategori->fetch_assoc()) {
    $all_kategori[] = $row;
    if ($row['tipe_kategori'] == 'MASUK') {
        $kategori_masuk[] = $row;
    } else {
        $kategori_keluar[] = $row;
    }
}

// Ambil parameter filter
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : '';
$filter_tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

// Handle multi kategori filter
$filter_kategori = [];
if (isset($_GET['kategori']) && is_array($_GET['kategori'])) {
    foreach ($_GET['kategori'] as $kategori_id) {
        $kategori_id = intval($kategori_id);
        if ($kategori_id > 0) {
            $filter_kategori[] = $kategori_id;
        }
    }
} elseif (isset($_GET['kategori']) && $_GET['kategori'] != '') {
    $filter_kategori[] = intval($_GET['kategori']);
}

// Query dasar untuk transaksi
$sql_base = "SELECT kt.*, kk.nama_kategori, kk.tipe_kategori 
             FROM kas_transaksi kt 
             JOIN kas_kategori kk ON kt.id_kategori = kk.id_kategori";

// Tambahkan kondisi WHERE jika ada filter
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(kt.keterangan LIKE ? OR kk.nama_kategori LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if (!empty($filter_kategori)) {
    $placeholders = implode(',', array_fill(0, count($filter_kategori), '?'));
    $where_conditions[] = "kt.id_kategori IN ($placeholders)";
    $params = array_merge($params, $filter_kategori);
    $types .= str_repeat('i', count($filter_kategori));
}

if (!empty($filter_bulan)) {
    $where_conditions[] = "MONTH(kt.tanggal) = ?";
    $params[] = $filter_bulan;
    $types .= "i";
    $where_conditions[] = "YEAR(kt.tanggal) = ?";
    $params[] = $filter_tahun;
    $types .= "i";
}

// Gabungkan kondisi WHERE
if (!empty($where_conditions)) {
    $sql_base .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql_transaksi = $sql_base . " ORDER BY kt.tanggal DESC, kt.created_at DESC";
$stmt_transaksi = $conn->prepare($sql_transaksi);

// Bind parameter jika ada
if (!empty($params)) {
    $stmt_transaksi->bind_param($types, ...$params);
}

$stmt_transaksi->execute();
$result_transaksi = $stmt_transaksi->get_result();
$transaksi_data = [];

while ($row = $result_transaksi->fetch_assoc()) {
    $transaksi_data[] = $row;
}

// Query untuk total dengan filter yang sama
$sql_total_base = "SELECT 
    SUM(CASE WHEN kt.tipe = 'MASUK' THEN kt.jumlah ELSE 0 END) as total_masuk,
    SUM(CASE WHEN kt.tipe = 'KELUAR' THEN kt.jumlah ELSE 0 END) as total_keluar
    FROM kas_transaksi kt
    JOIN kas_kategori kk ON kt.id_kategori = kk.id_kategori";

// Gunakan kondisi WHERE yang sama
if (!empty($where_conditions)) {
    $sql_total_base .= " WHERE " . implode(" AND ", $where_conditions);
}

$stmt_total = $conn->prepare($sql_total_base);

if (!empty($params)) {
    $stmt_total->bind_param($types, ...$params);
}

$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_data = $result_total->fetch_assoc();
$total_masuk = $total_data['total_masuk'] ?? 0;
$total_keluar = $total_data['total_keluar'] ?? 0;
$saldo = $total_masuk - $total_keluar;

// Hitung per kategori MASUK dengan filter
$sql_kategori_masuk_base = "SELECT 
    kk.id_kategori,
    kk.nama_kategori,
    COALESCE(SUM(kt.jumlah), 0) as total,
    COUNT(kt.id_transaksi) as jumlah_transaksi
FROM kas_kategori kk
LEFT JOIN kas_transaksi kt ON kk.id_kategori = kt.id_kategori AND kt.tipe = 'MASUK'";

// Tambahkan kondisi WHERE untuk filter
$kategori_conditions = ["kk.tipe_kategori = 'MASUK'"];
$kategori_params = [];
$kategori_types = "";

if (!empty($search)) {
    $kategori_conditions[] = "(kt.keterangan LIKE ? OR kk.nama_kategori LIKE ?)";
    $kategori_params[] = "%$search%";
    $kategori_params[] = "%$search%";
    $kategori_types .= "ss";
}

if (!empty($filter_kategori)) {
    $placeholders = implode(',', array_fill(0, count($filter_kategori), '?'));
    $kategori_conditions[] = "kk.id_kategori IN ($placeholders)";
    $kategori_params = array_merge($kategori_params, $filter_kategori);
    $kategori_types .= str_repeat('i', count($filter_kategori));
}

if (!empty($filter_bulan)) {
    $kategori_conditions[] = "MONTH(kt.tanggal) = ?";
    $kategori_params[] = $filter_bulan;
    $kategori_types .= "i";
    $kategori_conditions[] = "YEAR(kt.tanggal) = ?";
    $kategori_params[] = $filter_tahun;
    $kategori_types .= "i";
}

$sql_kategori_masuk = $sql_kategori_masuk_base . " WHERE " . implode(" AND ", $kategori_conditions) .
    " GROUP BY kk.id_kategori, kk.nama_kategori ORDER BY total DESC";

$stmt_kategori_masuk = $conn->prepare($sql_kategori_masuk);

if (!empty($kategori_params)) {
    $stmt_kategori_masuk->bind_param($kategori_types, ...$kategori_params);
}

$stmt_kategori_masuk->execute();
$result_kategori_masuk = $stmt_kategori_masuk->get_result();
$kategori_masuk_total = [];
$total_all_masuk = 0;

while ($row = $result_kategori_masuk->fetch_assoc()) {
    $kategori_masuk_total[] = $row;
    $total_all_masuk += $row['total'];
}

// Hitung per kategori KELUAR dengan filter
$sql_kategori_keluar_base = "SELECT 
    kk.id_kategori,
    kk.nama_kategori,
    COALESCE(SUM(kt.jumlah), 0) as total,
    COUNT(kt.id_transaksi) as jumlah_transaksi
FROM kas_kategori kk
LEFT JOIN kas_transaksi kt ON kk.id_kategori = kt.id_kategori AND kt.tipe = 'KELUAR'";

$kategori_keluar_conditions = ["kk.tipe_kategori = 'KELUAR'"];
$kategori_keluar_params = [];
$kategori_keluar_types = "";

if (!empty($search)) {
    $kategori_keluar_conditions[] = "(kt.keterangan LIKE ? OR kk.nama_kategori LIKE ?)";
    $kategori_keluar_params[] = "%$search%";
    $kategori_keluar_params[] = "%$search%";
    $kategori_keluar_types .= "ss";
}

if (!empty($filter_kategori)) {
    $placeholders = implode(',', array_fill(0, count($filter_kategori), '?'));
    $kategori_keluar_conditions[] = "kk.id_kategori IN ($placeholders)";
    $kategori_keluar_params = array_merge($kategori_keluar_params, $filter_kategori);
    $kategori_keluar_types .= str_repeat('i', count($filter_kategori));
}

if (!empty($filter_bulan)) {
    $kategori_keluar_conditions[] = "MONTH(kt.tanggal) = ?";
    $kategori_keluar_params[] = $filter_bulan;
    $kategori_keluar_types .= "i";
    $kategori_keluar_conditions[] = "YEAR(kt.tanggal) = ?";
    $kategori_keluar_params[] = $filter_tahun;
    $kategori_keluar_types .= "i";
}

$sql_kategori_keluar = $sql_kategori_keluar_base . " WHERE " . implode(" AND ", $kategori_keluar_conditions) .
    " GROUP BY kk.id_kategori, kk.nama_kategori ORDER BY total DESC";

$stmt_kategori_keluar = $conn->prepare($sql_kategori_keluar);

if (!empty($kategori_keluar_params)) {
    $stmt_kategori_keluar->bind_param($kategori_keluar_types, ...$kategori_keluar_params);
}

$stmt_kategori_keluar->execute();
$result_kategori_keluar = $stmt_kategori_keluar->get_result();
$kategori_keluar_total = [];
$total_all_keluar = 0;

while ($row = $result_kategori_keluar->fetch_assoc()) {
    $kategori_keluar_total[] = $row;
    $total_all_keluar += $row['total'];
}

// Proses tambah transaksi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_transaksi'])) {
    $tanggal = $_POST['tanggal'];
    $keterangan = $_POST['keterangan'];
    $id_kategori = $_POST['id_kategori'];
    $tipe = $_POST['tipe'];
    $jumlah = str_replace('.', '', $_POST['jumlah']);

    // Validasi
    if (empty($tanggal) || empty($id_kategori) || empty($jumlah)) {
        $_SESSION['error'] = "Tanggal, kategori, dan jumlah harus diisi!";
        header("Location: kas.php");
        exit();
    }

    // Validasi jumlah positif
    if ($jumlah <= 0) {
        $_SESSION['error'] = "Jumlah harus lebih dari 0!";
        header("Location: kas.php");
        exit();
    }

    $sql_insert = "INSERT INTO kas_transaksi (tanggal, keterangan, id_kategori, tipe, jumlah) 
                   VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("ssisd", $tanggal, $keterangan, $id_kategori, $tipe, $jumlah);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Transaksi berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan transaksi: " . $conn->error;
    }

    header("Location: kas.php");
    exit();
}

// Proses edit transaksi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_transaksi'])) {
    $id_transaksi = $_POST['id_transaksi'];
    $tanggal = $_POST['tanggal'];
    $keterangan = $_POST['keterangan'];
    $id_kategori = $_POST['id_kategori'];
    $tipe = $_POST['tipe'];
    $jumlah = str_replace('.', '', $_POST['jumlah']);

    // Validasi
    if (empty($tanggal) || empty($id_kategori) || empty($jumlah)) {
        $_SESSION['error'] = "Tanggal, kategori, dan jumlah harus diisi!";
        header("Location: kas.php");
        exit();
    }

    $sql_update = "UPDATE kas_transaksi SET 
                   tanggal = ?, keterangan = ?, id_kategori = ?, tipe = ?, jumlah = ?
                   WHERE id_transaksi = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("ssisdi", $tanggal, $keterangan, $id_kategori, $tipe, $jumlah, $id_transaksi);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Transaksi berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal update transaksi: " . $conn->error;
    }

    header("Location: kas.php");
    exit();
}

// Proses hapus transaksi
if (isset($_GET['hapus'])) {
    $id_transaksi = intval($_GET['hapus']);

    $sql_delete = "DELETE FROM kas_transaksi WHERE id_transaksi = ?";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("i", $id_transaksi);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Transaksi berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus transaksi: " . $conn->error;
    }

    header("Location: kas.php");
    exit();
}

// Daftar bulan untuk dropdown
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

// Daftar tahun (5 tahun terakhir)
$tahun_sekarang = date('Y');
$tahun_list = [];
for ($i = $tahun_sekarang; $i >= $tahun_sekarang - 4; $i--) {
    $tahun_list[$i] = $i;
}
?>

<style>
    /* Style tambahan untuk filter */
    .filter-section {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .filter-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background-color: white;
    }

    .filter-header {
        background-color: #f8f9fa;
        padding: 10px 15px;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
    }

    .filter-body {
        padding: 15px;
    }

    .search-box {
        position: relative;
    }

    .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }

    .search-input {
        padding-left: 40px;
    }

    .btn-apply-filter {
        width: 100%;
    }

    .active-filters {
        background-color: #e7f1ff;
        border-left: 4px solid #0d6efd;
        padding: 10px 15px;
        border-radius: 4px;
        margin-bottom: 15px;
    }

    .filter-badge {
        background-color: #0d6efd;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        margin-right: 5px;
        margin-bottom: 5px;
    }

    .filter-badge i {
        margin-right: 4px;
    }

    .btn-clear {
        font-size: 0.85rem;
    }

    /* Style untuk multi select */
    .multiselect-container {
        position: relative;
    }

    .multiselect-display {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
        min-height: 38px;
        background-color: white;
        cursor: pointer;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 5px;
    }

    .multiselect-display:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .multiselect-placeholder {
        color: #6c757d;
    }

    .selected-item {
        background-color: #e7f1ff;
        border: 1px solid #86b7fe;
        border-radius: 15px;
        padding: 2px 8px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
    }

    .selected-item-remove {
        margin-left: 5px;
        cursor: pointer;
        color: #666;
    }

    .selected-item-remove:hover {
        color: #dc3545;
    }

    .multiselect-options {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background-color: white;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        display: none;
    }

    .multiselect-options.show {
        display: block;
    }

    .multiselect-option {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
    }

    .multiselect-option:hover {
        background-color: #f8f9fa;
    }

    .multiselect-option.checked {
        background-color: #e7f1ff;
    }

    .multiselect-option-group {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
        padding: 8px 12px;
        border-bottom: 1px solid #dee2e6;
    }

    .multiselect-actions {
        padding: 8px 12px;
        border-top: 1px solid #dee2e6;
        background-color: #f8f9fa;
        display: flex;
        justify-content: space-between;
    }

    /* Style untuk card summary */
    .card-summary {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .card-masuk {
        border-left: 5px solid #28a745;
    }

    .card-keluar {
        border-left: 5px solid #dc3545;
    }

    .card-saldo {
        border-left: 5px solid #007bff;
    }

    .table-transaksi th {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    .badge-masuk {
        background-color: #28a745;
    }

    .badge-keluar {
        background-color: #dc3545;
    }

    .form-control:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
    }

    .action-buttons .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .kategori-card {
        border-radius: 8px;
        border: 1px solid #dee2e6;
        margin-bottom: 15px;
    }

    .kategori-header {
        background-color: #f8f9fa;
        padding: 10px 15px;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
    }

    .kategori-body {
        padding: 15px;
    }

    .kategori-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .kategori-item:last-child {
        border-bottom: none;
        font-weight: 600;
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
    }

    .kategori-name {
        flex: 1;
    }

    .kategori-total {
        font-weight: 600;
        color: #495057;
    }

    .kategori-count {
        background-color: #e9ecef;
        color: #495057;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.8rem;
        margin-right: 10px;
    }

    .text-success {
        color: #28a745 !important;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    .progress-bar-masuk {
        background-color: #28a745;
    }

    .progress-bar-keluar {
        background-color: #dc3545;
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
                    <h2>Manajemen Kas</h2>

                </div>
            </div>

            <!-- Alert Messages -->
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

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">

                <!-- Total Masuk -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="text-muted">Total Masuk</h3>
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

                <!-- Total Keluar -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="text-muted">Total Keluar</h3>
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

                <!-- Saldo Kas -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="text-muted">Saldo Kas</h3>
                                <h4 class="fw-bold <?= $saldo >= 0 ? 'text-primary' : 'text-warning' ?> mb-1">
                                    <?= formatRupiah($saldo) ?>
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
            <div class="row mb-3">
                <div class="col-12">
                    <div class="filter-card">
                        <div class="collapse show" id="filterCollapse">
                            <div class="filter-body">
                                <form method="GET" action="" id="filterForm">
                                    <div class="row g-3">
                                        <!-- Search -->
                                        <div class="col-md-4">
                                            <label for="search" class="form-label">Pencarian</label>
                                            <div class="search-box">
                                                <i class="ti ti-search search-icon"></i>
                                                <input type="text" class="form-control search-input" id="search" name="search"
                                                    placeholder="Cari keterangan atau kategori..."
                                                    value="<?= htmlspecialchars($search) ?>">
                                            </div>
                                        </div>

                                        <!-- Filter Kategori (Multi Select) -->
                                        <div class="col-md-3">
                                            <label for="kategori_multiselect" class="form-label">Kategori</label>
                                            <div class="multiselect-container">
                                                <div class="multiselect-display" id="multiselectDisplay" tabindex="0">
                                                    <?php if (empty($filter_kategori)): ?>
                                                        <span class="multiselect-placeholder">Pilih kategori...</span>
                                                    <?php else: ?>
                                                        <?php foreach ($filter_kategori as $kat_id):
                                                            $kategori_name = '';
                                                            foreach ($all_kategori as $kat) {
                                                                if ($kat['id_kategori'] == $kat_id) {
                                                                    $kategori_name = $kat['nama_kategori'];
                                                                    break;
                                                                }
                                                            }
                                                        ?>
                                                            <span class="selected-item" data-id="<?= $kat_id ?>">
                                                                <?= htmlspecialchars($kategori_name) ?>
                                                                <span class="selected-item-remove" onclick="removeSelectedCategory(<?= $kat_id ?>)">×</span>
                                                                <input type="hidden" name="kategori[]" value="<?= $kat_id ?>">
                                                            </span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="multiselect-options" id="multiselectOptions">
                                                    <div class="multiselect-option-group">Pemasukan</div>
                                                    <?php foreach ($kategori_masuk as $kategori): ?>
                                                        <div class="multiselect-option <?= in_array($kategori['id_kategori'], $filter_kategori) ? 'checked' : '' ?>"
                                                            data-id="<?= $kategori['id_kategori'] ?>"
                                                            data-name="<?= htmlspecialchars($kategori['nama_kategori']) ?>">
                                                            <input type="checkbox" class="form-check-input me-2"
                                                                <?= in_array($kategori['id_kategori'], $filter_kategori) ? 'checked' : '' ?>>
                                                            <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <div class="multiselect-option-group">Pengeluaran</div>
                                                    <?php foreach ($kategori_keluar as $kategori): ?>
                                                        <div class="multiselect-option <?= in_array($kategori['id_kategori'], $filter_kategori) ? 'checked' : '' ?>"
                                                            data-id="<?= $kategori['id_kategori'] ?>"
                                                            data-name="<?= htmlspecialchars($kategori['nama_kategori']) ?>">
                                                            <input type="checkbox" class="form-check-input me-2"
                                                                <?= in_array($kategori['id_kategori'], $filter_kategori) ? 'checked' : '' ?>>
                                                            <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <div class="multiselect-actions">
                                                        <button type="button" class="btn btn-sm btn-link" onclick="selectAllCategories()">Pilih Semua</button>
                                                        <button type="button" class="btn btn-sm btn-link text-danger" onclick="clearAllCategories()">Hapus Semua</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Filter Bulan & Tahun -->
                                        <div class="col-md-4 mt-4">
                                            <div class="card border-0 bg-light shadow-sm">
                                                <div class="card-body p-3">
                                                    <div class="row g-2">
                                                        <div class="col-md-6">
                                                            <!-- <label for="bulan" class="form-label fw-semibold">Bulan</label> -->
                                                            <select class="form-select" id="bulan" name="bulan">
                                                                <option value="">Semua Bulan</option>
                                                                <?php foreach ($bulan_list as $key => $nama): ?>
                                                                    <option value="<?= $key ?>"
                                                                        <?= $filter_bulan == $key ? 'selected' : '' ?>>
                                                                        <?= $nama ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <!-- <label for="tahun" class="form-label fw-semibold">Tahun</label> -->
                                                            <select class="form-select" id="tahun" name="tahun">
                                                                <?php foreach ($tahun_list as $tahun): ?>
                                                                    <option value="<?= $tahun ?>"
                                                                        <?= $filter_tahun == $tahun ? 'selected' : '' ?>>
                                                                        <?= $tahun ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <!-- Tombol Aksi -->
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="submit" class="btn btn-primary btn-apply-filter">
                                                <i class="ti ti-filter"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>

                                <!-- Active Filters -->
                                <?php if (!empty($search) || !empty($filter_kategori) || !empty($filter_bulan)): ?>
                                    <div class="active-filters mt-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted">Filter aktif:</small>
                                                <?php if (!empty($search)): ?>
                                                    <span class="filter-badge">
                                                        <i class="ti ti-search"></i> "<?= htmlspecialchars($search) ?>"
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($filter_kategori)):
                                                    $kategori_names = [];
                                                    foreach ($filter_kategori as $kat_id) {
                                                        foreach ($all_kategori as $kat) {
                                                            if ($kat['id_kategori'] == $kat_id) {
                                                                $kategori_names[] = $kat['nama_kategori'];
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    $kategori_count = count($kategori_names);
                                                    if ($kategori_count > 0):
                                                ?>
                                                        <span class="filter-badge">
                                                            <i class="ti ti-category"></i>
                                                            <?php if ($kategori_count <= 3): ?>
                                                                <?= implode(', ', array_map('htmlspecialchars', $kategori_names)) ?>
                                                            <?php else: ?>
                                                                <?= implode(', ', array_map('htmlspecialchars', array_slice($kategori_names, 0, 3))) ?> +<?= $kategori_count - 3 ?>
                                                            <?php endif; ?>
                                                        </span>
                                                <?php endif;
                                                endif; ?>

                                                <?php if (!empty($filter_bulan)): ?>
                                                    <span class="filter-badge">
                                                        <i class="ti ti-calendar"></i> <?= $bulan_list[$filter_bulan] ?> <?= $filter_tahun ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="kas.php" class="btn btn-sm btn-danger btn-clear">
                                                <i class="ti ti-x"></i> Hapus Filter
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Info Filter -->
            <!-- <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert">
                        <i class="ti ti-info-circle me-2"></i>
                        <div>
                            Menampilkan <strong><?= count($transaksi_data) ?></strong> transaksi
                            <?php if (!empty($search) || !empty($filter_kategori) || !empty($filter_bulan)): ?>
                                berdasarkan filter yang dipilih
                            <?php else: ?>
                                (tanpa filter)
                            <?php endif; ?>
                            <?php if (!empty($filter_kategori) && count($filter_kategori) > 0): ?>
                                <br><small>Kategori terpilih: <strong><?= count($filter_kategori) ?></strong> kategori</small>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div> -->

            <!-- Main Content -->
            <div class="row">
                <!-- Daftar Transaksi -->
                <div class="col-md-8 mb-4">
                    <div class="mb-3 text-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                            <i class="ti ti-circle-plus"></i> Tambah Transaksi
                        </button>
                    </div>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Daftar Transaksi Kas</h5>
                            <span class="badge bg-primary">
                                <?= count($transaksi_data) ?> Transaksi
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($transaksi_data)): ?>
                                <div class="text-center py-5">
                                    <i class="ti ti-search-off display-4 text-muted mb-3"></i>
                                    <h5 class="text-muted">Tidak ada transaksi ditemukan</h5>
                                    <p class="text-muted">
                                        <?php if (!empty($search) || !empty($filter_kategori) || !empty($filter_bulan)): ?>
                                            Coba gunakan filter yang berbeda atau <a href="kas.php" class="text-primary">reset filter</a>
                                        <?php else: ?>
                                            Mulai dengan <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">menambahkan transaksi baru</a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered table-transaksi">
                                        <thead>
                                            <tr>
                                                <th width="20%">Tanggal</th>
                                                <th width="20%">Kategori</th>
                                                <th width="25%">Keterangan</th>
                                                <th width="10%">Tipe</th>
                                                <th width="15%">Jumlah</th>
                                                <th width="10%" class="text-center">Aksi</th>
                                            </tr>

                                        </thead>
                                        <tbody>
                                            <?php foreach ($transaksi_data as $transaksi): ?>
                                                <tr>
                                                    <td><?= dateIndo($transaksi['tanggal']) ?></td>
                                                    <td><?= htmlspecialchars($transaksi['nama_kategori']) ?></td>
                                                    <td><?= htmlspecialchars($transaksi['keterangan']) ?></td>
                                                    <td>
                                                        <span class="badge <?= $transaksi['tipe'] == 'MASUK' ? 'badge-masuk' : 'badge-keluar' ?>">
                                                            <?= $transaksi['tipe'] ?>
                                                        </span>
                                                    </td>
                                                    <td class="<?= $transaksi['tipe'] == 'MASUK' ? 'text-success' : 'text-danger' ?>">
                                                        <strong><?= formatRupiah($transaksi['jumlah']) ?></strong>
                                                    </td>
                                                    <td class="text-center action-buttons">
                                                        <button type="button" class="btn btn-sm btn-warning"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editModal"
                                                            data-id="<?= $transaksi['id_transaksi'] ?>"
                                                            data-tanggal="<?= $transaksi['tanggal'] ?>"
                                                            data-keterangan="<?= htmlspecialchars($transaksi['keterangan']) ?>"
                                                            data-idkategori="<?= $transaksi['id_kategori'] ?>"
                                                            data-tipe="<?= $transaksi['tipe'] ?>"
                                                            data-jumlah="<?= $transaksi['jumlah'] ?>">
                                                            <i class="ti ti-pencil"></i>
                                                        </button>
                                                        <a href="kas.php?hapus=<?= $transaksi['id_transaksi'] ?>&<?= http_build_query($_GET) ?>"
                                                            class="btn btn-sm btn-danger btn-hapus"
                                                            data-url="kas.php?hapus=<?= $transaksi['id_transaksi'] ?>&<?= http_build_query($_GET) ?>">
                                                            <i class="ti ti-trash"></i>
                                                        </a>

                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Ringkasan Per Kategori -->
                <div class="col-md-4">
                    <div class="mb-3 text-end">
                        <a href="kas_kategori.php" class="btn btn-info">
                            <i class="ti ti-settings"></i> Kelola Kategori
                        </a>
                    </div>
                    <!-- Kategori Masuk -->
                    <div class="card kategori-card mb-4">
                        <div class="kategori-header bg-success bg-opacity-10 text-success">
                            <i class="ti ti-arrow-down-right"></i> Kategori Masuk
                            <?php if (!empty($search) || !empty($filter_kategori) || !empty($filter_bulan)): ?>
                                <span class="badge bg-success ms-2">Filter Aktif</span>
                            <?php endif; ?>
                        </div>
                        <div class="kategori-body">
                            <?php if (empty($kategori_masuk_total)): ?>
                                <div class="text-center text-muted">Belum ada transaksi masuk</div>
                            <?php else: ?>
                                <?php foreach ($kategori_masuk_total as $kategori): ?>
                                    <div class="kategori-item">
                                        <div class="kategori-name">
                                            <span class="kategori-count"><?= $kategori['jumlah_transaksi'] ?></span>
                                            <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                        </div>
                                        <div class="kategori-total text-success">
                                            <?= formatRupiah($kategori['total']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="kategori-item">
                                    <div class="kategori-name">Total Semua Masuk</div>
                                    <div class="kategori-total text-success fw-bold">
                                        <?= formatRupiah($total_all_masuk) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kategori Keluar -->
                    <div class="card kategori-card">
                        <div class="kategori-header bg-danger bg-opacity-10 text-danger">
                            <i class="ti ti-arrow-up-right"></i> Kategori Keluar
                            <?php if (!empty($search) || !empty($filter_kategori) || !empty($filter_bulan)): ?>
                                <span class="badge bg-danger ms-2">Filter Aktif</span>
                            <?php endif; ?>
                        </div>
                        <div class="kategori-body">
                            <?php if (empty($kategori_keluar_total)): ?>
                                <div class="text-center text-muted">Belum ada transaksi keluar</div>
                            <?php else: ?>
                                <?php foreach ($kategori_keluar_total as $kategori): ?>
                                    <div class="kategori-item">
                                        <div class="kategori-name">
                                            <span class="kategori-count"><?= $kategori['jumlah_transaksi'] ?></span>
                                            <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                        </div>
                                        <div class="kategori-total text-danger">
                                            <?= formatRupiah($kategori['total']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="kategori-item">
                                    <div class="kategori-name">Total Semua Keluar</div>
                                    <div class="kategori-total text-danger fw-bold">
                                        <?= formatRupiah($total_all_keluar) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Transaksi -->
        <div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tambahModalLabel">Tambah Transaksi Kas</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="tanggal" class="form-label">Tanggal *</label>
                                <input type="date" class="form-control" id="tanggal" name="tanggal"
                                    value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="tipe" class="form-label">Tipe Transaksi *</label>
                                <select class="form-select" id="tipe" name="tipe" required onchange="updateKategoriOptions()">
                                    <option value="">Pilih Tipe</option>
                                    <option value="MASUK">Masuk</option>
                                    <option value="KELUAR">Keluar</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="id_kategori" class="form-label">Kategori *</label>
                                <select class="form-select" id="id_kategori" name="id_kategori" required>
                                    <option value="">Pilih Tipe terlebih dahulu</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="jumlah" class="form-label">Jumlah *</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control" id="jumlah" name="jumlah"
                                        placeholder="0" required oninput="formatRupiahInput(this)">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="keterangan" class="form-label">Keterangan</label>
                                <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="tambah_transaksi" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Transaksi -->
        <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <input type="hidden" id="edit_id_transaksi" name="id_transaksi">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editModalLabel">Edit Transaksi Kas</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_tanggal" class="form-label">Tanggal *</label>
                                <input type="date" class="form-control" id="edit_tanggal" name="tanggal" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_tipe" class="form-label">Tipe Transaksi *</label>
                                <select class="form-select" id="edit_tipe" name="tipe" required onchange="updateEditKategoriOptions()">
                                    <option value="">Pilih Tipe</option>
                                    <option value="MASUK">Masuk</option>
                                    <option value="KELUAR">Keluar</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit_id_kategori" class="form-label">Kategori *</label>
                                <select class="form-select" id="edit_id_kategori" name="id_kategori" required>
                                    <option value="">Pilih Kategori</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit_jumlah" class="form-label">Jumlah *</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control" id="edit_jumlah" name="jumlah"
                                        placeholder="0" required oninput="formatRupiahInput(this)">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_keterangan" class="form-label">Keterangan</label>
                                <textarea class="form-control" id="edit_keterangan" name="keterangan" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="edit_transaksi" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>
</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.querySelectorAll('.btn-hapus').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            const url = this.getAttribute('data-url');

            Swal.fire({
                title: 'Yakin ingin menghapus?',
                text: 'Data transaksi ini akan dihapus permanen.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });
    });

    // Multi Select Kategori Functions
    const multiselectDisplay = document.getElementById('multiselectDisplay');
    const multiselectOptions = document.getElementById('multiselectOptions');
    const filterForm = document.getElementById('filterForm');

    // Toggle dropdown
    multiselectDisplay.addEventListener('click', function(e) {
        e.stopPropagation();
        multiselectOptions.classList.toggle('show');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!multiselectDisplay.contains(e.target) && !multiselectOptions.contains(e.target)) {
            multiselectOptions.classList.remove('show');
        }
    });

    // Handle option selection
    multiselectOptions.addEventListener('click', function(e) {
        if (e.target.classList.contains('multiselect-option') ||
            e.target.parentElement.classList.contains('multiselect-option')) {

            const option = e.target.classList.contains('multiselect-option') ?
                e.target : e.target.parentElement;

            const id = option.dataset.id;
            const name = option.dataset.name;
            const checkbox = option.querySelector('input[type="checkbox"]');

            checkbox.checked = !checkbox.checked;
            option.classList.toggle('checked');

            updateSelectedCategories(id, name, checkbox.checked);
            updateHiddenInputs();
        }
    });

    // Update selected categories display
    function updateSelectedCategories(id, name, isChecked) {
        const selectedItems = multiselectDisplay.querySelectorAll('.selected-item');
        let existingItem = null;

        selectedItems.forEach(item => {
            if (item.dataset.id === id) {
                existingItem = item;
            }
        });

        if (isChecked && !existingItem) {
            // Add new selected item
            const newItem = document.createElement('span');
            newItem.className = 'selected-item';
            newItem.dataset.id = id;
            newItem.innerHTML = `
                ${name}
                <span class="selected-item-remove" onclick="removeSelectedCategory(${id})">×</span>
            `;

            // Remove placeholder if exists
            const placeholder = multiselectDisplay.querySelector('.multiselect-placeholder');
            if (placeholder) {
                placeholder.remove();
            }

            multiselectDisplay.appendChild(newItem);
        } else if (!isChecked && existingItem) {
            // Remove existing item
            existingItem.remove();

            // Add placeholder if no items left
            if (multiselectDisplay.children.length === 0) {
                const placeholder = document.createElement('span');
                placeholder.className = 'multiselect-placeholder';
                placeholder.textContent = 'Pilih kategori...';
                multiselectDisplay.appendChild(placeholder);
            }
        }
    }

    // Remove selected category
    function removeSelectedCategory(id) {
        event.stopPropagation();

        // Remove from display
        const item = multiselectDisplay.querySelector(`.selected-item[data-id="${id}"]`);
        if (item) {
            item.remove();

            // Add placeholder if no items left
            if (multiselectDisplay.children.length === 0) {
                const placeholder = document.createElement('span');
                placeholder.className = 'multiselect-placeholder';
                placeholder.textContent = 'Pilih kategori...';
                multiselectDisplay.appendChild(placeholder);
            }
        }

        // Uncheck option
        const option = multiselectOptions.querySelector(`.multiselect-option[data-id="${id}"]`);
        if (option) {
            option.classList.remove('checked');
            const checkbox = option.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = false;
        }

        updateHiddenInputs();
    }

    // Update hidden inputs
    function updateHiddenInputs() {
        // Remove existing hidden inputs
        const existingInputs = multiselectDisplay.querySelectorAll('input[name="kategori[]"]');
        existingInputs.forEach(input => input.remove());

        // Add new hidden inputs for each selected item
        const selectedItems = multiselectDisplay.querySelectorAll('.selected-item');
        selectedItems.forEach(item => {
            const id = item.dataset.id;
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'kategori[]';
            hiddenInput.value = id;
            multiselectDisplay.appendChild(hiddenInput);
        });
    }

    // Select all categories
    function selectAllCategories() {
        const options = multiselectOptions.querySelectorAll('.multiselect-option');
        options.forEach(option => {
            const id = option.dataset.id;
            const name = option.dataset.name;
            const checkbox = option.querySelector('input[type="checkbox"]');

            if (!checkbox.checked) {
                checkbox.checked = true;
                option.classList.add('checked');
                updateSelectedCategories(id, name, true);
            }
        });
        updateHiddenInputs();
    }

    // Clear all categories
    function clearAllCategories() {
        const options = multiselectOptions.querySelectorAll('.multiselect-option');
        options.forEach(option => {
            const id = option.dataset.id;
            const checkbox = option.querySelector('input[type="checkbox"]');

            if (checkbox.checked) {
                checkbox.checked = false;
                option.classList.remove('checked');
                removeSelectedCategory(id);
            }
        });
        updateHiddenInputs();
    }

    // Initialize selected categories on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateHiddenInputs();
    });

    // Format input rupiah
    function formatRupiahInput(input) {
        let value = input.value.replace(/[^\d]/g, '');
        if (value) {
            value = parseInt(value).toLocaleString('id-ID');
        }
        input.value = value;
    }

    // Update kategori options berdasarkan tipe
    function updateKategoriOptions() {
        const tipe = document.getElementById('tipe').value;
        const kategoriSelect = document.getElementById('id_kategori');
        kategoriSelect.innerHTML = '<option value="">Pilih Kategori</option>';

        <?php if (!empty($kategori_masuk)): ?>
            if (tipe === 'MASUK') {
                <?php foreach ($kategori_masuk as $kategori): ?>
                    kategoriSelect.innerHTML += '<option value="<?= $kategori['id_kategori'] ?>"><?= htmlspecialchars(addslashes($kategori['nama_kategori'])) ?></option>';
                <?php endforeach; ?>
            }
        <?php endif; ?>

        <?php if (!empty($kategori_keluar)): ?>
            if (tipe === 'KELUAR') {
                <?php foreach ($kategori_keluar as $kategori): ?>
                    kategoriSelect.innerHTML += '<option value="<?= $kategori['id_kategori'] ?>"><?= htmlspecialchars(addslashes($kategori['nama_kategori'])) ?></option>';
                <?php endforeach; ?>
            }
        <?php endif; ?>
    }

    // Update kategori options untuk edit
    function updateEditKategoriOptions() {
        const tipe = document.getElementById('edit_tipe').value;
        const kategoriSelect = document.getElementById('edit_id_kategori');
        kategoriSelect.innerHTML = '<option value="">Pilih Kategori</option>';

        <?php if (!empty($kategori_masuk)): ?>
            if (tipe === 'MASUK') {
                <?php foreach ($kategori_masuk as $kategori): ?>
                    kategoriSelect.innerHTML += '<option value="<?= $kategori['id_kategori'] ?>"><?= htmlspecialchars(addslashes($kategori['nama_kategori'])) ?></option>';
                <?php endforeach; ?>
            }
        <?php endif; ?>

        <?php if (!empty($kategori_keluar)): ?>
            if (tipe === 'KELUAR') {
                <?php foreach ($kategori_keluar as $kategori): ?>
                    kategoriSelect.innerHTML += '<option value="<?= $kategori['id_kategori'] ?>"><?= htmlspecialchars(addslashes($kategori['nama_kategori'])) ?></option>';
                <?php endforeach; ?>
            }
        <?php endif; ?>
    }

    // Handle modal edit show
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;

        const id = button.getAttribute('data-id');
        const tanggal = button.getAttribute('data-tanggal');
        const keterangan = button.getAttribute('data-keterangan');
        const idkategori = button.getAttribute('data-idkategori');
        const tipe = button.getAttribute('data-tipe');
        const jumlah = button.getAttribute('data-jumlah');

        document.getElementById('edit_id_transaksi').value = id;
        document.getElementById('edit_tanggal').value = tanggal;
        document.getElementById('edit_keterangan').value = keterangan;
        document.getElementById('edit_tipe').value = tipe;

        // Format jumlah untuk input
        const formattedJumlah = parseFloat(jumlah).toLocaleString('id-ID');
        document.getElementById('edit_jumlah').value = formattedJumlah;

        // Update kategori options
        updateEditKategoriOptions();

        // Set kategori yang dipilih setelah options dimuat
        setTimeout(() => {
            document.getElementById('edit_id_kategori').value = idkategori;
        }, 100);
    });

    // Format semua input rupiah saat modal dibuka
    const tambahModal = document.getElementById('tambahModal');
    tambahModal.addEventListener('shown.bs.modal', function() {
        formatRupiahInput(document.getElementById('jumlah'));
    });

    editModal.addEventListener('shown.bs.modal', function() {
        formatRupiahInput(document.getElementById('edit_jumlah'));
    });

    // Alert messages auto hide
    document.addEventListener('DOMContentLoaded', function() {
        // Auto hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Auto focus pada search input jika ada parameter search
        <?php if (!empty($search)): ?>
            document.getElementById('search').focus();
            document.getElementById('search').select();
        <?php endif; ?>
    });

    // Submit form filter dengan Enter pada search field
    document.getElementById('search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            filterForm.submit();
        }
    });

    // Collapse filter pada mobile
    if (window.innerWidth < 768) {
        const filterCollapse = document.getElementById('filterCollapse');
        const bsCollapse = new bootstrap.Collapse(filterCollapse, {
            toggle: false
        });
        bsCollapse.hide();
    }
</script>

</html>