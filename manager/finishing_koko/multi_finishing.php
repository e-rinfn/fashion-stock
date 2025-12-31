<?php
// Aktifkan error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Validasi akses
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// ✅ FUNGSI: untuk mendapatkan tarif upah terkini
function getTarifUpah($jenis_tarif, $tanggal_referensi = null)
{
    global $conn;

    if ($tanggal_referensi === null) {
        $tanggal_referensi = date('Y-m-d');
    }

    $sql = "SELECT tarif_per_unit 
            FROM tarif_upah 
            WHERE jenis_tarif = ? 
            AND berlaku_sejak <= ? 
            ORDER BY berlaku_sejak DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $jenis_tarif, $tanggal_referensi);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['tarif_per_unit'];
    }

    return 0; // Default jika tidak ada tarif
}

// ✅ FUNGSI: untuk menambahkan hutang upah petugas finishing
function tambahHutangUpahFinishing($id_petugas_finishing, $jumlah_tambah, $tanggal)
{
    global $conn;

    try {
        // 1. Cek apakah sudah ada hutang
        $sql_check = "SELECT id_hutang, total_upah, sisa_hutang 
                     FROM hutang_upah 
                     WHERE id_karyawan = ? AND jenis_karyawan = 'finishing'
                     LIMIT 1";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("i", $id_petugas_finishing);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update hutang yang sudah ada
            $hutang = $result->fetch_assoc();
            $total_upah_baru = $hutang['total_upah'] + $jumlah_tambah;
            $sisa_hutang_baru = $hutang['sisa_hutang'] + $jumlah_tambah;

            $sql_update = "UPDATE hutang_upah 
                          SET total_upah = ?, 
                              sisa_hutang = ?,
                              updated_at = NOW()
                          WHERE id_hutang = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);
            if (!$stmt->execute()) {
                throw new Exception("Gagal update hutang upah: " . $conn->error);
            }
        } else {
            // Buat hutang baru
            $sql_insert = "INSERT INTO hutang_upah 
                          (id_karyawan, jenis_karyawan, total_upah, sisa_hutang, tanggal_hutang, created_at, updated_at)
                          VALUES (?, 'finishing', ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("idds", $id_petugas_finishing, $jumlah_tambah, $jumlah_tambah, $tanggal);
            if (!$stmt->execute()) {
                throw new Exception("Gagal membuat hutang upah baru: " . $conn->error);
            }
        }

        return true;
    } catch (Exception $e) {
        throw new Exception("Gagal menambahkan hutang upah petugas finishing: " . $e->getMessage());
    }
}

