<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Set default bulan ke bulan ini jika tidak ada parameter
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$startDate = date('Y-m-01', strtotime($bulan));
$endDate = date('Y-m-t', strtotime($bulan));
$namaBulan = date('F Y', strtotime($bulan));

// Query untuk mendapatkan data transaksi (gunakan query yang sudah diperbaiki sebelumnya)
$transaksi = query("
    SELECT * FROM (
        -- Data Penjualan Produk
        SELECT 
            'Penjualan Produk' AS jenis, 
            tanggal_penjualan AS tanggal, 
            CONCAT('Penjualan Produk #', id_penjualan) AS keterangan, 
            total_harga AS jumlah, 
            CASE 
                WHEN status_pembayaran = 'lunas' THEN 'pemasukan-lunas'
                WHEN status_pembayaran = 'cicilan' THEN 'pemasukan-cicilan'
                ELSE 'pemasukan-belum-lunas'
            END AS tipe,
            status_pembayaran,
            'penjualan_produk' AS sumber,
            id_penjualan AS id_sumber
        FROM penjualan
        WHERE tanggal_penjualan BETWEEN '$startDate' AND '$endDate'
        
        UNION ALL
        
        -- Data Penjualan Bahan
        SELECT 
            'Penjualan Bahan' AS jenis, 
            tanggal_penjualan_bahan AS tanggal, 
            CONCAT('Penjualan Bahan #', id_penjualan_bahan) AS keterangan, 
            total_harga AS jumlah, 
            CASE 
                WHEN status_pembayaran = 'lunas' THEN 'pemasukan-lunas'
                WHEN status_pembayaran = 'cicilan' THEN 'pemasukan-cicilan'
                ELSE 'pemasukan-belum-lunas'
            END AS tipe,
            status_pembayaran,
            'penjualan_bahan' AS sumber,
            id_penjualan_bahan AS id_sumber
        FROM penjualan_bahan
        WHERE tanggal_penjualan_bahan BETWEEN '$startDate' AND '$endDate'
        
        UNION ALL
        
        -- Data Pembelian Bahan
        SELECT 
            'Pembelian Bahan' AS jenis, 
            tanggal_pembelian AS tanggal, 
            CONCAT('Pembelian Bahan #', id_pembelian_bahan) AS keterangan, 
            total_harga AS jumlah, 
            'pengeluaran' AS tipe,
            'lunas' as status_pembayaran,
            'pembelian_bahan' AS sumber,
            id_pembelian_bahan AS id_sumber
        FROM pembelian_bahan
        WHERE tanggal_pembelian BETWEEN '$startDate' AND '$endDate'

        UNION ALL

        -- Data Pembelian Produk
        SELECT 
            'Pembelian Produk' AS jenis, 
            tanggal_pembelian AS tanggal, 
            CONCAT('Pembelian Produk #', id_pembelian) AS keterangan, 
            total_harga AS jumlah, 
            'pengeluaran' AS tipe,
            'lunas' as status_pembayaran,
            'pembelian' AS sumber,
            id_pembelian AS id_sumber
        FROM pembelian
        WHERE tanggal_pembelian BETWEEN '$startDate' AND '$endDate'
    ) AS transaksi
    ORDER BY tanggal DESC, id_sumber DESC;
");

// Hitung total berdasarkan tipe
$summary = [
    'pemasukan-lunas' => 0,
    'pemasukan-cicilan' => 0,
    'pemasukan-belum-lunas' => 0,
    'pengeluaran' => 0
];

foreach ($transaksi as $trx) {
    if (isset($summary[$trx['tipe']])) {
        $summary[$trx['tipe']] += $trx['jumlah'];
    }
}

// Hitung total
$total_pemasukan = $summary['pemasukan-lunas'] + $summary['pemasukan-cicilan'] + $summary['pemasukan-belum-lunas'];
$pengeluaran = $summary['pengeluaran'];
$laba_bersih = $total_pemasukan - $pengeluaran;

// Hitung untuk chart penjualan produk dan bahan
$pemasukan_lunas = query("SELECT COALESCE(SUM(total_harga), 0) as total FROM penjualan WHERE status_pembayaran = 'lunas' AND tanggal_penjualan BETWEEN '$startDate' AND '$endDate'")[0]['total'];
$pemasukan_belum_lunas = query("SELECT COALESCE(SUM(total_harga), 0) as total FROM penjualan WHERE status_pembayaran != 'lunas' AND tanggal_penjualan BETWEEN '$startDate' AND '$endDate'")[0]['total'];

$pemasukan_lunas_bahan = query("SELECT COALESCE(SUM(total_harga), 0) as total FROM penjualan_bahan WHERE status_pembayaran = 'lunas' AND tanggal_penjualan_bahan BETWEEN '$startDate' AND '$endDate'")[0]['total'];
$pemasukan_belum_lunas_bahan = query("SELECT COALESCE(SUM(total_harga), 0) as total FROM penjualan_bahan WHERE status_pembayaran != 'lunas' AND tanggal_penjualan_bahan BETWEEN '$startDate' AND '$endDate'")[0]['total'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - <?= $namaBulan ?></title>
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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-semibold mb-0">Laporan Keuangan <?= $namaBulan ?></h4>
                    <form method="get" class="d-flex align-items-center gap-2 no-print">
                        <input type="month" name="bulan" value="<?= $bulan ?>" class="form-control form-control-sm" style="width: 160px;">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <a href="keuangan.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                        <button type="button" hidden class="btn btn-sm btn-success" onclick="printReport()">
                            <i class="ti ti-printer"></i> Cetak
                        </button>
                    </form>
                </div>

                <!-- Ringkasan Keuangan -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">Ringkasan Keuangan Bulan Ini</h6>
                        <div class="row text-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="fw-medium text-muted">Total Pemasukan</div>
                                <div class="fs-5 text-success"><?= formatRupiah($total_pemasukan) ?></div>
                                <small class="text-muted d-block">
                                    Lunas: <?= formatRupiah($summary['pemasukan-lunas']) ?> |
                                    Cicilan: <?= formatRupiah($summary['pemasukan-cicilan']) ?>
                                </small>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="fw-medium text-muted">Total Pengeluaran</div>
                                <div class="fs-5 text-danger"><?= formatRupiah($pengeluaran) ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="fw-medium text-muted">Laba Bersih</div>
                                <div class="fs-5 <?= $laba_bersih >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= formatRupiah($laba_bersih) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Ringkasan -->
                <div class="row mb-4">
                    <!-- Penjualan Produk -->
                    <div class="col-md-6">
                        <div class="card card-summary mb-3">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Penjualan Produk</h5>
                                <span class="badge bg-primary"><?= formatRupiah($pemasukan_lunas + $pemasukan_belum_lunas) ?></span>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Lunas:</span>
                                            <strong class="text-success"><?= formatRupiah($pemasukan_lunas) ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Belum Lunas:</span>
                                            <strong class="text-warning"><?= formatRupiah($pemasukan_belum_lunas) ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <canvas id="chartPenjualanProduk"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Penjualan Bahan -->
                    <div class="col-md-6">
                        <div class="card card-summary mb-3">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Penjualan Bahan</h5>
                                <span class="badge bg-primary"><?= formatRupiah($pemasukan_lunas_bahan + $pemasukan_belum_lunas_bahan) ?></span>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Lunas:</span>
                                            <strong class="text-success"><?= formatRupiah($pemasukan_lunas_bahan) ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Belum Lunas:</span>
                                            <strong class="text-warning"><?= formatRupiah($pemasukan_belum_lunas_bahan) ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <canvas id="chartPenjualanBahan"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail Transaksi -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Detail Transaksi</h5>
                        <div class="no-print">
                            <span class="badge bg-light text-dark">
                                Total: <?= count($transaksi) ?> transaksi
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-transaksi table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="15%">Tanggal</th>
                                        <th width="20%">Jenis</th>
                                        <th width="30%">Keterangan</th>
                                        <th width="15%" class="text-end">Jumlah</th>
                                        <th width="15%">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transaksi)) : ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="ti ti-receipt-off fs-1"></i>
                                                <p class="mt-2">Tidak ada transaksi pada periode ini</p>
                                            </td>
                                        </tr>
                                    <?php else : ?>
                                        <?php $no = 1; ?>
                                        <?php foreach ($transaksi as $trx) : ?>
                                            <tr class="<?= str_contains($trx['tipe'], 'pemasukan') ? 'table-success' : 'table-danger' ?>">
                                                <td class="text-center"><?= $no++ ?></td>
                                                <td><?= date('d/m/Y', strtotime($trx['tanggal'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= str_contains($trx['tipe'], 'pemasukan') ? 'success' : 'danger' ?>">
                                                        <?= htmlspecialchars($trx['jenis']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($trx['keterangan']) ?></td>
                                                <td class="text-end fw-bold <?= str_contains($trx['tipe'], 'pemasukan') ? 'text-success' : 'text-danger' ?>">
                                                    <?= str_contains($trx['tipe'], 'pemasukan') ? '+' : '-' ?>
                                                    <?= formatRupiah($trx['jumlah']) ?>
                                                </td>
                                                <td>
                                                    <?php if (str_contains($trx['tipe'], 'pemasukan')) : ?>
                                                        <span class="badge badge-status bg-<?=
                                                                                            $trx['status_pembayaran'] === 'lunas' ? 'success' : ($trx['status_pembayaran'] === 'cicilan' ? 'warning' : 'danger')
                                                                                            ?>">
                                                            <?=
                                                            $trx['status_pembayaran'] === 'lunas' ? 'Lunas' : ($trx['status_pembayaran'] === 'cicilan' ? 'Cicilan' : 'Belum Lunas')
                                                            ?>
                                                        </span>
                                                    <?php else : ?>
                                                        <span class="badge badge-status bg-secondary">Pengeluaran</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="table-active">
                                    <tr>
                                        <th colspan="4" class="text-end">TOTAL LABA BERSIH</th>
                                        <th class="text-end <?= $laba_bersih >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= formatRupiah($laba_bersih) ?>
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Grafik -->
                <div class="row no-print">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Perbandingan Pemasukan & Pengeluaran</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 300px;">
                                    <canvas id="perbandinganChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart Penjualan Produk
            const ctxProduk = document.getElementById('chartPenjualanProduk');
            if (ctxProduk) {
                new Chart(ctxProduk, {
                    type: 'doughnut',
                    data: {
                        labels: ['Lunas', 'Belum Lunas'],
                        datasets: [{
                            data: [<?= $pemasukan_lunas ?>, <?= $pemasukan_belum_lunas ?>],
                            backgroundColor: ['#28a745', '#ffc107'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                        return `${label}: ${formatRupiahJS(value)} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Chart Penjualan Bahan
            const ctxBahan = document.getElementById('chartPenjualanBahan');
            if (ctxBahan) {
                new Chart(ctxBahan, {
                    type: 'doughnut',
                    data: {
                        labels: ['Lunas', 'Belum Lunas'],
                        datasets: [{
                            data: [<?= $pemasukan_lunas_bahan ?>, <?= $pemasukan_belum_lunas_bahan ?>],
                            backgroundColor: ['#28a745', '#ffc107'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                        return `${label}: ${formatRupiahJS(value)} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Chart Perbandingan
            const perbandinganCtx = document.getElementById('perbandinganChart');
            if (perbandinganCtx) {
                new Chart(perbandinganCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Pemasukan', 'Pengeluaran', 'Laba Bersih'],
                        datasets: [{
                            label: 'Jumlah (Rp)',
                            data: [<?= $total_pemasukan ?>, <?= $pengeluaran ?>, <?= $laba_bersih ?>],
                            backgroundColor: [
                                'rgba(40, 167, 69, 0.8)',
                                'rgba(220, 53, 69, 0.8)',
                                'rgba(23, 162, 184, 0.8)'
                            ],
                            borderColor: [
                                'rgba(40, 167, 69, 1)',
                                'rgba(220, 53, 69, 1)',
                                'rgba(23, 162, 184, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatRupiahJS(value);
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + formatRupiahJS(context.raw);
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });

        function formatRupiahJS(number) {
            return 'Rp ' + Math.round(number).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function printReport() {
            window.print();
        }
    </script>
</body>

</html>