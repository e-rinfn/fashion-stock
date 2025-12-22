<?php
// Aktifkan error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Validasi parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID pengiriman finishing tidak valid";
    header("Location: finishing.php");
    exit();
}

$id_hasil_kirim_finishing = intval($_GET['id']);

// Ambil data utama pengiriman finishing
$sql_main = "SELECT 
    hk.*,
    p.nama_produk,
    p.id_produk as id_produk_utama,
    pet.nama_petugas,
    pet.id_petugas_finishing,
    hk.tanggal_kirim_finishing,
    hk.status_finishing,
    hk.total_kirim,
    hk.tanggal_hasil_finishing
FROM hasil_kirim_finishing hk
LEFT JOIN produk p ON hk.id_produk = p.id_produk 
LEFT JOIN petugas_finishing pet ON hk.id_petugas_finishing = pet.id_petugas_finishing 
WHERE hk.id_hasil_kirim_finishing = ?";

$stmt = $conn->prepare($sql_main);
$stmt->bind_param("i", $id_hasil_kirim_finishing);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Data pengiriman finishing tidak ditemukan";
    header("Location: finishing.php");
    exit();
}

$main_data = $result->fetch_assoc();


// Ambil semua detail koko yang dikirim
$sql_details = "SELECT 
    dh.*,
    k.nama_koko,
    k.stok as stok_koko,
    k.id_produk as id_produk_koko,
    p.nama_produk as nama_produk_koko,
    p2.id_produk as id_produk_finishing,
    p2.nama_produk as nama_produk_finishing,
    -- Ambil petugas finishing dari pengiriman utama
    pf.nama_petugas as nama_petugas_finishing,
    hk.id_petugas_finishing as id_petugas_finishing_utama,
    -- Hitung total yang sudah diselesaikan sebelumnya
    COALESCE(SUM(dhfk.jumlah_selesai), 0) as sudah_selesai,
    COALESCE(SUM(dhfk.jumlah_rusak), 0) as sudah_rusak,
    COALESCE(dhfk.upah_per_unit, 0) as upah_sebelumnya
FROM detail_hasil_kirim_finishing dh
JOIN koko k ON dh.id_koko = k.id_koko
LEFT JOIN produk p ON k.id_produk = p.id_produk
LEFT JOIN produk p2 ON k.id_produk = p2.id_produk
JOIN hasil_kirim_finishing hk ON dh.id_hasil_kirim_finishing = hk.id_hasil_kirim_finishing
LEFT JOIN petugas_finishing pf ON hk.id_petugas_finishing = pf.id_petugas_finishing
LEFT JOIN detail_hasil_finishing_koko dhfk ON dh.id_detail_hasil_kirim_finishing = dhfk.id_detail_hasil_kirim_finishing
WHERE dh.id_hasil_kirim_finishing = ?
GROUP BY dh.id_detail_hasil_kirim_finishing, dh.id_koko, k.nama_koko, k.stok, k.id_produk, p.nama_produk, p2.id_produk, p2.nama_produk, pf.nama_petugas, hk.id_petugas_finishing, dhfk.upah_per_unit
ORDER BY k.nama_koko";

$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("i", $id_hasil_kirim_finishing);
$stmt_details->execute();
$details_result = $stmt_details->get_result();
$details_data = [];

while ($row = $details_result->fetch_assoc()) {
    $details_data[] = $row;
}

// Ambil data hasil finishing koko yang sudah ada
$sql_finishing_data = "SELECT 
    dhfk.*,
    k.nama_koko,
    p.nama_produk as nama_produk_koko,
    dh.jumlah as jumlah_dikirim
FROM detail_hasil_finishing_koko dhfk
JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
JOIN koko k ON dhfk.id_koko = k.id_koko
LEFT JOIN produk p ON k.id_produk = p.id_produk
WHERE dh.id_hasil_kirim_finishing = ?
ORDER BY k.nama_koko";

$stmt_finishing = $conn->prepare($sql_finishing_data);
$stmt_finishing->bind_param("i", $id_hasil_kirim_finishing);
$stmt_finishing->execute();
$finishing_result = $stmt_finishing->get_result();
$finishing_data = [];
$total_selesai_finishing = 0;
$total_rusak_finishing = 0;
$total_upah_finishing = 0;

while ($row = $finishing_result->fetch_assoc()) {
    $finishing_data[] = $row;
    $total_selesai_finishing += $row['jumlah_selesai'];
    $total_rusak_finishing += $row['jumlah_rusak'];
    $total_upah_finishing += $row['total_upah'];
}