// ✅ PROSES SIMPAN DATA FINISHING SELESAI
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_finishing'])) {
    $id_petugas_finishing = intval($_POST['id_petugas_finishing']);
    $tanggal_hasil = $_POST['tanggal_hasil'];
    $keterangan = $_POST['keterangan'] ?? '';

    // Validasi input
    if (empty($id_petugas_finishing) || empty($tanggal_hasil)) {
        $_SESSION['error'] = "❌ Petugas finishing dan tanggal hasil wajib diisi!";
        header("Location: multi_finishing.php");
        exit();
    }

    // Ambil data produk dari POST
    $produk_items = [];
    if (isset($_POST['produk_id']) && is_array($_POST['produk_id'])) {
        foreach ($_POST['produk_id'] as $index => $produk_id) {
            $jumlah = isset($_POST['jumlah'][$index]) ? intval($_POST['jumlah'][$index]) : 0;
            $keterangan_item = isset($_POST['keterangan_item'][$index]) ? $_POST['keterangan_item'][$index] : '';

            if ($produk_id > 0 && $jumlah > 0) {
                $produk_items[] = [
                    'produk_id' => $produk_id,
                    'jumlah' => $jumlah,
                    'keterangan' => $keterangan_item
                ];
            }
        }
    }

    // Validasi minimal ada 1 produk
    if (empty($produk_items)) {
        $_SESSION['error'] = "❌ Minimal harus ada 1 produk dengan jumlah lebih dari 0!";
        header("Location: multi_finishing.php");
        exit();
    }

    // Hitung total upah
    $tarif_finishing = getTarifUpah('finishing', $tanggal_hasil);
    $total_upah = 0;
    $total_hasil = 0;

    foreach ($produk_items as $item) {
        $total_upah += ($item['jumlah'] * $tarif_finishing);
        $total_hasil += $item['jumlah'];
    }

    // Generate seri unik
    $seri_prefix = "FIN-" . date('Ymd');
    $sql_seri = "SELECT COUNT(*) as count FROM hasil_kirim_finishing WHERE seri LIKE ?";
    $stmt_seri = $conn->prepare($sql_seri);
    $like_seri = $seri_prefix . '%';
    $stmt_seri->bind_param("s", $like_seri);
    $stmt_seri->execute();
    $result_seri = $stmt_seri->get_result();
    $data_seri = $result_seri->fetch_assoc();
    $seri_number = $data_seri['count'] + 1;
    $seri = $seri_prefix . "-" . str_pad($seri_number, 3, '0', STR_PAD_LEFT);

    $conn->autocommit(FALSE);
    try {
        // Simpan hutang upah terlebih dahulu
        if ($total_upah > 0) {
            if (!tambahHutangUpahFinishing($id_petugas_finishing, $total_upah, $tanggal_hasil)) {
                throw new Exception("Gagal menyimpan hutang upah");
            }
        }

        // Update stok produk dan simpan data finishing untuk setiap produk
        foreach ($produk_items as $item) {
            $produk_id = $item['produk_id'];
            $jumlah = $item['jumlah'];
            $keterangan_item = $item['keterangan'];

            // 1. Update stok produk
            $sql_update_stok = "UPDATE produk SET stok = stok + ?, updated_at = NOW() WHERE id_produk = ?";
            $stmt_stok = $conn->prepare($sql_update_stok);
            $stmt_stok->bind_param("ii", $jumlah, $produk_id);
            if (!$stmt_stok->execute()) {
                throw new Exception("Gagal update stok produk ID $produk_id: " . $conn->error);
            }

            // 2. Simpan data hasil finishing
            $sql_insert = "INSERT INTO hasil_kirim_finishing 
                          (seri, id_produk, id_petugas_finishing, tanggal_kirim_finishing, 
                           tanggal_hasil_finishing, total_kirim, total_hasil_finishing, 
                           status_finishing, keterangan, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'selesai', ?, NOW(), NOW())";

            $stmt_insert = $conn->prepare($sql_insert);
            // Untuk multi finishing, tanggal_kirim_finishing diisi sama dengan tanggal_hasil_finishing
            $tanggal_kirim = $tanggal_hasil;
            $total_kirim = $jumlah; // Karena langsung selesai, total kirim = hasil

            $stmt_insert->bind_param(
                "siissiis",
                $seri,
                $produk_id,
                $id_petugas_finishing,
                $tanggal_kirim,
                $tanggal_hasil,
                $total_kirim,
                $jumlah,
                $keterangan_item
            );

            if (!$stmt_insert->execute()) {
                throw new Exception("Gagal menyimpan data finishing produk ID $produk_id: " . $conn->error);
            }
        }

        $conn->commit();
        $conn->autocommit(TRUE);

        // Buat pesan sukses detail
        $pesan_detail = "";
        foreach ($produk_items as $item) {
            // Ambil nama produk
            $sql_nama = "SELECT nama_produk FROM produk WHERE id_produk = ?";
            $stmt_nama = $conn->prepare($sql_nama);
            $stmt_nama->bind_param("i", $item['produk_id']);
            $stmt_nama->execute();
            $result_nama = $stmt_nama->get_result();
            $produk_nama = $result_nama->fetch_assoc()['nama_produk'];

            $pesan_detail .= "<li><strong>" . htmlspecialchars($produk_nama) . ":</strong> " . number_format($item['jumlah']) . " pcs</li>";
        }

        // Ambil nama petugas
        $sql_petugas = "SELECT nama_petugas FROM petugas_finishing WHERE id_petugas_finishing = ?";
        $stmt_petugas = $conn->prepare($sql_petugas);
        $stmt_petugas->bind_param("i", $id_petugas_finishing);
        $stmt_petugas->execute();
        $result_petugas = $stmt_petugas->get_result();
        $nama_petugas = $result_petugas->fetch_assoc()['nama_petugas'];

        $_SESSION['success'] = "✅ Finishing selesai berhasil dicatat!<br><br>
                              <strong>Detail:</strong><br>
                              <ul>$pesan_detail</ul>
                              <strong>Seri:</strong> $seri<br>
                              <strong>Petugas:</strong> " . htmlspecialchars($nama_petugas) . "<br>
                              <strong>Tanggal:</strong> " . date('d/m/Y', strtotime($tanggal_hasil)) . "<br>
                              <strong>Total Hasil:</strong> " . number_format($total_hasil) . " pcs<br>
                              <strong>Total Upah:</strong> " . formatRupiah($total_upah);

        header("Location: finishing.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(TRUE);
        $_SESSION['error'] = "❌ Gagal menyimpan data finishing: " . $e->getMessage();
        header("Location: multi_finishing.php");
        exit();
    }
}

