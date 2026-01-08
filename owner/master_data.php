<?php

// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

$page_title = "MASTER DATA";


require_once '../config/functions.php';
require_once '../config/database.php';
require_once './includes/header.php';

// Handle tambah tarif upah
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_tarif'])) {
    $jenis_tarif = $conn->real_escape_string($_POST['jenis_tarif']);
    $tarif_per_unit = floatval($_POST['tarif_per_unit']);
    $berlaku_sejak = $conn->real_escape_string($_POST['berlaku_sejak']);
    $keterangan = $conn->real_escape_string($_POST['keterangan'] ?? '');

    $sql = "INSERT INTO tarif_upah (jenis_tarif, tarif_per_unit, berlaku_sejak, keterangan) 
            VALUES ('$jenis_tarif', $tarif_per_unit, '$berlaku_sejak', '$keterangan')";

    if ($conn->query($sql)) {
        $_SESSION['success'] = "Tarif upah berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan tarif upah: " . $conn->error;
    }

    header("Location: master_data.php");
    exit();
}

// Query semua data master
$bahan_baku = query("SELECT * FROM bahan_baku ORDER BY nama_bahan");
$produk = query("SELECT * FROM produk ORDER BY nama_produk");
$pemotong = query("SELECT * FROM pemotong ORDER BY nama_pemotong");
$bordir = query("SELECT * FROM bordir ORDER BY nama_bordir");
$penjahit = query("SELECT * FROM penjahit ORDER BY nama_penjahit");
$finishing = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");
$reseller = query("SELECT * FROM reseller ORDER BY nama_reseller");
$supplier = query("SELECT * FROM supplier ORDER BY nama_supplier");
$tarif_upah = query("SELECT * FROM tarif_upah ORDER BY jenis_tarif ASC, berlaku_sejak DESC");
?>

<style>
    .nav-tabs-custom {
        border-bottom: 2px solid #e9ecef;
        flex-wrap: wrap;
        gap: 5px;
    }

    .nav-tabs-custom .nav-link {
        color: #6c757d;
        border: none;
        border-bottom: 2px solid transparent;
        padding: 8px 16px;
        font-size: 0.9rem;
        background: transparent;
        margin-bottom: -2px;
    }

    .nav-tabs-custom .nav-link:hover {
        color: #4680ff;
        border-bottom-color: #4680ff;
    }

    .nav-tabs-custom .nav-link.active {
        color: #4680ff;
        font-weight: 600;
        border-bottom: 2px solid #4680ff;
        background: transparent;
    }

    .nav-tabs-custom .badge {
        font-size: 0.7rem;
        padding: 2px 6px;
        margin-left: 4px;
    }


    .card-header-simple {
        background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        padding: 12px 16px;
    }
