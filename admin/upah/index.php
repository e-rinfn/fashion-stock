<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: {$base_url}auth/login.php");
    exit;
}

function dateIndo($tanggal)
{
    $bulanIndo = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $tanggal = date('Y-m-d', strtotime($tanggal));
    $pecah = explode('-', $tanggal);
    return $pecah[2] . ' ' . $bulanIndo[(int)$pecah[1]] . ' ' . $pecah[0];
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_tarif = $_POST['id_tarif'] ?? '';
        $jenis_tarif = $conn->real_escape_string($_POST['jenis_tarif']);
        $tarif_per_unit = str_replace('.', '', $conn->real_escape_string($_POST['tarif_per_unit']));
        $berlaku_sejak = $conn->real_escape_string($_POST['berlaku_sejak']);
        $keterangan = $conn->real_escape_string($_POST['keterangan'] ?? '');

        // Validate input
        if (empty($jenis_tarif) || empty($tarif_per_unit) || empty($berlaku_sejak)) {
            throw new Exception("Semua field wajib diisi!");
        }

        if (!is_numeric($tarif_per_unit)) {
            throw new Exception("Tarif harus berupa angka!");
        }

        if ($id_tarif) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE tarif_upah SET jenis_tarif=?, tarif_per_unit=?, berlaku_sejak=?, keterangan=? WHERE id_tarif=?");
            $stmt->bind_param("sdssi", $jenis_tarif, $tarif_per_unit, $berlaku_sejak, $keterangan, $id_tarif);
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO tarif_upah (jenis_tarif, tarif_per_unit, berlaku_sejak, keterangan) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdss", $jenis_tarif, $tarif_per_unit, $berlaku_sejak, $keterangan);
        }

        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan data: " . $stmt->error);
        }

        $_SESSION['success'] = "Data tarif berhasil " . ($id_tarif ? "diperbarui" : "ditambahkan") . ".";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    try {
        $id = intval($_GET['delete']);

        // Check if the wage rate is being used in pemotongan
        $check_pemotongan = $conn->query("SELECT COUNT(*) FROM hasil_pemotongan WHERE id_tarif = $id");
        $count_pemotongan = $check_pemotongan->fetch_row()[0];

        // Check if the wage rate is being used in penjahitan
        $check_penjahitan = $conn->query("SELECT COUNT(*) FROM hasil_penjahitan WHERE id_tarif = $id");
        $count_penjahitan = $check_penjahitan->fetch_row()[0];

        $total_usage = $count_pemotongan + $count_penjahitan;

        if ($total_usage > 0) {
            $error_message = "Tarif tidak dapat dihapus karena sudah digunakan dalam";

            if ($count_pemotongan > 0) {
                $error_message .= "\n Proses pemotongan";
            }
            if ($count_penjahitan > 0) {
                $error_message .= "\n Proses penjahitan";
            }

            throw new Exception($error_message);
        }

        $stmt = $conn->prepare("DELETE FROM tarif_upah WHERE id_tarif = ?");
        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            throw new Exception("Gagal menghapus data: " . $stmt->error);
        }

        $_SESSION['success'] = "Data tarif berhasil dihapus.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get all wage rates
$result = $conn->query("SELECT * FROM tarif_upah ORDER BY berlaku_sejak DESC");
?>


<style>
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
                    <h2>Data Tarif Upah</h2>
                    <div>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tarifModal">
                            <i class="ti ti-circle-plus"></i>Tambah Tarif Upah
                        </button>
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

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th width="50">No</th>
                                    <th>Jenis</th>
                                    <th>Tarif</th>
                                    <th>Berlaku Sejak</th>
                                    <th>Keterangan</th>
                                    <th width="120">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php $no = 1;
                                    while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td>
                                                <?php if ($row['jenis_tarif'] === 'pemotongan'): ?>
                                                    <span class="badge bg-primary">
                                                        <?= ucfirst($row['jenis_tarif']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        <?= ucfirst($row['jenis_tarif']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <td>Rp <?= number_format($row['tarif_per_unit'], 0, ',', '.') ?></td>
                                            <td><?= dateIndo($row['berlaku_sejak']) ?></td>
                                            <td><?= $row['keterangan'] ? htmlspecialchars($row['keterangan']) : '-' ?></td>
                                            <td class="text-center d-flex justify-content-center">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-primary me-1 d-inline-flex align-items-center"
                                                    onclick="editData(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)">
                                                    <i class="ti ti-pencil"></i> Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-danger d-inline-flex align-items-center"
                                                    onclick="confirmDelete(<?= $row['id_tarif'] ?>)">
                                                    <i class="ti ti-trash"></i> Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">Tidak ada data tarif upah</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarif Modal -->
    <div class="modal fade" id="tarifModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="tarifForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Tambah Tarif Upah</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_tarif" id="id_tarif">
                        <div class="mb-3">
                            <label class="form-label">Jenis Tarif <span class="text-danger">*</span></label>
                            <select name="jenis_tarif" id="jenis_tarif" class="form-select" required>
                                <option value="">Pilih Jenis Tarif</option>
                                <option value="pemotongan">Pemotongan</option>
                                <option value="penjahitan">Penjahitan</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tarif per Unit (Rp) <span class="text-danger">*</span></label>
                            <input type="text" name="tarif_per_unit" id="tarif_per_unit" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Berlaku Sejak <span class="text-danger">*</span></label>
                            <input type="date" name="berlaku_sejak" id="berlaku_sejak" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" id="keterangan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
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
    // Format currency input
    document.getElementById('tarif_per_unit').addEventListener('input', function(e) {
        this.value = formatRupiah(this.value);
    });

    // Format as Rupiah
    function formatRupiah(angka) {
        let number_string = angka.replace(/[^,\d]/g, '').toString();
        let split = number_string.split(',');
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        return rupiah === '' ? '' : rupiah;
    }

    // Edit data function
    function editData(data) {
        document.getElementById('modalTitle').textContent = 'Edit Tarif Upah';
        document.getElementById('id_tarif').value = data.id_tarif;
        document.getElementById('jenis_tarif').value = data.jenis_tarif;
        document.getElementById('tarif_per_unit').value = data.tarif_per_unit;
        document.getElementById('berlaku_sejak').value = data.berlaku_sejak;
        document.getElementById('keterangan').value = data.keterangan;

        var modal = new bootstrap.Modal(document.getElementById('tarifModal'));
        modal.show();
    }

    // Confirm delete
    function confirmDelete(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete=' + id;
            }
        });
    }

    // Reset modal when closed
    document.getElementById('tarifModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('tarifForm').reset();
        document.getElementById('modalTitle').textContent = 'Tambah Tarif Upah';
        document.getElementById('id_tarif').value = '';
    });

    // Form validation
    document.getElementById('tarifForm').addEventListener('submit', function(e) {
        let tarif = document.getElementById('tarif_per_unit').value;
        tarif = tarif.replace(/\./g, '');

        if (!/^\d+$/.test(tarif)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Format tarif tidak valid. Harap masukkan angka yang benar.',
                confirmButtonColor: '#3085d6'
            });
            return false;
        }

        document.getElementById('tarif_per_unit').value = tarif;
        return true;
    });
</script>


</html>