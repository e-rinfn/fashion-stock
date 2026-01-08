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

                <div class="d-flex justify-content-between align-items-center">
                    <h2>Laporan Produksi</h2>
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
                            <a href="layout.php" class="btn btn-secondary me-2">
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

                    <!-- Info Filter -->
                    <?php if ($id_produk > 0 || $status != 'all' || !empty($start_date) || !empty($end_date)): ?>
                        <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                            <strong>Filter Aktif:</strong>
                            <?php
                            $filter_info = [];
                            if ($id_produk > 0) {
                                $produk_info = query("SELECT nama_produk FROM produk WHERE id_produk = $id_produk")[0];
                                $filter_info[] = "Produk: " . $produk_info['nama_produk'];
                            }
                            if ($status != 'all') {
                                $filter_info[] = "Status: " . ucfirst($status);
                            }
                            if (!empty($start_date)) {
                                $filter_info[] = "Dari: " . dateIndo($start_date);
                            }
                            if (!empty($end_date)) {
                                $filter_info[] = "Sampai: " . dateIndo($end_date);
                            }
                            echo implode(' | ', $filter_info);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

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
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (empty($all_data)): ?>
                                    <tr>
                                        <td colspan="12" class="text-center">Tidak ada data produksi</td>
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

    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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