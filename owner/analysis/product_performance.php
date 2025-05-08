<?php
require_once '../../config/auth.php';
require_role('owner');

$title = "Analisis Kinerja Produk";
include '../../includes/header.php';

// Default: 3 bulan terakhir
$start_date = date('Y-m-01', strtotime('-2 months'));
$end_date = date('Y-m-t');

if (isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}
if (isset($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

require_once '../../config/database.php';

// Query untuk top produk
$top_products = $pdo->prepare("
    SELECT 
        p.id,
        p.nama,
        p.kode_barang,
        c.nama as category,
        SUM(td.quantity) as total_sold,
        SUM(td.subtotal) as total_revenue,
        COUNT(DISTINCT t.id) as transaction_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN transaction_details td ON p.id = td.product_id
    LEFT JOIN transactions t ON td.transaction_id = t.id AND t.tipe = 'keluar' AND t.tanggal BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10
");
$top_products->execute([$start_date, $end_date]);

// Query untuk produk tidak laku
$unsold_products = $pdo->prepare("
    SELECT p.*, c.nama as category
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id NOT IN (
        SELECT DISTINCT td.product_id
        FROM transaction_details td
        JOIN transactions t ON td.transaction_id = t.id
        WHERE t.tipe = 'keluar' AND t.tanggal BETWEEN ? AND ?
    )
    ORDER BY p.stok DESC
");
$unsold_products->execute([$start_date, $end_date]);
?>

<div class="container py-4">
    <h1 class="mb-4">Analisis Kinerja Produk</h1>

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
                    <a href="product_performance.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">10 Produk Terlaris</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Kode</th>
                                    <th>Nama Produk</th>
                                    <th>Kategori</th>
                                    <th>Terjual</th>
                                    <th>Pendapatan</th>
                                    <th>Transaksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rank = 1;
                                foreach ($top_products as $product):
                                ?>
                                    <tr>
                                        <td><?= $rank++ ?></td>
                                        <td><?= htmlspecialchars($product['kode_barang']) ?></td>
                                        <td><?= htmlspecialchars($product['nama']) ?></td>
                                        <td><?= htmlspecialchars($product['category'] ?? '-') ?></td>
                                        <td><?= $product['total_sold'] ?? 0 ?></td>
                                        <td>Rp <?= number_format($product['total_revenue'] ?? 0, 0, ',', '.') ?></td>
                                        <td><?= $product['transaction_count'] ?? 0 ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Statistik Produk</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Query untuk statistik produk
                    $product_stats = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_products,
                            SUM(CASE WHEN p.stok <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                            SUM(CASE WHEN p.stok > 0 AND p.stok < 10 THEN 1 ELSE 0 END) as low_stock,
                            SUM(CASE WHEN p.id NOT IN (
                                SELECT DISTINCT td.product_id
                                FROM transaction_details td
                                JOIN transactions t ON td.transaction_id = t.id
                                WHERE t.tipe = 'keluar' AND t.tanggal BETWEEN ? AND ?
                            ) THEN 1 ELSE 0 END) as unsold_products
                        FROM products p
                    ");
                    $product_stats->execute([$start_date, $end_date]);
                    $stats = $product_stats->fetch();
                    ?>
                    <div class="mb-3">
                        <h6>Total Produk</h6>
                        <p class="display-4"><?= $stats['total_products'] ?></p>
                    </div>
                    <div class="mb-3">
                        <h6>Produk Habis</h6>
                        <p class="display-4 text-danger"><?= $stats['out_of_stock'] ?></p>
                    </div>
                    <div class="mb-3">
                        <h6>Stok Rendah (<10)< /h6>
                                <p class="display-4 text-warning"><?= $stats['low_stock'] ?></p>
                    </div>

                    <div class="mb-3">
                        <h6>Produk Tidak Terjual</h6>
                        <p class="display-4 text-muted"><?= $stats['unsold_products'] ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Produk Tidak Terjual</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Kode</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th>Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $index = 1;
                        foreach ($unsold_products as $product):
                        ?>
                            <tr>
                                <td><?= $index++ ?></td>
                                <td><?= htmlspecialchars($product['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($product['nama']) ?></td>
                                <td><?= htmlspecialchars($product['category'] ?? '-') ?></td>
                                <td><?= $product['stok'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($index === 1): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Tidak ada produk tidak terjual dalam periode ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>