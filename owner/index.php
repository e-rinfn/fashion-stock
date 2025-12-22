<?php
require_once './includes/header.php';

// Cek apakah ini request AJAX
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == 'true';

// Parameter filter
$nama_bahan = isset($_GET['nama_bahan']) ? trim($_GET['nama_bahan']) : '';
$jumlah_stok = isset($_GET['jumlah_stok']) ? $_GET['jumlah_stok'] : 'all';
$nama_koko = isset($_GET['nama_koko']) ? trim($_GET['nama_koko']) : '';
$stok_koko = isset($_GET['stok_koko']) ? $_GET['stok_koko'] : 'all';
$nama_produk = isset($_GET['nama_produk']) ? trim($_GET['nama_produk']) : '';
$stok_produk = isset($_GET['stok_produk']) ? $_GET['stok_produk'] : 'all';

// Filter bahan baku
function getBahanBaku($conn, $nama_bahan, $jumlah_stok)
{
    $sql = "SELECT * FROM bahan_baku WHERE 1=1";

    if (!empty($nama_bahan)) {
        $nama_bahan_escaped = $conn->real_escape_string($nama_bahan);
        $sql .= " AND nama_bahan LIKE '%$nama_bahan_escaped%'";
    }

    if ($jumlah_stok == 'tersedia') {
        $sql .= " AND jumlah_stok > 0";
    } elseif ($jumlah_stok == 'habis') {
        $sql .= " AND jumlah_stok = 0";
    }

    $sql .= " ORDER BY nama_bahan";
    return query($sql);
}

// Filter koko
function getKoko($conn, $nama_koko, $stok_koko)
{
    $sql = "SELECT * FROM koko WHERE 1=1";

    if (!empty($nama_koko)) {
        $nama_koko_escaped = $conn->real_escape_string($nama_koko);
        $sql .= " AND nama_koko LIKE '%$nama_koko_escaped%'";
    }

    if ($stok_koko == 'tersedia') {
        $sql .= " AND stok > 0";
    } elseif ($stok_koko == 'habis') {
        $sql .= " AND stok = 0";
    }

    $sql .= " ORDER BY nama_koko";
    return query($sql);
}

// Filter produk
function getProduk($conn, $nama_produk, $stok_produk)
{
    $sql = "SELECT * FROM produk WHERE 1=1";

    if (!empty($nama_produk)) {
        $nama_produk_escaped = $conn->real_escape_string($nama_produk);
        $sql .= " AND nama_produk LIKE '%$nama_produk_escaped%'";
    }

    if ($stok_produk == 'tersedia') {
        $sql .= " AND stok > 0";
    } elseif ($stok_produk == 'habis') {
        $sql .= " AND stok = 0";
    }

    $sql .= " ORDER BY nama_produk";
    return query($sql);
}

// Ambil data
$bahan = getBahanBaku($conn, $nama_bahan, $jumlah_stok);
$koko = getKoko($conn, $nama_koko, $stok_koko);
$produk = getProduk($conn, $nama_produk, $stok_produk);

