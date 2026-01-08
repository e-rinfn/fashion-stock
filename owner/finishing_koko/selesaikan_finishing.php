<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Ambil data finishing dengan status 'pengiriman' atau 'diproses'
$sql_finishing = "SELECT 
    hk.*, 
    p.nama_produk, 
    pet.nama_petugas,
    GROUP_CONCAT(DISTINCT k.nama_koko ORDER BY k.nama_koko SEPARATOR ', ') as jenis_bahan,
    SUM(dh.jumlah) as total_bahan
FROM hasil_kirim_finishing hk 
LEFT JOIN produk p ON hk.id_produk = p.id_produk 
LEFT JOIN petugas_finishing pet ON hk.id_petugas_finishing = pet.id_petugas_finishing 
LEFT JOIN detail_hasil_kirim_finishing dh ON hk.id_hasil_kirim_finishing = dh.id_hasil_kirim_finishing
LEFT JOIN koko k ON dh.id_koko = k.id_koko
WHERE hk.status_finishing IN ('pengiriman', 'diproses')
GROUP BY hk.id_hasil_kirim_finishing
ORDER BY hk.tanggal_kirim_finishing DESC";

$data_finishing = query($sql_finishing);

// Ambil semua produk
$produk = query("SELECT * FROM produk ORDER BY nama_produk");

// Proses penyimpanan hasil finishing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_hasil'])) {
    $id_hasil_kirim_finishing = intval($_POST['id_hasil_kirim_finishing']);
    $tanggal_hasil = $conn->real_escape_string($_POST['tanggal_hasil']);
    $status_finishing = $conn->real_escape_string($_POST['status_finishing']);
    $items = $_POST['items'];

    // Debug
    error_log("Data yang diterima:");
    error_log("ID Finishing: " . $id_hasil_kirim_finishing);
    error_log("Tanggal Hasil: " . $tanggal_hasil);
    error_log("Status: " . $status_finishing);
    error_log("Items: " . print_r($items, true));

    $conn->autocommit(FALSE);
    try {
        // 1. Update data finishing utama
        $sql_update = "UPDATE hasil_kirim_finishing 
                      SET tanggal_hasil_finishing = ?,
                          status_finishing = ?,
                          total_hasil_finishing = ?
                      WHERE id_hasil_kirim_finishing = ?";

        // Hitung total hasil
        $total_hasil = 0;
        foreach ($items as $item) {
            $total_hasil += intval($item['jumlah']);
        }

        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ssii", $tanggal_hasil, $status_finishing, $total_hasil, $id_hasil_kirim_finishing);

        if (!$stmt->execute()) {
            throw new Exception("Gagal update data finishing: " . $conn->error);
        }

        // 2. Update detail hasil finishing
        foreach ($items as $item) {
            $id_koko = intval($item['id_koko']);
            $id_produk = intval($item['id_produk']);
            $jumlah = intval($item['jumlah']);
            $harga_satuan = floatval($item['harga_satuan']);

            if ($jumlah > 0) {
                // Update detail
                $sql_detail = "UPDATE detail_hasil_kirim_finishing 
                              SET id_produk = ?,
                                  jumlah = ?,
                                  harga_satuan = ?,
                                  subtotal = ?
                              WHERE id_hasil_kirim_finishing = ? AND id_koko = ?";

                $subtotal = $jumlah * $harga_satuan;
                $stmt_detail = $conn->prepare($sql_detail);
                $stmt_detail->bind_param("iiddii", $id_produk, $jumlah, $harga_satuan, $subtotal, $id_hasil_kirim_finishing, $id_koko);

                if (!$stmt_detail->execute()) {
                    throw new Exception("Gagal update detail finishing: " . $conn->error);
                }

                // 3. Update stok produk (tambah stok produk hasil)
                if ($id_produk > 0) {
                    $sql_stok = "UPDATE produk SET stok = stok + ? WHERE id_produk = ?";
                    $stmt_stok = $conn->prepare($sql_stok);
                    $stmt_stok->bind_param("ii", $jumlah, $id_produk);

                    if (!$stmt_stok->execute()) {
                        throw new Exception("Gagal update stok produk: " . $conn->error);
                    }

                    error_log("Stok produk $id_produk ditambah $jumlah");
                }
            }
        }

        $conn->commit();
        $conn->autocommit(TRUE);

        $_SESSION['success'] = "✅ Hasil finishing berhasil disimpan!";
        header("Location: finishing.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(TRUE);
        $error = "❌ Error: " . $e->getMessage();
        error_log("ERROR: " . $e->getMessage());
    }
}
?>

