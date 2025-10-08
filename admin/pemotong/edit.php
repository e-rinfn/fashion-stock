<?php
require_once '../includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$id = $conn->real_escape_string($_GET['id']);
$sql = "SELECT * FROM pemotong WHERE id_pemotong = '$id'";
$result = $conn->query($sql);
$pemotong = $result->fetch_assoc();

if (!$pemotong) {
    header("Location: list.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $conn->real_escape_string($_POST['nama_pemotong']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $kontak = $conn->real_escape_string($_POST['kontak']);

    $sql = "UPDATE pemotong SET 
            nama_pemotong = '$nama',
            alamat = '$alamat',
            kontak = '$kontak'
            WHERE id_pemotong = '$id'";

    if ($conn->query($sql)) {
        $_SESSION['success'] = "Data pemotong berhasil diperbarui";
        header("Location: list.php");
        exit();
    } else {
        $error = "Gagal memperbarui data pemotong: " . $conn->error;
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
                    <h2>Edit Data Pemotong</h2>
                </div>

                <div class="card p-4 shadow-sm">

                    <form method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nama_pemotong" class="form-label">Nama Pemotong</label>
                                <input type="text" class="form-control" id="nama_pemotong" name="nama_pemotong"
                                    value="<?= htmlspecialchars($pemotong['nama_pemotong']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="kontak" class="form-label">Kontak</label>
                                <input type="text" class="form-control" id="kontak" name="kontak"
                                    value="<?= htmlspecialchars($pemotong['kontak']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= htmlspecialchars($pemotong['alamat']); ?></textarea>
                        </div>


                        <div class="">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-file-plus"></i> Simpan
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