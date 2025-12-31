<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Cek apakah mode edit
$id_hasil_kirim_finishing = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$mode_edit = ($id_hasil_kirim_finishing > 0);

// Inisialisasi variabel untuk data yang akan diedit
$data_finishing = null;
$items_data = [];

if ($mode_edit) {
    // Ambil data finishing yang akan diedit
    $data_finishing = query("SELECT * FROM hasil_kirim_finishing WHERE id_hasil_kirim_finishing = $id_hasil_kirim_finishing")[0];

    if (!$data_finishing) {
        $_SESSION['error'] = "Data finishing tidak ditemukan!";
        header("Location: finishing.php");
        exit();
    }

    // Ambil detail bahan baku yang sudah diproses
    $items_data = query("SELECT 
        dh.*,
        k.nama_koko,
        k.stok as stok_awal,
        p.nama_produk,
        p.id_produk,
        k.harga_jual
    FROM detail_hasil_kirim_finishing dh
    LEFT JOIN koko k ON dh.id_koko = k.id_koko
    LEFT JOIN produk p ON dh.id_produk = p.id_produk
    WHERE dh.id_hasil_kirim_finishing = $id_hasil_kirim_finishing");
}

// Ambil data bahan (koko) - untuk mode edit, tampilkan semua koko termasuk yang sudah dipilih
$sql_koko = "SELECT * FROM koko WHERE stok > 0 ORDER BY nama_koko";
if ($mode_edit && !empty($items_data)) {
    // Tambahkan koko yang sudah dipilih meskipun stoknya 0
    $selected_koko_ids = array_column($items_data, 'id_koko');
    if (!empty($selected_koko_ids)) {
        $sql_koko = "SELECT * FROM koko WHERE stok > 0 OR id_koko IN (" . implode(',', $selected_koko_ids) . ") ORDER BY nama_koko";
    }
}

$koko = query($sql_koko);
$petugas = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");
$produk_hasil = query("SELECT * FROM produk ORDER BY nama_produk");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_hasil_finishing'])) {
    $id_petugas_finishing = intval($_POST['id_petugas_finishing']);
    $seri = $conn->real_escape_string($_POST['seri']);
    $tanggal_kirim_finishing = $conn->real_escape_string($_POST['tanggal_kirim_finishing']);
    $total_kirim = intval($_POST['total_kirim']);
    $tanggal_hasil_finishing = $conn->real_escape_string($_POST['tanggal_hasil_finishing']);
    $status_finishing = $conn->real_escape_string($_POST['status_finishing']);
    $items = $_POST['items'];

    // Debug: Tampilkan data items
    error_log("====================");
    error_log("PROSES SIMPAN HASIL FINISHING");
    error_log("Mode: " . ($mode_edit ? "EDIT" : "TAMBAH BARU"));
    error_log("Items data: " . print_r($items, true));

    // Validasi duplikasi koko dalam finishing
    $kokoIds = array_column($items, 'id_koko');
    if (count($kokoIds) !== count(array_unique($kokoIds))) {
        $error = "Tidak boleh ada bahan baku yang duplikat dalam satu batch finishing!";
    } else {
        $total_hasil_finishing = 0;
        $produk_stok_tambahan = []; // Array untuk mencatat stok produk yang akan ditambah

        // Validasi setiap item
        foreach ($items as $item) {
            $id_koko = intval($item['id_koko']);
            $id_produk = intval($item['id_produk']); // AMBIL id_produk dari item
            $jumlah = intval($item['jumlah']);

            // Debug: Tampilkan data per item
            error_log("Item: id_koko=$id_koko, id_produk=$id_produk, jumlah=$jumlah");

            if ($jumlah <= 0) {
                $error = "Jumlah bahan baku tidak boleh nol.";
                break;
            }

            if ($id_produk <= 0) {
                $error = "Produk hasil harus dipilih untuk setiap bahan baku.";
                break;
            }

            // Untuk mode edit, ambil stok asli dari database sebelum update
            if ($mode_edit) {
                // Cari stok asli koko sebelum diproses
                $stok_awal = query("SELECT stok FROM koko WHERE id_koko = $id_koko")[0]['stok'];

                // Cari jumlah yang sudah diproses sebelumnya
                $jumlah_sebelumnya = 0;
                foreach ($items_data as $item_lama) {
                    if ($item_lama['id_koko'] == $id_koko) {
                        $jumlah_sebelumnya = $item_lama['jumlah'];
                        break;
                    }
                }

                // Stok maksimal = stok saat ini + jumlah sebelumnya (karena akan dikembalikan dulu)
                $stok_maksimal = $stok_awal + $jumlah_sebelumnya;

                if ($jumlah > $stok_maksimal) {
                    $error = "Jumlah melebihi stok bahan baku yang tersedia untuk koko ini!";
                    break;
                }
            } else {
                // Mode tambah baru - validasi stok biasa
                $stok_koko = query("SELECT stok FROM koko WHERE id_koko = $id_koko")[0]['stok'];
                if ($jumlah > $stok_koko) {
                    $error = "Jumlah melebihi stok bahan baku yang tersedia!";
                    break;
                }
            }

            $total_hasil_finishing += $jumlah;

            // Simpan data stok produk yang akan ditambahkan - PASTIKAN ini dijalankan
            if (!isset($produk_stok_tambahan[$id_produk])) {
                $produk_stok_tambahan[$id_produk] = 0;
            }
            $produk_stok_tambahan[$id_produk] += $jumlah;

            // Debug untuk produk_stok_tambahan
            error_log("  - Produk $id_produk: tambah $jumlah, total sekarang: " . $produk_stok_tambahan[$id_produk]);
        }

        // Debug: Tampilkan data stok produk
        error_log("Produk stok tambahan: " . print_r($produk_stok_tambahan, true));
        error_log("Total produk yang akan diupdate: " . count($produk_stok_tambahan));
        error_log("Total hasil finishing: $total_hasil_finishing");

        if (!isset($error)) {
            $conn->autocommit(FALSE);
            try {
                if ($mode_edit) {
                    error_log("MODE EDIT - Memulai proses...");

                    // 1. Kembalikan stok koko dan produk yang sebelumnya diproses
                    foreach ($items_data as $item_lama) {
                        $id_koko_lama = $item_lama['id_koko'];
                        $jumlah_lama = $item_lama['jumlah'];
                        $id_produk_lama = $item_lama['id_produk'];

                        error_log("Kembalikan stok lama: id_koko=$id_koko_lama, jumlah=$jumlah_lama, id_produk=$id_produk_lama");

                        // Kembalikan stok koko
                        $sql_kembalikan_koko = "UPDATE koko SET stok = stok + $jumlah_lama WHERE id_koko = $id_koko_lama";
                        if (!$conn->query($sql_kembalikan_koko)) {
                            throw new Exception("Gagal mengembalikan stok koko: " . $conn->error);
                        }
                        error_log("  ✓ Stok koko $id_koko_lama dikembalikan $jumlah_lama");

                        // Kurangi stok produk lama (jika ada id_produk)
                        if ($id_produk_lama > 0) {
                            $sql_kurangi_produk = "UPDATE produk SET stok = stok - $jumlah_lama WHERE id_produk = $id_produk_lama";
                            if (!$conn->query($sql_kurangi_produk)) {
                                throw new Exception("Gagal mengurangi stok produk lama: " . $conn->error);
                            }
                            error_log("  ✓ Stok produk lama $id_produk_lama dikurangi $jumlah_lama");
                        }
                    }

                    // 2. Hapus detail lama
                    $sql_hapus_detail = "DELETE FROM detail_hasil_kirim_finishing WHERE id_hasil_kirim_finishing = $id_hasil_kirim_finishing";
                    if (!$conn->query($sql_hapus_detail)) {
                        throw new Exception("Gagal menghapus detail lama: " . $conn->error);
                    }
                    error_log("✓ Detail lama dihapus");

                    // 3. Update header - gunakan id_produk yang pertama
                    $first_product_id = !empty($produk_stok_tambahan) ? array_key_first($produk_stok_tambahan) : 0;
                    $sql_finishing = "UPDATE hasil_kirim_finishing 
                            SET id_petugas_finishing = $id_petugas_finishing,
                                id_produk = $first_product_id,
                                seri = '$seri',
                                tanggal_kirim_finishing = '$tanggal_kirim_finishing',
                                total_kirim = $total_kirim,
                                tanggal_hasil_finishing = '$tanggal_hasil_finishing',
                                total_hasil_finishing = $total_hasil_finishing,
                                status_finishing = '$status_finishing'
                            WHERE id_hasil_kirim_finishing = $id_hasil_kirim_finishing";
                } else {
                    error_log("MODE TAMBAH BARU - Memulai proses...");

                    // MODE TAMBAH BARU
                    // Gunakan id_produk yang pertama
                    $first_product_id = !empty($produk_stok_tambahan) ? array_key_first($produk_stok_tambahan) : 0;

                    // 1. Simpan ke tabel hasil_kirim_finishing
                    $sql_finishing = "INSERT INTO hasil_kirim_finishing 
                            (id_petugas_finishing, id_produk, seri, tanggal_kirim_finishing, total_kirim, 
                             tanggal_hasil_finishing, total_hasil_finishing, status_finishing) 
                            VALUES ($id_petugas_finishing, $first_product_id, '$seri', '$tanggal_kirim_finishing', $total_kirim,
                                    '$tanggal_hasil_finishing', $total_hasil_finishing, '$status_finishing')";
                }

                // Debug: Query finishing
                error_log("SQL Finishing: " . $sql_finishing);

                if (!$conn->query($sql_finishing)) {
                    throw new Exception("Gagal menyimpan data finishing: " . $conn->error);
                }
                error_log("✓ Data finishing disimpan");

                // Untuk mode baru, ambil ID yang baru saja dibuat
                if (!$mode_edit) {
                    $id_hasil_kirim_finishing = $conn->insert_id;
                    error_log("ID baru dibuat: " . $id_hasil_kirim_finishing);
                }

                // 4. Simpan detail baru dan update stok
                $item_counter = 1;
                foreach ($items as $item) {
                    $id_koko = intval($item['id_koko']);
                    $id_produk = intval($item['id_produk']);
                    $jumlah = intval($item['jumlah']);
                    $harga_satuan = floatval($item['harga_satuan']);
                    $subtotal = $jumlah * $harga_satuan;

                    error_log("Item #$item_counter: id_koko=$id_koko, id_produk=$id_produk, jumlah=$jumlah");

                    // Simpan ke detail_hasil_kirim_finishing
                    $sql_detail = "INSERT INTO detail_hasil_kirim_finishing 
                         (id_hasil_kirim_finishing, id_koko, id_produk, id_petugas_finishing, 
                          jumlah, harga_satuan, subtotal) 
                         VALUES ($id_hasil_kirim_finishing, $id_koko, $id_produk, $id_petugas_finishing,
                                 $jumlah, $harga_satuan, $subtotal)";

                    if (!$conn->query($sql_detail)) {
                        throw new Exception("Gagal menyimpan detail finishing: " . $conn->error);
                    }
                    error_log("  ✓ Detail disimpan");

                    // Update stok koko (kurangi stok karena diproses)
                    $sql_update_koko = "UPDATE koko SET stok = stok - $jumlah WHERE id_koko = $id_koko";
                    if (!$conn->query($sql_update_koko)) {
                        throw new Exception("Gagal update stok koko: " . $conn->error);
                    }
                    error_log("  ✓ Stok koko $id_koko dikurangi $jumlah");

                    // Untuk MODE TAMBAH BARU: Update stok produk SEKARANG
                    if (!$mode_edit && $id_produk > 0 && $jumlah > 0) {
                        $sql_update_produk = "UPDATE produk SET stok = stok + $jumlah WHERE id_produk = $id_produk";
                        error_log("  [TAMBAH] Update stok produk: id_produk=$id_produk, tambah=$jumlah");

                        if (!$conn->query($sql_update_produk)) {
                            throw new Exception("Gagal update stok produk ID $id_produk: " . $conn->error);
                        }
                        error_log("  ✓ Stok produk $id_produk ditambah $jumlah");
                    }

                    $item_counter++;
                }

                // 5. Update stok produk untuk MODE EDIT (setelah semua detail disimpan)
                if ($mode_edit && !empty($produk_stok_tambahan)) {
                    error_log("Proses update stok produk untuk mode EDIT:");
                    foreach ($produk_stok_tambahan as $id_produk => $jumlah_tambahan) {
                        if ($id_produk > 0 && $jumlah_tambahan > 0) {
                            $sql_update_produk = "UPDATE produk SET stok = stok + $jumlah_tambahan WHERE id_produk = $id_produk";
                            error_log("  [EDIT] Update stok produk: id_produk=$id_produk, tambah=$jumlah_tambahan");

                            if (!$conn->query($sql_update_produk)) {
                                throw new Exception("Gagal update stok produk ID $id_produk: " . $conn->error);
                            }
                            error_log("  ✓ Stok produk $id_produk ditambah $jumlah_tambahan");
                        }
                    }
                }

                $conn->commit();
                $conn->autocommit(TRUE);

                error_log("✅ Transaksi berhasil di-commit!");
                error_log("====================");

                // Pesan sukses dengan detail produk
                $pesan_sukses = "✅ Data hasil finishing " . ($mode_edit ? "berhasil diperbarui" : "berhasil disimpan") . "!";

                if (!empty($produk_stok_tambahan)) {
                    $pesan_detail = "<br><strong>Stok produk yang ditambahkan:</strong>";
                    foreach ($produk_stok_tambahan as $id_produk => $jumlah) {
                        $nama_produk = query("SELECT nama_produk FROM produk WHERE id_produk = $id_produk")[0]['nama_produk'] ?? "Produk ID $id_produk";
                        $pesan_detail .= "<br>• $nama_produk: +$jumlah pcs";
                    }
                    $pesan_sukses .= $pesan_detail;
                }

                $_SESSION['success'] = $pesan_sukses;
                header("Location: finishing.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $conn->autocommit(TRUE);
                $error = "❌ Error: " . $e->getMessage();
                error_log("❌ ERROR: " . $e->getMessage());
                error_log("====================");
            }
        } else {
            error_log("❌ Validasi gagal: $error");
            error_log("====================");
        }
    }
}
?>

<style>
    .swal2-container {
        z-index: 99999 !important;
    }

    .error {
        color: #dc3545;
        background-color: #f8d7da;
        border-color: #f5c6cb;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
    }

    .currency-format {
        text-align: right;
    }

    .harga-input {
        min-width: 120px;
    }

    .stok-info {
        font-size: 0.9em;
        color: #666;
    }

    .btn-edit-mode {
        background-color: #28a745;
        border-color: #28a745;
    }

    .btn-edit-mode:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }

    /* Tambahan style untuk field read-only */
    .form-control[readonly] {
        background-color: #f8f9fa;
        border-color: #e9ecef;
        cursor: not-allowed;
    }

    .form-control[readonly]:focus {
        box-shadow: none;
        border-color: #e9ecef;
    }

    .table th {
        font-size: 0.9rem;
    }

    .table td {
        font-size: 0.9rem;
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
                    <h2><?= $mode_edit ? 'EDIT HASIL FINISHING' : 'HASIL FINISHING PRODUK' ?></h2>
                    <?php if ($mode_edit): ?>
                        <span class="badge bg-warning text-dark p-2">Mode Edit - ID: <?= $id_hasil_kirim_finishing ?></span>
                    <?php endif; ?>
                </div>

                <div class="card p-3">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['success']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-body">
                            <form method="post" id="formFinishing">
                                <input type="hidden" name="mode_edit" value="<?= $mode_edit ? '1' : '0' ?>">

                                <div class="card border border-dark shadow-sm rounded-3">
                                    <div class="card-body">
                                        <?php if (isset($error)): ?>
                                            <div class="alert error"><?= $error ?></div>
                                        <?php endif; ?>

                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Petugas Finishing</label>
                                                <?php if ($mode_edit): ?>
                                                    <input type="text" class="form-control"
                                                        value="<?php
                                                                $petugas_nama = query("SELECT nama_petugas FROM petugas_finishing WHERE id_petugas_finishing = {$data_finishing['id_petugas_finishing']}")[0]['nama_petugas'] ?? '';
                                                                echo htmlspecialchars($petugas_nama);
                                                                ?>" readonly>
                                                    <input type="hidden" name="id_petugas_finishing"
                                                        value="<?= $data_finishing['id_petugas_finishing'] ?>">
                                                <?php else: ?>
                                                    <select name="id_petugas_finishing" class="form-control" required>
                                                        <option value="">-- Pilih Petugas --</option>
                                                        <?php foreach ($petugas as $p): ?>
                                                            <option value="<?= $p['id_petugas_finishing'] ?>"
                                                                <?= (isset($_POST['id_petugas_finishing']) && $_POST['id_petugas_finishing'] == $p['id_petugas_finishing']) ? 'selected' : '' ?>>
                                                                <?= $p['nama_petugas'] ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php endif; ?>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Nomor Seri</label>
                                                <input type="text" name="seri" class="form-control" required
                                                    placeholder="Contoh: FIN-001"
                                                    value="<?= $mode_edit ? htmlspecialchars($data_finishing['seri']) : (isset($_POST['seri']) ? htmlspecialchars($_POST['seri']) : '') ?>"
                                                    <?= $mode_edit ? 'readonly' : '' ?>>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Tanggal Kirim Finishing</label>
                                                <input type="date" name="tanggal_kirim_finishing"
                                                    class="form-control" required
                                                    value="<?= $mode_edit ? $data_finishing['tanggal_kirim_finishing'] : (isset($_POST['tanggal_kirim_finishing']) ? $_POST['tanggal_kirim_finishing'] : date('Y-m-d')) ?>"
                                                    <?= $mode_edit ? 'readonly' : '' ?>>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Total Kirim</label>
                                                <input type="number" name="total_kirim" class="form-control" required
                                                    min="1" placeholder="Jumlah total yang dikirim"
                                                    value="<?= $mode_edit ? $data_finishing['total_kirim'] : (isset($_POST['total_kirim']) ? $_POST['total_kirim'] : '') ?>"
                                                    id="totalKirimInput" readonly>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Tanggal Hasil Finishing</label>
                                                <input type="datetime-local"
                                                    name="tanggal_hasil_finishing"
                                                    class="form-control"
                                                    required
                                                    value="<?php
                                                            if ($mode_edit && !empty($data_finishing['tanggal_hasil_finishing'])) {
                                                                echo date('Y-m-d\TH:i', strtotime($data_finishing['tanggal_hasil_finishing']));
                                                            } else {
                                                                echo date('Y-m-d\TH:i');
                                                            }
                                                            ?>">
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Status Finishing</label>
                                                <select name="status_finishing" class="form-control" required>
                                                    <option value="selesai" <?= (($mode_edit && $data_finishing['status_finishing'] == 'selesai') ||
                                                                                (isset($_POST['status_finishing']) && $_POST['status_finishing'] == 'selesai')) ? 'selected' : '' ?>>Selesai</option>
                                                    <option value="diproses" <?= (($mode_edit && $data_finishing['status_finishing'] == 'diproses') ||
                                                                                    (isset($_POST['status_finishing']) && $_POST['status_finishing'] == 'diproses')) ? 'selected' : '' ?>>Diproses</option>
                                                    <option value="pengiriman" <?= (($mode_edit && $data_finishing['status_finishing'] == 'pengiriman') ||
                                                                                    (isset($_POST['status_finishing']) && $_POST['status_finishing'] == 'pengiriman')) ? 'selected' : '' ?>>Pengiriman</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mt-3 border border-dark shadow-sm rounded-3">
                                    <div class="card-header">
                                        <h3>Detail Hasil Finishing</h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table" id="tabelFinishing">
                                            <thead>
                                                <tr class="text-center">
                                                    <th>Bahan Baku (Koko)</th>
                                                    <th>Produk Hasil</th>
                                                    <th>Stok Tersedia</th>
                                                    <th>Jumlah Diproses</th>
                                                    <th>Harga Satuan</th>
                                                    <th>Subtotal</th>
                                                    <?php if (!$mode_edit): ?>
                                                        <th>Aksi</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody id="finishingContainer">
                                                <?php if ($mode_edit && !empty($items_data)): ?>
                                                    <?php
                                                    $total_hasil = 0;
                                                    $total_kirim = 0;
                                                    foreach ($items_data as $index => $item):
                                                        $subtotal = $item['jumlah'] * $item['harga_satuan'];
                                                        $total_hasil += $subtotal;
                                                        $total_kirim += $item['jumlah'];
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <input type="hidden" name="items[<?= $index ?>][id_koko]" value="<?= $item['id_koko'] ?>">
                                                                <input type="text" class="form-control" value="<?= htmlspecialchars($item['nama_koko']) ?>" readonly>
                                                            </td>
                                                            <td>
                                                                <input type="hidden" name="items[<?= $index ?>][id_produk]" value="<?= $item['id_produk'] ?>">
                                                                <input type="text" class="form-control" value="<?= htmlspecialchars($item['nama_produk']) ?>" readonly>
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control text-center" value="<?= $item['stok_awal'] ?>" readonly>
                                                            </td>
                                                            <td>
                                                                <input type="number" name="items[<?= $index ?>][jumlah]"
                                                                    class="form-control qty" min="1"
                                                                    value="<?= $item['jumlah'] ?>" required
                                                                    data-stok="<?= $item['stok_awal'] ?>"
                                                                    onchange="hitungSubtotal(<?= $index ?>)">
                                                            </td>
                                                            <td>
                                                                <input type="hidden" name="items[<?= $index ?>][harga_satuan]" value="<?= $item['harga_satuan'] ?>">
                                                                <input type="number" class="form-control harga-satuan"
                                                                    value="<?= $item['harga_satuan'] ?>" readonly>
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control text-center subtotal"
                                                                    value="<?= number_format($subtotal, 2) ?>" readonly>
                                                            </td>
                                                            <?php if (!$mode_edit): ?>
                                                                <td class="text-center">
                                                                    <button type="button" class="btn btn-sm btn-danger hapus-item">
                                                                        Hapus
                                                                    </button>
                                                                </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="<?= $mode_edit ? '5' : '6' ?>" class="text-right"><strong>Total Hasil Finishing</strong></td>
                                                    <td class="currency-format">
                                                        <span id="totalHasil"><?= $mode_edit ? number_format($total_hasil, 2) : '0' ?></span> pcs
                                                    </td>
                                                    <?php if (!$mode_edit): ?>
                                                        <td></td>
                                                    <?php endif; ?>
                                                </tr>
                                                <tr>
                                                    <td colspan="<?= $mode_edit ? '5' : '6' ?>" class="text-right"><strong>Total Kirim</strong></td>
                                                    <td class="currency-format">
                                                        <span id="totalKirimDisplay"><?= $mode_edit ? $total_kirim : '0' ?></span> roll
                                                        <input type="hidden" name="total_kirim" id="totalKirimInput" value="<?= $mode_edit ? $total_kirim : '0' ?>">
                                                    </td>
                                                    <?php if (!$mode_edit): ?>
                                                        <td></td>
                                                    <?php endif; ?>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        <?php if (!$mode_edit): ?>
                                            <button type="button" class="btn btn-secondary mt-3" id="tambahItem">
                                                <i class="bx bx-plus"></i> Tambah Item
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="simpan_hasil_finishing" class="btn btn-primary <?= $mode_edit ? 'btn-edit-mode' : '' ?>">
                                        <i class="bx bx-save"></i> <?= $mode_edit ? 'Update Hasil Finishing' : 'Simpan Hasil Finishing' ?>
                                    </button>
                                    <a href="finishing.php" class="btn btn-danger">
                                        <i class="bx bx-x"></i> Batal
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once '../includes/footer.php'; ?>
</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Data dari PHP
    const kokoData = <?= json_encode($koko) ?>;
    const produkHasil = <?= json_encode($produk_hasil) ?>;
    const modeEdit = <?= $mode_edit ? 'true' : 'false' ?>;
    const itemsData = <?= $mode_edit ? json_encode($items_data) : '[]' ?>;

    // Variabel global
    let selectedKoko = [];
    let selectedProdukHasil = [];

    // Fungsi untuk menghitung subtotal per baris (mode edit)
    function hitungSubtotal(index) {
        const row = document.querySelector(`input[name="items[${index}][jumlah]"]`).closest('tr');
        const qty = parseFloat(row.querySelector('.qty').value) || 0;
        const harga = parseFloat(row.querySelector('.harga-satuan').value) || 0;
        const subtotal = qty * harga;

        row.querySelector('.subtotal').value = subtotal.toFixed(2);

        hitungTotal();
        hitungTotalKirim();
    }

    // Fungsi untuk menghitung total hasil finishing
    function hitungTotal() {
        let total = 0;
        const subtotals = document.querySelectorAll('.subtotal');

        subtotals.forEach(sub => {
            total += parseFloat(sub.value) || 0;
        });

        document.getElementById('totalHasil').textContent = total.toFixed(2);
    }

    // Fungsi untuk menghitung total kirim
    function hitungTotalKirim() {
        let totalKirim = 0;
        const qtyInputs = document.querySelectorAll('.qty');

        qtyInputs.forEach(qty => {
            totalKirim += parseInt(qty.value) || 0;
        });

        document.getElementById('totalKirimDisplay').textContent = totalKirim;
        document.getElementById('totalKirimInput').value = totalKirim;
    }

    // Hanya jalankan script ini jika mode edit = false (tambah baru)
    if (!modeEdit) {
        document.addEventListener('DOMContentLoaded', function() {
            // Mode tambah baru - tambahkan satu baris kosong
            document.getElementById('tambahItem').click();
        });

        // Fungsi untuk menambahkan baris item
        function tambahBarisItem(itemData = null) {
            const container = document.getElementById('finishingContainer');
            const rowId = Date.now();

            // Filter koko yang tersedia
            const availableKoko = kokoData.filter(k =>
                !selectedKoko.includes(k.id_koko.toString()) ||
                (itemData && k.id_koko == itemData.id_koko)
            );

            if (availableKoko.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Tidak ada bahan baku tersedia',
                    text: 'Semua bahan baku sudah digunakan',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Buat opsi untuk bahan baku
            let kokoOptions = '<option value="">Pilih Bahan Baku</option>';
            availableKoko.forEach(koko => {
                const selected = (itemData && koko.id_koko == itemData.id_koko) ? 'selected' : '';
                kokoOptions += `<option value="${koko.id_koko}" 
                                      data-stok="${koko.stok}" 
                                      data-harga="${koko.harga_jual}"
                                      ${selected}>
                                ${koko.nama_koko} (Stok: ${koko.stok})
                            </option>`;
            });

            // Buat opsi untuk produk hasil
            let produkOptions = '<option value="">Pilih Produk Hasil</option>';
            produkHasil.forEach(produk => {
                const selected = (itemData && produk.id_produk == itemData.id_produk) ? 'selected' : '';
                produkOptions += `<option value="${produk.id_produk}" ${selected}>
                                ${produk.nama_produk}
                            </option>`;
            });

            const row = document.createElement('tr');
            row.id = `row-${rowId}`;
            row.innerHTML = `
                <td class="w-25">
                    <select name="items[${rowId}][id_koko]" class="form-control select-koko" required>
                        ${kokoOptions}
                    </select>
                </td>
                <td class="w-25">
                    <select name="items[${rowId}][id_produk]" class="form-control select-produk" required>
                        ${produkOptions}
                    </select>
                </td>
                <td class="stok-bahan text-center">${itemData ? itemData.stok_awal || 0 : 0}</td>
                <td>
                    <input type="number" name="items[${rowId}][jumlah]" 
                           class="form-control qty" min="1" 
                           value="${itemData ? itemData.jumlah : 1}" required>
                </td>
                <td>
                    <input type="number" name="items[${rowId}][harga_satuan]" 
                           class="form-control harga-satuan" min="0" step="0.01" 
                           value="${itemData ? itemData.harga_satuan : 0}" required>
                </td>
                <td class="subtotal text-center">${itemData ? (itemData.jumlah * itemData.harga_satuan).toFixed(2) : '0'}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger hapus-item" data-row="${rowId}">
                        Hapus
                    </button>
                </td>
            `;
            container.appendChild(row);
            initRowEvents(rowId, itemData);

            // Update selected arrays
            if (itemData && itemData.id_koko) {
                selectedKoko.push(itemData.id_koko.toString());
            }
            if (itemData && itemData.id_produk) {
                selectedProdukHasil.push(itemData.id_produk.toString());
            }
        }

        document.getElementById('tambahItem').addEventListener('click', function() {
            tambahBarisItem();
        });

        function initRowEvents(rowId, itemData = null) {
            const row = document.getElementById(`row-${rowId}`);
            const selectKoko = row.querySelector('.select-koko');
            const selectProduk = row.querySelector('.select-produk');
            const qtyInput = row.querySelector('.qty');
            const hargaInput = row.querySelector('.harga-satuan');
            const stokDisplay = row.querySelector('.stok-bahan');

            // Event untuk bahan baku (koko)
            selectKoko.addEventListener('change', function() {
                const previousId = selectKoko.dataset.previousValue;

                // Hapus dari selected jika ada sebelumnya
                if (previousId) {
                    selectedKoko = selectedKoko.filter(id => id != previousId);
                }

                const newId = this.value;
                const selectedOption = this.options[this.selectedIndex];

                if (newId) {
                    selectedKoko.push(newId);
                    selectKoko.dataset.previousValue = newId;

                    // Update stok display
                    const stok = selectedOption.getAttribute('data-stok');
                    const harga = selectedOption.getAttribute('data-harga');

                    stokDisplay.textContent = stok;
                    hargaInput.value = harga;

                    // Set max quantity
                    qtyInput.max = stok;
                    if (parseInt(qtyInput.value) > parseInt(stok)) {
                        qtyInput.value = stok;
                        Swal.fire({
                            icon: 'warning',
                            title: 'Stok tidak mencukupi',
                            text: `Jumlah melebihi stok bahan baku (${stok})`,
                            confirmButtonText: 'OK'
                        });
                    }
                } else {
                    selectKoko.dataset.previousValue = '';
                    stokDisplay.textContent = '0';
                    hargaInput.value = '0';
                    qtyInput.value = '1';
                    qtyInput.removeAttribute('max');
                }

                updateKokoDropdowns();
                hitungSubtotalBaru(rowId);
                hitungTotal();
                hitungTotalKirim();
            });

            // Event untuk produk hasil
            selectProduk.addEventListener('change', function() {
                const previousId = selectProduk.dataset.previousValue;

                if (previousId) {
                    selectedProdukHasil = selectedProdukHasil.filter(id => id != previousId);
                }

                const newId = this.value;
                if (newId) {
                    selectedProdukHasil.push(newId);
                    selectProduk.dataset.previousValue = newId;
                } else {
                    selectProduk.dataset.previousValue = '';
                }

                updateProdukDropdowns();
            });

            // Event untuk quantity dan harga
            qtyInput.addEventListener('input', function() {
                const maxStok = parseInt(stokDisplay.textContent) || 0;
                if (this.value > maxStok) {
                    this.value = maxStok;
                    Swal.fire({
                        icon: 'warning',
                        title: 'Stok tidak mencukupi',
                        text: `Jumlah melebihi stok bahan baku (${maxStok})`,
                        confirmButtonText: 'OK'
                    });
                }
                hitungSubtotalBaru(rowId);
                hitungTotal();
                hitungTotalKirim();
            });

            hargaInput.addEventListener('input', function() {
                hitungSubtotalBaru(rowId);
                hitungTotal();
            });

            // Jika ada data item, trigger change event
            if (itemData && itemData.id_koko) {
                setTimeout(() => {
                    selectKoko.dispatchEvent(new Event('change'));
                    if (itemData.id_produk) {
                        selectProduk.value = itemData.id_produk;
                        selectProduk.dataset.previousValue = itemData.id_produk;
                    }
                }, 100);
            }
        }

        function hitungSubtotalBaru(rowId) {
            const row = document.getElementById(`row-${rowId}`);
            const qty = parseInt(row.querySelector('.qty').value) || 0;
            const harga = parseFloat(row.querySelector('.harga-satuan').value) || 0;
            const subtotal = qty * harga;

            row.querySelector('.subtotal').textContent = subtotal.toFixed(2);
        }

        document.addEventListener('click', function(e) {
            if (e.target.closest('.hapus-item')) {
                const rowId = e.target.closest('.hapus-item').dataset.row;
                const row = document.getElementById(`row-${rowId}`);

                // Hapus dari selected arrays
                const selectKoko = row.querySelector('.select-koko');
                const selectProduk = row.querySelector('.select-produk');

                if (selectKoko.value) {
                    selectedKoko = selectedKoko.filter(id => id != selectKoko.value);
                }

                if (selectProduk.value) {
                    selectedProdukHasil = selectedProdukHasil.filter(id => id != selectProduk.value);
                }

                row.remove();
                hitungTotal();
                hitungTotalKirim();
                updateKokoDropdowns();
                updateProdukDropdowns();
            }
        });

        function updateKokoDropdowns() {
            document.querySelectorAll('.select-koko').forEach(select => {
                const currentValue = select.value;
                const availableKoko = kokoData.filter(k =>
                    (!selectedKoko.includes(k.id_koko.toString()) || k.id_koko.toString() == currentValue)
                );

                let options = '<option value="">Pilih Bahan Baku</option>';
                availableKoko.forEach(koko => {
                    const selected = koko.id_koko.toString() == currentValue ? 'selected' : '';
                    options += `<option value="${koko.id_koko}" 
                                      data-stok="${koko.stok}" 
                                      data-harga="${koko.harga_jual}" ${selected}>
                                ${koko.nama_koko} (Stok: ${koko.stok})
                            </option>`;
                });

                select.innerHTML = options;

                // Set kembali nilai yang dipilih sebelumnya
                if (currentValue) {
                    select.value = currentValue;
                }
            });
        }

        function updateProdukDropdowns() {
            document.querySelectorAll('.select-produk').forEach(select => {
                const currentValue = select.value;
                const availableProduk = produkHasil.filter(p =>
                    !selectedProdukHasil.includes(p.id_produk.toString()) || p.id_produk.toString() == currentValue
                );

                let options = '<option value="">Pilih Produk Hasil</option>';
                availableProduk.forEach(produk => {
                    const selected = produk.id_produk.toString() == currentValue ? 'selected' : '';
                    options += `<option value="${produk.id_produk}" ${selected}>
                                ${produk.nama_produk}
                            </option>`;
                });

                select.innerHTML = options;

                // Set kembali nilai yang dipilih sebelumnya
                if (currentValue) {
                    select.value = currentValue;
                }
            });
        }
    }

    // Validasi form sebelum submit (untuk mode edit)
    if (modeEdit) {
        document.addEventListener('DOMContentLoaded', function() {
            // Validasi jumlah tidak melebihi stok
            const qtyInputs = document.querySelectorAll('.qty');
            qtyInputs.forEach(qty => {
                qty.addEventListener('change', function() {
                    const stok = parseInt(this.getAttribute('data-stok')) || 0;
                    const value = parseInt(this.value) || 0;

                    if (value > stok) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Stok tidak mencukupi',
                            text: `Jumlah melebihi stok bahan baku (${stok})`,
                            confirmButtonText: 'OK'
                        });
                        this.value = stok;
                        hitungSubtotal(Array.from(qtyInputs).indexOf(this));
                    }
                });
            });
        });
    }

    // Validasi form sebelum submit (umum)
    document.getElementById('formFinishing').addEventListener('submit', function(e) {
        const rows = document.querySelectorAll('#finishingContainer tr');

        if (rows.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Tidak ada item',
                text: 'Minimal harus ada satu bahan baku yang diproses',
                confirmButtonText: 'OK'
            });
            return;
        }

        let hasError = false;
        let produkList = [];

        rows.forEach((row, index) => {
            let qty, harga, id_produk;

            if (modeEdit) {
                // Mode edit: hanya validasi qty
                qty = row.querySelector('.qty');
                if (!qty.value || parseInt(qty.value) <= 0) {
                    hasError = true;
                    Swal.fire({
                        icon: 'error',
                        title: 'Jumlah Invalid',
                        text: `Jumlah harus lebih dari 0 untuk baris ${index + 1}`,
                        confirmButtonText: 'OK'
                    });
                    qty.focus();
                    return false;
                }

                // Ambil id_produk untuk log
                const hiddenProduk = row.querySelector('input[name*="[id_produk]"]');
                if (hiddenProduk) {
                    produkList.push(hiddenProduk.value);
                }
            } else {
                // Mode tambah: validasi semua field
                const selectKoko = row.querySelector('.select-koko');
                const selectProduk = row.querySelector('.select-produk');
                qty = row.querySelector('.qty');
                harga = row.querySelector('.harga-satuan');
                id_produk = selectProduk?.value;

                if (!selectKoko.value) {
                    hasError = true;
                    Swal.fire({
                        icon: 'error',
                        title: 'Pilih Koko',
                        text: `Harap pilih bahan baku untuk baris ${index + 1}`,
                        confirmButtonText: 'OK'
                    });
                    selectKoko.focus();
                    return false;
                }

                if (!selectProduk.value) {
                    hasError = true;
                    Swal.fire({
                        icon: 'error',
                        title: 'Pilih Produk Hasil',
                        text: `Harap pilih produk hasil untuk baris ${index + 1}`,
                        confirmButtonText: 'OK'
                    });
                    selectProduk.focus();
                    return false;
                }

                if (!qty.value || parseInt(qty.value) <= 0) {
                    hasError = true;
                    Swal.fire({
                        icon: 'error',
                        title: 'Jumlah Invalid',
                        text: `Jumlah harus lebih dari 0 untuk baris ${index + 1}`,
                        confirmButtonText: 'OK'
                    });
                    qty.focus();
                    return false;
                }

                if (!harga.value || parseFloat(harga.value) < 0) {
                    hasError = true;
                    Swal.fire({
                        icon: 'error',
                        title: 'Harga Invalid',
                        text: `Harga tidak valid untuk baris ${index + 1}`,
                        confirmButtonText: 'OK'
                    });
                    harga.focus();
                    return false;
                }

                if (id_produk) {
                    produkList.push(id_produk);
                }
            }
        });

        // Debug: Tampilkan data produk yang akan diproses
        console.log("Produk yang akan diproses:", produkList);

        if (hasError) {
            e.preventDefault();
        }
    });
</script>

</html>