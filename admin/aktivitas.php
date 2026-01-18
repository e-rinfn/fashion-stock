<?php

$page_title = "AKTIVITAS";

require_once '../config/functions.php';
require_once './includes/header.php';

// Filter parameters
$filter_types = isset($_GET['type']) ? (array)$_GET['type'] : [];
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : date('Y-m-01');
$filter_date_end   = isset($_GET['date_end'])   ? $_GET['date_end']   : date('Y-m-d');
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Daftar semua jenis aktivitas
$activity_types = [
    'pembelian' => 'Pembelian Produk',
    'penjualan' => 'Penjualan Produk',
    'pembelian_bahan' => 'Pembelian Bahan',
    'penjualan_bahan' => 'Penjualan Bahan',
    'produksi' => 'Hasil Penjahitan',
    'hasil_jahit_mukena' => 'Hasil Jahit Mukena',
    'hasil_finishing_koko' => 'Hasil Finishing Koko',
    'pembayaran_upah' => 'Pembayaran Upah'
];

$base_sql = "
    (SELECT 'pembelian' as type, 'Pembelian Produk' as kategori, tanggal_pembelian as waktu,
        CONCAT('Pembelian ke: ', nama_supplier) as aktivitas,
        CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail,
        'ti ti-shopping-cart' as icon,
        'bg-primary' as badge_color,
        p.id_pembelian as id_ref
     FROM pembelian p
     JOIN supplier r ON p.id_supplier = r.id_supplier)

    UNION ALL
    (SELECT 'penjualan' as type, 'Penjualan Produk' as kategori, tanggal_penjualan as waktu,
        CONCAT('Penjualan ke: ', nama_reseller) as aktivitas,
        CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail,
        'ti ti-cash' as icon,
        'bg-success' as badge_color,
        p.id_penjualan as id_ref
     FROM penjualan p
     JOIN reseller r ON p.id_reseller = r.id_reseller)

    UNION ALL
    (SELECT 'produksi' as type, 'Hasil Penjahitan' as kategori, created_at as waktu,
        'Hasil Penjahitan' as aktivitas,
        CONCAT(jumlah_produk_jadi, ' pcs produk jadi') as detail,
        'ti ti-needle' as icon,
        'bg-info' as badge_color,
        id_hasil_jahit as id_ref
     FROM hasil_penjahitan)

    UNION ALL
    (SELECT 'pembelian_bahan' as type, 'Pembelian Bahan' as kategori, tanggal_pembelian as waktu,
        CONCAT('Pembelian Bahan dari: ', nama_supplier) as aktivitas,
        CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail,
        'ti ti-package' as icon,
        'bg-warning' as badge_color,
        pb.id_pembelian_bahan as id_ref
     FROM pembelian_bahan pb
     JOIN supplier s ON pb.id_supplier = s.id_supplier)

    UNION ALL
    (SELECT 'hasil_jahit_mukena' as type, 'Hasil Jahit Mukena' as kategori, hpf.tanggal_hasil_jahit as waktu,
        CONCAT('Hasil Jahit: ', p.nama_produk) as aktivitas,
        CONCAT(hpf.total_hasil_jahit, ' pcs selesai dari ', hpf.total_hasil, ' pcs potong (Seri: ', hpf.seri, ')') as detail,
        'ti ti-shirt' as icon,
        'bg-secondary' as badge_color,
        hpf.id_hasil_potong_fix as id_ref
     FROM hasil_potong_fix hpf
     JOIN produk p ON hpf.id_produk = p.id_produk
     WHERE p.tipe_produk = 'mukena'
       AND hpf.tanggal_hasil_jahit IS NOT NULL
       AND hpf.total_hasil_jahit IS NOT NULL)

    UNION ALL
    (SELECT 'penjualan_bahan' as type, 'Penjualan Bahan' as kategori, tanggal_penjualan_bahan as waktu,
        CONCAT('Penjualan Bahan ke: ', nama_reseller) as aktivitas,
        CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail,
        'ti ti-truck-delivery' as icon,
        'bg-danger' as badge_color,
        pb.id_penjualan_bahan as id_ref
     FROM penjualan_bahan pb
     JOIN reseller r ON pb.id_reseller = r.id_reseller)

    UNION ALL
    (SELECT 'hasil_finishing_koko' as type, 'Hasil Finishing Koko' as kategori, dhfk.tanggal_finishing as waktu,
        CONCAT('Finishing: ', COALESCE(p.nama_produk, 'Produk')) as aktivitas,
        CONCAT(dhfk.jumlah_selesai, ' pcs selesai, ', dhfk.jumlah_rusak, ' pcs kembali') as detail,
        'ti ti-shirt' as icon,
        'bg-dark' as badge_color,
        dhfk.id_detail_hasil_finishing_koko as id_ref
     FROM detail_hasil_finishing_koko dhfk
     JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
     LEFT JOIN produk p ON dh.id_produk = p.id_produk
     WHERE dhfk.tanggal_finishing IS NOT NULL)

    UNION ALL
    (SELECT 'pembayaran_upah' as type, 'Pembayaran Upah' as kategori, tanggal_bayar as waktu,
        'Pembayaran Upah' as aktivitas,
        CONCAT('Rp.', FORMAT(jumlah_bayar, 0), ' - ', metode_bayar) as detail,
        'ti ti-wallet' as icon,
        'bg-success' as badge_color,
        id_pembayaran as id_ref
     FROM pembayaran_upah_2)
