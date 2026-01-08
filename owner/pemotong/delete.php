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
    header("Location: list.php");
    exit();
}

$id_pemotong = intval($_GET['id']);

// Cek apakah pemotong ada di database
$pemotong = query("SELECT * FROM pemotong WHERE id_pemotong = $id_pemotong");
if (empty($pemotong)) {
    $_SESSION['error'] = "Data pemotong tidak ditemukan";
    header("Location: list.php");
    exit();
}

// Cek relasi dengan tabel lain
$cek_pengiriman = query("SELECT id_pengiriman_potong FROM pengiriman_pemotong WHERE id_pemotong = $id_pemotong LIMIT 1");

if ($cek_pengiriman) {
    $_SESSION['error'] = "Pemotong tidak dapat dihapus karena masih digunakan dalam data pengiriman!";
    header("Location: list.php");
    exit();
}

// Hapus pemotong
$sql = "DELETE FROM pemotong WHERE id_pemotong = $id_pemotong";

if ($conn->query($sql)) {
    $_SESSION['success'] = "Data pemotong berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus data pemotong: " . $conn->error;
}

header("Location: list.php");
exit();
