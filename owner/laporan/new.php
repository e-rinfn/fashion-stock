<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Ambil data bahan dengan informasi meter
$bahan = query("SELECT *, COALESCE(jumlah_meter, 0) as jumlah_meter, COALESCE(meter_per_roll, 0) as meter_per_roll FROM bahan_baku WHERE jumlah_stok > 0 ORDER BY nama_bahan");
$produk = query("SELECT * FROM produk ORDER BY nama_produk");
$pemotong  = query("SELECT * FROM pemotong ORDER BY nama_pemotong");

function catatHutangUpah($id_karyawan, $jenis_karyawan, $tanggal_produksi, $jumlah_upah)
{
    global $conn;

    /**
     * ------------------------------------------------------------
     * 1. Cek catatan hutang tanpa periode
     * ------------------------------------------------------------
     * Hanya berdasarkan id_karyawan + jenis_karyawan.
     */
    $check = $conn->prepare("
        SELECT id_hutang, total_upah, sisa_hutang 
        FROM hutang_upah 
        WHERE id_karyawan = ? AND jenis_karyawan = ?
    ");
    $check->bind_param("is", $id_karyawan, $jenis_karyawan);
    $check->execute();
    $result = $check->get_result();

    /**
     * ------------------------------------------------------------
     * 2. Jika catatan hutang SUDAH ADA → update total / sisa hutang
     * ------------------------------------------------------------
     */
    if ($result->num_rows > 0) {

        $hutang = $result->fetch_assoc();

        // Tambah upah ke total & sisa
        $total_upah_baru = $hutang['total_upah'] + $jumlah_upah;
        $sisa_hutang_baru = $hutang['sisa_hutang'] + $jumlah_upah;

        $update = $conn->prepare("
            UPDATE hutang_upah 
            SET total_upah = ?, sisa_hutang = ?, updated_at = NOW()
            WHERE id_hutang = ?
        ");
        $update->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);

        return $update->execute();
    }

    /**
     * ------------------------------------------------------------
     * 3. Jika catatan hutang BELUM ADA → buat baru
     * ------------------------------------------------------------
     */
    else {

        $insert = $conn->prepare("
            INSERT INTO hutang_upah (id_karyawan, jenis_karyawan, total_upah, sisa_hutang, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $insert->bind_param("isdd", $id_karyawan, $jenis_karyawan, $jumlah_upah, $jumlah_upah);

        return $insert->execute();
    }
}

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

    // Default value jika tidak ada tarif
    return 500.00;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_hasil_potong_fix'])) {

    // VALIDASI INPUT WAJIB
    if (empty($_POST['id_produk']) || empty($_POST['id_pemotong']) || empty($_POST['seri']) || empty($_POST['total_upah'])) {
        $error = "Semua field wajib diisi, termasuk upah pemotong!";
    } else {
        $id_produk = intval($_POST['id_produk']);
        $id_pemotong = intval($_POST['id_pemotong']);
        $items = $_POST['items'];
        $seri = $conn->real_escape_string($_POST['seri']);
        $tanggal_hasil_potong = $conn->real_escape_string($_POST['tanggal_hasil_potong']);

        $status_potong = 'diproses'; // Default value untuk produksi baru

        // Jika ada input dari form, validasi dulu
        if (isset($_POST['status_potong'])) {
            $input_status = $_POST['status_potong'];

            // Hanya terima nilai yang valid untuk ENUM
            $valid_statuses = ['diproses', 'penjahitan', 'selesai'];
            if (in_array($input_status, $valid_statuses)) {
                $status_potong = $input_status;
            } else {
                $status_potong = 'diproses'; // Default jika tidak valid
            }
        }

        // Ambil input upah (bisa dari tarif standar atau input manual)
        $upah_per_potongan = floatval($_POST['upah_per_potongan']);
        $total_upah = floatval($_POST['total_upah']);
        $total_hasil_pcs = intval($_POST['total_hasil']);

        // Validasi total upah
        if ($total_upah <= 0) {
            $error = "Total upah harus lebih dari 0!";
        }

        // Validasi jika input manual, hitung ulang total upah
        if ($upah_per_potongan > 0 && $total_hasil_pcs > 0) {
            $calculated_upah = $upah_per_potongan * $total_hasil_pcs;
            if (abs($calculated_upah - $total_upah) > 1) { // Toleransi 1 rupiah
                $error = "Perhitungan upah tidak sesuai! Total seharusnya: Rp " . number_format($calculated_upah);
            }
        }

        // Validasi duplikasi seri (server-side)
        $check_seri = $conn->query("SELECT id_hasil_potong_fix FROM hasil_potong_fix WHERE seri = '$seri'");
        if ($check_seri->num_rows > 0) {
            $error = "Nomor seri '$seri' sudah digunakan! Silakan gunakan nomor seri yang berbeda.";
        } else {
            // Validasi duplikasi bahan
            $bahanIds = array_column($items, 'id_bahan');
            if (count($bahanIds) !== count(array_unique($bahanIds))) {
                $error = "Tidak boleh ada bahan yang duplikat dalam satu produksi!";
            } else {
                // Validasi stok dan meter
                foreach ($items as $item) {
                    $id_bahan = intval($item['id_bahan']);
                    $qty = intval($item['qty']);
                    $meter = floatval($item['meter']);

                    // Ambil data stok bahan
                    $bahan_stok = query("SELECT jumlah_stok, jumlah_meter FROM bahan_baku WHERE id_bahan = $id_bahan")[0];

                    if ($qty > $bahan_stok['jumlah_stok']) {
                        $error = "Jumlah roll melebihi stok tersedia untuk bahan " . $bahan_stok['nama_bahan'];
                        break;
                    }

                    // Hitung total meter yang akan digunakan
                    $total_meter = floatval($item['total_meter']); // Input langsung total meter
                    if ($total_meter > $bahan_stok['jumlah_meter']) {
                        $error = "Total meter melebihi stok meter tersedia untuk bahan " . $bahan_stok['nama_bahan'];
                        break;
                    }

                    if ($total_meter <= 0) {
                        $error = "Total meter tidak valid untuk bahan " . $bahan_stok['nama_bahan'];
                        break;
                    }
                }

                if (!isset($error)) {
                    $total_harga = 0;
                    $total_hasil = 0;

                    foreach ($items as $item) {
                        $id_bahan = intval($item['id_bahan']);
                        $qty = intval($item['qty']);
                        $meter = floatval($item['meter']);
                        $harga = floatval($item['harga']);

                        if ($qty <= 0) {
                            $error = "Jumlah bahan tidak boleh nol.";
                            break;
                        }

                        if ($harga <= 0) {
                            $error = "Harga bahan tidak boleh nol atau negatif.";
                            break;
                        }

                        $total_harga += $harga * $qty;
                        $total_hasil += $meter * $qty;
                    }

                    // Ambil nilai total_hasil (pcs) dari form
                    if (isset($_POST['total_hasil']) && !empty($_POST['total_hasil'])) {
                        $total_hasil_pcs = intval($_POST['total_hasil']);
                    } else {
                        $total_hasil_pcs = 0;
                    }

                    // Ambil total upah dari form
                    $total_upah = floatval($_POST['total_upah']); // INI PENTING!

                    if (!isset($error)) {
                        $conn->autocommit(FALSE);
                        try {
                            // Insert hasil potong utama - TAMBAHKAN total_upah
                            $stmt = $conn->prepare("INSERT INTO hasil_potong_fix (id_produk, id_pemotong, seri, tanggal_hasil_potong, total_hasil, total_harga, status_potong, total_upah) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            // Perhatikan tipe data: i = integer, s = string, d = decimal
                            // Parameter: id_produk, id_pemotong, seri, tanggal_hasil_potong, total_hasil, total_harga, status_potong, total_upah
                            $stmt->bind_param("iissidss", $id_produk, $id_pemotong, $seri, $tanggal_hasil_potong, $total_hasil_pcs, $total_harga, $status_potong, $total_upah);

                            if (!$stmt->execute()) {
                                throw new Exception("Gagal menyimpan hasil pemotongan: " . $stmt->error);
                            }

                            $id_hasil_potong_fix = $stmt->insert_id;
                            $stmt->close();

                            // HITUNG UPAH PEMOTONG
                            $tarif_pemotong = getTarifUpah('pemotongan', $tanggal_hasil_potong);
                            $upah_pemotong = $total_hasil_pcs * $tarif_pemotong;

                            // CATAT HUTANG UPAH - menggunakan input manual
                            if (!catatHutangUpah($id_pemotong, 'pemotong', $tanggal_hasil_potong, $total_upah)) {
                                throw new Exception("Gagal mencatat hutang upah pemotong");
                            }

                            // Insert detail dan update stok (termasuk meter)
                            $stmt_detail = $conn->prepare("INSERT INTO detail_hasil_potong_fix (id_hasil_potong_fix, id_bahan, id_produk, id_pemotong, jumlah, meter_per_roll, total_meter, harga_satuan, subtotal) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                            foreach ($items as $item) {
                                $id_bahan = intval($item['id_bahan']);
                                $qty = intval($item['qty']);
                                $meter_per_roll = floatval($item['meter']);
                                $harga = floatval($item['harga']);
                                $total_meter = floatval($item['total_meter']); // Input langsung total meter
                                $subtotal = $harga * $qty;

                                $stmt_detail->bind_param("iiiiddddd", $id_hasil_potong_fix, $id_bahan, $id_produk, $id_pemotong, $qty, $meter_per_roll, $total_meter, $harga, $subtotal);

                                if (!$stmt_detail->execute()) {
                                    throw new Exception("Gagal menyimpan detail hasil pemotongan: " . $stmt_detail->error);
                                }

                                // Update stok dan jumlah meter
                                $sql_update = "UPDATE bahan_baku SET 
                                               jumlah_stok = jumlah_stok - ?, 
                                               jumlah_meter = jumlah_meter - ? 
                                               WHERE id_bahan = ?";
                                $stmt_update = $conn->prepare($sql_update);
                                $stmt_update->bind_param("ddi", $qty, $total_meter, $id_bahan);

                                if (!$stmt_update->execute()) {
                                    throw new Exception("Gagal update stok bahan: " . $stmt_update->error);
                                }
                                $stmt_update->close();
                            }

                            $stmt_detail->close();
                            $conn->commit();
                            $conn->autocommit(TRUE);

                            $_SESSION['success'] = "Data hasil pemotongan berhasil disimpan";
                            header("Location: list.php");
                            exit();
                        } catch (Exception $e) {
                            $conn->rollback();
                            $conn->autocommit(TRUE);
                            $error = $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}
?>

<style>
    /* Paksa SweetAlert berada di atas segalanya */
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

    .stok-warning {
        color: #dc3545;
        font-size: 0.8rem;
    }

    .stok-info {
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
                    <h2>Tambah Riwayat Pemotongan Bahan</h2>
                </div>

                <div class="card">
                    <!-- Tampilkan pesan error atau success -->
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

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <div class="card-body">
                        <div class="row">
                            <form method="post" id="formPenjualanBahan">
                                <div class="card border border-dark shadow-sm rounded-3">
                                    <div class="card-body">
                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-3">
                                                <label class="form-label">Nama Produk <span class="text-danger">*</span></label>
                                                <select name="id_produk" class="form-control" required>
                                                    <option value="">-- Pilih Produk --</option>
                                                    <?php foreach ($produk as $p): ?>
                                                        <option value="<?= $p['id_produk'] ?>" <?= isset($_POST['id_produk']) && $_POST['id_produk'] == $p['id_produk'] ? 'selected' : '' ?>>
                                                            <?= $p['nama_produk'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label">Nama Pemotong <span class="text-danger">*</span></label>
                                                <select name="id_pemotong" class="form-control" required>
                                                    <option value="">-- Pilih Pemotong --</option>
                                                    <?php foreach ($pemotong as $p): ?>
                                                        <option value="<?= $p['id_pemotong'] ?>" <?= isset($_POST['id_pemotong']) && $_POST['id_pemotong'] == $p['id_pemotong'] ? 'selected' : '' ?>>
                                                            <?= $p['nama_pemotong'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label">Tanggal Hasil Potong <span class="text-danger">*</span></label>
                                                <input type="date" name="tanggal_hasil_potong" class="form-control"
                                                    value="<?= isset($_POST['tanggal_hasil_potong']) ? $_POST['tanggal_hasil_potong'] : date('Y-m-d') ?>"
                                                    required>
                                            </div>

                                            <div hidden class="col-md-3 mt-3">
                                                <label class="form-label">Status Potong <span class="text-danger">*</span></label>
                                                <select name="status_potong" class="form-control" required>
                                                    <option value="diproses" selected>Diproses</option>
                                                    <option value="selesai">Selesai</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mt-3 g-3 align-items-center">

                                            <div class="col-md-3 mt-3">
                                                <label class="form-label">Seri Produksi <span class="text-danger">*</span></label>
                                                <input type="text" name="seri" class="form-control" id="seriInput"
                                                    value="<?= isset($_POST['seri']) ? $_POST['seri'] : '' ?>" required
                                                    oninput="checkSeri(this.value)">
                                                <small id="seriFeedback" class="text-muted">Masukkan nomor seri</small>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label">Total Hasil (Potongan) <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="number" name="total_hasil" class="form-control" min="1"
                                                        value="<?= isset($_POST['total_hasil']) ? $_POST['total_hasil'] : '' ?>"
                                                        required id="totalHasilInput">
                                                    <span class="input-group-text">Pcs</span>
                                                </div>
                                                <small class="text-muted">Total jumlah potongan yang dihasilkan</small>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label">Upah Pemotong per Potongan <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number" name="upah_per_potongan" class="form-control"
                                                        min="0" step="100" id="upahPerPotonganInput"
                                                        value="<?= isset($_POST['upah_per_potongan']) ? $_POST['upah_per_potongan'] : '' ?>">
                                                </div>
                                                <small class="text-muted">Atau pilih tarif standar:</small>
                                                <select class="form-control mt-1" id="tarifDropdown">
                                                    <option value="">-- Pilih Tarif Standar --</option>
                                                    <?php
                                                    $tarif_standar = query("SELECT * FROM tarif_upah WHERE jenis_tarif = 'pemotongan' ORDER BY berlaku_sejak DESC");
                                                    foreach ($tarif_standar as $tarif):
                                                    ?>
                                                        <option value="<?= $tarif['tarif_per_unit'] ?>"
                                                            data-tanggal="<?= $tarif['berlaku_sejak'] ?>">
                                                            Rp <?= number_format($tarif['tarif_per_unit']) ?> (sejak <?= date('d/m/Y', strtotime($tarif['berlaku_sejak'])) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label">Total Upah Pemotong (Otomatis)</label>
                                                <div class="input-group">
                                                    <!-- <span class="input-group-text">Rp</span> -->
                                                    <input type="text" name="total_upah_pemotong" class="form-control"
                                                        id="totalUpahDisplay" readonly>
                                                </div>
                                                <input type="hidden" name="total_upah" id="totalUpahHidden">
                                                <small class="text-muted">Total: <span id="detailUpah">0 potongan × Rp 0</span></small>
                                            </div>


                                        </div>

                                    </div>
                                </div>

                                <div class="card mt-3 border border-dark shadow-sm rounded-3">
                                    <div class="card-header">
                                        <h3>Daftar Bahan Digunakan</h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table" id="tabelBahan">
                                            <thead>
                                                <tr class="text-center">
                                                    <th>Bahan <span class="text-danger">*</span></th>
                                                    <th>Stok</th>
                                                    <th>Roll/Yard <span class="text-danger">*</span></th>
                                                    <th>Meter <span class="text-danger">*</span></th>
                                                    <th>Total Meter</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bahanContainer"></tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5" class="text-right"><strong>Total Meter Digunakan:</strong></td>
                                                    <td class="text-center"><span id="totalMeter">0</span> Meter</td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        <button type="button" class="btn btn-secondary mt-3" id="tambahBahan">
                                            <i class="ti ti-plus"></i> Tambah Bahan
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="simpan_hasil_potong_fix" class="btn btn-primary">
                                        <i class="ti ti-file-plus"></i> Simpan Hasil Pemotongan
                                    </button>
                                    <a href="list.php" class="btn btn-danger">
                                        <i class="ti ti-x"></i> Batal
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const bahanData = <?= json_encode($bahan) ?>;
    let selectedBahans = [];
    let totalMeterUsed = 0;
    let totalUpah = 0;

    // ============================================
    // FUNGSI FORMATTING
    // ============================================
    function formatRupiah(angka) {
        return 'Rp ' + formatNumber(angka);
    }

    function formatNumber(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // ============================================
    // FUNGSI UPAH
    // ============================================
    function hitungTotalUpah() {
        const totalHasil = parseInt(document.getElementById('totalHasilInput').value) || 0;
        const upahPerPotongan = parseFloat(document.getElementById('upahPerPotonganInput').value) || 0;

        totalUpah = totalHasil * upahPerPotongan;

        // Update tampilan
        document.getElementById('totalUpahDisplay').value = formatRupiah(totalUpah);
        document.getElementById('totalUpahHidden').value = totalUpah;
        document.getElementById('detailUpah').textContent =
            `${totalHasil} potongan × Rp ${formatNumber(upahPerPotongan)}`;
    }

    // ============================================
    // FUNGSI CHECK SERI
    // ============================================
    function checkSeri(seriValue) {
        const feedbackElement = document.getElementById('seriFeedback');

        if (seriValue.trim() === '') {
            feedbackElement.innerHTML = 'Masukkan nomor seri';
            feedbackElement.className = 'text-muted';
            return;
        }

        // Lakukan AJAX request untuk memeriksa seri
        fetch('check_seri.php?seri=' + encodeURIComponent(seriValue))
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    feedbackElement.innerHTML = `❌ Nomor seri telah ada! Coba nomor yang lain. <br><small>Seri terakhir: <strong>${data.last_seri}</strong></small>`;
                    feedbackElement.className = 'text-danger';
                } else {
                    feedbackElement.innerHTML = '✅ Nomor seri tersedia';
                    feedbackElement.className = 'text-success';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedbackElement.innerHTML = 'Error memeriksa seri';
                feedbackElement.className = 'text-warning';
            });
    }

    // ============================================
    // FUNGSI BAHAN (DIUBAH: TOTAL METER SAJA)
    // ============================================
    function initRowEvents(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const select = row.querySelector('.select-bahan');
        const hargaInput = row.querySelector('.harga-input');
        const qtyInput = row.querySelector('.qty');
        const totalMeterInput = row.querySelector('.total-meter-input');
        const stokRollDisplay = row.querySelector('.stok-roll');
        const stokMeterDisplay = row.querySelector('.stok-meter');
        const stokRollWarning = row.querySelector('.stok-roll-warning');

        select.addEventListener('change', function() {
            const prevId = select.dataset.previousValue;
            if (prevId) selectedBahans = selectedBahans.filter(id => id != prevId);

            const newId = this.value;
            if (newId) {
                selectedBahans.push(newId);
                select.dataset.previousValue = newId;

                const bahan = bahanData.find(b => b.id_bahan == newId);
                if (bahan) {
                    hargaInput.value = bahan.harga_per_satuan;
                    stokRollDisplay.textContent = bahan.jumlah_stok;
                    stokMeterDisplay.textContent = bahan.jumlah_meter || 0;

                    // Set nilai default qty = 1
                    qtyInput.value = 1;
                    qtyInput.max = bahan.jumlah_stok;

                    // Reset total meter input
                    totalMeterInput.value = '';
                    row.querySelector('.display-total-meter').textContent = '0 m';

                    validateStok(rowId);
                    updateTotalMeterUsed();
                }
            } else {
                select.dataset.previousValue = '';
                hargaInput.value = 0;
                stokRollDisplay.textContent = '0';
                stokMeterDisplay.textContent = '0';
                qtyInput.value = 1;
                qtyInput.max = '';
                totalMeterInput.value = '';
                row.querySelector('.display-total-meter').textContent = '0 m';
            }

            updateBahanDropdowns();
        });

        qtyInput.addEventListener('input', () => {
            validateStok(rowId);
        });

        totalMeterInput.addEventListener('input', () => {
            const meterValue = parseFloat(totalMeterInput.value) || 0;
            row.querySelector('.display-total-meter').textContent = meterValue.toFixed(0) + ' m';
            validateStok(rowId);
            updateTotalMeterUsed();
        });

        // Trigger change event jika sudah ada value
        if (select.value) select.dispatchEvent(new Event('change'));
    }

    function validateStok(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const select = row.querySelector('.select-bahan');
        const qtyInput = row.querySelector('.qty');
        const totalMeterInput = row.querySelector('.total-meter-input');
        const stokRollWarning = row.querySelector('.stok-roll-warning');
        const stokMeterWarning = row.querySelector('.stok-meter-warning');

        if (!select.value) return;

        const bahan = bahanData.find(b => b.id_bahan == select.value);
        if (!bahan) return;

        const qty = parseInt(qtyInput.value) || 0;
        const totalMeter = parseFloat(totalMeterInput.value) || 0;

        // Validasi stok roll
        if (qty > bahan.jumlah_stok) {
            stokRollWarning.style.display = 'block';
            stokRollWarning.textContent = `Stok hanya ${bahan.jumlah_stok} roll`;
            qtyInput.value = bahan.jumlah_stok;
        } else {
            stokRollWarning.style.display = 'none';
        }

        // Validasi stok meter
        if (totalMeter > (bahan.jumlah_meter || 0)) {
            stokMeterWarning.style.display = 'block';
            stokMeterWarning.textContent = `Stok meter hanya ${bahan.jumlah_meter || 0}m`;
            totalMeterInput.value = bahan.jumlah_meter || 0;
            row.querySelector('.display-total-meter').textContent = (bahan.jumlah_meter || 0) + ' m';
        } else {
            stokMeterWarning.style.display = 'none';
        }

        // Validasi: total meter harus cukup untuk qty (minimal 1 meter per roll)
        if (totalMeter > 0 && qty > 0 && totalMeter < qty) {
            stokMeterWarning.style.display = 'block';
            stokMeterWarning.textContent = `Minimal ${qty} meter untuk ${qty} roll`;
            totalMeterInput.value = qty;
            row.querySelector('.display-total-meter').textContent = qty + ' m';
        }

        updateTotalMeterUsed();
    }

    function updateTotalMeterUsed() {
        totalMeterUsed = 0;
        document.querySelectorAll('#bahanContainer tr').forEach(row => {
            const totalMeterText = row.querySelector('.display-total-meter').textContent;
            const meterValue = parseFloat(totalMeterText) || 0;
            totalMeterUsed += meterValue;
        });
        document.getElementById('totalMeter').textContent = totalMeterUsed.toFixed(0);
    }

    function updateBahanDropdowns() {
        document.querySelectorAll('.select-bahan').forEach(select => {
            const currentValue = select.value;
            const row = select.closest('tr');

            const availableBahans = bahanData.filter(bahan =>
                !selectedBahans.includes(bahan.id_bahan) || bahan.id_bahan == currentValue
            );

            let options = '<option value="">Pilih Bahan</option>';
            availableBahans.forEach(bahan => {
                const stokLabel = `${bahan.nama_bahan} (Stok: ${bahan.jumlah_stok} Roll, ${bahan.jumlah_meter || 0} m)`;
                const selected = bahan.id_bahan == currentValue ? 'selected' : '';
                options += `<option value="${bahan.id_bahan}" 
                            data-harga="${bahan.harga_per_satuan}" 
                            data-stok="${bahan.jumlah_stok}"
                            data-stok-meter="${bahan.jumlah_meter || 0}" 
                            ${selected}>
                            ${stokLabel}
                        </option>`;
            });

            select.innerHTML = options;

            // Update stok info jika bahan masih sama
            if (currentValue && row) {
                const bahan = bahanData.find(b => b.id_bahan == currentValue);
                if (bahan) {
                    row.querySelector('.stok-roll').textContent = bahan.jumlah_stok;
                    row.querySelector('.stok-meter').textContent = bahan.jumlah_meter || 0;
                }
            }
        });
    }

    // ============================================
    // EVENT LISTENERS UTAMA
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Set tarif default berdasarkan tanggal
        const tanggalInput = document.querySelector('input[name="tanggal_hasil_potong"]');
        const tarifDropdown = document.getElementById('tarifDropdown');

        // Event untuk mengubah tarif berdasarkan tanggal
        tanggalInput.addEventListener('change', function() {
            const selectedDate = this.value;
            if (selectedDate) {
                // Cari tarif yang berlaku pada tanggal tersebut
                const options = tarifDropdown.options;
                let found = false;
                for (let i = 0; i < options.length; i++) {
                    const option = options[i];
                    const tanggalBerlaku = option.dataset.tanggal;
                    if (tanggalBerlaku && selectedDate >= tanggalBerlaku) {
                        tarifDropdown.value = option.value;
                        document.getElementById('upahPerPotonganInput').value = option.value;
                        hitungTotalUpah();
                        found = true;
                        break;
                    }
                }

                // Jika tidak ditemukan, gunakan tarif tertua
                if (!found && options.length > 1) {
                    tarifDropdown.value = options[1].value;
                    document.getElementById('upahPerPotonganInput').value = options[1].value;
                    hitungTotalUpah();
                }
            }
        });

        // Trigger change untuk tanggal default
        if (tanggalInput.value) {
            tanggalInput.dispatchEvent(new Event('change'));
        }

        // 2. Event listener untuk input total hasil
        const totalHasilInput = document.getElementById('totalHasilInput');
        if (totalHasilInput) {
            totalHasilInput.addEventListener('input', function() {
                hitungTotalUpah();
            });
        }

        // 3. Event listener untuk input upah per potongan
        const upahPerPotonganInput = document.getElementById('upahPerPotonganInput');
        if (upahPerPotonganInput) {
            upahPerPotonganInput.addEventListener('input', function() {
                hitungTotalUpah();
            });
        }

        // 4. Event listener untuk dropdown tarif standar
        if (tarifDropdown) {
            tarifDropdown.addEventListener('change', function() {
                const selectedTarif = parseFloat(this.value) || 0;
                if (selectedTarif > 0) {
                    document.getElementById('upahPerPotonganInput').value = selectedTarif;
                    hitungTotalUpah();
                }
            });
        }

        // 5. Event listener untuk input manual upah (override dropdown)
        if (upahPerPotonganInput) {
            upahPerPotonganInput.addEventListener('focus', function() {
                // Kosongkan dropdown jika user ingin input manual
                if (tarifDropdown) {
                    tarifDropdown.value = '';
                }
            });
        }

        // 6. Event listener untuk seri input
        const seriInput = document.getElementById('seriInput');
        if (seriInput) {
            seriInput.addEventListener('blur', function() {
                checkSeri(this.value);
            });
        }

        // 7. Tombol Tambah Bahan (DIUBAH: total meter saja)
        const tambahBahanBtn = document.getElementById('tambahBahan');
        if (tambahBahanBtn) {
            tambahBahanBtn.addEventListener('click', function() {
                const container = document.getElementById('bahanContainer');
                const rowId = Date.now();

                // Filter bahan yang belum dipilih
                const availableBahans = bahanData.filter(b => !selectedBahans.includes(b.id_bahan));

                if (availableBahans.length === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Tidak ada bahan tersedia',
                        text: 'Semua bahan sudah ditambahkan atau stok habis',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Buat opsi dropdown
                let options = '<option value="">Pilih Bahan</option>';
                availableBahans.forEach(bahan => {
                    const stokLabel = `${bahan.nama_bahan} (Stok: ${bahan.jumlah_stok} Roll, ${bahan.jumlah_meter || 0} Meter)`;
                    options += `<option value="${bahan.id_bahan}" 
                                data-harga="${bahan.harga_per_satuan}" 
                                data-stok="${bahan.jumlah_stok}"
                                data-stok-meter="${bahan.jumlah_meter || 0}">
                                ${stokLabel}
                            </option>`;
                });

                // Tambahkan baris baru ke tabel (DIUBAH: hanya total meter)
                const row = document.createElement('tr');
                row.id = `row-${rowId}`;
                row.innerHTML = `
                    <td>
                        <select name="items[${rowId}][id_bahan]" class="form-control select-bahan" required>
                            ${options}
                        </select>
                    </td>
                    <td class="stok-info">
                        <span class="stok-roll">0</span> Roll<br>
                        <span class="stok-meter">0</span> Meter
                    </td>
                    <td>
                        <div class="input-group">
                            <input type="number" name="items[${rowId}][qty]" class="form-control qty" min="1" value="1" required>
                            <span class="input-group-text">Roll</span>
                        </div>
                        <small class="stok-warning stok-roll-warning" style="display:none"></small>
                    </td>
                    <td>
                        <div class="input-group">
                            <input type="number" name="items[${rowId}][total_meter]" 
                                   class="form-control total-meter-input" 
                                   step="1" min="1" placeholder="Total Meter" required>
                            <span class="input-group-text">Meter</span>
                        </div>
                        <small class="stok-warning stok-meter-warning" style="display:none"></small>
                        <input type="hidden" name="items[${rowId}][harga]" class="harga-input" value="0">
                    </td>
                    <td class="display-total-meter">0 Meter</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger hapus-bahan" data-row="${rowId}">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                `;
                container.appendChild(row);
                initRowEvents(rowId);
                updateTotalMeterUsed();
            });
        }

        // 8. Hapus bahan dari daftar
        document.addEventListener('click', function(e) {
            if (e.target.closest('.hapus-bahan')) {
                const button = e.target.closest('.hapus-bahan');
                const rowId = button.dataset.row;
                const row = document.getElementById(`row-${rowId}`);

                if (row) {
                    const select = row.querySelector('.select-bahan');
                    if (select && select.value) {
                        selectedBahans = selectedBahans.filter(id => id != select.value);
                    }
                    row.remove();
                    updateTotalMeterUsed();
                    updateBahanDropdowns();
                }
            }
        });

        // 9. Validasi form sebelum submit (DIUBAH: validasi total meter)
        const formPenjualanBahan = document.getElementById('formPenjualanBahan');
        if (formPenjualanBahan) {
            formPenjualanBahan.addEventListener('submit', function(e) {
                const seriInput = document.getElementById('seriInput');
                const seriFeedback = document.getElementById('seriFeedback');
                const rows = document.querySelectorAll('#bahanContainer tr');

                // Validasi seri
                if (seriFeedback && seriFeedback.classList.contains('text-danger')) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Nomor Seri Sudah Ada',
                        text: 'Silakan gunakan nomor seri yang berbeda',
                        confirmButtonText: 'Oke'
                    });
                    if (seriInput) seriInput.focus();
                    return;
                }

                // Validasi minimal satu bahan
                if (rows.length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Tidak ada bahan',
                        text: 'Minimal harus ada satu bahan yang digunakan',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Validasi setiap bahan
                let isValid = true;
                let errorMessage = '';

                rows.forEach((row, index) => {
                    const select = row.querySelector('.select-bahan');
                    const qty = row.querySelector('.qty').value;
                    const totalMeter = row.querySelector('.total-meter-input').value;

                    if (!select || !select.value) {
                        isValid = false;
                        errorMessage = `Pilih bahan untuk baris ${index + 1}`;
                    } else if (!qty || qty <= 0) {
                        isValid = false;
                        errorMessage = `Jumlah roll tidak valid untuk baris ${index + 1}`;
                    } else if (!totalMeter || totalMeter <= 0) {
                        isValid = false;
                        errorMessage = `Total meter tidak valid untuk baris ${index + 1}`;
                    } else if (parseFloat(totalMeter) < parseInt(qty)) {
                        isValid = false;
                        errorMessage = `Total meter (${totalMeter}m) tidak cukup untuk ${qty} roll di baris ${index + 1}. Minimal ${qty}m`;
                    }
                });

                // Validasi upah
                const totalUpahValue = parseFloat(document.getElementById('totalUpahHidden').value) || 0;
                if (totalUpahValue <= 0) {
                    isValid = false;
                    errorMessage = 'Total upah harus lebih dari 0!';
                }

                if (!isValid) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Validasi Error',
                        text: errorMessage,
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Validasi perhitungan upah
                const totalHasil = parseInt(document.getElementById('totalHasilInput').value) || 0;
                const upahPerPotongan = parseFloat(document.getElementById('upahPerPotonganInput').value) || 0;
                const calculated = totalHasil * upahPerPotongan;

                if (Math.abs(calculated - totalUpahValue) > 1) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Perhitungan Upah',
                        html: `Perhitungan upah tidak sesuai!<br>
                              Total Hasil: ${totalHasil} pcs<br>
                              Upah per Potongan: Rp ${formatNumber(upahPerPotongan)}<br>
                              Total Seharusnya: Rp ${formatNumber(calculated)}<br><br>
                              Apakah ingin menggunakan perhitungan ini?`,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Gunakan',
                        cancelButtonText: 'Perbaiki Manual'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Update nilai
                            document.getElementById('totalUpahHidden').value = calculated;
                            document.getElementById('totalUpahDisplay').value = formatRupiah(calculated);
                            document.getElementById('formPenjualanBahan').submit();
                        }
                    });
                }
            });
        }

        // 10. Tambahkan satu bahan secara default saat halaman dimuat
        setTimeout(() => {
            const tambahBahanBtn = document.getElementById('tambahBahan');
            if (tambahBahanBtn) {
                tambahBahanBtn.click();
            }
        }, 100);

        // 11. Inisialisasi hitung upah awal
        hitungTotalUpah();
    });
</script>

</html>