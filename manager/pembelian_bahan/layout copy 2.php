<?php
// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Ambil data dengan filter dan pencarian
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_supplier = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;
$filter_bahan = isset($_GET['bahan']) ? $_GET['bahan'] : [];
$filter_tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : '';
$filter_tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

// Konversi filter_bahan ke array jika string
if (!is_array($filter_bahan) && !empty($filter_bahan)) {
    $filter_bahan = explode(',', $filter_bahan);
} elseif (!is_array($filter_bahan)) {
    $filter_bahan = [];
}

// Konversi ke integer
$filter_bahan = array_map('intval', $filter_bahan);
$filter_bahan = array_filter($filter_bahan); // Hapus nilai 0

// Query utama untuk mendapatkan detail pembelian bahan
$query = "SELECT 
            pb.id_pembelian_bahan,
            pb.no_transaksi,
            pb.tanggal_pembelian,
            pb.status_pembayaran,
            pb.metode_pembayaran,
            s.nama_supplier,
            b.id_bahan,
            b.nama_bahan,
            b.satuan,
            dpb.jumlah,
            dpb.meter,
            dpb.harga_satuan,
            dpb.subtotal,
            (dpb.jumlah * dpb.meter) as total_meter
          FROM detail_pembelian_bahan dpb
          JOIN pembelian_bahan pb ON dpb.id_pembelian_bahan = pb.id_pembelian_bahan
          JOIN supplier s ON pb.id_supplier = s.id_supplier
          JOIN bahan_baku b ON dpb.id_bahan = b.id_bahan
          WHERE 1=1";

// Tambahkan kondisi pencarian
if (!empty($search)) {
    $query .= " AND (pb.no_transaksi LIKE '%$search%' 
                OR s.nama_supplier LIKE '%$search%'
                OR b.nama_bahan LIKE '%$search%')";
}

// Filter supplier
if ($filter_supplier > 0) {
    $query .= " AND pb.id_supplier = $filter_supplier";
}

// Filter bahan (multiple)
if (!empty($filter_bahan)) {
    $bahan_ids = implode(',', $filter_bahan);
    $query .= " AND dpb.id_bahan IN ($bahan_ids)";
}

// Filter tanggal
if (!empty($filter_tanggal_awal) && !empty($filter_tanggal_akhir)) {
    $query .= " AND DATE(pb.tanggal_pembelian) BETWEEN '$filter_tanggal_awal' AND '$filter_tanggal_akhir'";
} elseif (!empty($filter_tanggal_awal)) {
    $query .= " AND DATE(pb.tanggal_pembelian) >= '$filter_tanggal_awal'";
} elseif (!empty($filter_tanggal_akhir)) {
    $query .= " AND DATE(pb.tanggal_pembelian) <= '$filter_tanggal_akhir'";
}

// Order by
$query .= " ORDER BY pb.tanggal_pembelian DESC, pb.id_pembelian_bahan DESC, b.nama_bahan";

$detail_pembelian = query($query);

// Ambil data supplier untuk filter
$suppliers = query("SELECT * FROM supplier ORDER BY nama_supplier");

// Ambil data bahan untuk filter
$bahan_list = query("SELECT * FROM bahan_baku ORDER BY nama_bahan");

// Hitung summary total secara manual dari $detail_pembelian
$summary_manual = [
    'total_transaksi' => 0,
    'total_item' => 0,
    'total_jumlah' => 0,
    'total_meter' => 0,
    'total_meter_all' => 0,
    'total_nilai' => 0
];

$transaksi_ids = [];
if (!empty($detail_pembelian)) {
    foreach ($detail_pembelian as $item) {
        // Hitung total transaksi unik
        if (!in_array($item['id_pembelian_bahan'], $transaksi_ids)) {
            $transaksi_ids[] = $item['id_pembelian_bahan'];
            $summary_manual['total_transaksi']++;
        }

        // Hitung total item
        $summary_manual['total_item']++;

        // Hitung total jumlah
        $summary_manual['total_jumlah'] += $item['jumlah'];

        // Hitung total meter
        $summary_manual['total_meter'] += $item['meter'];

        // Hitung total meter all (jumlah × meter)
        $summary_manual['total_meter_all'] += ($item['jumlah'] * $item['meter']);

        // Hitung total nilai (harga_satuan × meter)
        $summary_manual['total_nilai'] += ($item['harga_satuan'] * $item['meter']);
    }
}

