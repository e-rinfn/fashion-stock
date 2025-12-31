<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';


$koko = query("SELECT * FROM koko WHERE stok > 0 ORDER BY nama_koko");
$petugas_finishing = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_penjualan'])) {
    $id_petugas_finishing = intval($_POST['id_petugas_finishing']);
    $metode_pembayaran = $conn->real_escape_string($_POST['metode_pembayaran']);
    $status_pembayaran = $conn->real_escape_string($_POST['status_pembayaran']);
    $items = $_POST['items'];

    // Validasi duplikasi koko
    $kokoIds = array_column($items, 'id_koko');
    if (count($kokoIds) !== count(array_unique($kokoIds))) {
        $error = "Tidak boleh ada koko yang duplikat dalam satu penjualan!";
    } else {
        // Validasi stok
        foreach ($items as $item) {
            $id_koko = intval($item['id_koko']);
            $qty = intval($item['qty']);
            $unit = $conn->real_escape_string($item['unit']);

            // Convert kodi to pcs for stock check
            $actual_qty = ($unit == 'kodi') ? $qty * 20 : $qty;

            $koko = query("SELECT stok FROM koko WHERE id_koko = $id_koko")[0];

            if ($actual_qty > $koko['stok']) {
                $error = "Jumlah melebihi stok tersedia untuk koko ID $id_koko";
                break;
            }
        }

        if (!isset($error)) {
            $total_harga = 0;

            foreach ($items as $item) {
                $id_koko = intval($item['id_koko']);
                $qty = intval($item['qty']);
                $unit = $conn->real_escape_string($item['unit']);
                $harga = floatval($item['harga']);

                if ($qty <= 0) {
                    $error = "Jumlah koko tidak boleh nol.";
                    break;
                }

                if ($harga <= 0) {
                    $error = "Harga koko tidak boleh nol atau negatif.";
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
                                      VALUES ($id_reseller, NOW(), $total_harga, '$status_pembayaran', '$metode_pembayaran')";
                    if (!$conn->query($sql_penjualan)) throw new Exception("Gagal menyimpan penjualan");

                    $id_penjualan = $conn->insert_id;

                    foreach ($items as $item) {
                        $id_koko = intval($item['id_koko']);
                        $qty = intval($item['qty']);
                        $unit = $conn->real_escape_string($item['unit']);
                        $harga = floatval($item['harga']);
                        $koko = query("SELECT stok FROM koko WHERE id_koko = $id_koko")[0];
                        // Convert kodi to pcs for storage
                        $actual_qty = ($unit == 'kodi') ? $qty * 20 : $qty;

                        if ($koko['stok'] < $actual_qty) throw new Exception("Stok koko tidak mencukupi untuk koko ID $id_koko");

                        $subtotal = $harga * $actual_qty;

                        $sql_detail = "INSERT INTO detail_penjualan (id_penjualan, id_koko, jumlah, harga_satuan, subtotal) 
                                       VALUES ($id_penjualan, $id_koko, $actual_qty, $harga, $subtotal)";
                        if (!$conn->query($sql_detail)) throw new Exception("Gagal menyimpan detail penjualan");

                        $new_stok = $koko['stok'] - $actual_qty;
                        $sql_update = "UPDATE koko SET stok = $new_stok WHERE id_koko = $id_koko";
                        if (!$conn->query($sql_update)) throw new Exception("Gagal update stok koko");
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
                    <h2>KIRIM KOKO KE PETUGAS</h2>
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
                                            <div class="col-md-6">
                                                <label class="form-label">Nama Petugas Finishing</label>
                                                <select name="id_petugas_finishing" class="form-control" required>
                                                    <option value="">-- Pilih Petugas Finishing --</option>
                                                    <?php foreach ($petugas as $p): ?>
                                                        <option value="<?= $p['id_petugas_finishing'] ?>"><?= $p['nama_petugas'] ?></option>
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
                                        <h3>Daftar Koko</h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table" id="tabelKoko">
                                            <thead>
                                                <tr class="text-center">
                                                    <th>Koko</th>
                                                    <th>Harga Per Pcs</th>
                                                    <th>Stok</th>
                                                    <th>Qty</th>
                                                    <th>Subtotal</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="kokoContainer"></tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4" class="text-right"><strong>Total</strong></td>
                                                    <td class="currency-format"><span id="totalHarga">0</span></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        <button type="button" class="btn btn-secondary mt-3" id="tambahKoko">
                                            <i class="bx bx-plus"></i> Tambah Koko
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="simpan_penjualan" class="btn btn-primary">
                                        <i class="bx bx-save"></i> Simpan Penjualan
                                    </button>
                                    <a href="list.php" class="btn btn-danger">
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
    const kokoData = <?= json_encode($koko) ?>;
    let selectedKoko = [];

    document.getElementById('tambahKoko').addEventListener('click', function() {
        const container = document.getElementById('kokoContainer');
        const rowId = Date.now();

        // Filter koko yang belum dipilih
        const availableKoko = kokoData.filter(k => !selectedKoko.includes(k.id_koko));

        if (availableKoko.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Koko Tidak Tersedia',
                text: 'Semua koko sudah ditambahkan atau stok habis',
                confirmButtonText: 'OK'
            });
            return;
        }

        let options = '<option value="">Pilih Koko</option>';
        availableKoko.forEach(koko => {
            options += `<option value="${koko.id_koko}" data-harga="${koko.harga_jual}" data-stok="${koko.stok}">
                                ${koko.nama_koko}
                            </option>`;
        });

        const row = document.createElement('tr');
        row.id = `row-${rowId}`;
        row.innerHTML = `
                <td class="w-25">
                    <select name="items[${rowId}][id_koko]" class="form-control select-koko" required>
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
                <td><button type="button" class="btn btn-sm btn-danger hapus-koko" data-row="${rowId}">Hapus</button>
                </td>
            `;
        container.appendChild(row);
        initRowEvents(rowId);
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.hapus-koko')) {
            const button = e.target.closest('.hapus-koko');
            const rowId = button.dataset.row;
            const row = document.getElementById(`row-${rowId}`);
            const select = row.querySelector('.select-koko');
            // Hapus koko dari daftar yang sudah dipilih
            if (select.value) {
                selectedKoko = selectedKoko.filter(id => id != select.value);
            }

            row.remove();
            hitungTotal();
            updateProductDropdowns();
        }
    });

    function initRowEvents(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const select = row.querySelector('.select-koko');
        const hargaInput = row.querySelector('.harga-input');
        const qtyInput = row.querySelector('.qty');
        const unitSelect = row.querySelector('.unit-select');
        const stokError = row.querySelector('.stok-error');
        const stokDisplay = row.querySelector('.stok');

        select.addEventListener('change', function() {
            const previousKokoId = select.dataset.previousValue;

            // Hapus koko sebelumnya dari daftar yang dipilih
            if (previousKokoId) {
                selectedKoko = selectedKoko.filter(id => id != previousKokoId);
            }

            const newKokoId = this.value;
            const selectedOption = this.options[this.selectedIndex];

            if (newKokoId) {
                selectedKoko.push(newKokoId);
                select.dataset.previousValue = newKokoId;

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
        document.querySelectorAll('.select-koko').forEach(select => {
            const currentValue = select.value;
            const rowId = select.closest('tr').id.split('-')[1];

            // Filter koko yang belum dipilih ATAU koko yang sedang dipilih di dropdown ini
            const availableKoko = kokoData.filter(p =>
                !selectedKoko.includes(p.id_koko) || p.id_koko == currentValue
            );

            let options = '<option value="">Pilih Koko</option>';
            availableKoko.forEach(koko => {
                const selected = koko.id_koko == currentValue ? 'selected' : '';
                options += `<option value="${koko.id_koko}" data-harga="${koko.harga_jual}" data-stok="${koko.stok}" ${selected}>
                                    ${koko.nama_koko}
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
        document.querySelectorAll('#kokoContainer tr').forEach(row => {
            const subtotalText = row.querySelector('.subtotal').textContent.replace(/[^\d]/g, '');
            const subtotal = parseFloat(subtotalText) || 0;
            total += subtotal;
        });
        document.getElementById('totalHarga').textContent = formatCurrency(total);
    }

    // Tambahkan satu koko secara default saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('tambahKoko').click();
    });
</script>


</html>