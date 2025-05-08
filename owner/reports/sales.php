<?php
require_once '../../config/auth.php';
require_role('owner');

$title = "Laporan Penjualan";
include '../../includes/header.php';

require_once '../../config/database.php';

// Filter tanggal
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Query untuk laporan penjualan
$salesReport = $pdo->prepare("
    SELECT 
        DATE(t.tanggal) as tanggal,
        COUNT(DISTINCT t.id) as jumlah_transaksi,
        SUM(t.total) as total_penjualan,
        SUM(td.quantity) as total_barang_terjual
    FROM 
        transactions t
    JOIN 
        transaction_details td ON t.id = td.transaction_id
    WHERE 
        t.tipe = 'keluar'
        AND DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY 
        DATE(t.tanggal)
    ORDER BY 
        tanggal DESC
");
$salesReport->execute([$start_date, $end_date]);
$salesData = $salesReport->fetchAll(PDO::FETCH_ASSOC);

// Total keseluruhan
$totalSales = array_sum(array_column($salesData, 'total_penjualan'));
$totalTransactions = array_sum(array_column($salesData, 'jumlah_transaksi'));
$totalItemsSold = array_sum(array_column($salesData, 'total_barang_terjual'));
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Laporan Penjualan</h4>
                    <div>
                        <button class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                        <button class="btn btn-danger" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filter Form -->
                    <form method="GET" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Dari Tanggal</label>
                                <input type="date" class="form-control" id="start_date" name="start_date"
                                    value="<?= $start_date ?>" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">Sampai Tanggal</label>
                                <input type="date" class="form-control" id="end_date" name="end_date"
                                    value="<?= $end_date ?>" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Transaksi</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalTransactions ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-receipt fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Total Penjualan</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?= number_format($totalSales, 0, ',', '.') ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Barang Terjual</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalItemsSold ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-box-open fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Laporan -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="salesReportTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jumlah Transaksi</th>
                                    <th>Barang Terjual</th>
                                    <th>Total Penjualan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salesData as $row): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                        <td><?= $row['jumlah_transaksi'] ?></td>
                                        <td><?= $row['total_barang_terjual'] ?></td>
                                        <td>Rp <?= number_format($row['total_penjualan'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th><?= $totalTransactions ?></th>
                                    <th><?= $totalItemsSold ?></th>
                                    <th>Rp <?= number_format($totalSales, 0, ',', '.') ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function exportToExcel() {
        // Implementasi export ke Excel
        alert('Fitur export Excel akan diimplementasikan');
    }

    function exportToPDF() {
        // Implementasi export ke PDF
        alert('Fitur export PDF akan diimplementasikan');
    }
</script>

<?php include '../../includes/footer.php'; ?>