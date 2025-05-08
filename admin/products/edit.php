<?php
require_once '../../config/auth.php';
require_role('admin');

$title = "Edit Produk Mukena";

require_once '../../config/database.php';
require_once '../../functions/product_functions.php';

// Ambil data produk berdasarkan ID
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

$product = get_product_by_id($id);
if (!$product) {
    $_SESSION['error'] = 'Produk tidak ditemukan!';
    header('Location: index.php');
    exit;
}

// Ambil data kategori dan supplier untuk dropdown
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'id' => $id,
        'nama' => $_POST['nama'],
        'category_id' => $_POST['category_id'],
        'harga_beli' => str_replace('.', '', $_POST['harga_beli']),
        'harga_jual' => str_replace('.', '', $_POST['harga_jual']),
        'stok' => $_POST['stok'],
        'supplier_id' => $_POST['supplier_id'],
        'deskripsi' => $_POST['deskripsi']
    ];

    if (update_product($data)) {
        $_SESSION['success'] = 'Produk mukena berhasil diperbarui!';
        header('Location: index.php');
        exit;
    } else {
        $error = 'Gagal memperbarui produk mukena';
    }
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
                <h3>EDIT PRODUK</h3>
            </div>
            <!-- Content title end -->

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <!-- Content Start -->
            <div class="page-content">
                <section class="row">
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="kode_barang" class="form-label">Kode Barang</label>
                                <input type="text" class="form-control" id="kode_barang" value="<?= htmlspecialchars($product['kode_barang']) ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="nama" class="form-label">Nama Mukena</label>
                                <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($product['nama']) ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Kategori</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>" <?= $category['id'] == $product['category_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['nama']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="supplier_id" class="form-label">Supplier</label>
                                    <select class="form-select" id="supplier_id" name="supplier_id">
                                        <option value="">Pilih Supplier (opsional)</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?= $supplier['id'] ?>" <?= $supplier['id'] == $product['supplier_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($supplier['nama']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="harga_beli" class="form-label">Harga Beli</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" class="form-control harga-input" id="harga_beli" name="harga_beli" value="<?= number_format($product['harga_beli'], 0, ',', '.') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="harga_jual" class="form-label">Harga Jual</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" class="form-control harga-input" id="harga_jual" name="harga_jual" value="<?= number_format($product['harga_jual'], 0, ',', '.') ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="stok" class="form-label">Stok</label>
                                <input type="number" class="form-control" id="stok" name="stok" min="0" value="<?= $product['stok'] ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="deskripsi" class="form-label">Deskripsi Produk</label>
                                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?= htmlspecialchars($product['deskripsi']) ?></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary me-md-2">Kembali</a>
                                <button type="submit" class="btn btn-warning">Perbarui</button>
                            </div>
                        </form>
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