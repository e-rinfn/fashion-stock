<?php
require_once '../includes/header.php';

$sql = "SELECT * FROM pemotong ORDER BY nama_pemotong";
$pemotong = query($sql);
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
                    <h2>Data Pemotong</h2>
                    <div>
                        <!-- <a href="biaya_upah_pemotong.php" class="btn btn-warning">
                            <i class="ti ti-report-money"></i> Upah Pemotong
                        </a> -->
                        <a href="add.php" class="btn btn-success">
                            <i class="ti ti-circle-plus"></i> Tambah Pemotong
                        </a>
                    </div>
                </div>


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
                            <thead class="table-light text-center">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Pemotong</th>
                                    <th>Alamat</th>
                                    <th>Kontak</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pemotong)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Tidak ada data pemotong</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($pemotong as $ptg): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($ptg['nama_pemotong']); ?></td>
                                            <td><?= htmlspecialchars($ptg['alamat']); ?></td>
                                            <td><?= htmlspecialchars($ptg['kontak']); ?></td>
                                            <td class="text-center">
                                                <a href="edit.php?id=<?= $ptg['id_pemotong']; ?>" class="btn btn-sm btn-primary me-1">
                                                    <i class="ti ti-pencil"></i> Edit
                                                </a>
                                                <a href="delete.php?id=<?= $ptg['id_pemotong']; ?>"
                                                    class="btn btn-sm btn-danger btn-delete"
                                                    data-id="<?= $ptg['id_pemotong']; ?>">
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