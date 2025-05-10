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


            <div class="col-md-12 text-end">
                <a href="add.php" class="btn btn-primary">Tambah Penjualan</a>
            </div>

            <div class="page-content">



                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped border table-hover" id="suppliersTable">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th>No</th>
                                    <th>No. Transaksi</th>
                                    <th>Tanggal</th>
                                    <th>Customer</th>
                                    <th>Invoice</th>
                                    <th>Total</th>
                                    <th>Admin</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody> <?php $no = 1;
                                    foreach ($transactions as $trx): ?> <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($trx['kode_transaksi']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($trx['tanggal'])) ?></td>
                                        <td><?= htmlspecialchars($trx['customer_nama'] ?? '-') ?></td>
                                        <td><?= $trx['no_invoice'] ?? '-' ?></td>
                                        <td>Rp <?= number_format($trx['total'], 0, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($trx['full_name']) ?></td>
                                        <td> <a href="detail.php?id=<?= $trx['id'] ?>" class="btn btn-sm btn-info"> <i class="bi bi-eye"></i> </a> </td>
                                    </tr> <?php endforeach; ?> </tbody>
                        </table>
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