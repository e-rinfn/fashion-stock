<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';
// redirectIfNotLoggedIn();
// checkRole('admin');

$bahan = query("SELECT * FROM bahan_baku ORDER BY nama_bahan");
$supplier = query("SELECT * FROM supplier ORDER BY nama_supplier");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_pembelian_bahan'])) {
    $id_supplier = intval($_POST['id_supplier']);
    $metode_pembayaran = $conn->real_escape_string($_POST['metode_pembayaran']);
    $status_pembayaran = $conn->real_escape_string($_POST['status_pembayaran']);
    $items = $_POST['items'];

    // Validasi duplikasi bahan
    $bahanIds = array_column($items, 'id_bahan');
    if (count($bahanIds) !== count(array_unique($bahanIds))) {
        $error = "Tidak boleh ada bahan yang duplikat dalam satu pembelian bahan!";
    } else {
        // Validasi stok
        foreach ($items as $item) {
            $id_bahan = intval($item['id_bahan']);
            $qty = intval($item['qty']);
            $harga = floatval($item['harga']);
            $bahan = query("SELECT jumlah_stok FROM bahan_baku WHERE id_bahan = $id_bahan")[0];

            // if ($qty > $bahan['jumlah_stok']) {
            //     $error = "Jumlah melebihi stok tersedia untuk bahan ID $id_bahan";
            //     break;
            // }

            if ($harga <= 0) {
                $error = "Harga tidak valid untuk bahan ID $id_bahan";
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

                $total_harga += $harga * $qty;
            }

            if (!isset($error)) {
                $conn->autocommit(FALSE);
                try {
                    $sql_pembelian_bahan = "INSERT INTO pembelian_bahan (id_supplier, tanggal_pembelian, total_harga, status_pembayaran, metode_pembayaran) 
                                      VALUES ($id_supplier, NOW(), $total_harga, '$status_pembayaran', '$metode_pembayaran')";
                    if (!$conn->query($sql_pembelian_bahan)) throw new Exception("Gagal menyimpan pembelian bahan");

                    $id_pembelian_bahan = $conn->insert_id;

                    foreach ($items as $item) {
                        $id_bahan = intval($item['id_bahan']);
                        $qty = intval($item['qty']);
                        $harga = floatval($item['harga']);
                        $bahan = query("SELECT jumlah_stok FROM bahan_baku WHERE id_bahan = $id_bahan")[0];

                        // if ($bahan['jumlah_stok'] < $qty) throw new Exception("Stok bahan tidak mencukupi untuk bahan ID $id_bahan");

                        $subtotal = $harga * $qty;

                        $sql_detail = "INSERT INTO detail_pembelian_bahan (id_pembelian_bahan, id_bahan, jumlah, harga_satuan, subtotal) 
                                       VALUES ($id_pembelian_bahan, $id_bahan, $qty, $harga, $subtotal)";
                        if (!$conn->query($sql_detail)) throw new Exception("Gagal menyimpan detail pembelian bahan");

                        $new_stok = $bahan['jumlah_stok'] + $qty;
                        $sql_update = "UPDATE bahan_baku SET jumlah_stok = $new_stok WHERE id_bahan = $id_bahan";
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
?>


<style>
    /* Paksa SweetAlert berada di atas segalanya */
    .swal2-container {
        z-index: 99999 !important;
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

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error'];
                                                        unset($_SESSION['error']); ?></div>
                    <?php endif; ?>


                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <form method="post" id="formPembelian">
                                    <div class="card border border-dark shadow-sm rounded-3">
                                        <div class="card-body">
                                            <?php if (isset($error)): ?>
                                                <div class="alert error"><?= $error ?></div>
                                            <?php endif; ?>

                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-6">
                                                    <label class="form-label">Nama Supplier</label>
                                                    <select name="id_supplier" class="form-control" required>
                                                        <option value="">-- Pilih Supplier --</option>
                                                        <?php foreach ($supplier as $s): ?>
                                                            <option value="<?= $s['id_supplier'] ?>"><?= $s['nama_supplier'] ?></option>
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
                                            <h3>Daftar Bahan Baku</h3>
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

                                            <button type="button" class="btn btn-secondary mt-3" id="tambahBahan">
                                                <i class="bx bx-plus"></i> Tambah Bahan
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" name="simpan_pembelian_bahan" class="btn btn-primary">
                                            <i class="bx bx-save"></i> Simpan Pembelian
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
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Tombol Tambah Bahan
    document.getElementById('tambahBahan').addEventListener('click', function() {
        const container = document.getElementById('bahanContainer');
        const rowId = Date.now();

        // Ambil semua bahan yang belum dipilih (tidak lagi filter stok > 0)
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
                `${bahan.nama_bahan} (Stok: ${bahan.jumlah_stok})`;

            options += `<option value="${bahan.id_bahan}" data-harga="${bahan.harga_per_satuan}" data-stok="${bahan.jumlah_stok}">
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
                    <span class="input-group-text">Rp</span>
                    <input type="number" name="items[${rowId}][harga]" class="form-control harga-input" min="1" required>
                </div>
            </td>
            <td class="stok">0</td>
            <td class="w-25">
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
        const stokDisplay = row.querySelector('.stok');

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
                    stokDisplay.textContent = bahan.jumlah_stok;
                    qtyInput.value = 1;
                    hitungSubtotal(rowId);
                }
            } else {
                select.dataset.previousValue = '';
                hargaInput.value = '';
                stokDisplay.textContent = '0';
                qtyInput.value = 1;
            }

            updateBahanDropdowns();
        });

        hargaInput.addEventListener('input', () => hitungSubtotal(rowId));
        qtyInput.addEventListener('input', () => hitungSubtotal(rowId));

        if (select.value) select.dispatchEvent(new Event('change'));
    }

    // Update semua dropdown bahan
    function updateBahanDropdowns() {
        document.querySelectorAll('.select-bahan').forEach(select => {
            const currentValue = select.value;

            const availableBahans = bahanData.filter(bahan =>
                !selectedBahans.includes(bahan.id_bahan) || bahan.id_bahan == currentValue
            );

            let options = '<option value="">Pilih Bahan</option>';
            availableBahans.forEach(bahan => {
                const stokLabel = bahan.jumlah_stok == 0 ?
                    `${bahan.nama_bahan} (Stok Habis)` :
                    `${bahan.nama_bahan} (Stok: ${bahan.jumlah_stok})`;

                const selected = bahan.id_bahan == currentValue ? 'selected' : '';
                options += `<option value="${bahan.id_bahan}" data-harga="${bahan.harga_per_satuan}" data-stok="${bahan.jumlah_stok}" ${selected}>
                                ${stokLabel}
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
        const subtotal = harga * qty;
        row.querySelector('.subtotal').textContent = formatCurrency(subtotal);
        hitungTotal();
    }

    function hitungTotal() {
        let total = 0;
        document.querySelectorAll('#bahanContainer tr').forEach(row => {
            const subtotalText = row.querySelector('.subtotal').textContent.replace(/[^\d]/g, '');
            total += parseFloat(subtotalText) || 0;
        });
        document.getElementById('totalHarga').textContent = formatCurrency(total);
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('tambahBahan').click();
    });
</script>


</html>