<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../config/functions.php';

session_start();

// Validasi input
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID pembelian tidak valid!";
    header("Location: list.php");
    exit;
}

$id = intval($_GET['id']);

// Mulai transaksi database
$conn->begin_transaction();

try {
    // 1. Dapatkan detail pembelian untuk mengembalikan stok produk
    $details = query("SELECT * FROM detail_pembelian WHERE id_pembelian = $id");

    foreach ($details as $d) {
        $id_produk = intval($d['id_produk']);
        $jumlah = intval($d['jumlah']);

        // Kembalikan stok produk (dikurangi karena ini pembelian bahan baku)
        $sql_update = "UPDATE produk SET stok = stok - $jumlah WHERE id_produk = $id_produk";
        if (!$conn->query($sql_update)) {
            throw new Exception("Gagal mengembalikan stok produk ID $id_produk");
        }
    }

    // 2. Hapus pembelian utama (akan otomatis hapus detail dan cicilan karena ON DELETE CASCADE)
    $sql_delete = "DELETE FROM pembelian WHERE id_pembelian = $id";
    if (!$conn->query($sql_delete)) {
        throw new Exception("Gagal menghapus data pembelian");
    }

    // Commit transaksi jika semua berhasil
    $conn->commit();

    $_SESSION['success'] = "Pembelian #$id berhasil dibatalkan dan stok produk telah dikembalikan.";
} catch (Exception $e) {
    // Rollback jika ada error
    $conn->rollback();
    $_SESSION['error'] = "Gagal membatalkan pembelian: " . $e->getMessage();

    // Tambahkan error MySQL jika ada
    if ($conn->errno) {
        $_SESSION['error'] .= " | MySQL Error: " . $conn->error;
    }
}

header("Location: list.php");
exit;
