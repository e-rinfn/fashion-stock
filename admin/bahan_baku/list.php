<?php
require_once '../includes/header.php';

// Cek apakah user sudah login
if (!isLoggedIn()) {
    header("Location: {$base_url}auth/login.php");
    exit;
}

// Query data bahan baku
$sql = "SELECT * FROM bahan_baku ORDER BY nama_bahan";
$bahan_baku = query($sql);

// Proses tambah stok jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_stok'])) {
    $id_bahan = intval($_POST['id_bahan']);
    $jumlah = floatval($_POST['jumlah']);

    // Validasi input
    if ($id_bahan > 0 && $jumlah > 0) {
        // Update stok di database
        $sql_update = "UPDATE bahan_baku SET jumlah_stok = jumlah_stok + $jumlah WHERE id_bahan = $id_bahan";
        if ($conn->query($sql_update)) {
            $_SESSION['success'] = "Stok berhasil ditambahkan";
            header("Location: list.php");
            exit();
        } else {
            $error = "Gagal menambahkan stok: " . $conn->error;
        }
    } else {
        $error = "Jumlah tidak valid!";
    }
}

// Query data bahan baku
$sql = "SELECT * FROM bahan_baku ORDER BY nama_bahan";
$bahan_baku = query($sql);

// Proses tambah stok jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah_stok'])) {
        $id_bahan = intval($_POST['id_bahan']);
        $jumlah = floatval($_POST['jumlah']);

        // Validasi input
        if ($id_bahan > 0 && $jumlah > 0) {
            // Update stok di database
            $sql_update = "UPDATE bahan_baku SET jumlah_stok = jumlah_stok + $jumlah WHERE id_bahan = $id_bahan";
            if ($conn->query($sql_update)) {
                $_SESSION['success'] = "Stok berhasil ditambahkan";
                header("Location: list.php");
                exit();
            } else {
                $error = "Gagal menambahkan stok: " . $conn->error;
            }
        } else {
            $error = "Jumlah tidak valid!";
        }
    }

    // Proses adjust stok (tambah/kurang)
    if (isset($_POST['adjust_stok'])) {
        $id_bahan = intval($_POST['id_bahan']);
        $jumlah = floatval($_POST['adjust_jumlah']);
        $tipe = $_POST['adjust_tipe']; // 'tambah' atau 'kurang'

        // Validasi input
        if ($id_bahan > 0 && $jumlah > 0) {
            // Update stok berdasarkan tipe
            if ($tipe == 'kurang') {
                // Cek stok cukup
                $current_stock = query("SELECT jumlah_stok FROM bahan_baku WHERE id_bahan = $id_bahan")[0]['jumlah_stok'];
                if ($current_stock < $jumlah) {
                    $error = "Stok tidak cukup! Stok tersedia: $current_stock";
                } else {
                    $sql_update = "UPDATE bahan_baku SET jumlah_stok = jumlah_stok - $jumlah WHERE id_bahan = $id_bahan";
                }
            } else {
                $sql_update = "UPDATE bahan_baku SET jumlah_stok = jumlah_stok + $jumlah WHERE id_bahan = $id_bahan";
            }

            if (isset($sql_update) && $conn->query($sql_update)) {
                $_SESSION['success'] = "Stok berhasil disesuaikan";
                header("Location: list.php");
                exit();
            } elseif (!isset($sql_update)) {
                $error = $error ?? "Gagal menyesuaikan stok";
            } else {
                $error = "Gagal menyesuaikan stok: " . $conn->error;
            }
        } else {
            $error = "Jumlah tidak valid!";
        }
    }
}
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
                                    <th>No</th>
                                    <th>Nama Bahan</th>
                                    <th>Stok</th>
                                    <th>Satuan</th>
                                    <th>Harga/Satuan</th>
                                    <th>Aksi</th>
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
                                            <td class="text-center"><?= htmlspecialchars($bahan['satuan']); ?></td>
                                            <td class="text-end"><?= formatRupiah($bahan['harga_per_satuan']); ?></td>
                                            <td class="text-center">
                                                <a href="edit.php?id=<?= $bahan['id_bahan']; ?>" class="btn btn-primary btn-sm me-1 mb-1">
                                                    <i class="ti ti-pencil"></i> Edit
                                                </a>
                                                <a href="delete.php?id=<?= $bahan['id_bahan']; ?>"
                                                    class="btn btn-danger btn-sm me-1 mb-1 btn-delete"
                                                    data-id="<?= $bahan['id_bahan']; ?>">
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