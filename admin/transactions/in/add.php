<?php
require_once '../../../config/auth.php';
require_role('admin');

$title = "Tambah Barang Masuk";

// Ambil data supplier dan produk
require_once '../../../config/database.php';
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY nama")->fetchAll();
$products = $pdo->query("SELECT * FROM products ORDER BY nama")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert transaksi
        $stmt = $pdo->prepare("INSERT INTO transactions (kode_transaksi, tipe, tanggal, user_id, supplier_id, total) 
                              VALUES (?, 'masuk', NOW(), ?, ?, ?)");
        $kode_transaksi = 'TRX-IN-' . date('YmdHis');
        $stmt->execute([
            $kode_transaksi,
            $_SESSION['user_id'],
            $_POST['supplier_id'],
            0 // Total sementara 0
        ]);
        $transaction_id = $pdo->lastInsertId();

        // Insert detail transaksi
        $total_transaksi = 0;
        foreach ($_POST['products'] as $product) {
            $product_id = $product['id'];
            $quantity = $product['quantity'];
            $harga_beli = $product['harga_beli'];

            $stmt = $pdo->prepare("INSERT INTO transaction_details 
                                  (transaction_id, product_id, quantity, harga_satuan, subtotal) 
                                  VALUES (?, ?, ?, ?, ?)");
            $subtotal = $quantity * $harga_beli;
            $stmt->execute([$transaction_id, $product_id, $quantity, $harga_beli, $subtotal]);

            $total_transaksi += $subtotal;
        }

        // Update total transaksi
        $pdo->prepare("UPDATE transactions SET total = ? WHERE id = ?")
            ->execute([$total_transaksi, $transaction_id]);

        $pdo->commit();

        $_SESSION['success'] = "Transaksi barang masuk berhasil dicatat";
        header("Location: ../in/?id=" . $transaction_id);
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Gagal mencatat transaksi: " . $e->getMessage();
    }
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
                <h3>TAMBAH TRANSAKSI</h3>
            </div>
            <!-- Content title end -->

            <!-- Content Start -->

            <div class="page-content">

                <section class="row">
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="card mb-4">
                                <div class="card-header">Informasi Transaksi</div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="supplier_id" class="form-label">Supplier</label>
                                                <select class="form-select" id="supplier_id" name="supplier_id" required>
                                                    <option value="">Pilih Supplier</option>
                                                    <?php foreach ($suppliers as $supplier): ?>
                                                        <option value="<?= $supplier['id'] ?>">
                                                            <?= htmlspecialchars($supplier['nama']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Tanggal</label>
                                                <input type="text" class="form-control" value="<?= date('d/m/Y H:i') ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-4">
                                <div class="card-header">Daftar Barang</div>
                                <div class="card-body">
                                    <div id="product-list">
                                        <!-- Barang akan ditambahkan di sini via JavaScript -->
                                    </div>

                                    <button type="button" class="btn btn-secondary mt-3" id="add-product">
                                        <i class="fas fa-plus"></i> Tambah Barang
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="../in/" class="btn btn-secondary">Kembali</a>
                                <button type="submit" class="btn btn-primary">Simpan Transaksi</button>
                            </div>
                        </form>
                    </div>

                </section>
            </div>
        </div>
        <!-- Content End -->
    </div>
    <!-- main content end -->

    </div>

    <!-- Template untuk row produk (digunakan oleh JavaScript) -->
    <template id="product-template">
        <div class="product-row mb-3 border p-3">
            <div class="row">
                <div class="col-md-5">
                    <select class="form-select product-select" name="products[0][id]" required>
                        <option value="">Pilih Produk</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>"
                                data-harga="<?= $product['harga_beli'] ?>">
                                <?= htmlspecialchars($product['nama']) ?>
                                (<?= number_format($product['harga_beli'], 0, ',', '.') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control quantity" name="products[0][quantity]" required>
                    min="1" value="1" required>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control harga" name="products[0][harga_beli]" required
                        step="100" min="0" required>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm remove-product">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productList = document.getElementById('product-list');
            const addButton = document.getElementById('add-product');
            const template = document.getElementById('product-template');

            // Tambah produk pertama saat load
            addProductRow();

            // Event untuk tambah produk
            addButton.addEventListener('click', addProductRow);

            // Event untuk hapus produk
            productList.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-product')) {
                    e.target.closest('.product-row').remove();
                    updateProductIndexes();
                }
            });

            // Event untuk update harga saat produk dipilih
            productList.addEventListener('change', function(e) {
                if (e.target.classList.contains('product-select')) {
                    const selectedOption = e.target.options[e.target.selectedIndex];
                    const harga = selectedOption.dataset.harga;
                    const hargaInput = e.target.closest('.product-row').querySelector('.harga');
                    if (harga && hargaInput) {
                        hargaInput.value = harga;
                    }
                }
            });

            function addProductRow() {
                const clone = template.content.cloneNode(true);
                productList.appendChild(clone);
                updateProductIndexes();
            }

            function updateProductIndexes() {
                const rows = productList.querySelectorAll('.product-row');
                rows.forEach((row, index) => {
                    // Update nama input untuk array PHP
                    const inputs = row.querySelectorAll('select, input');
                    inputs.forEach(input => {
                        const name = input.name.replace(/products\[\d*\]/, `products[${index}]`);
                        input.name = name;
                    });
                });
            }
        });
    </script>


    <script>
        // Tampilkan field angsuran jika metode kredit dipilih
        document.getElementById('metode_bayar').addEventListener('change', function() {
            const angsuranFields = document.getElementById('angsuranFields');
            if (this.value === 'kredit') {
                angsuranFields.style.display = 'block';
            } else {
                angsuranFields.style.display = 'none';
            }
        });
    </script>


    <script src="../../../assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>

    <script src="../../../assets/vendors/apexcharts/apexcharts.js"></script>
    <script src="../../../assets/js/pages/dashboard.js"></script>

    <script src="../../../assets/js/main.js"></script>
</body>

</html>