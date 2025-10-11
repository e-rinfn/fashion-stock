<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';

function dateIndo($tanggal)
{
    $bulanIndo = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $tanggal = date('Y-m-d', strtotime($tanggal));
    $pecah = explode('-', $tanggal);
    return $pecah[2] . ' ' . $bulanIndo[(int)$pecah[1]] . ' ' . $pecah[0];
}


// Fungsi untuk mendapatkan tarif upah terkini
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
    return 700.00;
}

// Ambil semua produk untuk dropdown
$produk = query("SELECT * FROM produk");
$pemotong = query("SELECT * FROM pemotong");
$penjahit = query("SELECT * FROM penjahit");

// Cek filter yang diterapkan
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Query untuk mengambil data produksi
$sql = "SELECT h.*, p.nama_produk, pem.nama_pemotong, 
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

$sql .= " ORDER BY h.tanggal_hasil_potong DESC";

$produksi = query($sql);

// Gabungkan data produksi untuk tampilan dengan perhitungan upah
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
        'seri' => $prod['seri'],
        'pemotong' => $prod['nama_pemotong'],
        'penjahit' => $prod['nama_penjahit'],
        'id_penjahit' => $prod['id_penjahit'],
        'status' => $prod['status_potong'],
        'total_hasil' => $prod['total_hasil'],
        'total_harga' => $prod['total_harga'],
        'tanggal_hasil_jahit' => $prod['tanggal_hasil_jahit'],
        'total_hasil_jahit' => $prod['total_hasil_jahit'],
        'upah_pemotong' => $upah_pemotong,
        'upah_penjahit' => $upah_penjahit,
        'total_upah' => $total_upah,
        'rate_pemotong' => $tarif_pemotong,
        'rate_penjahit' => $tarif_penjahit
    ];
}

// Urutkan berdasarkan tanggal descending
usort($all_data, function ($a, $b) {
    return strtotime($b['tanggal']) - strtotime($a['tanggal']);
});