";

// Terapkan filter tanggal di luar UNION menggunakan alias waktu
$start_datetime = $filter_date_start . ' 00:00:00';
$end_datetime   = $filter_date_end . ' 23:59:59';
$sql = "SELECT * FROM ($base_sql) AS all_activities
        WHERE waktu BETWEEN '$start_datetime' AND '$end_datetime'";

// Filter tipe (multiple)
if (!empty($filter_types)) {
    $types_escaped = array_map(function ($t) {
        return "'" . addslashes($t) . "'";
    }, $filter_types);
    $sql .= " AND type IN (" . implode(',', $types_escaped) . ")";
}

// Filter pencarian
if (!empty($search)) {
    $search_safe = addslashes($search);
    $sql .= " AND (aktivitas LIKE '%$search_safe%' OR detail LIKE '%$search_safe%')";
}

// Urutkan terbaru
$sql .= " ORDER BY waktu DESC";

$activities = query($sql);
// Hitung statistik
$total_aktivitas = count($activities);
$aktivitas_hari_ini = 0;
$today = date('Y-m-d');
foreach ($activities as $act) {
    if (date('Y-m-d', strtotime($act['waktu'])) === $today) {
        $aktivitas_hari_ini++;
    }
}
?>

<style>
    .activity-item {
        transition: all 0.2s ease;
        border-left: 4px solid transparent;
    }

    .activity-item:hover {
        background-color: #f8f9fa;
        border-left-color: #0d6efd;
    }

    .activity-icon {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .activity-icon-sm {
        width: 32px;
        height: 32px;
        min-width: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 0.9rem;
    }

    .filter-section {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .stat-card {
        border-radius: 10px;
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .activity-time {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .badge-type {
        font-size: 0.7rem;
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
    <?php include_once './includes/sidebar.php'; ?>
    <!-- Sidebar End -->

    <?php include_once './includes/navbar.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">

            <!-- Statistik Cards -->
            <div class="row mb-3">
                <div class="col-md-3 col-6 mb-2">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center">
                                <div class="activity-icon-sm bg-primary text-white me-2">
                                    <i class="ti ti-list-check"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0"><?= $total_aktivitas ?></h5>
                                    <small class="text-muted" style="font-size: 0.75rem;">Total Aktivitas</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center">
                                <div class="activity-icon-sm bg-success text-white me-2">
                                    <i class="ti ti-calendar-event"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0"><?= $aktivitas_hari_ini ?></h5>
                                    <small class="text-muted" style="font-size: 0.75rem;">Hari Ini</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center">
                                <div class="activity-icon-sm bg-info text-white me-2">
                                    <i class="ti ti-calendar-stats"></i>
                                </div>
                                <div>
                                    <small class="mb-0 fw-semibold"><?= dateIndo($filter_date_start) ?></small><br>
                                    <small class="text-muted" style="font-size: 0.7rem;">Dari Tanggal</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center">
                                <div class="activity-icon-sm bg-warning text-white me-2">
                                    <i class="ti ti-calendar-due"></i>
                                </div>
                                <div>
                                    <small class="mb-0 fw-semibold"><?= dateIndo($filter_date_end) ?></small><br>
                                    <small class="text-muted" style="font-size: 0.7rem;">Sampai Tanggal</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Jenis Aktivitas</label>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start btn-sm" type="button" id="typeDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                <?php
                                if (empty($filter_types)) {
                                    echo 'Semua Jenis';
                                } elseif (count($filter_types) == 1) {
                                    echo $activity_types[$filter_types[0]] ?? $filter_types[0];
                                } else {
                                    echo count($filter_types) . ' jenis dipilih';
                                }
                                ?>
                            </button>
                            <div class="dropdown-menu p-3" style="min-width: 250px;">
                                <div class="mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="selectAllTypes()">Pilih Semua</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllTypes()">Hapus Semua</button>
                                </div>
                                <hr class="my-2">
                                <?php foreach ($activity_types as $type_key => $type_label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input type-checkbox" type="checkbox" name="type[]" value="<?= $type_key ?>" id="type_<?= $type_key ?>" <?= in_array($type_key, $filter_types) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="type_<?= $type_key ?>"><?= $type_label ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small">Dari Tanggal</label>
                        <input type="date" name="date_start" class="form-control form-control-sm" value="<?= $filter_date_start ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small">Sampai Tanggal</label>
                        <input type="date" name="date_end" class="form-control form-control-sm" value="<?= $filter_date_end ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Cari</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari aktivitas..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="ti ti-filter"></i>
                            </button>
                            <a href="aktivitas.php" class="btn btn-secondary btn-sm">
                                <i class="ti ti-refresh"></i>
                            </a>
                            <a href="aktivitas_pdf.php?<?= !empty($filter_types) ? http_build_query(['type' => $filter_types]) . '&' : '' ?>date_start=<?= $filter_date_start ?>&date_end=<?= $filter_date_end ?>&search=<?= urlencode($search) ?>"
                                class="btn btn-danger btn-sm" target="_blank">
                                <i class="ti ti-printer"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Activity List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-activity me-2"></i>Daftar Aktivitas</h5>
                    <span class="badge bg-primary"><?= $total_aktivitas ?> aktivitas</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($activities)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="ti ti-mood-empty fs-1"></i>
                            <p class="mt-3">Tidak ada aktivitas ditemukan</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
                            <?php
                            $current_date = '';
                            foreach ($activities as $activity):
                                $activity_date = date('Y-m-d', strtotime($activity['waktu']));

                                // Tampilkan header tanggal jika berbeda
                                if ($current_date !== $activity_date):
                                    $current_date = $activity_date;
                            ?>
                                    <div class="list-group-item bg-light py-2">
                                        <strong>
                                            <i class="ti ti-calendar me-2"></i>
                                            <?= dateIndo($current_date) ?>
                                            <?php if ($current_date === $today): ?>
                                                <span class="badge bg-success ms-2">Hari Ini</span>
                                            <?php endif; ?>
                                        </strong>
                                    </div>
                                <?php endif; ?>

                                <div class="list-group-item activity-item">
                                    <div class="d-flex align-items-start">
                                        <div class="activity-icon <?= $activity['badge_color'] ?> text-white me-3 flex-shrink-0">
                                            <i class="<?= $activity['icon'] ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($activity['aktivitas']) ?></h6>
                                                    <p class="mb-1 text-muted"><?= htmlspecialchars($activity['detail']) ?></p>
                                                    <span class="badge badge-type <?= $activity['badge_color'] ?>"><?= $activity['kategori'] ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <hr>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once './includes/footer.php'; ?>

    <script>
        function selectAllTypes() {
            document.querySelectorAll('.type-checkbox').forEach(cb => cb.checked = true);
            updateDropdownText();
        }

        function clearAllTypes() {
            document.querySelectorAll('.type-checkbox').forEach(cb => cb.checked = false);
            updateDropdownText();
        }

        function updateDropdownText() {
            const checked = document.querySelectorAll('.type-checkbox:checked');
            const btn = document.getElementById('typeDropdown');
            if (checked.length === 0) {
                btn.textContent = 'Semua Jenis';
            } else if (checked.length === 1) {
                btn.textContent = checked[0].nextElementSibling.textContent;
            } else {
                btn.textContent = checked.length + ' jenis dipilih';
            }
        }

        document.querySelectorAll('.type-checkbox').forEach(cb => {
            cb.addEventListener('change', updateDropdownText);
        });
    </script>

</body>
<!-- [Body] end -->

</html>