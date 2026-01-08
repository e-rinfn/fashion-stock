<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: {$base_url}auth/login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID supplier tidak valid";
    header("Location: {$base_url}/owner/master_data.php");
    exit();
}

$id_supplier = intval($_GET['id']);

// Check if supplier exists
$supplier = query("SELECT * FROM supplier WHERE id_supplier = $id_supplier");
if (empty($supplier)) {
    $_SESSION['error'] = "Supplier tidak ditemukan";
    header("Location: {$base_url}/owner/master_data.php");
    exit();
}

// Check relations
$cek_pembelian = query("SELECT 1 FROM pembelian WHERE id_supplier = $id_supplier LIMIT 1");
$cek_pembelian_bahan = query("SELECT 1 FROM pembelian_bahan WHERE id_supplier = $id_supplier LIMIT 1");

if ($cek_pembelian || $cek_pembelian_bahan) {
    $error_msg = "Supplier tidak dapat dihapus karena masih digunakan dalam: ";
    $reasons = [];

    if ($cek_pembelian) $reasons[] = "pembelian produk";
    if ($cek_pembelian_bahan) $reasons[] = "pembelian bahan";

    $_SESSION['error'] = $error_msg . implode(", ", $reasons);
    header("Location: {$base_url}/owner/master_data.php");
    exit();
}

// Delete supplier
if ($conn->query("DELETE FROM supplier WHERE id_supplier = $id_supplier")) {
    $_SESSION['success'] = "Supplier berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus supplier: " . $conn->error;
}

header("Location: {$base_url}/owner/master_data.php");
exit();
