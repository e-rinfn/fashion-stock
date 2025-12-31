<?php
// File: koko_keluar.php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

session_start();

// Ambil data koko yang tersedia
$koko = query("SELECT * FROM koko WHERE stok > 0 ORDER BY nama_koko");
$petugas = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_koko_keluar'])) {
    $id_petugas_finishing = intval($_POST['id_petugas_finishing']);
    $metode_pembayaran = 'transfer'; // default
    $status = 'dikirim'; // default
    $items = $_POST['items'];

    // Validasi
    if (empty($id_petugas_finishing) || empty($items)) {
        $_SESSION['error'] = "Data petugas dan koko harus diisi!";
    } else {
        $conn->autocommit(FALSE);
        $error = null;

        try {
            // 1. Hitung total harga
            $total_harga = 0;
            foreach ($items as $item) {
                if (empty($item['id_koko'])) continue;

                $id_koko = intval($item['id_koko']);
                $qty = intval($item['qty']);
                $harga = floatval($item['harga']);

                // Validasi stok
                $koko_data = query("SELECT stok FROM koko WHERE id_koko = $id_koko LIMIT 1")[0];
                if ($koko_data['stok'] < $qty) {
                    throw new Exception("Stok tidak mencukupi untuk koko ID $id_koko");
                }

                $total_harga += $harga * $qty;
            }

            // 2. Simpan koko_keluar
            $sql_keluar = "INSERT INTO koko_keluar 
                (id_petugas_finishing, tanggal_koko_keluar, total_harga, status, metode_pembayaran) 
                VALUES ($id_petugas_finishing, NOW(), $total_harga, '$status', '$metode_pembayaran')";

            if (!$conn->query($sql_keluar)) {
                throw new Exception("Gagal menyimpan koko_keluar: " . $conn->error);
            }

            $id_koko_keluar = $conn->insert_id;

            // 3. Simpan detail dan update stok
            foreach ($items as $item) {
                if (empty($item['id_koko'])) continue;

                $id_koko = intval($item['id_koko']);
                $qty = intval($item['qty']);
                $harga = floatval($item['harga']);
                $subtotal = $harga * $qty;

                // Simpan detail
                $sql_detail = "INSERT INTO detail_koko_keluar 
                    (id_koko_keluar, id_koko, jumlah, harga_satuan, subtotal) 
                    VALUES ($id_koko_keluar, $id_koko, $qty, $harga, $subtotal)";

                if (!$conn->query($sql_detail)) {
                    throw new Exception("Gagal menyimpan detail: " . $conn->error);
                }

                // Kurangi stok koko
                $sql_update = "UPDATE koko SET stok = stok - $qty WHERE id_koko = $id_koko";
                if (!$conn->query($sql_update)) {
                    throw new Exception("Gagal update stok: " . $conn->error);
                }
            }

            $conn->commit();
            $_SESSION['success'] = "Koko berhasil dikirim ke petugas finishing!";
            header("Location: list.php?tipe_data=keluar");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }

        $conn->autocommit(TRUE);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <title>Kirim Koko ke Petugas Finishing</title>
    <style>
        .item-row {
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }

        .stok-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>KIRIM KOKO KE PETUGAS FINISHING</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error'];
                                            unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form method="post">
            <!-- Pilih Petugas -->
            <div class="mb-3">
                <label>Pilih Petugas Finishing</label>
                <select name="id_petugas_finishing" class="form-control" required>
                    <option value="">-- Pilih Petugas --</option>
                    <?php foreach ($petugas as $p): ?>
                        <option value="<?= $p['id_petugas_finishing'] ?>"><?= $p['nama_petugas'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Daftar Koko -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5>Daftar Koko yang Dikirim</h5>
                </div>
                <div class="card-body" id="items-container">
                    <!-- Item akan ditambahkan via JavaScript -->
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-secondary" onclick="tambahItem()">
                        <i class="bx bx-plus"></i> Tambah Koko
                    </button>
                </div>
            </div>

            <!-- Total -->
            <div class="card mb-3">
                <div class="card-body">
                    <h5>Total: <span id="total-harga">Rp 0</span></h5>
                </div>
            </div>

            <!-- Tombol Aksi -->
            <div class="d-flex gap-2">
                <button type="submit" name="simpan_koko_keluar" class="btn btn-primary">
                    <i class="bx bx-send"></i> Kirim Koko
                </button>
                <a href="list.php" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    <script>
        const kokoData = <?= json_encode($koko) ?>;
        let itemCounter = 0;
        let selectedItems = [];

        function tambahItem() {
            const container = document.getElementById('items-container');
            const itemId = Date.now();

            // Filter koko yang belum dipilih atau stok > 0
            const availableKoko = kokoData.filter(k =>
                !selectedItems.includes(k.id_koko) && k.stok > 0
            );

            if (availableKoko.length === 0) {
                alert('Semua koko sudah ditambahkan atau stok habis!');
                return;
            }

            // Buat opsi dropdown
            let options = '<option value="">Pilih Koko</option>';
            availableKoko.forEach(koko => {
                options += `<option value="${koko.id_koko}" data-stok="${koko.stok}" data-harga="${koko.harga_jual}">
                    ${koko.nama_koko} (Stok: ${koko.stok} pcs)
                </option>`;
            });

            // Buat row item
            const row = document.createElement('div');
            row.className = 'item-row';
            row.id = `item-${itemId}`;
            row.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-5">
                        <select name="items[${itemId}][id_koko]" class="form-control koko-select" onchange="updateItem(${itemId})" required>
                            ${options}
                        </select>
                        <div class="stok-info mt-1" id="stok-info-${itemId}"></div>
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="items[${itemId}][qty]" class="form-control qty-input" 
                               min="1" value="1" onchange="updateSubtotal(${itemId})" required>
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="items[${itemId}][harga]" class="form-control harga-input" 
                               min="1" onchange="updateSubtotal(${itemId})" required>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm" onclick="hapusItem(${itemId})">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <strong>Subtotal: <span id="subtotal-${itemId}">Rp 0</span></strong>
                    </div>
                </div>
            `;

            container.appendChild(row);
            itemCounter++;
        }

        function updateItem(itemId) {
            const select = document.querySelector(`#item-${itemId} .koko-select`);
            const selectedOption = select.options[select.selectedIndex];
            const hargaInput = document.querySelector(`#item-${itemId} .harga-input`);
            const stokInfo = document.getElementById(`stok-info-${itemId}`);

            if (select.value) {
                // Update selected items
                selectedItems = selectedItems.filter(id => id != select.dataset.previous);
                selectedItems.push(select.value);
                select.dataset.previous = select.value;

                // Set harga default
                const defaultHarga = selectedOption.getAttribute('data-harga');
                const stok = selectedOption.getAttribute('data-stok');

                hargaInput.value = defaultHarga;
                stokInfo.innerHTML = `Stok tersedia: ${stok} pcs`;

                // Update max qty
                const qtyInput = document.querySelector(`#item-${itemId} .qty-input`);
                qtyInput.max = stok;
            }

            updateSubtotal(itemId);
        }

        function updateSubtotal(itemId) {
            const qtyInput = document.querySelector(`#item-${itemId} .qty-input`);
            const hargaInput = document.querySelector(`#item-${itemId} .harga-input`);
            const subtotalSpan = document.getElementById(`subtotal-${itemId}`);

            const qty = parseInt(qtyInput.value) || 0;
            const harga = parseInt(hargaInput.value) || 0;
            const subtotal = qty * harga;

            subtotalSpan.textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
            hitungTotal();
        }

        function hapusItem(itemId) {
            const row = document.getElementById(`item-${itemId}`);
            const select = row.querySelector('.koko-select');

            // Hapus dari selected items
            if (select.value) {
                selectedItems = selectedItems.filter(id => id != select.value);
            }

            row.remove();
            hitungTotal();
        }

        function hitungTotal() {
            let total = 0;
            document.querySelectorAll('[id^="subtotal-"]').forEach(span => {
                const subtotalText = span.textContent.replace(/[^\d]/g, '');
                total += parseInt(subtotalText) || 0;
            });

            document.getElementById('total-harga').textContent = 'Rp ' + total.toLocaleString('id-ID');
        }

        // Tambah item pertama saat halaman dimuat
        document.addEventListener('DOMContentLoaded', tambahItem);
    </script>
</body>

</html>