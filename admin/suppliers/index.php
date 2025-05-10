<?php
require_once '../../config/auth.php';
require_role('admin');

$title = "Manajemen Supplier Mukena";

require_once '../../config/database.php';
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY nama ASC")->fetchAll();
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
                <h3>DAFTAR SUPPLIER</h3>
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
                        <a href="add.php" class="btn btn-primary">Tambah Supplier</a>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped border table-hover" id="suppliersTable">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Nama Supplier</th>
                                        <th>Kontak</th>
                                        <th>Email</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suppliers as $index => $supplier): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($supplier['nama']) ?></td>
                                            <td><?= htmlspecialchars($supplier['kontak']) ?></td>
                                            <td><?= htmlspecialchars($supplier['email'] ?? '-') ?></td>
                                            <td class="text-center">
                                                <a href="edit.php?id=<?= $supplier['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <!-- Tombol Delete -->
                                                <button class="btn btn-sm btn-danger btn-delete"
                                                    data-id="<?= $supplier['id'] ?>"
                                                    data-name="<?= htmlspecialchars($supplier['nama']) ?>"
                                                    title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>

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
    <!-- Sweetalert start -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".btn-delete").forEach(function(button) {
                button.addEventListener("click", function() {
                    const supplierId = this.getAttribute("data-id");
                    const supplierName = this.getAttribute("data-name");

                    Swal.fire({
                        title: `Hapus supplier "${supplierName}"?`,
                        text: "Data akan dihapus secara permanen!",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#d33",
                        cancelButtonColor: "#6c757d",
                        confirmButtonText: "Ya, hapus!",
                        cancelButtonText: "Batal"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `delete.php?id=${supplierId}`;
                        }
                    });
                });
            });
        });
    </script>
    <!-- Sweetalert end -->


    <script src="../../assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>

    <script src="../../assets/vendors/apexcharts/apexcharts.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>

    <script src="../../assets/js/main.js"></script>
</body>

</html>