<style>
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .table th {
        font-size: 0.9rem;
        font-weight: 600;
    }

    .table td {
        font-size: 0.9rem;
    }

    .badge-status {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    .modal-lg {
        max-width: 800px;
    }

    .form-control:disabled {
        background-color: #f8f9fa;
    }

    .btn-submit {
        background-color: #28a745;
        border-color: #28a745;
        color: white;
    }

    .btn-submit:hover {
        background-color: #218838;
        border-color: #1e7e34;
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
                    <h2>Selesaikan Finishing</h2>
                    <a href="finishing.php" class="btn btn-secondary">
                        <i class="ti ti-arrow-left"></i> Kembali
                    </a>
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

                    <?php if (empty($data_finishing)): ?>
                        <div class="alert alert-info">
                            <i class="ti ti-info-circle"></i> Tidak ada data finishing yang perlu diselesaikan.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th>Seri</th>
                                        <th>Petugas Finishing</th>
                                        <th>Produk</th>
                                        <th>Jenis Bahan</th>
                                        <th>Total Bahan</th>
                                        <th>Tgl Kirim</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data_finishing as $data): ?>
                                        <?php
                                        $status_badge = [
                                            'pengiriman' => 'secondary',
                                            'diproses' => 'warning',
                                            'selesai' => 'success'
                                        ];
                                        $status_color = $status_badge[$data['status_finishing']] ?? 'secondary';
                                        ?>
                                        <tr>
                                            <td class="text-center"><?= htmlspecialchars($data['seri']) ?></td>
                                            <td><?= htmlspecialchars($data['nama_petugas']) ?></td>
                                            <td><?= htmlspecialchars($data['nama_produk']) ?></td>
                                            <td>
                                                <?php if (!empty($data['jenis_bahan'])): ?>
                                                    <small><?= htmlspecialchars($data['jenis_bahan']) ?></small>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?= $data['total_bahan'] ?? 0 ?></td>
                                            <td class="text-center"><?= date('d/m/Y', strtotime($data['tanggal_kirim_finishing'])) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $status_color ?> badge-status">
                                                    <?= ucfirst($data['status_finishing']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-primary btn-input-hasil"
                                                    data-id="<?= $data['id_hasil_kirim_finishing'] ?>"
                                                    data-seri="<?= htmlspecialchars($data['seri']) ?>">
                                                    <i class="ti ti-edit"></i> Input Hasil
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Modal Input Hasil -->
    <div class="modal fade" id="modalInputHasil" tabindex="-1" aria-labelledby="modalInputHasilLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalInputHasilLabel">Input Hasil Finishing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="formInputHasil">
                    <div class="modal-body">
                        <div id="modalContent">
                            <!-- Konten akan diisi via AJAX -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="simpan_hasil" class="btn btn-submit">
                            <i class="ti ti-save"></i> Simpan Hasil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Data produk untuk dropdown
        const produkData = <?= json_encode($produk) ?>;

        // Event untuk tombol input hasil
        $(document).on('click', '.btn-input-hasil', function() {
            const id = $(this).data('id');
            const seri = $(this).data('seri');

            // Load data via AJAX
            $.ajax({
                url: 'get_data_finishing.php',
                type: 'GET',
                data: {
                    id: id
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderModalContent(response.data, seri);
                        $('#modalInputHasil').modal('show');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Gagal mengambil data'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Gagal mengambil data dari server'
                    });
                }
            });
        });

        function renderModalContent(data, seri) {
            let html = `
                <input type="hidden" name="id_hasil_kirim_finishing" value="${data.id_hasil_kirim_finishing}">
                <input type="hidden" name="status_finishing" value="selesai">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Seri</label>
                        <input type="text" class="form-control" value="${seri}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tanggal Hasil Finishing</label>
                        <input type="datetime-local" name="tanggal_hasil" class="form-control" 
                               value="${new Date().toISOString().slice(0, 16)}" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Petugas Finishing</label>
                        <input type="text" class="form-control" value="${data.nama_petugas}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tanggal Kirim</label>
                        <input type="text" class="form-control" value="${data.tanggal_kirim_finishing}" readonly>
                    </div>
                </div>
                
                <h5 class="mt-4 mb-3">Detail Bahan Baku</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr class="text-center">
                                <th>Bahan Baku (Koko)</th>
                                <th>Jumlah Awal</th>
                                <th>Produk Hasil</th>
                                <th>Jumlah Hasil</th>
                                <th>Harga Satuan</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>`;

            let totalHasil = 0;

            data.detail.forEach((item, index) => {
                const produkOptions = produkData.map(p =>
                    `<option value="${p.id_produk}">${p.nama_produk}</option>`
                ).join('');

                html += `
                    <tr>
                        <td>
                            <input type="hidden" name="items[${index}][id_koko]" value="${item.id_koko}">
                            <input type="text" class="form-control" value="${item.nama_koko}" readonly>
                        </td>
                        <td class="text-center">
                            <input type="number" class="form-control text-center" value="${item.jumlah}" readonly>
                        </td>
                        <td>
                            <select name="items[${index}][id_produk]" class="form-control select-produk" required>
                                <option value="">Pilih Produk</option>
                                ${produkOptions}
                            </select>
                        </td>
                        <td>
                            <input type="number" name="items[${index}][jumlah]" 
                                   class="form-control qty-hasil" min="0" max="${item.jumlah}" 
                                   value="${item.jumlah}" required>
                        </td>
                        <td>
                            <input type="number" name="items[${index}][harga_satuan]" 
                                   class="form-control harga-satuan" value="${item.harga_satuan}" 
                                   step="0.01" min="0" required>
                        </td>
                        <td>
                            <input type="text" class="form-control subtotal" value="${(item.jumlah * item.harga_satuan).toFixed(2)}" readonly>
                        </td>
                    </tr>`;
            });

            html += `
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Total Hasil Finishing</strong></td>
                                <td>
                                    <input type="text" id="totalHasil" class="form-control" value="0" readonly>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>`;

            $('#modalContent').html(html);

            // Event untuk menghitung total
            $(document).on('input', '.qty-hasil, .harga-satuan', function() {
                calculateTotals();
            });

            // Event untuk select produk
            $(document).on('change', '.select-produk', function() {
                const row = $(this).closest('tr');
                const qty = row.find('.qty-hasil');
                const maxQty = qty.attr('max');
                qty.val(maxQty);
                calculateTotals();
            });

            calculateTotals();
        }

        function calculateTotals() {
            let total = 0;

            $('tbody tr').each(function() {
                const qty = parseFloat($(this).find('.qty-hasil').val()) || 0;
                const harga = parseFloat($(this).find('.harga-satuan').val()) || 0;
                const subtotal = qty * harga;

                $(this).find('.subtotal').val(subtotal.toFixed(2));
                total += subtotal;
            });

            $('#totalHasil').val(total.toFixed(2));
        }

        // Validasi form
        $('#formInputHasil').on('submit', function(e) {
            let valid = true;
            let errorMessage = '';

            // Cek semua produk sudah dipilih
            $('.select-produk').each(function(index) {
                if (!$(this).val()) {
                    valid = false;
                    errorMessage = `Pilih produk untuk bahan baku ${index + 1}`;
                    $(this).focus();
                    return false;
                }
            });

            // Cek jumlah tidak melebihi maksimal
            if (valid) {
                $('.qty-hasil').each(function() {
                    const max = parseFloat($(this).attr('max')) || 0;
                    const value = parseFloat($(this).val()) || 0;

                    if (value > max) {
                        valid = false;
                        errorMessage = `Jumlah tidak boleh melebihi ${max}`;
                        $(this).focus();
                        return false;
                    }
                });
            }

            if (!valid) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validasi Error',
                    text: errorMessage
                });
            }
        });
    });
</script>

</html>