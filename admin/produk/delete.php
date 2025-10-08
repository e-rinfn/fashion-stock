<?php
require_once '../includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$id = $conn->real_escape_string($_GET['id']);

// Cek apakah produk pernah terjual
$checkSql = "SELECT COUNT(*) as total FROM detail_penjualan WHERE id_produk = '$id'";
$checkResult = $conn->query($checkSql);
$checkData = $checkResult->fetch_assoc();

if ($checkData['total'] > 0) {
    $_SESSION['error'] = "Produk tidak dapat dihapus karena sudah ada dalam transaksi penjualan";
    header("Location: list.php");
    exit();
}

$sql = "DELETE FROM produk WHERE id_produk = '$id'";

if ($conn->query($sql)) {
    $_SESSION['success'] = "Produk berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus produk: " . $conn->error;
}

header("Location: list.php");
exit();
