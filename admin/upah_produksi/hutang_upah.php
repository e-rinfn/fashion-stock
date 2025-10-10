<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Ambil data untuk filter
$pemotong = query("SELECT * FROM pemotong ORDER BY nama_pemotong");
$penjahit = query("SELECT * FROM penjahit ORDER BY nama_penjahit");

// Filter parameters
$jenis_karyawan = isset($_GET['jenis_karyawan']) ? $_GET['jenis_karyawan'] : 'all';
$id_karyawan = isset($_GET['id_karyawan']) ? intval($_GET['id_karyawan']) : 0;
$status_hutang = isset($_GET['status_hutang']) ? $_GET['status_hutang'] : 'all';
$periode = isset($_GET['periode']) ? $_GET['periode'] : '';

// Build query dengan filter
$sql = "SELECT h.*, 
               CASE 
                   WHEN h.jenis_karyawan = 'pemotong' THEN p.nama_pemotong 
                   ELSE j.nama_penjahit 
               END as nama_karyawan
        FROM hutang_upah h
        LEFT JOIN pemotong p ON h.jenis_karyawan = 'pemotong' AND h.id_karyawan = p.id_pemotong
        LEFT JOIN penjahit j ON h.jenis_karyawan = 'penjahit' AND h.id_karyawan = j.id_penjahit
        WHERE 1=1";

$params = [];

// Filter jenis karyawan
if ($jenis_karyawan != 'all') {
    $sql .= " AND h.jenis_karyawan = ?";
    $params[] = $jenis_karyawan;
}

// Filter karyawan spesifik
if ($id_karyawan > 0) {
    if ($jenis_karyawan == 'pemotong') {
        $sql .= " AND h.jenis_karyawan = 'pemotong' AND h.id_karyawan = ?";
    } else {
        $sql .= " AND h.jenis_karyawan = 'penjahit' AND h.id_karyawan = ?";
    }
    $params[] = $id_karyawan;
}

// Filter status
if ($status_hutang != 'all') {
    $sql .= " AND h.status = ?";
    $params[] = $status_hutang;
}

// Filter periode
if (!empty($periode)) {
    $sql .= " AND h.periode = ?";
    $params[] = $periode;
}

$sql .= " ORDER BY h.periode DESC, h.jenis_karyawan";

// Prepare and execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $hutang = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $hutang = query($sql);
}

