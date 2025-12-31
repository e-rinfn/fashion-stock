<?php
// File: koko_masuk.php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

session_start();

// Ambil data petugas
$petugas = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");

// Jika ada referensi dari koko_keluar
$referensi_keluar = isset($_GET['referensi']) ? intval($_GET['referensi']) : 0;
$id_petugas = isset($_GET['petugas']) ? intval($_GET['petugas']) : 0;

if ($referensi_keluar > 0) {
    // Ambil data koko yang dikirim ke petugas ini
    $koko_dikirim = query("
        SELECT d.id_koko, d.jumlah as jumlah_dikirim, k.nama_koko, k.stok 
        FROM detail_koko_keluar d
        JOIN koko k ON d.id_koko = k.id_koko
        WHERE d.id_koko_keluar = $referensi_keluar
    ");
} else {
    $koko_dikirim = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_koko_masuk'])) {
    $id_petugas_finishing = intval($_POST['id_petugas_finishing']);
    $metode_pembayaran = 'transfer';
    $status = 'selesai';
    $items = $_POST['items'];
    $id_koko_keluar_ref = intval($_POST['id_koko_keluar_ref'] ?? 0);

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

                $total_harga += $harga * $qty;
            }

            // 2. Simpan koko_masuk
            $sql_masuk = "INSERT INTO koko_masuk 
                (id_petugas_finishing, tanggal_koko_masuk, total_harga, status, metode_pembayaran) 
                VALUES ($id_petugas_finishing, NOW(), $total_harga, '$status', '$metode_pembayaran')";

            if (!$conn->query($sql_masuk)) {
                throw new Exception("Gagal menyimpan koko_masuk: " . $conn->error);
            }

            $id_koko_masuk = $conn->insert_id;

            // 3. Simpan detail dan update stok
            foreach ($items as $item) {
                if (empty($item['id_koko'])) continue;

                $id_koko = intval($item['id_koko']);
                $qty = intval($item['qty']);
                $harga = floatval($item['harga']);
                $subtotal = $harga * $qty;

                // Simpan detail koko masuk
                $sql_detail = "INSERT INTO detail_koko_masuk 
                    (id_koko_masuk, id_koko, jumlah, harga_satuan, subtotal) 
                    VALUES ($id_koko_masuk, $id_koko, $qty, $harga, $subtotal)";

                if (!$conn->query($sql_detail)) {
                    throw new Exception("Gagal menyimpan detail masuk: " . $conn->error);
                }

                // Tambah stok koko
                $sql_update = "UPDATE koko SET stok = stok + $qty WHERE id_koko = $id_koko";
                if (!$conn->query($sql_update)) {
                    throw new Exception("Gagal update stok: " . $conn->error);
                }
            }

            // 4. Update status koko_keluar menjadi selesai jika ada referensi
            if ($id_koko_keluar_ref > 0) {
                $sql_update_keluar = "UPDATE koko_keluar SET status = 'selesai' WHERE id_koko_keluar = $id_koko_keluar_ref";
                $conn->query($sql_update_keluar);
            }

            $conn->commit();
            $_SESSION['success'] = "Koko hasil finishing berhasil dimasukkan ke stok!";
            header("Location: list.php?tipe_data=masuk");
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
    <title>Terima Hasil Finishing Koko</title>
    <style>
        .reference-info {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .item-row {
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>TERIMA HASIL FINISHING KOKO</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error'];
                                            unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Info Referensi (jika ada) -->
        <?php if ($referensi_keluar > 0 && !empty($koko_dikirim)): ?>
            <div class="reference-info">
                <h5>Referensi Pengiriman: #<?= $referensi_keluar ?></h5>
                <p>Silakan input hasil finishing berdasarkan koko yang dikirim:</p>
                <ul>
                    <?php foreach ($koko_dikirim as $kd): ?>
                        <li><?= $kd['nama_koko'] ?> - Dikirim: <?= $kd['jumlah_dikirim'] ?> pcs</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="id_koko_keluar_ref" value="<?= $referensi_keluar ?>">

            <!-- Pilih Petugas -->
            <div class="mb-3">
                <label>Pilih Petugas Finishing</label>
                <select name="id_petugas_finishing" class="form-control" required
                    <?= $id_petugas > 0 ? 'disabled' : '' ?>>
                    <option value="">-- Pilih Petugas --</option>
                    <?php foreach ($petugas as $p): ?>
                        <option value="<?= $p['id_petugas_finishing'] ?>"
                            <?= ($id_petugas == $p['id_petugas_finishing']) ? 'selected' : '' ?>>
                            <?= $p['nama_petugas'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($id_petugas > 0): ?>
                    <input type="hidden" name="id_petugas_finishing" value="<?= $id_petugas ?>">
                <?php endif; ?>
            </div>

            <!-- Daftar Koko Masuk -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5>Hasil Finishing yang Diterima</h5>
                </div>
                <div class="card-body" id="items-container">
                    <!-- Item akan ditambahkan via JavaScript -->
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-secondary" onclick="tambahItem()">
                        <i class="bx bx-plus"></i> Tambah Hasil
                    </button>
                </div>
            </div>

            <!-- Total -->
            <div class="card mb-3">
                <div class="card-body">
                    <h5>Total Nilai: <span id="total-harga">Rp 0</span></h5>
                </div>
            </div>

            <!-- Tombol Aksi -->
            <div class="d-flex gap-2">
                <button type="submit" name="simpan_koko_masuk" class="btn btn-success">
                    <i class="bx bx-check"></i> Simpan Hasil Finishing
                </button>
                <a href="list.php" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    <script>
        // Data koko yang tersedia (untuk dropdown)
        const kokoData = <?= json_encode(query("SELECT * FROM koko ORDER BY nama_koko")) ?>;
        // Data koko yang dikirim (jika ada referensi)
        const kokoDikirim = <?= json_encode($koko_dikirim) ?>;

        let itemCounter = 0;
        let selectedItems = [];

        function tambahItem() {
            const container = document.getElementById('items-container');
            const itemId = Date.now();

            // Filter koko yang belum dipilih
            const availableKoko = kokoData.filter(k => !selectedItems.includes(k.id_koko));

            if (availableKoko.length === 0) {
                alert('Semua koko sudah ditambahkan!');
                return;
            }

            // Buat opsi dropdown
            let options = '<option value="">Pilih Koko</option>';
            availableKoko.forEach(koko => {
                options += `<option value="${koko.id_koko}" data-harga="${koko.harga_jual}">
                    ${koko.nama_koko}
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

            // Jika ada referensi, auto-fill item pertama
            if (itemCounter === 1 && kokoDikirim.length > 0) {
                setTimeout(() => {
                    // Auto-select koko pertama yang dikirim
                    const firstKoko = kokoDikirim[0];
                    const select = document.querySelector(`#item-${itemId} .koko-select`);
                    select.value = firstKoko.id_koko;
                    updateItem(itemId);

                    // Set qty berdasarkan yang dikirim
                    const qtyInput = document.querySelector(`#item-${itemId} .qty-input`);
                    qtyInput.value = firstKoko.jumlah_dikirim;
                    updateSubtotal(itemId);
                }, 100);
            }
        }

        function updateItem(itemId) {
            const select = document.querySelector(`#item-${itemId} .koko-select`);
            const selectedOption = select.options[select.selectedIndex];
            const hargaInput = document.querySelector(`#item-${itemId} .harga-input`);

            if (select.value) {
                // Update selected items
                selectedItems = selectedItems.filter(id => id != select.dataset.previous);
                selectedItems.push(select.value);
                select.dataset.previous = select.value;

                // Set harga default
                const defaultHarga = selectedOption.getAttribute('data-harga');
                hargaInput.value = defaultHarga;
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