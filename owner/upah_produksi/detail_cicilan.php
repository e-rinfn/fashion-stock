<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Cek parameter
$id_akumulasi = isset($_GET['id_akumulasi']) ? intval($_GET['id_akumulasi']) : 0;

if ($id_akumulasi <= 0) {
    $_SESSION['error'] = "ID akumulasi tidak valid";
    header("Location: hutang_akumulasi.php");
    exit();
}

// Ambil data akumulasi
$sql = "SELECT h.*, 
               CASE 
                   WHEN h.jenis_karyawan = 'pemotong' THEN p.nama_pemotong 
                   ELSE j.nama_penjahit 
               END as nama_karyawan
        FROM hutang_upah h
        LEFT JOIN pemotong p ON h.jenis_karyawan = 'pemotong' AND h.id_karyawan = p.id_pemotong
        LEFT JOIN penjahit j ON h.jenis_karyawan = 'penjahit' AND h.id_karyawan = j.id_penjahit
        WHERE h.id_hutang = ? AND h.is_accumulated = TRUE";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_akumulasi);
$stmt->execute();
$result = $stmt->get_result();
$akumulasi = $result->fetch_assoc();

if (!$akumulasi) {
    $_SESSION['error'] = "Data akumulasi tidak ditemukan";
    header("Location: hutang_akumulasi.php");
    exit();
}

// Ambil riwayat cicilan
$sql_cicilan = "SELECT * FROM pembayaran_upah_2 
                WHERE id_hutang = ? 
                ORDER BY tanggal_bayar DESC, id_pembayaran DESC";
$stmt_cicilan = $conn->prepare($sql_cicilan);
$stmt_cicilan->bind_param("i", $id_akumulasi);
$stmt_cicilan->execute();
$result_cicilan = $stmt_cicilan->get_result();
$riwayat_cicilan = $result_cicilan->fetch_all(MYSQLI_ASSOC);

// Proses input cicilan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_cicilan'])) {
    $tanggal_bayar = $_POST['tanggal_bayar'];
    $jumlah_bayar = floatval($_POST['jumlah_bayar']);
    $metode_bayar = $_POST['metode_bayar'];
    $keterangan = $_POST['keterangan'] ?? '';

    // Validasi
    if ($jumlah_bayar <= 0) {
        $_SESSION['error'] = "Jumlah cicilan harus lebih dari 0";
    } elseif ($jumlah_bayar > $akumulasi['sisa_hutang']) {
        $_SESSION['error'] = "Jumlah cicilan tidak boleh melebihi sisa hutang (Rp " . number_format($akumulasi['sisa_hutang'], 0, ',', '.') . ")";
    } else {
        // Mulai transaksi
        $conn->begin_transaction();

        try {
            // 1. Update hutang akumulasi
            $new_sisa = $akumulasi['sisa_hutang'] - $jumlah_bayar;
            $new_dibayar = $akumulasi['total_dibayar'] + $jumlah_bayar;

            $sql_update = "UPDATE hutang_upah 
                          SET total_dibayar = ?, 
                              sisa_hutang = ?,
                              updated_at = NOW()
                          WHERE id_hutang = ?";

            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ddi", $new_dibayar, $new_sisa, $id_akumulasi);
            $stmt_update->execute();

            // 2. Catat pembayaran/cicilan
            $sql_payment = "INSERT INTO pembayaran_upah_2 
                           (id_hutang, tanggal_bayar, jumlah_bayar, metode_bayar, keterangan, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())";

            $stmt_payment = $conn->prepare($sql_payment);
            $stmt_payment->bind_param("isdss", $id_akumulasi, $tanggal_bayar, $jumlah_bayar, $metode_bayar, $keterangan);
            $stmt_payment->execute();

            // 3. Update juga hutang per bulan yang sudah diakumulasi (jika perlu)
            // ... kode untuk update hutang per bulan ...

            $conn->commit();

            $_SESSION['success'] = "Cicilan berhasil dicatat! Sisa hutang: Rp " . number_format($new_sisa, 0, ',', '.');
            header("Location: input_cicilan.php?id_akumulasi=" . $id_akumulasi);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Gagal mencatat cicilan: " . $e->getMessage();
        }
    }
}
?>

