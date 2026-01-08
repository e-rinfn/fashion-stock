<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Cek apakah user sudah login
if (!isLoggedIn()) {
    header("Location: {$base_url}auth/login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID pemotong tidak valid";
    header("Location: {$base_url}/owner/master_data.php");
    exit();
}

$id_pemotong = intval($_GET['id']);

// Cek apakah pemotong ada di database
$pemotong = query("SELECT * FROM pemotong WHERE id_pemotong = $id_pemotong");
if (empty($pemotong)) {
    $_SESSION['error'] = "Data pemotong tidak ditemukan";
    header("Location: {$base_url}/owner/master_data.php");
    exit();
}

// Cek relasi dengan tabel lain
$cek_pengiriman = query("SELECT 1 FROM pengiriman_pemotong WHERE id_pemotong = $id_pemotong LIMIT 1");
$cek_hasil_potong = query("SELECT 1 FROM hasil_potong_fix WHERE id_pemotong = $id_pemotong LIMIT 1");
$cek_hutang_upah = query("SELECT 1 FROM hutang_upah WHERE jenis_karyawan = 'pemotong' AND id_karyawan = $id_pemotong LIMIT 1");

if ($cek_pengiriman || $cek_hasil_potong || $cek_hutang_upah) {
    $error_msg = "Pemotong tidak dapat dihapus karena masih digunakan dalam: ";
    $reasons = [];

    if ($cek_pengiriman) $reasons[] = "pengiriman pemotong";
    if ($cek_hasil_potong) $reasons[] = "hasil pemotongan";
    if ($cek_hutang_upah) $reasons[] = "hutang upah";

    $_SESSION['error'] = $error_msg . implode(", ", $reasons);
    header("Location: {$base_url}/owner/master_data.php");
    exit();
}

// Hapus pemotong
$sql = "DELETE FROM pemotong WHERE id_pemotong = $id_pemotong";

if ($conn->query($sql)) {
    $_SESSION['success'] = "Data pemotong berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus data pemotong: " . $conn->error;
}

header("Location: {$base_url}/owner/master_data.php");
exit();
