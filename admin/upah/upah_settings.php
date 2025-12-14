<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';


// Ambil semua tarif upah
$tarif_upah = query("SELECT * FROM tarif_upah ORDER BY berlaku_sejak DESC");

// Tambah tarif baru
if (isset($_POST['tambah_tarif'])) {
    $jenis_tarif = $conn->real_escape_string($_POST['jenis_tarif']);
    $tarif_per_unit = floatval($_POST['tarif_per_unit']);
    $berlaku_sejak = $conn->real_escape_string($_POST['berlaku_sejak']);
    $keterangan = $conn->real_escape_string($_POST['keterangan']);

    $sql = "INSERT INTO tarif_upah (jenis_tarif, tarif_per_unit, berlaku_sejak, keterangan) 
            VALUES ('$jenis_tarif', $tarif_per_unit, '$berlaku_sejak', '$keterangan')";

    if ($conn->query($sql)) {
        $_SESSION['success'] = "Tarif upah berhasil ditambahkan";
        header("Location: upah_settings.php");
        exit();
    } else {
        $error = "Gagal menambahkan tarif upah: " . $conn->error;
    }
}

// Hapus tarif
if (isset($_GET['hapus'])) {
    $id_tarif = intval($_GET['hapus']);

    $sql = "DELETE FROM tarif_upah WHERE id_tarif = $id_tarif";
    if ($conn->query($sql)) {
        $_SESSION['success'] = "Tarif upah berhasil dihapus";
        header("Location: upah_settings.php");
        exit();
    } else {
        $error = "Gagal menghapus tarif upah: " . $conn->error;
    }
}
?>


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
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTambahTarif">
                        <i class="ti ti-circle-plus"></i> Tambah Tarif Baru
                    </button>
                </div>

                <!-- Daftar Tarif -->
                <div class="card p-3">

                    <!-- Tampilkan pesan error atau success -->
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
                    <!-- /Tampilkan pesan error atau success -->

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr class="text-center">
                                    <th style="width: 5%;">No</th>
                                    <th style="width: 20%;">Jenis Tarif</th>
                                    <th style="width: 15%;">Tarif per Unit</th>
                                    <th style="width: 15%;">Berlaku Sejak</th>
                                    <th style="width: 35%;">Keterangan</th>
                                    <th style="width: 10%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                foreach ($tarif_upah as $tarif):
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td>
                                            <span class="badge bg-<?=
                                                                    $tarif['jenis_tarif'] == 'pemotongan' ? 'warning' : ($tarif['jenis_tarif'] == 'finishing' ? 'success' : 'info')
                                                                    ?>">
                                                <?= ucfirst($tarif['jenis_tarif']) ?>
                                            </span>
                                        </td>

                                        <td><?= formatRupiah($tarif['tarif_per_unit']) ?></td>
                                        <td><?= dateIndo($tarif['berlaku_sejak']) ?></td>
                                        <td><?= htmlspecialchars($tarif['keterangan'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <a href="upah_settings.php?hapus=<?= $tarif['id_tarif'] ?>"
                                                class="btn btn-sm btn-danger btn-hapus"
                                                data-id="<?= $tarif['id_tarif'] ?>">
                                                <i class="ti ti-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Tarif -->
    <div class="modal fade" id="modalTambahTarif" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Tarif Upah Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formTambahTarif">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Jenis Tarif <span class="text-danger">*</span></label>
                            <select name="jenis_tarif" class="form-select" required>
                                <option value="">-- Pilih Jenis Tarif --</option>
                                <option value="pemotongan">Pemotong</option>
                                <option value="penjahitan">Penjahit</option>
                                <option value="finishing">Finishing</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tarif per Unit (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="tarif_per_unit" class="form-control"
                                min="0" step="0.01" placeholder="Masukkan tarif per unit" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Berlaku Sejak <span class="text-danger">*</span></label>
                            <input type="date" name="berlaku_sejak" class="form-control"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"
                                placeholder="Masukkan keterangan (opsional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_tarif" class="btn btn-primary">Simpan Tarif</button>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const deleteButtons = document.querySelectorAll('.btn-hapus');

        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.getAttribute('href');

                Swal.fire({
                    title: 'Yakin ingin menghapus?',
                    text: 'Data tarif ini akan dihapus permanen!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
        });
    });
</script>

</html>