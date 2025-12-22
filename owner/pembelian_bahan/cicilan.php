<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

$id_pembelian_bahan = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data pembelian_bahan
$pembelian_bahan = query("SELECT p.*, r.nama_supplier 
                    FROM pembelian_bahan p
                    JOIN supplier r ON p.id_supplier = r.id_supplier
                    WHERE p.id_pembelian_bahan = $id_pembelian_bahan")[0] ?? null;

if (!$pembelian_bahan) {
    header("Location: list.php");
    exit();
}

// Ambil data cicilan_pembelian_bahan
$cicilan_pembelian_bahan = query("SELECT * FROM cicilan_pembelian_bahan WHERE id_pembelian_bahan = $id_pembelian_bahan ORDER BY tanggal_bayar DESC");

// Hitung total sudah dibayar
$total_dibayar = array_sum(array_column($cicilan_pembelian_bahan, 'jumlah_cicilan'));

// Sisa hutang
$sisa_hutang = $pembelian_bahan['total_harga'] - $total_dibayar;

$bukti_pembayaran = null; // default null

// Ambil detail pembelian_bahan
$detail = query("SELECT d.*, pr.nama_bahan 
                FROM detail_pembelian_bahan d
                JOIN bahan_baku pr ON d.id_bahan = pr.id_bahan
                WHERE d.id_pembelian_bahan = $id_pembelian_bahan");


if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['bukti_pembayaran']['tmp_name'];
    $fileName = $_FILES['bukti_pembayaran']['name'];
    $fileSize = $_FILES['bukti_pembayaran']['size'];
    $fileType = $_FILES['bukti_pembayaran']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    // Allowed extensions
    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

    if (in_array($fileExtension, $allowedfileExtensions)) {
        if ($fileSize <= 2 * 1024 * 1024) { // max 2MB
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

            // UNTUK LOKAL
            $uploadFileDir = './bukti/';

            // UNTUK SERVER
            // $uploadFileDir = $_SERVER['DOCUMENT_ROOT'] . '/admin/penjualan/bukti/';

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $bukti_pembayaran = $newFileName;
            } else {
                $error = "Gagal meng-upload file bukti pembayaran.";
            }
        } else {
            $error = "Ukuran file terlalu besar. Maksimal 2MB.";
        }
    } else {
        $error = "Format file tidak diizinkan. Hanya jpg, jpeg, png, dan pdf.";
    }
}

// Proses tambah cicilan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_cicilan'])) {
    $jumlah = floatval(str_replace('.', '', $_POST['jumlah']));
    $tanggal = $_POST['tanggal'];
    $metode = $conn->real_escape_string($_POST['metode']);
    $keterangan = $conn->real_escape_string($_POST['keterangan'] ?? '');

    if ($jumlah > 0 && $jumlah <= $sisa_hutang) {
        $sql = "INSERT INTO cicilan_pembelian_bahan (id_pembelian_bahan, jumlah_cicilan, tanggal_jatuh_tempo, tanggal_bayar, status, metode_pembayaran, bukti_pembayaran) 
                VALUES ($id_pembelian_bahan, $jumlah, '$tanggal', '$tanggal', 'lunas', '$metode', " . ($bukti_pembayaran ? "'$bukti_pembayaran'" : "NULL") . ")";

        if ($conn->query($sql)) {
            // Update status jika sudah lunas
            $new_total = $total_dibayar + $jumlah;
            if ($new_total >= $pembelian_bahan['total_harga']) {
                $conn->query("UPDATE pembelian_bahan SET status_pembayaran = 'lunas' WHERE id_pembelian_bahan = $id_pembelian_bahan");
            }

            header("Location: cicilan.php?id=$id_pembelian_bahan&success=1");
            exit();
        } else {
            $error = "Gagal menambahkan cicilan: " . $conn->error;
        }
    } else {
        $error = "Jumlah cicilan tidak valid!";
    }
}