// PROSES INPUT PENJAHITAN JIKA ADA FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Simpan/Update Penjahitan
    if (isset($_POST['simpan_penjahitan'])) {
        $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);
        $id_penjahit = intval($_POST['id_penjahit']);
        $tanggal_hasil_jahit = $conn->real_escape_string($_POST['tanggal_hasil_jahit']);
        $total_hasil_jahit = intval($_POST['total_hasil_jahit']);

        // Validasi total hasil jahit
        if ($total_hasil_jahit <= 0) {
            $error_modal = "Total hasil jahit harus lebih dari 0";
        } else {
            // Ambil data produksi
            $produksi_data = query("SELECT id_produk, total_hasil FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix")[0];
            $id_produk = $produksi_data['id_produk'];
            $total_hasil_potong = $produksi_data['total_hasil'];

            // Validasi tidak melebihi total hasil potong
            if ($total_hasil_jahit > $total_hasil_potong) {
                $error_modal = "Total hasil jahit tidak boleh melebihi total hasil potong ($total_hasil_potong Pcs)";
            } else {
                $conn->autocommit(FALSE);
                try {
                    // HITUNG UPAH PENJAHIT SEBELUM TRANSAKSI
                    $tarif_penjahit = getTarifUpah('penjahitan', $tanggal_hasil_jahit);
                    $upah_penjahit = $total_hasil_jahit * $tarif_penjahit;

                    // 1. Update data penjahitan
                    $sql_update = "UPDATE hasil_potong_fix 
                              SET id_penjahit = $id_penjahit, 
                                  tanggal_hasil_jahit = '$tanggal_hasil_jahit', 
                                  total_hasil_jahit = $total_hasil_jahit,
                                  status_potong = 'selesai'
                              WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                    if (!$conn->query($sql_update)) {
                        throw new Exception("Gagal update data penjahitan: " . $conn->error);
                    }

                    // 2. Update stok produk
                    $sql_update_stok = "UPDATE produk 
                                   SET stok = stok + $total_hasil_jahit 
                                   WHERE id_produk = $id_produk";

                    if (!$conn->query($sql_update_stok)) {
                        throw new Exception("Gagal update stok produk: " . $conn->error);
                    }

                    // 3. Catat hutang upah penjahit
                    if (!catatHutangUpah($id_penjahit, 'penjahit', $tanggal_hasil_jahit, $upah_penjahit)) {
                        throw new Exception("Gagal mencatat hutang upah penjahit");
                    }

                    $conn->commit();
                    $conn->autocommit(TRUE);

                    $_SESSION['success'] = "Data penjahitan berhasil disimpan. Stok produk bertambah +$total_hasil_jahit. Upah penjahit: " . formatRupiah($upah_penjahit);
                    header("Location: list.php");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $conn->autocommit(TRUE);
                    $error_modal = $e->getMessage();
                }
            }
        }
    }

    // Batal Penjahitan
    // if (isset($_POST['batal_penjahitan'])) {
    //     $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);

    //     // Ambil data sebelum dibatalkan
    //     $produksi_data = query("SELECT id_produk, total_hasil_jahit, id_penjahit, tanggal_hasil_jahit FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix")[0];
    //     $id_produk = $produksi_data['id_produk'];
    //     $total_hasil_jahit = $produksi_data['total_hasil_jahit'];
    //     $id_penjahit = $produksi_data['id_penjahit'];
    //     $tanggal_hasil_jahit = $produksi_data['tanggal_hasil_jahit'];

    //     $conn->autocommit(FALSE);
    //     try {

    if (isset($_POST['batal_penjahitan'])) {
        $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);

        // Ambil data sebelum dibatalkan
        $produksi_data = query("SELECT id_produk, total_hasil_jahit, id_penjahit, tanggal_hasil_jahit FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix")[0];
        $id_produk = $produksi_data['id_produk'];
        $total_hasil_jahit = $produksi_data['total_hasil_jahit'];
        $id_penjahit = $produksi_data['id_penjahit'];
        $tanggal_hasil_jahit = $produksi_data['tanggal_hasil_jahit'];

        // CEK APAKAH UPAH SUDAH DIBERIKAN
        $periode = date('Y-m-01', strtotime($tanggal_hasil_jahit));
        $check_upah_dibayar = $conn->prepare("
            SELECT COUNT(*) as total_pembayaran 
            FROM pembayaran_upah_2 pu
            JOIN hutang_upah hu ON pu.id_hutang = hu.id_hutang
            WHERE hu.id_karyawan = ? 
            AND hu.jenis_karyawan = 'penjahit' 
            AND hu.periode = ?
            AND hu.total_dibayar > 0
        ");
        $check_upah_dibayar->bind_param("is", $id_penjahit, $periode);
        $check_upah_dibayar->execute();
        $result_upah = $check_upah_dibayar->get_result();
        $upah_dibayar = $result_upah->fetch_assoc()['total_pembayaran'] > 0;
        $check_upah_dibayar->close();

        if ($upah_dibayar) {
            $_SESSION['error'] = "Tidak dapat membatalkan penjahitan karena upah penjahit untuk produksi ini sudah dibayar. Silakan batalkan pembayaran upah penjahitan terlebih dahulu.";
            header("Location: list.php");
            exit();
        }

        $conn->autocommit(FALSE);
        try {
            // HITUNG UPAH YANG AKAN DIHAPUS (jika ada)
            $upah_dihapus = 0;
            if ($total_hasil_jahit > 0 && $id_penjahit > 0) {
                $tarif_penjahit = getTarifUpah('penjahitan', $tanggal_hasil_jahit);
                $upah_dihapus = $total_hasil_jahit * $tarif_penjahit;
            }

            // 1. Hapus data penjahitan (set ke NULL)
            $sql_batal = "UPDATE hasil_potong_fix 
                     SET id_penjahit = NULL, 
                         tanggal_hasil_jahit = NULL, 
                         total_hasil_jahit = NULL,
                         status_potong = 'diproses'
                     WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

            if (!$conn->query($sql_batal)) {
                throw new Exception("Gagal membatalkan data penjahitan: " . $conn->error);
            }

            // 2. Kurangi stok produk (jika ada total hasil jahit)
            if ($total_hasil_jahit > 0) {
                $sql_kurangi_stok = "UPDATE produk 
                                SET stok = stok - $total_hasil_jahit 
                                WHERE id_produk = $id_produk";

                if (!$conn->query($sql_kurangi_stok)) {
                    throw new Exception("Gagal mengurangi stok produk: " . $conn->error);
                }
            }

            // 3. Hapus/Update hutang upah penjahit (jika ada)
            if ($upah_dihapus > 0) {
                // Cari hutang yang terkait
                $periode = date('Y-m-01', strtotime($tanggal_hasil_jahit));
                $check_hutang = $conn->prepare("SELECT id_hutang, total_upah, sisa_hutang FROM hutang_upah 
                                              WHERE id_karyawan = ? AND jenis_karyawan = 'penjahit' AND periode = ?");
                $check_hutang->bind_param("is", $id_penjahit, $periode);
                $check_hutang->execute();
                $result_hutang = $check_hutang->get_result();

                if ($result_hutang->num_rows > 0) {
                    $hutang = $result_hutang->fetch_assoc();
                    $total_upah_baru = $hutang['total_upah'] - $upah_dihapus;
                    $sisa_hutang_baru = $hutang['sisa_hutang'] - $upah_dihapus;

                    if ($total_upah_baru <= 0) {
                        // Hapus record hutang jika total upah menjadi 0
                        $delete_hutang = $conn->prepare("DELETE FROM hutang_upah WHERE id_hutang = ?");
                        $delete_hutang->bind_param("i", $hutang['id_hutang']);
                        $delete_hutang->execute();
                    } else {
                        // Update hutang yang sudah ada
                        $update_hutang = $conn->prepare("UPDATE hutang_upah SET total_upah = ?, sisa_hutang = ? 
                                                      WHERE id_hutang = ?");
                        $update_hutang->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);
                        $update_hutang->execute();
                    }
                }
            }

            $conn->commit();
            $conn->autocommit(TRUE);

            $pesan_success = "Data penjahitan berhasil dibatalkan" .
                ($total_hasil_jahit > 0 ? " dan stok produk dikurangi -$total_hasil_jahit" : "") .
                ($upah_dihapus > 0 ? ". Upah penjahit dikurangi: " . formatRupiah($upah_dihapus) : "");

            $_SESSION['success'] = $pesan_success;
            header("Location: list.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $conn->autocommit(TRUE);
            $error_modal = "Gagal membatalkan data penjahitan: " . $e->getMessage();
        }
    }
}
?>

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
                    <h2>Master Data Produksi</h2>
                    <div>
                        <a href="new.php" class="btn btn-success">
                            <i class="ti ti-circle-plus"></i> Tambah Produksi
                        </a>
                    </div>
                </div>

                <!-- Filter Form -->
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
                    <div class="col-md-2">
                        <label class="form-label">Filter Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= ($status == 'all') ? 'selected' : '' ?>>Semua Status</option>
                            <option value="diproses" <?= ($status == 'diproses') ? 'selected' : '' ?>>Diproses</option>
                            <option value="selesai" <?= ($status == 'selesai') ? 'selected' : '' ?>>Selesai</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="ti ti-filter"></i> Filter
                        </button>
                        <?php if ($id_produk > 0 || $status != 'all' || !empty($start_date) || !empty($end_date)): ?>
                            <a href="list.php" class="btn btn-secondary me-2">
                                <i class="ti ti-rotate"></i> Reset
                            </a>
                        <?php endif; ?>

                        <!-- Tombol Print PDF -->
                        <button type="button" class="btn btn-danger" id="btnPrintPDF">
                            <i class="ti ti-file-text"></i> Print PDF
                        </button>
                    </div>
                </form>

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
                    <!-- /Tampilkan pesan error atau success -->

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <!-- <th class="align-middle" style="width: 30px;">No</th> -->
                                    <th class="bg-warning text-white align-middle">Seri</th>
                                    <th class="bg-warning text-white align-middle">Pemotong</th>
                                    <th class="bg-warning text-white align-middle">Tgl Potong</th>
                                    <th class="bg-warning text-white align-middle">Produk</th>
                                    <th class="bg-warning text-white align-middle">Hasil Potong</th>
                                    <th class="upah-column align-middle">Upah Pemotong</th>
                                    <th class="align-middle">Status</th>
                                    <th class="bg-info text-white align-middle">Tgl Jahit</th>
                                    <th class="bg-info text-white align-middle">Penjahit</th>
                                    <th class="bg-info text-white align-middle">Hasil Jahit</th>
                                    <th class="upah-column align-middle">Upah Penjahit</th>
                                    <th class="upah-column align-middle">Total Upah</th>
                                    <th class="align-middle">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (empty($all_data)): ?>
                                    <tr>
                                        <td colspan="14" class="text-center">Tidak ada data produksi</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($all_data as $data): ?>
                                        <tr>
                                            <!-- <td class="text-center"><?= $no++ ?></td> -->
                                            <td class="text-center"><?= htmlspecialchars($data['seri']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($data['pemotong']) ?>
                                                <br><small class="tarif-info"><?= formatRupiah($data['rate_pemotong']) ?>/pcs</small>
                                            </td>
                                            <td><?= dateIndo($data['tanggal']) ?></td>
                                            <td><?= htmlspecialchars($data['produk']) ?></td>
                                            <td class="text-center"><?= $data['total_hasil'] ?> Pcs</td>
                                            <td class="text-center upah-column">
                                                <?= formatRupiah($data['upah_pemotong']) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $data['status'] == 'selesai' ? 'success' : 'warning' ?> p-1 fw-normal">
                                                    <?= ucfirst($data['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= !empty($data['tanggal_hasil_jahit']) ? dateIndo($data['tanggal_hasil_jahit']) : '-' ?>
                                            </td>
                                            <td class="">
                                                <?php if (!empty($data['penjahit'])): ?>
                                                    <?= htmlspecialchars($data['penjahit']) ?>
                                                    <br><small class="tarif-info"><?= formatRupiah($data['rate_penjahit']) ?>/pcs</small>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= !empty($data['total_hasil_jahit']) ? $data['total_hasil_jahit'] . ' Pcs' : '-' ?>
                                            </td>
                                            <td class="text-center upah-column">
                                                <?= !empty($data['total_hasil_jahit']) ? formatRupiah($data['upah_penjahit']) : '-' ?>
                                            </td>
                                            <td class="text-center upah-column fw-bold">
                                                <?= formatRupiah($data['total_upah']) ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group-actions text-center">
                                                    <!-- Tombol Detail -->
                                                    <a href="detail.php?id=<?= $data['id'] ?>" class="btn btn-sm btn-primary" title="Detail">
                                                        <i class="ti ti-eye"></i>
                                                    </a>

                                                    <?php
                                                    // Cek status produksi
                                                    $status = $data['status'];              // misal: 'diproses', 'penjahitan', 'selesai'
                                                    ?>

                                                    <?php if ($status == 'diproses'): ?>
                                                        <!-- Tombol Input Penjahitan -->
                                                        <button class="btn btn-sm btn-info btn-input-penjahitan"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            data-total-potong="<?= $data['total_hasil'] ?>"
                                                            data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                            title="Input Penjahitan">
                                                            <i class="ti ti-pencil"></i>
                                                        </button>

                                                        <!-- <button class="btn btn-sm btn-danger btn-batal" data-id="<?= $data['id'] ?>" title="Batalkan Produksi">
                                                            <i class="ti ti-trash"></i>
                                                        </button> -->

                                                        <!-- <button class="btn btn-sm btn-danger btn-batal"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-pemotong="<?= htmlspecialchars($data['pemotong']) ?>"
                                                            title="Batalkan Produksi">
                                                            <i class="ti ti-trash"></i>
                                                        </button> -->

                                                        <button class="btn btn-sm btn-danger btn-batal"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-pemotong="<?= htmlspecialchars($data['pemotong']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            title="Batalkan Produksi">
                                                            <i class="ti ti-trash"></i>
                                                        </button>

                                                    <?php elseif ($status == 'penjahitan' && $status != 'selesai'): ?>
                                                        <!-- Tombol Edit Penjahitan (hanya jika belum selesai) -->
                                                        <button class="btn btn-sm btn-info btn-edit-penjahitan"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            data-total-potong="<?= $data['total_hasil'] ?>"
                                                            data-penjahit="<?= $data['id_penjahit'] ?>"
                                                            data-tanggal-jahit="<?= $data['tanggal_hasil_jahit'] ?>"
                                                            data-total-jahit="<?= $data['total_hasil_jahit'] ?>"
                                                            data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                            title="Edit Penjahitan">
                                                            <i class="ti ti-pencil"></i>
                                                        </button>

                                                        <!-- Tombol Batal Penjahitan -->
                                                        <button class="btn btn-sm btn-outline-danger btn-batal-penjahitan"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            title="Batal Penjahitan">
                                                            <i class="ti ti-arrow-back"></i>
                                                        </button>

                                                    <?php elseif ($status == 'selesai'): ?>
                                                        <!-- Jika penjahitan sudah selesai, hanya tampilkan tombol batal -->
                                                        <button class="btn btn-sm btn-outline-danger btn-batal-penjahitan"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            title="Batal Penjahitan">
                                                            <i class="ti ti-arrow-back"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Input/Edit Penjahitan -->
    <div class="modal fade" id="modalPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Input Data Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formPenjahitan">
                    <div class="modal-body">
                        <?php if (isset($error_modal)): ?>
                            <div class="alert alert-danger"><?= $error_modal ?></div>
                        <?php endif; ?>

                        <input type="hidden" name="id_hasil_potong_fix" id="modal_id_hasil_potong">
                        <input type="hidden" id="modal_tanggal_potong">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Produk</label>
                                <input type="text" class="form-control" id="modal_produk" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Seri</label>
                                <input type="text" class="form-control" id="modal_seri" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Potong</label>
                            <input type="text" class="form-control" id="modal_total_potong" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Penjahit <span class="text-danger">*</span></label>
                            <select name="id_penjahit" class="form-control" id="modal_penjahit" required>
                                <option value="">-- Pilih Penjahit --</option>
                                <?php foreach ($penjahit as $j): ?>
                                    <option value="<?= $j['id_penjahit'] ?>">
                                        <?= htmlspecialchars($j['nama_penjahit']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Hasil Jahit <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_hasil_jahit" class="form-control"
                                id="modal_tanggal_jahit" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Jahit (Pcs) <span class="text-danger">*</span></label>
                            <input type="number" name="total_hasil_jahit" class="form-control"
                                min="1" max="" id="input_total_jahit" required>
                            <small class="text-muted">Maksimal: <span id="max_total_jahit">0</span> Pcs</small>
                        </div>

                        <div hidden class="mb-3">
                            <label class="form-label">Perkiraan Upah Penjahit</label>
                            <input type="text" class="form-control" id="modal_perkiraan_upah" readonly>
                            <small class="text-muted" id="modal_rate_upah"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="simpan_penjahitan" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Batal Penjahitan -->
    <div class="modal fade" id="modalBatalPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Batal Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formBatalPenjahitan">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="batal_modal_id">
                        <p>Apakah Anda yakin ingin membatalkan data penjahitan untuk:</p>
                        <p><strong>Produk:</strong> <span id="batal_modal_produk"></span></p>
                        <p><strong>Seri:</strong> <span id="batal_modal_seri"></span></p>
                        <p class="text-danger"><strong>Data penjahitan akan dihapus dan status akan dikembalikan ke "Diproses".</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="batal_penjahitan" class="btn btn-danger">Ya, Batalkan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- SweetAlert2 & Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal instances
        const modalPenjahitan = new bootstrap.Modal(document.getElementById('modalPenjahitan'));
        const modalBatalPenjahitan = new bootstrap.Modal(document.getElementById('modalBatalPenjahitan'));
        const maxTotalJahit = document.getElementById('max_total_jahit');
        const inputTotalJahit = document.getElementById('input_total_jahit');
        const modalPerkiraanUpah = document.getElementById('modal_perkiraan_upah');
        const modalRateUpah = document.getElementById('modal_rate_upah');
        const modalTanggalPotong = document.getElementById('modal_tanggal_potong');

        // Fungsi untuk mendapatkan tarif upah via AJAX
        async function getTarifUpah(jenis_tarif, tanggal) {
            try {
                const response = await fetch(`ajax_get_tarif.php?jenis_tarif=${jenis_tarif}&tanggal=${tanggal}`);
                const data = await response.json();
                return data.tarif_per_unit || 0;
            } catch (error) {
                console.error('Error getting tarif:', error);
                return 0;
            }
        }

        // Fungsi untuk menghitung perkiraan upah
        async function hitungPerkiraanUpah() {
            const totalJahit = parseInt(inputTotalJahit.value) || 0;
            const tanggalJahit = document.getElementById('modal_tanggal_jahit').value;
            const tanggalPotong = modalTanggalPotong.value;

            // Gunakan tanggal jahit jika ada, jika tidak gunakan tanggal potong
            const tanggalReferensi = tanggalJahit || tanggalPotong;

            if (totalJahit > 0 && tanggalReferensi) {
                const tarifPenjahit = await getTarifUpah('penjahit', tanggalReferensi);
                const totalUpah = totalJahit * tarifPenjahit;

                modalPerkiraanUpah.value = 'Rp ' + totalUpah.toLocaleString('id-ID');
                modalRateUpah.textContent = 'Rate: Rp ' + tarifPenjahit.toLocaleString('id-ID') + ' per pcs';
            } else {
                modalPerkiraanUpah.value = '';
                modalRateUpah.textContent = '';
            }
        }

        // Event listener untuk perubahan total jahit dan tanggal jahit
        inputTotalJahit.addEventListener('input', hitungPerkiraanUpah);
        document.getElementById('modal_tanggal_jahit').addEventListener('change', hitungPerkiraanUpah);

        // Tombol batal produksi
        // document.querySelectorAll('.btn-batal').forEach(button => {
        //     button.addEventListener('click', function() {
        //         const id = this.getAttribute('data-id');
        //         Swal.fire({
        //             title: 'Yakin ingin membatalkan produksi ini?',
        //             text: "Tindakan ini tidak dapat dikembalikan!",
        //             icon: 'warning',
        //             showCancelButton: true,
        //             confirmButtonColor: '#d33',
        //             cancelButtonColor: '#6c757d',
        //             confirmButtonText: 'Ya, batalkan!',
        //             cancelButtonText: 'Batal'
        //         }).then((result) => {
        //             if (result.isConfirmed) {
        //                 window.location.href = 'batal.php?id=' + id;
        //             }
        //         });
        //     });
        // });

        // Tombol Input Penjahitan (Baru)
        document.querySelectorAll('.btn-input-penjahitan').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const produk = this.getAttribute('data-produk');
                const seri = this.getAttribute('data-seri');
                const totalPotong = this.getAttribute('data-total-potong');
                const tanggalPotong = this.getAttribute('data-tanggal-potong');

                // Set nilai modal
                document.getElementById('modalTitle').textContent = 'Input Data Penjahitan';
                document.getElementById('modal_id_hasil_potong').value = id;
                document.getElementById('modal_produk').value = produk;
                document.getElementById('modal_seri').value = seri;
                document.getElementById('modal_total_potong').value = totalPotong + ' Pcs';
                document.getElementById('modal_penjahit').value = '';
                document.getElementById('modal_tanggal_jahit').value = '<?= date('Y-m-d') ?>';
                modalTanggalPotong.value = tanggalPotong;

                // Set maksimal total jahit
                maxTotalJahit.textContent = totalPotong;
                inputTotalJahit.max = totalPotong;
                inputTotalJahit.value = totalPotong;

                // Reset perkiraan upah
                modalPerkiraanUpah.value = '';
                modalRateUpah.textContent = '';

                // Hitung perkiraan upah
                setTimeout(hitungPerkiraanUpah, 100);

                // Tampilkan modal
                modalPenjahitan.show();
            });
        });

        // Tombol Edit Penjahitan (Sudah ada data)
        document.querySelectorAll('.btn-edit-penjahitan').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const produk = this.getAttribute('data-produk');
                const seri = this.getAttribute('data-seri');
                const totalPotong = this.getAttribute('data-total-potong');
                const penjahit = this.getAttribute('data-penjahit');
                const tanggalJahit = this.getAttribute('data-tanggal-jahit');
                const totalJahit = this.getAttribute('data-total-jahit');
                const tanggalPotong = this.getAttribute('data-tanggal-potong');

                // Set nilai modal
                document.getElementById('modalTitle').textContent = 'Edit Data Penjahitan';
                document.getElementById('modal_id_hasil_potong').value = id;
                document.getElementById('modal_produk').value = produk;
                document.getElementById('modal_seri').value = seri;
                document.getElementById('modal_total_potong').value = totalPotong + ' Pcs';
                document.getElementById('modal_penjahit').value = penjahit;
                document.getElementById('modal_tanggal_jahit').value = tanggalJahit;
                modalTanggalPotong.value = tanggalPotong;

                // Set maksimal total jahit
                maxTotalJahit.textContent = totalPotong;
                inputTotalJahit.max = totalPotong;
                inputTotalJahit.value = totalJahit;

                // Hitung perkiraan upah
                setTimeout(hitungPerkiraanUpah, 100);

                // Tampilkan modal
                modalPenjahitan.show();
            });
        });

        // Tombol Batal Penjahitan
        // document.querySelectorAll('.btn-batal-penjahitan').forEach(button => {
        //     button.addEventListener('click', function() {
        //         const id = this.getAttribute('data-id');
        //         const produk = this.getAttribute('data-produk');
        //         const seri = this.getAttribute('data-seri');

        //         // Set nilai modal batal
        //         document.getElementById('batal_modal_id').value = id;
        //         document.getElementById('batal_modal_produk').textContent = produk;
        //         document.getElementById('batal_modal_seri').textContent = seri;

        //         // Tampilkan modal konfirmasi
        //         modalBatalPenjahitan.show();
        //     });
        // });

        // Tombol Batal Penjahitan dengan validasi
        document.querySelectorAll('.btn-batal-penjahitan').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const produk = this.getAttribute('data-produk');
                const seri = this.getAttribute('data-seri');
                const penjahit = this.getAttribute('data-penjahit');

                // Cek apakah upah sudah dibayar via AJAX
                checkUpahStatus(id).then(upahDibayar => {
                    if (upahDibayar) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Tidak Dapat Membatalkan',
                            html: `
                        <div class="text-start">
                            <p><strong>Penjahitan tidak dapat dibatalkan!</strong></p>
                            <p>Upah untuk penjahit <strong>${penjahit}</strong> pada produksi ini sudah dibayar.</p>
                            <p class="text-danger mb-3">
                                <i class="ti ti-alert-triangle"></i> 
                                Silakan batalkan pembayaran upah terlebih dahulu di menu <strong>Hutang Upah</strong>.
                            </p>
                            <div class="d-grid">
                                <a href="hutang_upah.php" class="btn btn-warning">
                                    <i class="ti ti-report-money"></i> Ke Menu Hutang Upah
                                </a>
                            </div>
                        </div>
                    `,
                            confirmButtonText: 'Mengerti',
                            showCancelButton: false
                        });
                    } else {
                        // Set nilai modal batal
                        document.getElementById('batal_modal_id').value = id;
                        document.getElementById('batal_modal_produk').textContent = produk;
                        document.getElementById('batal_modal_seri').textContent = seri;

                        // Tampilkan modal konfirmasi
                        modalBatalPenjahitan.show();
                    }
                }).catch(error => {
                    console.error('Error checking upah status:', error);
                    // Fallback ke modal biasa jika error
                    document.getElementById('batal_modal_id').value = id;
                    document.getElementById('batal_modal_produk').textContent = produk;
                    document.getElementById('batal_modal_seri').textContent = seri;
                    modalBatalPenjahitan.show();
                });
            });
        });

        // Fungsi untuk mengecek status pembayaran upah
        async function checkUpahStatus(idProduksi) {
            try {
                const response = await fetch(`check_upah_status.php?id_produksi=${idProduksi}`);
                const data = await response.json();
                return data.upah_dibayar || false;
            } catch (error) {
                console.error('Error checking upah status:', error);
                return false;
            }
        }

        // Validasi form penjahitan
        document.getElementById('formPenjahitan').addEventListener('submit', function(e) {
            const totalJahit = parseInt(inputTotalJahit.value) || 0;
            const maxJahit = parseInt(inputTotalJahit.max) || 0;

            if (totalJahit > maxJahit) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Total hasil jahit tidak boleh melebihi total hasil potong (' + maxJahit + ' Pcs)',
                    confirmButtonText: 'Oke'
                });
                inputTotalJahit.focus();
            }

            if (totalJahit <= 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Total hasil jahit harus lebih dari 0',
                    confirmButtonText: 'Oke'
                });
                inputTotalJahit.focus();
            }
        });
    });

    // Fungsi untuk mengecek status pembayaran upah pemotong berdasarkan seri
    async function checkUpahPemotongStatus(idProduksi) {
        try {
            const response = await fetch(`check_upah_pemotong_status.php?id_produksi=${idProduksi}`);
            const data = await response.json();
            return data.upah_dibayar || false;
        } catch (error) {
            console.error('Error checking upah pemotong status:', error);
            return false;
        }
    }

    // Tombol batal produksi dengan validasi upah pemotong berdasarkan seri
    document.querySelectorAll('.btn-batal').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const pemotong = this.getAttribute('data-pemotong');
            const seri = this.getAttribute('data-seri'); // Tambahkan atribut ini

            // Cek apakah upah pemotong sudah dibayar untuk seri ini via AJAX
            checkUpahPemotongStatus(id).then(upahDibayar => {
                if (upahDibayar) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Tidak Dapat Membatalkan',
                        html: `
                <div class="text-start">
                    <p><strong>Produksi tidak dapat dibatalkan!</strong></p>
                    <p>Upah untuk pemotong <strong>${pemotong}</strong> pada produksi <strong>Seri ${seri}</strong> sudah dibayar.</p>
                    <p class="text-danger mb-3">
                        <i class="ti ti-alert-triangle"></i> 
                        Silakan batalkan pembayaran upah terlebih dahulu di menu <strong>Hutang Upah</strong>.
                    </p>
                    <div class="d-grid">
                        <a href="../hutang_upah/list.php" class="btn btn-warning">
                            <i class="ti ti-report-money"></i> Ke Menu Hutang Upah
                        </a>
                    </div>
                </div>
            `,
                        confirmButtonText: 'Mengerti',
                        showCancelButton: false
                    });
                } else {
                    // Jika upah belum dibayar untuk seri ini, tampilkan konfirmasi pembatalan
                    Swal.fire({
                        title: 'Yakin ingin membatalkan produksi ini?',
                        html: `<p>Produksi <strong>Seri ${seri}</strong> akan dibatalkan.</p><p>Tindakan ini tidak dapat dikembalikan!</p>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, batalkan!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'batal_pemotongan.php?id=' + id;
                        }
                    });
                }
            }).catch(error => {
                console.error('Error checking upah status:', error);
                // Fallback ke konfirmasi biasa jika error
                Swal.fire({
                    title: 'Yakin ingin membatalkan produksi ini?',
                    html: `<p>Produksi <strong>Seri ${seri}</strong> akan dibatalkan.</p><p>Tindakan ini tidak dapat dikembalikan!</p>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, batalkan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'batal.php?id=' + id;
                    }
                });
            });
        });
    });
</script>

<script>
    $(document).ready(function() {
        // Print PDF
        $('#btnPrintPDF').click(function() {
            // Ambil parameter filter
            const id_produk = $('select[name="id_produk"]').val();
            const status = $('select[name="status"]').val();
            const start_date = $('input[name="start_date"]').val();
            const end_date = $('input[name="end_date"]').val();

            // Buat URL untuk print PDF dengan parameter filter
            let url = 'print_laporan_produksi.php?id_produk=' + id_produk +
                '&status=' + status +
                '&start_date=' + start_date +
                '&end_date=' + end_date;

            // Buka di tab baru
            window.open(url, '_blank');
        });

        // Set default date range (30 hari terakhir)
        function setDefaultDateRange() {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 30);

            // Format to YYYY-MM-DD
            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };

            // Only set if dates are empty
            if (!$('input[name="start_date"]').val()) {
                $('input[name="start_date"]').val(formatDate(startDate));
            }
            if (!$('input[name="end_date"]').val()) {
                $('input[name="end_date"]').val(formatDate(endDate));
            }
        }

        // Panggil fungsi set default date range
        setDefaultDateRange();
    });
</script>


</html>