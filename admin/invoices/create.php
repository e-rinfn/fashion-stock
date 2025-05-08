<?php
require_once '../../config/auth.php';
require_role('admin');

$title = "Buat Invoice Baru";

// Ambil daftar transaksi penjualan yang belum memiliki invoice
require_once '../../config/database.php';
$stmt = $pdo->prepare("
    SELECT t.id, t.kode_transaksi, t.tanggal, t.customer_nama, SUM(td.subtotal) as total
    FROM transactions t
    JOIN transaction_details td ON t.id = td.transaction_id
    LEFT JOIN invoices i ON t.id = i.transaction_id
    WHERE t.tipe = 'keluar' AND i.id IS NULL
    GROUP BY t.id
");
$stmt->execute();
$transactions = $stmt->fetchAll();
?>


<!-- Head start -->
<?php include '../../includes/head.php'; ?>
<!-- Head end -->

<body>
    <div id="app">

        <!-- Sidebar start -->
        <?php include '../../includes/side.php'; ?>
        <!-- sidebar end -->

        <!-- main content start -->
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

            <!-- Content title start -->
            <div class="page-heading">
                <h3>KELOLA ANGSURAN</h3>
            </div>
            <!-- Content title end -->

            <!-- Content Start -->

            <div class="page-content">

                <section class="row">
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>

                        <?php if (empty($transactions)): ?>
                            <div class="alert alert-info">Tidak ada transaksi penjualan yang membutuhkan invoice.</div>
                        <?php else: ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Kode Transaksi</th>
                                        <th>Tanggal</th>
                                        <th>Customer</th>
                                        <th>Total</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($transaction['kode_transaksi']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($transaction['tanggal'])) ?></td>
                                            <td><?= htmlspecialchars($transaction['customer_nama']) ?: '-' ?></td>
                                            <td>Rp <?= number_format($transaction['total'], 0, ',', '.') ?></td>
                                            <td>
                                                <a href="view.php?transaction_id=<?= $transaction['id'] ?>" class="btn btn-sm btn-info">Detail</a>
                                                <a href="#" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createModal<?= $transaction['id'] ?>">Buat Invoice</a>

                                                <!-- Modal untuk buat invoice -->
                                                <div class="modal fade" id="createModal<?= $transaction['id'] ?>" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form action="../../functions/create_invoice.php" method="POST">
                                                                <input type="hidden" name="transaction_id" value="<?= $transaction['id'] ?>">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="createModalLabel">Buat Invoice untuk Transaksi <?= $transaction['kode_transaksi'] ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-close="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label for="metode_bayar" class="form-label">Metode Pembayaran</label>
                                                                        <select class="form-select" id="metode_bayar" name="metode_bayar" required>
                                                                            <option value="tunai">Tunai</option>
                                                                            <option value="transfer">Transfer</option>
                                                                            <option value="kredit">Kredit/Angsuran</option>
                                                                        </select>
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label for="dibayar" class="form-label">Jumlah Dibayar</label>
                                                                        <input type="number" class="form-control" id="dibayar" name="dibayar"
                                                                            value="<?= $transaction['total'] ?>" min="0" max="<?= $transaction['total'] ?>" required>
                                                                    </div>

                                                                    <div id="angsuranFields" style="display: none;">
                                                                        <div class="mb-3">
                                                                            <label for="jatuh_tempo" class="form-label">Tanggal Jatuh Tempo</label>
                                                                            <input type="date" class="form-control" id="jatuh_tempo" name="jatuh_tempo"
                                                                                min="<?= date('Y-m-d') ?>">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                    <button type="submit" class="btn btn-primary">Simpan Invoice</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                </section>
            </div>
        </div>
        <!-- Content End -->
    </div>
    <!-- main content end -->

    </div>


    <script>
        // Tampilkan field angsuran jika metode kredit dipilih
        document.getElementById('metode_bayar').addEventListener('change', function() {
            const angsuranFields = document.getElementById('angsuranFields');
            if (this.value === 'kredit') {
                angsuranFields.style.display = 'block';
            } else {
                angsuranFields.style.display = 'none';
            }
        });
    </script>


    <script src="../../assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>

    <script src="../../assets/vendors/apexcharts/apexcharts.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>

    <script src="../../assets/js/main.js"></script>
</body>

</html>