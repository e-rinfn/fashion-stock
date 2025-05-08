<?php
require_once '../../config/auth.php';
require_role('admin');

$title = "Manajemen Produk Mukena";

// Ambil data produk dari database
require_once '../../config/database.php';
require_once '../../functions/product_functions.php';

$products = get_all_products();
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
                <h3>DAFTAR PRODUK</h3>
            </div>
            <!-- Content title end -->

            <!-- Content Start -->
            <div class="page-content">
                <section class="row">

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


                    <div class="col-md-12 text-end">
                        <a href="add.php" class="btn btn-primary">Tambah Produk</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped border table-hover" id="productsTable">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Kode</th>
                                        <th>Nama Mukena</th>
                                        <th>Kategori</th>
                                        <th>Harga</th>
                                        <th>Stok</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $index => $product): ?>
                                        <tr>
                                            <td class="text-center"><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($product['kode_barang']) ?></td>
                                            <td><?= htmlspecialchars($product['nama']) ?></td>
                                            <td><?= htmlspecialchars($product['kategori'] ?? '-') ?></td>
                                            <td>Rp <?= number_format($product['harga_jual'], 0, ',', '.') ?></td>
                                            <td>
                                                <span class="badge <?= $product['stok'] > 10 ? 'bg-success' : 'bg-warning' ?>">
                                                    <?= $product['stok'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <a href="#" class="btn btn-sm btn-danger btn-delete"
                                                    data-id="<?= $product['id'] ?>"
                                                    data-nama="<?= htmlspecialchars($product['nama']) ?>"
                                                    title="Hapus">
                                                    <i class="bi bi-trash"></i>
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

    <!-- Sweet alert start -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const deleteButtons = document.querySelectorAll(".btn-delete");

            deleteButtons.forEach(btn => {
                btn.addEventListener("click", function(e) {
                    e.preventDefault();

                    const id = this.getAttribute("data-id");
                    const nama = this.getAttribute("data-nama");

                    Swal.fire({
                        title: `Hapus produk "${nama}"?`,
                        text: "Data yang dihapus tidak bisa dikembalikan!",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#d33",
                        cancelButtonColor: "#6c757d",
                        confirmButtonText: "Ya, hapus!",
                        cancelButtonText: "Batal"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `delete.php?id=${id}`;
                        }
                    });
                });
            });
        });
    </script>

    <!-- Sweet alert end -->


    <script src="../../assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>

    <script src="../../assets/vendors/apexcharts/apexcharts.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>

    <script src="../../assets/js/main.js"></script>
</body>

</html>