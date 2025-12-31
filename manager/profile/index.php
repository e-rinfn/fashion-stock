<?php

// owner/profile/index.php
require_once '../../config/database.php';
require_once '../../config/functions.php';
require_once '../includes/header.php';

// Pastikan sudah login dan role owner
// redirectIfNotLoggedIn();
// checkRole('owner');

// Ambil data user yang sedang login
$user_id = $_SESSION['user_id'];
$user = query("SELECT * FROM users WHERE id_user = $user_id")[0];

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $kontak = $conn->real_escape_string($_POST['kontak']);
    $username = $conn->real_escape_string($_POST['username']);

    // Validasi username unik
    $check_username = query("SELECT id_user FROM users WHERE username = '$username' AND id_user != $user_id");
    if ($check_username) {
        $error = "Username sudah digunakan!";
    } else {
        // Update data profil
        $sql = "UPDATE users SET 
                nama_lengkap = '$nama_lengkap',
                kontak = '$kontak',
                username = '$username'
                WHERE id_user = $user_id";

        if ($conn->query($sql)) {
            $_SESSION['success'] = "Profil berhasil diperbarui";
            $_SESSION['nama'] = $nama_lengkap;
            $_SESSION['username'] = $username;
            header("Refresh:0");
            exit();
        } else {
            $error = "Gagal memperbarui profil: " . $conn->error;
        }
    }
}

// Proses update password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // Verifikasi password saat ini
    if (!password_verify($current_pass, $user['password'])) {
        $error_pass = "Password saat ini salah!";
    } elseif ($new_pass !== $confirm_pass) {
        $error_pass = "Password baru tidak cocok!";
    } else {
        // Update password
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = '$hashed_pass' WHERE id_user = $user_id";

        if ($conn->query($sql)) {
            $_SESSION['success_pass'] = "Password berhasil diubah";
            header("Refresh:0");
            exit();
        } else {
            $error_pass = "Gagal mengubah password: " . $conn->error;
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
                    <h2>Profile</h2>
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

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <h5 class="card-header">Detail Profil</h5>
                                <div class="card-body">
                                    <?php if (isset($_SESSION['success'])): ?>
                                        <div class="alert alert-success"><?= $_SESSION['success'];
                                                                            unset($_SESSION['success']); ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($error)): ?>
                                        <div class="alert alert-danger"><?= $error; ?></div>
                                    <?php endif; ?>

                                    <form method="post">
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" name="username"
                                                value="<?= htmlspecialchars($user['username']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Nama Lengkap</label>
                                            <input type="text" class="form-control" name="nama_lengkap"
                                                value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Kontak</label>
                                            <input type="text" class="form-control" name="kontak"
                                                value="<?= htmlspecialchars($user['kontak']) ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control"
                                                value="<?= htmlspecialchars(ucfirst($user['role'])) ?>" disabled>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" name="update_profile" class="btn btn-primary">
                                                <i class="ti ti-file-plus"></i> Simpan Perubahan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <h5 class="card-header">Ubah Password</h5>
                                <div class="card-body">
                                    <?php if (isset($_SESSION['success_pass'])): ?>
                                        <div class="alert alert-success">
                                            <?= $_SESSION['success_pass'];
                                            unset($_SESSION['success_pass']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($error_pass)): ?>
                                        <div class="alert alert-danger"><?= $error_pass; ?></div>
                                    <?php endif; ?>

                                    <form method="post">
                                        <div class="mb-3 position-relative">
                                            <label class="form-label">Password Saat Ini</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="current_password" id="current_password" required>
                                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current_password">
                                                    <i class="ti ti-eye-off"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mb-3 position-relative">
                                            <label class="form-label">Password Baru</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="new_password" id="new_password" required>
                                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password">
                                                    <i class="ti ti-eye-off"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mb-3 position-relative">
                                            <label class="form-label">Konfirmasi Password Baru</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password">
                                                    <i class="ti ti-eye-off"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" name="update_password" class="btn btn-info">
                                                <i class="ti ti-file-plus"></i> Ubah Password
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Tambahkan ini di bawah (pastikan sudah ada Bootstrap Icons di layout kamu) -->
                        <script>
                            document.querySelectorAll('.toggle-password').forEach(button => {
                                button.addEventListener('click', () => {
                                    const targetId = button.getAttribute('data-target');
                                    const input = document.getElementById(targetId);
                                    const icon = button.querySelector('i');

                                    if (input.type === 'password') {
                                        input.type = 'text';
                                        icon.classList.remove('bi-eye');
                                        icon.classList.add('bi-eye-slash');
                                    } else {
                                        input.type = 'password';
                                        icon.classList.remove('bi-eye-slash');
                                        icon.classList.add('bi-eye');
                                    }
                                });
                            });
                        </script>

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
        const deleteButtons = document.querySelectorAll('.btn-hapus');

        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');

                // Cek relasi produk via AJAX
                fetch(`check_produk.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.can_delete) {
                            Swal.fire({
                                title: 'Yakin hapus data produk?',
                                text: "Data yang dihapus tidak bisa dikembalikan!",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#d33',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: 'Ya, hapus!',
                                cancelButtonText: 'Batal'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'delete.php?id=' + id;
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'Tidak Dapat Dihapus',
                                html: data.message,
                                icon: 'error',
                                confirmButtonColor: '#3085d6',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
            });
        });
    });
</script>


</html>