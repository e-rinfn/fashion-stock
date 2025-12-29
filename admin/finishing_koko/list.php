<?php
// File: list.php
require_once '../includes/header.php';
require_once '../../config/functions.php';

session_start();

// Ambil data untuk filter
$petugas = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");
$tipe_data = isset($_GET['tipe_data']) ? $_GET['tipe_data'] : 'all';
$id_petugas_filter = isset($_GET['id_petugas']) ? intval($_GET['id_petugas']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : '';
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

// Query untuk koko_keluar
$where_keluar = "1=1";
if ($id_petugas_filter > 0) $where_keluar .= " AND kk.id_petugas_finishing = $id_petugas_filter";
if ($status_filter != 'all') $where_keluar .= " AND kk.status = '$status_filter'";
if ($tanggal_awal) $where_keluar .= " AND DATE(kk.tanggal_koko_keluar) >= '$tanggal_awal'";
if ($tanggal_akhir) $where_keluar .= " AND DATE(kk.tanggal_koko_keluar) <= '$tanggal_akhir'";

$koko_keluar = query("
    SELECT kk.*, pf.nama_petugas, 
           (SELECT COUNT(*) FROM detail_koko_keluar d WHERE d.id_koko_keluar = kk.id_koko_keluar) as jumlah_item
    FROM koko_keluar kk
    JOIN petugas_finishing pf ON kk.id_petugas_finishing = pf.id_petugas_finishing
    WHERE $where_keluar
    ORDER BY kk.tanggal_koko_keluar DESC
");

// Query untuk koko_masuk
$where_masuk = "1=1";
if ($id_petugas_filter > 0) $where_masuk .= " AND km.id_petugas_finishing = $id_petugas_filter";
if ($status_filter != 'all') $where_masuk .= " AND km.status = '$status_filter'";
if ($tanggal_awal) $where_masuk .= " AND DATE(km.tanggal_koko_masuk) >= '$tanggal_awal'";
if ($tanggal_akhir) $where_masuk .= " AND DATE(km.tanggal_koko_masuk) <= '$tanggal_akhir'";

$koko_masuk = query("
    SELECT km.*, pf.nama_petugas,
           (SELECT COUNT(*) FROM detail_koko_masuk d WHERE d.id_koko_masuk = km.id_koko_masuk) as jumlah_item
    FROM koko_masuk km
    JOIN petugas_finishing pf ON km.id_petugas_finishing = pf.id_petugas_finishing
    WHERE $where_masuk
    ORDER BY km.tanggal_koko_masuk DESC
");

// Hitung statistik
$total_keluar = array_sum(array_column($koko_keluar, 'total_harga'));
$total_masuk = array_sum(array_column($koko_masuk, 'total_harga'));
$count_keluar = count($koko_keluar);
$count_masuk = count($koko_masuk);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <title>Laporan Finishing Koko</title>
    <style>
        .tab-content {
            padding: 20px 0;
        }

        .status-badge {
            font-size: 0.8rem;
        }

        .summary-card {
            transition: transform 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>LAPORAN FINISHING KOKO</h2>

        <!-- Statistik -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card summary-card border-start border-danger border-4">
                    <div class="card-body">
                        <h6 class="text-muted">KOKO KELUAR</h6>
                        <h3><?= formatRupiah($total_keluar) ?></h3>
                        <small><?= $count_keluar ?> transaksi</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card border-start border-success border-4">
                    <div class="card-body">
                        <h6 class="text-muted">KOKO MASUK</h6>
                        <h3><?= formatRupiah($total_masuk) ?></h3>
                        <small><?= $count_masuk ?> transaksi</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card border-start border-primary border-4">
                    <div class="card-body">
                        <h6 class="text-muted">SELISIH</h6>
                        <h3 class="<?= ($total_masuk - $total_keluar) >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatRupiah($total_masuk - $total_keluar) ?>
                        </h3>
                        <small>Nilai tambah finishing</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card border-start border-warning border-4">
                    <div class="card-body">
                        <h6 class="text-muted">TOTAL TRANSAKSI</h6>
                        <h3><?= $count_keluar + $count_masuk ?></h3>
                        <small>Pengiriman & Penerimaan</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label>Tipe Data</label>
                        <select name="tipe_data" class="form-select">
                            <option value="all" <?= $tipe_data == 'all' ? 'selected' : '' ?>>Semua</option>
                            <option value="keluar" <?= $tipe_data == 'keluar' ? 'selected' : '' ?>>Koko Keluar</option>
                            <option value="masuk" <?= $tipe_data == 'masuk' ? 'selected' : '' ?>>Koko Masuk</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Petugas</label>
                        <select name="id_petugas" class="form-select">
                            <option value="0">Semua Petugas</option>
                            <?php foreach ($petugas as $p): ?>
                                <option value="<?= $p['id_petugas_finishing'] ?>"
                                    <?= $id_petugas_filter == $p['id_petugas_finishing'] ? 'selected' : '' ?>>
                                    <?= $p['nama_petugas'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="all">Semua Status</option>
                            <option value="dikirim" <?= $status_filter == 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                            <option value="selesai" <?= $status_filter == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="list.php" class="btn btn-secondary ms-2">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tipe_data != 'masuk' ? 'active' : '' ?>"
                    id="keluar-tab" data-bs-toggle="tab" data-bs-target="#keluar" type="button">
                    KOKO KELUAR (<?= $count_keluar ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tipe_data == 'masuk' ? 'active' : '' ?>"
                    id="masuk-tab" data-bs-toggle="tab" data-bs-target="#masuk" type="button">
                    KOKO MASUK (<?= $count_masuk ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Tab Koko Keluar -->
            <div class="tab-pane fade <?= $tipe_data != 'masuk' ? 'show active' : '' ?>" id="keluar">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Petugas</th>
                                <th>Jumlah Item</th>
                                <th>Total Harga</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($koko_keluar as $i => $kk): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($kk['tanggal_koko_keluar'])) ?></td>
                                    <td><?= $kk['nama_petugas'] ?></td>
                                    <td><?= $kk['jumlah_item'] ?> item</td>
                                    <td><?= formatRupiah($kk['total_harga']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $kk['status'] == 'selesai' ? 'success' : 'warning' ?>">
                                            <?= strtoupper($kk['status']) ?>
                                        </span>

                                    </td>
                                    <td>
                                        <a href="detail_koko_keluar.php?id=<?= $kk['id_koko_keluar'] ?>"
                                            class="btn btn-sm btn-info">Detail</a>
                                        <?php if ($kk['status'] == 'dikirim'): ?>
                                            <a href="koko_masuk.php?referensi=<?= $kk['id_koko_keluar'] ?>&petugas=<?= $kk['id_petugas_finishing'] ?>"
                                                class="btn btn-sm btn-success">Input Hasil</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab Koko Masuk -->
            <div class="tab-pane fade <?= $tipe_data == 'masuk' ? 'show active' : '' ?>" id="masuk">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Petugas</th>
                                <th>Jumlah Item</th>
                                <th>Total Harga</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($koko_masuk as $i => $km): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($km['tanggal_koko_masuk'])) ?></td>
                                    <td><?= $km['nama_petugas'] ?></td>
                                    <td><?= $km['jumlah_item'] ?> item</td>
                                    <td><?= formatRupiah($km['total_harga']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $km['status'] == 'selesai' ? 'success' : 'warning' ?>">
                                            <?= strtoupper($km['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="detail_koko_masuk.php?id=<?= $km['id_koko_masuk'] ?>"
                                            class="btn btn-sm btn-info">Detail</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>