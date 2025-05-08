<?php
require_once '../../config/auth.php';
require_role('owner');

$title = "Laporan Pembelian";
include '../../includes/header.php';

// Tanggal default: bulan ini
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

// Filter tanggal
if (isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}
if (isset($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

require_once '../../config/database.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Laporan Pembelian</h1>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="purchases.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Data Pembelian</h5>
            <a href="export_purchases.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-sm btn-success">Export Excel</a>
        </div>
        <div class="card-body">
            <?php
            $stmt = $pdo->prepare("
                SELECT t.*, s.nama as supplier_name, u.full_name as admin_name, 
                       COUNT(td.id) as item_count, SUM(td.quantity) as total_quantity
                FROM transactions t
                JOIN suppliers s ON t.supplier_id = s.id
                JOIN users u ON t.user_id = u.id
                JOIN transaction_details td ON t.id = td.transaction_id
                WHERE t.tipe = 'masuk' AND t.tanggal BETWEEN ? AND ?
                GROUP BY t.id
                ORDER BY t.tanggal DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $purchases = $stmt->fetchAll();

            if (count($purchases) > 0):
            ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>No. Transaksi</th>
                                <th>Tanggal</th>
                                <th>Supplier</th>
                                <th>Jumlah Item</th>
                                <th>Total Kuantitas</th>
                                <th>Total Pembelian</th>
                                <th>Admin</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td><?= htmlspecialchars($purchase['kode_transaksi']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($purchase['tanggal'])) ?></td>
                                    <td><?= htmlspecialchars($purchase['supplier_name']) ?></td>
                                    <td><?= $purchase['item_count'] ?></td>
                                    <td><?= $purchase['total_quantity'] ?></td>
                                    <td>Rp <?= number_format($purchase['total'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($purchase['admin_name']) ?></td>
                                    <td>
                                        <a href="../transactions/in/detail.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-info">Detail</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4">Total</th>
                                <th><?= array_sum(array_column($purchases, 'total_quantity')) ?></th>
                                <th>Rp <?= number_format(array_sum(array_column($purchases, 'total')), 0, ',', '.') ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Tidak ada data pembelian pada periode ini.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>