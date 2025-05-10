<?php
require_once '../../../config/auth.php';
require_role('admin');

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$transaction_id = $_GET['id'];
$title = "Detail Penjualan";

// Ambil data transaksi
require_once '../../../config/database.php';
$stmt = $pdo->prepare("SELECT t.*, u.full_name, i.no_invoice, i.status_bayar, i.metode_bayar
                      FROM transactions t
                      JOIN users u ON t.user_id = u.id
                      LEFT JOIN invoices i ON t.id = i.transaction_id
                      WHERE t.id = ?");
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    $_SESSION['error'] = "Transaksi tidak ditemukan";
    header('Location: index.php');
    exit;
}

// Ambil detail transaksi
$stmt = $pdo->prepare("SELECT td.*, p.nama as product_name, p.kode_barang
                      FROM transaction_details td
                      JOIN products p ON td.product_id = p.id
                      WHERE td.transaction_id = ?");
$stmt->execute([$transaction_id]);
$details = $stmt->fetchAll();

// Ambil data angsuran jika kredit
$installments = [];
if ($transaction['metode_bayar'] === 'kredit') {
    $stmt = $pdo->prepare("SELECT * FROM installments WHERE invoice_id = 
                          (SELECT id FROM invoices WHERE transaction_id = ?) 
                          ORDER BY tanggal_jatuh_tempo");
    $stmt->execute([$transaction_id]);
    $installments = $stmt->fetchAll();
}
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
                <h3>DETAIL TRANSAKSI</h3>
            </div>
            <!-- Content title end -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <!-- Content Start -->

            <div class="page-content">
                <div class="row">
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
                </div>
            </div>
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

                <?php if (!empty($installments)): ?>
                    <div class="card">
                        <div class="card-header">Jadwal Angsuran</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr class="text-center">
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
                                                <td class="text-center"><?= $index + 1 ?></td>
                                                <td>Rp <?= number_format($angsuran['jumlah'], 0, ',', '.') ?></td>
                                                <td><?= date('d/m/Y', strtotime($angsuran['tanggal_jatuh_tempo'])) ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?= $angsuran['status'] === 'lunas' ? 'success' : 'warning' ?>">
                                                        <?= strtoupper(str_replace('_', ' ', $angsuran['status'])) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <?= $angsuran['tanggal_bayar'] ? date('d/m/Y', strtotime($angsuran['tanggal_bayar'])) : '-' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div>
                                <a href="index.php" class="btn btn-secondary">Kembali</a>
                                <a href="print.php?id=<?= $transaction_id ?>" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-print"></i> Cetak
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

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