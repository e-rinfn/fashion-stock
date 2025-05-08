<?php
require_once '../../config/auth.php';
require_role('admin');

$title = "Tambah Produk Mukena";

require_once '../../config/database.php';
require_once '../../functions/product_functions.php';

// Ambil data kategori dan supplier untuk dropdown
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'kode_barang' => generate_product_code(),
        'nama' => $_POST['nama'],
        'category_id' => $_POST['category_id'],
        'harga_beli' => str_replace('.', '', $_POST['harga_beli']),
        'harga_jual' => str_replace('.', '', $_POST['harga_jual']),
        'stok' => $_POST['stok'],
        'supplier_id' => $_POST['supplier_id'],
        'deskripsi' => $_POST['deskripsi']
    ];

    if (add_product($data)) {
        $_SESSION['success'] = 'Produk mukena berhasil ditambahkan!';
        header('Location: index.php');
        exit;
    } else {
        $error = 'Gagal menambahkan produk mukena';
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
                <h3>TAMBAH PRODUK</h3>
            </div>
            <!-- Content title end -->

            <!-- Content Start -->
            <div class="page-content">
                <section class="row">
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="nama" class="form-label">Nama Mukena</label>
                                <input type="text" class="form-control" id="nama" name="nama" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Kategori</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['nama']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="supplier_id" class="form-label">Supplier</label>
                                    <select class="form-select" id="supplier_id" name="supplier_id">
                                        <option value="">Pilih Supplier (opsional)</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['nama']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="harga_beli" class="form-label">Harga Beli</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" class="form-control harga-input" id="harga_beli" name="harga_beli" required>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="harga_jual" class="form-label">Harga Jual</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" class="form-control harga-input" id="harga_jual" name="harga_jual" required>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="stok" class="form-label">Stok Awal</label>
                                    <input type="number" class="form-control" id="stok" name="stok" min="0" value="0" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="deskripsi" class="form-label">Deskripsi Produk</label>
                                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary me-md-2">Kembali</a>
                                <button type="submit" class="btn btn-primary">Simpan</button>
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