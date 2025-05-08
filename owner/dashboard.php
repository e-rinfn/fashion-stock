<?php
require_once '../config/auth.php';
require_role('owner');

$title = "Dashboard Owner";
include '../includes/header.php';

require_once '../config/database.php';

// Query untuk statistik dashboard
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalSales = $pdo->query("SELECT SUM(total) FROM transactions WHERE tipe = 'keluar' AND DATE(tanggal) = CURDATE()")->fetchColumn();
$totalPurchases = $pdo->query("SELECT SUM(total) FROM transactions WHERE tipe = 'masuk' AND DATE(tanggal) = CURDATE()")->fetchColumn();
$pendingInstallments = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status_bayar = 'cicilan'")->fetchColumn();
?>

<?php require_once '../includes/sidebar.php'; ?>


<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center">
                <h1 class="mb-0">Dashboard Owner</h1>
                <div class="last-login">
                    <small class="text-muted">Login terakhir: <?= date('d/m/Y H:i', strtotime($_SESSION['last_login'] ?? 'now')) ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistik Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Produk</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalProducts ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?= number_format($totalSales ?? 0, 0, ',', '.') ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
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
                                Pembelian Hari Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?= number_format($totalPurchases ?? 0, 0, ',', '.') ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
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
                                Angsuran Tertunda</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $pendingInstallments ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-comments-dollar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

        <!-- Produk Terlaris -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">5 Produk Terlaris</h6>
                </div>
                <div class="card-body">
                    <?php
                    $topProducts = $pdo->query("
                        SELECT p.nama, SUM(td.quantity) as total_terjual 
                        FROM transaction_details td
                        JOIN products p ON td.product_id = p.id
                        JOIN transactions t ON td.transaction_id = t.id
                        WHERE t.tipe = 'keluar'
                        GROUP BY p.id
                        ORDER BY total_terjual DESC
                        LIMIT 5
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <div class="list-group">
                        <?php foreach ($topProducts as $product): ?>
                            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($product['nama']) ?>
                                <span class="badge bg-primary rounded-pill"><?= $product['total_terjual'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Transaksi Terakhir -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">5 Transaksi Terakhir</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>No. Transaksi</th>
                                    <th>Tanggal</th>
                                    <th>Tipe</th>
                                    <th>Total</th>
                                    <th>Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recentTransactions = $pdo->query("
                                    SELECT t.*, u.full_name as admin 
                                    FROM transactions t
                                    JOIN users u ON t.user_id = u.id
                                    ORDER BY t.tanggal DESC
                                    LIMIT 5
                                ")->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($recentTransactions as $transaction):
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($transaction['kode_transaksi']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?></td>
                                        <td>
                                            <span class="badge <?= $transaction['tipe'] === 'masuk' ? 'bg-success' : 'bg-primary' ?>">
                                                <?= $transaction['tipe'] === 'masuk' ? 'Barang Masuk' : 'Penjualan' ?>
                                            </span>
                                        </td>
                                        <td>Rp <?= number_format($transaction['total'], 0, ',', '.') ?></td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-info">Lihat</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Grafik Penjualan
    document.addEventListener('DOMContentLoaded', function() {
        // Data untuk grafik (contoh, bisa diganti dengan data real dari database)
        var ctx = document.getElementById('salesChart').getContext('2d');
        var salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
                datasets: [{
                    label: 'Penjualan',
                    data: [1200000, 1900000, 3000000, 2500000, 2200000, 3500000, 4000000],
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    pointRadius: 3,
                    pointHoverRadius: 5,
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
                                return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.raw.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>