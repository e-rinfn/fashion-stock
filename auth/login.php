<?php
// Pastikan session_start() dipanggil di paling atas
session_start();

require_once '../config/database.php';
require_once '../config/functions.php';
include_once '../config/config.php';

// Inisialisasi variabel error
$error = '';

// Debug: Tampilkan semua error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cek jika form login disubmit
if (isset($_POST['login'])) {
  $username = $conn->real_escape_string($_POST['username']);
  $password = $_POST['password'];

  // Debug: Lihat input yang diterima
  error_log("Login attempt - Username: $username, Password: $password");

  $sql = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
  $result = $conn->query($sql);

  if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // Debug: Lihat data user dan hash password dari database
    error_log("User found: " . print_r($user, true));
    error_log("Stored hash: " . $user['password']);
    error_log("Input password: " . $password);

    // Verifikasi password
    if (password_verify($password, $user['password'])) {
      // Set session
      $_SESSION['user_id'] = $user['id_user'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = $user['role'];
      $_SESSION['nama'] = $user['nama_lengkap'];

      // Debug: Session values
      error_log("Session set: " . print_r($_SESSION, true));

      // Redirect berdasarkan role
      if ($user['role'] == 'admin') {
        header("Location: ../admin/index.php");
      } elseif ($user['role'] == 'owner') {
        header("Location: ../owner/index.php");
      } elseif ($user['role'] == 'manager') {
        header("Location: ../manager/index.php");
      } else {
        header("Location: ../user/index.php");
      }
      exit();
    } else {
      $error = "Password yang Anda masukkan salah!";
      error_log("Password verification failed");
    }
  } else {
    $error = "Username tidak ditemukan!";
    error_log("User not found");
  }

  // Jika ada error, simpan di session untuk ditampilkan setelah redirect
  if (!empty($error)) {
    $_SESSION['login_error'] = $error;
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<!-- [Head] start -->

<head>
  <title>Ipenk Legend</title>
  <!-- [Meta] -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="Mantis is made using Bootstrap 5 design framework. Download the free admin template & use it for your project.">
  <meta name="keywords" content="Mantis, Dashboard UI Kit, Bootstrap 5, Admin Template, Admin Dashboard, CRM, CMS, Bootstrap Admin Template">
  <meta name="author" content="CodedThemes">

  <!-- [Favicon] icon -->
  <link rel="icon" type="image/x-icon" href="<?= $base_url ?>/assets/img/Logo-Ipenk.png" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" id="main-font-link">
  <!-- [Tabler Icons] https://tablericons.com -->
  <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css">
  <!-- [Feather Icons] https://feathericons.com -->
  <link rel="stylesheet" href="../assets/fonts/feather.css">
  <!-- [Font Awesome Icons] https://fontawesome.com/icons -->
  <link rel="stylesheet" href="../assets/fonts/fontawesome.css">
  <!-- [Material Icons] https://fonts.google.com/icons -->
  <link rel="stylesheet" href="../assets/fonts/material.css">
  <!-- [Template CSS Files] -->
  <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link">
  <link rel="stylesheet" href="../assets/css/style-preset.css">

</head>
<!-- [Head] end -->
<!-- [Body] Start -->

<body>

  <div class="auth-main">
    <div class="auth-wrapper">
      <div class="auth-form">
        <div class="card my-5">
          <div class="card-body">
            <img src="<?= $base_url ?>/assets/img/Logo-Ipenk.png" width="100px" alt="Ipenk Logo" class="mx-auto d-block mb-3">
            <div class="text-center">
              <div class="app-brand-link d-block">
                <span class="text-body fw-bolder fs-3">Ipenk Legend <br> INVENTORY STOCK</span>
              </div>
            </div>
            <hr>
            <!-- /Logo -->
            <h4 class="mb-2">Login Ipenk Legend!</h4>
            <p class="mb-4">Masukan username dan password yang valid</p>
            <!-- <?php if (isset($error)): ?>
              <div class="alert"><?= $error ?></div>
            <?php endif; ?> -->

            <!-- Tambahkan ini di bagian atas form untuk menampilkan error -->
            <?php
            // Tampilkan error dari session jika ada
            if (isset($_SESSION['login_error'])) {
              echo '<div class="alert alert-danger alert-dismissible" role="alert">';
              echo htmlspecialchars($_SESSION['login_error']);
              echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
              echo '</div>';
              unset($_SESSION['login_error']); // Hapus setelah ditampilkan
            }
            ?>

            <form class="mb-3" method="POST">
              <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                  placeholder="Masukkan username" autofocus required>
              </div>

              <div class="mb-3 form-password-toggle">
                <div class="d-flex justify-content-between">
                  <label class="form-label" for="password">Password</label>
                </div>
                <div class="input-group input-group-merge">
                  <input type="password" id="password" class="form-control" name="password"
                    placeholder="************" required>
                  <span class="input-group-text" id="togglePassword"><i class="ti ti-eye-off"></i></span>
                </div>
              </div>

              <div class="mb-3">
                <button class="btn btn-secondary d-grid w-100" name="login" type="submit">Masuk</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    const icon = togglePassword.querySelector('i');

    togglePassword.addEventListener('click', () => {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      icon.classList.toggle('ti-eye');
      icon.classList.toggle('ti-eye-off');
    });
  </script>

  <!-- Required Js -->
  <script src="../assets/js/plugins/popper.min.js"></script>
  <script src="../assets/js/plugins/simplebar.min.js"></script>
  <script src="../assets/js/plugins/bootstrap.min.js"></script>
  <script src="../assets/js/fonts/custom-font.js"></script>
  <script src="../assets/js/pcoded.js"></script>
  <script src="../assets/js/plugins/feather.min.js"></script>





  <script>
    layout_change('light');
  </script>




  <script>
    change_box_container('false');
  </script>



  <script>
    layout_rtl_change('false');
  </script>


  <script>
    preset_change("preset-1");
  </script>


  <script>
    font_change("Public-Sans");
  </script>



</body>
<!-- [Body] end -->

</html>