<?php
require_once '../../config/auth.php';
require_role('admin');

$title = "Laporan Stok Mukena";

require_once '../../config/database.php';

// Ambil data stok produk
$products = $pdo->query("
    SELECT p.*, c.nama as kategori 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.stok ASC, p.nama ASC
")->fetchAll();
?>

<div class="container py-4">
    <div class="row justify-content-between mb-4">
        <div class="col-md-6">
            <h1><i class="fas fa-boxes"></i> Laporan Stok Mukena</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="export_stock.php" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="stockTable">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Kode</th>
                            <th>Nama Mukena</th>
                            <th>Kategori</th>
                            <th>Harga Beli</th>
                            <th>Harga Jual</th>
                            <th>Stok</th>
                            <th>Nilai Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $index => $product):
                            $stock_value = $product['harga_beli'] * $product['stok'];
                            $stock_class = '';
                            if ($product['stok'] > 20) {
                                $stock_class = 'table-success';
                            } elseif ($product['stok'] <= 5) {
                                $stock_class = 'table-danger';
                            } elseif ($product['stok'] <= 10) {
                                $stock_class = 'table-warning';
                            }
                        ?>
                            <tr class="<?= $stock_class ?>">
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($product['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($product['nama']) ?></td>
                                <td><?= htmlspecialchars($product['kategori'] ?? '-') ?></td>
                                <td>Rp <?= number_format($product['harga_beli'], 0, ',', '.') ?></td>
                                <td>Rp <?= number_format($product['harga_jual'], 0, ',', '.') ?></td>
                                <td><?= $product['stok'] ?></td>
                                <td>Rp <?= number_format($stock_value, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-dark">
                            <th colspan="7">Total Nilai Stok</th>
                            <th>
                                <?php
                                $total_stock_value = array_reduce($products, function ($carry, $item) {
                                    return $carry + ($item['harga_beli'] * $item['stok']);
                                }, 0);
                                ?>
                                Rp <?= number_format($total_stock_value, 0, ',', '.') ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>