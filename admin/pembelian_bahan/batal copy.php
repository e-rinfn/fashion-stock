<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../config/functions.php';

session_start();

// Validasi input
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID pembelian bahan tidak valid!";
    header("Location: list.php");
    exit;
}

$id = intval($_GET['id']);

// Mulai transaksi database
$conn->begin_transaction();

try {
    // 1. Dapatkan detail pembelian bahan untuk mengembalikan stok bahan baku
    $details = query("SELECT * FROM detail_pembelian_bahan WHERE id_pembelian_bahan = $id");

    foreach ($details as $d) {
        $id_bahan = intval($d['id_bahan']);
        $jumlah = intval($d['jumlah']);

        // Kembalikan stok bahan (dikurangi karena ini pembelian bahan baku)
        $sql_update = "UPDATE bahan_baku SET jumlah_stok = jumlah_stok - $jumlah WHERE id_bahan = $id_bahan";
        if (!$conn->query($sql_update)) {
            throw new Exception("Gagal mengembalikan stok bahan ID $id_bahan");
        }
    }

    // 2. Hapus pembelian bahan utama (akan otomatis hapus detail dan cicilan karena ON DELETE CASCADE)
    $sql_delete = "DELETE FROM pembelian_bahan WHERE id_pembelian_bahan = $id";
    if (!$conn->query($sql_delete)) {
        throw new Exception("Gagal menghapus data pembelian bahan");
    }

    // Commit transaksi jika semua berhasil
    $conn->commit();

    $_SESSION['success'] = "Pembelian bahan #$id berhasil dibatalkan dan stok bahan telah dikembalikan.";
} catch (Exception $e) {
    // Rollback jika ada error
    $conn->rollback();
    $_SESSION['error'] = "Gagal membatalkan pembelian bahan: " . $e->getMessage();

    // Tambahkan error MySQL jika ada
    if ($conn->errno) {
        $_SESSION['error'] .= " | MySQL Error: " . $conn->error;
    }
}

header("Location: list.php");
exit;
