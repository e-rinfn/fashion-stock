<?php

$page_title = "TAMBAH PENGGUNA BARU";

require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama = $conn->real_escape_string($_POST['nama']);
    $role = $conn->real_escape_string($_POST['role']);
    $kontak = $conn->real_escape_string($_POST['kontak']);

    // Cek username sudah ada
    $check = query("SELECT id_user FROM users WHERE username = '$username'");
    if ($check) {
        $error = "Username sudah digunakan!";
    } else {
        $sql = "INSERT INTO users (username, password, nama_lengkap, role, kontak) 
                VALUES ('$username', '$password', '$nama', '$role', '$kontak')";

        if ($conn->query($sql)) {
            $_SESSION['success'] = "User berhasil ditambahkan";
            header("Location: index.php");
            exit();
        } else {
            $error = "Gagal menambahkan user: " . $conn->error;
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

                <div class="card p-3">
                    <!-- Tampilkan pesan error atau success -->
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['success']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    <!-- /Tampilkan pesan error atau success -->

                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required
                                placeholder="Masukkan username" autocomplete="off">
                            <small class="form-text text-muted">Username harus unik dan tidak boleh ada spasi</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required
                                placeholder="Masukkan password" autocomplete="new-password">
                            <small class="form-text text-muted">Minimal 6 karakter</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control" required
                                placeholder="Masukkan nama lengkap">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="owner">Owner</option>
                            </select>
                            <small class="form-text text-muted">
                                <strong>Admin:</strong> Akses penuh semua fitur<br>
                                <strong>Manager:</strong> Akses manajemen operasional<br>
                                <strong>Owner:</strong> Akses laporan dan monitoring
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kontak</label>
                            <input type="text" name="kontak" class="form-control"
                                placeholder="No. HP atau WhatsApp">
                            <small class="form-text text-muted">Contoh: 081234567890</small>
                        </div>

                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-file-plus"></i> Simpan
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="ti ti-arrow-back"></i> Kembali
                                </a>
                            </div>
                            <small class="text-muted align-self-center">
                                <span class="text-danger">*</span> Wajib diisi
                            </small>
                        </div>
                    </form>
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
        // Validasi form sebelum submit
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const username = document.querySelector('input[name="username"]').value.trim();
            const password = document.querySelector('input[name="password"]').value;
            const nama = document.querySelector('input[name="nama"]').value.trim();
            const role = document.querySelector('select[name="role"]').value;

            let errors = [];

            if (!username) {
                errors.push('Username harus diisi');
            }

            if (password.length < 6) {
                errors.push('Password minimal 6 karakter');
            }

            if (!nama) {
                errors.push('Nama lengkap harus diisi');
            }

            if (!role) {
                errors.push('Role harus dipilih');
            }

            if (errors.length > 0) {
                e.preventDefault();
                Swal.fire({
                    title: 'Validasi Gagal',
                    html: errors.join('<br>'),
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    // SweetAlert untuk konfirmasi delete
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const userId = this.getAttribute('data-id');

            Swal.fire({
                title: 'Yakin hapus user?',
                text: "Data yang dihapus tidak bisa dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = this.getAttribute('href');
                }
            });
        });
    });
</script>

</html>