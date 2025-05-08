<?php
require_once '../../../config/auth.php';
require_role('admin');

$title = "Daftar Penjualan";

// Ambil data transaksi penjualan
require_once '../../../config/database.php';
$stmt = $pdo->prepare("SELECT t.*, u.full_name, i.no_invoice 
                      FROM transactions t
                      JOIN users u ON t.user_id = u.id
                      LEFT JOIN invoices i ON t.id = i.transaction_id
                      WHERE t.tipe = 'keluar'
                      ORDER BY t.tanggal DESC");
$stmt->execute();
$transactions = $stmt->fetchAll();
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
                <h3>JUDUL HALAMAN</h3>
            </div>
            <!-- Content title end -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <!-- Content Start -->

            <div class="page-content">

                <section class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">Informasi Transaksi</div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">No. Transaksi</th>
                                        <td><?= htmlspecialchars($transaction['kode_transaksi']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal</th>
                                        <td><?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Customer</th>
                                        <td><?= htmlspecialchars($transaction['customer_nama'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Kontak</th>
                                        <td><?= htmlspecialchars($transaction['customer_kontak'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Admin</th>
                                        <td><?= htmlspecialchars($transaction['full_name']) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">Informasi Pembayaran</div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">No. Invoice</th>
                                        <td><?= $transaction['no_invoice'] ?? '-' ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total</th>
                                        <td>Rp <?= number_format($transaction['total'], 0, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Metode Bayar</th>
                                        <td>
                                            <?= strtoupper($transaction['metode_bayar']) ?>
                                            (<?= strtoupper(str_replace('_', ' ', $transaction['status_bayar'])) ?>)
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                </section>
                <div class="card mb-4">
                    <div class="card-header">Daftar Barang</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama Barang</th>
                                        <th>Harga</th>
                                        <th>Qty</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($details as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['kode_barang']) ?></td>
                                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td>Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td>Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Total</th>
                                        <th>Rp <?= number_format($transaction['total'], 0, ',', '.') ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if (!empty($installments)): ?>
                    <div class="card">
                        <div class="card-header">Jadwal Angsuran</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Jumlah</th>
                                            <th>Jatuh Tempo</th>
                                            <th>Status</th>
                                            <th>Tanggal Bayar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($installments as $index => $angsuran): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td>Rp <?= number_format($angsuran['jumlah'], 0, ',', '.') ?></td>
                                                <td><?= date('d/m/Y', strtotime($angsuran['tanggal_jatuh_tempo'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $angsuran['status'] === 'lunas' ? 'success' : 'warning' ?>">
                                                        <?= strtoupper(str_replace('_', ' ', $angsuran['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= $angsuran['tanggal_bayar'] ? date('d/m/Y', strtotime($angsuran['tanggal_bayar'])) : '-' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <!-- Content End -->
    </div>
    <!-- main content end -->

    </div>


    <script src="../../../assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>

    <script src="../../../assets/vendors/apexcharts/apexcharts.js"></script>
    <script src="../../../assets/js/pages/dashboard.js"></script>

    <script src="../../../assets/js/main.js"></script>
</body>

</html>