<?php

include_once __DIR__ . '/../../config/config.php';
require_once '../includes/header.php';

// Cek apakah user sudah login
if (!isLoggedIn()) {
    header("Location: {$base_url}auth/login.php");
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: {$base_url}/auth/role_tidak_cocok.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$id = $conn->real_escape_string($_GET['id']);
$sql = "SELECT * FROM bahan_baku WHERE id_bahan = '$id'";
$result = $conn->query($sql);
$bahan = $result->fetch_assoc();

if (!$bahan) {
    header("Location: list.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $conn->real_escape_string($_POST['nama_bahan']);
    $stok = $conn->real_escape_string($_POST['jumlah_stok']);
    $satuan = $conn->real_escape_string($_POST['satuan']);
    $jumlah_meter = $conn->real_escape_string($_POST['jumlah_meter']);
    $harga = $conn->real_escape_string($_POST['harga_per_satuan']);

    $sql = "UPDATE bahan_baku SET 
            nama_bahan = '$nama',
            jumlah_stok = '$stok',
            satuan = '$satuan',
            jumlah_meter = '$jumlah_meter',
            harga_per_satuan = '$harga'
            WHERE id_bahan = '$id'";

    if ($conn->query($sql)) {
        $_SESSION['success'] = "Bahan baku berhasil diperbarui";
        header("Location: list.php");
        exit();
    } else {
        $error = "Gagal memperbarui bahan baku: " . $conn->error;
    }
}
?>

<style>
    /* Paksa SweetAlert berada di atas segalanya */
    .swal2-container {
        z-index: 99999 !important;
    }
</style>

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
                    <h2>Ubah Data Bahan Baku</h2>
                </div>


                <div class="card p-4 shadow-sm">

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error; ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label for="nama_bahan" class="form-label">Nama Bahan</label>
                            <input type="text" id="nama_bahan" name="nama_bahan" class="form-control" value="<?= htmlspecialchars($bahan['nama_bahan']); ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="jumlah_stok" class="form-label">Jumlah Stok</label>
                                <input type="number" step="1" id="jumlah_stok" name="jumlah_stok" class="form-control" value="<?= number_format($bahan['jumlah_stok']); ?>" required>
                            </div>

                            <div class="col-md-2 mb-3">
                                <label for="satuan" class="form-label">Satuan</label>
                                <input type="text" id="satuan" name="satuan" class="form-control" value="<?= htmlspecialchars($bahan['satuan']); ?>" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="jumlah_meter" class="form-label">Jumlah Meter</label>
                                <div class="input-group">
                                    <input type="number" step="1" id="jumlah_meter" name="jumlah_meter" class="form-control" value="<?= number_format($bahan['jumlah_meter']); ?>" required>
                                    <span class="input-group-text">Meter</span>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="harga_per_satuan" class="form-label">Harga per Meter</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" id="harga_per_satuan" step="500" name="harga_per_satuan" class="form-control" value="<?= $bahan['harga_per_satuan']; ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-file-plus"></i> Simpan Perubahan
                            </button>
                            <a href="list.php" class="btn btn-secondary">
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