<?php
require_once '../../../config/auth.php';
require_role('admin');

$title = "Barang Masuk";

require_once '../../../config/database.php';
$transactions = $pdo->query("
    SELECT t.*, s.nama as supplier_nama, u.full_name as user_name 
    FROM transactions t
    LEFT JOIN suppliers s ON t.supplier_id = s.id
    JOIN users u ON t.user_id = u.id
    WHERE t.tipe = 'masuk'
    ORDER BY t.tanggal DESC
")->fetchAll();
?>


<!-- Head start -->
<?php include '../../../includes/head.php'; ?>
<!-- Head end -->

<body>
    <div id="app">

        <!-- Sidebar start -->
        <?php include '../../../includes/side.php'; ?>
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
                <h3>DAFTAR BARANG MASUK</h3>
            </div>
            <!-- Content title end -->

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Content Start -->
            <div class="col-md-12 text-end">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Barang Masuk
                </a>
            </div>
            <div class="page-content">

                <section class="row">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped border table-hover" id="transactionsTable">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th>#</th>
                                        <th>Kode Transaksi</th>
                                        <th>Tanggal</th>
                                        <th>Supplier</th>
                                        <th>Total</th>
                                        <th>Input Oleh</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $index => $transaction): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($transaction['kode_transaksi']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?></td>
                                            <td><?= htmlspecialchars($transaction['supplier_nama'] ?? 'Produksi Sendiri') ?></td>
                                            <td>Rp <?= number_format($transaction['total'], 0, ',', '.') ?></td>
                                            <td><?= htmlspecialchars($transaction['user_name']) ?></td>
                                            <td>
                                                <a href="#" class="btn btn-sm btn-info" title="Detail" data-bs-toggle="modal" data-bs-target="#detailModal<?= $transaction['id'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </section>
            </div>
        </div>
        <!-- Content End -->
    </div>
    <!-- main content end -->

    </div>

    <!-- Modal untuk detail transaksi -->
    <?php foreach ($transactions as $transaction): ?>
        <div class="modal fade" id="detailModal<?= $transaction['id'] ?>" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="detailModalLabel">Detail Transaksi #<?= $transaction['kode_transaksi'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Tanggal:</strong> <?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?></p>
                                <p><strong>Supplier:</strong> <?= htmlspecialchars($transaction['supplier_nama'] ?? 'Produksi Sendiri') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total:</strong> Rp <?= number_format($transaction['total'], 0, ',', '.') ?></p>
                                <p><strong>Input oleh:</strong> <?= htmlspecialchars($transaction['user_name']) ?></p>
                            </div>
                        </div>

                        <h6>Detail Barang:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nama Barang</th>
                                        <th>Qty</th>
                                        <th>Harga Satuan</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $details = $pdo->query("
                                SELECT td.*, p.nama as product_name 
                                FROM transaction_details td
                                JOIN products p ON td.product_id = p.id
                                WHERE td.transaction_id = {$transaction['id']}
                            ")->fetchAll();

                                    foreach ($details as $detail):
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($detail['product_name']) ?></td>
                                            <td><?= $detail['quantity'] ?></td>
                                            <td>Rp <?= number_format($detail['harga_satuan'], 0, ',', '.') ?></td>
                                            <td>Rp <?= number_format($detail['subtotal'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>


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


    <script src="../../../assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>

    <script src="../../../assets/vendors/apexcharts/apexcharts.js"></script>
    <script src="../../../assets/js/pages/dashboard.js"></script>

    <script src="../../../assets/js/main.js"></script>
</body>

</html>