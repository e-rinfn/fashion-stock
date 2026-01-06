<?php
// Aktifkan error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Ambil data bahan (koko) dengan informasi stok
$koko = query("SELECT * FROM koko WHERE stok > 0 ORDER BY nama_koko");
$petugas_finishing = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");

// Ambil data penjahit dari tabel penjahit
$penjahit = query("SELECT * FROM penjahit ORDER BY nama_penjahit");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_hasil_kirim_finishing'])) {

    // VALIDASI INPUT WAJIB
    if (empty($_POST['id_petugas_finishing'])) {
        $error = "Petugas Finishing wajib diisi!";
    } else {
        $id_petugas_finishing = intval($_POST['id_petugas_finishing']);
        // Handle multiple penjahit - gabungkan dengan koma (opsional)
        if (isset($_POST['nama_penjahit']) && is_array($_POST['nama_penjahit']) && !empty($_POST['nama_penjahit']) && $_POST['nama_penjahit'][0] !== '-') {
            $nama_penjahit_array = array_filter($_POST['nama_penjahit'], function ($name) {
                return $name !== '-' && !empty($name);
            });
            $nama_penjahit_array = array_map(function ($name) use ($conn) {
                return $conn->real_escape_string($name);
            }, $nama_penjahit_array);
            $nama_penjahit = !empty($nama_penjahit_array) ? implode(', ', $nama_penjahit_array) : '-';
        } else {
            $nama_penjahit = '-'; // Default jika tidak ada penjahit yang dipilih
        }
        $status_finishing = $conn->real_escape_string($_POST['status_finishing']);
        $items = $_POST['items'];
        $tanggal_kirim_finishing = $conn->real_escape_string($_POST['tanggal_kirim_finishing']);
        $keterangan = isset($_POST['keterangan']) ? $conn->real_escape_string($_POST['keterangan']) : '';

        // Ambil nilai total_kirim dari form (jumlah total pcs yang dikirim)
        if (isset($_POST['total_kirim']) && !empty($_POST['total_kirim'])) {
            $total_kirim = intval($_POST['total_kirim']);
        } else {
            // Hitung otomatis dari jumlah semua items
            $total_kirim = 0;
            if (isset($items)) {
                foreach ($items as $item) {
                    $total_kirim += intval($item['qty']);
                }
            }
        }

        // Validasi duplikasi penjahit untuk tanggal yang sama (opsional)

        $check_duplicate = true; // Asumsi tidak ada duplikasi
        // Jika tabel punya kolom untuk penjahit, bisa validasi di sini

        if ($check_duplicate) {
            // Validasi duplikasi bahan
            $bahanIds = array_column($items, 'id_bahan');
            if (count($bahanIds) !== count(array_unique($bahanIds))) {
                $error = "Tidak boleh ada bahan yang duplikat dalam satu pengiriman!";
            } else {
                // Validasi stok (HANYA STOK PCS)
                foreach ($items as $item) {
                    $id_bahan = intval($item['id_bahan']);
                    $qty = intval($item['qty']);

                    // Ambil data stok bahan
                    $bahan_stok = query("SELECT stok, nama_koko FROM koko WHERE id_koko = $id_bahan")[0];

                    if ($qty > $bahan_stok['stok']) {
                        $error = "Jumlah pcs melebihi stok tersedia untuk bahan " . $bahan_stok['nama_koko'];
                        break;
                    }

                    if ($qty <= 0) {
                        $error = "Jumlah bahan tidak boleh nol atau negatif.";
                        break;
                    }
                }

                if (!isset($error)) {
                    $conn->autocommit(FALSE);
                    try {
                        // Pilih produk pertama sebagai referensi
                        $main_id_produk = 0;
                        if (isset($items) && count($items) > 0) {
                            $first_item = reset($items);
                            $first_koko_id = intval($first_item['id_bahan']);
                            $koko_data = query("SELECT id_produk FROM koko WHERE id_koko = $first_koko_id")[0];
                            $main_id_produk = $koko_data['id_produk'] ?? 0;
                        }

                        // Cek struktur tabel hasil_kirim_finishing
                        // Jika tabel punya kolom nama_penjahit atau penjahit
                        $table_check = $conn->query("SHOW COLUMNS FROM hasil_kirim_finishing LIKE '%penjahit%'");
                        $has_penjahit_column = $table_check->num_rows > 0;

                        // Cek apakah tabel punya kolom keterangan
                        $table_check_ket = $conn->query("SHOW COLUMNS FROM hasil_kirim_finishing LIKE 'keterangan'");
                        $has_keterangan_column = $table_check_ket->num_rows > 0;

                        // Insert hasil kirim finishing utama - sesuaikan dengan struktur tabel
                        if ($has_penjahit_column && $has_keterangan_column) {
                            // Jika tabel punya kolom penjahit DAN keterangan
                            $sql_insert = "INSERT INTO hasil_kirim_finishing 
                                (id_petugas_finishing, id_produk, tanggal_kirim_finishing, total_kirim, status_finishing, nama_penjahit, keterangan) 
                                VALUES ($id_petugas_finishing, $main_id_produk, '$tanggal_kirim_finishing', $total_kirim, '$status_finishing', '$nama_penjahit', '$keterangan')";
                        } elseif ($has_penjahit_column) {
                            // Jika tabel punya kolom penjahit SAJA
                            $sql_insert = "INSERT INTO hasil_kirim_finishing 
                                (id_petugas_finishing, id_produk, tanggal_kirim_finishing, total_kirim, status_finishing, nama_penjahit) 
                                VALUES ($id_petugas_finishing, $main_id_produk, '$tanggal_kirim_finishing', $total_kirim, '$status_finishing', '$nama_penjahit')";
                        } elseif ($has_keterangan_column) {
                            // Jika tabel punya kolom keterangan SAJA
                            $sql_insert = "INSERT INTO hasil_kirim_finishing 
                                (id_petugas_finishing, id_produk, tanggal_kirim_finishing, total_kirim, status_finishing, keterangan) 
                                VALUES ($id_petugas_finishing, $main_id_produk, '$tanggal_kirim_finishing', $total_kirim, '$status_finishing', '$keterangan')";
                        } else {
                            // Jika tabel TIDAK punya kedua kolom
                            $sql_insert = "INSERT INTO hasil_kirim_finishing 
                                (id_petugas_finishing, id_produk, tanggal_kirim_finishing, total_kirim, status_finishing) 
                                VALUES ($id_petugas_finishing, $main_id_produk, '$tanggal_kirim_finishing', $total_kirim, '$status_finishing')";
                        }

                        if (!$conn->query($sql_insert)) {
                            throw new Exception("Gagal menyimpan hasil kirim finishing: " . $conn->error);
                        }

                        $id_hasil_kirim_finishing = $conn->insert_id;

                        // Insert detail hasil kirim finishing
                        foreach ($items as $item) {
                            $id_bahan = intval($item['id_bahan']);
                            $qty = intval($item['qty']);

                            // Ambil id_produk dari tabel koko untuk detail
                            $koko_data = query("SELECT id_produk FROM koko WHERE id_koko = $id_bahan")[0];
                            $detail_id_produk = $koko_data['id_produk'] ?? 0;

                            // Gunakan prepared statement untuk detail
                            $stmt_detail = $conn->prepare("INSERT INTO detail_hasil_kirim_finishing 
                                (id_hasil_kirim_finishing, id_koko, id_produk, id_petugas_finishing, jumlah) 
                                VALUES (?, ?, ?, ?, ?)");

                            if (!$stmt_detail) {
                                throw new Exception("Gagal prepare statement: " . $conn->error);
                            }

                            $stmt_detail->bind_param(
                                "iiiii",
                                $id_hasil_kirim_finishing,
                                $id_bahan,
                                $detail_id_produk,
                                $id_petugas_finishing,
                                $qty
                            );

                            if (!$stmt_detail->execute()) {
                                throw new Exception("Gagal menyimpan detail hasil kirim finishing: " . $stmt_detail->error);
                            }
                            $stmt_detail->close();

                            // Update stok (HANYA stok pcs) - kurangi stok karena dikirim
                            $sql_update = "UPDATE koko SET stok = stok - $qty WHERE id_koko = $id_bahan";

                            if (!$conn->query($sql_update)) {
                                throw new Exception("Gagal update stok bahan: " . $conn->error);
                            }
                        }

                        $conn->commit();
                        $conn->autocommit(TRUE);

                        $_SESSION['success'] = "Data kirim finishing berhasil disimpan! Total " . $total_kirim . " pcs dikirim.";
                        header("Location: finishing.php");
                        exit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        $conn->autocommit(TRUE);
                        $error = "Error: " . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    /* Paksa SweetAlert berada di atas segalanya */
    .swal2-container {
        z-index: 99999 !important;
    }

    /* Styling untuk Select2 multi-select */
    .select2-container--bootstrap-5 .select2-selection--multiple {
        min-height: 38px;
    }

    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        /* background-color: #0d6efd; */
        color: white;
        border: none;
        padding: 2px 8px;
        margin: 3px;
    }

    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
        color: white;
        margin-right: 5px;
    }

    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ff6b6b;
    }

    .error {
        color: #dc3545;
        background-color: #f8d7da;
        border-color: #f5c6cb;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
    }

    .stok-warning {
        color: #dc3545;
        font-size: 0.8rem;
    }

    .stok-info {
        font-size: 0.9rem;
    }

    .produk-info {
        background-color: #f0f8ff;
        padding: 5px;
        border-radius: 4px;
        font-size: 0.9rem;
        margin-top: 5px;
    }

    .produk-multiple {
        background-color: #fff3cd;
        border: 1px solid #ffc107;
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
                    <h2>Tambah Kirim Finishing Koko</h2>
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
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="card-body">
                        <div class="row">
                            <form method="post" id="formPenjualanBahan">
                                <div class="card border border-dark shadow-sm rounded-3">
                                    <div class="card-body">
                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-4">
                                                <label class="form-label">Nama Petugas Finishing <span class="text-danger">*</span></label>
                                                <select name="id_petugas_finishing" class="form-control" required>
                                                    <option value="">-- Pilih Petugas Finishing --</option>
                                                    <?php foreach ($petugas_finishing as $p): ?>
                                                        <option value="<?= $p['id_petugas_finishing'] ?>" <?= isset($_POST['id_petugas_finishing']) && $_POST['id_petugas_finishing'] == $p['id_petugas_finishing'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($p['nama_petugas']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Nama Penjahit</label>
                                                <select name="nama_penjahit[]" id="selectPenjahit" class="form-control" multiple="multiple">
                                                    <option value="-">- (Tidak Ada Penjahit)</option>
                                                    <?php foreach ($penjahit as $j): ?>
                                                        <option value="<?= htmlspecialchars($j['nama_penjahit']) ?>"
                                                            <?php
                                                            if (isset($_POST['nama_penjahit']) && is_array($_POST['nama_penjahit'])) {
                                                                echo in_array($j['nama_penjahit'], $_POST['nama_penjahit']) ? 'selected' : '';
                                                            }
                                                            ?>>
                                                            <?= htmlspecialchars($j['nama_penjahit']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">Opsional - bisa pilih lebih dari satu atau kosongkan</small>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Tanggal Kirim Finishing <span class="text-danger">*</span></label>
                                                <input type="date" name="tanggal_kirim_finishing" class="form-control"
                                                    value="<?= isset($_POST['tanggal_kirim_finishing']) ? htmlspecialchars($_POST['tanggal_kirim_finishing']) : date('Y-m-d') ?>"
                                                    required>
                                            </div>
                                        </div>

                                        <div class="row mt-3 g-3 align-items-center">
                                            <div class="col-md-4">
                                                <label class="form-label">Total Kirim Pcs (Otomatis)</label>
                                                <div class="input-group">
                                                    <input type="number" name="total_kirim" class="form-control" min="1"
                                                        value="<?= isset($_POST['total_kirim']) ? htmlspecialchars($_POST['total_kirim']) : '' ?>"
                                                        id="totalKirimInput" readonly>
                                                    <span class="input-group-text">Pcs</span>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Status Finishing (Otomatis)</label>
                                                <select name="status_finishing"
                                                    class="form-control"
                                                    style="pointer-events:none; background:#e9ecef;">
                                                    <option value="pengiriman" selected>Pengiriman</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Produk yang Dihasilkan (Otomatis)</label>
                                                <div class="produk-info" id="produkPreview">
                                                    Pilih koko terlebih dahulu
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mt-3 g-3">
                                            <div class="col-md-12">
                                                <label class="form-label">Keterangan</label>
                                                <textarea name="keterangan" class="form-control" rows="3" placeholder="Masukkan keterangan (opsional)"><?= isset($_POST['keterangan']) ? htmlspecialchars($_POST['keterangan']) : '' ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mt-3 border border-dark shadow-sm rounded-3">
                                    <div class="card-header">
                                        <h3>Daftar Koko yang Dikirim</h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table" id="tabelBahan">
                                            <thead>
                                                <tr class="text-center">
                                                    <th>Koko Mentah <span class="text-danger">*</span></th>
                                                    <th>Produk Terkait</th>
                                                    <th>Stok Tersedia</th>
                                                    <th>Pcs <span class="text-danger">*</span></th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bahanContainer"></tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4" class="text-right"><strong>Total Pcs Dikirim:</strong></td>
                                                    <td class="text-center"><span id="totalPcsKirim">0</span> Pcs</td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        <button type="button" class="btn btn-secondary mt-3" id="tambahBahan">
                                            <i class="ti ti-plus"></i> Tambah Koko
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="simpan_hasil_kirim_finishing" class="btn btn-primary">
                                        <i class="ti ti-file-plus"></i> Simpan Kirim Finishing
                                    </button>
                                    <a href="finishing.php" class="btn btn-danger">
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
<script>
    // Gunakan data koko dari PHP dengan join produk
    const bahanData = <?= json_encode(query("SELECT k.*, p.nama_produk FROM koko k LEFT JOIN produk p ON k.id_produk = p.id_produk WHERE k.stok > 0 ORDER BY k.nama_koko")) ?>;
    let selectedBahans = [];
    let totalPcsKirim = 0;

    // Fungsi untuk update preview produk
    function updateProdukPreview() {
        const produkPreview = document.getElementById('produkPreview');

        const allProduks = new Set();
        document.querySelectorAll('#bahanContainer tr').forEach(row => {
            const select = row.querySelector('.select-bahan');
            if (select.value) {
                const bahan = bahanData.find(b => b.id_koko == select.value);
                if (bahan && bahan.nama_produk) {
                    allProduks.add(bahan.nama_produk);
                }
            }
        });

        if (allProduks.size === 0) {
            produkPreview.innerHTML = 'Pilih koko terlebih dahulu';
            produkPreview.className = 'produk-info';
        } else if (allProduks.size === 1) {
            produkPreview.innerHTML = `<strong>${Array.from(allProduks)[0]}</strong>`;
            produkPreview.className = 'produk-info';
        } else {
            produkPreview.innerHTML = `<strong>Multiple Produk:</strong><br>${Array.from(allProduks).join(', ')}`;
            produkPreview.className = 'produk-info produk-multiple';
        }
    }

    // Tombol Tambah Koko
    document.getElementById('tambahBahan').addEventListener('click', function() {
        const container = document.getElementById('bahanContainer');
        const rowId = Date.now();

        // Filter koko yang belum dipilih
        const availableBahans = bahanData.filter(b => !selectedBahans.includes(b.id_koko));

        if (availableBahans.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Tidak ada koko tersedia',
                text: 'Semua koko sudah ditambahkan atau stok habis',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Buat opsi dropdown
        let options = `<option value="">Pilih Koko</option>`;
        availableBahans.forEach(koko => {
            const produkNama = koko.nama_produk || 'Tidak terkait produk';
            const produkLabel = koko.nama_produk ? ` → ${koko.nama_produk}` : ' (Tidak terkait produk)';
            const stokLabel = `${koko.nama_koko} — Stok: ${koko.stok} pcs${produkLabel}`;

            options += `
                <option 
                    value="${koko.id_koko}" 
                    data-stok="${koko.stok}"
                    data-produk-id="${koko.id_produk || ''}"
                    data-produk-nama="${produkNama}"
                >
                    ${stokLabel}
                </option>
            `;
        });

        // Tambahkan baris baru ke tabel
        const row = document.createElement('tr');
        row.id = `row-${rowId}`;
        row.innerHTML = `
            <td>
                <select name="items[${rowId}][id_bahan]" class="form-control select-bahan" required>
                    ${options}
                </select>
            </td>
            <td class="produk-info-cell">
                <span class="text-center produk-nama">-</span>
            </td>
            <td class="stok-info">
                <span class="stok-pcs">0</span> Pcs
            </td>
            <td>
                <div class="input-group">
                    <input type="number" name="items[${rowId}][qty]" class="form-control qty" min="1" value="1" required>
                    <span class="input-group-text">Pcs</span>
                </div>
                <small class="stok-warning stok-pcs-warning" style="display:none">Melebihi stok pcs</small>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger hapus-bahan" data-row="${rowId}">
                    <i class="ti ti-trash"></i>
                </button>
            </td>
        `;
        container.appendChild(row);
        initRowEvents(rowId);
        updateTotalPcs();
        updateProdukPreview();
    });

    // Hapus koko dari daftar
    document.addEventListener('click', function(e) {
        if (e.target.closest('.hapus-bahan')) {
            const button = e.target.closest('.hapus-bahan');
            const rowId = button.dataset.row;
            const row = document.getElementById(`row-${rowId}`);
            const select = row.querySelector('.select-bahan');

            if (select.value) {
                selectedBahans = selectedBahans.filter(id => id != select.value);
            }

            row.remove();
            updateTotalPcs();
            updateBahanDropdowns();
            updateProdukPreview();
        }
    });

    // Event untuk setiap baris
    function initRowEvents(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const select = row.querySelector('.select-bahan');
        const qtyInput = row.querySelector('.qty');
        const stokPcsDisplay = row.querySelector('.stok-pcs');
        const stokPcsWarning = row.querySelector('.stok-pcs-warning');
        const produkNamaCell = row.querySelector('.produk-nama');

        select.addEventListener('change', function() {
            const prevId = select.dataset.previousValue;
            if (prevId) selectedBahans = selectedBahans.filter(id => id != prevId);

            const newId = this.value;
            if (newId) {
                selectedBahans.push(newId);
                select.dataset.previousValue = newId;

                const bahan = bahanData.find(b => b.id_koko == newId);
                if (bahan) {
                    stokPcsDisplay.textContent = bahan.stok;

                    // Tampilkan produk terkait
                    produkNamaCell.textContent = bahan.nama_produk || '-';
                    if (bahan.nama_produk) {
                        produkNamaCell.className = 'produk-nama';
                        produkNamaCell.title = bahan.nama_produk;
                    } else {
                        produkNamaCell.className = 'produk-nama text-warning';
                        produkNamaCell.title = 'Koko tidak terkait produk';
                    }

                    qtyInput.value = 1;
                    qtyInput.max = bahan.stok;

                    validateStok(rowId);
                    updateTotalPcs();
                    updateProdukPreview();
                }
            } else {
                select.dataset.previousValue = '';
                stokPcsDisplay.textContent = '0';
                produkNamaCell.textContent = '-';
                qtyInput.value = 1;
                qtyInput.max = '';
            }

            updateBahanDropdowns();
        });

        qtyInput.addEventListener('input', () => {
            validateStok(rowId);
            updateTotalPcs();
        });

        // Trigger change event jika sudah ada value
        if (select.value) select.dispatchEvent(new Event('change'));
    }

    // Fungsi validasi stok
    function validateStok(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const select = row.querySelector('.select-bahan');
        const qtyInput = row.querySelector('.qty');
        const stokPcsWarning = row.querySelector('.stok-pcs-warning');

        if (!select.value) return;

        const bahan = bahanData.find(b => b.id_koko == select.value);
        if (!bahan) return;

        const qty = parseInt(qtyInput.value) || 0;

        // Validasi stok pcs
        if (qty > bahan.stok) {
            stokPcsWarning.style.display = 'block';
            qtyInput.value = bahan.stok;
            updateTotalPcs();
        } else {
            stokPcsWarning.style.display = 'none';
        }
    }

    // Fungsi update total pcs yang dikirim
    function updateTotalPcs() {
        totalPcsKirim = 0;
        document.querySelectorAll('#bahanContainer tr').forEach(row => {
            const qtyInput = row.querySelector('.qty');
            const qty = parseInt(qtyInput.value) || 0;
            totalPcsKirim += qty;
        });

        // Update tampilan total pcs
        document.getElementById('totalPcsKirim').textContent = totalPcsKirim;

        // Update input total_kirim
        document.getElementById('totalKirimInput').value = totalPcsKirim;
    }

    // Update semua dropdown koko
    function updateBahanDropdowns() {
        document.querySelectorAll('.select-bahan').forEach(select => {
            const currentValue = select.value;

            const availableBahans = bahanData.filter(bahan =>
                !selectedBahans.includes(bahan.id_koko) || bahan.id_koko == currentValue
            );

            let options = '<option value="">Pilih Koko</option>';
            availableBahans.forEach(bahan => {
                const produkLabel = bahan.nama_produk ? ` → ${bahan.nama_produk}` : ' (Tidak terkait produk)';
                const stokLabel = `${bahan.nama_koko} (Stok: ${bahan.stok} Pcs)${produkLabel}`;
                const selected = bahan.id_koko == currentValue ? 'selected' : '';
                options += `<option value="${bahan.id_koko}" 
                            data-stok="${bahan.stok}"
                            data-produk-id="${bahan.id_produk || ''}"
                            data-produk-nama="${bahan.nama_produk || 'Tidak ada'}"
                            ${selected}>
                            ${stokLabel}
                        </option>`;
            });

            select.innerHTML = options;
        });
    }

    // Validasi form sebelum submit
    document.getElementById('formPenjualanBahan').addEventListener('submit', function(e) {
        const rows = document.querySelectorAll('#bahanContainer tr');

        // Validasi minimal satu koko
        if (rows.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Tidak ada koko',
                text: 'Minimal harus ada satu koko yang dikirim',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Validasi setiap koko
        let isValid = true;
        let errorMessage = '';

        rows.forEach((row, index) => {
            const select = row.querySelector('.select-bahan');
            const qty = row.querySelector('.qty').value;

            if (!select.value) {
                isValid = false;
                errorMessage = `Pilih koko untuk baris ${index + 1}`;
            } else if (!qty || qty <= 0) {
                isValid = false;
                errorMessage = `Jumlah pcs tidak valid untuk baris ${index + 1}`;
            }
        });

        if (!isValid) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validasi Error',
                text: errorMessage,
                confirmButtonText: 'OK'
            });
        }
    });

    // Tambahkan satu koko secara default saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('tambahBahan').click();

        // Inisialisasi Select2 untuk multi-select penjahit
        $('#selectPenjahit').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Pilih Penjahit (opsional) --',
            allowClear: true,
            width: '100%',
            closeOnSelect: false
        });
    });
</script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

</html>