// Jika request AJAX, kembalikan JSON
if ($isAjax) {
    header('Content-Type: application/json');

    $tab = $_GET['tab'] ?? '';
    $response = [];

    if ($tab == 'bahan') {
        $response = [
            'bahan' => $bahan,
            'count' => count($bahan)
        ];
    } elseif ($tab == 'koko') {
        $response = [
            'koko' => $koko,
            'count' => count($koko)
        ];
    } elseif ($tab == 'produk') {
        $response = [
            'produk' => $produk,
            'count' => count($produk)
        ];
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Stok</title>
    <style>
        /* Responsive styles */
        .mobile-table {
            display: none;
        }

        @media (max-width: 768px) {
            .desktop-table {
                display: none !important;
            }

            .mobile-table {
                display: block;
            }

            .mobile-card {
                margin-bottom: 1rem;
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
                padding: 1rem;
                background: white;
            }

            .mobile-card .row {
                margin-bottom: 0.5rem;
                padding-bottom: 0.5rem;
                border-bottom: 1px solid #f1f1f1;
            }

            .mobile-card .row:last-child {
                border-bottom: none;
            }

            .mobile-label {
                font-weight: 600;
                color: #495057;
                font-size: 0.875rem;
            }

            .mobile-value {
                font-size: 0.9rem;
            }

            .nav-tabs .nav-link {
                font-size: 0.9rem;
                padding: 0.5rem 0.75rem;
            }

            .form-control,
            .form-select {
                font-size: 0.9rem;
            }

            .btn {
                font-size: 0.9rem;
                padding: 0.375rem 0.75rem;
            }
        }

        .tab-content {
            padding-top: 1.5rem;
        }

        .stok-tersedia {
            color: #198754;
            font-weight: 600;
        }

        .stok-habis {
            color: #dc3545;
            font-weight: 600;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
    </style>
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
    <?php include_once './includes/sidebar.php'; ?>
    <!-- Sidebar End -->

    <?php include_once './includes/navbar.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h4 mb-0">Dashboard Stok</h1>
                    </div>

                    <!-- Notifikasi -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['success']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs mb-3" id="stokTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="bahan-tab" data-bs-toggle="tab" data-bs-target="#bahan" type="button" role="tab">
                                <i class="ti ti-package me-1"></i> Bahan
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="koko-tab" data-bs-toggle="tab" data-bs-target="#koko" type="button" role="tab">
                                <i class="ti ti-shirt me-1"></i> Koko Finishing
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="produk-tab" data-bs-toggle="tab" data-bs-target="#produk" type="button" role="tab">
                                <i class="ti ti-box me-1"></i> Produk
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="stokTabContent">
                        <!-- Tab Bahan Baku -->
                        <div class="tab-pane fade show active" id="bahan" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h5 mb-0">Stok Bahan Baku</h2>
                                <span class="badge bg-primary bahan-count"><?= count($bahan) ?> item</span>
                            </div>

                            <!-- Filter Bahan Baku -->
                            <div class="filter-section">
                                <form class="filter-form" data-tab="bahan">
                                    <div class="row g-2">
                                        <div class="col-md-6 col-12">
                                            <input type="text" name="nama_bahan" class="form-control form-control-sm"
                                                placeholder="Cari Nama Bahan" value="<?= htmlspecialchars($nama_bahan) ?>">
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <select name="jumlah_stok" class="form-select form-select-sm">
                                                <option value="all" <?= $jumlah_stok == 'all' ? 'selected' : '' ?>>Semua Stok</option>
                                                <option value="tersedia" <?= $jumlah_stok == 'tersedia' ? 'selected' : '' ?>>Stok Tersedia</option>
                                                <option value="habis" <?= $jumlah_stok == 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <button type="button" class="btn btn-primary btn-sm w-100 filter-btn">
                                                <i class="ti ti-filter"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Desktop View -->
                            <div class="desktop-table">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width: 5%;">No</th>
                                                <th style="width: 35%;">Nama Bahan</th>
                                                <th colspan="2" style="width: 20%;">Stok</th>
                                                <th style="width: 15%;">Total (Meter)</th>
                                                <th style="width: 25%;">Harga Per Meter</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bahan-tbody">
                                            <?php $no = 1;
                                            foreach ($bahan as $b): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($b['nama_bahan']) ?></td>
                                                    <td class="text-end"><?= $b['jumlah_stok'] ?></td>
                                                    <td class="text-start"><?= $b['satuan'] ?></td>
                                                    <td class="text-end"><?= $b['jumlah_meter'] ?></td>
                                                    <td class="text-end"><?= formatRupiah($b['harga_per_satuan']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($bahan)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center no-data">Tidak ada data bahan baku</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Mobile View -->
                            <div class="mobile-table">
                                <div id="bahan-mobile">
                                    <?php $no = 1;
                                    foreach ($bahan as $b): ?>
                                        <div class="mobile-card">
                                            <div class="row">
                                                <div class="col-5 mobile-label">No</div>
                                                <div class="col-7 mobile-value"><?= $no++ ?></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-5 mobile-label">Nama Bahan</div>
                                                <div class="col-7 mobile-value"><?= htmlspecialchars($b['nama_bahan']) ?></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-5 mobile-label">Stok</div>
                                                <div class="col-7 mobile-value">
                                                    <?= $b['jumlah_stok'] ?> <?= $b['satuan'] ?>
                                                    <?php if ($b['jumlah_stok'] > 0): ?>
                                                        <span class="stok-tersedia">• Tersedia</span>
                                                    <?php else: ?>
                                                        <span class="stok-habis">• Habis</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-5 mobile-label">Total Meter</div>
                                                <div class="col-7 mobile-value"><?= $b['jumlah_meter'] ?> m</div>
                                            </div>
                                            <div class="row">
                                                <div class="col-5 mobile-label">Harga/Meter</div>
                                                <div class="col-7 mobile-value"><?= formatRupiah($b['harga_per_satuan']) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($bahan)): ?>
                                        <div class="alert alert-info text-center">
                                            Tidak ada data bahan baku
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Koko -->
                        <div class="tab-pane fade" id="koko" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h5 mb-0">Stok Finishing Koko</h2>
                                <span class="badge bg-primary koko-count"><?= count($koko) ?> item</span>
                            </div>

                            <!-- Filter Koko -->
                            <div class="filter-section">
                                <form class="filter-form" data-tab="koko">
                                    <div class="row g-2">
                                        <div class="col-md-6 col-12">
                                            <input type="text" name="nama_koko" class="form-control form-control-sm"
                                                placeholder="Cari Nama Koko" value="<?= htmlspecialchars($nama_koko) ?>">
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <select name="stok_koko" class="form-select form-select-sm">
                                                <option value="all" <?= $stok_koko == 'all' ? 'selected' : '' ?>>Semua Stok</option>
                                                <option value="tersedia" <?= $stok_koko == 'tersedia' ? 'selected' : '' ?>>Stok Tersedia</option>
                                                <option value="habis" <?= $stok_koko == 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <button type="button" class="btn btn-primary btn-sm w-100 filter-btn">
                                                <i class="ti ti-filter"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Desktop View -->
                            <div class="desktop-table">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width: 5%;">No</th>
                                                <th style="width: 60%;">Nama Koko</th>
                                                <th style="width: 35%;">Stok (Pcs)</th>
                                            </tr>
                                        </thead>
                                        <tbody id="koko-tbody">
                                            <?php $no = 1;
                                            foreach ($koko as $k): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($k['nama_koko']) ?></td>
                                                    <td class="text-end <?= $k['stok'] > 0 ? 'stok-tersedia' : 'stok-habis' ?>">
                                                        <?= $k['stok'] ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($koko)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center no-data">Tidak ada data koko</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Mobile View -->
                            <div class="mobile-table">
                                <div id="koko-mobile">
                                    <?php $no = 1;
                                    foreach ($koko as $k): ?>
                                        <div class="mobile-card">
                                            <div class="row">
                                                <div class="col-5 mobile-label">No</div>
                                                <div class="col-7 mobile-value"><?= $no++ ?></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-5 mobile-label">Nama Koko</div>
                                                <div class="col-7 mobile-value"><?= htmlspecialchars($k['nama_koko']) ?></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-5 mobile-label">Stok</div>
                                                <div class="col-7 mobile-value <?= $k['stok'] > 0 ? 'stok-tersedia' : 'stok-habis' ?>">
                                                    <?= $k['stok'] ?> pcs
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($koko)): ?>
                                        <div class="alert alert-info text-center">
                                            Tidak ada data koko
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Produk -->
                        <div class="tab-pane fade" id="produk" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h5 mb-0">Stok Produk</h2>
                                <span class="badge bg-primary produk-count"><?= count($produk) ?> item</span>
                            </div>

                            <!-- Filter Produk -->
                            <div class="filter-section">
                                <form class="filter-form" data-tab="produk">
                                    <div class="row g-2">
                                        <div class="col-md-6 col-12">
                                            <input type="text" name="nama_produk" class="form-control form-control-sm"
                                                placeholder="Cari Nama Produk" value="<?= htmlspecialchars($nama_produk) ?>">
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <select name="stok_produk" class="form-select form-select-sm">
                                                <option value="all" <?= $stok_produk == 'all' ? 'selected' : '' ?>>Semua Stok</option>
                                                <option value="tersedia" <?= $stok_produk == 'tersedia' ? 'selected' : '' ?>>Stok Tersedia</option>
                                                <option value="habis" <?= $stok_produk == 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <button type="button" class="btn btn-primary btn-sm w-100 filter-btn">
                                                <i class="ti ti-filter"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Desktop View -->
                            <div class="desktop-table">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width: 5%;">No</th>
                                                <th style="width: 35%;">Nama Produk</th>
                                                <th style="width: 20%;">Tipe Produk</th>
                                                <th style="width: 20%;">Stok (Pcs)</th>
                                                <th style="width: 20%;">Harga Per Pcs</th>
                                            </tr>
                                        </thead>
                                        <tbody id="produk-tbody">
                                            <?php $no = 1;
                                            foreach ($produk as $p): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($p['nama_produk']) ?></td>
                                                    <td class="text-start"><?= htmlspecialchars(ucfirst($p['tipe_produk'])) ?></td>
                                                    <td class="text-end <?= $p['stok'] > 0 ? 'stok-tersedia' : 'stok-habis' ?>">
                                                        <?= $p['stok'] ?>
                                                    </td>
                                                    <td class="text-end"><?= formatRupiah($p['harga_jual'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($produk)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center no-data">Tidak ada data produk</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Mobile View -->
                            <div class="mobile-table">
                                <div id="produk-mobile">
                                    <?php $no = 1;
                                    foreach ($produk as $p): ?>
                                        <div class="mobile-card">
                                            <div class="row">
                                                <div class="col-5 mobile-label">No</div>
                                                <div class="col-7 mobile-value"><?= $no++ ?></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-5 mobile-label">Nama Produk</div>
                                                <div class="col-7 mobile-value"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-5 mobile-label">Tipe Produk</div>
                                                <div class="col-7 mobile-value"><?= htmlspecialchars(ucfirst($p['tipe_produk'])) ?></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-5 mobile-label">Stok</div>
                                                <div class="col-7 mobile-value <?= $p['stok'] > 0 ? 'stok-tersedia' : 'stok-habis' ?>">
                                                    <?= $p['stok'] ?> pcs
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-5 mobile-label">Harga</div>
                                                <div class="col-7 mobile-value"><?= formatRupiah($p['harga_jual'] ?? 0) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($produk)): ?>
                                        <div class="alert alert-info text-center">
                                            Tidak ada data produk
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once './includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Ganti kode JavaScript dengan ini:
        $(document).ready(function() {
            // Cegah submit form default
            $('.filter-form').on('submit', function(e) {
                e.preventDefault();

                const tab = $(this).data('tab');
                const formData = $(this).serialize();
                const currentUrl = window.location.pathname;

                // Simpan tab aktif di localStorage
                localStorage.setItem('activeTab', tab);

                // Redirect dengan parameter filter
                window.location.href = currentUrl + '?' + formData;
            });

            // Setel tab aktif dari localStorage
            const activeTab = localStorage.getItem('activeTab');
            if (activeTab) {
                const tabElement = $('#' + activeTab + '-tab');
                if (tabElement.length) {
                    new bootstrap.Tab(tabElement).show();
                    localStorage.removeItem('activeTab');
                }
            }

            // Trigger click pada filter button
            $('.filter-btn').click(function() {
                $(this).closest('form').submit();
            });
        }); // Fungsi untuk render tabel desktop bahan
        function renderDesktopBahan(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="6" class="text-center no-data">Tidak ada data bahan baku</td></tr>';
            } else {
                data.forEach((item, index) => {
                    html += `
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td>${escapeHtml(item.nama_bahan)}</td>
                        <td class="text-end">${item.jumlah_stok}</td>
                        <td class="text-start">${item.satuan}</td>
                        <td class="text-end">${item.jumlah_meter}</td>
                        <td class="text-end">${formatRupiah(item.harga_per_satuan)}</td>
                    </tr>`;
                });
            }
            return html;
        }

        // Fungsi untuk render mobile bahan
        function renderMobileBahan(data) {
            let html = '';
            if (data.length === 0) {
                html = '<div class="alert alert-info text-center">Tidak ada data bahan baku</div>';
            } else {
                data.forEach((item, index) => {
                    const stokClass = item.jumlah_stok > 0 ? 'stok-tersedia' : 'stok-habis';
                    const stokText = item.jumlah_stok > 0 ? '• Tersedia' : '• Habis';

                    html += `
                    <div class="mobile-card">
                        <div class="row">
                            <div class="col-5 mobile-label">No</div>
                            <div class="col-7 mobile-value">${index + 1}</div>
                        </div>
                        <div class="row">
                            <div class="col-5 mobile-label">Nama Bahan</div>
                            <div class="col-7 mobile-value">${escapeHtml(item.nama_bahan)}</div>
                        </div>
                        <div class="row">
                            <div class="col-5 mobile-label">Stok</div>
                            <div class="col-7 mobile-value">
                                ${item.jumlah_stok} ${item.satuan}
                                <span class="${stokClass}">${stokText}</span>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-5 mobile-label">Total Meter</div>
                            <div class="col-7 mobile-value">${item.jumlah_meter} m</div>
                        </div>
                        <div class="row">
                            <div class="col-5 mobile-label">Harga/Meter</div>
                            <div class="col-7 mobile-value">${formatRupiah(item.harga_per_satuan)}</div>
                        </div>
                    </div>`;
                });
            }
            return html;
        }

        // Fungsi untuk render koko
        function renderDesktopKoko(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="3" class="text-center no-data">Tidak ada data koko</td></tr>';
            } else {
                data.forEach((item, index) => {
                    const stokClass = item.stok > 0 ? 'stok-tersedia' : 'stok-habis';
                    html += `
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td>${escapeHtml(item.nama_koko)}</td>
                        <td class="text-end ${stokClass}">${item.stok}</td>
                    </tr>`;
                });
            }
            return html;
        }

        function renderMobileKoko(data) {
            let html = '';
            if (data.length === 0) {
                html = '<div class="alert alert-info text-center">Tidak ada data koko</div>';
            } else {
                data.forEach((item, index) => {
                    const stokClass = item.stok > 0 ? 'stok-tersedia' : 'stok-habis';
                    html += `
                    <div class="mobile-card">
                        <div class="row">
                            <div class="col-5 mobile-label">No</div>
                            <div class="col-7 mobile-value">${index + 1}</div>
                        </div>
                        <div class="row">
                            <div class="col-5 mobile-label">Nama Koko</div>
                            <div class="col-7 mobile-value">${escapeHtml(item.nama_koko)}</div>
                        </div>
                        <div class="row">
                            <div class="col-5 mobile-label">Stok</div>
                            <div class="col-7 mobile-value ${stokClass}">
                                ${item.stok} pcs
                            </div>
                        </div>
                    </div>`;
                });
            }
            return html;
        }

        // Fungsi untuk render produk
        function renderDesktopProduk(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="5" class="text-center no-data">Tidak ada data produk</td></tr>';
            } else {
                data.forEach((item, index) => {
                    const stokClass = item.stok > 0 ? 'stok-tersedia' : 'stok-habis';
                    html += `
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td>${escapeHtml(item.nama_produk)}</td>
                        <td class="text-start">${escapeHtml(item.tipe_produk)}</td>
                        <td class="text-end ${stokClass}">${item.stok}</td>
                        <td class="text-end">${formatRupiah(item.harga_jual || 0)}</td>
                    </tr>`;
                });
            }
            return html;
        }

        function renderMobileProduk(data) {
            let html = '';
            if (data.length === 0) {
                html = '<div class="alert alert-info text-center">Tidak ada data produk</div>';
            } else {
                data.forEach((item, index) => {
                    const stokClass = item.stok > 0 ? 'stok-tersedia' : 'stok-habis';
                    html += `
                    <div class="mobile-card">
                        <div class="row">
                            <div class="col-5 mobile-label">No</div>
                            <div class="col-7 mobile-value">${index + 1}</div>
                        </div>
                        <div class="row">
                            <div class="col-5 mobile-label">Nama Produk</div>
                            <div class="col-7 mobile-value">${escapeHtml(item.nama_produk)}</div>
                        </div>
                        <div class="row">
                            <div class="col-5 mobile-label">Tipe Produk</div>
                            <div class="col-7 mobile-value">${escapeHtml(item.tipe_produk)}</div>
                        </div>
                        <div class="row">
                            <div class="col-5 mobile-label">Stok</div>
                            <div class="col-7 mobile-value ${stokClass}">
                                ${item.stok} pcs
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-5 mobile-label">Harga</div>
                            <div class="col-7 mobile-value">${formatRupiah(item.harga_jual || 0)}</div>
                        </div>
                    </div>`;
                });
            }
            return html;
        }

        // Helper functions
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function formatRupiah(angka) {
            if (!angka) return 'Rp 0';
            return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
        }

        // AJAX Filter
        $('.filter-btn').click(function() {
            const form = $(this).closest('.filter-form');
            const tab = form.data('tab');
            const formData = form.serialize();

            // Tampilkan loading
            const loadingHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';

            if (tab === 'bahan') {
                $('#bahan-tbody').html(loadingHTML);
                $('#bahan-mobile').html(loadingHTML);

                $.ajax({
                    url: window.location.pathname + '?ajax=true&tab=bahan&' + formData,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        $('#bahan-tbody').html(renderDesktopBahan(response.bahan));
                        $('#bahan-mobile').html(renderMobileBahan(response.bahan));
                        $('.bahan-count').text(response.count + ' item');
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        $('#bahan-tbody').html('<tr><td colspan="6" class="text-center text-danger">Gagal memuat data</td></tr>');
                        $('#bahan-mobile').html('<div class="alert alert-danger text-center">Gagal memuat data</div>');
                    }
                });
            } else if (tab === 'koko') {
                $('#koko-tbody').html(loadingHTML);
                $('#koko-mobile').html(loadingHTML);

                $.ajax({
                    url: window.location.pathname + '?ajax=true&tab=koko&' + formData,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        $('#koko-tbody').html(renderDesktopKoko(response.koko));
                        $('#koko-mobile').html(renderMobileKoko(response.koko));
                        $('.koko-count').text(response.count + ' item');
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        $('#koko-tbody').html('<tr><td colspan="3" class="text-center text-danger">Gagal memuat data</td></tr>');
                        $('#koko-mobile').html('<div class="alert alert-danger text-center">Gagal memuat data</div>');
                    }
                });
            } else if (tab === 'produk') {
                $('#produk-tbody').html(loadingHTML);
                $('#produk-mobile').html(loadingHTML);

                $.ajax({
                    url: window.location.pathname + '?ajax=true&tab=produk&' + formData,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        $('#produk-tbody').html(renderDesktopProduk(response.produk));
                        $('#produk-mobile').html(renderMobileProduk(response.produk));
                        $('.produk-count').text(response.count + ' item');
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        $('#produk-tbody').html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat data</td></tr>');
                        $('#produk-mobile').html('<div class="alert alert-danger text-center">Gagal memuat data</div>');
                    }
                });
            }
        });

        // Submit form dengan Enter
        $('.filter-form input').keypress(function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).closest('.filter-form').find('.filter-btn').click();
            }
        });
    </script>
</body>

</html>