<?php

$page_title = "UBAH DATA PRODUK";

require_once '../includes/header.php';

// Cek apakah parameter ID ada
if (!isset($_GET['id'])) {
    header("Location: {$base_url}/manager/master_data.php");
    exit;
}

$id_produk = intval($_GET['id']);

// Ambil data produk yang akan diedit
$sql = "SELECT * FROM produk WHERE id_produk = $id_produk";
$result = $conn->query($sql);
$produk = $result->fetch_assoc();

if (!$produk) {
    header("Location: {$base_url}/manager/master_data.php");
    exit;
}

// Proses update data
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

    $sql = "UPDATE produk SET 
            nama_produk = '$nama',
            tipe_produk = '$tipe_produk',
            harga_jual = '$harga',
            stok = '$stok',
            deskripsi = '$deskripsi'
            WHERE id_produk = $id_produk";

    if ($conn->query($sql)) {
        $_SESSION['success'] = "Produk berhasil diperbarui";
        header("Location: {$base_url}/manager/master_data.php");
        exit();
    } else {
        $error = "Gagal memperbarui produk: " . $conn->error;
    }
}

// Calculate kodi value for display if stock is divisible by 20
$stok_pcs = $produk['stok'];
$stok_kodi = floor($stok_pcs / 20);
$show_kodi = ($stok_pcs % 20 == 0) && ($stok_pcs > 0);
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
                                <input type="text" name="nama" id="nama" class="form-control"
                                    value="<?= htmlspecialchars($produk['nama_produk']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="tipe_produk" class="form-label">Tipe Produk</label>
                                <select name="tipe_produk" id="tipe_produk" class="form-select" required>
                                    <option value="" disabled>Pilih Tipe Produk</option>
                                    <option value="koko" <?= ($produk['tipe_produk'] == 'koko'   ? 'selected' : '') ?>>Koko</option>
                                    <option value="mukena" <?= ($produk['tipe_produk'] == 'mukena' ? 'selected' : '') ?>>Mukena</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- <div class="col-md-4 mb-3">
                                        <label for="harga" class="form-label">Harga Jual</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" id="harga" name="harga" class="form-control"
                                                value="<?= rtrim(rtrim($produk['harga_jual'], '0'), '.') ?>" min="0" required>
                                        </div>
                                    </div> -->
                            <div class="col-md-4 mb-3">
                                <label for="harga" class="form-label">Harga Jual</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" id="harga" name="harga" class="form-control"
                                        value="<?= rtrim($produk['harga_jual']) ?>" min="0" required>
                                </div>
                            </div>

                            <div class="col-md-8 mb-3">
                                <label for="stok" class="form-label">Stok Awal</label>
                                <div class="input-group">
                                    <input type="number" id="stok" name="stok" class="form-control"
                                        value="<?= $show_kodi ? $stok_kodi : $produk['stok'] ?>" min="0" required>
                                    <select name="stok_unit" class="form-select" style="max-width: 100px;">
                                        <option value="pcs" <?= !$show_kodi ? 'selected' : '' ?>>Pcs</option>
                                        <option value="kodi" <?= $show_kodi ? 'selected' : '' ?>>Kodi</option>
                                    </select>
                                </div>
                                <small class="text-muted">1 kodi = 20 pcs</small>
                            </div>

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