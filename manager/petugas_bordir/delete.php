<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID bordir tidak valid";
    header("Location: {$base_url}/manager/master_data.php");

    exit();
}

$id_bordir = intval($_GET['id']);

// Cek apakah bordir ada
$bordir = query("SELECT * FROM bordir WHERE id_bordir = $id_bordir");
if (empty($bordir)) {
    $_SESSION['error'] = "Data bordir tidak ditemukan";
    header("Location: {$base_url}/manager/master_data.php");

    exit();
}

// Cek relasi dengan tabel lain - sesuaikan dengan database Anda
$cek_pesanan = query("SELECT 1 FROM hasil_potong_fix WHERE id_bordir = $id_bordir LIMIT 1");

// Jika ada relasi, tidak boleh dihapus
if (!empty($cek_pesanan)) {
    $_SESSION['error'] = "Data bordir tidak dapat dihapus karena masih digunakan dalam proses lain";
    header("Location: {$base_url}/manager/master_data.php");

    exit();
}

// Hapus data
$sql = "DELETE FROM bordir WHERE id_bordir = $id_bordir";
if ($conn->query($sql)) {
    $_SESSION['success'] = "Data bordir berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus data bordir: " . $conn->error;
}

header("Location: {$base_url}/manager/master_data.php");

exit();
