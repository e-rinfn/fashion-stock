<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID penjualan tidak ditemukan.";
    header("Location: list.php");
    exit;
}

$id = intval($_GET['id']);

// Ambil data penjualan_bahan
$penjualan_bahan = query("SELECT * FROM penjualan_bahan WHERE id_penjualan_bahan = $id");
if (!$penjualan_bahan || count($penjualan_bahan) === 0) {
    $_SESSION['error'] = "Data penjualan tidak ditemukan.";
    header("Location: list.php");
    exit;
}

// Ambil data pertama
$penjualan = $penjualan_bahan[0];

// Jika tidak ingin membatasi lunas/belum lunas, hapus pengecekan ini
// Kalau mau membatasi, aktifkan:
// if ($penjualan['status_pembayaran'] === 'lunas') {
//     $_SESSION['error'] = "Penjualan yang sudah lunas tidak dapat dibatalkan.";
//     header("Location: list.php");
//     exit;
// }

// Ambil detail penjualan bahan
$details = query("SELECT * FROM detail_penjualan_bahan WHERE id_penjualan_bahan = $id");

$conn->begin_transaction();

try {
    // Kembalikan stok produk
    foreach ($details as $d) {
        $id_produk = intval($d['id_produk']);
        $jumlah = intval($d['jumlah']);

        $sql_update = "UPDATE produk SET stok = stok + $jumlah WHERE id_produk = $id_produk";
        if (!$conn->query($sql_update)) {
            throw new Exception("Gagal mengembalikan stok produk ID $id_produk");
        }
    }

    // Hapus data cicilan terkait
    if (!$conn->query("DELETE FROM cicilan_penjualan_bahan WHERE id_penjualan_bahan = $id")) {
        throw new Exception("Gagal menghapus cicilan");
    }

    // Hapus detail penjualan
    if (!$conn->query("DELETE FROM detail_penjualan_bahan WHERE id_penjualan_bahan = $id")) {
        throw new Exception("Gagal menghapus detail penjualan");
    }

    // Hapus data penjualan
    if (!$conn->query("DELETE FROM penjualan_bahan WHERE id_penjualan_bahan = $id")) {
        throw new Exception("Gagal menghapus data penjualan");
    }

    $conn->commit();
    $_SESSION['success'] = "Penjualan berhasil dibatalkan.";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Gagal membatalkan penjualan: " . $e->getMessage();
}

header("Location: list.php");
exit;
