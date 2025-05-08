<?php
require_once '../../config/auth.php';
require_role('admin');

$title = "Manajemen Invoice";

require_once '../../config/database.php';

// Ambil data invoice
$invoices = $pdo->query("
    SELECT i.*, t.customer_nama, t.customer_kontak 
    FROM invoices i
    JOIN transactions t ON i.transaction_id = t.id
    ORDER BY i.tanggal_invoice DESC
")->fetchAll();
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

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <div class="col-md-12 text-end">
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">Filter Status</button>
                            <ul class="dropdown-menu mt-2" aria-labelledby="filterDropdown">
                                <li><a class="dropdown-item" href="?status=all">Semua</a></li>
                                <li><a class="dropdown-item" href="?status=lunas">Lunas</a></li>
                                <li><a class="dropdown-item" href="?status=cicilan">Cicilan</a></li>
                                <li><a class="dropdown-item" href="?status=belum_lunas">Belum Lunas</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body"></div>
                    <div class="table-responsive">
                        <table class="table table-striped border table-hover" id="invoicesTable">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th>No</th>
                                    <th>No. Invoice</th>
                                    <th>Tanggal</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Dibayar</th>
                                    <th>Sisa</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $index => $invoice):
                                    $status_class = '';
                                    if ($invoice['status_bayar'] === 'lunas') {
                                        $status_class = 'success';
                                    } elseif ($invoice['status_bayar'] === 'cicilan') {
                                        $status_class = 'warning';
                                    } else {
                                        $status_class = 'danger';
                                    }
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($invoice['no_invoice']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($invoice['tanggal_invoice'])) ?></td>
                                        <td>
                                            <?= htmlspecialchars($invoice['customer_nama'] ?? '---'); ?><br>
                                            <!-- <small class="text-muted"><?= htmlspecialchars($invoice['customer_kontak'] ?? '') ?></small> -->
                                            <small class="text-muted"><?= htmlspecialchars($invoice['customer_kontak'] ?? ''); ?></small>
                                        </td>
                                        <td>Rp <?= number_format($invoice['total'], 0, ',', '.') ?></td>
                                        <td>Rp <?= number_format($invoice['dibayar'], 0, ',', '.') ?></td>
                                        <td>Rp <?= number_format($invoice['sisa'], 0, ',', '.') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $status_class ?>">
                                                <?= ucfirst(str_replace('_', ' ', $invoice['status_bayar'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($invoice['status_bayar'] !== 'lunas'): ?>
                                                <a href="installments.php?id=<?= $invoice['id'] ?>" class="btn m-1 btn-sm btn-warning" title="Angsuran">
                                                    <i class="bi bi-cash"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="view.php?id=<?= $invoice['id'] ?>" class="btn m-1 btn-sm btn-info" title="Lihat">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="print.php?id=<?= $invoice['id'] ?>" class="btn m-1 btn-sm btn-primary" title="Cetak" target="_blank">
                                                <i class="bi bi-printer"></i>
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