<?php
require_once '../../config/auth.php';
require_role('owner');

$title = "Laporan Stok";
include '../../includes/header.php';

require_once '../../config/database.php';

// Filter kategori jika ada
$category_filter = '';
if (isset($_GET['category_id']) && !empty($_GET['category_id'])) {
    $category_filter = "WHERE p.category_id = " . intval($_GET['category_id']);
}

// Query untuk data stok
$stmt = $pdo->query("
    SELECT p.*, c.nama as category_name, s.nama as supplier_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    $category_filter
    ORDER BY p.stok ASC, p.nama ASC
");
$products = $stmt->fetchAll();

// Query untuk dropdown kategori
$categories = $pdo->query("SELECT * FROM categories ORDER BY nama")->fetchAll();
?>

<div class="container py-4">
    <h1 class="mb-4">Laporan Stok</h1>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="category_id" class="form-label">Filter Kategori</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= isset($_GET['category_id']) && $_GET['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="stock.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Data Stok Produk</h5>
            <div>
                <a href="export_stock.php?category_id=<?= $_GET['category_id'] ?? '' ?>" class="btn btn-sm btn-success">Export Excel</a>
                <button class="btn btn-sm btn-warning" onclick="window.print()">Cetak</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th>Supplier</th>
                            <th>Harga Beli</th>
                            <th>Harga Jual</th>
                            <th>Stok</th>
                            <th>Nilai Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product):
                            $stock_value = $product['harga_beli'] * $product['stok'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($product['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($product['nama']) ?></td>
                                <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($product['supplier_name'] ?? '-') ?></td>
                                <td>Rp <?= number_format($product['harga_beli'], 0, ',', '.') ?></td>
                                <td>Rp <?= number_format($product['harga_jual'], 0, ',', '.') ?></td>
                                <td>
                                    <span class="badge <?= $product['stok'] < 10 ? 'bg-danger' : ($product['stok'] < 20 ? 'bg-warning' : 'bg-success') ?>">
                                        <?= $product['stok'] ?>
                                    </span>
                                </td>
                                <td>Rp <?= number_format($stock_value, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="6">Total Nilai Stok</th>
                            <th><?= array_sum(array_column($products, 'stok')) ?></th>
                            <th>Rp <?= number_format(array_sum(array_map(function ($p) {
                                        return $p['harga_beli'] * $p['stok'];
                                    }, $products)), 0, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>