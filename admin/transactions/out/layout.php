<?php
require_once '../../../config/auth.php';
require_role('admin');

$title = "Tambah Penjualan";

// Ambil data produk dari database
require_once '../../../config/database.php';
$products = $pdo->query("SELECT * FROM products WHERE stok > 0 ORDER BY nama")->fetchAll();

// Inisialisasi variabel
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi data dasar
    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        $error = "Harap tambahkan minimal 1 produk";
    } else {
        try {
            $pdo->beginTransaction();

            // Data customer
            $customer_nama = !empty($_POST['customer_nama']) ? trim($_POST['customer_nama']) : null;
            $customer_kontak = !empty($_POST['customer_kontak']) ? trim($_POST['customer_kontak']) : null;

            // Generate kode transaksi
            $kode_transaksi = 'TRX-OUT-' . date('YmdHis');

            // Insert transaksi
            $stmt = $pdo->prepare("INSERT INTO transactions 
                                (kode_transaksi, tipe, tanggal, user_id, customer_nama, customer_kontak, total) 
                                VALUES (?, 'keluar', NOW(), ?, ?, ?, ?)");
            $stmt->execute([
                $kode_transaksi,
                $_SESSION['user_id'],
                $customer_nama,
                $customer_kontak,
                0 // Total sementara 0
            ]);
            $transaction_id = $pdo->lastInsertId();

            // Proses setiap produk
            $total_transaksi = 0;
            $product_details = [];

            foreach ($_POST['products'] as $index => $product) {
                // Validasi data produk
                if (empty($product['id']) || empty($product['quantity']) || empty($product['harga_jual'])) {
                    throw new Exception("Data produk pada baris " . ($index + 1) . " tidak lengkap");
                }

                $product_id = (int)$product['id'];
                $quantity = (int)$product['quantity'];
                $harga_jual = (float)$product['harga_jual'];

                // Validasi nilai
                if ($quantity <= 0) {
                    throw new Exception("Quantity harus lebih dari 0 pada baris " . ($index + 1));
                }

                if ($harga_jual <= 0) {
                    throw new Exception("Harga jual harus lebih dari 0 pada baris " . ($index + 1));
                }

                // Cek stok tersedia
                $stmt = $pdo->prepare("SELECT id, nama, stok FROM products WHERE id = ? FOR UPDATE");
                $stmt->execute([$product_id]);
                $product_data = $stmt->fetch();

                if (!$product_data) {
                    throw new Exception("Produk tidak ditemukan pada baris " . ($index + 1));
                }

                if ($product_data['stok'] < $quantity) {
                    throw new Exception("Stok tidak mencukupi untuk produk: " . $product_data['nama'] .
                        " (Stok tersedia: " . $product_data['stok'] . ")");
                }

                // Hitung subtotal
                $subtotal = $quantity * $harga_jual;

                // Simpan detail produk
                $product_details[] = [
                    'transaction_id' => $transaction_id,
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'harga_satuan' => $harga_jual,
                    'subtotal' => $subtotal
                ];

                $total_transaksi += $subtotal;
            }

            // Insert semua detail transaksi
            foreach ($product_details as $detail) {
                $stmt = $pdo->prepare("INSERT INTO transaction_details 
                                    (transaction_id, product_id, quantity, harga_satuan, subtotal) 
                                    VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $detail['transaction_id'],
                    $detail['product_id'],
                    $detail['quantity'],
                    $detail['harga_satuan'],
                    $detail['subtotal']
                ]);

                // Update stok produk
                // $pdo->prepare("UPDATE products SET stok = stok - ? WHERE id = ?")
                //     ->execute([$detail['quantity'], $detail['product_id']]);
            }

            // Update total transaksi
            $pdo->prepare("UPDATE transactions SET total = ? WHERE id = ?")
                ->execute([$total_transaksi, $transaction_id]);

            // Proses pembayaran
            $metode_bayar = $_POST['metode_bayar'] ?? 'tunai';
            $dibayar = (float)($_POST['dibayar'] ?? 0);

            // Validasi pembayaran
            if ($dibayar < 0) {
                throw new Exception("Jumlah pembayaran tidak valid");
            }

            // Generate nomor invoice
            $no_invoice = 'INV-' . date('Ymd') . '-' . str_pad($transaction_id, 4, '0', STR_PAD_LEFT);

            if ($metode_bayar === 'kredit') {
                // Validasi kredit
                $min_dp = $total_transaksi * 0.3;
                if ($dibayar < $min_dp) {
                    throw new Exception("DP minimal 30% dari total (Rp " . number_format($min_dp, 0, ',', '.') . ")");
                }

                $jumlah_angsuran = (int)($_POST['jumlah_angsuran'] ?? 2);
                $interval_angsuran = (int)($_POST['interval_angsuran'] ?? 30);
                $sisa_bayar = $total_transaksi - $dibayar;

                // Insert invoice
                $stmt = $pdo->prepare("INSERT INTO invoices 
                                      (transaction_id, no_invoice, tanggal_invoice, total, dibayar, sisa, 
                                      status_bayar, jatuh_tempo, metode_bayar) 
                                      VALUES (?, ?, CURDATE(), ?, ?, ?, 'cicilan', DATE_ADD(CURDATE(), INTERVAL ? DAY), ?)");
                $stmt->execute([
                    $transaction_id,
                    $no_invoice,
                    $total_transaksi,
                    $dibayar,
                    $sisa_bayar,
                    $interval_angsuran,
                    $metode_bayar
                ]);
                $invoice_id = $pdo->lastInsertId();

                // Buat jadwal angsuran
                $jumlah_per_angsuran = $sisa_bayar / $jumlah_angsuran;

                for ($i = 1; $i <= $jumlah_angsuran; $i++) {
                    $jatuh_tempo = date('Y-m-d', strtotime("+ " . ($i * $interval_angsuran) . " days"));
                    $stmt = $pdo->prepare("INSERT INTO installments 
                                          (invoice_id, jumlah, tanggal_jatuh_tempo, status) 
                                          VALUES (?, ?, ?, 'belum_lunas')");
                    $stmt->execute([
                        $invoice_id,
                        $jumlah_per_angsuran,
                        $jatuh_tempo
                    ]);
                }
            } else {
                // Untuk tunai/transfer
                $status = ($dibayar >= $total_transaksi) ? 'lunas' : 'belum_lunas';

                $stmt = $pdo->prepare("INSERT INTO invoices 
                                      (transaction_id, no_invoice, tanggal_invoice, total, dibayar, sisa, 
                                      status_bayar, metode_bayar) 
                                      VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $transaction_id,
                    $no_invoice,
                    $total_transaksi,
                    $dibayar,
                    $total_transaksi - $dibayar,
                    $status,
                    $metode_bayar
                ]);
            }

            $pdo->commit();

            $_SESSION['success'] = "Transaksi penjualan berhasil dicatat dengan nomor invoice: " . $no_invoice;
            header("Location: detail.php?id=" . $transaction_id);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Terjadi kesalahan database: " . $e->getMessage();
            error_log("PDOException: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>


<!-- Head start -->
<?php include '../../../includes/head.php'; ?>
<!-- Head end -->

<body>
    <div id="app">

        <!-- Sidebar start -->
        <?php include '../../../includes/side.php'; ?>
        <!-- sidebar end -->

        <!-- main content start -->
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

            <!-- Content title start -->
            <div class="page-heading">
                <h3>DETAIL TRANSAKSI</h3>
            </div>
            <!-- Content title end -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <!-- Content Start -->

            <div class="page-content">

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" id="sales-form">
                    <!-- Form input customer -->
                    <div class="card mb-4">
                        <div class="card-header">Informasi Customer</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customer_nama" class="form-label">Nama Customer</label>
                                        <input type="text" class="form-control" id="customer_nama" name="customer_nama"
                                            value="<?= isset($_POST['customer_nama']) ? htmlspecialchars($_POST['customer_nama']) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customer_kontak" class="form-label">Kontak</label>
                                        <input type="text" class="form-control" id="customer_kontak" name="customer_kontak"
                                            value="<?= isset($_POST['customer_kontak']) ? htmlspecialchars($_POST['customer_kontak']) : '' ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar produk -->
                    <div class="card mb-4">
                        <div class="card-header">Daftar Barang</div>
                        <div class="card-body">
                            <div id="product-list">
                                <!-- Barang akan ditambahkan di sini via JavaScript -->
                            </div>

                            <button type="button" class="btn btn-secondary mt-3" id="add-product">
                                <i class="fas fa-plus"></i> Tambah Barang
                            </button>

                            <div class="mt-4 border-top pt-3">
                                <div class="row justify-content-end">
                                    <div class="col-md-4">
                                        <div class="input-group mb-3">
                                            <span class="input-group-text">Total</span>
                                            <input type="text" class="form-control text-end" id="total-amount"
                                                value="0" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pembayaran -->
                    <div class="card mb-4">
                        <div class="card-header">Pembayaran</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="metode_bayar" class="form-label">Metode Pembayaran</label>
                                        <select class="form-select" id="metode_bayar" name="metode_bayar" required>
                                            <option value="tunai" <?= (isset($_POST['metode_bayar']) && $_POST['metode_bayar'] === 'tunai') ? 'selected' : '' ?>>Tunai</option>
                                            <option value="transfer" <?= (isset($_POST['metode_bayar']) && $_POST['metode_bayar'] === 'transfer') ? 'selected' : '' ?>>Transfer</option>
                                            <option value="kredit" <?= (isset($_POST['metode_bayar']) && $_POST['metode_bayar'] === 'kredit') ? 'selected' : '' ?>>Kredit</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="dibayar" class="form-label">Dibayar</label>
                                        <input type="number" class="form-control" id="dibayar" name="dibayar"
                                            min="0" value="<?= isset($_POST['dibayar']) ? htmlspecialchars($_POST['dibayar']) : '0' ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="sisa" class="form-label">Sisa</label>
                                        <input type="text" class="form-control" id="sisa" value="0" readonly>
                                    </div>
                                </div>
                            </div>

                            <div id="credit-options" style="display: none;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="jumlah_angsuran" class="form-label">Jumlah Angsuran</label>
                                            <select class="form-select" id="jumlah_angsuran" name="jumlah_angsuran">
                                                <option value="2" <?= (isset($_POST['jumlah_angsuran']) && $_POST['jumlah_angsuran'] == 2) ? 'selected' : '' ?>>2x</option>
                                                <option value="3" <?= (isset($_POST['jumlah_angsuran']) && $_POST['jumlah_angsuran'] == 3) ? 'selected' : '' ?>>3x</option>
                                                <option value="4" <?= (isset($_POST['jumlah_angsuran']) && $_POST['jumlah_angsuran'] == 4) ? 'selected' : '' ?>>4x</option>
                                                <option value="6" <?= (isset($_POST['jumlah_angsuran']) && $_POST['jumlah_angsuran'] == 6) ? 'selected' : '' ?>>6x</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="interval_angsuran" class="form-label">Interval (hari)</label>
                                            <select class="form-select" id="interval_angsuran" name="interval_angsuran">
                                                <option value="7" <?= (isset($_POST['interval_angsuran']) && $_POST['interval_angsuran'] == 7) ? 'selected' : '' ?>>7 hari</option>
                                                <option value="14" <?= (isset($_POST['interval_angsuran']) && $_POST['interval_angsuran'] == 14) ? 'selected' : '' ?>>14 hari</option>
                                                <option value="30" <?= (isset($_POST['interval_angsuran']) && $_POST['interval_angsuran'] == 30) ? 'selected' : '' ?>>30 hari</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Minimal DP</label>
                                            <input type="text" class="form-control" id="min-dp" value="0" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">Kembali</a>
                        <button type="submit" class="btn btn-primary">Simpan Transaksi</button>
                    </div>
                </form>

                <!-- Template untuk row produk -->
                <template id="product-template">
                    <div class="product-row mb-3 border p-3">
                        <div class="row">
                            <div class="col-md-5">
                                <select class="form-select product-select" name="products[][id]" required>
                                    <option value="">Pilih Produk</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product['id'] ?>"
                                            data-harga="<?= $product['harga_jual'] ?>"
                                            data-stok="<?= $product['stok'] ?>">
                                            <?= htmlspecialchars($product['nama']) ?>
                                            (Stok: <?= $product['stok'] ?>, Harga: <?= number_format($product['harga_jual'], 0, ',', '.') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control quantity" name="products[0][quantity]"
                                    min="1" value="1" required>
                                <small class="text-muted stok-info">Stok: 0</small>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control harga" name="products[0][harga_jual]"
                                    step="100" min="0" required>
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control subtotal" readonly>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm remove-product">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const productList = document.getElementById('product-list');
                        const addButton = document.getElementById('add-product');
                        const template = document.getElementById('product-template');
                        const totalAmountInput = document.getElementById('total-amount');
                        const metodeBayarSelect = document.getElementById('metode_bayar');
                        const dibayarInput = document.getElementById('dibayar');
                        const sisaInput = document.getElementById('sisa');
                        const creditOptions = document.getElementById('credit-options');
                        const minDpInput = document.getElementById('min-dp');
                        const salesForm = document.getElementById('sales-form');

                        let totalAmount = 0;

                        // Fungsi untuk menambahkan row produk
                        function addProductRow(productData = null) {
                            const clone = template.content.cloneNode(true);
                            const row = clone.querySelector('.product-row');

                            if (productData) {
                                const select = row.querySelector('.product-select');
                                const quantity = row.querySelector('.quantity');
                                const harga = row.querySelector('.harga');
                                const stokInfo = row.querySelector('.stok-info');

                                select.value = productData.id;
                                quantity.value = productData.quantity;
                                harga.value = productData.harga_jual;

                                // Trigger event change untuk update stok info
                                const event = new Event('change');
                                select.dispatchEvent(event);
                            }

                            productList.appendChild(row);
                            updateProductIndexes();

                            // Set harga default untuk row baru
                            const select = row.querySelector('.product-select');
                            if (select.options.length > 1 && !productData) {
                                select.selectedIndex = 1; // Pilih produk pertama (setelah option kosong)
                                const harga = select.options[select.selectedIndex].dataset.harga;
                                row.querySelector('.harga').value = harga;
                                calculateSubtotal(row);
                            }
                        }

                        // Fungsi untuk update index array produk
                        function updateProductIndexes() {
                            const rows = productList.querySelectorAll('.product-row');
                            rows.forEach((row, index) => {
                                const inputs = row.querySelectorAll('select, input');
                                inputs.forEach(input => {
                                    const name = input.name.replace(/products\[\d+\]/, `products[${index}]`);
                                    input.name = name;
                                });
                            });
                        }

                        // Fungsi untuk menghitung subtotal per produk
                        function calculateSubtotal(row) {
                            const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
                            const harga = parseFloat(row.querySelector('.harga').value) || 0;
                            const subtotal = quantity * harga;

                            const subtotalInput = row.querySelector('.subtotal');
                            subtotalInput.value = formatCurrency(subtotal);

                            calculateTotal();
                        }

                        // Fungsi untuk menghitung total
                        function calculateTotal() {
                            let newTotal = 0;
                            const rows = productList.querySelectorAll('.product-row');

                            rows.forEach(row => {
                                const subtotalInput = row.querySelector('.subtotal');
                                const subtotalValue = subtotalInput.value.replace(/[^\d]/g, '');
                                newTotal += parseFloat(subtotalValue) || 0;
                            });

                            updateTotal(newTotal);
                        }

                        // Fungsi untuk update total
                        function updateTotal(newTotal) {
                            totalAmount = newTotal;
                            totalAmountInput.value = formatCurrency(totalAmount);
                            updateMinDp();
                            updateSisa();
                        }

                        // Fungsi untuk update minimal DP
                        function updateMinDp() {
                            if (metodeBayarSelect.value === 'kredit') {
                                const minDp = totalAmount * 0.3;
                                minDpInput.value = formatCurrency(minDp);

                                if (parseFloat(dibayarInput.value.replace(/[^\d]/g, '')) < minDp) {
                                    dibayarInput.value = formatCurrency(minDp);
                                }
                                dibayarInput.min = minDp;
                            } else {
                                dibayarInput.min = 0;
                            }
                        }

                        // Fungsi untuk update sisa pembayaran
                        function updateSisa() {
                            const dibayar = parseFloat(dibayarInput.value.replace(/[^\d]/g, '')) || 0;
                            const sisa = Math.max(0, totalAmount - dibayar);
                            sisaInput.value = formatCurrency(sisa);
                        }

                        // Fungsi untuk format currency
                        function formatCurrency(amount) {
                            return 'Rp ' + amount.toFixed(0).replace(/\d(?=(\d{3})+$)/g, '$&.');
                        }

                        // Event listener untuk metode pembayaran
                        metodeBayarSelect.addEventListener('change', function() {
                            if (this.value === 'kredit') {
                                creditOptions.style.display = 'block';
                                updateMinDp();
                            } else {
                                creditOptions.style.display = 'none';
                                minDpInput.value = formatCurrency(0);
                                dibayarInput.min = 0;
                            }
                            updateSisa();
                        });

                        // Event listener untuk input dibayar
                        dibayarInput.addEventListener('input', updateSisa);

                        // Event listener untuk tambah produk
                        addButton.addEventListener('click', function() {
                            addProductRow();
                        });

                        // Event listener untuk hapus produk
                        productList.addEventListener('click', function(e) {
                            if (e.target.classList.contains('remove-product')) {
                                const row = e.target.closest('.product-row');
                                const subtotalInput = row.querySelector('.subtotal');
                                const subtotalValue = subtotalInput.value.replace(/[^\d]/g, '');

                                row.remove();
                                updateProductIndexes();
                                updateTotal(totalAmount - (parseFloat(subtotalValue) || 0));
                            }
                        });

                        // Event listener untuk perubahan produk
                        productList.addEventListener('change', function(e) {
                            if (e.target.classList.contains('product-select')) {
                                const selectedOption = e.target.options[e.target.selectedIndex];
                                const harga = selectedOption.dataset.harga;
                                const stok = selectedOption.dataset.stok;
                                const hargaInput = e.target.closest('.product-row').querySelector('.harga');
                                const stokInfo = e.target.closest('.product-row').querySelector('.stok-info');
                                const qtyInput = e.target.closest('.product-row').querySelector('.quantity');

                                if (harga && hargaInput) {
                                    hargaInput.value = harga;
                                    stokInfo.textContent = `Stok: ${stok}`;
                                    qtyInput.max = stok;

                                    // Reset quantity jika melebihi stok
                                    if (parseInt(qtyInput.value) > parseInt(stok)) {
                                        qtyInput.value = stok;
                                    }

                                    calculateSubtotal(e.target.closest('.product-row'));
                                }
                            }
                        });

                        // Event listener untuk perubahan quantity atau harga
                        productList.addEventListener('input', function(e) {
                            if (e.target.classList.contains('quantity') || e.target.classList.contains('harga')) {
                                calculateSubtotal(e.target.closest('.product-row'));
                            }
                        });

                        // Event listener untuk form submit
                        salesForm.addEventListener('submit', function(e) {
                            const productRows = productList.querySelectorAll('.product-row');
                            let isValid = true;

                            // Validasi produk
                            if (productRows.length === 0) {
                                alert('Harap tambahkan minimal 1 produk');
                                e.preventDefault();
                                return;
                            }

                            productRows.forEach(row => {
                                const productSelect = row.querySelector('.product-select');
                                const quantityInput = row.querySelector('.quantity');
                                const hargaInput = row.querySelector('.harga');

                                if (!productSelect.value || !quantityInput.value || !hargaInput.value) {
                                    isValid = false;
                                    row.style.border = '1px solid red';
                                } else {
                                    row.style.border = '';
                                }

                                // Validasi stok
                                const stok = parseInt(row.querySelector('.stok-info').textContent.replace('Stok: ', ''));
                                const qty = parseInt(quantityInput.value);
                                if (qty > stok) {
                                    isValid = false;
                                    quantityInput.classList.add('is-invalid');
                                    alert(`Jumlah melebihi stok yang tersedia (Stok: ${stok})`);
                                } else {
                                    quantityInput.classList.remove('is-invalid');
                                }
                            });

                            // Validasi pembayaran
                            if (metodeBayarSelect.value === 'kredit') {
                                const minDp = totalAmount * 0.3;
                                const dibayar = parseFloat(dibayarInput.value.replace(/[^\d]/g, '')) || 0;
                                if (dibayar < minDp) {
                                    alert(`DP minimal 30% dari total (${formatCurrency(minDp)})`);
                                    isValid = false;
                                }
                            }

                            if (!isValid) {
                                e.preventDefault();
                                alert('Harap lengkapi semua data produk dan pembayaran');
                            }
                        });

                        // Inisialisasi form jika ada data POST (setelah error)
                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['products'])): ?>
                            <?php foreach ($_POST['products'] as $product): ?>
                                addProductRow({
                                    id: <?= json_encode($product['id'] ?? '') ?>,
                                    quantity: <?= json_encode($product['quantity'] ?? 1) ?>,
                                    harga_jual: <?= json_encode($product['harga_jual'] ?? 0) ?>
                                });
                            <?php endforeach; ?>
                            calculateTotal();
                        <?php else: ?>
                            addProductRow();
                        <?php endif; ?>

                        // Inisialisasi metode bayar
                        if (metodeBayarSelect.value === 'kredit') {
                            creditOptions.style.display = 'block';
                            updateMinDp();
                        }
                    });
                </script>



            </div>
        </div>
    </div>
    <!-- Content End -->
    </div>
    <!-- main content end -->

    </div>


    <script src="../../../assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>

    <script src="../../../assets/vendors/apexcharts/apexcharts.js"></script>
    <script src="../../../assets/js/pages/dashboard.js"></script>

    <script src="../../../assets/js/main.js"></script>
</body>

</html>