<style>
    .card-summary {
        border-left: 4px solid #6f42c1;
        background-color: #f8f9ff;
    }

    .card-cicilan {
        border-left: 4px solid #28a745;
        background-color: #f0fff4;
    }

    .card-riwayat {
        border-left: 4px solid #17a2b8;
        background-color: #f0f9ff;
    }

    .btn-submit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 10px 30px;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .metode-badge {
        font-size: 0.8rem;
        padding: 3px 8px;
    }

    .metode-tunai {
        background-color: #28a745;
        color: white;
    }

    .metode-transfer {
        background-color: #17a2b8;
        color: white;
    }

    .metode-wallet {
        background-color: #ffc107;
        color: black;
    }
</style>

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
    <?php include_once '../includes/sidebar.php'; ?>
    <?php include_once '../includes/navbar.php'; ?>

    <div class="pc-container">
        <div class="pc-content">
            <div class="row">
                <!-- Breadcrumb -->
                <div class="col-12 mb-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="hutang_akumulasi.php">Hutang Akumulasi</a></li>
                            <li class="breadcrumb-item active">Input Cicilan</li>
                        </ol>
                    </nav>
                </div>

                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Input Cicilan Hutang Akumulasi</h2>
                        <a href="hutang_akumulasi.php" class="btn btn-outline-secondary">
                            <i class="ti ti-arrow-left me-2"></i>Kembali
                        </a>
                    </div>

                    <!-- Pesan Error/Success -->
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

                    <div class="row">
                        <!-- Kolom 1: Ringkasan Akumulasi -->
                        <div class="col-md-4 mb-4">
                            <div class="card card-summary h-100">
                                <div class="card-header bg-purple text-white">
                                    <h5 class="mb-0">Ringkasan Akumulasi</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6 class="text-muted">Karyawan</h6>
                                        <h4><?= htmlspecialchars($akumulasi['nama_karyawan']) ?></h4>
                                        <span class="badge bg-<?= $akumulasi['jenis_karyawan'] == 'pemotong' ? 'warning' : 'info' ?>">
                                            <?= ucfirst($akumulasi['jenis_karyawan']) ?>
                                        </span>
                                    </div>

                                    <div class="mb-3">
                                        <h6 class="text-muted">Periode Akumulasi</h6>
                                        <h5><?= date('F Y', strtotime($akumulasi['periode'])) ?></h5>
                                    </div>

                                    <div class="mb-3">
                                        <h6 class="text-muted">Total Hutang</h6>
                                        <h3 class="text-primary">Rp <?= number_format($akumulasi['total_upah'], 0, ',', '.') ?></h3>
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <h6 class="text-muted">Sudah Dibayar</h6>
                                            <h5 class="text-success">Rp <?= number_format($akumulasi['total_dibayar'], 0, ',', '.') ?></h5>
                                        </div>
                                        <div class="col-6">
                                            <h6 class="text-muted">Sisa Hutang</h6>
                                            <h5 class="<?= $akumulasi['sisa_hutang'] > 0 ? 'text-danger' : 'text-success' ?>">
                                                Rp <?= number_format($akumulasi['sisa_hutang'], 0, ',', '.') ?>
                                            </h5>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <div class="progress" style="height: 10px;">
                                            <?php
                                            $persentase = $akumulasi['total_upah'] > 0 ? ($akumulasi['total_dibayar'] / $akumulasi['total_upah']) * 100 : 0;
                                            $progress_color = $persentase >= 100 ? 'bg-success' : ($persentase >= 50 ? 'bg-warning' : 'bg-danger');
                                            ?>
                                            <div class="progress-bar <?= $progress_color ?>"
                                                role="progressbar"
                                                style="width: <?= min($persentase, 100) ?>%"
                                                aria-valuenow="<?= $persentase ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted mt-1 d-block text-center">
                                            <?= number_format($persentase, 1) ?>% sudah dibayar
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom 2: Form Input Cicilan -->
                        <div class="col-md-4 mb-4">
                            <div class="card card-cicilan h-100">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">Input Cicilan Baru</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="formCicilan">
                                        <div class="mb-3">
                                            <label class="form-label">Tanggal Bayar *</label>
                                            <input type="date"
                                                name="tanggal_bayar"
                                                class="form-control"
                                                value="<?= date('Y-m-d') ?>"
                                                required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Jumlah Cicilan *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rp</span>
                                                <input type="number"
                                                    name="jumlah_bayar"
                                                    class="form-control"
                                                    min="1"
                                                    max="<?= $akumulasi['sisa_hutang'] ?>"
                                                    step="1000"
                                                    value="<?= min(50000, $akumulasi['sisa_hutang']) ?>"
                                                    required>
                                            </div>
                                            <small class="text-muted">
                                                Maksimal: Rp <?= number_format($akumulasi['sisa_hutang'], 0, ',', '.') ?>
                                            </small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Metode Pembayaran *</label>
                                            <select name="metode_bayar" class="form-select" required>
                                                <option value="tunai">Tunai</option>
                                                <option value="transfer">Transfer Bank</option>
                                                <option value="e-wallet">E-Wallet</option>
                                                <option value="lainnya">Lainnya</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Keterangan (Opsional)</label>
                                            <textarea name="keterangan"
                                                class="form-control"
                                                rows="3"
                                                placeholder="Contoh: Cicilan ke-1, Transfer dari BCA, dll."></textarea>
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit"
                                                name="simpan_cicilan"
                                                class="btn btn-submit"
                                                <?= $akumulasi['sisa_hutang'] <= 0 ? 'disabled' : '' ?>>
                                                <i class="ti ti-check me-2"></i>
                                                Simpan Cicilan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom 3: Riwayat Cicilan -->
                        <div class="col-md-4 mb-4">
                            <div class="card card-riwayat h-100">
                                <div class="card-header bg-info text-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Riwayat Cicilan</h5>
                                        <span class="badge bg-light text-dark">
                                            <?= count($riwayat_cicilan) ?>x cicilan
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <?php if (empty($riwayat_cicilan)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="ti ti-receipt" style="font-size: 3rem; opacity: 0.5;"></i>
                                            <p class="mt-2">Belum ada cicilan</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($riwayat_cicilan as $cicilan): ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1">Rp <?= number_format($cicilan['jumlah_bayar'], 0, ',', '.') ?></h6>
                                                            <small class="text-muted">
                                                                <?= date('d/m/Y', strtotime($cicilan['tanggal_bayar'])) ?>
                                                            </small>
                                                        </div>
                                                        <div class="text-end">
                                                            <?php
                                                            $metode_class = '';
                                                            switch ($cicilan['metode_bayar']) {
                                                                case 'tunai':
                                                                    $metode_class = 'metode-tunai';
                                                                    break;
                                                                case 'transfer':
                                                                    $metode_class = 'metode-transfer';
                                                                    break;
                                                                case 'e-wallet':
                                                                    $metode_class = 'metode-wallet';
                                                                    break;
                                                                default:
                                                                    $metode_class = 'bg-secondary';
                                                            }
                                                            ?>
                                                            <span class="badge <?= $metode_class ?> metode-badge">
                                                                <?= ucfirst($cicilan['metode_bayar']) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($cicilan['keterangan'])): ?>
                                                        <small class="text-muted mt-1 d-block">
                                                            <i class="ti ti-note me-1"></i>
                                                            <?= htmlspecialchars($cicilan['keterangan']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
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

    <?php include_once '../includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Format input jumlah cicilan
            const jumlahInput = document.querySelector('input[name="jumlah_bayar"]');

            jumlahInput.addEventListener('input', function() {
                const max = parseFloat(this.max);
                const value = parseFloat(this.value);

                if (value > max) {
                    this.value = max;
                    alert('Jumlah cicilan tidak boleh melebihi sisa hutang!');
                }
            });

            // Validasi form sebelum submit
            document.getElementById('formCicilan').addEventListener('submit', function(e) {
                const jumlah = parseFloat(jumlahInput.value);
                const sisaHutang = parseFloat(jumlahInput.max);

                if (jumlah <= 0) {
                    e.preventDefault();
                    alert('Jumlah cicilan harus lebih dari 0');
                    return false;
                }

                if (jumlah > sisaHutang) {
                    e.preventDefault();
                    alert('Jumlah cicilan tidak boleh melebihi sisa hutang');
                    return false;
                }

                return confirm('Yakin ingin menyimpan cicilan ini?');
            });
        });
    </script>
</body>

</html>