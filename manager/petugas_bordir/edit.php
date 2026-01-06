<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

require_once '../includes/header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data bordir
$sql = "SELECT * FROM bordir WHERE id_bordir = $id";
$bordir = query($sql);
if (empty($bordir)) {
    header("Location: list.php");
    exit();
}
$bordir = $bordir[0];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_bordir = $conn->real_escape_string($_POST['nama_bordir']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $telepon = $conn->real_escape_string($_POST['telepon']);
    $keterangan = $conn->real_escape_string($_POST['keterangan']);

    $sql = "UPDATE bordir SET 
            nama_bordir = '$nama_bordir',
            alamat = '$alamat',
            telepon = '$telepon',
            keterangan = '$keterangan'
            WHERE id_bordir = $id";

    if ($conn->query($sql)) {
        $_SESSION['success'] = "Data bordir berhasil diupdate";
        header("Location: list.php");
        exit();
    } else {
        $error = "Gagal update data bordir: " . $conn->error;
    }
}
?>

<!-- [Body] Start -->

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    <!-- Sidebar Start -->
    <?php include_once '../includes/sidebar.php'; ?>
    <!-- Sidebar End -->

    <?php include_once '../includes/navbar.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">

            <!-- [ Main Content ] start -->
            <div class="row">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Edit Data Bordir</h2>
                </div>

                <!-- Tampilkan pesan error -->
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card p-4 shadow-sm">

                    <form method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nama_bordir" class="form-label">Nama Bordir <span class="text-danger">*</span></label>
                                <input type="text" id="nama_bordir" name="nama_bordir" class="form-control"
                                    value="<?= htmlspecialchars($bordir['nama_bordir']) ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="telepon" class="form-label">Telepon</label>
                                <input type="text" id="telepon" name="telepon" class="form-control"
                                    value="<?= htmlspecialchars($bordir['telepon']) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea id="alamat" name="alamat" class="form-control" rows="3"><?= htmlspecialchars($bordir['alamat']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan</label>
                            <textarea id="keterangan" name="keterangan" class="form-control" rows="3"><?= htmlspecialchars($bordir['keterangan']) ?></textarea>
                        </div>

                        <div class="">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-file-plus"></i> Simpan Perubahan
                            </button>
                            <a href="<?= $base_url ?>/manager/master_data.php" class="btn btn-secondary">
                                <i class="ti ti-arrow-back"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

</html>