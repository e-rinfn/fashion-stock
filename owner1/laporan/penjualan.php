<?php
$pageTitle = "Laporan Penjualan";
require_once '../includes/header.php';

// Filter tanggal
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Query data penjualan
$penjualan = query("SELECT p.id_penjualan, p.tanggal_penjualan, r.nama_reseller, 
                    p.total_harga, p.status_pembayaran, 
                    (SELECT SUM(jumlah_cicilan) FROM cicilan WHERE id_penjualan = p.id_penjualan) as dibayar
                    FROM penjualan p
                    JOIN reseller r ON p.id_reseller = r.id_reseller
                    WHERE p.tanggal_penjualan BETWEEN '$startDate' AND '$endDate'
                    ORDER BY p.tanggal_penjualan DESC");

// Hitung total penjualan
$totalPenjualan = query("SELECT SUM(total_harga) as total FROM penjualan 
                       WHERE tanggal_penjualan BETWEEN '$startDate' AND '$endDate'")[0]['total'] ?? 0;

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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Data Penjualan</h2>
            </div>


            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Detail Penjualan</h5>
                </div>

                <div class="card-body">
                    <form method="get" class="row g-2 mb-3">
                        <div class="col-auto">
                            <input type="date" name="start_date" value="<?= $startDate ?>" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <input type="date" name="end_date" value="<?= $endDate ?>" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="penjualan.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Reseller</th>
                                    <th>Total</th>
                                    <th>Dibayar</th>
                                    <th>Sisa</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($penjualan)) : ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Tidak ada data penjualan</td>
                                    </tr>
                                <?php else : ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($penjualan as $jual) : ?>
                                        <?php
                                        $dibayar = $jual['dibayar'] ?? 0;
                                        $sisa = $jual['total_harga'] - $dibayar;
                                        ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= date('d/m/Y', strtotime($jual['tanggal_penjualan'])) ?></td>
                                            <td><?= htmlspecialchars($jual['nama_reseller']) ?></td>
                                            <td class="text-end"><?= formatRupiah($jual['total_harga']) ?></td>
                                            <td class="text-end"><?= formatRupiah($dibayar) ?></td>
                                            <td class="text-end"><?= formatRupiah($sisa) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $jual['status_pembayaran'] === 'lunas' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($jual['status_pembayaran']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../penjualan/detail.php?id=<?= $jual['id_penjualan'] ?>"
                                                    class="btn btn-sm btn-info" title="Detail">
                                                    <i class="bx bx-detail"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-active">
                                        <th colspan="3" class="text-end">TOTAL</th>
                                        <th class="text-end fs-6"><?= formatRupiah($totalPenjualan) ?></th>
                                        <th colspan="4"></th>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Statistik Penjualan</h5>
                </div>
                <div class="card-body">
                    <canvas id="penjualanChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart Penjualan
            const ctx = document.getElementById('penjualanChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($penjualan, 'nama_reseller')) ?>,
                    datasets: [{
                        label: 'Total Penjualan',
                        data: <?= json_encode(array_column($penjualan, 'total_harga')) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Rp' + context.raw.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>


</body>
<!-- [Body] end -->

</html>