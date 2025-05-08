<?php
require_once '../../config/auth.php';
require_role('admin');

$title = "Kelola Pembayaran Angsuran";

require_once '../../config/database.php';

// Ambil daftar invoice dengan status cicilan
$stmt = $pdo->prepare("
    SELECT i.id, i.no_invoice, i.tanggal_invoice, i.total, i.dibayar, i.sisa, 
           t.customer_nama, t.customer_kontak, COUNT(ins.id) as jumlah_angsuran
    FROM invoices i
    JOIN transactions t ON i.transaction_id = t.id
    LEFT JOIN installments ins ON i.id = ins.invoice_id
    WHERE i.status_bayar = 'cicilan' OR (i.status_bayar = 'belum_lunas' AND i.metode_bayar = 'kredit')
    GROUP BY i.id
    ORDER BY i.tanggal_invoice DESC
");
$stmt->execute();
$invoices = $stmt->fetchAll();
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

                        <?php if (empty($invoices)): ?>
                            <div class="alert alert-info">Tidak ada invoice dengan pembayaran angsuran.</div>
                        <?php else: ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr class="text-center">
                                        <th>No. Invoice</th>
                                        <th>Tanggal</th>
                                        <th>Customer</th>
                                        <th>Total</th>
                                        <th>Dibayar</th>
                                        <th>Sisa</th>
                                        <th>Angsuran</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($invoice['no_invoice']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($invoice['tanggal_invoice'])) ?></td>
                                            <td><?= htmlspecialchars($invoice['customer_nama'] ?: '-') ?></td>
                                            <td>Rp <?= number_format($invoice['total'], 0, ',', '.') ?></td>
                                            <td>Rp <?= number_format($invoice['dibayar'], 0, ',', '.') ?></td>
                                            <td>Rp <?= number_format($invoice['sisa'], 0, ',', '.') ?></td>
                                            <td><?= $invoice['jumlah_angsuran'] ?></td>
                                            <td>
                                                <a href="view.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-info">Detail</a>
                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addInstallmentModal<?= $invoice['id'] ?>">
                                                    Tambah Angsuran
                                                </button>

                                                <!-- Modal Tambah Angsuran -->
                                                <div class="modal fade" id="addInstallmentModal<?= $invoice['id'] ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form action="../../functions/add_installment.php" method="POST">
                                                                <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Tambah Angsuran untuk <?= $invoice['no_invoice'] ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-close="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label for="amount" class="form-label">Jumlah Angsuran</label>
                                                                        <input type="number" class="form-control" id="amount" name="amount"
                                                                            min="1" max="<?= $invoice['sisa'] ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label for="due_date" class="form-label">Tanggal Jatuh Tempo</label>
                                                                        <input type="date" class="form-control" id="due_date" name="due_date"
                                                                            min="<?= date('Y-m-d') ?>" required>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-close="modal">Batal</button>
                                                                    <button type="submit" class="btn btn-primary">Simpan Angsuran</button>
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


    <script src="../../assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>

    <script src="../../assets/vendors/apexcharts/apexcharts.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>

    <script src="../../assets/js/main.js"></script>
</body>

</html>