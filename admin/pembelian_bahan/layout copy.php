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
$filter_bahan = isset($_GET['bahan']) ? intval($_GET['bahan']) : 0;
$filter_tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : '';
$filter_tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

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

// Filter bahan
if ($filter_bahan > 0) {
    $query .= " AND dpb.id_bahan = $filter_bahan";
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

// Hitung summary total
$total_query = "SELECT 
                  COUNT(DISTINCT pb.id_pembelian_bahan) as total_transaksi,
                  COUNT(*) as total_item,
                  SUM(dpb.subtotal) as total_nilai,
                  SUM(dpb.jumlah) as total_jumlah,
                  SUM(dpb.meter) as total_meter,
                  SUM(dpb.jumlah * dpb.meter) as total_meter_all
                FROM detail_pembelian_bahan dpb
                JOIN pembelian_bahan pb ON dpb.id_pembelian_bahan = pb.id_pembelian_bahan
                JOIN supplier s ON pb.id_supplier = s.id_supplier
                JOIN bahan_baku b ON dpb.id_bahan = b.id_bahan
                WHERE 1=1";

// Tambahkan filter yang sama untuk total
if (!empty($search)) {
    $total_query .= " AND (pb.no_transaksi LIKE '%$search%' 
                    OR s.nama_supplier LIKE '%$search%'
                    OR b.nama_bahan LIKE '%$search%')";
}

if ($filter_supplier > 0) {
    $total_query .= " AND pb.id_supplier = $filter_supplier";
}

if ($filter_bahan > 0) {
    $total_query .= " AND dpb.id_bahan = $filter_bahan";
}

if (!empty($filter_tanggal_awal) && !empty($filter_tanggal_akhir)) {
    $total_query .= " AND DATE(pb.tanggal_pembelian) BETWEEN '$filter_tanggal_awal' AND '$filter_tanggal_akhir'";
} elseif (!empty($filter_tanggal_awal)) {
    $total_query .= " AND DATE(pb.tanggal_pembelian) >= '$filter_tanggal_awal'";
} elseif (!empty($filter_tanggal_akhir)) {
    $total_query .= " AND DATE(pb.tanggal_pembelian) <= '$filter_tanggal_akhir'";
}

$summary = query($total_query)[0];

