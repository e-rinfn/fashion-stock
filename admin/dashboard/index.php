<?php
require_once '../../config/auth.php';
require_role('admin');

$title = "Admin Dashboard";

// Koneksi database
require_once '../../config/database.php';

// Query untuk data dashboard
$product_count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$low_stock_count = $pdo->query("SELECT COUNT(*) FROM products WHERE stok < 10")->fetchColumn();
$today_sales = $pdo->query("SELECT SUM(total) FROM transactions WHERE tipe = 'keluar' AND DATE(tanggal) = CURDATE()")->fetchColumn();
$today_purchases = $pdo->query("SELECT SUM(total) FROM transactions WHERE tipe = 'masuk' AND DATE(tanggal) = CURDATE()")->fetchColumn();
$pending_installments = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status_bayar = 'cicilan'")->fetchColumn();

// Data untuk chart (7 hari terakhir)
$sales_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM transactions WHERE tipe = 'keluar' AND DATE(tanggal) = ?");
    $stmt->execute([$date]);
    $sales_data[] = [
        'date' => date('d M', strtotime($date)),
        'amount' => $stmt->fetchColumn()
    ];
}

// Produk dengan stok rendah
$low_stock_products = $pdo->query("SELECT nama, stok FROM products WHERE stok < 10 ORDER BY stok ASC LIMIT 5")->fetchAll();

// Transaksi terakhir
$recent_transactions = $pdo->query("
    SELECT t.id, t.kode_transaksi, t.tanggal, t.total, COUNT(td.id) as items, t.customer_nama 
    FROM transactions t
    JOIN transaction_details td ON t.id = td.transaction_id
    WHERE t.tipe = 'keluar'
    GROUP BY t.id
    ORDER BY t.tanggal DESC
    LIMIT 5
")->fetchAll();
?>


<!-- Head start -->
<?php include '../../includes/head.php'; ?>
<!-- Head end -->

<body>
    <div id="app">

        <!-- Sidebar start -->
        <?php include '../../includes/side.php'; ?>
        <!-- sidebar end -->

        <!-- main content start -->
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

            <!-- Content title start -->
            <div class="page-heading">
                <h3>DASHBOARD ADMIN</h3>
            </div>
            <!-- Content title end -->

            <!-- Content Start -->

            <di class="page-content">

                <section class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Produk</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $product_count ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-boxes fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Stok Rendah</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $low_stock_count ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Penjualan Hari Ini</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?= number_format($today_sales ?? 0, 0, ',', '.') ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-cash-register fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Angsuran Tertunda</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $pending_installments ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <!-- Grafik dan Tabel -->
                    <div class="row">
                        <!-- Grafik Penjualan -->
                        <div class="col-xl-8 col-lg-7">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Grafik Penjualan 7 Hari Terakhir</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-area">
                                        <canvas id="salesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Produk Stok Rendah -->
                        <div class="col-xl-4 col-lg-5">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-warning">Produk Stok Rendah</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (count($low_stock_products) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Produk</th>
                                                        <th class="text-end">Stok</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($low_stock_products as $product): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($product['nama']) ?></td>
                                                            <td class="text-end text-danger fw-bold"><?= $product['stok'] ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <a href="../products/" class="btn btn-sm btn-warning">Kelola Stok</a>
                                    <?php else: ?>
                                        <div class="text-center text-success py-3">
                                            <i class="fas fa-check-circle fa-3x mb-2"></i>
                                            <p>Tidak ada produk dengan stok rendah</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transaksi Terakhir -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Transaksi Penjualan Terakhir</h6>
                                    <a href="../transactions/out/" class="btn btn-sm btn-primary">Lihat Semua</a>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Kode Transaksi</th>
                                                    <th>Tanggal</th>
                                                    <th>Customer</th>
                                                    <th>Item</th>
                                                    <th class="text-end">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_transactions as $transaction): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($transaction['kode_transaksi']) ?></td>
                                                        <td><?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?></td>
                                                        <td><?= htmlspecialchars($transaction['customer_nama'] ?? '-') ?></td>
                                                        <td><?= $transaction['items'] ?></td>
                                                        <td class="text-end">Rp <?= number_format($transaction['total'], 0, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
        </div>
    </div>
    <!-- Content End -->
    </div>
    <!-- main content end -->

    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($sales_data, 'date')) ?>,
                datasets: [{
                    label: 'Penjualan (Rp)',
                    data: <?= json_encode(array_column($sales_data, 'amount')) ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.raw.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    </script>


    <script>
        // Tampilkan field angsuran jika metode kredit dipilih
        document.getElementById('metode_bayar').addEventListener('change', function() {
            const angsuranFields = document.getElementById('angsuranFields');
            if (this.value === 'kredit') {
                angsuranFields.style.display = 'block';
            } else {
                angsuranFields.style.display = 'none';
            }
        });
    </script>


    <script src="../../assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>

    <script src="../../assets/vendors/apexcharts/apexcharts.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>

    <script src="../../assets/js/main.js"></script>
</body>

</html>