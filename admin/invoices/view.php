<?php
require_once '../../config/auth.php';
require_role('admin');

if (!isset($_GET['id']) && !isset($_GET['transaction_id'])) {
    header('Location: view.php');
    exit;
}

$title = "Detail Invoice";

require_once '../../config/database.php';

// Ambil data invoice
if (isset($_GET['id'])) {
    $invoice_id = $_GET['id'];
    $stmt = $pdo->prepare("
        SELECT i.*, t.kode_transaksi, t.tanggal, t.customer_nama, t.customer_kontak, u.full_name as admin
        FROM invoices i
        JOIN transactions t ON i.transaction_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        $_SESSION['error'] = "Invoice tidak ditemukan";
        header('Location: create.php');
        exit;
    }
} else {
    $transaction_id = $_GET['transaction_id'];
    $stmt = $pdo->prepare("
        SELECT t.*, SUM(td.subtotal) as total, u.full_name as admin
        FROM transactions t
        JOIN transaction_details td ON t.id = td.transaction_id
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
        GROUP BY t.id
    ");
    $stmt->execute([$transaction_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        $_SESSION['error'] = "Transaksi tidak ditemukan";
        header('Location: create.php');
        exit;
    }

    // Format data untuk transaksi yang belum punya invoice
    $invoice['no_invoice'] = 'BELUM DIBUAT';
    $invoice['status_bayar'] = 'belum_lunas';
    $invoice['dibayar'] = 0;
    $invoice['sisa'] = $invoice['total'];
    $invoice['metode_bayar'] = '';
}

// Ambil detail transaksi
$stmt = $pdo->prepare("
    SELECT td.*, p.nama as product_name, p.kode_barang
    FROM transaction_details td
    JOIN products p ON td.product_id = p.id
    WHERE td.transaction_id = ?
");
$stmt->execute([$invoice['transaction_id'] ?? $invoice['id']]);
$details = $stmt->fetchAll();

// Jika sudah ada invoice, ambil data angsuran
$installments = [];
if (isset($invoice_id)) {
    $stmt = $pdo->prepare("SELECT * FROM installments WHERE invoice_id = ? ORDER BY tanggal_jatuh_tempo");
    $stmt->execute([$invoice_id]);
    $installments = $stmt->fetchAll();
}
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
                <h3>DAFTAR NOTA INVOICE</h3>
            </div>
            <!-- Content title end -->

            <!-- Content Start -->

            <div class="page-content">

                <section class="row">
                    <!-- Menampilkan pesan error -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $_SESSION['error'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <!-- Menampilkan pesan sukses -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $_SESSION['success'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Informasi Invoice</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <th width="40%">No. Invoice</th>
                                            <td><?= htmlspecialchars($invoice['no_invoice']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Kode Transaksi</th>
                                            <td><?= htmlspecialchars($invoice['kode_transaksi']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal</th>
                                            <td><?= date('d/m/Y', strtotime($invoice['tanggal'])) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Customer</th>
                                            <td><?= htmlspecialchars($invoice['customer_nama'] ?: '-') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Kontak</th>
                                            <td><?= htmlspecialchars($invoice['customer_kontak'] ?: '-') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Admin</th>
                                            <td><?= htmlspecialchars($invoice['admin']) ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Pembayaran</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <th width="40%">Total Tagihan</th>
                                            <td>Rp <?= number_format($invoice['total'], 0, ',', '.') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Dibayar</th>
                                            <td>Rp <?= number_format($invoice['dibayar'], 0, ',', '.') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Sisa</th>
                                            <td>Rp <?= number_format($invoice['sisa'], 0, ',', '.') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'lunas' => 'success',
                                                    'cicilan' => 'warning',
                                                    'belum_lunas' => 'danger'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $status_class[$invoice['status_bayar']] ?>">
                                                    <?= strtoupper(str_replace('_', ' ', $invoice['status_bayar'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Metode Bayar</th>
                                            <td><?= strtoupper($invoice['metode_bayar'] ?: '-') ?></td>
                                        </tr>
                                    </table>

                                    <?php if (!isset($_GET['id'])): ?>
                                        <a href="create.php" class="btn btn-primary">Buat Invoice</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Detail Produk</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Produk</th>
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
                                        <th>Rp <?= number_format($invoice['total'], 0, ',', '.') ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <?php if (!empty($installments)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Riwayat Angsuran</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Jumlah</th>
                                            <th>Jatuh Tempo</th>
                                            <th>Tanggal Bayar</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($installments as $index => $installment): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td>Rp <?= number_format($installment['jumlah'], 0, ',', '.') ?></td>
                                                <td><?= date('d/m/Y', strtotime($installment['tanggal_jatuh_tempo'])) ?></td>
                                                <td>
                                                    <?= $installment['tanggal_bayar'] ? date('d/m/Y', strtotime($installment['tanggal_bayar'])) : '-' ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = [
                                                        'lunas' => 'success',
                                                        'belum_lunas' => 'warning',
                                                        'terlambat' => 'danger'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $status_class[$installment['status']] ?>">
                                                        <?= strtoupper(str_replace('_', ' ', $installment['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($installment['status'] === 'belum_lunas' || $installment['status'] === 'terlambat'): ?>
                                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payModal<?= $installment['id'] ?>">
                                                            <i class="fas fa-money-bill-wave"></i> Bayar
                                                        </button>

                                                        <!-- Modal Pembayaran Angsuran -->
                                                        <div class="modal fade" id="payModal<?= $installment['id'] ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <form action="../../functions/pay_installment.php" method="POST" enctype="multipart/form-data"> <input type="hidden" name="installment_id" value="<?= $installment['id'] ?>">
                                                                        <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">Pembayaran Angsuran #<?= $index + 1 ?></h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="mb-3">
                                                                                <label class="form-label">Jumlah</label>
                                                                                <input type="text" class="form-control" value="Rp <?= number_format($installment['jumlah'], 0, ',', '.') ?>" readonly>
                                                                            </div>
                                                                            <div class="mb-3">
                                                                                <label for="payment_date" class="form-label">Tanggal Pembayaran</label>
                                                                                <input type="date" class="form-control" id="payment_date" name="payment_date"
                                                                                    value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                                                                            </div>
                                                                            <div class="mb-3">
                                                                                <label for="payment_method" class="form-label">Metode Pembayaran</label>
                                                                                <select class="form-select" id="payment_method" name="payment_method" required>
                                                                                    <option value="tunai">Tunai</option>
                                                                                    <option value="transfer">Transfer</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="mb-3">
                                                                                <label for="payment_proof" class="form-label">Bukti Pembayaran (Opsional)</label>
                                                                                <input type="file" class="form-control" id="payment_proof" name="payment_proof">
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                            <button type="submit" class="btn btn-primary">
                                                                                <i class="fas fa-save"></i> Simpan Pembayaran
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <a href="index.php" class="btn btn-secondary">Kembali</a>
                        <?php if (isset($invoice_id)): ?>
                            <a href="print.php?id=<?= $invoice_id ?>" target="_blank" class="btn btn-primary">Cetak Invoice</a>
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