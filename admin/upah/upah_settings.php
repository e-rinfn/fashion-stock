<?php
require_once '../includes/header.php';

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
                </div>

                <!-- Filter Form -->
                <form method="POST">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Jenis Tarif</label>
                            <select name="jenis_tarif" class="form-select" required>
                                <option value="pemotongan">Pemotong</option>
                                <option value="penjahitan">Penjahit</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tarif per Unit (Rp)</label>
                            <input type="number" name="tarif_per_unit" class="form-control"
                                min="0" step="0.01" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Berlaku Sejak</label>
                            <input type="date" name="berlaku_sejak" class="form-control"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Keterangan</label>
                            <input type="text" name="keterangan" class="form-control">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" name="tambah_tarif" class="btn btn-primary">
                            <i class="ti ti-circle-plus"></i> Tambah Tarif
                        </button>
                    </div>
                </form>

                <!-- Daftar Tarif -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title">Daftar Tarif Upah</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Jenis Tarif</th>
                                        <th>Tarif per Unit</th>
                                        <th>Berlaku Sejak</th>
                                        <th>Keterangan</th>
                                        <th>Dibuat Pada</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    foreach ($tarif_upah as $tarif):
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $tarif['jenis_tarif'] == 'pemotongan' ? 'warning' : 'info' ?>">
                                                    <?= ucfirst($tarif['jenis_tarif']) ?>
                                                </span>
                                            </td>
                                            <td><?= formatRupiah($tarif['tarif_per_unit']) ?></td>
                                            <td><?= dateIndo($tarif['berlaku_sejak']) ?></td>
                                            <td><?= htmlspecialchars($tarif['keterangan'] ?? '-') ?></td>
                                            <td><?= dateIndo($tarif['created_at']) ?></td>
                                            <td class="text-center">
                                                <a href="upah_settings.php?hapus=<?= $tarif['id_tarif'] ?>"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Yakin ingin menghapus tarif ini?')">
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
    </div>

    <!-- Modal Input/Edit Penjahitan -->
    <div class="modal fade" id="modalPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Input Data Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formPenjahitan">
                    <div class="modal-body">
                        <?php if (isset($error_modal)): ?>
                            <div class="alert alert-danger"><?= $error_modal ?></div>
                        <?php endif; ?>

                        <input type="hidden" name="id_hasil_potong_fix" id="modal_id_hasil_potong">
                        <input type="hidden" id="modal_tanggal_potong">

                        <div class="mb-3">
                            <label class="form-label">Produk</label>
                            <input type="text" class="form-control" id="modal_produk" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Seri</label>
                            <input type="text" class="form-control" id="modal_seri" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Potong</label>
                            <input type="text" class="form-control" id="modal_total_potong" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Penjahit <span class="text-danger">*</span></label>
                            <select name="id_penjahit" class="form-control" id="modal_penjahit" required>
                                <option value="">-- Pilih Penjahit --</option>
                                <?php foreach ($penjahit as $j): ?>
                                    <option value="<?= $j['id_penjahit'] ?>">
                                        <?= htmlspecialchars($j['nama_penjahit']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Hasil Jahit <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_hasil_jahit" class="form-control"
                                id="modal_tanggal_jahit" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Jahit (Pcs) <span class="text-danger">*</span></label>
                            <input type="number" name="total_hasil_jahit" class="form-control"
                                min="1" max="" id="input_total_jahit" required>
                            <small class="text-muted">Maksimal: <span id="max_total_jahit">0</span> Pcs</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Perkiraan Upah Penjahit</label>
                            <input type="text" class="form-control" id="modal_perkiraan_upah" readonly>
                            <small class="text-muted" id="modal_rate_upah"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="simpan_penjahitan" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Batal Penjahitan -->
    <div class="modal fade" id="modalBatalPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Batal Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formBatalPenjahitan">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="batal_modal_id">
                        <p>Apakah Anda yakin ingin membatalkan data penjahitan untuk:</p>
                        <p><strong>Produk:</strong> <span id="batal_modal_produk"></span></p>
                        <p><strong>Seri:</strong> <span id="batal_modal_seri"></span></p>
                        <p class="text-danger"><strong>Data penjahitan akan dihapus dan status akan dikembalikan ke "Diproses".</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="batal_penjahitan" class="btn btn-danger">Ya, Batalkan</button>
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
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.btn-delete');

        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                const url = this.getAttribute('href');

                // Check if pemotong can be deleted
                fetch(`check_delete.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.can_delete) {
                            Swal.fire({
                                title: 'Yakin hapus data pemotong?',
                                text: "Data yang dihapus tidak bisa dikembalikan!",
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
                        } else {
                            Swal.fire({
                                title: 'Tidak Dapat Dihapus',
                                text: data.message,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: 'Gagal memverifikasi data',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
            });
        });
    });
</script>


</html>