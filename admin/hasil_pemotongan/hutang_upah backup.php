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

<div class="pc-container">
    <div class="pc-content">
        <div class="row">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Management Hutang Upah</h2>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
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

<?php include_once '../includes/footer.php'; ?>