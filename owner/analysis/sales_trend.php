<?php
require_once '../../config/auth.php';
require_role('owner');

$title = "Analisis Trend Penjualan";
include '../../includes/header.php';

// Default: 6 bulan terakhir
$start_date = date('Y-m-01', strtotime('-5 months'));
$end_date = date('Y-m-t');

if (isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}
if (isset($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

require_once '../../config/database.php';

// Query untuk data trend bulanan
$monthly_sales = $pdo->prepare("
    SELECT 
        DATE_FORMAT(t.tanggal, '%Y-%m') as month,
        SUM(t.total) as total_sales,
        COUNT(DISTINCT t.id) as transaction_count,
        SUM(td.quantity) as total_quantity
    FROM transactions t
    JOIN transaction_details td ON t.id = td.transaction_id
    WHERE t.tipe = 'keluar' AND t.tanggal BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(t.tanggal, '%Y-%m')
    ORDER BY month ASC
");
$monthly_sales->execute([$start_date, $end_date]);
$sales_data = $monthly_sales->fetchAll();

// Siapkan data untuk chart
$labels = [];
$sales = [];
$transactions = [];
$quantities = [];

foreach ($sales_data as $data) {
    $labels[] = date('M Y', strtotime($data['month'] . '-01'));
    $sales[] = $data['total_sales'];
    $transactions[] = $data['transaction_count'];
    $quantities[] = $data['total_quantity'];
}
?>

<div class="container py-4">
    <h1 class="mb-4">Analisis Trend Penjualan</h1>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="sales_trend.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Grafik Trend Penjualan</h5>
        </div>
        <div class="card-body">
            <canvas id="salesChart" height="300"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Data Trend Penjualan</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th>Total Penjualan</th>
                            <th>Jumlah Transaksi</th>
                            <th>Jumlah Item Terjual</th>
                            <th>Rata-rata per Transaksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_data as $data):
                            $avg_per_transaction = $data['transaction_count'] > 0 ?
                                $data['total_sales'] / $data['transaction_count'] : 0;
                        ?>
                            <tr>
                                <td><?= date('F Y', strtotime($data['month'] . '-01')) ?></td>
                                <td>Rp <?= number_format($data['total_sales'], 0, ',', '.') ?></td>
                                <td><?= $data['transaction_count'] ?></td>
                                <td><?= $data['total_quantity'] ?></td>
                                <td>Rp <?= number_format($avg_per_transaction, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                    label: 'Total Penjualan (Rp)',
                    data: <?= json_encode($sales) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y'
                },
                {
                    label: 'Jumlah Transaksi',
                    data: <?= json_encode($transactions) ?>,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y1'
                },
                {
                    label: 'Jumlah Item Terjual',
                    data: <?= json_encode($quantities) ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y2'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Total Penjualan (Rp)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                    title: {
                        display: true,
                        text: 'Jumlah Transaksi'
                    }
                },
                y2: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                    title: {
                        display: true,
                        text: 'Jumlah Item Terjual'
                    }
                }
            }
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>