// Proses pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Bayar Hutang
    if (isset($_POST['bayar_hutang'])) {
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
                header("Location: hutang_upah.php?" . http_build_query($_GET));
                exit();
            } else {
                $error = "Gagal mencatat pembayaran";
            }
        }
    }

    // Batal Pembayaran
    if (isset($_POST['batal_pembayaran'])) {
        $id_pembayaran = intval($_POST['id_pembayaran']);

        if (batalPembayaranUpah($id_pembayaran)) {
            $_SESSION['success'] = "Pembayaran berhasil dibatalkan";
            header("Location: hutang_upah.php?" . http_build_query($_GET));
            exit();
        } else {
            $error = "Gagal membatalkan pembayaran";
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

    .filter-section {
        background-color: #f8f9fa;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Manajemen Hutang Upah</h2>
                </div>

                <!-- Filter Form -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Jenis Karyawan</label>
                            <select name="jenis_karyawan" class="form-select" id="jenisKaryawanSelect">
                                <option value="all">Semua Jenis</option>
                                <option value="pemotong" <?= $jenis_karyawan == 'pemotong' ? 'selected' : '' ?>>Pemotong</option>
                                <option value="penjahit" <?= $jenis_karyawan == 'penjahit' ? 'selected' : '' ?>>Penjahit</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Nama Karyawan</label>
                            <select name="id_karyawan" class="form-select" id="karyawanSelect">
                                <option value="0">Semua Karyawan</option>
                                <?php if ($jenis_karyawan == 'pemotong' || $jenis_karyawan == 'all'): ?>
                                    <?php foreach ($pemotong as $p): ?>
                                        <option value="<?= $p['id_pemotong'] ?>"
                                            <?= ($id_karyawan == $p['id_pemotong']) ? 'selected' : '' ?>
                                            data-jenis="pemotong">
                                            <?= htmlspecialchars($p['nama_pemotong']) ?> (Pemotong)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if ($jenis_karyawan == 'penjahit' || $jenis_karyawan == 'all'): ?>
                                    <?php foreach ($penjahit as $j): ?>
                                        <option value="<?= $j['id_penjahit'] ?>"
                                            <?= ($id_karyawan == $j['id_penjahit']) ? 'selected' : '' ?>
                                            data-jenis="penjahit">
                                            <?= htmlspecialchars($j['nama_penjahit']) ?> (Penjahit)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Status Hutang</label>
                            <select name="status_hutang" class="form-select">
                                <option value="all" <?= $status_hutang == 'all' ? 'selected' : '' ?>>Semua Status</option>
                                <option value="belum_lunas" <?= $status_hutang == 'belum_lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                                <option value="lunas" <?= $status_hutang == 'lunas' ? 'selected' : '' ?>>Lunas</option>
                            </select>
                        </div>


                        <div hidden class="col-md-2">
                            <label class="form-label">Periode</label>
                            <input type="month" name="periode" class="form-control" value="<?= $periode ?>">
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="ti ti-filter"></i> Filter
                            </button>
                            <?php if ($jenis_karyawan != 'all' || $id_karyawan > 0 || $status_hutang != 'all' || !empty($periode)): ?>
                                <a href="hutang_upah.php" class="btn btn-secondary">
                                    <i class="ti ti-rotate"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="card p-3">

                    <!-- Tampilkan pesan error atau success -->
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
                    <!-- /Tampilkan pesan error atau success -->

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th style="width: 5%;">No</th>
                                    <th style="width: 12%;">Periode</th>
                                    <th style="width: 15%;">Karyawan</th>
                                    <th style="width: 10%;">Jenis</th>
                                    <th style="width: 13%;">Total Upah</th>
                                    <th style="width: 13%;">Total Dibayar</th>
                                    <th style="width: 13%;">Sisa Hutang</th>
                                    <th style="width: 10%;">Status</th>
                                    <th style="width: 9%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($hutang)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">Tidak ada data hutang upah</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($hutang as $h): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td><?= date('F Y', strtotime($h['periode'])) ?></td>
                                            <td><?= htmlspecialchars($h['nama_karyawan']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $h['jenis_karyawan'] == 'pemotong' ? 'warning' : 'info' ?>">
                                                    <?= ucfirst($h['jenis_karyawan']) ?>
                                                </span>
                                            </td>
                                            <td><?= formatRupiah($h['total_upah']) ?></td>
                                            <td><?= formatRupiah($h['total_dibayar']) ?></td>
                                            <td class="<?= $h['sisa_hutang'] > 0 ? 'text-danger fw-bold' : '' ?>">
                                                <?= formatRupiah($h['sisa_hutang']) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $h['sisa_hutang'] <= 0 ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($h['sisa_hutang'] <= 0 ? 'Lunas' : 'Belum Lunas') ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group-actions">
                                                    <button class="btn btn-sm btn-primary btn-bayar"
                                                        data-id="<?= $h['id_hutang'] ?>"
                                                        data-nama="<?= htmlspecialchars($h['nama_karyawan']) ?>"
                                                        data-sisa="<?= $h['sisa_hutang'] ?>"
                                                        <?= $h['sisa_hutang'] <= 0 ? 'disabled' : '' ?>
                                                        title="Bayar Hutang">
                                                        <i class="ti ti-cash"></i>
                                                    </button>
                                                    <a href="detail_hutang.php?id=<?= $h['id_hutang'] ?>" class="btn btn-sm btn-info" title="Detail">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                </div>
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
                            <input type="number" name="jumlah_bayar" class="form-control" min="1" value="">
                        </div>

                        <div class="mb-3">
                            <label>Metode Bayar</label>
                            <select name="metode_bayar" class="form-control" required>
                                <option value="tunai">Tunai</option>
                                <option value="transfer">Transfer</option>
                                <option value="e-wallet">E-Wallet</option>
                            </select>
                        </div>

                        <div hidden class="mb-3">
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

    <?php include_once '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalBayar = new bootstrap.Modal(document.getElementById('modalBayar'));

        // Dynamic dropdown karyawan berdasarkan jenis
        const jenisSelect = document.getElementById('jenisKaryawanSelect');
        const karyawanSelect = document.getElementById('karyawanSelect');

        jenisSelect.addEventListener('change', function() {
            const selectedJenis = this.value;
            const options = karyawanSelect.querySelectorAll('option');

            options.forEach(option => {
                if (option.value === "0") {
                    option.style.display = '';
                } else {
                    const optionJenis = option.getAttribute('data-jenis');
                    if (selectedJenis === 'all' || optionJenis === selectedJenis) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                }
            });

            // Reset ke semua karyawan
            karyawanSelect.value = "0";
        });

        // Modal pembayaran
        document.querySelectorAll('.btn-bayar').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const nama = this.dataset.nama;
                const sisa = this.dataset.sisa;

                document.getElementById('bayar_id_hutang').value = id;
                document.getElementById('bayar_nama_karyawan').value = nama;
                document.getElementById('bayar_sisa_hutang').value = formatRupiah(sisa);

                // Set max value untuk input jumlah bayar
                const jumlahInput = document.querySelector('input[name="jumlah_bayar"]');
                jumlahInput.max = sisa;
                jumlahInput.value = ''; // Default isi dengan sisa hutang

                modalBayar.show();
            });
        });

        function formatRupiah(amount) {
            return 'Rp ' + Number(amount).toLocaleString('id-ID');
        }
    });
</script>

</html>