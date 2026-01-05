<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';


$bahan = query("SELECT * FROM bahan_baku ORDER BY nama_bahan");
$supplier = query("SELECT * FROM supplier ORDER BY nama_supplier");
$bahan = query("SELECT *, COALESCE(jumlah_meter, 0) as jumlah_meter, COALESCE(meter_per_roll, 0) as meter_per_roll FROM bahan_baku ORDER BY nama_bahan");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_pembelian_bahan'])) {
    $id_supplier = intval($_POST['id_supplier']);
    $metode_pembayaran = $conn->real_escape_string($_POST['metode_pembayaran']);
    $status_pembayaran = $conn->real_escape_string($_POST['status_pembayaran']);
    $tanggal_pembelian = $conn->real_escape_string($_POST['tanggal_pembelian']);
    $items = $_POST['items'];

    // Validasi tanggal
    if (empty($tanggal_pembelian)) {
        $error = "Tanggal pembelian bahan harus diisi!";
    } else {
        // Validasi duplikasi bahan
        $bahanIds = array_column($items, 'id_bahan');
        if (count($bahanIds) !== count(array_unique($bahanIds))) {
            $error = "Tidak boleh ada bahan yang duplikat dalam satu pembelian bahan!";
        } else {
            // Validasi
            foreach ($items as $item) {
                $id_bahan = intval($item['id_bahan']);
                $qty = intval($item['qty']);
                $harga = floatval($item['harga']);
                $meter = floatval($item['meter']);

                if ($harga <= 0) {
                    $error = "Harga tidak valid untuk bahan ID $id_bahan";
                    break;
                }

                if ($meter <= 0) {
                    $error = "Meter per roll tidak valid untuk bahan ID $id_bahan";
                    break;
                }
            }

            if (!isset($error)) {
                $total_harga = 0;

                foreach ($items as $item) {
                    $id_bahan = intval($item['id_bahan']);
                    $qty = intval($item['qty']);
                    $harga = floatval($item['harga']);
                    $meter = floatval($item['meter']);

                    if ($qty <= 0) {
                        $error = "Jumlah bahan tidak boleh nol.";
                        break;
                    }

                    $total_harga += $harga * $meter;
                }

                if (!isset($error)) {
                    $conn->autocommit(FALSE);
                    try {
                        $sql_pembelian_bahan = "INSERT INTO pembelian_bahan (id_supplier, tanggal_pembelian, total_harga, status_pembayaran, metode_pembayaran) 
                                              VALUES ($id_supplier, '$tanggal_pembelian', $total_harga, '$status_pembayaran', '$metode_pembayaran')";
                        if (!$conn->query($sql_pembelian_bahan)) throw new Exception("Gagal menyimpan pembelian bahan");

                        $id_pembelian_bahan = $conn->insert_id;

                        foreach ($items as $item) {
                            $id_bahan = intval($item['id_bahan']);
                            $qty = intval($item['qty']);
                            $harga = floatval($item['harga']);
                            $meter = floatval($item['meter']);

                            // Ambil data bahan saat ini
                            $bahan = query("SELECT jumlah_stok, jumlah_meter FROM bahan_baku WHERE id_bahan = $id_bahan")[0];

                            $subtotal = $harga * $qty;
                            $total_meter = $meter;

                            // Simpan detail pembelian dengan meter
                            $sql_detail = "INSERT INTO detail_pembelian_bahan (id_pembelian_bahan, id_bahan, jumlah, harga_satuan, meter, subtotal) 
                                           VALUES ($id_pembelian_bahan, $id_bahan, $qty, $harga, $total_meter, $subtotal)";
                            if (!$conn->query($sql_detail)) throw new Exception("Gagal menyimpan detail pembelian bahan");

                            // Update stok (baik jumlah roll maupun meter)
                            $new_stok_roll = $bahan['jumlah_stok'] + $qty;
                            $new_stok_meter = $bahan['jumlah_meter'] + $total_meter;

                            $sql_update = "UPDATE bahan_baku SET 
                                            jumlah_stok = $new_stok_roll,
                                            jumlah_meter = $new_stok_meter
                                          WHERE id_bahan = $id_bahan";
                            if (!$conn->query($sql_update)) throw new Exception("Gagal update stok bahan");
                        }

                        $conn->commit();
                        $conn->autocommit(TRUE);
                        header("Location: cicilan.php?id=$id_pembelian_bahan");
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
                    <h2>PESANAN PEMBELIAN BAHAN BAKU</h2>
                </div>


                <div class="card">
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

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error'];
                                                        unset($_SESSION['error']); ?></div>
                    <?php endif; ?>



                    <div class="card-body">
                        <div class="row">
                            <form method="post" id="formPembelian">
                                <div class="card border border-dark shadow-sm rounded-3">
                                    <div class="card-body">
                                        <?php if (isset($error)): ?>
                                            <div class="alert error"><?= $error ?></div>
                                        <?php endif; ?>

                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-4">
                                                <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                                                <select name="id_supplier" class="form-control" required>
                                                    <option value="">-- Pilih Supplier --</option>
                                                    <?php foreach ($supplier as $s): ?>
                                                        <option value="<?= $s['id_supplier'] ?>"><?= $s['nama_supplier'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Tanggal Pembelian (bulan/tanggal/tahun)<span class="text-danger"> *</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                                    <input type="date" name="tanggal_pembelian" class="form-control date-input"
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
                                        <h3>Daftar Bahan Baku</h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table" id="tabelBahan">
                                            <thead>
                                                <tr class="text-center">
                                                    <th>Bahan</th>
                                                    <th>Harga/M (Rp)</th>
                                                    <th>Stok</th>
                                                    <th>Roll/Yard</th>
                                                    <th>Meter</th>
                                                    <th>Total Meter</th>
                                                    <th>Subtotal</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bahanContainer"></tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="6" class="text-right"><strong>Total</strong></td>
                                                    <td class="currency-format"><span id="totalHarga">0</span></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        <button type="button" class="btn btn-secondary mt-3" id="tambahBahan">
                                            <i class="ti ti-plus"></i> Tambah Bahan
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="simpan_pembelian_bahan" class="btn btn-primary">
                                        <i class="ti ti-file"></i> Simpan Pembelian
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
    const bahanData = <?= json_encode($bahan) ?>;
    let selectedBahans = [];

    function formatRupiah(angka) {
        return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function formatCurrency(amount) {
        return 'Rp ' + Number(amount).toLocaleString('id-ID');
    }

    // Tombol Tambah Bahan
    document.getElementById('tambahBahan').addEventListener('click', function() {
        const container = document.getElementById('bahanContainer');
        const rowId = Date.now();

        // Ambil semua bahan yang belum dipilih
        const availableBahans = bahanData.filter(b => !selectedBahans.includes(b.id_bahan));

        if (availableBahans.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Tidak ada bahan tersedia',
                text: 'Semua bahan sudah ditambahkan',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Buat opsi dropdown
        let options = '<option value="">Pilih Bahan</option>';
        availableBahans.forEach(bahan => {
            const stokLabel = bahan.jumlah_stok == 0 ?
                `${bahan.nama_bahan} (Stok Habis)` :
                `${bahan.nama_bahan} (Stok: ${bahan.jumlah_stok} Roll, ${bahan.jumlah_meter || 0} Meter)`;

            options += `<option value="${bahan.id_bahan}" 
                        data-harga="${bahan.harga_per_satuan}" 
                        data-stok="${bahan.jumlah_stok}"
                        data-meter="${bahan.meter_per_roll || 0}">
                        ${stokLabel}
                    </option>`;
        });

        // Tambahkan baris baru ke tabel
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
                    <input type="number" name="items[${rowId}][harga]" class="form-control harga-input" min="1" required>
                </div>
            </td>
            <td class="stok-info">
                <span class="stok-roll">0</span> Roll<br>
                <span class="stok-meter">0</span> M
            </td>
            <td class="w-15">
                <div class="input-group">
                    <input type="number" name="items[${rowId}][qty]" class="form-control qty" min="1" value="1" required>
                </div>
            </td>
            <td class="w-15">
                <div class="input-group">
                    <input type="number" name="items[${rowId}][meter]" class="form-control meter-input" 
                           step="1" min="1" value="0" required>
                </div>
            </td>
            <td class="total-meter">0 m</td>
            <td class="currency-format subtotal">Rp 0</td>
            <td><button type="button" class="btn btn-sm btn-danger hapus-bahan" data-row="${rowId}">Hapus</button></td>
        `;
        container.appendChild(row);
        initRowEvents(rowId);
    });

    // Hapus bahan dari daftar
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
            hitungTotal();
            updateBahanDropdowns();
        }
    });

    // Event untuk setiap baris
    function initRowEvents(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const select = row.querySelector('.select-bahan');
        const hargaInput = row.querySelector('.harga-input');
        const qtyInput = row.querySelector('.qty');
        const meterInput = row.querySelector('.meter-input');
        const stokRollDisplay = row.querySelector('.stok-roll');
        const stokMeterDisplay = row.querySelector('.stok-meter');
        const totalMeterDisplay = row.querySelector('.total-meter');

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

                    // Set nilai default meter per roll
                    meterInput.value = bahan.meter_per_roll || 0;

                    qtyInput.value = 1;
                    hitungTotalMeter(rowId);
                    hitungSubtotal(rowId);
                }
            } else {
                select.dataset.previousValue = '';
                hargaInput.value = '';
                stokRollDisplay.textContent = '0';
                stokMeterDisplay.textContent = '0';
                meterInput.value = 0;
                qtyInput.value = 1;
                totalMeterDisplay.textContent = '0 m';
                row.querySelector('.subtotal').textContent = 'Rp 0';
            }

            updateBahanDropdowns();
        });

        hargaInput.addEventListener('input', () => hitungSubtotal(rowId));
        qtyInput.addEventListener('input', () => {
            hitungTotalMeter(rowId);
            hitungSubtotal(rowId);
        });
        meterInput.addEventListener('input', () => {
            hitungTotalMeter(rowId);
            hitungSubtotal(rowId);
        });

        // Trigger change event jika sudah ada value
        if (select.value) select.dispatchEvent(new Event('change'));
    }

    // Fungsi hitung total meter
    function hitungTotalMeter(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const qty = parseInt(row.querySelector('.qty').value) || 0;
        const meterPerRoll = parseFloat(row.querySelector('.meter-input').value) || 0;
        const totalMeter = qty * meterPerRoll;
        row.querySelector('.total-meter').textContent = totalMeter.toFixed(0) + ' m';
    }

    // Fungsi hitung subtotal
    function hitungSubtotal(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const harga = parseFloat(row.querySelector('.harga-input').value) || 0;
        const qty = parseInt(row.querySelector('.qty').value) || 0;
        const meterPerRoll = parseFloat(row.querySelector('.meter-input').value) || 0;
        const totalMeter = qty * meterPerRoll;
        const subtotal = harga * totalMeter;
        row.querySelector('.subtotal').textContent = formatCurrency(subtotal);
        hitungTotal();
    }

    // Update semua dropdown bahan
    function updateBahanDropdowns() {
        document.querySelectorAll('.select-bahan').forEach(select => {
            const currentValue = select.value;
            const row = select.closest('tr');
            const currentMeter = row ? row.querySelector('.meter-input').value : 0;

            const availableBahans = bahanData.filter(bahan =>
                !selectedBahans.includes(bahan.id_bahan) || bahan.id_bahan == currentValue
            );

            let options = '<option value="">Pilih Bahan</option>';
            availableBahans.forEach(bahan => {
                const stokLabel = bahan.jumlah_stok == 0 ?
                    `${bahan.nama_bahan} (Stok Habis)` :
                    `${bahan.nama_bahan} (Stok: ${bahan.jumlah_stok} Roll, ${bahan.jumlah_meter || 0} Meter)`;

                const selected = bahan.id_bahan == currentValue ? 'selected' : '';
                options += `<option value="${bahan.id_bahan}" 
                            data-harga="${bahan.harga_per_satuan}" 
                            data-stok="${bahan.jumlah_stok}"
                            data-meter="${bahan.meter_per_roll || 0}" 
                            ${selected}>
                            ${stokLabel}
                        </option>`;
            });

            select.innerHTML = options;

            // Set nilai meter jika bahan masih sama
            if (currentValue) {
                const bahan = bahanData.find(b => b.id_bahan == currentValue);
                if (bahan && row) {
                    const meterInput = row.querySelector('.meter-input');
                    if (meterInput.value == 0 || meterInput.value == '') {
                        meterInput.value = bahan.meter_per_roll || 0;
                    }
                    hitungTotalMeter(row.id.replace('row-', ''));
                    hitungSubtotal(row.id.replace('row-', ''));
                }
            }
        });
    }

    function hitungTotal() {
        let total = 0;
        document.querySelectorAll('#bahanContainer tr').forEach(row => {
            const subtotalText = row.querySelector('.subtotal').textContent.replace(/[^\d]/g, '');
            total += parseFloat(subtotalText) || 0;
        });
        document.getElementById('totalHarga').textContent = formatCurrency(total);
    }

    // Tambahkan validasi form sebelum submit
    document.getElementById('formPembelian').addEventListener('submit', function(e) {
        const tanggal = document.querySelector('input[name="tanggal_pembelian"]').value;
        const supplier = document.querySelector('select[name="id_supplier"]').value;
        const rows = document.querySelectorAll('#bahanContainer tr');

        // Validasi tanggal
        if (!tanggal) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Tanggal Kosong',
                text: 'Silakan pilih tanggal pembelian bahan',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Validasi supplier
        if (!supplier) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Supplier Kosong',
                text: 'Silakan pilih supplier',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Validasi minimal satu bahan
        if (rows.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Bahan Kosong',
                text: 'Minimal harus ada satu bahan yang dibeli',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Validasi setiap baris bahan
        let isValid = true;
        let errorMessage = '';

        rows.forEach((row, index) => {
            const select = row.querySelector('.select-bahan');
            const harga = row.querySelector('.harga-input').value;
            const qty = row.querySelector('.qty').value;
            const meter = row.querySelector('.meter-input').value;

            if (!select.value) {
                isValid = false;
                errorMessage = `Pilih bahan untuk baris ${index + 1}`;
            } else if (harga <= 0) {
                isValid = false;
                errorMessage = `Harga tidak valid untuk baris ${index + 1}`;
            } else if (qty <= 0) {
                isValid = false;
                errorMessage = `Jumlah roll tidak valid untuk baris ${index + 1}`;
            } else if (meter <= 0) {
                isValid = false;
                errorMessage = `Meter per roll tidak valid untuk baris ${index + 1}`;
            }
        });

        if (!isValid) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage,
                confirmButtonText: 'OK'
            });
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('tambahBahan').click();

        // Set default tanggal ke hari ini
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="tanggal_pembelian"]').value = today;
    });
</script>

</html>