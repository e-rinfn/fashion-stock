<?php
// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

$page_title = "GRAFIK PENJUALAN PRODUK";

require_once '../includes/header.php';
require_once '../../config/functions.php';

// =================== DATA GRAFIK ===================

// Query untuk data penjualan per bulan (12 bulan terakhir)
$bulan_labels = [];
$penjualan_per_bulan = [];

for ($i = 11; $i >= 0; $i--) {
    $bulan = date('Y-m', strtotime("-$i months"));
    $bulan_labels[] = date('M Y', strtotime("-$i months"));

    // Query penjualan untuk bulan ini
    $query_bulan = "SELECT COALESCE(SUM(dp.subtotal), 0) as total
                    FROM detail_penjualan dp
                    JOIN penjualan pj ON dp.id_penjualan = pj.id_penjualan
                    WHERE DATE_FORMAT(pj.tanggal_penjualan, '%Y-%m') = '$bulan'
                    AND pj.status_pembayaran != 'batal'";

    $result_bulan = query($query_bulan);
    $penjualan_per_bulan[] = $result_bulan[0]['total'] ?? 0;
}

// Data untuk chart dalam format JSON
$chart_labels = json_encode($bulan_labels);
$chart_data = json_encode($penjualan_per_bulan);

// Ambil tahun-tahun yang tersedia untuk dropdown
$tahun_tersedia = query("SELECT DISTINCT YEAR(tanggal_penjualan) as tahun 
                          FROM penjualan 
                          WHERE status_pembayaran != 'batal' 
                          ORDER BY tahun DESC");

$tahun_grafik = isset($_GET['tahun_grafik']) ? intval($_GET['tahun_grafik']) : date('Y');
$mode_grafik = 'tahun'; // Selalu mode per tahun

// Query untuk data penjualan per produk (per bulan dalam tahun tertentu atau 12 bulan terakhir)
$data_produk_per_bulan = [];
$produk_colors = [
    'rgba(255, 99, 132, 0.8)',
    'rgba(54, 162, 235, 0.8)',
    'rgba(255, 206, 86, 0.8)',
    'rgba(75, 192, 192, 0.8)',
    'rgba(153, 102, 255, 0.8)',
    'rgba(255, 159, 64, 0.8)',
    'rgba(199, 199, 199, 0.8)',
    'rgba(83, 102, 255, 0.8)',
    'rgba(255, 99, 255, 0.8)',
    'rgba(99, 255, 132, 0.8)',
    'rgba(255, 132, 99, 0.8)',
    'rgba(132, 99, 255, 0.8)'
];

$produk_border_colors = [
    'rgba(255, 99, 132, 1)',
    'rgba(54, 162, 235, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(153, 102, 255, 1)',
    'rgba(255, 159, 64, 1)',
    'rgba(199, 199, 199, 1)',
    'rgba(83, 102, 255, 1)',
    'rgba(255, 99, 255, 1)',
    'rgba(99, 255, 132, 1)',
    'rgba(255, 132, 99, 1)',
    'rgba(132, 99, 255, 1)'
];

// Ambil semua produk yang pernah terjual
$produk_terjual = query("SELECT DISTINCT p.id_produk, p.nama_produk 
                          FROM produk p 
                          JOIN detail_penjualan dp ON p.id_produk = dp.id_produk 
                          JOIN penjualan pj ON dp.id_penjualan = pj.id_penjualan
                          WHERE pj.status_pembayaran != 'batal'
                          ORDER BY p.nama_produk");

// Generate labels dan data - selalu mode per tahun
$labels_detail = [];
$nama_bulan_indo = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

for ($m = 1; $m <= 12; $m++) {
    $labels_detail[] = $nama_bulan_indo[$m - 1] . ' ' . $tahun_grafik;
}

// Data per produk per bulan
foreach ($produk_terjual as $index => $produk) {
    $data_bulanan = [];
    for ($m = 1; $m <= 12; $m++) {
        $bulan_str = sprintf('%04d-%02d', $tahun_grafik, $m);
        $query_produk = "SELECT COALESCE(SUM(dp.subtotal), 0) as total, COALESCE(SUM(dp.jumlah), 0) as qty
                          FROM detail_penjualan dp
                          JOIN penjualan pj ON dp.id_penjualan = pj.id_penjualan
                          WHERE dp.id_produk = {$produk['id_produk']}
                          AND DATE_FORMAT(pj.tanggal_penjualan, '%Y-%m') = '$bulan_str'
                          AND pj.status_pembayaran != 'batal'";
        $result = query($query_produk);
        $data_bulanan[] = [
            'total' => $result[0]['total'] ?? 0,
            'qty' => $result[0]['qty'] ?? 0
        ];
    }
    $data_produk_per_bulan[] = [
        'nama' => $produk['nama_produk'],
        'data' => array_column($data_bulanan, 'total'),
        'qty' => array_column($data_bulanan, 'qty'),
        'color' => $produk_colors[$index % count($produk_colors)],
        'borderColor' => $produk_border_colors[$index % count($produk_border_colors)]
    ];
}

// Data ringkasan produk terlaris
$query_top_produk = "SELECT p.nama_produk, SUM(dp.jumlah) as total_qty, SUM(dp.subtotal) as total_nilai
                      FROM detail_penjualan dp
                      JOIN produk p ON dp.id_produk = p.id_produk
                      JOIN penjualan pj ON dp.id_penjualan = pj.id_penjualan
                      WHERE pj.status_pembayaran != 'batal'";

if ($mode_grafik == 'tahun') {
    $query_top_produk .= " AND YEAR(pj.tanggal_penjualan) = $tahun_grafik";
} else {
    $query_top_produk .= " AND pj.tanggal_penjualan >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
}

$query_top_produk .= " GROUP BY p.id_produk, p.nama_produk ORDER BY total_qty DESC LIMIT 10";
$top_produk = query($query_top_produk);

// Hitung summary
$query_summary = "SELECT 
                    COUNT(DISTINCT pj.id_penjualan) as total_transaksi,
                   
                    COALESCE(SUM(dp.jumlah), 0) as total_jumlah,
                    COALESCE(SUM(dp.subtotal), 0) as total_nilai
                  FROM detail_penjualan dp
                  JOIN penjualan pj ON dp.id_penjualan = pj.id_penjualan
                  WHERE pj.status_pembayaran != 'batal'";

if ($mode_grafik == 'tahun') {
    $query_summary .= " AND YEAR(pj.tanggal_penjualan) = $tahun_grafik";
} else {
    $query_summary .= " AND pj.tanggal_penjualan >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
}

$summary = query($query_summary);
$summary_data = $summary[0] ?? [
    'total_transaksi' => 0,
    'total_item' => 0,
    'total_jumlah' => 0,
    'total_nilai' => 0
];

// JSON encode untuk JavaScript
$labels_detail_json = json_encode($labels_detail);
$data_produk_json = json_encode($data_produk_per_bulan);
$top_produk_json = json_encode($top_produk);
?>

<style>
    .chart-container {
        position: relative;
        margin: auto;
    }

    .card-header.bg-info,
    .card-header.bg-primary,
    .card-header.bg-success,
    .card-header.bg-danger,
    .card-header.bg-dark,
    .card-header.bg-secondary {
        border-bottom: 0;
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

    <?php include_once '../includes/navbar.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <!-- [ Main Content ] start -->
            <div class="row">
                <div class="col-12">

                    <!-- Filter dan Summary dalam satu baris -->
                    <div class="row mb-2 align-items-center">


                        <!-- Summary Cards -->
                        <div class="col-lg-10 col-md-9">
                            <div class="row g-2">
                                <div class="col-md-4 col-4">
                                    <div class="card text-center shadow-sm border-0 bg-light">
                                        <div class="card-body py-1 px-2">
                                            <h6 class="fw-bold mb-0"><?= number_format($summary_data['total_transaksi'], 0, ',', '.') ?></h6>
                                            <small class="text-muted" style="font-size: 0.7rem;">Total Transaksi</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-4">
                                    <div class="card text-center shadow-sm border-0 bg-light">
                                        <div class="card-body py-1 px-2">
                                            <h6 class="fw-bold mb-0"><?= number_format($summary_data['total_jumlah'], 0, ',', '.') ?></h6>
                                            <small class="text-muted" style="font-size: 0.7rem;">Total Jumlah</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-4">
                                    <div class="card text-center shadow-sm border-0 bg-light">
                                        <div class="card-body py-1 px-2">
                                            <h6 class="fw-bold mb-0 text-primary"><?= formatRupiah($summary_data['total_nilai']) ?></h6>
                                            <small class="text-muted" style="font-size: 0.7rem;">Total Nilai</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Filter Periode Grafik -->
                        <div class="col-lg-2 col-md-3 mb-2 mb-md-0">
                            <form method="GET" id="grafikFilterForm" class="d-flex gap-1">
                                <select class="form-select form-select-sm" name="tahun_grafik" id="tahunGrafik" style="width: auto;">
                                    <?php foreach ($tahun_tersedia as $t): ?>
                                        <option value="<?= $t['tahun'] ?>" <?= $tahun_grafik == $t['tahun'] ? 'selected' : '' ?>>
                                            <?= $t['tahun'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (empty($tahun_tersedia)): ?>
                                        <option value="<?= date('Y') ?>"><?= date('Y') ?></option>
                                    <?php endif; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="ti ti-refresh"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Grafik Penjualan Total Per Bulan (hidden) -->
                    <div hidden class="card mb-4">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0 text-dark d-flex align-items-center">
                                <i class="ti ti-chart-bar me-2"></i>
                                Grafik Total Penjualan
                                <?= $mode_grafik == 'tahun' ? "Tahun $tahun_grafik" : '12 Bulan Terakhir' ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div style="position: relative; height: 400px;">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Grafik Penjualan Per Produk -->
                        <div class="col-lg-8">
                            <div class="card mb-3">
                                <div class="card-header bg-info text-white py-2">
                                    <h6 class="mb-0 d-flex align-items-center">
                                        <i class="ti ti-chart-line me-2"></i>
                                        Grafik Penjualan Per Produk (Nilai Rupiah)
                                    </h6>
                                </div>
                                <div class="card-body py-2">
                                    <div style="position: relative; height: 280px;">
                                        <canvas id="productSalesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Produk Terlaris -->
                        <div class="col-lg-4">
                            <div class="card mb-3">
                                <div class="card-header bg-success text-white py-2">
                                    <h6 class="mb-0 d-flex align-items-center">
                                        <i class="ti ti-trophy me-2"></i>
                                        Top 10 Produk Terlaris
                                    </h6>
                                </div>
                                <div class="card-body p-0" style="max-height: 320px; overflow-y: auto;">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0" style="font-size: 0.8rem;">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th width="10%">#</th>
                                                    <th>Produk</th>
                                                    <th class="text-end">Pcs</th>
                                                    <th class="text-end">Nilai</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($top_produk)): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted py-2">
                                                            Tidak ada data
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($top_produk as $i => $tp): ?>
                                                        <tr>
                                                            <td>
                                                                <?php if ($i == 0): ?>
                                                                    <span class="badge bg-warning text-dark">🥇</span>
                                                                <?php elseif ($i == 1): ?>
                                                                    <span class="badge bg-secondary">🥈</span>
                                                                <?php elseif ($i == 2): ?>
                                                                    <span class="badge bg-danger">🥉</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-light text-dark"><?= $i + 1 ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <small><?= htmlspecialchars($tp['nama_produk']) ?></small>
                                                            </td>
                                                            <td class="text-end">
                                                                <span class="badge bg-primary"><?= number_format($tp['total_qty'], 0, ',', '.') ?></span>
                                                            </td>
                                                            <td class="text-end">
                                                                <small class="text-success"><?= formatRupiah($tp['total_nilai']) ?></small>
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

                    <!-- Grafik Jumlah Terjual Per Produk -->
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white py-2">
                            <h6 class="mb-0 d-flex align-items-center">
                                <i class="ti ti-chart-dots me-2"></i>
                                Grafik Jumlah Terjual Per Produk (Pcs)
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            <div style="position: relative; height: 280px;">
                                <canvas id="productQtyChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Grafik Pie Distribusi Produk -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card mb-3">
                                <div class="card-header bg-light text-dark py-2">
                                    <h6 class="mb-0 d-flex align-items-center">
                                        <i class="ti ti-chart-pie me-2"></i>
                                        Distribusi Penjualan Per Produk (Nilai)
                                    </h6>
                                </div>
                                <div class="card-body py-2">
                                    <div style="position: relative; height: 200px;">
                                        <canvas id="productPieChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card mb-3">
                                <div class="card-header bg-light text-dark py-2">
                                    <h6 class="mb-0 d-flex align-items-center">
                                        <i class="ti ti-chart-donut me-2"></i>
                                        Distribusi Penjualan Per Produk (Pcs)
                                    </h6>
                                </div>
                                <div class="card-body py-2">
                                    <div style="position: relative; height: 200px;">
                                        <canvas id="productDonutChart"></canvas>
                                    </div>
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

    <?php include_once '../includes/footer.php'; ?>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        const chartLabels = <?= $chart_labels ?>;
        const chartData = <?= $chart_data ?>;
        const labelsDetail = <?= $labels_detail_json ?>;
        const dataProduk = <?= $data_produk_json ?>;
        const topProduk = <?= $top_produk_json ?>;
        // Inisialisasi semua chart saat halaman load
        document.addEventListener('DOMContentLoaded', function() {
            initSalesChart();
            initProductSalesChart();
            initProductQtyChart();
            initProductPieChart();
            initProductDonutChart();
        });

        function initSalesChart() {
            const ctx = document.getElementById('salesChart').getContext('2d');

            // Hitung total per bulan dari semua produk
            const displayLabels = labelsDetail;
            const displayData = labelsDetail.map((_, index) => {
                return dataProduk.reduce((sum, produk) => sum + parseFloat(produk.data[index] || 0), 0);
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: displayLabels,
                    datasets: [{
                        label: 'Total Penjualan',
                        data: displayData,
                        backgroundColor: 'rgba(255, 193, 7, 0.8)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                        hoverBackgroundColor: 'rgba(255, 193, 7, 1)',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 193, 7, 0.95)',
                            titleColor: '#000',
                            bodyColor: '#000',
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13
                            },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    let value = context.raw;
                                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            ticks: {
                                callback: function(value) {
                                    if (value >= 1000000) {
                                        return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
                                    } else if (value >= 1000) {
                                        return 'Rp ' + (value / 1000).toFixed(0) + ' Rb';
                                    }
                                    return 'Rp ' + value;
                                },
                                font: {
                                    size: 11
                                },
                                color: '#666'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                },
                                color: '#666'
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        function initProductSalesChart() {
            const ctx = document.getElementById('productSalesChart').getContext('2d');

            const datasets = dataProduk.map((produk, index) => ({
                label: produk.nama,
                data: produk.data,
                backgroundColor: produk.color,
                borderColor: produk.borderColor,
                borderWidth: 2,
                tension: 0.4,
                fill: false
            }));

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labelsDetail,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 15,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    if (value >= 1000000) {
                                        return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
                                    } else if (value >= 1000) {
                                        return 'Rp ' + (value / 1000).toFixed(0) + ' Rb';
                                    }
                                    return 'Rp ' + value;
                                }
                            }
                        }
                    }
                }
            });
        }

        function initProductQtyChart() {
            const ctx = document.getElementById('productQtyChart').getContext('2d');

            const datasets = dataProduk.map((produk, index) => ({
                label: produk.nama,
                data: produk.qty,
                backgroundColor: produk.color,
                borderColor: produk.borderColor,
                borderWidth: 1
            }));

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labelsDetail,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 15,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw + ' pcs';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: false
                        },
                        y: {
                            stacked: false,
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' pcs';
                                }
                            }
                        }
                    }
                }
            });
        }

        function initProductPieChart() {
            const ctx = document.getElementById('productPieChart').getContext('2d');

            // Hitung total nilai per produk
            const pieData = dataProduk.map(produk => ({
                label: produk.nama,
                value: produk.data.reduce((sum, val) => sum + parseFloat(val || 0), 0),
                color: produk.color,
                borderColor: produk.borderColor
            })).filter(item => item.value > 0);

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: pieData.map(d => d.label),
                    datasets: [{
                        data: pieData.map(d => d.value),
                        backgroundColor: pieData.map(d => d.color),
                        borderColor: pieData.map(d => d.borderColor),
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return context.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.raw) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        function initProductDonutChart() {
            const ctx = document.getElementById('productDonutChart').getContext('2d');

            // Hitung total qty per produk
            const donutData = dataProduk.map(produk => ({
                label: produk.nama,
                value: produk.qty.reduce((sum, val) => sum + parseInt(val || 0), 0),
                color: produk.color,
                borderColor: produk.borderColor
            })).filter(item => item.value > 0);

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: donutData.map(d => d.label),
                    datasets: [{
                        data: donutData.map(d => d.value),
                        backgroundColor: donutData.map(d => d.color),
                        borderColor: donutData.map(d => d.borderColor),
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '50%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.raw + ' pcs (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
<!-- [Body] end -->

</html>