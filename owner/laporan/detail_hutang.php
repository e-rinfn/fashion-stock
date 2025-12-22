<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

$id_hutang = intval($_GET['id']);
$detail = getDetailHutang($id_hutang);

// Cek apakah data hutang ditemukan
if (!$detail) {
    $_SESSION['error'] = "Data hutang tidak ditemukan";
    header("Location: upah.php");
    exit();
}

// Ambil riwayat pembayaran - GUNAKAN TABEL pembayaran_upah_2
$pembayaran = query("SELECT * FROM pembayaran_upah_2 WHERE id_hutang = $id_hutang ORDER BY tanggal_bayar DESC");

// Proses batal pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['batal_pembayaran'])) {
    $id_pembayaran = intval($_POST['id_pembayaran']);

    if (batalPembayaranUpah($id_pembayaran)) {
        $_SESSION['success'] = "Pembayaran berhasil dibatalkan";
        header("Location: detail_hutang.php?id=$id_hutang");
        exit();
    } else {
        $_SESSION['error'] = "Gagal membatalkan pembayaran";
        header("Location: detail_hutang.php?id=$id_hutang");
        exit();
    }
}
?>

<style>
    .swal2-container {
        z-index: 99999 !important;
    }

    .badge-produksi {
        background-color: #0d6efd;
    }

    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .btn-group-actions {
        display: flex;
        gap: 5px;
        flex-wrap: nowrap;
    }

    .btn-group-actions .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .upah-column {
        background-color: #e8f5e8 !important;
        font-weight: bold;
    }

    .table th {
        font-size: 0.8rem;
    }

    .table td {
        font-size: 0.8rem;
    }

    .tarif-info {
        font-size: 0.7rem;
        color: #6c757d;
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

    <!-- [ Header Topbar ] start -->
    <header class="pc-header">
        <div class="header-wrapper">
            <!-- [Mobile Media Block] start -->
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
                    <!-- ======= Menu collapse Icon ===== -->
                    <li class="pc-h-item pc-sidebar-collapse">
                        <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                    <li class="pc-h-item pc-sidebar-popup">
                        <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- [Mobile Media Block end] -->

        </div>
    </header>
    <!-- [ Header ] end -->
    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">

            <!-- [ Main Content ] start -->
            <div class="row">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Detail Hutang</h2>
                    <div>
                        <a href="upah.php" class="btn btn-secondary me-2">
                            <i class="ti ti-arrow-back"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Informasi Hutang</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr hidden>
                                    <th>Periode</th>
                                    <td><?= date('F Y', strtotime($detail['periode'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Nama Karyawan</th>
                                    <td><?= htmlspecialchars($detail['nama_karyawan']) ?></td>
                                </tr>
                                <tr>
                                    <th>Jenis</th>
                                    <td><?= ucfirst($detail['jenis_karyawan']) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Upah</th>
                                    <td><?= formatRupiah($detail['total_upah']) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Dibayar</th>
                                    <td><?= formatRupiah($detail['total_dibayar']) ?></td>
                                </tr>
                                <tr>
                                    <th>Sisa Hutang</th>
                                    <td class="text-danger fw-bold"><?= formatRupiah($detail['sisa_hutang']) ?></td>
                                </tr>
                                <tr hidden>
                                    <th>Status</th>
                                    <td>
                                        <span class="badge bg-<?= $detail['status'] == 'lunas' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($detail['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Riwayat Pembayaran</h5>
                            <span class="badge bg-primary"><?= count($pembayaran) ?> Pembayaran</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pembayaran)): ?>
                                <p class="text-muted">Belum ada pembayaran</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr class="text-center">
                                                <th>Tanggal</th>
                                                <th>Jumlah</th>
                                                <th>Metode</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pembayaran as $bayar): ?>
                                                <tr>
                                                    <td><?= dateIndo($bayar['tanggal_bayar']) ?></td>
                                                    <td><?= formatRupiah($bayar['jumlah_bayar']) ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-secondary">
                                                            <?= ucfirst($bayar['metode_bayar']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="id_pembayaran" value="<?= $bayar['id_pembayaran'] ?>">
                                                            <input type="hidden" name="batal_pembayaran" value="1">
                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-batal"
                                                                data-tanggal="<?= dateIndo($bayar['tanggal_bayar']) ?>"
                                                                data-jumlah="<?= formatRupiah($bayar['jumlah_bayar']) ?>">
                                                                <i class="ti ti-x"></i> Batal
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // Konfirmasi sebelum batal pembayaran
        $('.btn-batal').on('click', function() {
            const form = $(this).closest('form');
            const tanggal = $(this).data('tanggal');
            const jumlah = $(this).data('jumlah');

            Swal.fire({
                title: 'Batalkan Pembayaran?',
                html: `
                    <div class="text-start">
                        <p>Apakah Anda yakin ingin membatalkan pembayaran berikut?</p>
                        <div class="alert alert-warning">
                            <strong>Tanggal:</strong> ${tanggal}<br>
                            <strong>Jumlah:</strong> ${jumlah}
                        </div>
                        <p class="text-danger mb-0">
                            <i class="ti ti-alert-triangle"></i> 
                            Tindakan ini akan mengembalikan status hutang dan tidak dapat dibatalkan.
                        </p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                cancelButtonText: 'Batal',
                confirmButtonText: 'Ya, Batalkan!',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        // Notifikasi sukses atau error dari PHP session
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '<?= $_SESSION['success'] ?>',
                timer: 2500,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success']); ?>
        <?php elseif (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: '<?= $_SESSION['error'] ?>',
                timer: 2500,
                showConfirmButton: false
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    });
</script>

</html>