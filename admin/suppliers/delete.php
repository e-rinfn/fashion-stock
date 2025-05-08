<?php
require_once '../../config/auth.php';
require_role('admin');

require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

// Cek apakah supplier memiliki produk terkait
$stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
$stmt->execute([$id]);
$productCount = $stmt->fetchColumn();

if ($productCount > 0) {
    $_SESSION['error_message'] = "Supplier tidak dapat dihapus karena memiliki produk terkait";
    header('Location: index.php');
    exit;
}

// Hapus supplier
$stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['success'] = "Supplier berhasil dihapus";
header('Location: index.php');
exit;
