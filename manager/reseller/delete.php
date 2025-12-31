<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
// redirectIfNotLoggedIn();
// checkRole('admin');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Cek apakah reseller memiliki transaksi
$sql_check = "SELECT COUNT(*) as total FROM penjualan WHERE id_reseller = $id";
$result = $conn->query($sql_check);
$row = $result->fetch_assoc();

if ($row['total'] > 0) {
    $_SESSION['error'] = "Reseller tidak dapat dihapus karena memiliki riwayat penjualan";
    header("Location: list.php");
    exit();
}

$sql = "DELETE FROM reseller WHERE id_reseller = $id";
if ($conn->query($sql)) {
    $_SESSION['success'] = "Reseller berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus reseller: " . $conn->error;
}

header("Location: list.php");
exit();
