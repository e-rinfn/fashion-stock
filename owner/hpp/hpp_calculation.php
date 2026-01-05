<?php
// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Set timezone
date_default_timezone_set('Asia/Jakarta');

function dateIndo2($datetime, $withTime = false)
{
    $timestamp = strtotime($datetime);

    $bulan = [
        'Januari',
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

    $tanggal = date('d', $timestamp);
    $bulanIndo = $bulan[date('m', $timestamp) - 1];
    $tahun = date('Y', $timestamp);

    $hasil = "$tanggal $bulanIndo $tahun";

    if ($withTime) {
        $hasil .= ' ' . date('H:i', $timestamp);
    }

    return $hasil;
}


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

// Standardisasi struktur data penjualan
$data_penjualan = [];

if (!empty($penjualan_per_produk)) {
    // Data langsung dari query penjualan
    foreach ($penjualan_per_produk as $item) {
        $data_penjualan[] = [
            'nama_produk' => $item['nama_produk'],
            'jumlah' => $item['total_jumlah'],
            'total_harga' => $item['total_harga']
        ];
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
}

// Update variabel jika ada data periode
if ($periode_id > 0) {
    $periode = query("SELECT * FROM hpp_periode WHERE id_periode = $periode_id")[0];
    $total_biaya_produksi = $periode['total_biaya_produksi'];
    $total_penjualan = $periode['total_penjualan'];
    $total_hpp = $periode['total_hpp'];
    $laba_bersih = $periode['laba_bersih'];

    // Ambil data biaya produksi lagi setelah update
    $biaya_produksi = query("SELECT * FROM hpp_biaya_produksi WHERE id_periode = $periode_id ORDER BY created_at DESC");
}
?>

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
                        <h2>Perhitungan HPP (Harga Pokok Produksi)</h2>
                    </div>

                    <!-- Period Selector -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-1">
                                        <i class="ti ti-calendar me-2"></i>
                                        Periode: <?= date('F Y', strtotime($tanggal_awal)) ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?= date('d F Y', strtotime($tanggal_awal)) ?> - <?= date('d F Y', strtotime($tanggal_akhir)) ?>
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <form method="GET" class="row g-2">
                                        <div class="col-md-6">
                                            <select class="form-control" name="bulan">
                                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                                    <option value="<?= $i ?>" <?= $selected_month == $i ? 'selected' : '' ?>>
                                                        <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <select class="form-control" name="tahun">
                                                <?php for ($i = date('Y') - 2; $i <= date('Y'); $i++): ?>
                                                    <option value="<?= $i ?>" <?= $selected_year == $i ? 'selected' : '' ?>>
                                                        <?= $i ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="ti ti-filter"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="ti ti-check me-2"></i>
                            <?= htmlspecialchars($_SESSION['success']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="ti ti-alert-circle me-2"></i>
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Card Kiri: Biaya Produksi -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Biaya Produksi</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Form Tambah Biaya -->
                                    <form method="POST" class="mb-4">
                                        <div class="row g-2 mb-3">
                                            <div class="col-md-8">
                                                <input type="text"
                                                    name="keterangan"
                                                    class="form-control"
                                                    placeholder="Keterangan biaya"
                                                    required>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="input-group">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number"
                                                        name="biaya"
                                                        class="form-control"
                                                        placeholder="Jumlah"
                                                        min="0"
                                                        step="1000"
                                                        required>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" name="tambah_biaya" class="btn btn-primary w-100">
                                            <i class="ti ti-device-floppy me-1"></i> Simpan Biaya
                                        </button>
                                    </form>

                                    <!-- Daftar Biaya Produksi -->
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th width="50%">Keterangan</th>
                                                    <th width="30%">Biaya</th>
                                                    <th width="20%">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($biaya_produksi)): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted py-3">
                                                            Belum ada biaya produksi
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($biaya_produksi as $biaya): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($biaya['keterangan']) ?></td>
                                                            <td><?= formatRupiah($biaya['biaya']) ?></td>
                                                            <td>
                                                                <div class="btn-group btn-group-sm">
                                                                    <button type="button"
                                                                        class="btn btn-outline-primary btn-edit-biaya"
                                                                        data-id="<?= $biaya['id_biaya'] ?>"
                                                                        data-keterangan="<?= htmlspecialchars($biaya['keterangan']) ?>"
                                                                        data-biaya="<?= $biaya['biaya'] ?>">
                                                                        <i class="ti ti-edit"></i>
                                                                    </button>
                                                                    <button type="button"
                                                                        class="btn btn-outline-danger btn-hapus-biaya"
                                                                        data-id="<?= $biaya['id_biaya'] ?>"
                                                                        data-keterangan="<?= htmlspecialchars($biaya['keterangan']) ?>">
                                                                        <i class="ti ti-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-light">
                                                    <th class="text-end">Total:</th>
                                                    <th colspan="2" class="text-start"><?= formatRupiah($total_biaya_produksi) ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Kanan: Penjualan Produk -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Penjualan Produk</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Daftar Penjualan per Produk -->
                                    <div class="table-responsive mb-3">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th width="50%">Produk</th>
                                                    <th width="20%" class="text-center">Jumlah</th>
                                                    <th width="30%" class="text-end">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                // Inisialisasi variabel
                                                $total_produk = 0;
                                                $total_harga_all = 0;
                                                ?>

                                                <?php if (empty($data_penjualan)): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted py-3">
                                                            Tidak ada data penjualan
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($data_penjualan as $item):
                                                        $total_produk += $item['jumlah'];
                                                        $total_harga_all += $item['total_harga'];
                                                    ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['nama_produk']) ?></td>
                                                            <td class="text-center">
                                                                <?= number_format($item['jumlah'], 0, ',', '.') ?> pcs
                                                            </td>
                                                            <td class="text-end"><?= formatRupiah($item['total_harga']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-light">
                                                    <th class="text-end">Total:</th>
                                                    <th class="text-center"><?= number_format($total_produk, 0, ',', '.') ?> pcs</th>
                                                    <th class="text-end"><?= formatRupiah($total_penjualan) ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <!-- Perhitungan HPP -->
                                    <div class="card">
                                        <div class="card-body">
                                            <table class="table table-bordered mb-0">
                                                <tr>
                                                    <td width="70%"><strong>Total Penjualan Produk</strong></td>
                                                    <td class="text-end"><?= formatRupiah($total_penjualan) ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Total Biaya Produksi (HPP)</strong></td>
                                                    <td class="text-end"><?= formatRupiah($total_hpp) ?></td>
                                                </tr>
                                                <tr class="table-light">
                                                    <td><strong>LABA BERSIH</strong></td>
                                                    <td class="text-end fw-bold <?= $laba_bersih >= 0 ? 'text-success' : 'text-danger' ?>">
                                                        <?= formatRupiah($laba_bersih) ?>
                                                    </td>
                                                </tr>
                                            </table>
                                            <div class="mt-2 text-muted">
                                                <small>HPP = Total Biaya Produksi | Laba Bersih = Total Penjualan - HPP</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Info Status -->
                                    <?php if ($periode_id > 0): ?>
                                        <div class="alert alert-light border mt-3 mb-0">
                                            <small>
                                                <i class="ti ti-info-circle me-1"></i>
                                                Data tersimpan. Terakhir update:
                                                <?= dateIndo2($periode['updated_at'], true) ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning border mt-3 mb-0">
                                            <small>
                                                <i class="ti ti-alert-circle me-1"></i>
                                                Data belum disimpan. Tambahkan biaya produksi untuk menyimpan.
                                            </small>
                                        </div>
                                    <?php endif; ?>
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
    <div class="modal fade" id="editBiayaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Biaya Produksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editBiayaForm">
                    <div class="modal-body">
                        <input type="hidden" name="id_biaya" id="edit_id_biaya">
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <input type="text" class="form-control" id="edit_keterangan" name="keterangan" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jumlah Biaya</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number"
                                    class="form-control"
                                    id="edit_biaya"
                                    name="biaya"
                                    min="0"
                                    step="1"
                                    required
                                    pattern="[0-9]*"
                                    inputmode="numeric">
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
    <div class="modal fade" id="hapusBiayaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Hapus Biaya Produksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="hapusBiayaForm">
                    <div class="modal-body">
                        <input type="hidden" name="id_biaya" id="hapus_id_biaya">
                        <p>Apakah Anda yakin ingin menghapus biaya: <strong id="hapus_keterangan"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="hapus_biaya" class="btn btn-danger">Hapus</button>
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