// Hitung summary per bahan DARI DATA DETAIL YANG SUDAH DIFILTER
$summary_per_bahan = [];
$bahan_data = [];

if (!empty($detail_pembelian)) {
    foreach ($detail_pembelian as $item) {
        $bahan_id = $item['id_bahan'];

        if (!isset($bahan_data[$bahan_id])) {
            $bahan_data[$bahan_id] = [
                'id_bahan' => $bahan_id,
                'nama_bahan' => $item['nama_bahan'],
                'satuan' => $item['satuan'],
                'total_jumlah' => 0,
                'total_meter' => 0,
                'total_harga_satuan' => 0, // untuk menghitung rata-rata
                'total_subtotal' => 0 // total dari harga_satuan (untuk menghitung rata-rata)
            ];
        }

        $bahan_data[$bahan_id]['total_jumlah'] += $item['jumlah'];
        $bahan_data[$bahan_id]['total_meter'] += $item['meter'];
        $bahan_data[$bahan_id]['total_harga_satuan'] += $item['harga_satuan'];
        $bahan_data[$bahan_id]['total_subtotal'] += ($item['harga_satuan'] * $item['meter']); // Perbaikan: harga_satuan × meter
    }

    // Ubah ke array indexed
    $summary_per_bahan = array_values($bahan_data);
}