$has_finishing = (count($finishing_data) > 0);

// Ambil tarif upah standar untuk finishing dari tabel tarif_upah
$sql_tarif = "SELECT tarif_per_unit FROM tarif_upah 
             WHERE jenis_tarif = 'finishing' 
             ORDER BY berlaku_sejak DESC LIMIT 1";
$tarif_result = $conn->query($sql_tarif);
$tarif_standar = ($tarif_result->num_rows > 0) ? $tarif_result->fetch_assoc()['tarif_per_unit'] : 0;

// AMBIL DAFTAR TARIF UPAH DARI TABEL tarif_upah UNTUK DROPDOWN
$sql_upah_dropdown = "SELECT tarif_per_unit, berlaku_sejak, keterangan
                     FROM tarif_upah 
                     WHERE jenis_tarif = 'finishing'
                     ORDER BY berlaku_sejak DESC, tarif_per_unit DESC";
$upah_dropdown_result = $conn->query($sql_upah_dropdown);
$upah_dropdown_options = [];

while ($row = $upah_dropdown_result->fetch_assoc()) {
    $upah_dropdown_options[] = [
        'tarif_per_unit' => $row['tarif_per_unit'] ?? 0,
        'berlaku_sejak' => $row['berlaku_sejak'] ?? date('Y-m-d'),
        'keterangan' => $row['keterangan'] ?? 'Tanpa Keterangan'
    ];
}

// Fungsi untuk mendapatkan tarif upah
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

    return 0;
}
?>

