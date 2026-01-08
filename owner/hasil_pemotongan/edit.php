<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Ambil ID dari parameter URL
$id_hasil_potong_fix = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data existing dari database
$existing_data = null;
$existing_items = [];

if ($id_hasil_potong_fix > 0) {
    // Ambil data utama
    $existing_data = query("SELECT * FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix");
    $existing_data = $existing_data[0] ?? null;

    // Ambil detail items
    if ($existing_data) {
        $existing_items = query("SELECT dhpf.*, bb.nama_bahan, bb.harga_per_satuan 
                               FROM detail_hasil_potong_fix dhpf 
                               JOIN bahan_baku bb ON dhpf.id_bahan = bb.id_bahan 
                               WHERE dhpf.id_hasil_potong_fix = $id_hasil_potong_fix");
    }
}

// Redirect jika data tidak ditemukan
if ($id_hasil_potong_fix > 0 && !$existing_data) {
    $_SESSION['error'] = "Data tidak ditemukan!";
    header("Location: list.php");
    exit();
}

$bahan = query("SELECT * FROM bahan_baku WHERE jumlah_stok > 0 ORDER BY nama_bahan");
$produk = query("SELECT * FROM produk ORDER BY nama_produk");
$pemotong  = query("SELECT * FROM pemotong ORDER BY nama_pemotong");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_hasil_potong_fix'])) {

    // VALIDASI INPUT WAJIB
    if (empty($_POST['id_produk']) || empty($_POST['id_pemotong'])) {
        $error = "Semua field wajib diisi!";
    } else {
        $id_produk = intval($_POST['id_produk']);
        $id_pemotong = intval($_POST['id_pemotong']);
        $status_potong = $conn->real_escape_string($_POST['status_potong']);
        $items = $_POST['items'];
        $seri = $conn->real_escape_string($_POST['seri']);
        $tanggal_hasil_potong = $conn->real_escape_string($_POST['tanggal_hasil_potong']);

        // Validasi apakah ada items
        if (empty($items) || count($items) === 0) {
            $error = "Minimal harus ada satu bahan yang dipilih!";
        } else {
            // Validasi duplikasi bahan
            $bahanIds = array_column($items, 'id_bahan');
            if (count($bahanIds) !== count(array_unique($bahanIds))) {
                $error = "Tidak boleh ada bahan yang duplikat dalam satu penjualan bahan!";
            } else {
                // Validasi stok (untuk edit, perlu pertimbangkan stok yang sudah dikembalikan)
                foreach ($items as $item) {
                    $id_bahan = intval($item['id_bahan']);
                    $qty = intval($item['qty']);
                    $bahan_stok = query("SELECT jumlah_stok FROM bahan_baku WHERE id_bahan = $id_bahan")[0];

                    // Untuk edit, hitung selisih dengan quantity sebelumnya
                    $old_qty = 0;
                    if ($existing_data) {
                        foreach ($existing_items as $existing_item) {
                            if ($existing_item['id_bahan'] == $id_bahan) {
                                $old_qty = $existing_item['jumlah'];
                                break;
                            }
                        }
                    }

                    $selisih = $qty - $old_qty;

                    if ($selisih > 0 && $selisih > $bahan_stok['jumlah_stok']) {
                        $error = "Jumlah melebihi stok tersedia untuk bahan ID $id_bahan";
                        break;
                    }
                }

                if (!isset($error)) {
                    $total_harga = 0;
                    $total_hasil = 0;

                    foreach ($items as $item) {
                        $id_bahan = intval($item['id_bahan']);
                        $qty = intval($item['qty']);
                        $harga = floatval($item['harga']);

                        if ($qty <= 0) {
                            $error = "Jumlah bahan tidak boleh nol.";
                            break;
                        }

                        // if ($harga <= 0) {
                        //     $error = "Harga bahan tidak boleh nol atau negatif.";
                        //     break;
                        // }

                        $total_harga += $harga * $qty;
                        $total_hasil += $qty;
                    }

                    // Ambil nilai total_hasil dari form
                    if (isset($_POST['total_hasil']) && !empty($_POST['total_hasil'])) {
                        $total_hasil = intval($_POST['total_hasil']);
                    }

                    if (!isset($error)) {
                        $conn->autocommit(FALSE);
                        try {
                            // Update tabel utama menggunakan prepared statement
                            $stmt = $conn->prepare("UPDATE hasil_potong_fix 
                                                  SET id_produk = ?, 
                                                      id_pemotong = ?, 
                                                      seri = ?, 
                                                      tanggal_hasil_potong = ?, 
                                                      total_hasil = ?, 
                                                      total_harga = ?, 
                                                      status_potong = ?
                                                  WHERE id_hasil_potong_fix = ?");

                            $stmt->bind_param(
                                "iissidsi",
                                $id_produk,
                                $id_pemotong,
                                $seri,
                                $tanggal_hasil_potong,
                                $total_hasil,
                                $total_harga,
                                $status_potong,
                                $id_hasil_potong_fix
                            );

                            if (!$stmt->execute()) {
                                throw new Exception("Gagal update hasil pemotongan: " . $stmt->error);
                            }
                            $stmt->close();

                            // Kembalikan stok bahan yang lama terlebih dahulu
                            foreach ($existing_items as $existing_item) {
                                $sql_restore = "UPDATE bahan_baku 
                                               SET jumlah_stok = jumlah_stok + ? 
                                               WHERE id_bahan = ?";
                                $stmt_restore = $conn->prepare($sql_restore);
                                $stmt_restore->bind_param("ii", $existing_item['jumlah'], $existing_item['id_bahan']);

                                if (!$stmt_restore->execute()) {
                                    throw new Exception("Gagal restore stok bahan: " . $stmt_restore->error);
                                }
                                $stmt_restore->close();
                            }

                            // Hapus detail lama
                            $sql_delete_detail = "DELETE FROM detail_hasil_potong_fix WHERE id_hasil_potong_fix = ?";
                            $stmt_delete = $conn->prepare($sql_delete_detail);
                            $stmt_delete->bind_param("i", $id_hasil_potong_fix);

                            if (!$stmt_delete->execute()) {
                                throw new Exception("Gagal hapus detail lama: " . $stmt_delete->error);
                            }
                            $stmt_delete->close();

                            // Insert detail baru
                            $stmt_detail = $conn->prepare("INSERT INTO detail_hasil_potong_fix 
                                                         (id_hasil_potong_fix, id_bahan, id_produk, id_pemotong, jumlah, harga_satuan, subtotal) 
                                                         VALUES (?, ?, ?, ?, ?, ?, ?)");

                            foreach ($items as $item) {
                                $id_bahan = intval($item['id_bahan']);
                                $qty = intval($item['qty']);
                                $harga = floatval($item['harga']);
                                $subtotal = $harga * $qty;

                                $stmt_detail->bind_param("iiiiddd", $id_hasil_potong_fix, $id_bahan, $id_produk, $id_pemotong, $qty, $harga, $subtotal);

                                if (!$stmt_detail->execute()) {
                                    throw new Exception("Gagal menyimpan detail hasil pemotongan: " . $stmt_detail->error);
                                }

                                // Update stok bahan dengan quantity baru
                                $sql_update_stok = "UPDATE bahan_baku 
                                                  SET jumlah_stok = jumlah_stok - ? 
                                                  WHERE id_bahan = ?";
                                $stmt_update = $conn->prepare($sql_update_stok);
                                $stmt_update->bind_param("ii", $qty, $id_bahan);

                                if (!$stmt_update->execute()) {
                                    throw new Exception("Gagal update stok bahan: " . $stmt_update->error);
                                }
                                $stmt_update->close();
                            }

                            $stmt_detail->close();
                            $conn->commit();
                            $conn->autocommit(TRUE);

                            $_SESSION['success'] = "Data hasil pemotongan berhasil diupdate";
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


                <div class="card p-3">
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
                    <!-- /Tampilkan pesan error atau success -->


                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <form method="post" id="formPenjualanBahan">
                                    <input type="hidden" name="id_hasil_potong_fix" value="<?= $id_hasil_potong_fix ?>">

                                    <div class="card border border-dark shadow-sm rounded-3">
                                        <div class="card-body">
                                            <?php if (isset($error)): ?>
                                                <div class="alert error"><?= $error ?></div>
                                            <?php endif; ?>

                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-3">
                                                    <label class="form-label">Nama Produk</label>
                                                    <select name="id_produk" class="form-control" required>
                                                        <option value="">-- Pilih Produk --</option>
                                                        <?php foreach ($produk as $p): ?>
                                                            <option value="<?= $p['id_produk'] ?>"
                                                                <?= (isset($_POST['id_produk']) && $_POST['id_produk'] == $p['id_produk']) ||
                                                                    ($existing_data && $existing_data['id_produk'] == $p['id_produk']) ? 'selected' : '' ?>>
                                                                <?= $p['nama_produk'] ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label">Nama Pemotong</label>
                                                    <select name="id_pemotong" class="form-control" required>
                                                        <option value="">-- Pilih Pemotong --</option>
                                                        <?php foreach ($pemotong as $p): ?>
                                                            <option value="<?= $p['id_pemotong'] ?>"
                                                                <?= (isset($_POST['id_pemotong']) && $_POST['id_pemotong'] == $p['id_pemotong']) ||
                                                                    ($existing_data && $existing_data['id_pemotong'] == $p['id_pemotong']) ? 'selected' : '' ?>>
                                                                <?= $p['nama_pemotong'] ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label">Seri</label>
                                                    <input type="text" name="seri" class="form-control"
                                                        value="<?= isset($_POST['seri']) ? $_POST['seri'] : ($existing_data ? $existing_data['seri'] : '') ?>"
                                                        required>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label">Tanggal Hasil Potong</label>
                                                    <input type="date" name="tanggal_hasil_potong" class="form-control"
                                                        value="<?= isset($_POST['tanggal_hasil_potong']) ? $_POST['tanggal_hasil_potong'] : ($existing_data ? $existing_data['tanggal_hasil_potong'] : date('Y-m-d')) ?>"
                                                        required>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label">Total Hasil (Jumlah Potongan)</label>
                                                    <div class="input-group">
                                                        <input type="number" name="total_hasil" class="form-control" min="1"
                                                            value="<?= isset($_POST['total_hasil']) ? $_POST['total_hasil'] : ($existing_data ? $existing_data['total_hasil'] : '') ?>"
                                                            required>
                                                        <span class="input-group-text">Pcs</span>
                                                    </div>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label">Status Potong</label>
                                                    <select name="status_potong" class="form-control" required>
                                                        <option value="diproses" <?= (isset($_POST['status_potong']) && $_POST['status_potong'] == 'diproses') ||
                                                                                        ($existing_data && $existing_data['status_potong'] == 'diproses') ? 'selected' : '' ?>>Diproses</option>
                                                        <option value="selesai" <?= (isset($_POST['status_potong']) && $_POST['status_potong'] == 'selesai') ||
                                                                                    ($existing_data && $existing_data['status_potong'] == 'selesai') ? 'selected' : '' ?>>Selesai</option>
                                                    </select>
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
                                                        <th>Bahan</th>
                                                        <th>Stok</th>
                                                        <th>Qty</th>
                                                        <th>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="bahanContainer">
                                                    <?php if ($existing_data && !empty($existing_items)): ?>
                                                        <?php foreach ($existing_items as $index => $item): ?>
                                                            <?php
                                                            $current_stok = query("SELECT jumlah_stok FROM bahan_baku WHERE id_bahan = {$item['id_bahan']}")[0]['jumlah_stok'];
                                                            // Tambahkan stok yang sedang digunakan untuk tampilan yang benar
                                                            $stok_tersedia = $current_stok + $item['jumlah'];
                                                            $rowId = 'existing_' . $index;
                                                            ?>
                                                            <tr id="row-<?= $rowId ?>">
                                                                <td>
                                                                    <select name="items[<?= $rowId ?>][id_bahan]" class="form-control select-bahan" required>
                                                                        <option value="">Pilih Bahan</option>
                                                                        <?php foreach ($bahan as $b): ?>
                                                                            <option value="<?= $b['id_bahan'] ?>"
                                                                                <?= $item['id_bahan'] == $b['id_bahan'] ? 'selected' : '' ?>
                                                                                data-harga="<?= $b['harga_per_satuan'] ?>">
                                                                                <?= $b['nama_bahan'] ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </td>
                                                                <td class="stok"><?= $stok_tersedia ?></td>
                                                                <td>
                                                                    <small class="text-danger stok-error" style="display:none">Melebihi stok tersedia</small>
                                                                    <div class="input-group">
                                                                        <input type="number" name="items[<?= $rowId ?>][qty]" class="form-control qty" min="1"
                                                                            value="<?= $item['jumlah'] ?>" required>
                                                                        <span class="input-group-text">Roll</span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <button type="button" class="btn btn-sm btn-danger hapus-bahan" data-row="<?= $rowId ?>">
                                                                        Hapus
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>

                                            <button type="button" class="btn btn-secondary mt-3" id="tambahBahan">+ Tambah Bahan</button>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" name="update_hasil_potong_fix" class="btn btn-primary">
                                            <?= $id_hasil_potong_fix > 0 ? 'Update' : 'Simpan' ?> Hasil Pemotongan
                                        </button>
                                        <a href="detail.php?id=<?= $id_hasil_potong_fix ?>" class="btn btn-danger">Batal</a>
                                    </div>
                                </form>
                            </div>
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

<!-- / Layout wrapper -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const bahanData = <?= json_encode($bahan) ?>;
    let selectedBahan = []; // Menyimpan ID bahan yang sudah dipilih

    document.getElementById('tambahBahan').addEventListener('click', function() {
        const container = document.getElementById('bahanContainer');
        const rowId = Date.now();

        // Filter bahan yang belum dipilih
        const availableBahan = bahanData.filter(b => !selectedBahan.includes(b.id_bahan));

        if (availableBahan.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Oops!',
                text: 'Semua jenis bahan sudah ditambahkan atau tidak ada stok tersedia',
                confirmButtonText: 'Oke'
            });
            return;
        }

        let options = '<option value="">Pilih Bahan</option>';
        availableBahan.forEach(bahan => {
            options += `<option value="${bahan.id_bahan}" data-harga="${bahan.harga_per_satuan}">${bahan.nama_bahan}</option>`;
        });

        const row = document.createElement('tr');
        row.id = `row-${rowId}`;
        row.innerHTML = `
                <td>
                    <select name="items[${rowId}][id_bahan]" class="form-control select-bahan" required>
                        ${options}
                    </select>
                </td>
                <td>
                    <div class="input-group" hidden>
                        <span class="input-group-text">Rp</span>
                        <input type="number" name="items[${rowId}][harga]" class="form-control harga-input" min="1" value="" required>
                    </div>
                </td>
                <td class="stok">0</td>
                <td>
                <small class="text-danger stok-error" style="display:none">Melebihi stok tersedia</small>
                    <div class="input-group">
                        <input type="number" name="items[${rowId}][qty]" class="form-control qty" min="1" value="1" required>
                        <span class="input-group-text">Roll</span>
                    </div>
                </td>
                <td class="currency-format subtotal" hidden>0</td>
                <td><button type="button" class="btn btn-sm btn-danger hapus-bahan" data-row="${rowId}">Hapus</button></td>
            `;
        container.appendChild(row);
        initRowEvents(rowId);
        hitungTotal();
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('hapus-bahan')) {
            const rowId = e.target.dataset.row;
            const row = document.getElementById(`row-${rowId}`);
            const selectedBahanId = row.querySelector('.select-bahan').value;

            // Hapus bahan dari daftar yang sudah dipilih
            if (selectedBahanId) {
                selectedBahan = selectedBahan.filter(id => id != selectedBahanId);
            }

            row.remove();
            hitungTotal();
            updateBahanDropdowns();
        }
    });

    function initRowEvents(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const select = row.querySelector('.select-bahan');
        const hargaInput = row.querySelector('.harga-input');
        const qtyInput = row.querySelector('.qty');
        const stokError = row.querySelector('.stok-error');

        select.addEventListener('change', function() {
            const previousBahanId = select.dataset.previousValue;

            if (previousBahanId) {
                selectedBahan = selectedBahan.filter(id => id != previousBahanId);
            }

            const newBahanId = this.value;
            const selectedOption = this.options[this.selectedIndex];

            if (newBahanId) {
                selectedBahan.push(newBahanId);
                select.dataset.previousValue = newBahanId;

                const bahan = bahanData.find(p => p.id_bahan == newBahanId);
                if (bahan) {
                    // Set nilai default harga dari data bahan
                    hargaInput.value = bahan.harga_per_satuan;
                    row.querySelector('.stok').textContent = bahan.jumlah_stok;
                    qtyInput.max = bahan.jumlah_stok;
                    qtyInput.value = 1;
                    hitungSubtotal(rowId);
                }
            } else {
                select.dataset.previousValue = '';
                hargaInput.value = 0;
                hitungSubtotal(rowId);
            }

            updateBahanDropdowns();
        });

        // Event listener untuk input harga
        hargaInput.addEventListener('input', function() {
            hitungSubtotal(rowId);
        });

        qtyInput.addEventListener('input', function() {
            const maxStok = parseInt(qtyInput.max) || 0;
            const enteredQty = parseInt(this.value) || 0;

            if (enteredQty > maxStok) {
                stokError.style.display = 'block';
                this.value = maxStok;
            } else {
                stokError.style.display = 'none';
            }

            hitungSubtotal(rowId);
        });
    }

    function updateBahanDropdowns() {
        document.querySelectorAll('.select-bahan').forEach(select => {
            const currentValue = select.value;
            const rowId = select.closest('tr').id.split('-')[1];

            // Filter bahan yang belum dipilih ATAU bahan yang sedang dipilih di dropdown ini
            const availableBahan = bahanData.filter(p =>
                !selectedBahan.includes(p.id_bahan) || p.id_bahan == currentValue
            );

            let options = '<option value="">Pilih Bahan</option>';
            availableBahan.forEach(bahan => {
                const selected = bahan.id_bahan == currentValue ? 'selected' : '';
                options += `<option value="${bahan.id_bahan}" data-harga="${bahan.harga_per_satuan}" ${selected}>${bahan.nama_bahan}</option>`;
            });

            select.innerHTML = options;
        });
    }

    function formatCurrency(amount) {
        return 'Rp ' + Number(amount).toLocaleString('id-ID');
    }

    function hitungSubtotal(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const harga = parseFloat(row.querySelector('.harga-input').value) || 0;
        const qty = parseInt(row.querySelector('.qty').value) || 0;
        const subtotal = harga * qty;
        row.querySelector('.subtotal').textContent = formatCurrency(subtotal);
        hitungTotal();
    }

    function hitungTotal() {
        let total = 0;
        document.querySelectorAll('#bahanContainer tr').forEach(row => {
            const subtotal = parseFloat(row.querySelector('.subtotal').textContent.replace(/[^0-9]/g, '')) || 0;
            total += subtotal;
        });
        document.getElementById('totalHarga').textContent = formatCurrency(total);
    }

    // Tambahkan satu bahan secara default saat halaman dimuat
    // document.addEventListener('DOMContentLoaded', function() {
    //     document.getElementById('tambahBahan').click();
    // });

    // function hitungTotalHasil() {
    //     let totalHasil = 0;
    //     document.querySelectorAll('.qty').forEach(input => {
    //         totalHasil += parseInt(input.value) || 0;
    //     });

    //     // Set nilai ke input total_hasil
    //     const totalHasilInput = document.querySelector('input[name="total_hasil"]');
    //     if (totalHasilInput) {
    //         totalHasilInput.value = totalHasil;
    //     }

    //     return totalHasil;
    // }

    function hitungSubtotal(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const harga = parseFloat(row.querySelector('.harga-input').value) || 0;
        // const qty = parseInt(row.querySelector('.qty').value) || 0;
        const subtotal = harga * qty;
        row.querySelector('.subtotal').textContent = formatCurrency(subtotal);
        hitungTotal();
        hitungTotalHasil(); // Hitung total hasil setiap kali subtotal berubah
    }

    function hitungTotal() {
        let total = 0;
        document.querySelectorAll('#bahanContainer tr').forEach(row => {
            const subtotal = parseFloat(row.querySelector('.subtotal').textContent.replace(/[^0-9]/g, '')) || 0;
            total += subtotal;
        });
        document.getElementById('totalHarga').textContent = formatCurrency(total);
    }

    // Event listener untuk input quantity
    // document.addEventListener('input', function(e) {
    //     if (e.target.classList.contains('qty')) {
    //         hitungTotalHasil();
    //     }
    // });

    // Event listener untuk perubahan bahan (quantity akan direset)
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('select-bahan')) {
            // Beri jeda sedikit untuk memastikan quantity sudah di-set
            setTimeout(hitungTotalHasil, 100);
        }
    });

    // Event listener untuk penghapusan bahan
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('hapus-bahan')) {
            // Beri jeda sedikit untuk memastikan baris sudah dihapus
            setTimeout(hitungTotalHasil, 100);
        }
    });

    // Tambahkan satu bahan secara default saat halaman dimuat
    // document.addEventListener('DOMContentLoaded', function() {
    //     document.getElementById('tambahBahan').click();
    //     // Hitung total hasil setelah halaman dimuat
    //     setTimeout(hitungTotalHasil, 500);
    // });
</script>


</html>