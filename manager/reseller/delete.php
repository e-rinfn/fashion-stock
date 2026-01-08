<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validasi ID
if ($id <= 0) {
    $_SESSION['error'] = "ID reseller tidak valid";
    header("Location: {$base_url}/manager/master_data.php");
    exit();
}

// Cek apakah reseller ada
$reseller = query("SELECT * FROM reseller WHERE id_reseller = $id");
if (empty($reseller)) {
    $_SESSION['error'] = "Data reseller tidak ditemukan";
    header("Location: {$base_url}/manager/master_data.php");
    exit();
}

// Cek apakah reseller memiliki transaksi
$cek_penjualan = query("SELECT 1 FROM penjualan WHERE id_reseller = $id LIMIT 1");
$cek_penjualan_bahan = query("SELECT 1 FROM penjualan_bahan WHERE id_reseller = $id LIMIT 1");

if ($cek_penjualan || $cek_penjualan_bahan) {
    $error_msg = "Reseller tidak dapat dihapus karena masih digunakan dalam: ";
    $reasons = [];

    if ($cek_penjualan) $reasons[] = "penjualan produk";
    if ($cek_penjualan_bahan) $reasons[] = "penjualan bahan";

    $_SESSION['error'] = $error_msg . implode(", ", $reasons);
    header("Location: {$base_url}/manager/master_data.php");
    exit();
}

$sql = "DELETE FROM reseller WHERE id_reseller = $id";
if ($conn->query($sql)) {
    $_SESSION['success'] = "Reseller berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus reseller: " . $conn->error;
}

header("Location: {$base_url}/manager/master_data.php");
exit();
