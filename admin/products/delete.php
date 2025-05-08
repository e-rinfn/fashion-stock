<?php
require_once '../../config/auth.php';
require_role('admin');

require_once '../../config/database.php';
require_once '../../functions/product_functions.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    $_SESSION['error'] = 'ID produk tidak valid!';
    header('Location: index.php');
    exit;
}

if (delete_product($id)) {
    $_SESSION['success'] = 'Produk berhasil dihapus!';
} else {
    $_SESSION['error'] = 'Gagal menghapus produk!';
}

header('Location: index.php');
exit;