// Hitung summary per bahan jika tidak difilter bahan tertentu
$summary_per_bahan = [];
if ($filter_bahan == 0) {
    $bahan_query = "SELECT 
                      b.id_bahan,
                     
                      b.nama_bahan,
                      b.satuan,
                      SUM(dpb.jumlah) as total_jumlah,
                      SUM(dpb.meter) as total_meter,
                      SUM(dpb.subtotal) as total_subtotal
                    FROM detail_pembelian_bahan dpb
                    JOIN bahan_baku b ON dpb.id_bahan = b.id_bahan
                    JOIN pembelian_bahan pb ON dpb.id_pembelian_bahan = pb.id_pembelian_bahan
                    WHERE 1=1";

    if (!empty($search)) {
        $bahan_query .= " AND (pb.no_transaksi LIKE '%$search%' 
                        OR b.nama_bahan LIKE '%$search%')";
    }

    if ($filter_supplier > 0) {
        $bahan_query .= " AND pb.id_supplier = $filter_supplier";
    }

    if (!empty($filter_tanggal_awal) && !empty($filter_tanggal_akhir)) {
        $bahan_query .= " AND DATE(pb.tanggal_pembelian) BETWEEN '$filter_tanggal_awal' AND '$filter_tanggal_akhir'";
    } elseif (!empty($filter_tanggal_awal)) {
        $bahan_query .= " AND DATE(pb.tanggal_pembelian) >= '$filter_tanggal_awal'";
    } elseif (!empty($filter_tanggal_akhir)) {
        $bahan_query .= " AND DATE(pb.tanggal_pembelian) <= '$filter_tanggal_akhir'";
    }

    $bahan_query .= " GROUP BY b.id_bahan, b.nama_bahan, b.satuan
                      ORDER BY b.nama_bahan";

    $summary_per_bahan = query($bahan_query);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pembelian Bahan Baku</title>
    <!-- Tambahkan CSS DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

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
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-clipboard-data me-2"></i>LAPORAN PEMBELIAN BAHAN BAKU</h2>
                    </div>

                    <!-- Card Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="summary-card purple">
                                <h3><?= number_format($summary['total_transaksi'] ?? 0, 0, ',', '.') ?></h3>
                                <p>Total Transaksi</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h3><?= number_format($summary['total_item'] ?? 0, 0, ',', '.') ?></h3>
                                <p>Total Item Dibeli</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card orange">
                                <h3><?= number_format($summary['total_meter_all'] ?? 0, 1, ',', '.') ?> m</h3>
                                <p>Total Meter Pembelian</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card green">
                                <h3><?= formatRupiah($summary['total_nilai'] ?? 0) ?></h3>
                                <p>Total Nilai Pembelian</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Filter -->
                    <div class="card filter-card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Laporan</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
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

                                <div class="col-md-2">
                                    <label class="form-label">Bahan Baku</label>
                                    <select class="form-control" name="bahan">
                                        <option value="">Semua Bahan</option>
                                        <?php foreach ($bahan_list as $b): ?>
                                            <option value="<?= $b['id_bahan'] ?>"
                                                <?= $filter_bahan == $b['id_bahan'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($b['nama_bahan']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
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
                                    <div class="d-flex w-100">
                                        <a href="list.php" class="btn btn-secondary me-2 flex-grow-1">
                                            <i class="bi bi-arrow-clockwise me-1"></i> Reset
                                        </a>
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="bi bi-search me-1"></i> Filter
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Summary per Bahan (jika tidak difilter bahan tertentu) -->
                    <?php if (empty($filter_bahan) && !empty($summary_per_bahan)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Ringkasan per Bahan Baku</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0 summary-bahan-table">
                                        <thead>
                                            <tr>
                                                <th width="5%">No</th>
                                                <th width="30%">Nama Bahan</th>
                                                <th width="10%">Satuan</th>
                                                <th width="10%" class="text-end">Jumlah</th>
                                                <th width="10%" class="text-end">Total Meter</th>
                                                <th width="20%" class="text-end">Harga/Meter</th>
                                                <th width="20%" class="text-end">Total Nilai</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($summary_per_bahan as $index => $bahan): ?>
                                                <tr>
                                                    <td class="text-center"><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($bahan['nama_bahan']) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($bahan['satuan']) ?></td>
                                                    <td class="text-end"><?= number_format($bahan['total_jumlah'], 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= number_format($bahan['total_meter'], 2, ',', '.') ?> m</td>
                                                    <td class="text-end text-money"><?= formatRupiah($bahan['total_subtotal']) ?></td>
                                                    <td class="text-end text-money"><?= formatRupiah($bahan['total_subtotal'] * $bahan['total_meter']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <th colspan="3" class="text-end">TOTAL:</th>
                                                <th class="text-end"><?= number_format($summary['total_jumlah'] ?? 0, 0, ',', '.') ?></th>
                                                <th class="text-end"><?= number_format($summary['total_meter'] ?? 0, 2, ',', '.') ?> m</th>
                                                <th class="text-end text-primary"><?= formatRupiah($summary['total_nilai'] ?? 0) ?></th>
                                                <th class="text-end text-primary"><?= formatRupiah($summary['total_nilai'] ?? 0) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

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
                                                <td colspan="10" class="text-center py-5">
                                                    <div class="alert alert-info mb-0 d-flex flex-column align-items-center">
                                                        <i class="bi bi-inbox mb-2" style="font-size: 2rem;"></i>
                                                        <p class="mb-0">Tidak ada data pembelian bahan</p>
                                                        <?php if (!empty($search) || $filter_supplier > 0 || $filter_bahan > 0): ?>
                                                            <small class="text-muted">Coba gunakan filter yang berbeda</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php
                                            $current_pembelian_id = null;
                                            $row_number = 0;
                                            ?>

                                            <?php foreach ($detail_pembelian as $item): ?>
                                                <?php
                                                $row_number++;

                                                // Tampilkan baris supplier jika pembelian berbeda
                                                if ($current_pembelian_id != $item['id_pembelian_bahan']):
                                                    $current_pembelian_id = $item['id_pembelian_bahan'];
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
                                                        </td>
                                                        <td colspan="3"></td>
                                                        <td class="text-end">
                                                            <!-- Total per transaksi akan dihitung di JavaScript -->
                                                            <span id="total-<?= $item['id_pembelian_bahan'] ?>"></span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="status-badge <?= $status_class ?>">
                                                                <?= ucfirst($item['status_pembayaran']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>

                                                <!-- Baris detail bahan -->
                                                <tr class="bahan-row">
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($item['nama_bahan']) ?></div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary rounded-pill"><?= $item['jumlah'] ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= number_format($item['meter'], 2, ',', '.') ?> m
                                                    </td>
                                                    <td class="text-end text-money">
                                                        <?= formatRupiah($item['harga_satuan']) ?>
                                                    </td>
                                                    <td class="text-end text-money">
                                                        <strong><?= formatRupiah($item['subtotal']) ?></strong>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <th colspan="8" class="text-end">GRAND TOTAL:</th>
                                            <th class="text-end text-primary">
                                                <?= formatRupiah($summary['total_nilai'] ?? 0) ?>
                                            </th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
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
                ], // Urutkan berdasarkan tanggal descending
                "pageLength": 50,
                "responsive": true,
                "dom": '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                "columnDefs": [{
                    "orderable": false,
                    "targets": [0, 2, 3, 4, 5, 6, 7, 8, 9]
                }]
            });

            // Hitung total per transaksi
            calculateTransactionTotals();
        });

        // Fungsi untuk menghitung total per transaksi
        function calculateTransactionTotals() {
            const rows = document.querySelectorAll('#dataTable tbody tr.supplier-row');

            rows.forEach(row => {
                const pembelianId = row.querySelector('td:first-child').textContent.trim();
                let total = 0;

                // Cari baris bahan setelah baris supplier ini
                let nextRow = row.nextElementSibling;
                while (nextRow && nextRow.classList.contains('bahan-row')) {
                    const subtotalCell = nextRow.querySelector('td:nth-child(9)');
                    if (subtotalCell) {
                        const subtotalText = subtotalCell.textContent.trim();
                        const subtotalValue = parseFloat(subtotalText.replace(/[^0-9]/g, '')) || 0;
                        total += subtotalValue;
                    }
                    nextRow = nextRow.nextElementSibling;
                }

                // Tampilkan total
                const totalCell = row.querySelector('td:nth-child(9)');
                if (totalCell && total > 0) {
                    totalCell.innerHTML = `<strong>${formatRupiahJS(total)}</strong>`;
                }
            });
        }

        // Format Rupiah di JavaScript
        function formatRupiahJS(angka) {
            const number_string = angka.toString();
            const split = number_string.split('.');
            const sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            const ribuan = split[0].substr(sisa).match(/\d{3}/g);

            if (ribuan) {
                const separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
            return 'Rp ' + rupiah;
        }

        // Cetak laporan
        function printReport() {
            // Sembunyikan elemen yang tidak perlu dicetak
            const elementsToHide = document.querySelectorAll('.pc-container > .pc-content > .row > .col-12 > div:not(.card)');
            elementsToHide.forEach(el => {
                if (!el.classList.contains('card')) {
                    el.style.display = 'none';
                }
            });

            // Sembunyikan tombol aksi di tabel
            document.querySelectorAll('.btn-action-group').forEach(el => {
                el.style.display = 'none';
            });

            // Sembunyikan filter card
            document.querySelector('.filter-card').style.display = 'none';

            // Cetak
            window.print();

            // Tampilkan kembali elemen yang disembunyikan
            setTimeout(() => {
                elementsToHide.forEach(el => {
                    el.style.display = '';
                });

                document.querySelectorAll('.btn-action-group').forEach(el => {
                    el.style.display = '';
                });

                document.querySelector('.filter-card').style.display = '';
            }, 1000);
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
                            'No. Transaksi',
                            'Supplier',
                            'Bahan Baku',
                            'Kode Bahan',
                            'Jumlah',
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
                        ?>
                            const rowData = [
                                "<?= $export_number ?>",
                                "<?= date('d/m/Y H:i', strtotime($item['tanggal_pembelian'])) ?>",
                                "<?= addslashes($item['no_transaksi']) ?>",
                                "<?= addslashes($item['nama_supplier']) ?>",
                                "<?= addslashes($item['nama_bahan']) ?>",
                                "<?= $item['jumlah'] ?>",
                                "<?= number_format($item['meter'], 2, ',', '.') ?>",
                                "<?= $item['harga_satuan'] ?>",
                                "<?= $item['subtotal'] ?>",
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

        // Export data ke PDF
        function exportToPDF() {
            Swal.fire({
                title: 'Export ke PDF',
                text: 'Fitur ini membutuhkan konfigurasi server untuk generate PDF.',
                icon: 'info',
                confirmButtonText: 'OK'
            });
        }

        // Reset filter tanggal saat halaman dimuat ulang
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('tanggal_awal') && !urlParams.has('tanggal_akhir')) {
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

                document.querySelector('input[name="tanggal_awal"]').value = firstDay.toISOString().split('T')[0];
                document.querySelector('input[name="tanggal_akhir"]').value = lastDay.toISOString().split('T')[0];
            }
        });
    </script>
</body>

</html>