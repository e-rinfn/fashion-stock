<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Cek apakah user sudah login
if (!isLoggedIn()) {
    header("Location: {$base_url}auth/login.php");
    exit;
}

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID bahan tidak valid";
    header("Location: list.php");
    exit();
}

$id_bahan = intval($_GET['id']);

// Cek apakah bahan ada di database
$bahan = query("SELECT * FROM bahan_baku WHERE id_bahan = $id_bahan");
if (empty($bahan)) {
    $_SESSION['error'] = "Bahan baku tidak ditemukan";
    header("Location: list.php");
    exit();
}

// Cek relasi dengan tabel lain
$cek_pengiriman = query("SELECT id_pengiriman_potong FROM pengiriman_pemotong WHERE id_bahan = $id_bahan LIMIT 1");

if ($cek_pengiriman || $cek_pembelian || $cek_penjualan) {
    $error_msg = "Bahan baku tidak dapat dihapus karena masih digunakan dalam: ";
    $reasons = [];

    if ($cek_pengiriman) $reasons[] = "pengiriman pemotong";
    if ($cek_pembelian) $reasons[] = "pembelian bahan";
    if ($cek_penjualan) $reasons[] = "penjualan bahan";

    $_SESSION['error'] = $error_msg . implode(", ", $reasons);
    header("Location: list.php");
    exit();
}

// Mulai transaksi
$conn->begin_transaction();

try {
    // Jika ada tabel relasi lain, tambahkan query DELETE di sini

    // Hapus bahan baku
    $delete_sql = "DELETE FROM bahan_baku WHERE id_bahan = $id_bahan";
    if (!$conn->query($delete_sql)) {
        throw new Exception("Gagal menghapus bahan baku: " . $conn->error);
    }

    $conn->commit();
    $_SESSION['success'] = "Bahan baku berhasil dihapus";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

header("Location: list.php");
exit();
