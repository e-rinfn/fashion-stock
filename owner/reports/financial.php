<?php
require_once '../../config/auth.php';
require_role('owner');

$title = "Laporan Keuangan";
include '../../includes/header.php';

// Default: bulan ini
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

if (isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}
if (isset($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

require_once '../../config/database.php';

// Query untuk pendapatan (penjualan)
$income_stmt = $pdo->prepare("
    SELECT SUM(total) as total_income
    FROM transactions
    WHERE tipe = 'keluar' AND tanggal BETWEEN ? AND ?
");
$income_stmt->execute([$start_date, $end_date]);
$total_income = $income_stmt->fetchColumn();

// Query untuk pengeluaran (pembelian)
$expense_stmt = $pdo->prepare("
    SELECT SUM(total) as total_expense
    FROM transactions
    WHERE tipe = 'masuk' AND tanggal BETWEEN ? AND ?
");
$expense_stmt->execute([$start_date, $end_date]);
$total_expense = $expense_stmt->fetchColumn();

// Query untuk penjualan per kategori
$category_sales = $pdo->prepare("
    SELECT c.nama as category_name, SUM(td.quantity) as total_quantity, SUM(td.subtotal) as total_sales
    FROM transaction_details td
    JOIN transactions t ON td.transaction_id = t.id
    JOIN products p ON td.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE t.tipe = 'keluar' AND t.tanggal BETWEEN ? AND ?
    GROUP BY p.category_id
    ORDER BY total_sales DESC
");
$category_sales->execute([$start_date, $end_date]);
?>

<div class="container py-4">
    <h1 class="mb-4">Laporan Keuangan</h1>

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
                    <a href="financial.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title">Total Pendapatan</h5>
                    <p class="card-text display-4">Rp <?= number_format($total_income, 0, ',', '.') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5 class="card-title">Total Pengeluaran</h5>
                    <p class="card-text display-4">Rp <?= number_format($total_expense, 0, ',', '.') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Laba Rugi</h5>
            <?php
            $profit = $total_income - $total_expense;
            $profit_class = $profit >= 0 ? 'text-success' : 'text-danger';
            ?>
            <p class="display-3 <?= $profit_class ?>">
                Rp <?= number_format($profit, 0, ',', '.') ?>
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Penjualan per Kategori</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Jumlah Terjual</th>
                            <th>Total Penjualan</th>
                            <th>Persentase</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_sales_all = $category_sales->rowCount() > 0 ?
                            array_sum(array_column($category_sales->fetchAll(PDO::FETCH_ASSOC), 'total_sales')) : 0;
                        $category_sales->execute([$start_date, $end_date]); // Execute again for display

                        foreach ($category_sales as $sale):
                            $percentage = $total_sales_all > 0 ? ($sale['total_sales'] / $total_sales_all) * 100 : 0;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($sale['category_name'] ?? 'Tanpa Kategori') ?></td>
                                <td><?= $sale['total_quantity'] ?></td>
                                <td>Rp <?= number_format($sale['total_sales'], 0, ',', '.') ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%"
                                            aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?= round($percentage, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>