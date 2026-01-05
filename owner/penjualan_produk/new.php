<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';


$produk = query("SELECT * FROM produk WHERE stok > 0 ORDER BY nama_produk");
$reseller = query("SELECT * FROM reseller ORDER BY nama_reseller");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_penjualan'])) {
    $id_reseller = intval($_POST['id_reseller']);
    $metode_pembayaran = $conn->real_escape_string($_POST['metode_pembayaran']);
    $status_pembayaran = $conn->real_escape_string($_POST['status_pembayaran']);
    $tanggal_penjualan = $conn->real_escape_string($_POST['tanggal_penjualan']);
    $items = $_POST['items'];

    // Validasi tanggal
    if (empty($tanggal_penjualan)) {
        $error = "Tanggal penjualan harus diisi!";
    } else {
        // Validasi duplikasi produk
        $productIds = array_column($items, 'id_produk');
        if (count($productIds) !== count(array_unique($productIds))) {
            $error = "Tidak boleh ada produk yang duplikat dalam satu penjualan!";
        } else {
            // Validasi stok
            foreach ($items as $item) {
                $id_produk = intval($item['id_produk']);
                $qty = intval($item['qty']);
                $unit = $conn->real_escape_string($item['unit']);

                // Convert kodi to pcs for stock check
                $actual_qty = ($unit == 'kodi') ? $qty * 20 : $qty;

                $produk = query("SELECT stok FROM produk WHERE id_produk = $id_produk")[0];

                if ($actual_qty > $produk['stok']) {
                    $error = "Jumlah melebihi stok tersedia untuk produk ID $id_produk";
                    break;
                }
            }

            if (!isset($error)) {
                $total_harga = 0;

                foreach ($items as $item) {
                    $id_produk = intval($item['id_produk']);
                    $qty = intval($item['qty']);
                    $unit = $conn->real_escape_string($item['unit']);
                    $harga = floatval($item['harga']);

                    if ($qty <= 0) {
                        $error = "Jumlah produk tidak boleh nol.";
                        break;
                    }

                    if ($harga <= 0) {
                        $error = "Harga produk tidak boleh nol atau negatif.";
                        break;
                    }

                    // Calculate based on unit
                    $multiplier = ($unit == 'kodi') ? 20 : 1;
                    $total_harga += $harga * $qty * $multiplier;
                }

                if (!isset($error)) {
                    $conn->autocommit(FALSE);
                    try {
                        $sql_penjualan = "INSERT INTO penjualan (id_reseller, tanggal_penjualan, total_harga, status_pembayaran, metode_pembayaran) 
                                          VALUES ($id_reseller, '$tanggal_penjualan', $total_harga, '$status_pembayaran', '$metode_pembayaran')";
                        if (!$conn->query($sql_penjualan)) throw new Exception("Gagal menyimpan penjualan");

                        $id_penjualan = $conn->insert_id;

                        foreach ($items as $item) {
                            $id_produk = intval($item['id_produk']);
                            $qty = intval($item['qty']);
                            $unit = $conn->real_escape_string($item['unit']);
                            $harga = floatval($item['harga']);
                            $produk = query("SELECT stok FROM produk WHERE id_produk = $id_produk")[0];

                            // Convert kodi to pcs for storage
                            $actual_qty = ($unit == 'kodi') ? $qty * 20 : $qty;

                            if ($produk['stok'] < $actual_qty) throw new Exception("Stok produk tidak mencukupi untuk produk ID $id_produk");

                            $subtotal = $harga * $actual_qty;

                            $sql_detail = "INSERT INTO detail_penjualan (id_penjualan, id_produk, jumlah, harga_satuan, subtotal) 
                                           VALUES ($id_penjualan, $id_produk, $actual_qty, $harga, $subtotal)";
                            if (!$conn->query($sql_detail)) throw new Exception("Gagal menyimpan detail penjualan");

                            $new_stok = $produk['stok'] - $actual_qty;
                            $sql_update = "UPDATE produk SET stok = $new_stok WHERE id_produk = $id_produk";
                            if (!$conn->query($sql_update)) throw new Exception("Gagal update stok produk");
                        }

                        $conn->commit();
                        $conn->autocommit(TRUE);
                        header("Location: cicilan.php?id=$id_penjualan");
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

    .date-input {
        max-width: 200px;
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
                    <h2>PESANAN PENJUALAN PRODUK</h2>
                </div>


                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <form method="post" id="formPenjualan">
                                <div class="card border border-dark shadow-sm rounded-3">
                                    <div class="card-body">
                                        <?php if (isset($error)): ?>
                                            <div class="alert error"><?= $error ?></div>
                                        <?php endif; ?>

                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-4">
                                                <label class="form-label">Nama Reseller <span class="text-danger">*</span></label>
                                                <select name="id_reseller" class="form-control" required>
                                                    <option value="">-- Pilih Reseller --</option>
                                                    <?php foreach ($reseller as $r): ?>
                                                        <option value="<?= $r['id_reseller'] ?>"><?= $r['nama_reseller'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Tanggal Penjualan (bulan/tanggal/tahun)<span class="text-danger"> *</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                                    <input type="date" name="tanggal_penjualan" class="form-control date-input"
                                                        value="<?= date('Y-m-d') ?>" required>
                                                </div>
                                            </div>

                                            <div hidden class="col-md-2">
                                                <label class="form-label">Metode Pembayaran</label>
                                                <select name="metode_pembayaran" class="form-control" required>
                                                    <option value="transfer">Transfer Bank</option>
                                                    <option value="tunai">Tunai</option>
                                                    <option value="e-wallet">E-Wallet</option>
                                                </select>
                                            </div>

                                            <div hidden class="col-md-2">
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
                                        <h3>Daftar Produk</h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table" id="tabelProduk">
                                            <thead>
                                                <tr class="text-center">
                                                    <th>Produk</th>
                                                    <th>Harga/Pcs (Rp)</th>
                                                    <th>Stok</th>
                                                    <th>Qty</th>
                                                    <th>Subtotal</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="produkContainer"></tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4" class="text-right"><strong>Total</strong></td>
                                                    <td class="currency-format"><span id="totalHarga">0</span></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        <button type="button" class="btn btn-secondary mt-3" id="tambahProduk">
                                            <i class="ti ti-plus"></i> Tambah Produk
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="simpan_penjualan" class="btn btn-primary">
                                        <i class="ti ti-file"></i> Simpan Penjualan
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
<script>
    const produkData = <?= json_encode($produk) ?>;
    let selectedProducts = [];

    document.getElementById('tambahProduk').addEventListener('click', function() {
        const container = document.getElementById('produkContainer');
        const rowId = Date.now();

        // Filter produk yang belum dipilih
        const availableProducts = produkData.filter(p => !selectedProducts.includes(p.id_produk));

        if (availableProducts.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Produk Tidak Tersedia',
                text: 'Semua produk sudah ditambahkan atau stok habis',
                confirmButtonText: 'OK'
            });
            return;
        }

        let options = '<option value="">Pilih Produk</option>';
        availableProducts.forEach(produk => {
            options += `<option value="${produk.id_produk}" data-harga="${produk.harga_jual}" data-stok="${produk.stok}">
                                ${produk.nama_produk}
                            </option>`;
        });

        const row = document.createElement('tr');
        row.id = `row-${rowId}`;
        row.innerHTML = `
                <td class="w-25">
                    <select name="items[${rowId}][id_produk]" class="form-control select-produk" required>
                        ${options}
                    </select>
                </td>
                <td>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" name="items[${rowId}][harga]" class="form-control harga-input" min="1" required>
                    </div>
                </td>
                <td class="stok">0</td>
                <td>
                    <div class="input-group">
                        <input type="number" name="items[${rowId}][qty]" class="form-control qty" min="1" value="1" required>
                        <select name="items[${rowId}][unit]" class="form-select unit-select">
                            <option value="pcs">Pcs</option>
                            <option value="kodi">Kodi</option>
                        </select>
                    </div>
                    <small class="text-muted unit-info">1 kodi = 20 pcs</small>
                    <small class="text-danger stok-error" style="display:none">Melebihi stok tersedia</small>
                </td>
                <td class="currency-format subtotal">0</td>
                <td><button type="button" class="btn btn-sm btn-danger hapus-produk" data-row="${rowId}">Hapus</button>
                </td>
            `;
        container.appendChild(row);
        initRowEvents(rowId);
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.hapus-produk')) {
            const button = e.target.closest('.hapus-produk');
            const rowId = button.dataset.row;
            const row = document.getElementById(`row-${rowId}`);
            const select = row.querySelector('.select-produk');

            // Hapus produk dari daftar yang sudah dipilih
            if (select.value) {
                selectedProducts = selectedProducts.filter(id => id != select.value);
            }

            row.remove();
            hitungTotal();
            updateProductDropdowns();
        }
    });

    function initRowEvents(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const select = row.querySelector('.select-produk');
        const hargaInput = row.querySelector('.harga-input');
        const qtyInput = row.querySelector('.qty');
        const unitSelect = row.querySelector('.unit-select');
        const stokError = row.querySelector('.stok-error');
        const stokDisplay = row.querySelector('.stok');

        select.addEventListener('change', function() {
            const previousProductId = select.dataset.previousValue;

            // Hapus produk sebelumnya dari daftar yang dipilih
            if (previousProductId) {
                selectedProducts = selectedProducts.filter(id => id != previousProductId);
            }

            const newProductId = this.value;
            const selectedOption = this.options[this.selectedIndex];

            if (newProductId) {
                selectedProducts.push(newProductId);
                select.dataset.previousValue = newProductId;

                // Set nilai default
                const defaultHarga = selectedOption.getAttribute('data-harga');
                const stok = selectedOption.getAttribute('data-stok');

                hargaInput.value = defaultHarga;
                stokDisplay.textContent = stok;
                qtyInput.max = stok;
                qtyInput.value = 1;
                unitSelect.value = 'pcs';

                hitungSubtotal(rowId);
            } else {
                select.dataset.previousValue = '';
                hargaInput.value = '';
                stokDisplay.textContent = '0';
                qtyInput.max = 0;
                qtyInput.value = 1;
                unitSelect.value = 'pcs';
                hitungSubtotal(rowId);
            }

            updateProductDropdowns();
        });

        hargaInput.addEventListener('input', function() {
            // Validasi harga minimal
            if (this.value < 1) {
                this.value = 1;
            }
            hitungSubtotal(rowId);
        });

        qtyInput.addEventListener('input', function() {
            const maxStok = parseInt(qtyInput.max) || 0;
            const enteredQty = parseInt(this.value) || 0;
            const unit = unitSelect.value;

            // Convert to pcs for validation
            const actualQty = (unit === 'kodi') ? enteredQty * 20 : enteredQty;

            if (actualQty > maxStok) {
                stokError.style.display = 'block';
                this.value = (unit === 'kodi') ? Math.floor(maxStok / 20) : maxStok;
            } else {
                stokError.style.display = 'none';
            }

            hitungSubtotal(rowId);
        });

        unitSelect.addEventListener('change', function() {
            const maxStok = parseInt(qtyInput.max) || 0;
            const enteredQty = parseInt(qtyInput.value) || 0;

            // Convert to pcs for validation
            const actualQty = (this.value === 'kodi') ? enteredQty * 20 : enteredQty;

            if (actualQty > maxStok) {
                stokError.style.display = 'block';
                qtyInput.value = (this.value === 'kodi') ? Math.floor(maxStok / 20) : maxStok;
            } else {
                stokError.style.display = 'none';
            }

            hitungSubtotal(rowId);
        });

        // Trigger change event untuk inisialisasi
        if (select.value) {
            select.dispatchEvent(new Event('change'));
        }
    }

    function updateProductDropdowns() {
        document.querySelectorAll('.select-produk').forEach(select => {
            const currentValue = select.value;
            const rowId = select.closest('tr').id.split('-')[1];

            // Filter produk yang belum dipilih ATAU produk yang sedang dipilih di dropdown ini
            const availableProducts = produkData.filter(p =>
                !selectedProducts.includes(p.id_produk) || p.id_produk == currentValue
            );

            let options = '<option value="">Pilih Produk</option>';
            availableProducts.forEach(produk => {
                const selected = produk.id_produk == currentValue ? 'selected' : '';
                options += `<option value="${produk.id_produk}" data-harga="${produk.harga_jual}" data-stok="${produk.stok}" ${selected}>
                                    ${produk.nama_produk}
                                </option>`;
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
        const unit = row.querySelector('.unit-select').value;

        // Calculate based on unit
        const multiplier = (unit === 'kodi') ? 20 : 1;
        const subtotal = harga * qty * multiplier;

        row.querySelector('.subtotal').textContent = formatCurrency(subtotal);
        hitungTotal();
    }

    function hitungTotal() {
        let total = 0;
        document.querySelectorAll('#produkContainer tr').forEach(row => {
            const subtotalText = row.querySelector('.subtotal').textContent.replace(/[^\d]/g, '');
            const subtotal = parseFloat(subtotalText) || 0;
            total += subtotal;
        });
        document.getElementById('totalHarga').textContent = formatCurrency(total);
    }

    // Tambahkan validasi form sebelum submit
    document.getElementById('formPenjualan').addEventListener('submit', function(e) {
        const tanggal = document.querySelector('input[name="tanggal_penjualan"]').value;
        const reseller = document.querySelector('select[name="id_reseller"]').value;
        const rows = document.querySelectorAll('#produkContainer tr');

        // Validasi tanggal
        if (!tanggal) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Tanggal Kosong',
                text: 'Silakan pilih tanggal penjualan',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Validasi reseller
        if (!reseller) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Reseller Kosong',
                text: 'Silakan pilih reseller',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Validasi minimal satu produk
        if (rows.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Produk Kosong',
                text: 'Minimal harus ada satu produk yang dijual',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Validasi semua produk sudah dipilih
        let allProductsSelected = true;
        let errorMessage = '';

        rows.forEach((row, index) => {
            const select = row.querySelector('.select-produk');
            const harga = row.querySelector('.harga-input').value;
            const qty = row.querySelector('.qty').value;

            if (!select.value) {
                allProductsSelected = false;
                errorMessage = `Pilih produk untuk baris ${index + 1}`;
            } else if (harga <= 0) {
                allProductsSelected = false;
                errorMessage = `Harga tidak valid untuk baris ${index + 1}`;
            } else if (qty <= 0) {
                allProductsSelected = false;
                errorMessage = `Jumlah tidak valid untuk baris ${index + 1}`;
            }
        });

        if (!allProductsSelected) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Data Tidak Lengkap',
                text: errorMessage,
                confirmButtonText: 'OK'
            });
        }
    });

    // Tambahkan satu produk secara default saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('tambahProduk').click();

        // Set default tanggal ke hari ini
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="tanggal_penjualan"]').value = today;
    });
</script>

</html>