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

// Cek apakah sudah ada pembayaran cicilan
$cicilan_exist = query("SELECT COUNT(*) as total, SUM(jumlah_cicilan) as total_bayar FROM cicilan_pembelian_bahan WHERE id_pembelian_bahan = $id");
$total_cicilan = $cicilan_exist[0]['total'] ?? 0;
$total_bayar = $cicilan_exist[0]['total_bayar'] ?? 0;

// Jika sudah ada pembayaran cicilan, tidak boleh dihapus
if ($total_cicilan > 0 && $total_bayar > 0) {
    $_SESSION['error'] = "Pembelian tidak dapat dibatalkan karena sudah ada pembayaran cicilan sebesar " . formatRupiah($total_bayar) . ". Silakan batalkan cicilan terlebih dahulu.";
    header("Location: list.php");
    exit;
}

// Mulai transaksi database
$conn->begin_transaction();

try {
    // 1. Dapatkan detail pembelian bahan untuk mengembalikan stok bahan baku
    $details = query("SELECT * FROM detail_pembelian_bahan WHERE id_pembelian_bahan = $id");

    foreach ($details as $d) {
        $id_bahan = intval($d['id_bahan']);
        $jumlah = intval($d['jumlah']);
        $meter = floatval($d['meter']);

        // Validasi: Pastikan meter ada di tabel detail
        if (!isset($d['meter'])) {
            throw new Exception("Kolom meter tidak ditemukan pada detail_pembelian_bahan. Pastikan tabel sudah diupdate.");
        }

        // Kembalikan stok bahan (dikurangi karena ini pembelian bahan baku)
        // Perbaikan: tambahkan pengurangan jumlah_meter
        $sql_update = "UPDATE bahan_baku 
                       SET jumlah_stok = jumlah_stok - $jumlah, 
                           jumlah_meter = jumlah_meter - $meter 
                       WHERE id_bahan = $id_bahan";

        if (!$conn->query($sql_update)) {
            throw new Exception("Gagal mengembalikan stok bahan ID $id_bahan");
        }

        // Cek apakah stok tidak menjadi negatif
        $check_stok = query("SELECT jumlah_stok, jumlah_meter FROM bahan_baku WHERE id_bahan = $id_bahan")[0];
        if ($check_stok['jumlah_stok'] < 0 || $check_stok['jumlah_meter'] < 0) {
            throw new Exception("Stok tidak mencukupi untuk pengembalian bahan ID $id_bahan");
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