</style>

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

            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ti ti-check me-2"></i>
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ti ti-alert-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <ul class="nav nav-tabs-custom mb-4" id="masterDataTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#bahan" type="button">
                        Bahan Baku <span class="badge bg-secondary"><?= count($bahan_baku) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#produk" type="button">
                        Produk <span class="badge bg-secondary"><?= count($produk) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pemotong" type="button">
                        Pemotong <span class="badge bg-secondary"><?= count($pemotong) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#bordir" type="button">
                        Bordir <span class="badge bg-secondary"><?= count($bordir) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#penjahit" type="button">
                        Penjahit <span class="badge bg-secondary"><?= count($penjahit) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#finishing" type="button">
                        Finishing <span class="badge bg-secondary"><?= count($finishing) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#reseller" type="button">
                        Reseller <span class="badge bg-secondary"><?= count($reseller) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#supplier" type="button">
                        Supplier <span class="badge bg-secondary"><?= count($supplier) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tarif" type="button">
                        Tarif Upah <span class="badge bg-secondary"><?= count($tarif_upah) ?></span>
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="masterDataTabContent">

                <!-- Tab Bahan Baku -->
                <div class="tab-pane fade show active" id="bahan" role="tabpanel">
                    <div class="card">
                        <div class="card-header card-header-simple d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Data Bahan Baku</h6>
                            <a href="<?= $base_url ?>/owner/bahan_baku/add.php" class="btn btn-success btn-sm">
                                <i class="ti ti-plus"></i> Tambah
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive table-container">
                                <table class="table table-striped table-hover table-bordered" id="tableBahan">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-center">
                                            <th style="width: 5%">No</th>
                                            <th>Nama Bahan</th>
                                            <th>Stok (Roll)</th>
                                            <th>Total (Meter)</th>
                                            <th>Harga/Meter</th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($bahan_baku)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">Tidak ada data</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1;
                                            foreach ($bahan_baku as $b): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($b['nama_bahan']) ?></td>
                                                    <td class="text-center"><?= number_format($b['jumlah_stok'] ?? 0) ?></td>
                                                    <td class="text-end"><?= number_format($b['jumlah_meter'] ?? 0) ?></td>
                                                    <td class="text-end"><?= formatRupiah($b['harga_per_satuan'] ?? 0) ?></td>
                                                    <td class="text-center">
                                                        <a href="<?= $base_url ?>/owner/bahan_baku/edit.php?id=<?= $b['id_bahan'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm btn-delete"
                                                            data-id="<?= $b['id_bahan'] ?>"
                                                            data-nama="<?= htmlspecialchars($b['nama_bahan']) ?>"
                                                            data-url="<?= $base_url ?>/owner/bahan_baku/delete.php"
                                                            title="Hapus">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
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

                <!-- Tab Produk -->
                <div class="tab-pane fade" id="produk" role="tabpanel">
                    <div class="card">
                        <div class="card-header card-header-simple d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Data Produk</h6>
                            <a href="<?= $base_url ?>/owner/produk/add.php" class="btn btn-success btn-sm">
                                <i class="ti ti-plus"></i> Tambah
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive table-container">
                                <table class="table table-striped table-hover table-bordered" id="tableProduk">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-center">
                                            <th style="width: 5%">No</th>
                                            <th>Nama Produk</th>
                                            <th>Tipe Produk</th>
                                            <th>Stok</th>
                                            <th>Harga/Pcs</th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($produk)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">Tidak ada data</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1;
                                            foreach ($produk as $p): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($p['nama_produk']) ?></td>
                                                    <td class="text-center"><?= ucfirst($p['tipe_produk'] ?? '-') ?></td>
                                                    <td class="text-end"><?= number_format($p['stok'] ?? 0) ?></td>
                                                    <td class="text-end"><?= formatRupiah($p['harga_jual'] ?? 0) ?></td>
                                                    <td class="text-center">
                                                        <a href="<?= $base_url ?>/owner/produk/edit.php?id=<?= $p['id_produk'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm btn-delete"
                                                            data-id="<?= $p['id_produk'] ?>"
                                                            data-nama="<?= htmlspecialchars($p['nama_produk']) ?>"
                                                            data-url="<?= $base_url ?>/owner/produk/delete.php"
                                                            title="Hapus">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
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

                <!-- Tab Pemotong -->
                <div class="tab-pane fade" id="pemotong" role="tabpanel">
                    <div class="card">
                        <div class="card-header card-header-simple d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Data Pemotong</h6>
                            <a href="<?= $base_url ?>/owner/pemotong/add.php" class="btn btn-success btn-sm">
                                <i class="ti ti-plus"></i> Tambah
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive table-container">
                                <table class="table table-striped table-hover table-bordered" id="tablePemotong">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-center">
                                            <th style="width: 5%">No</th>
                                            <th>Nama Pemotong</th>
                                            <th>Kontak</th>
                                            <th>Alamat</th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($pemotong)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1;
                                            foreach ($pemotong as $pm): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($pm['nama_pemotong']) ?></td>
                                                    <td><?= htmlspecialchars($pm['kontak'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($pm['alamat'] ?? '-') ?></td>
                                                    <td class="text-center">
                                                        <a href="<?= $base_url ?>/owner/pemotong/edit.php?id=<?= $pm['id_pemotong'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm btn-delete"
                                                            data-id="<?= $pm['id_pemotong'] ?>"
                                                            data-nama="<?= htmlspecialchars($pm['nama_pemotong']) ?>"
                                                            data-url="<?= $base_url ?>/owner/pemotong/delete.php"
                                                            title="Hapus">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
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

                <!-- Tab Bordir -->
                <div class="tab-pane fade" id="bordir" role="tabpanel">
                    <div class="card">
                        <div class="card-header card-header-simple d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Data Bordir</h6>
                            <a href="<?= $base_url ?>/owner/petugas_bordir/add.php" class="btn btn-success btn-sm">
                                <i class="ti ti-plus"></i> Tambah
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive table-container">
                                <table class="table table-striped table-hover table-bordered" id="tableBordir">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-center">
                                            <th style="width: 5%">No</th>
                                            <th>Nama Bordir</th>
                                            <th>Kontak</th>
                                            <th>Alamat</th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($bordir)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1;
                                            foreach ($bordir as $bd): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($bd['nama_bordir']) ?></td>
                                                    <td><?= htmlspecialchars($bd['kontak'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($bd['alamat'] ?? '-') ?></td>
                                                    <td class="text-center">
                                                        <a href="<?= $base_url ?>/owner/petugas_bordir/edit.php?id=<?= $bd['id_bordir'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm btn-delete"
                                                            data-id="<?= $bd['id_bordir'] ?>"
                                                            data-nama="<?= htmlspecialchars($bd['nama_bordir']) ?>"
                                                            data-url="<?= $base_url ?>/owner/petugas_bordir/delete.php"
                                                            title="Hapus">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
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

                <!-- Tab Penjahit -->
                <div class="tab-pane fade" id="penjahit" role="tabpanel">
                    <div class="card">
                        <div class="card-header card-header-simple d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Data Penjahit</h6>
                            <a href="<?= $base_url ?>/owner/penjahit/add.php" class="btn btn-success btn-sm">
                                <i class="ti ti-plus"></i> Tambah
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive table-container">
                                <table class="table table-striped table-hover table-bordered" id="tablePenjahit">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-center">
                                            <th style="width: 5%">No</th>
                                            <th>Nama Penjahit</th>
                                            <th>Kontak</th>
                                            <th>Alamat</th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($penjahit)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1;
                                            foreach ($penjahit as $pj): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($pj['nama_penjahit']) ?></td>
                                                    <td><?= htmlspecialchars($pj['kontak'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($pj['alamat'] ?? '-') ?></td>
                                                    <td class="text-center">
                                                        <a href="<?= $base_url ?>/owner/penjahit/edit.php?id=<?= $pj['id_penjahit'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm btn-delete"
                                                            data-id="<?= $pj['id_penjahit'] ?>"
                                                            data-nama="<?= htmlspecialchars($pj['nama_penjahit']) ?>"
                                                            data-url="<?= $base_url ?>/owner/penjahit/delete.php"
                                                            title="Hapus">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
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

                <!-- Tab Finishing -->
                <div class="tab-pane fade" id="finishing" role="tabpanel">
                    <div class="card">
                        <div class="card-header card-header-simple d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Data Petugas Finishing</h6>
                            <a href="<?= $base_url ?>/owner/petugas_finishing/add.php" class="btn btn-success btn-sm">
                                <i class="ti ti-plus"></i> Tambah
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive table-container">
                                <table class="table table-striped table-hover table-bordered" id="tableFinishing">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-center">
                                            <th style="width: 5%">No</th>
                                            <th>Nama Petugas</th>
                                            <th>Kontak</th>
                                            <th>Alamat</th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($finishing)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1;
                                            foreach ($finishing as $fn): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($fn['nama_petugas']) ?></td>
                                                    <td><?= htmlspecialchars($fn['kontak'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($fn['alamat'] ?? '-') ?></td>
                                                    <td class="text-center">
                                                        <a href="<?= $base_url ?>/owner/petugas_finishing/edit.php?id=<?= $fn['id_petugas_finishing'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm btn-delete"
                                                            data-id="<?= $fn['id_petugas_finishing'] ?>"
                                                            data-nama="<?= htmlspecialchars($fn['nama_petugas']) ?>"
                                                            data-url="<?= $base_url ?>/owner/petugas_finishing/delete.php"
                                                            title="Hapus">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
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

                <!-- Tab Reseller -->
                <div class="tab-pane fade" id="reseller" role="tabpanel">
                    <div class="card">
                        <div class="card-header card-header-simple d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Data Reseller</h6>
                            <a href="<?= $base_url ?>/owner/reseller/add.php" class="btn btn-success btn-sm">
                                <i class="ti ti-plus"></i> Tambah
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive table-container">
                                <table class="table table-striped table-hover table-bordered" id="tableReseller">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-center">
                                            <th style="width: 5%">No</th>
                                            <th>Nama Reseller</th>
                                            <th>Kontak</th>
                                            <th>Tanggal Bergabung</th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reseller)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1;
                                            foreach ($reseller as $rs): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($rs['nama_reseller']) ?></td>
                                                    <td><?= htmlspecialchars($rs['kontak'] ?? '-') ?></td>
                                                    <td class="text-center"><?= isset($rs['tanggal_bergabung']) ? dateIndo($rs['tanggal_bergabung']) : '-' ?></td>
                                                    <td class="text-center">
                                                        <a href="<?= $base_url ?>/owner/reseller/edit.php?id=<?= $rs['id_reseller'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm btn-delete"
                                                            data-id="<?= $rs['id_reseller'] ?>"
                                                            data-nama="<?= htmlspecialchars($rs['nama_reseller']) ?>"
                                                            data-url="<?= $base_url ?>/owner/reseller/delete.php"
                                                            title="Hapus">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
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

                <!-- Tab Supplier -->
                <div class="tab-pane fade" id="supplier" role="tabpanel">
                    <div class="card">
                        <div class="card-header card-header-simple d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Data Supplier</h6>
                            <a href="<?= $base_url ?>/owner/supplier/add.php" class="btn btn-success btn-sm">
                                <i class="ti ti-plus"></i> Tambah
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive table-container">
                                <table class="table table-striped table-hover table-bordered" id="tableSupplier">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-center">
                                            <th style="width: 5%">No</th>
                                            <th>Nama Supplier</th>
                                            <th>Kontak</th>
                                            <th>Alamat</th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($supplier)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1;
                                            foreach ($supplier as $sp): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($sp['nama_supplier']) ?></td>
                                                    <td><?= htmlspecialchars($sp['kontak'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($sp['alamat'] ?? '-') ?></td>
                                                    <td class="text-center">
                                                        <a href="<?= $base_url ?>/owner/supplier/edit.php?id=<?= $sp['id_supplier'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm btn-delete"
                                                            data-id="<?= $sp['id_supplier'] ?>"
                                                            data-nama="<?= htmlspecialchars($sp['nama_supplier']) ?>"
                                                            data-url="<?= $base_url ?>/owner/supplier/delete.php"
                                                            title="Hapus">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
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

                <!-- Tab Tarif Upah -->
                <div class="tab-pane fade" id="tarif" role="tabpanel">
                    <div class="card">
                        <div class="card-header card-header-simple d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Data Tarif Upah</h6>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahTarif">
                                <i class="ti ti-plus"></i> Tambah
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive table-container">
                                <table class="table table-striped table-hover table-bordered" id="tableTarif">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-center">
                                            <th style="width: 5%">No</th>
                                            <th>Jenis Tarif</th>
                                            <th>Tarif/Unit</th>
                                            <th>Berlaku Sejak</th>
                                            <th>Keterangan</th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($tarif_upah)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">Tidak ada data</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1;
                                            foreach ($tarif_upah as $tu): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++ ?></td>
                                                    <td>
                                                        <?php
                                                        $badgeColor = match ($tu['jenis_tarif']) {
                                                            'pemotongan' => 'info',
                                                            'bordir' => 'primary',
                                                            'penjahitan' => 'danger',
                                                            'finishing' => 'success',
                                                            default => 'warning'
                                                        };
                                                        ?>
                                                        <span class="badge bg-<?= $badgeColor ?>">
                                                            <?= ucfirst($tu['jenis_tarif']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end"><?= formatRupiah($tu['tarif_per_unit'] ?? 0) ?></td>
                                                    <td class="text-center"><?= isset($tu['berlaku_sejak']) ? dateIndo($tu['berlaku_sejak']) : '-' ?></td>
                                                    <td><?= htmlspecialchars($tu['keterangan'] ?? '-') ?></td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-danger btn-sm btn-delete"
                                                            data-id="<?= $tu['id_tarif'] ?>"
                                                            data-nama="Tarif <?= ucfirst($tu['jenis_tarif']) ?>"
                                                            data-url="<?= $base_url ?>/owner/upah/upah_settings.php?hapus=<?= $tu['id_tarif'] ?>"
                                                            data-direct="true"
                                                            title="Hapus">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
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
            <!-- /Tab Content -->

        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once './includes/footer.php'; ?>

    <!-- Modal Tambah Tarif Upah -->
    <div class="modal fade" id="modalTambahTarif" tabindex="-1" aria-labelledby="modalTambahTarifLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTambahTarifLabel">Tambah Tarif Upah Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="jenis_tarif" class="form-label">Jenis Tarif <span class="text-danger">*</span></label>
                            <select class="form-select" id="jenis_tarif" name="jenis_tarif" required>
                                <option value="">-- Pilih Jenis Tarif --</option>
                                <option value="pemotongan">Pemotongan</option>
                                <option value="bordir">Bordir</option>
                                <option value="penjahitan">Penjahitan</option>
                                <option value="finishing">Finishing</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="tarif_per_unit" class="form-label">Tarif per Unit (Rp) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="tarif_per_unit" name="tarif_per_unit"
                                placeholder="Masukkan tarif per unit" min="0" step="100" required>
                        </div>
                        <div class="mb-3">
                            <label for="berlaku_sejak" class="form-label">Berlaku Sejak <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="berlaku_sejak" name="berlaku_sejak"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="2"
                                placeholder="Masukkan keterangan (opsional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_tarif" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Simpan tab aktif ke localStorage
        document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(button => {
            button.addEventListener('shown.bs.tab', function(e) {
                localStorage.setItem('activeTab', e.target.getAttribute('data-bs-target'));
            });
        });

        // Restore tab aktif dari localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = localStorage.getItem('activeTab');
            if (activeTab) {
                const tabButton = document.querySelector(`button[data-bs-target="${activeTab}"]`);
                if (tabButton) {
                    const tab = new bootstrap.Tab(tabButton);
                    tab.show();
                }
            }
        });

        // Handle delete button dengan SweetAlert
        document.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const nama = this.dataset.nama;
                const url = this.dataset.url;
                const isDirect = this.dataset.direct === 'true';

                Swal.fire({
                    title: 'Konfirmasi Hapus',
                    html: `Apakah Anda yakin ingin menghapus <strong>${nama}</strong>?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (isDirect) {
                            window.location.href = url;
                        } else {
                            window.location.href = `${url}?id=${id}`;
                        }
                    }
                });
            });
        });
    </script>

</body>
<!-- [Body] end -->

</html>