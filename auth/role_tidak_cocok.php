<?php
session_start();
require_once '../config/config.php'; // jika perlu base_url
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Akses Ditolak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .unauth-box {
            padding: 30px;
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="unauth-box">
        <h3 class="text-danger mb-3">Oops!, Akses Ditolak</h3>
        <p>Maaf, Role Anda tidak cocok untuk mengakses halaman ini.</p>
        <div class="mt-4">
            <a href="<?= $base_url ?>/<?php echo $_SESSION['role']; ?>/index.php" class="btn btn-primary">Kembali ke Beranda</a>
        </div>

    </div>
</body>

</html>