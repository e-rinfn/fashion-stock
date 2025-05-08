<?php
require_once '../../config/auth.php';
require_role('admin');

require_once '../../config/database.php';

// Ambil data supplier berdasarkan ID
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    $_SESSION['error_message'] = "Supplier tidak ditemukan";
    header('Location: index.php');
    exit;
}

$title = "Edit Supplier: " . htmlspecialchars($supplier['nama']);

// Proses form edit supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $alamat = $_POST['alamat'];
    $kontak = $_POST['kontak'];
    $email = $_POST['email'];

    // Validasi input
    $errors = [];
    if (empty($nama)) $errors[] = "Nama supplier harus diisi";
    if (empty($kontak)) $errors[] = "Kontak supplier harus diisi";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE suppliers SET nama = ?, alamat = ?, kontak = ?, email = ? WHERE id = ?");
            $stmt->execute([$nama, $alamat, $kontak, $email, $id]);

            $_SESSION['success'] = "Data supplier berhasil diperbarui";
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Gagal memperbarui supplier: " . $e->getMessage();
        }
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
                <h3>DAFTAR SUPPLIER</h3>
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
                            <div class="mb-3">
                                <label for="nama" class="form-label">Nama Supplier</label>
                                <input type="text" class="form-control" id="nama" name="nama"
                                    value="<?= htmlspecialchars($supplier['nama']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="alamat" class="form-label">Alamat</label>
                                <textarea class="form-control" id="alamat" name="alamat" rows="3"><?=
                                                                                                    htmlspecialchars($supplier['alamat'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="kontak" class="form-label">Kontak (No. HP/Telepon)</label>
                                <input type="text" class="form-control" id="kontak" name="kontak"
                                    value="<?= htmlspecialchars($supplier['kontak']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?= htmlspecialchars($supplier['email'] ?? '') ?>">
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