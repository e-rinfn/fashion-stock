<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';

// Start session untuk pesan
session_start();

// Ambil semua petugas finishing untuk dropdown
$petugas_finishing = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");

// Cek filter yang diterima
$id_petugas_finishing = isset($_GET['id_petugas_finishing']) ? (int)$_GET['id_petugas_finishing'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$tipe_data = isset($_GET['tipe_data']) ? $_GET['tipe_data'] : 'keluar'; // keluar atau masuk

// Inisialisasi array hasil
$koko_keluar = [];
$koko_masuk = [];

// Query untuk koko_keluar (jika dipilih atau default)
if ($tipe_data == 'keluar' || $tipe_data == 'all') {
    $sql_keluar = "SELECT p.*, r.nama_petugas 
                   FROM koko_keluar p 
                   JOIN petugas_finishing r ON p.id_petugas_finishing = r.id_petugas_finishing 
                   WHERE 1=1";

    // Filter petugas finishing
    if ($id_petugas_finishing > 0) {
        $sql_keluar .= " AND p.id_petugas_finishing = $id_petugas_finishing";
    }

    // Filter status - PERHATIAN: Sesuaikan nama kolom status di tabel koko_keluar
    if ($status != 'all') {
        // Cek apakah kolom di tabel koko_keluar bernama 'status' atau 'status_pembayaran'
        $sql_keluar .= " AND p.status = '$status'"; // Ganti dengan nama kolom yang benar
    }

    $sql_keluar .= " ORDER BY p.tanggal_koko_keluar DESC";

    $koko_keluar = query($sql_keluar);
}

// Query untuk koko_masuk (jika dipilih atau default)
if ($tipe_data == 'masuk' || $tipe_data == 'all') {
    $sql_masuk = "SELECT p.*, r.nama_petugas 
                  FROM koko_masuk p 
                  JOIN petugas_finishing r ON p.id_petugas_finishing = r.id_petugas_finishing 
                  WHERE 1=1";

    // Filter petugas finishing
    if ($id_petugas_finishing > 0) {
        $sql_masuk .= " AND p.id_petugas_finishing = $id_petugas_finishing";
    }

    // Filter status - PERHATIAN: Sesuaikan nama kolom status di tabel koko_masuk
    if ($status != 'all') {
        $sql_masuk .= " AND p.status = '$status'"; // Ganti dengan nama kolom yang benar
    }

    $sql_masuk .= " ORDER BY p.tanggal_koko_masuk DESC";

    $koko_masuk = query($sql_masuk);
}

// Hitung total data
$total_keluar = count($koko_keluar);
$total_masuk = count($koko_masuk);
$total_semua = $total_keluar + $total_masuk;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Koko - Finishing</title>
    <style>
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .badge-keluar {
            background-color: #dc3545;
        }

        .badge-masuk {
            background-color: #198754;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }

        .filter-card {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
        }

        .summary-card {
            transition: transform 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

    <?php include_once '../includes/navbar.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <!-- [ Main Content ] start -->
            <div class="row">
                <div class="col-md-12">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>LAPORAN KOKO FINISHING</h2>
                        <div>
                            <a href="koko_keluar.php" class="btn btn-primary me-2">
                                <i class="bx bx-plus"></i> Koko Keluar
                            </a>
                            <a href="koko_masuk.php" class="btn btn-success">
                                <i class="bx bx-plus"></i> Koko Masuk
                            </a>
                        </div>
                    </div>

                    <!-- Pesan Sukses/Error -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['success']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <!-- Card Summary -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card summary-card border-start border-primary border-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">Total Data</h6>
                                            <h3 class="mb-0"><?= number_format($total_semua) ?></h3>
                                        </div>
                                        <div class="avatar-sm rounded-circle bg-primary bg-opacity-10 p-3">
                                            <i class="bx bx-data text-primary" style="font-size: 24px;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card summary-card border-start border-danger border-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">Koko Keluar</h6>
                                            <h3 class="mb-0"><?= number_format($total_keluar) ?></h3>
                                        </div>
                                        <div class="avatar-sm rounded-circle bg-danger bg-opacity-10 p-3">
                                            <i class="bx bx-upload text-danger" style="font-size: 24px;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card summary-card border-start border-success border-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">Koko Masuk</h6>
                                            <h3 class="mb-0"><?= number_format($total_masuk) ?></h3>
                                        </div>
                                        <div class="avatar-sm rounded-circle bg-success bg-opacity-10 p-3">
                                            <i class="bx bx-download text-success" style="font-size: 24px;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Card -->
                    <div class="card filter-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3"><i class="bx bx-filter-alt"></i> Filter Data</h5>
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tipe Data</label>
                                    <select name="tipe_data" class="form-select">
                                        <option value="all" <?= $tipe_data == 'all' ? 'selected' : '' ?>>Semua Data</option>
                                        <option value="keluar" <?= $tipe_data == 'keluar' ? 'selected' : '' ?>>Koko Keluar</option>
                                        <option value="masuk" <?= $tipe_data == 'masuk' ? 'selected' : '' ?>>Koko Masuk</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Petugas Finishing</label>
                                    <select name="id_petugas_finishing" class="form-select">
                                        <option value="0">Semua Petugas</option>
                                        <?php foreach ($petugas_finishing as $p): ?>
                                            <option value="<?= $p['id_petugas_finishing'] ?>"
                                                <?= $id_petugas_finishing == $p['id_petugas_finishing'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['nama_petugas']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>Semua Status</option>
                                        <option value="dikirim" <?= $status == 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                                        <option value="selesai" <?= $status == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                        <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="bx bx-search"></i> Filter
                                    </button>
                                    <a href="list.php" class="btn btn-secondary">
                                        <i class="bx bx-refresh"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tab Navigasi -->
                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" id="dataTabs" role="tablist">
                                <?php if ($tipe_data == 'all' || $tipe_data == 'keluar'): ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?= ($tipe_data == 'all' || $tipe_data == 'keluar') ? 'active' : '' ?>"
                                            id="keluar-tab" data-bs-toggle="tab" data-bs-target="#keluar"
                                            type="button" role="tab">
                                            <i class="bx bx-upload me-1"></i> Koko Keluar
                                            <span class="badge bg-danger ms-2"><?= $total_keluar ?></span>
                                        </button>
                                    </li>
                                <?php endif; ?>

                                <?php if ($tipe_data == 'all' || $tipe_data == 'masuk'): ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?= ($tipe_data == 'all' || $tipe_data == 'masuk') ? 'active' : '' ?>" id="masuk-tab" data-bs-toggle="tab" data-bs-target="#masuk"
                                            type="button" role="tab">
                                            <i class="bx bx-download me-1"></i> Koko Masuk
                                            <span class="badge bg-success ms-2"><?= $total_masuk ?></span>
                                        </button>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="card-body">
                            <div class="tab-content" id="dataTabsContent">
                                <!-- Tab Koko Keluar -->
                                <?php if ($tipe_data == 'all' || $tipe_data == 'keluar'): ?>
                                    <div class="tab-pane fade <?= ($tipe_data == 'all' || $tipe_data == 'keluar') ? 'show active' : '' ?>"
                                        id="keluar" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="5%">No</th>
                                                        <th>Tanggal</th>
                                                        <th>Petugas Finishing</th>
                                                        <th>Total Harga</th>
                                                        <th>Status</th>
                                                        <th>Metode Pembayaran</th>
                                                        <th width="15%">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($koko_keluar)): ?>
                                                        <?php foreach ($koko_keluar as $i => $kk): ?>
                                                            <tr>
                                                                <td class="text-center"><?= $i + 1 ?></td>
                                                                <td>
                                                                    <?= date('d/m/Y', strtotime($kk['tanggal_koko_keluar'])) ?><br>
                                                                    <small class="text-muted"><?= date('H:i', strtotime($kk['tanggal_koko_keluar'])) ?></small>
                                                                </td>
                                                                <td><?= htmlspecialchars($kk['nama_petugas']) ?></td>
                                                                <td class="text-right"><?= formatRupiah($kk['total_harga']) ?></td>
                                                                <td>
                                                                    <span class="badge bg-<?= $kk['status'] == 'selesai' ? 'success' : 'warning' ?>">
                                                                        <?= ucfirst($kk['status']) ?>
                                                                    </span>
                                                                </td>
                                                                <td><?= ucfirst($kk['metode_pembayaran']) ?></td>
                                                                <td class="text-center">
                                                                    <a href="detail_koko_keluar.php?id=<?= $kk['id_koko_keluar'] ?>"
                                                                        class="btn btn-sm btn-info" title="Detail">
                                                                        <i class="bx bx-show"></i>
                                                                    </a>
                                                                    <a href="edit_koko_keluar.php?id=<?= $kk['id_koko_keluar'] ?>"
                                                                        class="btn btn-sm btn-warning" title="Edit">
                                                                        <i class="bx bx-edit"></i>
                                                                    </a>
                                                                    <a href="nota.php?id=<?= $kk['id_koko_keluar'] ?>"
                                                                        class="btn btn-sm btn-secondary" title="Cetak" target="_blank">
                                                                        <i class="bx bx-printer"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="7" class="text-center py-4">
                                                                <div class="text-muted">
                                                                    <i class="bx bx-package" style="font-size: 48px;"></i>
                                                                    <p class="mt-2">Tidak ada data koko keluar</p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Tab Koko Masuk -->
                                <?php if ($tipe_data == 'all' || $tipe_data == 'masuk'): ?>
                                    <div class="tab-pane fade <?= ($tipe_data == 'all' || $tipe_data == 'masuk') ? 'show active' : '' ?>"
                                        id="masuk" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="5%">No</th>
                                                        <th>Tanggal</th>
                                                        <th>Petugas Finishing</th>
                                                        <th>Total Harga</th>
                                                        <th>Status</th>
                                                        <th>Metode Pembayaran</th>
                                                        <th width="15%">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($koko_masuk)): ?>
                                                        <?php foreach ($koko_masuk as $i => $km): ?>
                                                            <tr>
                                                                <td class="text-center"><?= $i + 1 ?></td>
                                                                <td>
                                                                    <?= date('d/m/Y', strtotime($km['tanggal_koko_masuk'])) ?><br>
                                                                    <small class="text-muted"><?= date('H:i', strtotime($km['tanggal_koko_masuk'])) ?></small>
                                                                </td>
                                                                <td><?= htmlspecialchars($km['nama_petugas']) ?></td>
                                                                <td class="text-right"><?= formatRupiah($km['total_harga']) ?></td>
                                                                <td>
                                                                    <span class="badge bg-<?= $km['status'] == 'selesai' ? 'success' : 'warning' ?>">
                                                                        <?= ucfirst($km['status']) ?>
                                                                    </span>
                                                                </td>
                                                                <td><?= ucfirst($km['metode_pembayaran']) ?></td>
                                                                <td class="text-center">
                                                                    <a href="detail_koko_masuk.php?id=<?= $km['id_koko_masuk'] ?>"
                                                                        class="btn btn-sm btn-info" title="Detail">
                                                                        <i class="bx bx-show"></i>
                                                                    </a>
                                                                    <a href="edit_koko_masuk.php?id=<?= $km['id_koko_masuk'] ?>"
                                                                        class="btn btn-sm btn-warning" title="Edit">
                                                                        <i class="bx bx-edit"></i>
                                                                    </a>
                                                                    <a href="nota_koko_masuk.php?id=<?= $km['id_koko_masuk'] ?>"
                                                                        class="btn btn-sm btn-secondary" title="Cetak" target="_blank">
                                                                        <i class="bx bx-printer"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="7" class="text-center py-4">
                                                                <div class="text-muted">
                                                                    <i class="bx bx-package" style="font-size: 48px;"></i>
                                                                    <p class="mt-2">Tidak ada data koko masuk</p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Footer dengan Export -->
                        <div class="card-footer d-flex justify-content-between">
                            <div>
                                <small class="text-muted">
                                    Menampilkan
                                    <span class="fw-bold"><?= number_format($total_semua) ?></span>
                                    data
                                </small>
                            </div>
                            <div>
                                <a href="export_excel.php?<?= http_build_query($_GET) ?>"
                                    class="btn btn-success btn-sm">
                                    <i class="bx bx-export"></i> Export Excel
                                </a>
                                <a href="export_pdf.php?<?= http_build_query($_GET) ?>"
                                    class="btn btn-danger btn-sm ms-2">
                                    <i class="bx bx-file"></i> Export PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once '../includes/footer.php'; ?>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Aktifkan tab berdasarkan URL parameter
        $(document).ready(function() {
            const tipeData = '<?= $tipe_data ?>';

            if (tipeData === 'masuk') {
                $('#masuk-tab').tab('show');
            } else if (tipeData === 'keluar') {
                $('#keluar-tab').tab('show');
            }

            // Update URL saat tab berubah
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                const target = $(e.target).attr('id');
                let tipe = 'all';

                if (target === 'keluar-tab') tipe = 'keluar';
                if (target === 'masuk-tab') tipe = 'masuk';

                // Update parameter tipe_data di URL
                const url = new URL(window.location);
                url.searchParams.set('tipe_data', tipe);
                window.history.pushState({}, '', url);
            });

            // Konfirmasi hapus
            $('.btn-delete').on('click', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                const nama = $(this).data('nama');

                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    html: `Data <b>${nama}</b> akan dihapus permanen!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
        });
    </script>
</body>

</html>