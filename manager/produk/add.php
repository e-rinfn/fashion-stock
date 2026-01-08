<?php

$page_title = "TAMBAH DATA PRODUK";

require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $conn->real_escape_string($_POST['nama']);
    $tipe_produk = $conn->real_escape_string($_POST['tipe_produk']);
    $harga = $conn->real_escape_string($_POST['harga']);
    $stok = $conn->real_escape_string($_POST['stok']);
    $stok_unit = $conn->real_escape_string($_POST['stok_unit']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);

    // Convert kodi to pcs if needed
    if ($stok_unit == 'kodi') {
        $stok = $stok * 20;
    }

    $sql = "INSERT INTO produk (nama_produk, tipe_produk, harga_jual, stok, deskripsi) 
            VALUES ('$nama', '$tipe_produk', '$harga', '$stok', '$deskripsi')";

    if ($conn->query($sql)) {
        $_SESSION['success'] = "Produk berhasil ditambahkan";
        header("Location: {$base_url}/manager/master_data.php");
        exit();
    } else {
        $error = "Gagal menambahkan produk: " . $conn->error;
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

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nama" class="form-label">Nama Produk</label>
                                <input type="text" name="nama" id="nama" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="tipe_produk" class="form-label">Tipe Produk</label>
                                <select name="tipe_produk" id="tipe_produk" class="form-select" required>
                                    <option value="" selected disabled>Pilih Tipe Produk</option>
                                    <option value="koko">Koko</option>
                                    <option value="mukena">Mukena</option>
                                </select>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="harga" class="form-label">Harga Jual</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="harga" id="harga" class="form-control" min="0" required>
                                </div>
                            </div>

                            <div class="col-md-8 mb-3">
                                <label for="stok" class="form-label">Stok Awal</label>
                                <div class="input-group">
                                    <input type="number" name="stok" id="stok" class="form-control" min="0" required>
                                    <select name="stok_unit" class="form-select" style="max-width: 100px;">
                                        <option value="pcs">Pcs</option>
                                        <option value="kodi">Kodi</option>
                                    </select>
                                </div>
                                <small class="text-muted">1 kodi = 20 pcs</small>
                            </div>
                        </div>

                        <div class="">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-file-plus"></i> Simpan
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