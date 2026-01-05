<?php
require_once '../includes/header.php';

// Cek apakah user sudah login
if (!isLoggedIn()) {
    header("Location: {$base_url}auth/login.php");
    exit;
}

if ($_SESSION['role'] !== 'manager') {
    header("Location: {$base_url}/auth/role_tidak_cocok.php");
    exit();
}

// Query data bahan baku
$sql = "SELECT * FROM bahan_baku ORDER BY nama_bahan";
$bahan_baku = query($sql);

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
                    <h2>Data Bahan Baku</h2>
                    <div>
                        <a href="<?= $base_url ?>/manager/master_data.php" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> Kembali
                        </a>
                        <a href="add.php" class="btn btn-success">
                            <i class="ti ti-circle-plus"></i> Tambah Bahan Baku
                        </a>
                    </div>
                </div>


                <div class="card p-3">

                    <!-- Tampilkan pesan error atau success -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    <!-- /Tampilkan pesan error atau success -->

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 5%;">No</th>
                                    <th style="width: 30%;">Nama Bahan</th>
                                    <th colspan="2" style="width: 20%;">Stok</th>
                                    <th style="width: 15%;">Total (Meter)</th>
                                    <th style="width: 20%;">Harga Per Meter</th>
                                    <th style="width: 10%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bahan_baku)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Tidak ada data bahan baku</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($bahan_baku as $bahan): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($bahan['nama_bahan']); ?></td>
                                            <td class="text-end"><?= number_format($bahan['jumlah_stok']); ?></td>
                                            <td><?= htmlspecialchars($bahan['satuan']); ?></td>
                                            <td class="text-end"><?= number_format($bahan['jumlah_meter']); ?></td>
                                            <td class="text-end"><?= formatRupiah($bahan['harga_per_satuan']); ?></td>
                                            <td class="text-center">
                                                <a href="edit.php?id=<?= $bahan['id_bahan']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="ti ti-pencil"></i>
                                                </a>
                                                <a href="delete.php?id=<?= $bahan['id_bahan']; ?>"
                                                    class="btn btn-danger btn-sm btn-delete"
                                                    data-id="<?= $bahan['id_bahan']; ?>">
                                                    <i class="ti ti-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
    document.addEventListener('DOMContentLoaded', function() {
        // SweetAlert for delete confirmation
        $(document).on('click', '.btn-delete', function(e) {
            e.preventDefault();
            const url = $(this).attr('href');
            const id = $(this).data('id');

            Swal.fire({
                title: 'Konfirmasi Penghapusan',
                text: "Apakah Anda yakin ingin menghapus bahan ini?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`check_delete.php?id=${id}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .catch(error => {
                            Swal.showValidationMessage(
                                `Request failed: ${error}`
                            );
                            return null;
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    if (result.value.can_delete) {
                        // Proceed with deletion
                        window.location.href = url;
                    } else {
                        Swal.fire({
                            title: 'Tidak Bisa Dihapus',
                            text: result.value.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                } else if (result.isConfirmed && !result.value) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Gagal memeriksa ketergantungan bahan',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
    });
</script>


</html>