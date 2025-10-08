<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Ambil data hutang
$sql = "SELECT h.*, 
               CASE 
                   WHEN h.jenis_karyawan = 'pemotong' THEN p.nama_pemotong 
                   ELSE j.nama_penjahit 
               END as nama_karyawan
        FROM hutang_upah h
        LEFT JOIN pemotong p ON h.jenis_karyawan = 'pemotong' AND h.id_karyawan = p.id_pemotong
        LEFT JOIN penjahit j ON h.jenis_karyawan = 'penjahit' AND h.id_karyawan = j.id_penjahit
        ORDER BY h.periode DESC, h.jenis_karyawan";

$hutang = query($sql);

// Proses pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bayar_hutang'])) {
    $id_hutang = intval($_POST['id_hutang']);
    $tanggal_bayar = $conn->real_escape_string($_POST['tanggal_bayar']);
    $jumlah_bayar = floatval($_POST['jumlah_bayar']);
    $metode_bayar = $conn->real_escape_string($_POST['metode_bayar']);
    $keterangan = $conn->real_escape_string($_POST['keterangan']);

    // Validasi
    $detail_hutang = getDetailHutang($id_hutang);
    if ($jumlah_bayar <= 0) {
        $error = "Jumlah pembayaran harus lebih dari 0";
    } elseif ($jumlah_bayar > $detail_hutang['sisa_hutang']) {
        $error = "Jumlah pembayaran tidak boleh melebihi sisa hutang";
    } else {
        if (bayarHutangUpah($id_hutang, $tanggal_bayar, $jumlah_bayar, $metode_bayar, $keterangan)) {
            $_SESSION['success'] = "Pembayaran berhasil dicatat";
            header("Location: hutang_upah.php");
            exit();
        } else {
            $error = "Gagal mencatat pembayaran";
        }
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
                    <h2>Manajemen Hutang Upah</h2>
                    <div>
                        <a href="hutang_upah.php" class="btn btn-warning me-2">
                            <i class="ti ti-report-money"></i> Daftar Upah
                        </a>
                        <a href="upah_settings.php" class="btn btn-info me-2">
                            <i class="ti ti-settings"></i> Setting Tarif Upah
                        </a>
                        <a href="new.php" class="btn btn-primary">
                            <i class="ti ti-circle-plus"></i> Tambah Produksi
                        </a>
                    </div>
                </div>

                <!-- Filter Form -->
                <form method="GET" class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter Produk</label>
                        <select name="id_produk" class="form-select">
                            <option value="0">Semua Produk</option>
                            <?php foreach ($produk as $p): ?>
                                <option value="<?= $p['id_produk'] ?>" <?= ($id_produk == $p['id_produk']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nama_produk']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= ($status == 'all') ? 'selected' : '' ?>>Semua Status</option>
                            <option value="diproses" <?= ($status == 'diproses') ? 'selected' : '' ?>>Diproses</option>
                            <option value="selesai" <?= ($status == 'selesai') ? 'selected' : '' ?>>Selesai</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="ti ti-filter"></i> Filter
                        </button>
                        <?php if ($id_produk > 0 || $status != 'all'): ?>
                            <a href="list.php" class="btn btn-secondary">
                                <i class="ti ti-rotate"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="card p-3">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Periode</th>
                                    <th>Karyawan</th>
                                    <th>Jenis</th>
                                    <th>Total Upah</th>
                                    <th>Total Dibayar</th>
                                    <th>Sisa Hutang</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hutang as $h): ?>
                                    <tr>
                                        <td><?= date('F Y', strtotime($h['periode'])) ?></td>
                                        <td><?= htmlspecialchars($h['nama_karyawan']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $h['jenis_karyawan'] == 'pemotong' ? 'warning' : 'info' ?>">
                                                <?= ucfirst($h['jenis_karyawan']) ?>
                                            </span>
                                        </td>
                                        <td><?= formatRupiah($h['total_upah']) ?></td>
                                        <td><?= formatRupiah($h['total_dibayar']) ?></td>
                                        <td class="<?= $h['sisa_hutang'] > 0 ? 'text-danger fw-bold' : '' ?>">
                                            <?= formatRupiah($h['sisa_hutang']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $h['status'] == 'lunas' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($h['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary btn-bayar"
                                                data-id="<?= $h['id_hutang'] ?>"
                                                data-nama="<?= htmlspecialchars($h['nama_karyawan']) ?>"
                                                data-sisa="<?= $h['sisa_hutang'] ?>"
                                                <?= $h['sisa_hutang'] <= 0 ? 'disabled' : '' ?>>
                                                Bayar
                                            </button>
                                            <a href="detail_hutang.php?id=<?= $h['id_hutang'] ?>" class="btn btn-sm btn-info">
                                                Detail
                                            </a>
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

    <!-- Modal Pembayaran -->
    <div class="modal fade" id="modalBayar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Bayar Hutang Upah</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_hutang" id="bayar_id_hutang">

                        <div class="mb-3">
                            <label>Karyawan</label>
                            <input type="text" class="form-control" id="bayar_nama_karyawan" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Sisa Hutang</label>
                            <input type="text" class="form-control" id="bayar_sisa_hutang" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Tanggal Bayar *</label>
                            <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label>Jumlah Bayar *</label>
                            <input type="number" name="jumlah_bayar" class="form-control" min="1" step="0.01" required>
                        </div>

                        <div class="mb-3">
                            <label>Metode Bayar</label>
                            <select name="metode_bayar" class="form-control" required>
                                <option value="tunai">Tunai</option>
                                <option value="transfer">Transfer</option>
                                <option value="e-wallet">E-Wallet</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="bayar_hutang" class="btn btn-primary">Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalBayar = new bootstrap.Modal(document.getElementById('modalBayar'));

        document.querySelectorAll('.btn-bayar').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const nama = this.dataset.nama;
                const sisa = this.dataset.sisa;

                document.getElementById('bayar_id_hutang').value = id;
                document.getElementById('bayar_nama_karyawan').value = nama;
                document.getElementById('bayar_sisa_hutang').value = formatRupiah(sisa);

                // Set max value untuk input jumlah bayar
                document.querySelector('input[name="jumlah_bayar"]').max = sisa;

                modalBayar.show();
            });
        });

        function formatRupiah(amount) {
            return 'Rp ' + Number(amount).toLocaleString('id-ID');
        }
    });
</script>

</html>