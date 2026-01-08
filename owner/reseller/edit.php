<?php

$page_title = "UBAH DATA RESELLER";

require_once '../../config/database.php';
require_once '../../config/functions.php';


require_once '../includes/header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data reseller
$sql = "SELECT * FROM reseller WHERE id_reseller = $id";
$reseller = query($sql);
if (empty($reseller)) {
    header("Location: {$base_url}/owner/master_data.php");
    exit();
}
$reseller = $reseller[0];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $conn->real_escape_string($_POST['nama']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $kontak = $conn->real_escape_string($_POST['kontak']);
    $tanggal = $conn->real_escape_string($_POST['tanggal']);

    $sql = "UPDATE reseller SET 
            nama_reseller = '$nama',
            alamat = '$alamat',
            kontak = '$kontak',
            tanggal_bergabung = '$tanggal'
            WHERE id_reseller = $id";

    if ($conn->query($sql)) {
        $_SESSION['success'] = "Data reseller berhasil diupdate";
        header("Location: {$base_url}/owner/master_data.php");
        exit();
    } else {
        $error = "Gagal update data: " . $conn->error;
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

                <div class="card p-4 shadow-sm">

                    <form method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nama" class="form-label">Nama Reseller</label>
                                <input type="text" id="nama" name="nama" class="form-control" value="<?= htmlspecialchars($reseller['nama_reseller']) ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="kontak" class="form-label">Kontak</label>
                                <input type="text" id="kontak" name="kontak" class="form-control" value="<?= htmlspecialchars($reseller['kontak']) ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea id="alamat" name="alamat" class="form-control" rows="3" required><?= htmlspecialchars($reseller['alamat']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="tanggal" class="form-label">Tanggal Bergabung</label>
                            <input type="date" id="tanggal" name="tanggal" class="form-control" value="<?= $reseller['tanggal_bergabung'] ?>" required>
                        </div>

                        <div class="">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-file-plus"></i> Simpan Perubahan
                            </button>
                            <a href="<?= $base_url ?>/owner/master_data.php" class="btn btn-secondary">
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