// Ambil data untuk dropdown
$produk_list = query("SELECT * FROM produk ORDER BY nama_produk");
$petugas_list = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");

// Set tanggal default
$tanggal_default = date('Y-m-d');
?>

<style>
    .swal2-container {
        z-index: 99999 !important;
    }

    .product-row {
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
        background-color: #f8f9fa;
    }

    .product-row:last-child {
        margin-bottom: 0;
    }

    .btn-add-row {
        margin-top: 0.5rem;
    }

    .btn-remove-row {
        margin-top: 2rem;
    }

    .total-summary {
        background-color: #e7f3ff;
        border: 2px solid #0d6efd;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-top: 1rem;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        padding: 0.25rem 0;
    }

    .summary-item:last-child {
        margin-bottom: 0;
        border-top: 1px solid #dee2e6;
        padding-top: 0.5rem;
        font-weight: bold;
    }

    .summary-label {
        font-weight: 500;
    }

    .summary-value {
        font-weight: bold;
    }

    .tarif-info {
        font-size: 0.85rem;
        color: #6c757d;
        font-style: italic;
    }

    .form-label {
        font-weight: 500;
    }

    .form-control,
    .form-select {
        border-radius: 0.375rem;
    }

    .card {
        border: 1px solid rgba(0, 0, 0, .125);
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0, 0, 0, .125);
        font-weight: 600;
    }

    .table {
        margin-bottom: 0;
    }

    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    .btn-group-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-group-actions .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
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

    <!-- [ Header Topbar ] start -->
    <header class="pc-header">
        <div class="header-wrapper">
            <!-- [Mobile Media Block] start -->
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
                    <!-- ======= Menu collapse Icon ===== -->
                    <li class="pc-h-item pc-sidebar-collapse">
                        <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                    <li class="pc-h-item pc-sidebar-popup">
                        <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- [Mobile Media Block end] -->
        </div>
    </header>
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <!-- [ Main Content ] start -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2>Pencatatan Finishing Selesai</h2>
                        <div>
                            <a href="finishing.php" class="btn btn-secondary">
                                <i class="ti ti-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>

                    <!-- Tampilkan pesan error atau success -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $_SESSION['error'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $_SESSION['success'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Form Pencatatan Finishing Selesai</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="formMultiFinishing">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Petugas Finishing <span class="text-danger">*</span></label>
                                            <select name="id_petugas_finishing" class="form-select" required>
                                                <option value="">-- Pilih Petugas --</option>
                                                <?php foreach ($petugas_list as $petugas): ?>
                                                    <option value="<?= $petugas['id_petugas_finishing'] ?>">
                                                        <?= htmlspecialchars($petugas['nama_petugas']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tanggal Hasil Finishing <span class="text-danger">*</span></label>
                                            <input type="date" name="tanggal_hasil" class="form-control"
                                                value="<?= $tanggal_default ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Keterangan</label>
                                            <textarea name="keterangan" class="form-control" rows="2"
                                                placeholder="Catatan tambahan tentang finishing..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Info Tarif -->
                                <div class="alert alert-info mb-4">
                                    <h6 class="alert-heading">Informasi Tarif Upah Finishing</h6>
                                    <p class="mb-1">Tarif saat ini: <strong><?= formatRupiah(getTarifUpah('finishing')) ?></strong> per unit</p>
                                    <small class="text-muted">Tarif akan dihitung otomatis berdasarkan tanggal finishing</small>
                                </div>

                                <!-- Daftar Produk -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5>Daftar Produk Hasil Finishing</h5>
                                        <button type="button" class="btn btn-primary btn-sm" id="btnTambahProduk">
                                            <i class="ti ti-plus"></i> Tambah Produk
                                        </button>
                                    </div>

                                    <div id="produkContainer">
                                        <!-- Row produk pertama -->
                                        <div class="product-row" id="produkRow_0">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Produk <span class="text-danger">*</span></label>
                                                        <select name="produk_id[]" class="form-select produk-select" required>
                                                            <option value="">-- Pilih Produk --</option>
                                                            <?php foreach ($produk_list as $produk): ?>
                                                                <option value="<?= $produk['id_produk'] ?>"
                                                                    data-stok="<?= $produk['stok'] ?>">
                                                                    <?= htmlspecialchars($produk['nama_produk']) ?>
                                                                    (Stok: <?= number_format($produk['stok']) ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">Jumlah Hasil (Pcs) <span class="text-danger">*</span></label>
                                                        <input type="number" name="jumlah[]" class="form-control jumlah-input"
                                                            min="1" value="" required placeholder="0">
                                                        <small class="text-muted stok-info"></small>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Keterangan Produk</label>
                                                        <input type="text" name="keterangan_item[]" class="form-control"
                                                            placeholder="Catatan khusus untuk produk ini...">
                                                    </div>
                                                </div>
                                                <div class="col-md-1 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm btn-remove-row"
                                                        onclick="hapusProdukRow(0)" style="display: none;">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Total Summary -->
                                <div class="total-summary">
                                    <h6 class="mb-3">Ringkasan Finishing</h6>
                                    <div class="summary-item">
                                        <span class="summary-label">Jumlah Jenis Produk:</span>
                                        <span class="summary-value" id="totalJenis">1</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Total Hasil (Pcs):</span>
                                        <span class="summary-value" id="totalPcs">0</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Tarif per Unit:</span>
                                        <span class="summary-value" id="tarifUnit"><?= formatRupiah(getTarifUpah('finishing')) ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Total Upah:</span>
                                        <span class="summary-value" id="totalUpah">Rp 0</span>
                                    </div>
                                </div>

                                <!-- Tombol Aksi -->
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="finishing.php" class="btn btn-secondary">
                                        <i class="ti ti-x"></i> Batal
                                    </a>
                                    <button type="submit" name="simpan_finishing" class="btn btn-success">
                                        <i class="ti ti-check"></i> Simpan Finishing Selesai
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabel Preview -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Preview Data Finishing</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="previewTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Produk</th>
                                            <th>Jumlah (Pcs)</th>
                                            <th>Keterangan</th>
                                            <th>Upah</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewBody">
                                        <!-- Data akan diisi oleh JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>
</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        let produkCounter = 1;
        const tarifPerUnit = <?= getTarifUpah('finishing') ?>;

        // Format Rupiah
        function formatRupiah(angka) {
            return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Hitung total
        function hitungTotal() {
            let totalJenis = 0;
            let totalPcs = 0;
            let totalUpah = 0;

            // Update preview table
            let previewHTML = '';
            $('.product-row').each(function(index) {
                const produkSelect = $(this).find('.produk-select');
                const produkId = produkSelect.val();
                const produkNama = produkSelect.find('option:selected').text().split('(')[0].trim();
                const jumlah = parseInt($(this).find('.jumlah-input').val()) || 0;
                const keterangan = $(this).find('input[name="keterangan_item[]"]').val() || '-';

                if (produkId && produkId !== '' && jumlah > 0) {
                    totalJenis++;
                    totalPcs += jumlah;
                    totalUpah += (jumlah * tarifPerUnit);

                    // Add to preview
                    previewHTML += `
                        <tr>
                            <td>${totalJenis}</td>
                            <td>${produkNama}</td>
                            <td class="text-end">${jumlah.toLocaleString()}</td>
                            <td>${keterangan}</td>
                            <td class="text-end">${formatRupiah(jumlah * tarifPerUnit)}</td>
                        </tr>
                    `;
                }
            });

            // Update summary
            $('#totalJenis').text(totalJenis);
            $('#totalPcs').text(totalPcs.toLocaleString());
            $('#tarifUnit').text(formatRupiah(tarifPerUnit));
            $('#totalUpah').text(formatRupiah(totalUpah));

            // Update preview table
            if (totalJenis > 0) {
                $('#previewBody').html(previewHTML);
            } else {
                $('#previewBody').html('<tr><td colspan="5" class="text-center">Belum ada data produk</td></tr>');
            }
        }

        // Tambah row produk
        $('#btnTambahProduk').click(function() {
            const newRowId = 'produkRow_' + produkCounter;
            const produkOptions = `<?php foreach ($produk_list as $produk): ?>
                <option value="<?= $produk['id_produk'] ?>" data-stok="<?= $produk['stok'] ?>">
                    <?= htmlspecialchars($produk['nama_produk']) ?> (Stok: <?= number_format($produk['stok']) ?>)
                </option>
            <?php endforeach; ?>`;

            const newRow = `
                <div class="product-row" id="${newRowId}">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Produk <span class="text-danger">*</span></label>
                                <select name="produk_id[]" class="form-select produk-select" required>
                                    <option value="">-- Pilih Produk --</option>
                                    ${produkOptions}
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Jumlah Hasil (Pcs) <span class="text-danger">*</span></label>
                                <input type="number" name="jumlah[]" class="form-control jumlah-input" 
                                       min="1" value="" required placeholder="0">
                                <small class="text-muted stok-info"></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Keterangan Produk</label>
                                <input type="text" name="keterangan_item[]" class="form-control" 
                                       placeholder="Catatan khusus untuk produk ini...">
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm btn-remove-row" 
                                    onclick="hapusProdukRow(${produkCounter})">
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('#produkContainer').append(newRow);
            produkCounter++;

            // Update tombol hapus di row pertama
            if (produkCounter > 1) {
                $('#produkRow_0 .btn-remove-row').show();
            }

            // Bind events untuk row baru
            $(`#${newRowId} .produk-select`).change(updateStokInfo);
            $(`#${newRowId} .jumlah-input`).on('input', hitungTotal);

            hitungTotal();
        });

        // Update info stok saat produk dipilih
        function updateStokInfo() {
            const row = $(this).closest('.product-row');
            const selectedOption = $(this).find('option:selected');
            const stok = parseInt(selectedOption.data('stok')) || 0;
            const stokInfo = row.find('.stok-info');

            if (stok > 0) {
                stokInfo.text(`Stok tersedia: ${stok.toLocaleString()} pcs`);
                stokInfo.removeClass('text-danger').addClass('text-success');
            } else {
                stokInfo.text('Stok kosong');
                stokInfo.removeClass('text-success').addClass('text-danger');
            }
        }

        // Hapus row produk
        window.hapusProdukRow = function(rowId) {
            $(`#produkRow_${rowId}`).remove();

            // Hitung ulang counter
            produkCounter--;

            // Update tombol hapus di row pertama
            if (produkCounter <= 1) {
                $('#produkRow_0 .btn-remove-row').hide();
            }

            hitungTotal();
        }

        // Validasi form sebelum submit
        $('#formMultiFinishing').submit(function(e) {
            // Validasi minimal satu produk dengan jumlah > 0
            let isValid = false;
            $('.produk-select').each(function() {
                const produkId = $(this).val();
                const row = $(this).closest('.product-row');
                const jumlah = parseInt(row.find('.jumlah-input').val()) || 0;

                if (produkId && produkId !== '' && jumlah > 0) {
                    isValid = true;
                }
            });

            if (!isValid) {
                e.preventDefault();
                Swal.fire({
                    title: 'Data Tidak Valid!',
                    text: 'Minimal harus ada 1 produk dengan jumlah lebih dari 0',
                    icon: 'warning',
                    confirmButtonColor: '#3085d6',
                });
                return false;
            }

            // Konfirmasi sebelum simpan
            e.preventDefault();
            Swal.fire({
                title: 'Simpan Finishing Selesai?',
                html: `<div class="text-left">
                      <p>Apakah Anda yakin ingin menyimpan data finishing selesai?</p>
                      <ul>
                        <li><strong>Jumlah Jenis Produk:</strong> <span id="confirmJenis"></span></li>
                        <li><strong>Total Hasil:</strong> <span id="confirmPcs"></span> pcs</li>
                        <li><strong>Total Upah:</strong> <span id="confirmUpah"></span></li>
                      </ul>
                    </div>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Simpan!',
                cancelButtonText: 'Batal',
                width: '500px',
                didOpen: () => {
                    // Update data konfirmasi
                    $('#confirmJenis').text($('#totalJenis').text());
                    $('#confirmPcs').text($('#totalPcs').text());
                    $('#confirmUpah').text($('#totalUpah').text());
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit form
                    $(this).unbind('submit').submit();
                }
            });
        });

        // Event listeners
        $(document).on('change', '.produk-select', updateStokInfo);
        $(document).on('input', '.jumlah-input', hitungTotal);
        $(document).on('change', 'select[name="id_petugas_finishing"], input[name="tanggal_hasil"]', function() {
            // Jika tanggal berubah, hitung ulang tarif (implementasi bisa ditambahkan jika ada API tarif)
            hitungTotal();
        });

        // Hitung total awal
        hitungTotal();
    });
</script>

</html>