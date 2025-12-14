<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

$bahan = query("SELECT * FROM bahan_baku WHERE jumlah_stok > 0 ORDER BY nama_bahan");
$produk = query("SELECT * FROM produk ORDER BY nama_produk");
$pemotong  = query("SELECT * FROM pemotong ORDER BY nama_pemotong");

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
    if (empty($_POST['id_produk']) || empty($_POST['id_pemotong']) || empty($_POST['seri'])) {
        $error = "Semua field wajib diisi!";
    } else {
        $id_produk = intval($_POST['id_produk']);
        $id_pemotong = intval($_POST['id_pemotong']);
        $status_potong = $conn->real_escape_string($_POST['status_potong']);
        $items = $_POST['items'];
        $seri = $conn->real_escape_string($_POST['seri']);
        $tanggal_hasil_potong = $conn->real_escape_string($_POST['tanggal_hasil_potong']);

        // Validasi duplikasi seri (server-side)
        $check_seri = $conn->query("SELECT id_hasil_potong_fix FROM hasil_potong_fix WHERE seri = '$seri'");
        if ($check_seri->num_rows > 0) {
            $error = "Nomor seri '$seri' sudah digunakan! Silakan gunakan nomor seri yang berbeda.";
        } else {
            // Validasi duplikasi bahan
            $bahanIds = array_column($items, 'id_bahan');
            if (count($bahanIds) !== count(array_unique($bahanIds))) {
                $error = "Tidak boleh ada bahan yang duplikat dalam satu penjualan bahan!";
            } else {
                // Validasi stok
                foreach ($items as $item) {
                    $id_bahan = intval($item['id_bahan']);
                    $qty = intval($item['qty']);
                    $bahan_stok = query("SELECT jumlah_stok FROM bahan_baku WHERE id_bahan = $id_bahan")[0];

                    if ($qty > $bahan_stok['jumlah_stok']) {
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

                        if ($harga <= 0) {
                            $error = "Harga bahan tidak boleh nol atau negatif.";
                            break;
                        }

                        $total_harga += $harga * $qty;
                        $total_hasil += $qty;
                    }

                    // Ambil nilai total_hasil dari form jika ada
                    if (isset($_POST['total_hasil']) && !empty($_POST['total_hasil'])) {
                        $total_hasil = intval($_POST['total_hasil']);
                    }

                    if (!isset($error)) {
                        $conn->autocommit(FALSE);
                        try {
                            // Insert hasil potong utama
                            $stmt = $conn->prepare("INSERT INTO hasil_potong_fix (id_produk, id_pemotong, seri, tanggal_hasil_potong, total_hasil, total_harga, status_potong) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("iissids", $id_produk, $id_pemotong, $seri, $tanggal_hasil_potong, $total_hasil, $total_harga, $status_potong);

                            if (!$stmt->execute()) {
                                throw new Exception("Gagal menyimpan hasil pemotongan: " . $stmt->error);
                            }

                            $id_hasil_potong_fix = $stmt->insert_id;
                            $stmt->close();

                            // HITUNG UPAH PEMOTONG DI SINI (SEKALI SAJA)
                            $tarif_pemotong = getTarifUpah('pemotongan', $tanggal_hasil_potong);
                            $upah_pemotong = $total_hasil * $tarif_pemotong;

                            // CATAT HUTANG UPAH DI SINI (SEKALI SAJA)
                            if (!catatHutangUpah($id_pemotong, 'pemotong', $tanggal_hasil_potong, $upah_pemotong)) {
                                throw new Exception("Gagal mencatat hutang upah pemotong");
                            }

                            // Insert detail dan update stok
                            $stmt_detail = $conn->prepare("INSERT INTO detail_hasil_potong_fix (id_hasil_potong_fix, id_bahan, id_produk, id_pemotong, jumlah, harga_satuan, subtotal) 
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

                                // Update stok
                                $sql_update = "UPDATE bahan_baku SET jumlah_stok = jumlah_stok - ? WHERE id_bahan = ?";
                                $stmt_update = $conn->prepare($sql_update);
                                $stmt_update->bind_param("ii", $qty, $id_bahan);

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


                    <div class="card-body">
                        <div class="row">
                            <form method="post" id="formPenjualanBahan">
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
                                                        <option value="<?= $p['id_produk'] ?>"><?= $p['nama_produk'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label">Nama Pemotong</label>
                                                <select name="id_pemotong" class="form-control" required>
                                                    <option value="">-- Pilih Pemotong --</option>
                                                    <?php foreach ($pemotong as $p): ?>
                                                        <option value="<?= $p['id_pemotong'] ?>"><?= $p['nama_pemotong'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>


                                            <div class="col-md-3">
                                                <label class="form-label">Tanggal Hasil Potong</label>
                                                <input type="date" name="tanggal_hasil_potong" class="form-control"
                                                    value="<?= isset($_POST['tanggal_hasil_potong']) ? $_POST['tanggal_hasil_potong'] : date('Y-m-d') ?>"
                                                    required>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Total Hasil (Jumlah Potongan)</label>
                                                <div class="input-group">
                                                    <input type="number" name="total_hasil" class="form-control" min="1" value="<?= isset($_POST['total_hasil']) ? $_POST['total_hasil'] : '' ?>" required>
                                                    <span class="input-group-text">Pcs</span>
                                                </div>
                                                <small class="text-muted">Total jumlah potongan yang dihasilkan</small>
                                            </div>

                                            <div hidden class="col-md-4">
                                                <label class="form-label">Status Potong</label>
                                                <select name="status_potong" class="form-control" required>
                                                    <option value="diproses">Diproses</option>
                                                    <option hidden value="selesai">Selesai</option>
                                                </select>
                                            </div>

                                            <!-- TAMBAHKAN INPUT TOTAL HASIL -->
                                        </div>

                                        <div class="col-md-3 mt-3">
                                            <label class="form-label">Seri</label>
                                            <input type="text" name="seri" class="form-control" id="seriInput"
                                                value="<?= isset($_POST['seri']) ? $_POST['seri'] : '' ?>" required
                                                oninput="checkSeri(this.value)">
                                            <small id="seriFeedback" class="text-muted"></small>
                                        </div>

                                        <script>
                                            // Fungsi untuk memeriksa ketersediaan seri
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

                                            // Event listener untuk input seri
                                            document.getElementById('seriInput').addEventListener('blur', function() {
                                                checkSeri(this.value);
                                            });

                                            // Event listener saat form disubmit
                                            document.getElementById('formPenjualanBahan').addEventListener('submit', function(e) {
                                                const seriInput = document.getElementById('seriInput');
                                                const feedbackElement = document.getElementById('seriFeedback');

                                                if (feedbackElement.classList.contains('text-danger')) {
                                                    e.preventDefault();
                                                    Swal.fire({
                                                        icon: 'error',
                                                        title: 'Nomor Seri Sudah Ada',
                                                        text: 'Silakan gunakan nomor seri yang berbeda',
                                                        confirmButtonText: 'Oke'
                                                    });
                                                    seriInput.focus();
                                                }
                                            });
                                        </script>
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
                                            <tbody id="bahanContainer"></tbody>
                                        </table>

                                        <button type="button" class="btn btn-secondary mt-3" id="tambahBahan">+ Tambah Bahan</button>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="simpan_hasil_potong_fix" class="btn btn-primary">Simpan Hasil Pemotongan</button>
                                    <a href="list.php" class="btn btn-danger">Batal</a>
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
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('tambahBahan').click();
    });

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

    // function hitungTotal() {
    //     let total = 0;
    //     document.querySelectorAll('#bahanContainer tr').forEach(row => {
    //         const subtotal = parseFloat(row.querySelector('.subtotal').textContent.replace(/[^0-9]/g, '')) || 0;
    //         total += subtotal;
    //     });
    //     document.getElementById('totalHarga').textContent = formatCurrency(total);
    // }

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