// Hitung total cicilan
$cicilan = query("SELECT SUM(jumlah_cicilan) as total FROM cicilan_pembelian_bahan WHERE id_pembelian_bahan = $id_pembelian_bahan AND status = 'lunas'")[0];
$total_cicilan = $cicilan['total'] ?? 0;


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
                    <h2>PEMBELIAN BAHAN BAKU</h2>
                    <div>
                        <a href="list.php" class="btn btn-secondary">
                            <i class="ti ti-arrow-back"></i> Kembali
                        </a>
                    </div>
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


                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <table class="table table-bordered">
                                                <tr>
                                                    <th width="30%">Supplier</th>
                                                    <td><?= $pembelian_bahan['nama_supplier'] ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <td><?= dateIndo($pembelian_bahan['tanggal_pembelian']) ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-8">
                                            <table class="table table-bordered">
                                                <tr>
                                                    <th width="30%">Total Harga</th>
                                                    <td><?= formatRupiah($pembelian_bahan['total_harga']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Status Pembayaran</th>
                                                    <td>
                                                        <?= ucfirst($pembelian_bahan['status_pembayaran']) ?>

                                                        <?php if ($pembelian_bahan['status_pembayaran'] == 'cicilan'): ?>
                                                            <br>
                                                            Dibayar: <?= formatRupiah($total_cicilan) ?>
                                                            dari <?= formatRupiah($pembelian_bahan['total_harga']) ?>

                                                            <?php
                                                            $sisa_hutang = $pembelian_bahan['total_harga'] - $total_cicilan;
                                                            ?>
                                                            <br>
                                                            Sisa Hutang: <?= formatRupiah($sisa_hutang) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>

                                                <!-- <tr>
                                                <th>Metode Pembayaran</th>
                                                <td><?= ucfirst($pembelian_bahan['metode_pembayaran']) ?></td>
                                            </tr> -->
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header text-center">
                                    <h3>Daftar Bahan Baku Yang Dibeli</h3>
                                    <hr>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr class="text-center">
                                                <th>No</th>
                                                <th>Bahan</th>
                                                <th>Harga Per Meter</th>
                                                <th>Meter</th>
                                                <th>Roll/Yard</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detail as $i => $d): ?>
                                                <?php
                                                // Hitung subtotal = harga per meter x meter
                                                $subtotal = $d['harga_satuan'] * $d['meter'];
                                                ?>
                                                <tr>
                                                    <td class="text-center"><?= $i + 1 ?></td>
                                                    <td><?= $d['nama_bahan'] ?></td>
                                                    <td class="text-end"><?= formatRupiah($d['harga_satuan']) ?></td>
                                                    <td class="text-center"><?= $d['meter'] ?> m</td>
                                                    <td class="text-center"><?= $d['jumlah'] ?></td>
                                                    <td class="text-end"><?= formatRupiah($subtotal) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>

                                        <tfoot>
                                            <tr>
                                                <th colspan="5" class="text-right">Total</th>
                                                <th class="fs-6 text-end"><?= formatRupiah($pembelian_bahan['total_harga']) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <hr>

                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header text-center">
                                    <h3>Riwayat Pembayaran</h3>
                                    <hr>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($cicilan_pembelian_bahan)): ?>
                                        <div class="alert alert-info">Belum ada pembayaran</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr class="text-center">
                                                        <th>No</th>
                                                        <th>Tanggal</th>
                                                        <th>Jumlah</th>
                                                        <th>Metode</th>
                                                        <th>Bukti</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($cicilan_pembelian_bahan as $i => $c): ?>
                                                        <tr>
                                                            <td class="text-center"><?= $i + 1 ?></td>
                                                            <td><?= dateIndo($c['tanggal_bayar']) ?></td>
                                                            <td><?= formatRupiah($c['jumlah_cicilan']) ?></td>
                                                            <td><?= ucfirst($c['metode_pembayaran']) ?></td>
                                                            <td class="text-center">
                                                                <?php if ($c['bukti_pembayaran']): ?>
                                                                    <?php
                                                                    $ext = pathinfo($c['bukti_pembayaran'], PATHINFO_EXTENSION);
                                                                    $fileUrl = "bukti/" . $c['bukti_pembayaran'];
                                                                    ?>
                                                                    <?php if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])): ?>
                                                                        <a href="<?= $fileUrl ?>" target="_blank">
                                                                            <img src="<?= $fileUrl ?>" alt="Bukti" style="max-height: 50px;">
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <a href="<?= $fileUrl ?>" target="_blank">Lihat Bukti</a>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    Tidak ada bukti
                                                                <?php endif; ?>
                                                                <hr>
                                                                <div class="btn-group">
                                                                    <button class="btn btn-sm btn-danger" onclick="confirmCancel(<?= $c['id_cicilan_pembelian_bahan'] ?>)">
                                                                        <i class="ti ti-trash"></i>
                                                                    </button>
                                                                </div>
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
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Format input uang
    document.querySelectorAll('.money').forEach(input => {
        input.addEventListener('keyup', function(e) {
            let value = this.value.replace(/[^0-9]/g, '');
            this.value = formatRupiahInput(value);
        });
    });

    function formatRupiahInput(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function confirmCancel(id_cicilan) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Anda akan membatalkan pembayaran ini!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, batalkan!',
            cancelButtonText: 'Tidak'
        }).then((result) => {
            if (result.isConfirmed) {
                // Kirim request pembatalan ke server
                fetch(`cancel_cicilan.php?id=${id_cicilan}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire(
                                'Dibatalkan!',
                                'Pembayaran telah dibatalkan.',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Gagal!',
                                data.message || 'Gagal membatalkan pembayaran.',
                                'error'
                            );
                        }
                    })
                    .catch(error => {
                        Swal.fire(
                            'Error!',
                            'Terjadi kesalahan saat memproses permintaan.',
                            'error'
                        );
                    });
            }
        });
    }
</script>

<script>
    // Format input uang
    document.querySelectorAll('.money').forEach(input => {
        input.addEventListener('keyup', function(e) {
            let value = this.value.replace(/[^0-9]/g, '');
            this.value = formatRupiahInput(value);
            validateAmount(this);
        });
    });

    // Validasi real-time
    function validateAmount(input) {
        const jumlahInput = input;
        const jumlah = parseFloat(jumlahInput.value.replace(/\./g, '')) || 0;
        const sisaHutang = parseFloat(jumlahInput.dataset.sisaHutang) || 0;
        const errorElement = document.getElementById('jumlah-error');

        if (jumlah > sisaHutang) {
            jumlahInput.classList.add('is-invalid');
            if (errorElement) {
                errorElement.textContent = `Jumlah melebihi sisa hutang ${formatRupiahDisplay(sisaHutang)}`;
                errorElement.style.display = 'block';
            }
            return false;
        } else {
            jumlahInput.classList.remove('is-invalid');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
            return true;
        }
    }

    // Validasi sebelum submit form
    document.querySelector('form').addEventListener('submit', function(e) {
        const jumlahInput = document.querySelector('input[name="jumlah"]');
        const jumlah = parseFloat(jumlahInput.value.replace(/\./g, '')) || 0;
        const sisaHutang = parseFloat(jumlahInput.dataset.sisaHutang) || 0;

        // Validasi 1: Jumlah harus lebih dari 0
        if (jumlah <= 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Jumlah Tidak Valid!',
                text: 'Jumlah pembayaran harus lebih dari 0',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return false;
        }

        // Validasi 2: Jumlah tidak boleh melebihi sisa hutang
        if (jumlah > sisaHutang) {
            e.preventDefault();
            Swal.fire({
                title: 'Jumlah Melebihi Sisa Hutang!',
                html: `Jumlah pembayaran (${formatRupiahDisplay(jumlah)}) melebihi sisa hutang (${formatRupiahDisplay(sisaHutang)})`,
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return false;
        }

        return true;
    });

    // Fungsi format untuk display
    function formatRupiahInput(angka) {
        const num = parseInt(angka || 0);
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function formatRupiahDisplay(angka) {
        return 'Rp ' + formatRupiahInput(angka);
    }

    // Fungsi untuk menampilkan form edit
    function showEditForm(id_cicilan_penjualan_bahan) {
        // Ambil data cicilan via AJAX
        fetch(`get_cicilan.php?id=${id_cicilan_penjualan_bahan}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    // Validasi jumlah edit
                    const sisaHutang = <?= $sisa_hutang ?>;
                    const jumlahCicilanLama = parseFloat(data.jumlah_cicilan_penjualan_bahan) || 0;
                    const maxEditAmount = sisaHutang + jumlahCicilanLama;

                    document.getElementById('edit_id_cicilan_penjualan_bahan').value = data.id_cicilan_penjualan_bahan;
                    document.getElementById('edit_jumlah').value = jumlahCicilanLama;
                    document.getElementById('edit_jumlah').setAttribute('data-max-edit', maxEditAmount);
                    document.getElementById('edit_tanggal').value = data.tanggal_bayar;
                    document.getElementById('edit_metode').value = data.metode_pembayaran;

                    // Tampilkan form
                    document.getElementById('editFormContainer').style.display = 'block';

                    // Scroll ke form edit
                    document.getElementById('editFormContainer').scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
    }

    // Validasi form edit
    document.getElementById('editCicilanForm').addEventListener('submit', function(e) {
        const jumlahInput = document.getElementById('edit_jumlah');
        const jumlah = parseFloat(jumlahInput.value.replace(/\./g, '')) || 0;
        const maxAmount = parseFloat(jumlahInput.getAttribute('data-max-edit')) || 0;

        if (jumlah > maxAmount) {
            e.preventDefault();
            Swal.fire({
                title: 'Jumlah Melebihi Batas!',
                text: `Jumlah edit tidak boleh melebihi ${formatRupiahDisplay(maxAmount)}`,
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return false;
        }
    });
</script>



</html>