// Persiapkan string filter bahan untuk form
$filter_bahan_str = !empty($filter_bahan) ? implode(',', $filter_bahan) : '';
?>


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
                        <h2>LAPORAN PEMBELIAN BAHAN BAKU</h2>
                    </div>

                    <!-- Card Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="summary-card purple">
                                <h3><?= number_format($summary_manual['total_transaksi'], 0, ',', '.') ?></h3>
                                <p>Total Transaksi</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h3><?= number_format($summary_manual['total_item'], 0, ',', '.') ?></h3>
                                <p>Total Item Dibeli</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card orange">
                                <h3><?= number_format($summary_manual['total_meter_all'], 1, ',', '.') ?> m</h3>
                                <p>Total Meter Pembelian</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card green">
                                <h3><?= formatRupiah($summary_manual['total_nilai']) ?></h3>
                                <p>Total Nilai Pembelian</p>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Terpisah untuk Summary per Bahan -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter untuk Ringkasan per Bahan</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="filterSummaryForm" class="row g-3">
                                <!-- Simpan semua parameter filter lainnya -->
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="supplier" value="<?= $filter_supplier ?>">
                                <input type="hidden" name="tanggal_awal" value="<?= htmlspecialchars($filter_tanggal_awal) ?>">
                                <input type="hidden" name="tanggal_akhir" value="<?= htmlspecialchars($filter_tanggal_akhir) ?>">

                                <div class="col-md-10">
                                    <label class="form-label">Pilih Bahan untuk Ringkasan:</label>
                                    <select class="form-control select2-bahan-summary" name="bahan[]" multiple="multiple" style="width: 100%;">
                                        <?php foreach ($bahan_list as $b): ?>
                                            <option value="<?= $b['id_bahan'] ?>"
                                                <?= in_array($b['id_bahan'], $filter_bahan) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($b['nama_bahan']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Kosongkan untuk menampilkan semua bahan</small>
                                </div>

                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-filter me-1"></i> Filter Ringkasan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Summary per Bahan (selalu tampil jika ada data) -->
                    <?php if (!empty($summary_per_bahan)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>
                                    <?php if (!empty($filter_bahan)): ?>
                                        Ringkasan Bahan Terpilih
                                        <?php if (count($filter_bahan) <= 3): ?>
                                            (<?= count($filter_bahan) ?> bahan)
                                        <?php else: ?>
                                            (<?= count($filter_bahan) ?> bahan terpilih)
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Ringkasan Semua Bahan Baku
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0 summary-bahan-table">
                                        <thead>
                                            <tr>
                                                <th width="5%">No</th>
                                                <th width="30%">Nama Bahan</th>
                                                <th width="10%">Satuan</th>
                                                <th width="10%" class="text-end">Jumlah (pcs)</th>
                                                <th width="10%" class="text-end">Total Meter (m)</th>
                                                <th width="20%" class="text-end">Harga Rata-rata/m</th>
                                                <th width="20%" class="text-end">Total Nilai</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $total_jumlah_summary = 0;
                                            $total_meter_summary = 0;
                                            $total_nilai_summary = 0;
                                            ?>

                                            <?php foreach ($summary_per_bahan as $index => $bahan): ?>
                                                <?php
                                                // Hitung harga rata-rata per meter
                                                $harga_rata_rata = ($bahan['total_harga_satuan'] > 0 && $bahan['total_jumlah'] > 0)
                                                    ? $bahan['total_harga_satuan'] / $bahan['total_jumlah']
                                                    : 0;

                                                // Hitung total nilai
                                                $total_nilai_bahan = $bahan['total_subtotal'];

                                                // Akumulasi untuk total
                                                $total_jumlah_summary += $bahan['total_jumlah'];
                                                $total_meter_summary += $bahan['total_meter'];
                                                $total_nilai_summary += $total_nilai_bahan;
                                                ?>
                                                <tr>
                                                    <td class="text-center"><?= $index + 1 ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($bahan['nama_bahan']) ?>
                                                        <?php if (!empty($filter_bahan) && in_array($bahan['id_bahan'], $filter_bahan)): ?>
                                                            <span class="badge bg-success ms-1">Terpilih</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center"><?= htmlspecialchars($bahan['satuan']) ?></td>
                                                    <td class="text-end"><?= number_format($bahan['total_jumlah'], 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= number_format($bahan['total_meter'], 2, ',', '.') ?> m</td>
                                                    <td class="text-end text-money">
                                                        <?= formatRupiah($harga_rata_rata) ?>/m
                                                    </td>
                                                    <td class="text-end text-money"><?= formatRupiah($total_nilai_bahan) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <th colspan="3" class="text-end">TOTAL:</th>
                                                <th class="text-end"><?= number_format($total_jumlah_summary, 0, ',', '.') ?></th>
                                                <th class="text-end"><?= number_format($total_meter_summary, 2, ',', '.') ?> m</th>
                                                <th class="text-end">-</th>
                                                <th class="text-end text-primary"><?= formatRupiah($total_nilai_summary) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Garis pemisah -->
                    <hr class="my-4">

                    <!-- Filter Terpisah untuk Data Detail -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter untuk Data Detail</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="filterDetailForm" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Pencarian</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" name="search"
                                            placeholder="No. Transaksi/Supplier/Bahan..."
                                            value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Supplier</label>
                                    <select class="form-control" name="supplier">
                                        <option value="">Semua Supplier</option>
                                        <?php foreach ($suppliers as $s): ?>
                                            <option value="<?= $s['id_supplier'] ?>"
                                                <?= $filter_supplier == $s['id_supplier'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['nama_supplier']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Bahan Baku</label>
                                    <select class="form-control select2-bahan-detail" name="bahan[]" multiple="multiple" style="width: 100%;">
                                        <?php foreach ($bahan_list as $b): ?>
                                            <option value="<?= $b['id_bahan'] ?>"
                                                <?= in_array($b['id_bahan'], $filter_bahan) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($b['nama_bahan']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Tanggal Pembelian</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                        <input type="date" class="form-control" name="tanggal_awal"
                                            value="<?= htmlspecialchars($filter_tanggal_awal) ?>">
                                        <span class="input-group-text">s/d</span>
                                        <input type="date" class="form-control" name="tanggal_akhir"
                                            value="<?= htmlspecialchars($filter_tanggal_akhir) ?>">
                                    </div>
                                </div>

                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="d-flex w-100 gap-2">
                                        <a href="list.php" class="btn btn-secondary flex-grow-1">
                                            <i class="bi bi-arrow-clockwise me-1"></i> Reset
                                        </a>
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="bi bi-search me-1"></i> Filter Detail
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Card Data Detail -->
                    <div class="card">
                        <div class="card-body">
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <div><?= htmlspecialchars($_SESSION['success']) ?></div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <div><?= htmlspecialchars($_SESSION['error']) ?></div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table class="table table-hover" id="dataTable">
                                    <thead>
                                        <tr>
                                            <th width="50">No</th>
                                            <th width="100">Tanggal</th>
                                            <th>Supplier</th>
                                            <th>Bahan Baku</th>
                                            <th width="80" class="text-center">Jumlah</th>
                                            <th width="80" class="text-center">Meter</th>
                                            <th width="100" class="text-end">Harga/m</th>
                                            <th width="120" class="text-end">Subtotal</th>
                                            <th width="80" class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($detail_pembelian)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-5">
                                                    <div class="alert alert-info mb-0 d-flex flex-column align-items-center">
                                                        <i class="bi bi-inbox mb-2" style="font-size: 2rem;"></i>
                                                        <p class="mb-0">Tidak ada data pembelian bahan</p>
                                                        <?php if (!empty($search) || $filter_supplier > 0 || !empty($filter_bahan)): ?>
                                                            <small class="text-muted">Coba gunakan filter yang berbeda</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php
                                            $current_pembelian_id = null;
                                            $row_number = 0;
                                            $current_transaksi_total = 0;
                                            $grand_total = 0;
                                            ?>

                                            <?php foreach ($detail_pembelian as $item): ?>
                                                <?php
                                                $row_number++;

                                                // Hitung subtotal manual: harga_satuan × meter
                                                $hitung_subtotal = $item['harga_satuan'] * $item['meter'];
                                                $grand_total += $hitung_subtotal;

                                                // Tampilkan baris supplier jika pembelian berbeda
                                                if ($current_pembelian_id != $item['id_pembelian_bahan']):
                                                    // Tampilkan total transaksi sebelumnya jika ada
                                                    if ($current_pembelian_id !== null): ?>
                                                        <tr class="table-secondary">
                                                            <td colspan="7" class="text-end"><strong>Total Transaksi:</strong></td>
                                                            <td class="text-end text-money">
                                                                <strong><?= formatRupiah($current_transaksi_total) ?></strong>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                    <?php endif;

                                                    // Reset untuk transaksi baru
                                                    $current_pembelian_id = $item['id_pembelian_bahan'];
                                                    $current_transaksi_total = 0;
                                                    $status_class = 'status-' . $item['status_pembayaran'];
                                                    ?>
                                                    <tr class="supplier-row">
                                                        <td class="text-center"><?= $row_number ?></td>
                                                        <td>
                                                            <strong><?= date('d/m/Y', strtotime($item['tanggal_pembelian'])) ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?= date('H:i', strtotime($item['tanggal_pembelian'])) ?></small>
                                                        </td>
                                                        <td colspan="2">
                                                            <strong><?= htmlspecialchars($item['nama_supplier']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">No. Transaksi: <?= htmlspecialchars($item['no_transaksi']) ?></small>
                                                        </td>
                                                        <td colspan="3"></td>
                                                        <td></td>
                                                        <td class="text-center">
                                                            <span class="status-badge <?= $status_class ?>">
                                                                <?= ucfirst($item['status_pembayaran']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>

                                                <!-- Baris detail bahan -->
                                                <?php
                                                // Tambahkan ke total transaksi
                                                $current_transaksi_total += $hitung_subtotal;
                                                ?>
                                                <tr class="bahan-row">
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($item['nama_bahan']) ?></div>
                                                        <?php if (!empty($filter_bahan) && in_array($item['id_bahan'], $filter_bahan)): ?>
                                                            <span class="badge bg-success ms-1">Terpilih</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary rounded-pill"><?= $item['jumlah'] ?> pcs</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= number_format($item['meter'], 2, ',', '.') ?> m
                                                    </td>
                                                    <td class="text-end text-money">
                                                        <?= formatRupiah($item['harga_satuan']) ?>
                                                    </td>
                                                    <td class="text-end text-money">
                                                        <strong><?= formatRupiah($hitung_subtotal) ?></strong>
                                                        <?php if (abs($hitung_subtotal - $item['subtotal']) > 0.01): ?>
                                                            <br>
                                                            <small class="text-danger">
                                                                <i class="bi bi-exclamation-triangle"></i>
                                                                DB: <?= formatRupiah($item['subtotal']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Tampilkan total untuk transaksi terakhir -->
                                            <?php if ($current_pembelian_id !== null): ?>
                                                <tr class="table-secondary">
                                                    <td colspan="7" class="text-end"><strong>Total Transaksi:</strong></td>
                                                    <td class="text-end text-money">
                                                        <strong><?= formatRupiah($current_transaksi_total) ?></strong>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <th colspan="7" class="text-end">GRAND TOTAL:</th>
                                            <th class="text-end text-primary">
                                                <?= formatRupiah($grand_total) ?>
                                            </th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Info Perhitungan -->
                            <div class="alert alert-info mt-3">
                                <h6><i class="bi bi-info-circle me-2"></i>Info Perhitungan:</h6>
                                <small>
                                    • Subtotal = Harga per Meter × Meter<br>
                                    • Total per Transaksi = Jumlah semua subtotal dalam transaksi yang sama<br>
                                    • Grand Total = Jumlah semua subtotal dari seluruh data<br>
                                    • <span class="text-danger"><i class="bi bi-exclamation-triangle"></i> DB:</span> menunjukkan nilai subtotal yang tersimpan di database (jika berbeda dengan perhitungan)
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <!-- DataTables Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Inisialisasi DataTables
        $(document).ready(function() {
            $('#dataTable').DataTable({
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data per halaman",
                    "zeroRecords": "Data tidak ditemukan",
                    "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                    "infoEmpty": "Tidak ada data",
                    "infoFiltered": "(disaring dari _MAX_ total data)",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                },
                "order": [
                    [1, "desc"]
                ],
                "pageLength": 50,
                "responsive": true,
                "dom": '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                "columnDefs": [{
                    "orderable": false,
                    "targets": [0, 2, 3, 4, 5, 6, 7, 8]
                }]
            });

            // Inisialisasi Select2 untuk filter bahan
            $('.select2-bahan-summary, .select2-bahan-detail').select2({
                placeholder: "Pilih bahan...",
                allowClear: true,
                width: '100%'
            });

            // Set placeholder berdasarkan form
            $('.select2-bahan-summary').select2('option', 'placeholder', 'Pilih bahan untuk ringkasan (kosongkan untuk semua)');
            $('.select2-bahan-detail').select2('option', 'placeholder', 'Pilih bahan untuk detail');
        });

        // Fungsi untuk mengubah form filter ringkasan
        function updateFilterSummary() {
            const form = document.getElementById('filterSummaryForm');
            form.action = window.location.pathname;
        }

        // Fungsi untuk mengubah form filter detail
        function updateFilterDetail() {
            const form = document.getElementById('filterDetailForm');
            form.action = window.location.pathname;
        }

        // Cetak laporan
        function printReport() {
            window.print();
        }

        // Export data ke Excel
        function exportToExcel() {
            Swal.fire({
                title: 'Export ke Excel',
                text: 'Sedang menyiapkan data...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();

                    setTimeout(() => {
                        let csvContent = "data:text/csv;charset=utf-8,\uFEFF";

                        // Header
                        const headers = [
                            'No',
                            'Tanggal',
                            'Supplier',
                            'Bahan Baku',
                            'Jumlah (pcs)',
                            'Meter (m)',
                            'Harga per Meter',
                            'Subtotal',
                            'Status'
                        ];
                        csvContent += headers.join(",") + "\r\n";

                        // Data
                        <?php
                        $export_number = 0;
                        foreach ($detail_pembelian as $item):
                            $export_number++;
                            $hitung_subtotal = $item['harga_satuan'] * $item['meter'];
                        ?>
                            const rowData = [
                                "<?= $export_number ?>",
                                "<?= date('d/m/Y H:i', strtotime($item['tanggal_pembelian'])) ?>",
                                "<?= addslashes($item['nama_supplier']) ?>",
                                "<?= addslashes($item['nama_bahan']) ?>",
                                "<?= $item['jumlah'] ?>",
                                "<?= number_format($item['meter'], 2, ',', '.') ?>",
                                "<?= $item['harga_satuan'] ?>",
                                "<?= $hitung_subtotal ?>",
                                "<?= ucfirst($item['status_pembayaran']) ?>"
                            ];
                            csvContent += rowData.map(cell => `"${cell}"`).join(",") + "\r\n";
                        <?php endforeach; ?>

                        // Download
                        const encodedUri = encodeURI(csvContent);
                        const link = document.createElement("a");
                        link.setAttribute("href", encodedUri);
                        link.setAttribute("download", `laporan_pembelian_bahan_${new Date().toISOString().split('T')[0]}.csv`);
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        Swal.close();
                    }, 1000);
                }
            });
        }

        // Reset filter tanggal saat halaman dimuat ulang
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('tanggal_awal') && !urlParams.has('tanggal_akhir')) {
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

                document.querySelector('#filterDetailForm input[name="tanggal_awal"]').value = firstDay.toISOString().split('T')[0];
                document.querySelector('#filterDetailForm input[name="tanggal_akhir"]').value = lastDay.toISOString().split('T')[0];
            }
        });
    </script>
</body>

</html>