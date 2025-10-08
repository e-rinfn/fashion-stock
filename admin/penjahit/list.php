<?php
require_once '../includes/header.php';

$sql = "SELECT * FROM penjahit ORDER BY nama_penjahit";
$penjahit = query($sql);
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
                    <h2>Data Penjahit</h2>
                    <div>
                        <!-- <a href="biaya_upah_penjahit.php" class="btn btn-warning">
                            <i class="ti ti-report-money"></i> Upah Penjahit
                        </a> -->
                        <a href="add.php" class="btn btn-success">
                            <i class="ti ti-file-plus"></i> Tambah Penjahit
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

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover">
                            <thead class="table-light text-center">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Penjahit</th>
                                    <th>Kontak</th>
                                    <th>Alamat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($penjahit)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Tidak ada data penjahit</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1;
                                    foreach ($penjahit as $pj): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($pj['nama_penjahit']) ?></td>
                                            <td><?= htmlspecialchars($pj['kontak']) ?></td>
                                            <td><?= htmlspecialchars($pj['alamat']) ?></td>
                                            <td class="text-center">
                                                <a href="edit.php?id=<?= $pj['id_penjahit'] ?>" class="btn btn-sm btn-primary me-1">
                                                    <i class="ti ti-pencil"></i> Edit
                                                </a>
                                                <a href="javascript:void(0);"
                                                    class="btn btn-sm btn-danger btn-hapus"
                                                    data-id="<?= $pj['id_penjahit'] ?>">
                                                    <i class="ti ti-trash"></i> Hapus
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
        const hapusButtons = document.querySelectorAll('.btn-hapus');

        hapusButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const url = `delete.php?id=${id}`;

                Swal.fire({
                    title: 'Konfirmasi Hapus',
                    text: "Apakah Anda yakin ingin menghapus data penjahit ini?",
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
                                    throw new Error(response.statusText);
                                }
                                return response.json();
                            })
                            .catch(error => {
                                Swal.showValidationMessage(
                                    `Request failed: ${error}`
                                );
                            });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (result.value.can_delete) {
                            window.location.href = url;
                        } else {
                            Swal.fire({
                                title: 'Tidak Dapat Dihapus',
                                text: result.value.message,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    }
                });
            });
        });
    });
</script>


</html>