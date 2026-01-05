<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: {$base_url}auth/login.php");
    exit;
}
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
                    <h2>Data Supplier</h2>
                    <div>
                        <a href="<?= $base_url ?>/admin/master_data.php" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> Kembali
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
                                    <th style="width: 5%;">No</th>
                                    <th style="width: 30%;">Nama Supplier</th>
                                    <th style="width: 20%;">Kontak</th>
                                    <th style="width: 35%;">Tanggal Bergabung</th>
                                    <!-- <th style="width: 10%;">Aksi</th> -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT * FROM supplier ORDER BY nama_supplier";
                                $supplier = query($sql);
                                $no = 1;

                                if (empty($supplier)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Tidak ada data supplier</td>
                                    </tr>
                                    <?php else:
                                    foreach ($supplier as $row): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td><?= htmlspecialchars($row['nama_supplier']) ?></td>
                                            <td><?= htmlspecialchars($row['kontak']) ?></td>
                                            <td class="text-end"><?= dateIndo($row['tanggal_bergabung']) ?></td>
                                            <!-- <td class="text-center">
                                                <a href="edit.php?id=<?= $row['id_supplier'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="ti ti-pencil"></i>
                                                </a>
                                                <a href="delete.php?id=<?= $row['id_supplier'] ?>"
                                                    class="btn btn-sm btn-danger btn-delete"
                                                    data-id="<?= $row['id_supplier'] ?>">
                                                    <i class="ti ti-trash"></i>
                                                </a>
                                            </td> -->
                                        </tr>
                                <?php endforeach;
                                endif; ?>
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
        const deleteButtons = document.querySelectorAll('.btn-delete');

        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                const url = this.getAttribute('href');

                // Check if supplier can be deleted
                fetch(`check_delete.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.can_delete) {
                            Swal.fire({
                                title: 'Yakin hapus supplier?',
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
                            text: 'Terjadi kesalahan saat memeriksa data',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
            });
        });
    });
</script>


</html>