<style>
    .form-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .header-info {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .info-item {
        margin-bottom: 10px;
    }

    .info-label {
        font-weight: bold;
        color: #495057;
        min-width: 200px;
        display: inline-block;
    }

    .info-value {
        color: #212529;
    }

    .table-koko th {
        background-color: #e9ecef;
        font-weight: bold;
        font-size: 0.8rem;
        vertical-align: middle;
    }

    .table-koko td {
        font-size: 0.8rem;
        vertical-align: middle;
    }

    .btn-save {
        min-width: 150px;
    }

    .alert-container {
        margin-bottom: 20px;
    }

    .btn-batal-semua {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
        cursor: pointer !important;
    }

    .btn-batal-semua:hover {
        background-color: #c82333;
        border-color: #bd2130;
        cursor: pointer !important;
    }

    .upah-section {
        background-color: #e8f4fd;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 5px;
        border-left: 4px solid #0d6efd;
    }

    .upah-dropdown {
        width: 120px;
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }

    .upah-manual {
        width: 120px;
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }

    .upah-type-toggle {
        font-size: 0.7rem;
        cursor: pointer;
        color: #0d6efd;
        text-decoration: underline;
    }

    .petugas-info {
        background-color: #f0f9ff;
        padding: 5px;
        border-radius: 4px;
        border-left: 3px solid #0d6efd;
        font-size: 0.8rem;
    }

    .petugas-fixed {
        color: #0d6efd;
        font-weight: bold;
    }

    .petugas-note {
        font-size: 0.7rem;
        color: #6c757d;
        font-style: italic;
    }

    .upah-wrapper {
        min-width: 250px;
        max-width: 500px;
    }

    .table-koko .jumlah-input {
        width: 70px;
        margin: 0 auto;
        text-align: center;
    }

    .table-koko .upah-select {
        min-width: 140px;
        font-size: 0.8rem;
    }

    .table-koko .upah-input {
        min-width: 120px;
        font-size: 0.8rem;
    }

    .table-koko .btn-toggle-upah {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
    }

    .table-koko .upah-wrapper {
        min-width: 200px;
    }

    .table-koko tfoot {
        background-color: #f8f9fa;
    }

    .table-koko tfoot td {
        font-size: 0.9rem;
        padding: 12px 8px;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.25em 0.6em;
    }

    .form-control-sm {
        padding: 0.25rem 0.5rem;
    }

    .form-select {
        padding: 0.25rem 2.25rem 0.25rem 0.5rem;
    }

    .validation-message {
        font-size: 0.75rem;
        margin-top: 5px;
    }

    .valid-message {
        color: #198754;
    }

    .invalid-message {
        color: #dc3545;
    }

    .input-valid {
        border-color: #198754 !important;
        background-color: #f8fff9 !important;
    }

    .input-invalid {
        border-color: #dc3545 !important;
        background-color: #fff8f8 !important;
    }

    .warning-box {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .detail-box {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .detail-box h5 {
        color: #495057;
        border-bottom: 2px solid #0d6efd;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .summary-card {
        background-color: #e7f3ff;
        border: 1px solid #b6d4fe;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .summary-label {
        font-weight: 500;
        color: #495057;
    }

    .summary-value {
        font-weight: 600;
        color: #0d6efd;
    }

    .finishing-table th {
        background-color: #e9ecef;
        font-weight: 600;
    }

    .finishing-table td {
        vertical-align: middle;
    }

    .totals-row {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    .status-badge {
        font-size: 0.8rem;
        padding: 0.3em 0.6em;
    }

    /* Fix untuk tombol batal */
    .btn-batal-action {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
        cursor: pointer;
        padding: 8px 16px;
        font-size: 14px;
        border-radius: 4px;
        transition: all 0.3s;
    }

    .btn-batal-action:hover {
        background-color: #c82333;
        border-color: #bd2130;
        color: white;
        cursor: pointer;
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Hasil Finishing Koko</h2>
                    <div>
                        <a href="finishing_koko.php" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>

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

                <!-- Header Info -->
                <div hidden class="header-info">
                    <div class="row">
                        <div class="col-md-6">

                            <div class="info-item">
                                <span class="info-label">Produk Utama:</span>
                                <span class="info-value"><?= htmlspecialchars($main_data['nama_produk']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Petugas Finishing:</span>
                                <span class="info-value petugas-fixed"><?= htmlspecialchars($main_data['nama_petugas']) ?></span>
                                <small class="petugas-note">(Tetap sesuai pengiriman)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Tanggal Kirim:</span>
                                <span class="info-value"><?= date('d/m/Y', strtotime($main_data['tanggal_kirim_finishing'])) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Kirim:</span>
                                <span class="info-value"><?= $main_data['total_kirim'] ?> pcs</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status:</span>
                                <span class="info-value">
                                    <span class="badge bg-<?= $main_data['status_finishing'] == 'selesai' ? 'success' : ($main_data['status_finishing'] == 'diproses' ? 'warning' : 'secondary') ?> status-badge">
                                        <?= ucfirst($main_data['status_finishing']) ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAMPILAN DETAIL FINISHING JIKA SUDAH ADA -->
                <?php if ($has_finishing): ?>
                    <div class="detail-box">
                        <!-- Summary Card -->
                        <div class="summary-card">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="summary-item">
                                        <span class="summary-label">Tanggal Finishing:</span>
                                        <span class="summary-value">
                                            <?= dateIndo($finishing_data[0]['tanggal_finishing']) ?>
                                        </span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Petugas:</span>
                                        <span class="summary-value"><?= htmlspecialchars($main_data['nama_petugas']) ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="summary-item">
                                        <span class="summary-label">Total Selesai:</span>
                                        <span class="summary-value"><?= $total_selesai_finishing ?> pcs</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Total Kembali:</span>
                                        <span class="summary-value"><?= $total_rusak_finishing ?> pcs</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="summary-item">
                                        <span class="summary-label">Total Upah:</span>
                                        <span class="summary-value"><?= formatRupiah($total_upah_finishing) ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Status:</span>
                                        <span class="summary-value">
                                            <span class="badge bg-success status-badge">Selesai</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Table Detail Finishing -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover finishing-table">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th style="width: 28%">Jenis Koko</th>
                                        <th style="width: 10%">Dikirim</th>
                                        <th style="width: 10%">Selesai</th>
                                        <th style="width: 10%">Kembali</th>
                                        <th style="width: 14%">Upah / Unit</th>
                                        <th style="width: 18%">Total Upah</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($finishing_data as $finish): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($finish['nama_koko']) ?></td>
                                            <td class="text-center"><?= $finish['jumlah_dikirim'] ?> pcs</td>
                                            <td class="text-center">
                                                <span class="badge bg-success"><?= $finish['jumlah_selesai'] ?> pcs</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger"><?= $finish['jumlah_rusak'] ?> pcs</span>
                                            </td>
                                            <td class="text-center"><?= formatRupiah($finish['upah_per_unit']) ?></td>
                                            <td class="text-center fw-bold text-success"><?= formatRupiah($finish['total_upah']) ?></td>
                                            <!-- <td class="text-center">
                                                <?php if (!empty($finish['nama_produk_koko'])): ?>
                                                    <span class="badge bg-info"><?= htmlspecialchars($finish['nama_produk_koko']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">-</span>
                                                <?php endif; ?>
                                            </td> -->
                                            <!-- <td class="text-center"><?= dateIndo($finish['tanggal_finishing']) ?></td> -->
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="totals-row">
                                        <td class="text-end fw-bold">TOTAL:</td>
                                        <td class="text-center fw-bold">
                                            <?php
                                            $total_dikirim_finishing = 0;
                                            foreach ($finishing_data as $finish) {
                                                $total_dikirim_finishing += $finish['jumlah_dikirim'];
                                            }
                                            echo $total_dikirim_finishing . ' pcs';
                                            ?>
                                        </td>
                                        <td class="text-center fw-bold text-success"><?= $total_selesai_finishing ?> pcs</td>
                                        <td class="text-center fw-bold text-danger"><?= $total_rusak_finishing ?> pcs</td>
                                        <td class="text-center fw-bold">-</td>
                                        <td class="text-center fw-bold text-success"><?= formatRupiah($total_upah_finishing) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Informasi Produk -->
                        <div class="mt-4">
                            <h6><i class="ti ti-package"></i> Informasi Stok Produk</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <!-- <p class="mb-1"><strong>Produk Utama:</strong> <?= htmlspecialchars($main_data['nama_produk']) ?></p> -->
                                        <p class="mb-0"><strong>Ditambahkan ke Stok:</strong> <?= $total_selesai_finishing ?> pcs</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-warning">
                                        <p class="mb-1"><strong>Kembali ke Stok Koko Mentah:</strong></p>
                                        <p class="mb-0">
                                            <?php
                                            $koko_rusak_list = [];
                                            foreach ($finishing_data as $finish) {
                                                if ($finish['jumlah_rusak'] > 0) {
                                                    $koko_rusak_list[] = $finish['nama_koko'] . ' (' . $finish['jumlah_rusak'] . ' pcs)';
                                                }
                                            }
                                            if (!empty($koko_rusak_list)) {
                                                echo implode(", ", $koko_rusak_list);
                                            } else {
                                                echo 'Tidak ada koko kembali';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Batal -->
                        <div class="d-flex justify-content-end mt-4">
                            <button class="btn btn-batal-semua btn-batal-semua-koko"
                                data-id="<?= $id_hasil_kirim_finishing ?>"
                                title="Batalkan semua hasil finishing koko">
                                <i class="ti ti-trash"></i> Batalkan Semua Hasil
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Warning jika belum ada finishing -->
                    <!-- <div class="warning-box">
                        <h5><i class="ti ti-info-circle"></i> Informasi</h5>
                        <p class="mb-2">Hasil finishing untuk pengiriman ini belum diinput.</p>
                        <p class="mb-0"><strong>Perhatian:</strong> Input hasil finishing hanya bisa dilakukan sekali! Pastikan semua data sudah benar sebelum disimpan.</p>
                    </div> -->
                <?php endif; ?>

                <!-- Form Input Hasil Finishing Koko -->
                <?php if (!$has_finishing): ?>
                    <form method="POST" action="" id="formFinishingKoko">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="tanggal_finishing" class="form-label">
                                            <strong>Tanggal Finishing *</strong>
                                        </label>
                                        <input type="date"
                                            class="form-control"
                                            id="tanggal_finishing"
                                            name="tanggal_finishing"
                                            value="<?= date('Y-m-d') ?>"
                                            required>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="petugas-info mt-2">
                                            <strong>Petugas Finishing:</strong>
                                            <span class="petugas-fixed"><?= htmlspecialchars($main_data['nama_petugas']) ?></span>
                                            <small class="petugas-note">(Otomatis sesuai pengiriman, upah akan ditambahkan ke hutang petugas ini)</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover table-koko">
                                        <thead class="table-light">
                                            <tr class="text-center">
                                                <th width="15%">Jenis Koko</th>
                                                <th width="6%">Dikirim</th>
                                                <th width="8%">Selesai</th>
                                                <th width="8%">Kembali</th>
                                                <th width="6%">Total</th>
                                                <th hidden width="12%">Petugas Finishing</th>
                                                <th hidden width="18%">Upah per Unit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($details_data)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">Tidak ada data koko</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($details_data as $detail): ?>
                                                    <?php
                                                    $id_detail = $detail['id_detail_hasil_kirim_finishing'];
                                                    $jumlah_dikirim = $detail['jumlah'];
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <?= htmlspecialchars($detail['nama_koko']) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?= $jumlah_dikirim ?> pcs
                                                            <input type="hidden" id="jumlah_dikirim_<?= $id_detail ?>" value="<?= $jumlah_dikirim ?>">
                                                        </td>
                                                        <td>
                                                            <input type="number"
                                                                class="form-control form-control-sm jumlah-input text-center"
                                                                name="jumlah_selesai_<?= $id_detail ?>"
                                                                id="jumlah_selesai_<?= $id_detail ?>"
                                                                readonly
                                                                value="0"
                                                                min="0"
                                                                max="<?= $jumlah_dikirim ?>"
                                                                data-max="<?= $jumlah_dikirim ?>"
                                                                onchange="validateTotal(<?= $id_detail ?>)">
                                                        </td>
                                                        <td>
                                                            <input type="number"
                                                                class="form-control form-control-sm jumlah-input text-center"
                                                                name="jumlah_rusak_<?= $id_detail ?>"
                                                                id="jumlah_rusak_<?= $id_detail ?>"
                                                                readonly
                                                                value="0"
                                                                min="0"
                                                                max="<?= $jumlah_dikirim ?>"
                                                                data-max="<?= $jumlah_dikirim ?>"
                                                                onchange="validateTotal(<?= $id_detail ?>)">
                                                        </td>
                                                        <td class="text-center">
                                                            <span id="total_<?= $id_detail ?>" class="badge bg-secondary">
                                                                0 pcs
                                                            </span>
                                                            <div id="validation_<?= $id_detail ?>" class="validation-message"></div>
                                                        </td>
                                                        <td hidden class="text-center">
                                                            <span class="badge bg-primary">
                                                                <?= htmlspecialchars($main_data['nama_petugas']) ?>
                                                            </span>
                                                            <input type="hidden"
                                                                name="id_petugas_finishing_<?= $id_detail ?>"
                                                                value="<?= $main_data['id_petugas_finishing'] ?>">
                                                        </td>
                                                        <td hidden>
                                                            <div class="upah-wrapper" readonly>
                                                                <!-- mode input -->
                                                                <input type="hidden"
                                                                    name="upah_input_type_<?= $id_detail ?>"
                                                                    id="upah_input_type_<?= $id_detail ?>"
                                                                    value="dropdown">

                                                                <!-- DROPDOWN -->
                                                                <div class="input-group input-group-sm" id="upah_dropdown_<?= $id_detail ?>">
                                                                    <select name="upah_dropdown_<?= $id_detail ?>"
                                                                        class="form-select upah-select"
                                                                        onchange="updateTotalUpah(<?= $id_detail ?>)">
                                                                        <?php foreach ($upah_dropdown_options as $option): ?>
                                                                            <?php if ($option['tarif_per_unit'] > 0): ?>
                                                                                <option readonly value="<?= $option['tarif_per_unit'] ?>">
                                                                                    Rp <?= number_format($option['tarif_per_unit'], 0, ',', '.') ?>
                                                                                    <?= !empty($option['keterangan']) ? ' - ' . htmlspecialchars($option['keterangan']) : '' ?>
                                                                                </option>
                                                                            <?php endif; ?>
                                                                        <?php endforeach; ?>
                                                                    </select>

                                                                    <button type="button"
                                                                        class="m-1 btn btn-outline-secondary btn-toggle-upah"
                                                                        title="Input manual"
                                                                        onclick="toggleUpahInput(<?= $id_detail ?>)">
                                                                        <i class="ti ti-pencil"></i>
                                                                    </button>
                                                                </div>

                                                                <!-- MANUAL -->
                                                                <div class="input-group input-group-sm mt-1 d-none"
                                                                    id="upah_manual_<?= $id_detail ?>">
                                                                    <span class="input-group-text">Rp</span>
                                                                    <input type="number"
                                                                        name="upah_manual_<?= $id_detail ?>"
                                                                        id="upah_manual_input_<?= $id_detail ?>"
                                                                        readonly
                                                                        class="form-control upah-input"
                                                                        min="0"
                                                                        step="100"
                                                                        value="0"
                                                                        placeholder="Masukkan upah"
                                                                        onchange="updateTotalUpah(<?= $id_detail ?>)">

                                                                    <button type="button"
                                                                        class="m-1 btn btn-outline-secondary btn-toggle-upah"
                                                                        title="Pilih dari tarif"
                                                                        onclick="toggleUpahInput(<?= $id_detail ?>)">
                                                                        <i class="ti ti-list"></i>
                                                                    </button>
                                                                </div>

                                                                <!-- TOTAL -->
                                                                <div class="mt-1">
                                                                    <small class="text-muted">Total:</small>
                                                                    <div class="fw-bold text-success" id="total_upah_<?= $id_detail ?>">
                                                                        Rp 0
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">
                                                    <div class="pt-2">Total Upah Keseluruhan:</div>
                                                </td>
                                                <td class="text-start">
                                                    <div id="all_validation" class="validation-message mb-2"></div>
                                                    <div class="fw-bold fs-5 text-success" id="grand_total_upah">
                                                        Rp 0
                                                    </div>
                                                    <div class="small text-muted mt-1">
                                                        Total Upah: <?= htmlspecialchars($main_data['nama_petugas']) ?>
                                                    </div>
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
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
    // Fungsi untuk validasi total per baris
    function validateTotal(id_detail) {
        const inputSelesai = document.getElementById('jumlah_selesai_' + id_detail);
        const inputRusak = document.getElementById('jumlah_rusak_' + id_detail);
        const totalElement = document.getElementById('total_' + id_detail);
        const validationElement = document.getElementById('validation_' + id_detail);
        const jumlahDikirim = parseInt(document.getElementById('jumlah_dikirim_' + id_detail).value);

        const jumlahSelesai = parseInt(inputSelesai.value) || 0;
        const jumlahRusak = parseInt(inputRusak.value) || 0;
        const totalInput = jumlahSelesai + jumlahRusak;

        // Update total display
        totalElement.textContent = totalInput + ' pcs';

        // Validasi
        if (totalInput === jumlahDikirim) {
            // Valid - total sesuai
            inputSelesai.classList.remove('input-invalid');
            inputSelesai.classList.add('input-valid');
            inputRusak.classList.remove('input-invalid');
            inputRusak.classList.add('input-valid');
            totalElement.className = 'badge bg-success';
            validationElement.innerHTML = '<span class="valid-message">✓ Jumlah sesuai</span>';
        } else if (totalInput > jumlahDikirim) {
            // Invalid - melebihi
            inputSelesai.classList.add('input-invalid');
            inputSelesai.classList.remove('input-valid');
            inputRusak.classList.add('input-invalid');
            inputRusak.classList.remove('input-valid');
            totalElement.className = 'badge bg-danger';
            validationElement.innerHTML = '<span class="invalid-message">✗ Melebihi jumlah dikirim</span>';
        } else {
            // Invalid - kurang
            inputSelesai.classList.add('input-invalid');
            inputSelesai.classList.remove('input-valid');
            inputRusak.classList.add('input-invalid');
            inputRusak.classList.remove('input-valid');
            totalElement.className = 'badge bg-warning';
            validationElement.innerHTML = '<span class="invalid-message">✗ Belum mencapai jumlah dikirim</span>';
        }

        // Update total upah
        updateTotalUpah(id_detail);
        validateAllRows();
    }

    // Fungsi untuk validasi semua baris
    function validateAllRows() {
        let allValid = true;
        let totalRows = 0;
        let validRows = 0;

        <?php foreach ($details_data as $detail): ?>
            totalRows++;
            const jumlahDikirim_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                parseInt(document.getElementById('jumlah_dikirim_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value);
            const jumlahSelesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                parseInt(document.getElementById('jumlah_selesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value) || 0;
            const jumlahRusak_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                parseInt(document.getElementById('jumlah_rusak_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value) || 0;
            const totalInput_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                jumlahSelesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?> + jumlahRusak_<?= $detail['id_detail_hasil_kirim_finishing'] ?>;

            if (totalInput_<?= $detail['id_detail_hasil_kirim_finishing'] ?> === jumlahDikirim_<?= $detail['id_detail_hasil_kirim_finishing'] ?>) {
                validRows++;
            } else {
                allValid = false;
            }
        <?php endforeach; ?>

        const allValidationElement = document.getElementById('all_validation');

        if (allValid) {
            allValidationElement.innerHTML = '<span class="valid-message">✓ Semua koko sudah diproses dengan benar</span>';
        } else {
            allValidationElement.innerHTML = '<span class="invalid-message">✗ ' + validRows + ' dari ' + totalRows + ' koko sudah sesuai</span>';
        }

        return allValid;
    }

    function toggleUpahInput(id_detail) {
        const dropdownDiv = document.getElementById('upah_dropdown_' + id_detail);
        const manualDiv = document.getElementById('upah_manual_' + id_detail);
        const typeInput = document.getElementById('upah_input_type_' + id_detail);

        if (dropdownDiv.classList.contains('d-none')) {
            // Switch to dropdown
            dropdownDiv.classList.remove('d-none');
            manualDiv.classList.add('d-none');
            typeInput.value = 'dropdown';

            // Set default value jika manual kosong
            const manualValue = document.getElementById('upah_manual_input_' + id_detail).value;
            if (manualValue > 0) {
                const dropdownSelect = dropdownDiv.querySelector('select');
                dropdownSelect.value = manualValue;
            }
        } else {
            // Switch to manual
            dropdownDiv.classList.add('d-none');
            manualDiv.classList.remove('d-none');
            typeInput.value = 'manual';

            // Copy value dari dropdown ke manual
            const dropdownSelect = dropdownDiv.querySelector('select');
            const manualInput = document.getElementById('upah_manual_input_' + id_detail);
            manualInput.value = dropdownSelect.value;
        }

        // Update total upah
        updateTotalUpah(id_detail);
    }

    // Fungsi untuk update total upah per baris
    function updateTotalUpah(id_detail) {
        const jumlahSelesai = parseInt(document.getElementById('jumlah_selesai_' + id_detail).value) || 0;
        let upahPerUnit = 0;

        // Get upah value based on input type
        const inputType = document.getElementById('upah_input_type_' + id_detail).value;

        if (inputType === 'dropdown') {
            const dropdown = document.querySelector('select[name="upah_dropdown_' + id_detail + '"]');
            upahPerUnit = parseFloat(dropdown.value) || 0;
        } else {
            const manualInput = document.getElementById('upah_manual_input_' + id_detail);
            upahPerUnit = parseFloat(manualInput.value) || 0;
        }

        const totalUpah = jumlahSelesai * upahPerUnit;
        const totalUpahElement = document.getElementById('total_upah_' + id_detail);
        totalUpahElement.textContent = formatRupiahJS(totalUpah);

        // Update warna berdasarkan nilai
        if (totalUpah > 0) {
            totalUpahElement.className = 'fw-bold text-success';
        } else {
            totalUpahElement.className = 'fw-bold text-muted';
        }

        // Update grand total
        updateGrandTotalUpah();
    }

    function updateGrandTotalUpah() {
        let grandTotal = 0;

        // Loop through all details to calculate grand total
        <?php foreach ($details_data as $detail): ?>
            const jumlahSelesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                parseInt(document.getElementById('jumlah_selesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value) || 0;

            let upahPerUnit_<?= $detail['id_detail_hasil_kirim_finishing'] ?> = 0;
            const inputType_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                document.getElementById('upah_input_type_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value;

            if (inputType_<?= $detail['id_detail_hasil_kirim_finishing'] ?> === 'dropdown') {
                const dropdown_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                    document.querySelector('select[name="upah_dropdown_<?= $detail['id_detail_hasil_kirim_finishing'] ?>"]');
                upahPerUnit_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                    parseFloat(dropdown_<?= $detail['id_detail_hasil_kirim_finishing'] ?>.value) || 0;
            } else {
                const manualInput_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                    document.getElementById('upah_manual_input_<?= $detail['id_detail_hasil_kirim_finishing'] ?>');
                upahPerUnit_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                    parseFloat(manualInput_<?= $detail['id_detail_hasil_kirim_finishing'] ?>.value) || 0;
            }

            grandTotal += jumlahSelesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?> * upahPerUnit_<?= $detail['id_detail_hasil_kirim_finishing'] ?>;
        <?php endforeach; ?>

        const grandTotalElement = document.getElementById('grand_total_upah');
        grandTotalElement.textContent = formatRupiahJS(grandTotal);

        // Update warna grand total
        if (grandTotal > 0) {
            grandTotalElement.className = 'fw-bold fs-5 text-success';
        } else {
            grandTotalElement.className = 'fw-bold fs-5 text-muted';
        }
    }

    // Konfirmasi batal semua hasil finishing
    $(document).on('click', '.btn-batal-semua-koko', function() {

        Swal.fire({
            title: 'Batalkan Semua Finishing?',
            html: `
            <p class="text-danger mt-2">
                <strong>Aksi ini tidak dapat dibatalkan!</strong>
            </p>
        `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Batalkan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href =
                    'hasil_finishing_koko.php?id=<?= $id_hasil_kirim_finishing ?>&action=batal_hasil_koko';
            }
        });

    });


    // Initialize semua saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($details_data as $detail): ?>
            validateTotal(<?= $detail['id_detail_hasil_kirim_finishing'] ?>);
            updateTotalUpah(<?= $detail['id_detail_hasil_kirim_finishing'] ?>);
        <?php endforeach; ?>

        // Validasi form sebelum submit
        <?php if (!$has_finishing): ?>
            document.getElementById('formFinishingKoko').addEventListener('submit', function(e) {
                let allValid = validateAllRows();

                // Validasi tanggal
                const tanggal = document.getElementById('tanggal_finishing').value;
                if (!tanggal) {
                    e.preventDefault();
                    alert('Tanggal finishing harus diisi');
                    return false;
                }

                // Validasi minimal ada 1 input
                let totalSelesai = 0;
                <?php foreach ($details_data as $detail): ?>
                    totalSelesai += parseInt(document.getElementById('jumlah_selesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value) || 0;
                <?php endforeach; ?>

                if (totalSelesai === 0) {
                    e.preventDefault();
                    alert('Minimal ada 1 hasil finishing selesai');
                    return false;
                }

                // Validasi semua koko harus diproses
                if (!allValid) {
                    e.preventDefault();
                    alert('Semua koko harus diproses! Pastikan total (selesai + kembali) sama dengan jumlah dikirim untuk setiap koko.');
                    return false;
                }

                // Konfirmasi sebelum submit
                // const message = 'Anda yakin ingin menyimpan hasil finishing?\n\n' +
                //     'Petugas: <?= htmlspecialchars($main_data['nama_petugas']) ?>\n' +
                //     'Hasil finishing selesai akan ditambahkan ke stok produk.\n' +
                //     'Hasil finishing rusak akan dikembalikan ke stok koko.\n' +
                //     'Upah akan ditambahkan ke hutang petugas: <?= htmlspecialchars($main_data['nama_petugas']) ?>\n\n' +
                //     'Catatan: Hasil finishing hanya bisa diinput sekali!';

                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        <?php endif; ?>

        // Event listener untuk tombol batal dengan ID yang jelas
        document.getElementById('btnBatalFinishing').addEventListener('click', function(e) {
            e.preventDefault();

            const id = this.getAttribute('data-id');
            const tanggal = this.getAttribute('data-tanggal');
            const selesai = this.getAttribute('data-selesai');
            const rusak = this.getAttribute('data-rusak');
            const upah = this.getAttribute('data-upah');

            Swal.fire({
                title: 'Batalkan Hasil Finishing?',
                html: `<div class="text-left">
                      <p>Apakah Anda yakin ingin membatalkan <strong>SEMUA</strong> hasil finishing untuk:</p>
                      <ul>
                        <li><strong>Tanggal Finishing:</strong> ${tanggal}</li>
                        <li><strong>Petugas:</strong> <?= htmlspecialchars($main_data['nama_petugas']) ?></li>
                        <li><strong>Total Selesai:</strong> ${selesai} pcs</li>
                        <li><strong>Total Rusak:</strong> ${rusak} pcs</li>
                        <li><strong>Total Upah:</strong> ${formatRupiahJS(upah)}</li>
                      </ul>
                      <p class="text-danger mt-3"><strong>PERINGATAN:</strong></p>
                      <ul class="text-danger">
                        <li>Semua stok produk yang sudah ditambahkan akan dikurangi</li>
                        <li>Semua stok koko akan dikembalikan</li>
                        <li>Semua hutang upah petugas <?= htmlspecialchars($main_data['nama_petugas']) ?> akan dikurangi</li>
                        <li>Status akan kembali ke "pengiriman"</li>
                        <li><strong>Aksi ini tidak dapat dibatalkan!</strong></li>
                      </ul>
                    </div>`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Batalkan!',
                cancelButtonText: 'Batal',
                width: '600px'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'hasil_finishing_koko.php?id=' + id + '&action=batal_hasil_koko';
                }
            });
        });
    });

    // Fungsi helper untuk format rupiah di JavaScript
    function formatRupiahJS(angka) {
        if (!angka || angka == '0') return 'Rp 0';

        // Pastikan angka adalah number
        angka = parseFloat(angka);
        if (isNaN(angka)) return 'Rp 0';

        const number_string = angka.toString().replace(/[^,\d]/g, '');
        const split = number_string.split(',');
        const sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        const ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            const separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
        return 'Rp ' + rupiah;
    }
</script>

</html>