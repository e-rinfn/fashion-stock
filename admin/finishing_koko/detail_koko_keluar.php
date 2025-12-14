<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

$id_koko_keluar = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Query sederhana untuk cek data
$sql = "SELECT kk.*, pf.nama_petugas 
        FROM koko_keluar kk
        LEFT JOIN petugas_finishing pf ON kk.id_petugas_finishing = pf.id_petugas_finishing
        WHERE kk.id_koko_keluar = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_koko_keluar);
$stmt->execute();
$result = $stmt->get_result();
$koko_keluar = $result->fetch_assoc();
$stmt->close();

if (!$koko_keluar) {
    header("Location: list_koko_keluar.php");
    exit();
}

// Ambil detail koko - coba kedua tabel
$detail = [];
$sql_detail1 = "SELECT d.*, k.nama_koko 
                FROM detail_koko_keluar d
                JOIN koko k ON d.id_koko = k.id_koko
                WHERE d.id_koko_keluar = ?";

$stmt = $conn->prepare($sql_detail1);
$stmt->bind_param("i", $id_koko_keluar);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $detail[] = $row;
    }
} else {
    // Coba tabel detail_penjualan
    $sql_detail2 = "SELECT d.*, k.nama_koko 
                    FROM detail_penjualan d
                    JOIN koko k ON d.id_koko = k.id_koko
                    WHERE d.id_penjualan = ?";

    $stmt = $conn->prepare($sql_detail2);
    $stmt->bind_param("i", $id_koko_keluar);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $detail[] = $row;
    }
}
$stmt->close();
?>

<?php
// Script debugging - comment out setelah masalah teratasi
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- Debug Info -->";
echo "<!-- id_koko_keluar: " . $id_koko_keluar . " -->";
echo "<!-- Query koko_keluar: SELECT p.*, r.nama_petugas FROM koko_keluar p JOIN petugas_finishing r ON p.id_petugas_finishing = r.id_petugas_finishing WHERE p.id_koko_keluar = $id_koko_keluar -->";

// Cek semua tabel
$tables = $conn->query("SHOW TABLES");
echo "<!-- Tables: ";
while ($table = $tables->fetch_array()) {
    echo $table[0] . ", ";
}
echo " -->";
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
                    <h2>DATA KOKO KELUAR</h2>
                    <div>
                        <a href="list_koko_keluar.php" class="btn btn-secondary">
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
                                                    <th width="30%">Petugas Finishing</th>
                                                    <td><?= $koko_keluar['nama_petugas'] ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <td><?= dateIndo($koko_keluar['tanggal_koko_keluar']) . ' ' . date('H:i', strtotime($koko_keluar['tanggal_koko_keluar'])) ?></td>
                                                </tr>

                                            </table>
                                        </div>
                                        <div class="col-md-8">
                                            <table class="table table-bordered">
                                                <tr>
                                                    <th width="30%">Total Harga</th>
                                                    <td><?= formatRupiah($koko_keluar['total_harga']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Status</th>
                                                    <td>
                                                        <?= ucfirst($koko_keluar['status']) ?>
                                                        <?php if ($koko_keluar['status'] == 'kirim'): ?> <br>
                                                            Dibayar: <?= formatRupiah($total_cicilan_info) ?> dari <?= formatRupiah($koko_keluar['total_harga']) ?>
                                                            <?php
                                                            $sisa_hutang = $koko_keluar['total_harga'] - $total_cicilan_info;
                                                            ?>
                                                            <br>
                                                            Sisa Hutang: <?= formatRupiah($sisa_hutang) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <!-- <tr>
                                                <th>Metode Pembayaran</th>
                                                <td><?= ucfirst($koko_keluar['metode_pembayaran']) ?></td>
                                            </tr> -->
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between">
                                        <h3>Daftar Produk</h3>
                                        <a href="nota.php?id=<?= $id_koko_keluar ?>" class="btn btn-danger" target="_blank">
                                            <i class="bx bx-printer"></i> Cetak Nota
                                        </a>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <!-- Ganti bagian ini di HTML: -->
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Produk</th>
                                                <th>Harga Satuan</th>
                                                <th>Qty</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($detail)): ?>
                                                <?php foreach ($detail as $i => $d): ?>
                                                    <tr>
                                                        <td><?= $i + 1 ?></td>
                                                        <td><?= htmlspecialchars($d['nama_koko'] ?? $d['nama_produk'] ?? 'Tidak diketahui') ?></td>
                                                        <td><?= formatRupiah($d['harga_satuan']) ?></td>
                                                        <td><?= $d['jumlah'] ?></td>
                                                        <td><?= formatRupiah($d['subtotal']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">Tidak ada detail koko</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="4" class="text-right">Total</th>
                                                <th class="fs-6 text-center"><?= formatRupiah($koko_keluar['total_harga']) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
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

    function confirmCancel(id_cicilan) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Anda akan menghapus pembayaran ini!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Tampilkan loading
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Kirim request penghapusan ke server
                fetch(`cancel_cicilan.php?id=${id_cicilan}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            Swal.fire(
                                'Dihapus!',
                                'Pembayaran telah dihapus.',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Gagal!',
                                data.message || 'Gagal menghapus pembayaran.',
                                'error'
                            );
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        Swal.fire(
                            'Error!',
                            'Terjadi kesalahan saat memproses permintaan: ' + error.message,
                            'error'
                        );
                    });
            }
        });
    }

    function formatRupiahInput(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Fungsi untuk menampilkan form edit
    function showEditForm(id_cicilan) {
        // Ambil data cicilan via AJAX
        fetch(`get_cicilan.php?id=${id_cicilan}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    document.getElementById('edit_id_cicilan').value = data.id_cicilan;
                    // document.getElementById('edit_jumlah').value = data.jumlah_cicilan;
                    document.getElementById('edit_jumlah').value = parseFloat(data.jumlah_cicilan).toString();
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

    // Fungsi untuk menyembunyikan form edit
    function hideEditForm() {
        document.getElementById('editFormContainer').style.display = 'none';
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