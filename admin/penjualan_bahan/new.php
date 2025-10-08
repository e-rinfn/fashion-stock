<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';
// redirectIfNotLoggedIn();
// checkRole('admin');

$bahan = query("SELECT * FROM bahan_baku WHERE jumlah_stok > 0 ORDER BY nama_bahan");
$reseller = query("SELECT * FROM reseller ORDER BY nama_reseller");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_penjualan_bahan'])) {
    $id_reseller = intval($_POST['id_reseller']);
    $metode_pembayaran = $conn->real_escape_string($_POST['metode_pembayaran']);
    $status_pembayaran = $conn->real_escape_string($_POST['status_pembayaran']);
    $items = $_POST['items'];

    // Validasi duplikasi bahan
    $bahanIds = array_column($items, 'id_bahan');
    if (count($bahanIds) !== count(array_unique($bahanIds))) {
        $error = "Tidak boleh ada bahan yang duplikat dalam satu penjualan bahan!";
    } else {
        // Validasi stok
        foreach ($items as $item) {
            $id_bahan = intval($item['id_bahan']);
            $qty = intval($item['qty']);
            $bahan = query("SELECT jumlah_stok FROM bahan_baku WHERE id_bahan = $id_bahan")[0];

            if ($qty > $bahan['jumlah_stok']) {
                $error = "Jumlah melebihi stok tersedia untuk bahan ID $id_bahan";
                break;
            }
        }

        if (!isset($error)) {
            $total_harga = 0;

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
            }

            if (!isset($error)) {
                $conn->autocommit(FALSE);
                try {
                    $sql_penjualan_bahan = "INSERT INTO penjualan_bahan (id_reseller, tanggal_penjualan_bahan, total_harga, status_pembayaran, metode_pembayaran) 
                                      VALUES ($id_reseller, NOW(), $total_harga, '$status_pembayaran', '$metode_pembayaran')";
                    if (!$conn->query($sql_penjualan_bahan)) throw new Exception("Gagal menyimpan penjualan bahan");

                    $id_penjualan_bahan = $conn->insert_id;

                    foreach ($items as $item) {
                        $id_bahan = intval($item['id_bahan']);
                        $qty = intval($item['qty']);
                        $harga = floatval($item['harga']);
                        $bahan = query("SELECT jumlah_stok FROM bahan_baku WHERE id_bahan = $id_bahan")[0];

                        if ($bahan['jumlah_stok'] < $qty) throw new Exception("Stok bahan tidak mencukupi untuk bahan ID $id_bahan");

                        $subtotal = $harga * $qty;

                        $sql_detail = "INSERT INTO detail_penjualan_bahan (id_penjualan_bahan, id_bahan, jumlah, harga_satuan, subtotal) 
                                       VALUES ($id_penjualan_bahan, $id_bahan, $qty, $harga, $subtotal)";
                        if (!$conn->query($sql_detail)) throw new Exception("Gagal menyimpan detail penjualan bahan");

                        $new_stok = $bahan['jumlah_stok'] - $qty;
                        $sql_update = "UPDATE bahan_baku SET jumlah_stok = $new_stok WHERE id_bahan = $id_bahan";
                        if (!$conn->query($sql_update)) throw new Exception("Gagal update stok bahan");
                    }

                    $conn->commit();
                    $conn->autocommit(TRUE);
                    header("Location: cicilan.php?id=$id_penjualan_bahan");
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

    .unit-select {
        min-width: 80px;
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
                    <h2>PESANAN PENJUALAN BAHAN BAKU</h2>
                </div>


                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <form method="post" id="formPenjualanBahan">
                                <div class="card border border-dark shadow-sm rounded-3">
                                    <div class="card-body">
                                        <?php if (isset($error)): ?>
                                            <div class="alert error"><?= $error ?></div>
                                        <?php endif; ?>

                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-6">
                                                <label class="form-label">Nama Reseller</label>
                                                <select name="id_reseller" class="form-control" required>
                                                    <option value="">-- Pilih Reseller --</option>
                                                    <?php foreach ($reseller as $r): ?>
                                                        <option value="<?= $r['id_reseller'] ?>"><?= $r['nama_reseller'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div hidden class="col-md-4">
                                                <label class="form-label">Metode Pembayaran</label>
                                                <select name="metode_pembayaran" class="form-control" required>
                                                    <option value="transfer">Transfer Bank</option>
                                                    <option value="tunai">Tunai</option>
                                                    <option value="e-wallet">E-Wallet</option>
                                                </select>
                                            </div>

                                            <div hidden class="col-md-4">
                                                <label class="form-label">Status Pembayaran</label>
                                                <select name="status_pembayaran" class="form-control" required>
                                                    <option value="cicilan">Cicilan</option>
                                                    <option hidden value="lunas">Lunas</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mt-3 border border-dark shadow-sm rounded-3">
                                    <div class="card-header">
                                        <h3>Daftar Bahan</h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table" id="tabelBahan">
                                            <thead>
                                                <tr class="text-center">
                                                    <th>Bahan</th>
                                                    <th>Harga</th>
                                                    <th>Stok</th>
                                                    <th>Qty</th>
                                                    <th>Subtotal</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bahanContainer"></tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4" class="text-right"><strong>Total</strong></td>
                                                    <td class="currency-format"><span id="totalHarga">0</span></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        <button type="button" class="btn btn-secondary mt-3" id="tambahBahan">+ Tambah Bahan</button>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="simpan_penjualan_bahan" class="btn btn-primary">Simpan Penjualan Bahan</button>
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
                <td class="w-25">
                    <select name="items[${rowId}][id_bahan]" class="form-control select-bahan" required>
                        ${options}
                    </select>
                </td>
                <td>
                    <div class="input-group">
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
                <td class="currency-format subtotal">0</td>
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
</script>


</html>