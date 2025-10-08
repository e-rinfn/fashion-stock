<?php
require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $conn->real_escape_string($_POST['nama']);
    $harga = $conn->real_escape_string($_POST['harga']);
    $stok = $conn->real_escape_string($_POST['stok']);
    $stok_unit = $conn->real_escape_string($_POST['stok_unit']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);

    // Convert kodi to pcs if needed
    if ($stok_unit == 'kodi') {
        $stok = $stok * 20;
    }

    $sql = "INSERT INTO produk (nama_produk, harga_jual, stok, deskripsi) 
            VALUES ('$nama', '$harga', '$stok', '$deskripsi')";

    if ($conn->query($sql)) {
        $_SESSION['success'] = "Produk berhasil ditambahkan";
        header("Location: list.php");
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

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Tambah Data Produk</h2>
                </div>


                <div class="card p-4 shadow-sm">

                    <form method="post">
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Produk</label>
                            <input type="text" name="nama" id="nama" class="form-control" required>
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
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" id="deskripsi" rows="5" class="form-control"></textarea>
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